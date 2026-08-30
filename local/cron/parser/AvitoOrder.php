<?php
$_SERVER['DOCUMENT_ROOT'] = '/var/www/bitrix/data/www/tempusshop.ru';
define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';
$workers = new WorkersChecker("local_cron_parser_AvitoOrder_php");
if (!$workers->checkStatus()) {
	exit;
}
$workers->updateStatus("Y");

use Bitrix\Main\Loader;
use Bitrix\Sale\Order;
use Bitrix\Sale\Basket;
use Bitrix\Main\Context;

class AvitoOrder
{
    private $watchDir;
    private $processedDir;
    private $errorDir;
    private $logger;
    
    private $defaultUserId = 197524;
    private $siteId = 's1';
    private $tradingPlatformId = 22;
    private $statusCollect = 'CL';
    private $statusCancel = 'no';
    private $catalogIblockId = 16;
    private $defaultProductId = 135080;
    
    private $stats = [
        'files_processed' => 0,
        'orders_created' => 0,
        'orders_canceled' => 0,
        'orders_updated' => 0,
        'orders_skip' => 0,
        'orders_skip_updated' => 0,
        'errors' => 0
    ];

    public function __construct()
    {
        $this->watchDir = $_SERVER['DOCUMENT_ROOT'] . '/upload/zennolab/avito';
        $this->processedDir = $_SERVER['DOCUMENT_ROOT'] . '/upload/zennolab/avito/processed';
        $this->errorDir = $_SERVER['DOCUMENT_ROOT'] . '/upload/zennolab/avito/error';
        $this->logger = new \TsLogger("/" . __CLASS__ . "/");
		$this->triggers = new \TsTriggers();
        
        $this->createDirectories();
        $this->loadModules();
    }
    
    private function createDirectories()
    {
        foreach ([$this->processedDir, $this->errorDir] as $dir) {
            if (!is_dir($dir)) {
                if (mkdir($dir, 0755, true)) {
                    $this->logger->log("LOG", "Создана директория: {$dir}");
                } else {
                    $this->logger->log("ERROR", "Не удалось создать директорию: {$dir}");
                }
            }
        }
    }

    private function loadModules()
    {
        $requiredModules = ['sale', 'catalog', 'iblock'];
        
        foreach ($requiredModules as $module) {
            if (!Loader::includeModule($module)) {
                $this->logger->log("ERROR", "Не удалось загрузить модуль: {$module}");
                throw new \Exception("Module {$module} not loaded");
            }
        }
        
        $this->logger->log("LOG", "Модули Битрикс загружены");
    }

	public function checkLastFile() {
		if (!is_dir($this->processedDir)) {
			return;
		}
		
		$files = glob($this->processedDir . '/*.csv');
		
		if (empty($files)) {
			return;
		}
		
		$latestFile = null;
		$latestTime = 0;
		
		foreach ($files as $file) {
			$fileTime = filemtime($file);
			if ($fileTime > $latestTime) {
				$latestTime = $fileTime;
				$latestFile = $file;
			}
		}
		
		$currentTime = time();
		$timeDiff = $currentTime - $latestTime;
		$hoursDiff = $timeDiff / 3600;
		$fileName = basename($latestFile);
		
		if ($hoursDiff >= 2) {
			$message = sprintf(
				"Последний файл '%s' был создан %d часов и %d минут назад (больше 2 часов)!",
				$fileName,
				floor($hoursDiff),
				floor(($timeDiff % 3600) / 60)
			);
			
			$this->logger->log("LOG", "Старый файл. " . $message);
			
			$this->triggers->SetError(["Avito. " . $message]);
			$this->triggers->SendTriggerErrors();
		}
	}

    public function run()
    {
        $this->logger->log("LOG", "=== Запуск обработчика AvitoOrder ===");
        try {
            $files = $this->getCsvFiles();
            
            if (empty($files)) {
                $this->logger->log("LOG", "Нет CSV файлов для обработки");
                $this->printStats();
                return;
            }
            
            $this->logger->log("LOG", "Найдено файлов для обработки: " . count($files));
            
            foreach ($files as $file) {
                $this->processFile($file);
            }
            
            $this->printStats();
            $this->logger->log("LOG", "=== Обработка завершена ===");
            
        } catch (\Exception $e) {
            $this->logger->log("ERROR", "Критическая ошибка: " . $e->getMessage(), $e);
        }
    }

