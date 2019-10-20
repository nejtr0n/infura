<?php

namespace App\Infura;

use App\Exceptions\BadResponse;
use App\Exceptions\TimeoutExceed;
use Ratchet\Client\WebSocket;
use React\EventLoop\LoopInterface;
use React\Promise\Deferred;
use function React\Promise\all;

/**
 * Подключение к infura
 * Class Client
 * @internal
 * @package App\Infura
 */
class Connection
{
    /**
     * @var LoopInterface
     */
    private $loop;

    /**
     * @var WebSocket
     */
    private $conn;

    /**
     * @var array
     */
    private $subscriptions = [];

    /**
     * @var int
     */
    private $timeout = 15;

    /**
     * Connection constructor.
     * @param WebSocket $conn
     * @param LoopInterface $loop
     */
    public function __construct(WebSocket $conn, LoopInterface $loop)
    {
        $this->conn = $conn;
        $this->loop = $loop;
    }

    /**
     * Присоедиеняем обработчик сообщений
     * @param MessageHandler $handler
     */
    public function listenMessages(MessageHandler $handler)
    {
        $this->conn->on('message', function($msg) use ($handler) {
            $handler->handle($this->decode($msg));
        });
    }

    /**
     * Подписываемся на блоки
     * @param string $channel
     * @return \React\Promise\PromiseInterface
     */
    public function subscribe(string $channel)
    {
        return $this->send($this->subscribeMessage($channel))
            ->then(function ($response) {
                return with($this->decode($response), function (array $data) {
                    // Сохраянем id подписки
                    array_push($this->subscriptions, $data["result"]);
                    return $data;
                });
            }, function ($error) {
                throw $error;
            });
    }

    /**
     * Отписка от блоков
     * @param string $id
     * @return \React\Promise\PromiseInterface
     */
    public function unsubscribe(string $id)
    {
        return $this->send($this->unsubscribeMessage($id))
            ->then(function ($response) use ($id) {
                // Удаляем подписку из списка
                if (($key = array_search($id, $this->subscriptions)) !== false) {
                    unset($this->subscriptions[$key]);
                }
                return $this->decode($response);
            }, function ($error) {
                throw $error;
            });
    }

    /**
     * Количество транзакций по блоку
     * @param string $hash
     * @return \React\Promise\PromiseInterface
     */
    public function getBlockTransactionCountByHash(string $hash)
    {
        return $this->send($this->getBlockTransactionCountByHashMessage($hash))
            ->then(function ($response) {
                return $this->decode($response);
            }, function ($error) {
                throw $error;
            });
    }

    /**
     * Получение транзакции
     * @param string $hash
     * @param string $index
     * @return \React\Promise\PromiseInterface
     */
    public function getTransactionByBlockHashAndIndex(string $hash, string $index)
    {
        return $this->send($this->getTransactionByBlockHashAndIndexMessage($hash, $index))
            ->then(function ($response) {
                return $this->decode($response);
            }, function ($error) {
               throw $error;
            });
    }



    /**
     * Отправка ws запрса
     * @param string $message
     * @return \React\Promise\PromiseInterface
     */
    private function send(string $message)
    {
        // ожидание ответа через promise
        $deferred = new Deferred();
        $promise = $deferred->promise();

        // запрос
        $this->conn->send($message);

        // таймер на ошибку при отсутствии ответа в течении таймаута
        $onError = $this->loop->addTimer($this->timeout, function () use ($deferred) {
            $deferred->reject(new TimeoutExceed());
        });

        // обработка единичного ответа
        $this->conn->once('message', function($msg) use ($deferred, $onError) {
            // успешный ответ
            if (!empty($msg)) {
                $this->loop->cancelTimer($onError);
                $deferred->resolve($msg);
            } else {
                $deferred->reject(new BadResponse());
            }
        });

        return $promise;
    }


    /**
     * Закрытие соединения
     * @return \React\Promise\PromiseInterface
     */
    public function close()
    {
        // ожидание ответа через promise
        $deferred = new Deferred();
        $promise = $deferred->promise();

        // выключаем обработку сообщений
        $this->conn->removeAllListeners();

        if (!empty($this->subscriptions)) {
            $all = [];
            foreach ($this->subscriptions as $subscription) {
                array_push($all, $this->unsubscribe($subscription));
            }
            all($all)->then(function () use ($deferred) {
                $this->conn->close();
                $deferred->resolve();
            });
        } else {
            $this->conn->close();
            $deferred->resolve();
        }

        return $promise;
    }

    /**
     * Уникальный хеш соедиенения
     * @return string
     */
    public function id()
    {
        return spl_object_hash($this);
    }

    /**
     * Шаблон сообщения на подписку
     * @param string $type
     * @return string
     */
    private function subscribeMessage(string $type)
    {
        return sprintf('{"id": 1, "method": "eth_subscribe", "params": ["%s", {}]}', $type);
    }

    /**
     * Шаблон сообщения на отписку
     * @param string $id
     * @return string
     */
    private function unsubscribeMessage(string $id)
    {
        return sprintf('{"id": 1, "method": "eth_unsubscribe", "params": ["%s"]}', $id);
    }

    /**
     * Шаблон сообщения на количество транзакций по хешу
     * @param string $hash
     * @return string
     */
    private function getBlockTransactionCountByHashMessage(string $hash)
    {
        return sprintf('{"jsonrpc":"2.0","method":"eth_getBlockTransactionCountByHash","params": ["%s"],"id":1}', $hash);
    }

    /**
     * Шаблон сообщения на транзакция по хешу
     * @param string $hash
     * @param string $index
     * @return string
     */
    private function getTransactionByBlockHashAndIndexMessage(string $hash, string $index)
    {
        return sprintf('{"jsonrpc":"2.0","method":"eth_getTransactionByBlockHashAndIndex","params": ["%s","%s"],"id":1}', $hash, $index);
    }

    /**
     * Декодируем ответ от infura в массив
     * @param $msg
     * @return mixed
     */
    private function decode($msg)
    {
        return json_decode((string) $msg, true);
    }
}