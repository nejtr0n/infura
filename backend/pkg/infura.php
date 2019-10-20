<?php

use App\Services\Application;
use Psr\Container\ContainerInterface;

require __DIR__ . '/../vendor/autoload.php';

/** @var ContainerInterface $container */
$container = require __DIR__ . '/../bootstrap/container.php';

/** @var Application $manager */
$manager = $container->get(Application::class);

$manager->run();