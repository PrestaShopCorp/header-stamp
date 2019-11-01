#!/usr/bin/env php
<?php

require __DIR__ . '/vendor/autoload.php';

use PrestaShop\PimpMyHeader\Command\UpdateLicensesCommand;
use Symfony\Component\Console\Application;

$application = new Application('pimp-my-header', '1.0.0');
$command = new UpdateLicensesCommand();

$application->add($command);

$application->setDefaultCommand($command->getName());
$application->run();
