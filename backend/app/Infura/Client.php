<?php

namespace App\Infura;

use Ratchet\Client\WebSocket;
use React\EventLoop\LoopInterface;
use function Ratchet\Client\connect;
use function React\Promise\all;

/**
 * Клиент infura api
 * Class Client
 * @package App\Infura
 */
class Client
{
    /**
     * @var string
     */
    private $ws;

    /**
     * Список активных подключений к infura
     * @var array
     */
    private $connections = [];

    /**
     * @var LoopInterface
     */
    private $loop;

    /**
     * Client constructor.
     * @param string $ws
     * @param LoopInterface $loop
     */
    public function __construct(string $ws, LoopInterface $loop)
    {
        $this->ws = $ws;
        $this->loop = $loop;
    }

    /**
     * @param string $channel
     * @param MessageHandler $handler
     * @return \React\Promise\PromiseInterface
     */
    public function subscribeChannel(string $channel, MessageHandler $handler)
    {
        return $this->connection()
            ->then(function (Connection $connection) use ($channel, $handler) {
                return $connection->subscribe($channel)
                    ->then(function () use ($connection, $handler) {
                        $connection->listenMessages($handler);
                    });
            });
    }

    /**
     * Количество тразакций в блоке
     * @param array $block
     * @return \React\Promise\PromiseInterface
     */
    public function getTransactionCount(array $block)
    {
        return $this->connection()->then(function (Connection $connection) use ($block) {
            return $connection->getBlockTransactionCountByHash($block["params"]["result"]["hash"])
                ->then(function ($resp) {
                    $count = hexdec($resp["result"]);
                    return $count > 0 ? $count : 0;
                })
                ->always(function () use ($connection) {
                    $this->connectionClose($connection);
                });
        });
    }

    /**
     * Информация по отдельной транзакции
     * @param array $block
     * @param int $index
     * @return \React\Promise\PromiseInterface
     */
    public function getTransaction(array $block, int $index)
    {
        return $this->connection()->then(function (Connection $connection) use ($block, $index) {
            return $connection->getTransactionByBlockHashAndIndex(
                    $block["params"]["result"]["hash"],
                    sprintf("0x%s", dechex($index))
                )
                ->always(function () use ($connection) {
                    $this->connectionClose($connection);
                });
        });
    }

    /**
     * Закрываем все ws соединения к infura
     */
    public function close()
    {
        if (empty($this->connections)) {
            $this->loop->stop();
        } else {
            $all = [];
            /** @var Connection $connection */
            foreach ($this->connections as $connection) {
                array_push($all, $this->connectionClose($connection));
            }
            all($all)->then(function () {
                $this->loop->stop();
            });
        }
    }

    /**
     * Создние ws подключения к infura
     * @return \React\Promise\PromiseInterface<\App\Infura\Connection>
     */
    private function connection()
    {
        // подключаемся к infura
        return connect($this->ws, [], [], $this->loop)
            ->then(function(WebSocket $ws) {
                    return with(new Connection($ws, $this->loop), function (Connection $connection) {
                        $this->connections[$connection->id()] = $connection;
                        return $connection;
                    });
                },
                function ($error) {
                    throw $error;
                }
            );
    }

    /**
     * Закрытие одиночного соединения
     * @param Connection $connection
     * @return \React\Promise\PromiseInterface
     */
    private function connectionClose(Connection $connection)
    {
        return $connection->close()
            ->then(function () use ($connection) {
                if (($key = array_search($connection->id(), $this->connections)) !== false) {
                    unset($this->connections[$key]);
                }
            });
    }
}