<?php

namespace App\Services;

use Clue\React\Redis\Client;

/**
 * Транспорт для доставки сообщений
 * Class Transport
 * @package App\Services
 */
class Transport
{
    public const BLOCK_CHANNEL = "blocks";
    public const TRANSACTIONS_CHANNEL = "transactions";

    /**
     * @var Client
     */
    private $client;

    /**
     * Transport constructor.
     * @param Client $client
     */
    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    /**
     * @param array $data
     */
    public function publishBlock(array $data)
    {
        $this->publish(self::BLOCK_CHANNEL, $data);
    }

    /**
     * @param array $data
     */
    public function publishTransaction(array $data)
    {
        $this->publish(self::TRANSACTIONS_CHANNEL, $data);
    }

    /**
     * Публикация сообщения в канал
     * @param $channel
     * @param $message
     */
    private function publish($channel, $message)
    {
        $this->client->publish($channel, $this->encode($message));
    }

    /**
     * Подписка на канал
     * @param SenderContract $sender
     * @param mixed ...$channels
     */
    public function subscribe(SenderContract $sender, ...$channels)
    {
        foreach ($channels as $channel) {
            $this->client->subscribe($channel);
        }

        $this->client->on('message', function ($channel, $payload) use ($sender) {
            // pub/sub message received on given $channel
            $sender->send(["channel" => $channel, "data" => $this->decode($payload)]);
        });
    }

    private function encode(array $data)
    {
        return json_encode($data);
    }

    private function decode(string $data)
    {
        return json_decode($data, true);
    }
}