    private function getCsvFiles()
    {
        //return glob($this->watchDir . '/*.csv');
		$files = glob($this->watchDir . '/*.csv');
		if (empty($files)) {
			return [];
		}
		
		$times = array_map('filectime', $files);
		
		array_multisort($times, $oldestFirst ? SORT_ASC : SORT_DESC, $files);
		return $files;
    }

    private function processFile($filePath)
    {
        $fileName = basename($filePath);
        $this->logger->log("LOG", "Обработка файла: {$fileName}");
        
        $rows = $this->parseCsvFile($filePath);
        
        if (empty($rows)) {
            $this->logger->log("LOG", "Файл {$fileName} не содержит данных");
            $this->moveFileToProcessed($filePath);
            return;
        }
        
        $this->logger->log("LOG", "Получено записей из файла: " . count($rows));
        
        $orderIds = array_column($rows, 'order_id');
        
        $existingOrders = $this->getOrdersByAvitoNumbers($orderIds);
        
        $this->logger->log("LOG", "Найдено существующих заказов в Битрикс: " . count($existingOrders));
        
        $fileStats = [
            'created' => 0,
            'canceled' => 0,
            'updated' => 0,
            'skip' => 0,
            'skip_updated' => 0,
            'errors' => 0
        ];

        foreach ($rows as $row) {
            try {
				//prent($row);prent($existingOrders);//die;
                $result = $this->processRow($row, $existingOrders);
				
                if ($result === 'created') {
                    $fileStats['created']++;
                } elseif ($result === 'canceled') {
                    $fileStats['canceled']++;
                } elseif ($result === 'updated') {
                    $fileStats['updated']++;
                } elseif ($result === 'skip') {
                    $fileStats['skip']++;
                } elseif ($result === 'skip_updated') {
                    $fileStats['skip_updated']++;
                } elseif ($result === 'errors') {
                    $fileStats['errors']++;
                } elseif ($result === false) {
                    $fileStats['errors']++;
                }
            } catch (\Exception $e) {
                $this->logger->log("ERROR", "Ошибка обработки заказа {$row['order_id']}: " . $e->getMessage(), $e);
                $fileStats['errors']++;
            }
        }
        
        $this->logger->log("LOG", "Файл {$fileName} обработан: создано={$fileStats['created']}, отменено={$fileStats['canceled']}, обновлено={$fileStats['updated']}, ошибок={$fileStats['errors']}");
        
        $this->stats['orders_created'] += $fileStats['created'];
        $this->stats['orders_canceled'] += $fileStats['canceled'];
        $this->stats['orders_updated'] += $fileStats['updated'];
        $this->stats['orders_skip'] += $fileStats['skip'];
        $this->stats['orders_skip_updated'] += $fileStats['skip_updated'];
        $this->stats['errors'] += $fileStats['errors'];
        
        if ($fileStats['errors'] == 0) {
            $this->moveFileToProcessed($filePath);
        } else {
            //$this->moveFileToError($filePath);
        }
        
        $this->stats['files_processed']++;
    }
    
    private function parseCsvFile($filePath)
    {
        $rows = [];
        $handle = fopen($filePath, 'r');
        
        if (!$handle) {
            $this->logger->log("ERROR", "Не удалось открыть файл: " . basename($filePath));
            return $rows;
        }
        
        $lineNumber = 0;
        
        while (($line = fgets($handle)) !== false) {
            $lineNumber++;
            $line = trim($line);
            
            if (empty($line)) {
                continue;
            }
            
            if ($lineNumber == 1 && $this->isHeaderLine($line)) {
                continue;
            }
            
            $data = $this->parseCsvLine($line);
            if ($data && !empty($data['order_id'])) {
                $rows[] = $data;
            } else {
                //$this->logger->log("ERROR", "Ошибка парсинга строки {$lineNumber} в файле " . basename($filePath) . ": {$line}");
            }
        }
        
        fclose($handle);
        return $rows;
    }
    
    private function isHeaderLine($line)
    {
        return strpos($line, 'order;status;service') !== false;
    }
    
    private function parseCsvLine($line)
    {
        $data = str_getcsv($line, ';');
        
        if (count($data) < 6) {
            return null;
        }
        
        return [
            'order_id' => trim($data[0]),
            'status' => trim($data[1]),
            'service' => trim($data[2]),
            'track_code' => trim($data[3]),
            'model' => trim($data[4]),
            'price' => (float) trim($data[5])
        ];
    }
    
