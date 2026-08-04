<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();
require_once($_SERVER['DOCUMENT_ROOT'] . '/local/vendor/autoload.php');

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Exception\AMQPProtocolChannelException;

class RabbitMQStatusComponent extends CBitrixComponent
{
    const QUEUES = [
        'tempus_order' => 'Заказы с tempusshop',
        'reciever_order' => 'Заказы с tempus',
        'tempus_product' => 'Товары с tempusshop',
        'tempus_price' => 'Цены с tempusshop'
    ];

    public function onPrepareComponentParams($arParams)
    {
        $arParams['HOST'] = $arParams['HOST'] ?? 'localhost';
        $arParams['PORT'] = $arParams['PORT'] ?? 5672;
        $arParams['USER'] = $arParams['USER'] ?? 'guest';
        $arParams['PASSWORD'] = $arParams['PASSWORD'] ?? 'guest';
        $arParams['VHOST'] = $arParams['VHOST'] ?? '/';
        $arParams['TIMEOUT'] = $arParams['TIMEOUT'] ?? 2;
        
        return $arParams;
    }

    public function executeComponent()
    {
        $this->arResult['CONNECTION_STATUS'] = 'OK';
        $this->arResult['QUEUES'] = $this->checkAllQueues();
        $this->includeComponentTemplate();
    }

    private function checkAllQueues(): array
    {
        $result = [];
        
        foreach (self::QUEUES as $queueName => $name) {
            $result[$name] = $this->checkQueueWithReconnect($queueName);
        }

        return $result;
    }

    private function checkQueueWithReconnect(string $queueName): array
    {
        $connection = null;
        $channel = null;

        try {
            // Создаем новое соединение для каждой очереди
            $connection = new AMQPStreamConnection(
                $this->arParams['HOST'],
                $this->arParams['PORT'],
                $this->arParams['USER'],
                $this->arParams['PASSWORD'],
                $this->arParams['VHOST'],
                false,
                'AMQPLAIN',
                null,
                'en_US',
                $this->arParams['TIMEOUT'],
                $this->arParams['TIMEOUT']
            );

            $channel = $connection->channel();

            // Проверяем очередь
            list(, $messageCount, $consumerCount) = $channel->queue_declare(
                $queueName,
                true,  // passive
                false, // durable
                false, // exclusive
                false  // auto_delete
            );

            $deadLetters = $this->getDeadLettersCount($channel, $queueName);
			//$lastActivity = $this->getLastActivityTime($channel, $queueName);
			
            return [
                'STATUS' => 'UP',
                'MESSAGES' => $messageCount,
                'CONSUMERS' => $consumerCount,
                'DEAD_LETTERS' => $deadLetters,
                'LAST_ACTIVITY' => $lastActivity
            ];
        } catch (AMQPProtocolChannelException $e) {
            if (strpos($e->getMessage(), 'NOT_FOUND') !== false) {
                return [
                    'STATUS' => 'NOT_FOUND',
                    'MESSAGES' => 0,
                    'CONSUMERS' => 0,
                    'DEAD_LETTERS' => 0
                ];
            }
            return [
                'STATUS' => 'ERROR',
                'ERROR' => $e->getMessage()
            ];
        } catch (Exception $e) {
            return [
                'STATUS' => 'ERROR',
                'ERROR' => $e->getMessage()
            ];
        } finally {
            // Всегда закрываем соединение
            try {
                if ($channel) {
                    $channel->close();
                }
            } catch (Exception $e) {}

            try {
                if ($connection) {
                    $connection->close();
                }
            } catch (Exception $e) {}
        }
    }

    private function getDeadLettersCount($channel, string $queueName): int
    {
        $dlxQueueName = 'dead_letter.' . $queueName;
        
        try {
            list(, $messageCount) = $channel->queue_declare($dlxQueueName, true);
            return $messageCount;
        } catch (Exception $e) {
            return 0;
        }
    }
	/*
	private function getLastActivityTime($channel, string $queueName): string
	{
		try {
			// Используем HTTP API RabbitMQ Management
			$apiUrl = sprintf(
				'http://%s:15672/api/queues/%s/%s',
				$this->arParams['HOST'],
				urlencode($this->arParams['VHOST']),
				urlencode($queueName)
			);

			$ch = curl_init();
			curl_setopt_array($ch, [
				CURLOPT_URL => $apiUrl,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_USERPWD => $this->arParams['USER'] . ':' . $this->arParams['PASSWORD'],
				CURLOPT_TIMEOUT => 2,
			]);

			$response = curl_exec($ch);
			$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			curl_close($ch);

			if ($httpCode === 200) {
				$data = json_decode($response, true);
				
				// Возвращаем время последней активности
				if (isset($data['idle_since'])) {
					return date('Y-m-d H:i:s', strtotime($data['idle_since']));
				}
				
				// Или время последнего опубликованного сообщения
				if (isset($data['message_stats']['publish_details']['rate'])) {
					return 'Активна (сообщения: '.$data['messages'].')';
				}
			}

			return 'Нет данных';
		} catch (Exception $e) {
			return 'Ошибка: '.$e->getMessage();
		}
	}*/
}