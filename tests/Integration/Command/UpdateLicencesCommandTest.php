<?php
/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License version 3.0
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * @author    PrestaShop SA and Contributors <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */

namespace Tests\Integration\Command;

use PHPUnit\Framework\TestCase;
use PrestaShop\HeaderStamp\Command\UpdateLicensesCommand;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Tests\Integration\Tools\FolderComparator;

class UpdateLicencesCommandTest extends TestCase
{
    private static $workspaceFolder;
    private static $fs;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$workspaceFolder = __DIR__ . '/../../Resources/workspace/';
        self::cleanWorkspace();
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        self::cleanWorkspace();
    }

    protected static function cleanWorkspace(): void
    {
        // Remove and re-create folder to make it empty and existent
        self::$fs = new Filesystem();
        $finder = new Finder();
        foreach ($finder->directories()->depth(1)->in(self::$workspaceFolder) as $directory) {
            if (is_dir($directory->getRealPath())) {
                self::$fs->remove($directory->getRealPath());
            }
        }
    }

    /**
     * @dataProvider getFoldersToTest
     */
    public function testCommandModifications(string $folderToTest, bool $isFolderValid, array $invalidFiles = []): void
    {
        // Prepare module workspace
        $moduleSource = __DIR__ . '/../../Resources/module-samples/' . $folderToTest;
        $moduleExpected = __DIR__ . '/../../Resources/expected/' . $folderToTest;
        $moduleWorkspace = self::$workspaceFolder . $folderToTest;
        if (is_dir($moduleWorkspace)) {
            self::$fs->remove($moduleWorkspace);
        }
        self::$fs->mirror($moduleSource, $moduleWorkspace);

        // Execute command
        $application = new Application();
        $application->add(new UpdateLicensesCommand());
        $command = $application->find('prestashop:licenses:update');
        $commandTester = new CommandTester($command);
        $commandParameters = [
            '--license' => __DIR__ . '/../../../assets/afl.txt',
            '--target' => $moduleWorkspace,
            '--not-name' => '*.min.js',
            '--exclude' => 'ignoredFolder',
        ];
        if ('existing-headers-discrimination' === $folderToTest) {
            $commandParameters['--header-discrimination-string'] = 'friendsofpresta';
        }
        $commandTester->execute($commandParameters);

        // Compare folders
        $folderComparator = new FolderComparator();
        $check = $folderComparator->compareFolders($moduleExpected, $moduleWorkspace);
        $check2 = $folderComparator->compareFolders($moduleWorkspace, $moduleExpected);
        $this->assertEquals([], $check);
        $this->assertEquals([], $check2);

        // Check the corrections based on the expected status of the module (to make sure fixtures are not broken)
        $corrections = $folderComparator->compareFolders($moduleExpected, $moduleSource);
        sort($corrections);

        // Format expected messages based on file name list
        $invalidFiles = array_map(static function (string $fileName): string {
            return '/' . $fileName . ' has different md5';
        }, $invalidFiles);
        sort($invalidFiles);
        $this->assertEquals($invalidFiles, $corrections);

        // If all is good we can already clean the workspace folder
        self::$fs->remove($moduleWorkspace);
    }

    /**
     * @dataProvider getFoldersToTest
     */
    public function testCommandDryRun(string $folderToTest, bool $isFolderValid, array $invalidFiles = []): void
    {
        // Prepare module workspace
        $moduleSource = __DIR__ . '/../../Resources/module-samples/' . $folderToTest;
        $moduleWorkspace = self::$workspaceFolder . $folderToTest;
        if (is_dir($moduleWorkspace)) {
            self::$fs->remove($moduleWorkspace);
        }
        self::$fs->mirror($moduleSource, $moduleWorkspace);

        // Execute command
        $application = new Application();
        $application->add(new UpdateLicensesCommand());
        $command = $application->find('prestashop:licenses:update');
        $commandTester = new CommandTester($command);
        $commandParameters = [
            '--license' => __DIR__ . '/../../../assets/afl.txt',
            '--target' => $moduleWorkspace,
            '--not-name' => '*.min.js',
            '--exclude' => 'ignoredFolder',
            '--dry-run' => true,
        ];
        if ('existing-headers-discrimination' === $folderToTest) {
            $commandParameters['--header-discrimination-string'] = 'friendsofpresta';
        }
        $commandResult = $commandTester->execute($commandParameters);
        $this->assertEquals($isFolderValid ? 0 : 1, $commandResult);

        $commandOutput = $commandTester->getDisplay();
        if (!empty($invalidFiles)) {
            // First find the fixed files block
            $matches = [];
            $this->assertNotFalse(preg_match('/ Files with bad license headers:\n( \* [^\n]+\n)+/', $commandOutput, $matches));
            // Then extract the list from it
            $matches2 = [];
            preg_match_all('/( \* [^\n]+\n)/', $matches[0], $matches2);
            $this->assertEquals(count($matches2[0]), count($invalidFiles));
            $outputFiles = [];
            foreach ($matches2[0] as $file) {
                $outputFiles[] = str_replace(["\n", ' * '], '', $file);
            }
            sort($outputFiles);
            sort($invalidFiles);
            $this->assertEquals($outputFiles, $invalidFiles);
        } else {
            $this->assertFalse(str_contains($commandOutput, 'Files with bad license headers:'));
        }

        // If all is good we can already clean the workspace folder
        self::$fs->remove($moduleWorkspace);
    }

    public static function getFoldersToTest(): iterable
    {
        // Contains most of the files to be fixed:
        // - files without headers
        // - files with headers but not the proper license
        // - files that don't need to be modified
        // - files with old format of headers (mostly twig)
        // - an ignoredFolder that shouldn't be modified (passed as the argument)
        // - and ignoredFile (fake.min.js) that shouldn't be modified (passed as the argument)
        yield 'fakemodule, reference with invalid files of many types to validate them all' => [
            'fakemodule',
            false,
            [
                'FakeClassWithoutHeader.php',
                'FakeClassWithoutHeaderButComment.php',
                'views/css/app.css',
                'views/js/wrong_header.js',
                'views/js/app.js',
                'views/templates/app.tpl',
                'views/templates/old_v8_license_header.html.twig',
                'views/templates/no_license_header_but_comment.html.twig',
                'views/templates/invalid_v90_linter_format.html.twig',
                'views/templates/invalid_osl_header.html.twig',
                'views/templates/no_comment.html.twig',
                'views/templates/app.html.twig',
                'views/templates/missing-header.vue',
                'views/templates/wrong-osl-header.vue',
                'FakeClassWithWrongHeader.php',
                'composer.json',
            ],
        ];

        yield 'valid module gsitemap' => [
            'gsitemap',
            true,
        ];

        yield 'valid module dashproducts' => [
            'dashproducts',
            true,
        ];

        yield 'existing-headers-discrimination' => [
            'existing-headers-discrimination',
            false,
            [
                'existing-headers-discrimination.php',
            ],
        ];

        yield 'smart-headers' => [
            'smart-headers',
            false,
            [
                'emptynewlineheader.php',
            ],
        ];
    }
}