    private function getOrdersByAvitoNumbers($orderIds)
    {
        if (empty($orderIds)) {
            return [];
        }
        
        $existingOrders = [];
        
        $propertyId = $this->getAvitoOrderPropertyId();
        
        if (!$propertyId) {
            $this->logger->log("ERROR", "Не найдено свойство заказа AVITO_ORDER_NUMBER");
            return [];
        }
        
        $dbRes = \Bitrix\Sale\Internals\OrderPropsValueTable::getList([
            'select' => ['ORDER_ID', 'VALUE'],
            'filter' => [
                'ORDER_PROPS_ID' => $propertyId,
                'VALUE' => $orderIds
            ]
        ]);
        
        while ($prop = $dbRes->fetch()) {
            $existingOrders[$prop['VALUE']] = $prop['ORDER_ID'];
        }
        
        return $existingOrders;
    }
    
    private function getAvitoOrderPropertyId()
    {
        $dbRes = \Bitrix\Sale\Internals\OrderPropsTable::getList([
            'select' => ['ID'],
            'filter' => [
                'CODE' => 'AVITO_ORDER_NUMBER'
            ],
            'limit' => 1
        ]);
        
        if ($prop = $dbRes->fetch()) {
            return $prop['ID'];
        }
        
        return null;
    }
    
    private function processRow($data, $existingOrders)
    {
        $isCancelStatus = mb_stripos($data['status'], 'отмен') !== false 
                       || mb_stripos($data['status'], 'возврат') !== false;
        
        if ($isCancelStatus) {
            if (isset($existingOrders[$data['order_id']])) {
				//prent(['1',$data]);die;
                $orderId = $existingOrders[$data['order_id']];
                $order = Order::load($orderId);
				
                if ($order && $order->getField("STATUS_ID") != $this->statusCancel) {
                    $this->cancelOrder($order);
                    $this->logger->log("LOG", "Заказ {$data['order_id']} отменен");
                    return 'canceled';
                }
            } else {
                $this->logger->log("ERROR", "Заказ {$data['order_id']} не найден для отмены");
				return 'skip';
                //return false;
            }
        }
        
        if (isset($existingOrders[$data['order_id']])) {
			//prent(['2',$data]);die;
            $orderId = $existingOrders[$data['order_id']];
            $order = Order::load($orderId);
            if ($order) {
                $updated = $this->updateOrder($order, $data);
                if ($updated) {
                    $this->logger->log("LOG", "Заказ {$data['order_id']} обновлен (доставка/трек)");
                    return 'updated';
                }
                return 'skip_updated';
            }
        }
        
        $created = $this->createOrder($data);
		if ($created) {
			$this->logger->log("LOG", "Создан заказ {$data['order_id']}, сумма: {$data['price']}");
			return 'created';
		} else {
			$this->logger->log("ERROR", "Ошибка создания заказа", [$data, $created]);
			return 'errors';
		}
    }
    
    private function updateOrder(Order $order, $data)
    {
        $needUpdate = false;
        /*$comment = $order->getField('COMMENTS') ?: '';
        
        $newComment = $this->buildOrderComment($data);
        
        if (strpos($comment, $newComment) === false) {
			file_put_contents('/var/www/bitrix_data/tempusshop.ru/upload/zennolab/avito/buildOrderComment.txt', print_r(['comment' => $comment, 'newComment' => $newComment,], true), 8);
            $order->setField('COMMENTS', $newComment);
            $needUpdate = true;
        }*/
        
		/*if ($order->getField("CANCELED") == 'Y') {
            $order->setField('STATUS_ID', $this->statusCollect);
			$order->setField("CANCELED", "N");
            $needUpdate = true;
		}*/
		
        if ($needUpdate) {
            $result = $order->save();
            return $result->isSuccess();
        }
        
        return true;
    }
    
    private function getOrderByExternalId($externalId)
    {
        try {
            return Order::loadByAccountNumber($externalId);
        } catch (\Exception $e) {
            $this->logger->log("ERROR", "Ошибка поиска заказа {$externalId}: " . $e->getMessage());
            return null;
        }
    }
    
