#!/usr/bin/env php

<?php

require __DIR__.'/vendor/autoload.php';

use Dotenv\Dotenv;
use App\DeployCommand;
use Symfony\Component\Console\Application;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

$app = new Application('Laravel App Deployer', '1.0');

$app->add(new DeployCommand());

$app->run();
