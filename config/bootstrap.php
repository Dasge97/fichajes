<?php

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__) . '/vendor/autoload.php';

if (!isset($_SERVER['APP_ENV']) && !isset($_ENV['APP_ENV'])) {
    if (!class_exists(Dotenv::class)) {
        throw new RuntimeException('Instala symfony/dotenv para cargar variables de entorno.');
    }

    (new Dotenv())->bootEnv(dirname(__DIR__) . '/.env');
}