    private function cancelOrder(Order $order)
    {
        try {
            $order->setField('STATUS_ID', $this->statusCancel);
			$order->setField("CANCELED", "Y");
            $result = $order->save();
            
            if ($result->isSuccess()) {
                $this->stats['orders_canceled']++;
                return true;
            } else {
                $errors = implode(', ', $result->getErrorMessages());
                $this->logger->log("ERROR", "Ошибка отмены заказа #{$order->getId()}: {$errors}");
                return false;
            }
        } catch (\Exception $e) {
            $this->logger->log("ERROR", "Исключение при отмене заказа #{$order->getId()}: " . $e->getMessage(), $e);
            return false;
        }
    }
    
    private function createOrder($data)
    {
        try {
            $order = Order::create($this->siteId, $this->defaultUserId, 'RUB');

            $order->setPersonTypeId(1);
            
            $basket = Basket::create($this->siteId);
			$order->setBasket($basket);
			
			$product = $this->findProduct($data['model']);

			$item = $basket->createItem('catalog', $product['ID']);
			
			$item->setFields([
				'QUANTITY' => 1,
				'LID' => $this->siteId,
				'CURRENCY' => $order->getCurrency(),
				'PRICE' => $data['price'],
				'BASE_PRICE' => $data['price'],
				'PRODUCT_PROVIDER_CLASS' => 'CCatalogProductProvider',
				'NAME' => $product['NAME'],
				'PRODUCT_XML_ID' => $product["PRODUCT_XML_ID"] ?? $product['NAME'],
				'CATALOG_XML_ID' => $product["CATALOG_XML_ID"] ?? '',
				'NOTES' => $order->getCurrency(),
				'CUSTOM_PRICE' => 'Y',
			]);

			$order->setFieldNoDemand('USER_ID', $this->defaultUserId);
			
			$order->setField('STATUS_ID', $this->statusCollect);
			$order->setField('COMMENTS', $this->buildOrderComment($data));
			
			$propertyCollection = $order->getPropertyCollection();
			$prop = $propertyCollection->getItemByOrderPropertyCode('AVITO_ORDER_NUMBER');
			if ($prop) {
				$prop->setValue($data['order_id']);
			}
			
			$orderSumm = 0;
			$basketItems = $basket->getBasketItems();
			foreach($basketItems as $item) {
				$orderSumm += $item->getFinalPrice();
			}
				
			$deliverySumm = $order->getDeliveryPrice();
			$orderSumm += $deliverySumm;
			
			$order->setField('PRICE', $orderSumm);

			$result = $order->save();
			
			if (!$result->isSuccess()) {
				$errors = implode(', ', $result->getErrorMessages());
				throw new \Exception("Ошибка сохранения заказа: {$errors}");
			} else {
				$orderId = $order->getId();
				
				$res = \Bitrix\Sale\TradingPlatform\OrderTable::add(array(
					"ORDER_ID" => $orderId,
					"TRADING_PLATFORM_ID" => $this->tradingPlatformId,
					"EXTERNAL_ORDER_ID" => $orderId
				));

				if (!$res->isSuccess()) {
					throw new Exception(implode(', ', $res->getErrorMessages()));
				}
			}
			
            $this->stats['orders_created']++;
            
            return $orderId;
            
        } catch (\Exception $e) {
            $this->logger->log("ERROR", "Ошибка создания заказа {$data['order_id']}: " . $e->getMessage(), $e);
            return false;
        }
    }
    
    private function setAvitoOrderProperty($orderId, $avitoOrderNumber)
    {
        $propertyId = $this->getAvitoOrderPropertyId();
        
        if (!$propertyId) {
            $this->logger->log("ERROR", "Не удалось установить свойство AVITO_ORDER_NUMBER: свойство не найдено");
            return;
        }
        
        try {
            $propertyValue = \Bitrix\Sale\Internals\OrderPropsValueTable::getList([
                'select' => ['ID'],
                'filter' => [
                    'ORDER_ID' => $orderId,
                    'ORDER_PROPS_ID' => $propertyId
                ],
                'limit' => 1
            ])->fetch();
            
            if ($propertyValue) {
                \Bitrix\Sale\Internals\OrderPropsValueTable::update($propertyValue['ID'], [
                    'VALUE' => $avitoOrderNumber
                ]);
            } else {
                \Bitrix\Sale\Internals\OrderPropsValueTable::add([
                    'ORDER_ID' => $orderId,
                    'ORDER_PROPS_ID' => $propertyId,
                    'VALUE' => $avitoOrderNumber,
                    'NAME' => 'Номер заказа Авито'
                ]);
            }
            
            $this->logger->log("LOG", "Установлен AVITO_ORDER_NUMBER={$avitoOrderNumber} для заказа #{$orderId}");
        } catch (\Exception $e) {
            $this->logger->log("ERROR", "Ошибка установки свойства AVITO_ORDER_NUMBER: " . $e->getMessage());
        }
    }
    
