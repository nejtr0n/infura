<?php

use App\Services\Server;
use Psr\Container\ContainerInterface;
use Ratchet\App as RatchetApp;

require __DIR__ . '/../vendor/autoload.php';

/** @var ContainerInterface $container */
$container = require __DIR__ . '/../bootstrap/container.php';

/** @var RatchetApp $app */
$app = $container->get(RatchetApp::class);

/** @var Server $server */
$server = $container->get(Server::class);

$app->route('/infura', $server, array('*'));

$app->run();