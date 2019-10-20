<?php

use App\Infura\Client as InfuraClient;
use Clue\React\Redis\Client as RedisClient;
use Clue\React\Redis\Factory as RedisFactory;
use DI\ContainerBuilder;
use Monolog\ErrorHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Ratchet\App as RatchetApp;
use React\EventLoop\Factory;
use React\EventLoop\LoopInterface;
use function DI\autowire;
use function DI\get;

$builder = new ContainerBuilder();

$builder->addDefinitions([
    // url подключения к infura
    'infura_host' => function() {
        $infura = getenv('APP_INFURA_HOST');
        return empty($infura) ? 'wss://mainnet.infura.io/ws' : $infura;
    },
    // транспорт для сообщений
    'redis_host' => function() {
        $redis = getenv('APP_REDIS_HOST');
        return empty($redis) ? 'redis://localhost:6379' : $redis;
    },
    // хост на котором работает ws сервер
    'http_host' => function() {
        $host = getenv('APP_HTTP_HOST');
        return empty($host) ? 'localhost' : $host;
    },
    // порт на котором работает ws сервер
    'http_port' => function() {
        $port = getenv('APP_HTTP_PORT');
        return empty($port) ? '8080' : $port;
    },
    // интерфейс на который биндим ws сервер
    'bind_address' => function() {
        $address = getenv('APP_BIND_ADDRESS');
        return empty($port) ? '0.0.0.0' : $port;
    },
    // singleton
    LoopInterface::class => function() {
        return Factory::create();
    },

    // Логгер
    LoggerInterface::class => function() {
        $logger =new Logger('main');

        $info = new StreamHandler("php://stdout", Logger::INFO, false);
        $error = new StreamHandler('php://stderr', Logger::ERROR, false);

        $logger->pushHandler($info);
        $logger->pushHandler($error);

        ErrorHandler::register($logger);

        return $logger;
    },

    // менеджер соединений infura
    InfuraClient::class => autowire()
        ->constructor(get('infura_host')),

    // Соединение к redis
    RedisClient::class => function(ContainerInterface $c, LoopInterface $loop) {
        return with(new RedisFactory($loop), function (RedisFactory $factory) use ($c) {
            return $factory->createLazyClient($c->get('redis_host'));
        });
    },

    RatchetApp::class => autowire()
        ->constructor(
            get('http_host'),
            get('http_port'),
            get('bind_address'),
            get(LoopInterface::class)
        ),

]);

return $builder->build();
