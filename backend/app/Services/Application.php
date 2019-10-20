<?php

namespace App\Services;

use App\Infura\Client;
use App\Infura\MessageHandler;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;

/**
 * Класс для получения блоков/транзакций из infura
 * Class BlockManager
 * @package App\Services
 */
class Application implements MessageHandler
{
    /**
     * @var Client
     */
    private $client;
    /**
     * @var LoopInterface
     */
    private $loop;
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * PHP_INT_MAX;
     * @var int
     */
    private $maxTransactions = 10;
    /**
     * @var Transport
     */
    private $transport;

    /**
     * BlockManager constructor.
     * @param Client $client
     * @param LoopInterface $loop
     * @param LoggerInterface $logger
     * @param Transport $transport
     */
    public function __construct(Client $client, LoopInterface $loop, LoggerInterface $logger, Transport $transport)
    {
        $this->client = $client;
        $this->loop = $loop;
        $this->logger = $logger;
        $this->transport = $transport;
        $this->graceful();
    }

    /**
     * Получение блоков с infura
     */
    public function run()
    {
        $this->logger->info("infura parsing started");
        // Запускаем обработку новых блоков
        $this->client
            ->subscribeChannel("newHeads", $this)
            ->then(function () {
                // Запускаем цикл обработки
                $this->loop->run();
            });
    }

    /**
     * Обработка одного блоков
     * @param array $block
     */
    public function handle(array $block)
    {
        // обрабатываем блок
        $this->processBlock($block);

        // Если есть транзакции по блоку, забираем их
        $this->client->getTransactionCount($block)->then(function ($infuraCount) use ($block) {
            $this->logger->info(sprintf("Block: %s, Transaction count: %s", array_get($block, "params.result.hash"), $infuraCount));
            if ($infuraCount > 0) {
                // ограничиваем запросы на транзакции
                $count = $infuraCount > $this->maxTransactions ? $this->maxTransactions : $infuraCount;
                for ($i = 0; $i < $count; $i++) {
                    $this->client->getTransaction($block, $i)->then(function ($transaction) use ($block) {
                        $this->logger->info(sprintf("Transaction: %s, Block: %s, From: %s, To: %s",
                            array_get($transaction, "result.hash"),
                            array_get($transaction, "result.blockHash"),
                            array_get($transaction, "result.from"),
                            array_get($transaction, "result.to")
                        ));
                        $this->processTransaction($transaction, $block);
                    });
                }
            }
        });
    }

    /**
     * @param array $block
     */
    private function processBlock(array $block)
    {
        // отправляем в браузер через redis pub/sub
        $this->transport->publishBlock(array_get($block, "params.result", []));
    }

    /**
     * @param array $transaction
     * @param array $block
     */
    private function processTransaction(array $transaction, array $block)
    {
        // отправляем в браузер через redis pub/sub
        $this->transport->publishTransaction(array_get($transaction, "result", []));
    }

    /**
     * graceful завершение процесса
     */
    private function graceful()
    {
        $this->loop->addSignal(SIGINT, [$this, "signal"]);
        $this->loop->addSignal(SIGTERM, [$this, "signal"]);
    }

    /**
     * graceful завершение процесса
     * @param integer $signal
     */
    public function signal($signal)
    {
        $this->logger->info(sprintf("caught signal: %s", (string)$signal));
        $this->client->close();
    }
}