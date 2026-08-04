<?php
// /local/lib/RabbitMQConnector.php
require_once($_SERVER['DOCUMENT_ROOT'] . '/local/vendor/autoload.php');

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Exchange\AMQPExchangeType;

class RabbitMQConnector
{
    private static $instances = [];
    private $connection;
    private $channel;
    private $config;

    // Типы сообщений
    const TYPE_ORDER = 'order';
    const TYPE_PRODUCT = 'product';
    const TYPE_PRICE = 'price';
    const TYPE_CUSTOM = 'custom';
	const TYPE_VIDEO = 'video';
	const TYPE_RETAIL_CRM = 'retail_crm';
    const TYPE_PRICE_UPDATE = 'price_update';
	
    public function __construct(array $config = [])
    {
        if (!defined('RABBITMQ_PREFIX')) {
            throw new RuntimeException('RABBITMQ_PREFIX constant is not defined');
        }

        $this->config = array_merge([
            'host' => 'localhost',
            'port' => 5672,
            'user' => 'bitrix_user',
            'password' => 'WOwgsZB46GR2ibL',
            'vhost' => 'bitrix_sync',
            'exchange' => 'bitrix_exchange',
			'queue_prefix' => RABBITMQ_PREFIX
        ], $config);

        $this->connect();
    }
	// подтверждение
    public function ackMessage(int $deliveryTag): void
    {
		static $ackedTags = [];
		
		if (in_array($deliveryTag, $ackedTags)) {
			$this->logError("Duplicate ack attempt for tag: $deliveryTag");
			return;
		}
		
		$this->logMessage("Attempting to ack message", [
			'delivery_tag' => $deliveryTag,
			'channel_open' => $this->channel && $this->channel->is_open(),
			'connection_status' => $this->connection->isConnected()
		]);
        if (!$this->channel || !$this->channel->is_open()) {
            throw new RuntimeException('Channel is not open');
        }
        
        try {
            $this->channel->basic_ack($deliveryTag);
			$ackedTags[] = $deliveryTag;
            $this->logMessage("Message acknowledged (delivery_tag: $deliveryTag)");
        } catch (Exception $e) {
            //$this->logError("ACK failed for delivery_tag $deliveryTag: " . $e->getMessage());
			if (strpos($e->getMessage(), 'unknown delivery tag') !== false) {
				$this->logError("Stale delivery tag: $deliveryTag");
				return;
			}
            throw $e;
        }
    }
	
    public static function getInstance(array $config = []): self
    {
        $key = md5(serialize($config));
        if (!isset(self::$instances[$key])) {
            self::$instances[$key] = new self($config);
        }
        return self::$instances[$key];
    }

    private function connect(): void
    {
        try {
            $this->connection = new AMQPStreamConnection(
                $this->config['host'],
                $this->config['port'],
                $this->config['user'],
                $this->config['password'],
                $this->config['vhost']
            );

            $this->channel = $this->connection->channel();
            
            // Объявляем точку обмена
            $this->channel->exchange_declare(
                $this->config['exchange'],
                AMQPExchangeType::TOPIC,
                false,
                true,
                false
            );

            // Объявляем очереди для всех типов сообщений
            foreach ([self::TYPE_ORDER, self::TYPE_PRODUCT, self::TYPE_PRICE, self::TYPE_CUSTOM, self::TYPE_VIDEO, self::TYPE_RETAIL_CRM, self::TYPE_PRICE_UPDATE] as $type) {
                $queueName = $this->getQueueName($type);
                $routingKey = $this->getRoutingKey($type);
				
				$this->channel->queue_declare(
                    $queueName,
                    false,
                    true,
                    false,
                    false
                );

                // Привязываем к exchange с разными routing keys
                $this->channel->queue_bind(
                    $queueName,
                    $this->config['exchange'],
                    $routingKey
                );
            }

        } catch (Exception $e) {
            $this->logError('Connection error: ' . $e->getMessage());
            throw $e;
        }
    }

	// Очередь на текущем сайте (принимающая)
    /*public function getQueueName(string $messageType): string
    {
        return sprintf('%s_%s', $this->config['queue_prefix'], $messageType);
    }*/
    public function getQueueName(string $messageType): string
    {
        //return $this->config['queue_prefix'] . '_' . $messageType;
        $targetPrefix = ($this->config['queue_prefix'] === 'reciever') ? 'tempus' : 'reciever';
        return $targetPrefix . '_' . $messageType;
    }
    public function getOwnQueueName(string $messageType): string
    {
        return $this->config['queue_prefix'] . '_' . $messageType;
    }
	// Получаем routing key для СВОИХ сообщений
	public function getOwnRoutingKey(string $messageType): string
	{
		return sprintf('%s.%s.update', $this->config['queue_prefix'], $messageType);
	}
	public function getRoutingKey(string $messageType): string
	{
		// Определяем префикс куда отправляем
		$targetPrefix = ($this->config['queue_prefix'] === 'reciever') ? 'tempus' : 'reciever';
		return sprintf('%s.%s.update', $targetPrefix, $messageType);
	}
	
