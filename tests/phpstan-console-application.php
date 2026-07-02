<?php

declare(strict_types=1);

use App\Kernel;
use Symfony\Bundle\FrameworkBundle\Console\Application;

require dirname(__DIR__).'/vendor/autoload.php';

$_SERVER['APP_ENV'] ??= $_ENV['APP_ENV'] ?? 'test';
$_SERVER['APP_DEBUG'] ??= $_ENV['APP_DEBUG'] ?? '1';
$_SERVER['APP_SECRET'] ??= $_ENV['APP_SECRET'] ?? 'phpstan_secret';
$_SERVER['DEFAULT_URI'] ??= $_ENV['DEFAULT_URI'] ?? 'http://127.0.0.1:8092';

$kernel = new Kernel((string) $_SERVER['APP_ENV'], (bool) $_SERVER['APP_DEBUG']);

return new Application($kernel);
