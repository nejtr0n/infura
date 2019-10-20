<?php

namespace App\Services;

use Psr\Log\LoggerInterface;
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

/**
 * Класс для отправки блоков/транзакций через ratchet в браузер
 * Class Server
 * @package App\Services
 */
class Server implements MessageComponentInterface, SenderContract {
    /**
     * @var \SplObjectStorage
     */
    protected $clients;
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * Server constructor.
     * @param Transport $transport
     * @param LoggerInterface $logger
     */
    public function __construct(Transport $transport, LoggerInterface $logger) {
        $this->clients = new \SplObjectStorage;
        $this->logger = $logger;
        $transport->subscribe($this, Transport::BLOCK_CHANNEL, Transport::TRANSACTIONS_CHANNEL);

    }

    /**
     * Отправка сообщения из каналов в браузер
     * @param string $channel
     * @param array $data
     */
    public function send(array $data)
    {
        foreach ($this->clients as $client) {
            $client->send(json_encode($data));
        }
    }

    public function onOpen(ConnectionInterface $conn)
    {
        $this->clients->attach($conn);
        $this->logger->info(sprintf("new connection from %s", $conn->resourceId));
    }

    public function onMessage(ConnectionInterface $from, $msg)
    {

    }

    public function onClose(ConnectionInterface $conn)
    {
        $this->clients->detach($conn);
        $this->logger->info(sprintf("connection %s has disconnected", $conn->resourceId));
    }

    public function onError(ConnectionInterface $conn, \Exception $e)
    {
        $conn->close();
    }
}