    public function send(string $messageType, array $data): bool
    {
		if (!in_array($messageType, [self::TYPE_ORDER, self::TYPE_PRODUCT, self::TYPE_PRICE, self::TYPE_CUSTOM, self::TYPE_VIDEO, self::TYPE_RETAIL_CRM, self::TYPE_PRICE_UPDATE])) {
			throw new InvalidArgumentException("Invalid message type");
		}
        try {
            $messageData = [
                'origin' => $this->config['queue_prefix'],
                'type' => $messageType,
                'sent_at' => date('Y-m-d H:i:s'),
                'data' => $data
            ];

            $message = new AMQPMessage(
                json_encode($messageData, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                ['delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT]
            );

            // Отправляем с routing key для другого сайта 
            //$foreignPrefix = ($this->config['queue_prefix'] === 'reciever') ? 'tempus' : 'reciever';
            //$routingKey = sprintf('%s.%s.update', $foreignPrefix, $messageType);
			$routingKey = $this->getRoutingKey($messageType);

            $this->channel->basic_publish(
                $message,
                $this->config['exchange'],
                $routingKey
            );

            $this->logMessage(sprintf(
                "Sent %s message to %s",
                $messageType,
                $routingKey
            ));
            
            return true;
        } catch (Exception $e) {
            $this->logError('Send error: ' . $e->getMessage());
            return false;
        }
    }

    public function consume1(string $messageType, callable $callback): void
    {
		$ar = [$messageType, $this->getOwnQueueName($messageType)];
		file_put_contents("/var/www/bitrix_logs/rabbitmq/orders.txt", print_r($ar, true), 8);
        try {
            $this->channel->basic_consume(
                $this->getOwnQueueName($messageType),
                '',
                false,
                false,
                false,
                false,
                function ($message) use ($callback) {
                    try {
                        $data = json_decode($message->body, true, 512, JSON_THROW_ON_ERROR);
                        
                        // Пропускаем свои же сообщения
                        if ($data['origin'] === $this->config['queue_prefix']) {
                            $message->ack();
                            return;
                        }
                        
                        call_user_func($callback, $data['data']);
                        $message->ack();
                    } catch (Exception $e) {
                        $this->logError('Process error: ' . $e->getMessage());
                        $message->nack();
                    }
                }
            );

            while ($this->channel->is_consuming()) {
                $this->channel->wait();
            }
        } catch (Exception $e) {
            $this->logError('Consume setup error: ' . $e->getMessage());
            throw $e;
        }
    }
	public function consume2(string $messageType, callable $callback, bool $autoAck = false): void
	{
		try {
			$this->channel->basic_consume(
				$this->getOwnQueueName($messageType),
				'',
				false,
				false,
				false,
				false,
				function ($message) use ($callback, $autoAck) {
					try {
						$data = json_decode($message->body, true, 512, JSON_THROW_ON_ERROR);
						
						if ($data['origin'] === $this->config['queue_prefix']) {
							$message->ack();
							return;
						}
						
						// Передаем и данные и объект сообщения
						call_user_func($callback, $data['data'], $message);
						
						if ($autoAck) {
							$message->ack();
						}
					} catch (Exception $e) {
						$this->logError('Process error: ' . $e->getMessage());
						$message->nack();
					}
				}
			);

			while ($this->channel->is_consuming()) {
				$this->channel->wait();
			}
		} catch (Exception $e) {
			$this->logError('Consume setup error: ' . $e->getMessage());
			throw $e;
		}
	}
	public function consume3(string $messageType, callable $callback, bool $autoAck = false): void
	{
		try {
			// Ограничиваем параллельную обработку
			$this->channel->basic_qos(null, 1, false);

			$this->channel->basic_consume(
				$this->getOwnQueueName($messageType),
				'',
				false, // no_local
				false, // no_ack
				false, // exclusive
				false, // nowait
				function ($message) use ($callback, $autoAck) {
					$startTime = microtime(true);
					$deliveryTag = $message->getDeliveryTag();
					
					try {
						$data = json_decode($message->body, true, 512, JSON_THROW_ON_ERROR);

						// Пропускаем свои сообщения
						if ($data['origin'] === $this->config['queue_prefix']) {
							$message->ack();
							return;
						}

						// Основная обработка
						$result = call_user_func($callback, $data['data'], $message);

						// Подтверждение только при успехе или autoAck
						if ($autoAck || $result !== false) {
							$message->ack();
							$this->logMessage(sprintf(
								"ACK message [%s] (%.3fs)",
								$deliveryTag,
								microtime(true) - $startTime
							));
						} else {
							$message->nack(true); // Повторная очередь
						}
					} catch (JsonException $e) {
						$this->logError("Invalid JSON: " . $e->getMessage());
						$message->nack(false); // Удалить некорректное сообщение
					} catch (Exception $e) {
						$this->logError(sprintf(
							"Processing failed [%s]: %s",
							$deliveryTag,
							$e->getMessage()
						));
						$message->nack(true); // Повторная очередь
					}
				}
			);

			// Бесконечный цикл с обработкой ошибок канала
			while ($this->channel->is_consuming()) {
				try {
					$this->channel->wait();
				} catch (PhpAmqpLib\Exception\AMQPTimeoutException $e) {
					// Таймаут - не критично, продолжаем
					continue;
				} catch (Exception $e) {
					$this->logError("Channel error: " . $e->getMessage());
					$this->reconnect(); // Метод восстановления соединения
				}
			}
		} catch (Exception $e) {
			$this->logError("Consume fatal error: " . $e->getMessage());
			$this->reconnect();
			throw $e;
		}
	}
    
	public function consume4(string $messageType, callable $callback, bool $autoAck = false): void
	{
		try {
			// Ограничиваем параллельную обработку
			$this->channel->basic_qos(null, 1, false);

			$this->channel->basic_consume(
				$this->getOwnQueueName($messageType),
				'',
				false, // no_local
				false, // no_ack
				false, // exclusive
				false, // nowait
				function ($message) use ($callback, $autoAck) {
					$startTime = microtime(true);
					$deliveryTag = $message->getDeliveryTag();
					
					try {
						$data = json_decode($message->body, true, 512, JSON_THROW_ON_ERROR);

						// Пропускаем свои сообщения
						if ($data['origin'] === $this->config['queue_prefix']) {
							$message->ack();
							return;
						}

						// восстановление соединения перед обработкой
						$this->ensureDbConnection();

						// основная обработка с повторной попыткой
						$result = $this->retryOnDbFailure(function() use ($callback, $data, $message) {
							return call_user_func($callback, $data['data'], $message);
						});

						// только при успехе или autoAck
						if ($autoAck || $result !== false) {
							$message->ack();
							$this->logMessage(sprintf(
								"ACK message [%s] (%.3fs)",
								$deliveryTag,
								microtime(true) - $startTime
							));
						} else {
							$message->nack(true); // повтор
							$this->logError("Message processing failed, requeued");
						}
					} catch (JsonException $e) {
						$this->logError("Invalid JSON: " . $e->getMessage());
						$message->nack(false); // удалить некорректное сообщение
					} catch (Exception $e) {
						$this->logError(sprintf(
							"Processing failed [%s]: %s",
							$deliveryTag,
							$e->getMessage()
						));
						$this->ensureDbConnection();
						$message->nack(true); // повторная очередь
					}
				}
			);

			// Бесконечный цикл с обработкой ошибок канала
			while ($this->channel->is_consuming()) {
				try {
					$this->channel->wait();
				} catch (PhpAmqpLib\Exception\AMQPTimeoutException $e) {
					// Таймаут - не критично, продолжаем
					continue;
				} catch (Exception $e) {
					$this->logError("Channel error: " . $e->getMessage());
					$this->reconnect(); // Метод восстановления соединения
					$this->ensureDbConnection(); // Восстанавливаем MySQL соединение
				}
			}
		} catch (Exception $e) {
			$this->logError("Consume fatal error: " . $e->getMessage());
			$this->reconnect();
			$this->ensureDbConnection();
			throw $e;
		}
	}
	public function consume(string $messageType, callable $callback, bool $autoAck = false): void
	{
		$lastCheckTime = 0;
		$checkInterval = 300; // 5 минут
		
		$this->channel->basic_qos(null, 1, false);
		
		$this->channel->basic_consume(
			$this->getOwnQueueName($messageType),
			'',
			false,
			false,
			false,
			false,
			function ($message) use ($callback, $autoAck, &$lastCheckTime, $checkInterval) {
				try {
					// Проверяем соединение перед обработкой сообщения
					$currentTime = time();
					if ($currentTime - $lastCheckTime > $checkInterval) {
						$this->ensureDbConnection();
						$lastCheckTime = $currentTime;
					}
					
					$data = json_decode($message->body, true, 512, JSON_THROW_ON_ERROR);
					
					if ($data['origin'] === $this->config['queue_prefix']) {
						$message->ack();
						return;
					}
					
					call_user_func($callback, $data['data'], $message);
					
					if ($autoAck) {
						$message->ack();
					}
				} catch (Exception $e) {
					$this->logError("Processing error: " . $e->getMessage());
					$message->nack(true); // Возвращаем в очередь
				}
			}
		);
		
		while ($this->channel->is_consuming()) {
			try {
				// Проверяем соединение периодически
				if (time() - $lastCheckTime > $checkInterval) {
					$this->ensureDbConnection();
					$lastCheckTime = time();
				}
				
				$this->channel->wait(null, false, $checkInterval);
			} catch (PhpAmqpLib\Exception\AMQPTimeoutException $e) {
				// Таймаут - нормальная ситуация
				continue;
			} catch (Exception $e) {
				$this->logError("Channel error: " . $e->getMessage());
				$this->reconnect();
				sleep(5);
			}
		}
	}
	/**
	 * восстанавливает соединение с БД
	 */
	protected function ensureDbConnection1(): void
	{
		try {
			$connection = \Bitrix\Main\Application::getInstance()->getConnection();
			if (!$connection->isConnected()) {
				$connection->connect();
				// Опционально: устанавливаем таймауты
				$connection->queryExecute("SET SESSION wait_timeout=28800");
				$connection->queryExecute("SET SESSION interactive_timeout=28800");
			}
		} catch (Exception $e) {
			$this->logError("DB connection error: " . $e->getMessage());
			throw $e;
		}
	}
	private function ensureDbConnection(): bool
	{
		$connection = \Bitrix\Main\Application::getInstance()->getConnection();
		
		try {
			// Проверяем соединение с помощью простого запроса
			if (!$connection->isConnected()) {
				$connection->connect();
				$this->logMessage("MySQL connection established");
				return true;
			}
			
			// Для проверки работоспособности существующего соединения выполняем простой запрос
			$connection->query("SELECT 1");
			return false;
			
		} catch (\Bitrix\Main\DB\ConnectionException $e) {
			$this->logError("MySQL connection check failed: " . $e->getMessage());
			
			// Пытаемся переподключиться
			try {
				$connection->disconnect();
				$connection->connect();
				$this->logMessage("MySQL connection reestablished after failure");
				return true;
			} catch (\Bitrix\Main\DB\ConnectionException $e) {
				$this->logError("MySQL reconnection failed: " . $e->getMessage());
				throw $e; // Пробрасываем исключение дальше
			}
		}
	}
	/**
	 * повторяет операции при ошибке соединения
	 */
	protected function retryOnDbFailure(callable $operation, int $maxRetries = 2)
	{
		$retryCount = 0;
		$lastException = null;
		
		while ($retryCount <= $maxRetries) {
			try {
				return $operation();
			} catch (\Bitrix\Main\DB\ConnectionException $e) {
				$lastException = $e;
				$retryCount++;
				$this->logError("DB error (attempt $retryCount): " . $e->getMessage());
				
				if ($retryCount <= $maxRetries) {
					$this->ensureDbConnection();
					sleep(1); // Задержка перед повторной попыткой
				}
			} catch (Exception $e) {
				throw $e;
			}
		}
		
		throw $lastException;
	}
	private function logMessage(string $message): void
    {
        //AddMessage2Log($message, 'rabbitmq_success.log');
        file_put_contents(
            '/home/bitrix/logs/rabbitmq/rabbitmq_success.log',
            date('Y-m-d H:i:s') . ' - ' . RABBITMQ_PREFIX . " - " . $message . PHP_EOL,
            FILE_APPEND
        );
    }

    private function logError(string $message): void
    {
        //AddMessage2Log($message, 'rabbitmq_error.log');
        file_put_contents(
            '/home/bitrix/logs/rabbitmq/rabbitmq_errors.log',
            date('Y-m-d H:i:s') . ' - ' . RABBITMQ_PREFIX . " - " . $message . PHP_EOL,
            FILE_APPEND
        );
    }

    public function close(): void
    {
        try {
            if ($this->channel && $this->channel->is_open()) {
                $this->channel->close();
            }
            if ($this->connection && $this->connection->isConnected()) {
                $this->connection->close();
            }
        } catch (Exception $e) {
            $this->logError('Close error: ' . $e->getMessage());
        }
    }

    public function __destruct()
    {
        $this->close();
    }
}