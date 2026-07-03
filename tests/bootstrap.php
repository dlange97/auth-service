<?php

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__) . '/vendor/autoload.php';

if (method_exists(Dotenv::class, 'bootEnv')) {
    $projectDir = dirname(__DIR__);
    $dotenv = new Dotenv();
    $defaultEnv = $projectDir . '/.env';
    $testEnv = $projectDir . '/.env.test';

    if (is_file($defaultEnv)) {
        $dotenv->bootEnv($defaultEnv);
    } elseif (is_file($testEnv)) {
        $dotenv->bootEnv($testEnv);
    } else {
        $_SERVER['APP_ENV'] = $_SERVER['APP_ENV'] ?? $_ENV['APP_ENV'] ?? 'test';
        $_SERVER['APP_DEBUG'] = $_SERVER['APP_DEBUG'] ?? $_ENV['APP_DEBUG'] ?? '1';
    }
}

if (!empty($_SERVER['APP_DEBUG'])) {
    umask(0000);
}