    private function findProduct($modelName)
    {
		preg_match('/^(.*)\s+(\S+)$/', $modelName, $matches);
		$article = $matches[2];
		
		if ($article) {
			$dbRes = \CIBlockElement::GetList(
				[],
				[
					'IBLOCK_ID' => $this->catalogIblockId,
					'PROPERTY_CML2_ARTICLE' => $article,
				],
				false,
				false,
				['ID', 'NAME', 'IBLOCK_EXTERNAL_ID', 'XML_ID']
			);
			
			if ($item = $dbRes->Fetch()) {
				return [
					'ID' => $item['ID'],
					'NAME' => $item['NAME'],
					"CATALOG_XML_ID" => $item["IBLOCK_EXTERNAL_ID"],
					"PRODUCT_XML_ID" => $item["XML_ID"],
				];
			}
			
		}

		return [
			'ID' => $this->defaultProductId,
			'NAME' => $modelName,
		];
    }
    
    private function buildOrderComment($data)
    {
        //$comment = "Дата: " . date('Y-m-d H:i:s') . "\n";
        $comment = "ID: {$data['order_id']}\n";
        $comment .= "Служба доставки: {$data['service']}\n";
        $comment .= "Трек-код: {$data['track_code']}\n";
        $comment .= "Модель: {$data['model']}\n";
        $comment .= "Цена: {$data['price']} руб.\n";
        $comment .= "Статус в Авито: {$data['status']}\n";
        //$comment .= "========================";
        
        return $comment;
    }

    private function addPayment(Order $order, $price)
    {
        try {
            $paymentCollection = $order->getPaymentCollection();
            $payment = $paymentCollection->createItem();
            
            $paySystemId = $this->getDefaultPaySystemId();
            
            $payment->setField('SUM', $price);
            $payment->setField('CURRENCY', 'RUB');
            $payment->setField('PAY_SYSTEM_ID', $paySystemId);
            
            $order->save();
        } catch (\Exception $e) {
            $this->logger->log("ERROR", "Ошибка добавления оплаты: " . $e->getMessage());
        }
    }
    
    private function getDefaultPaySystemId()
    {
        $dbRes = \Bitrix\Sale\PaySystem\Manager::getList([
            'filter' => ['ACTIVE' => 'Y'],
            'select' => ['ID'],
            'limit' => 1
        ]);
        
        if ($row = $dbRes->fetch()) {
            return (int) $row['ID'];
        }
        
        return 1;
    }
    
    private function moveFileToProcessed($filePath)
    {
        $newPath = $this->processedDir . '/' . date('Y-m-d_H-i-s') . '_' . basename($filePath);
        $this->moveFile($filePath, $newPath, 'processed');
    }
    
    private function moveFileToError($filePath)
    {
        $newPath = $this->errorDir . '/' . date('Y-m-d_H-i-s') . '_' . basename($filePath);
        $this->moveFile($filePath, $newPath, 'error');
    }
    
    private function moveFile($from, $to, $type)
    {
        if (rename($from, $to)) {
            $this->logger->log("LOG", "Файл перемещён в {$type}: " . basename($to));
        } else {
            $this->logger->log("ERROR", "Не удалось переместить файл в {$type}: " . basename($from));
        }
    }
    
    private function printStats()
    {
        $this->logger->log("LOG", "=== Статистика обработки ===");
        $this->logger->log("LOG", "Обработано файлов: {$this->stats['files_processed']}");
        $this->logger->log("LOG", "Создано заказов: {$this->stats['orders_created']}");
        $this->logger->log("LOG", "Отменено заказов: {$this->stats['orders_canceled']}");
        $this->logger->log("LOG", "Обновлено заказов: {$this->stats['orders_updated']}");
        $this->logger->log("LOG", "Пропустили заказов: {$this->stats['orders_skip']}");
        $this->logger->log("LOG", "Пропустили обновление заказов: {$this->stats['orders_skip_updated']}");
        $this->logger->log("LOG", "Ошибок: {$this->stats['errors']}");
        $this->logger->log("LOG", "============================");
    }
}

$parser = new AvitoOrder();
$parser->run();
$parser->checkLastFile();