<?php
/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/OSL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to https://devdocs.prestashop.com/ for more information.
 *
 * @author    PrestaShop SA and Contributors <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
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
    public function testCommandResult(string $folderToTest, bool $isFolderValid, array $invalidFiles = []): void
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
        $this->assertEquals($invalidFiles, $corrections);

        // If all is good we can already clean the workspace folder
        self::$fs->remove($moduleWorkspace);
    }

    /**
     * @dataProvider getFoldersToTest
     */
    public function testCommandDryRun(string $folderToTest, bool $isFolderValid): void
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

        // If all is good we can already clean the workspace folder
        self::$fs->remove($moduleWorkspace);
    }

    public static function getFoldersToTest(): iterable
    {
        yield 'fakemodule, reference with invalid files of many types to validate them all' => [
            'fakemodule',
            false,
            []
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
                '/existing-headers-discrimination.php has different md5',
            ],
        ];

        yield 'smart-headers' => [
            'smart-headers',
            false,
            [
                '/emptynewlineheader.php has different md5',
            ],
        ];
    }
}
