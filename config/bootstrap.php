<?php

declare(strict_types=1);

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

if (!isset($_SERVER['APP_ENV'])) {
    if (!class_exists(Dotenv::class)) {
        throw new LogicException('You need to add "symfony/dotenv" as a dependency to load environment variables from a .env file.');
    }
    (new Dotenv())->usePutenv(true)->bootEnv(dirname(__DIR__).'/.env');
}
