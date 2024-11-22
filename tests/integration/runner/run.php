#!/usr/bin/env php
<?php

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/util.php';
require_once __DIR__ . '/FolderComparator.php';

use PrestaShop\HeaderStamp\Command\UpdateLicensesCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Filesystem\Filesystem;

$modulesToTest = [
    'gsitemap',
    'dashproducts',
    'fakemodule',
    'existing-headers-discrimination',
    'smart-headers',
];
$workspaceID = 100;
$filesystem = new Filesystem();
$folderComparator = new FolderComparator();

$application = buildTestApplication();

// Test modifications based on expected results
foreach ($modulesToTest as $moduleName) {
    ++$workspaceID;
    $moduleFolderpath = __DIR__ . '/../module-samples/' . $moduleName;
    $expectedModuleFolderpath = __DIR__ . '/../expected/' . $moduleName;
    $workspaceFolderpath = __DIR__ . '/../workspace/' . $workspaceID;

    // copy module into workspace
    $filesystem->remove($workspaceFolderpath);
    $filesystem->mirror($moduleFolderpath, $workspaceFolderpath);

    // run UpdateLicensesCommand on workspace
    $commandParameters = ['command' => 'prestashop:licenses:update',
                          '--license' => __DIR__ . '/../../../assets/afl.txt',
                          '--target' => $workspaceFolderpath,
                          '--not-name' => '*.min.js',
                          '--exclude' => 'ignoredFolder',
    ];

    if ('existing-headers-discrimination' === $moduleName) {
        $commandParameters['--header-discrimination-string'] = 'friendsofpresta';
    }

    $input = new ArrayInput(
        $commandParameters
    );

    $output = new BufferedOutput();
    $application->run($input, $output);

    // compare workspace with expected
    $check = $folderComparator->compareFolders($expectedModuleFolderpath, $workspaceFolderpath, '');
    $check2 = $folderComparator->compareFolders($workspaceFolderpath, $expectedModuleFolderpath, '');

    // empty workspace
    $filesystem->remove($workspaceFolderpath);

    if (!empty($check)) {
        printErrorsList($moduleName, $check);

        exit(1);
    }
    if (!empty($check2)) {
        printErrorsList($moduleName, $check2);

        exit(1);
    }

    printSuccessMessage(' - module ' . $moduleName . ' processed successfully' . PHP_EOL);
}

// Test dry run option
$modulesToValidate = [
    'gsitemap' => 0,
    'dashproducts' => 0,
    'fakemodule' => 1,
    'existing-headers-discrimination' => 1,
    'smart-headers' => 1,
];

foreach ($modulesToValidate as $moduleName => $expectedResult) {
    ++$workspaceID;
    $moduleFolderpath = __DIR__ . '/../module-samples/' . $moduleName;
    $workspaceFolderpath = __DIR__ . '/../workspace/' . $workspaceID;

    // copy module into workspace
    $filesystem->remove($workspaceFolderpath);
    $filesystem->mirror($moduleFolderpath, $workspaceFolderpath);

    // run UpdateLicensesCommand on workspace
    $commandParameters = ['command' => 'prestashop:licenses:update',
        '--license' => __DIR__ . '/../../../assets/afl.txt',
        '--target' => $workspaceFolderpath,
        '--not-name' => '*.min.js',
        '--exclude' => 'ignoredFolder',
        // Dry run option doesn't perform modification, it returns 1 in case errors were detected, 0 if no errors were found
        '--dry-run' => true,
    ];

    if ('existing-headers-discrimination' === $moduleName) {
        $commandParameters['--header-discrimination-string'] = 'friendsofpresta';
    }

    $input = new ArrayInput(
        $commandParameters
    );

    $output = new BufferedOutput();
    $result = $application->run($input, $output);

    // empty workspace
    $filesystem->remove($workspaceFolderpath);

    if ($result !== $expectedResult) {
        if ($expectedResult === 0) {
            printErrorMessage(' - module ' . $moduleName . ' validation failed, the module should not contain errors' . PHP_EOL);
        } else {
            printErrorMessage(' - module ' . $moduleName . ' validation failed, some errors were expected to be found' . PHP_EOL);
        }

        exit(1);
    }

    printSuccessMessage(' - module ' . $moduleName . ' validated as expected' . PHP_EOL);
}

printSuccessMessage('Integration tests run successfully' . PHP_EOL);

exit(0);
