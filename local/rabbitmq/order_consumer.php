<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";

set_time_limit(3600);
//error_reporting(E_ALL & ~E_NOTICE); 
ini_set('display_errors', '1');
define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);

require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');

/*$connection = \Bitrix\Main\Application::getInstance()->getConnection();
if (!$connection->isConnected()) {
    $connection->connect();
}*/

require_once($_SERVER['DOCUMENT_ROOT'] . '/local/lib/RabbitMQConnector.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/local/classes/SyncHelper.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/local/classes/SyncUser.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/local/classes/TsLogger.php');

use Bitrix\Main,   
    Bitrix\Main\Localization\Loc,    
    Bitrix\Main\Loader,
    Bitrix\Main\Application,
    Bitrix\Currency,    
    Bitrix\Sale\Delivery,
    Bitrix\Sale\PaySystem,
    Bitrix\Sale,
    Bitrix\Sale\Order,
    Bitrix\Sale\Basket,
    Bitrix\Sale\Affiliate,
    Bitrix\Sale\DiscountCouponsManager,
    Bitrix\Main\Context,
    Bitrix\Main\Config\Option; 

$syncHelper = new SyncHelper();
$rabbit = RabbitMQConnector::getInstance();



// Обработка заказов с "tempus"
$rabbit->consume(RabbitMQConnector::TYPE_ORDER, function(array $data) {
	
    //$_SERVER['REQUEST_TIME'] = time();
    //\CTimeZone::Disable();
    //date_default_timezone_set('Europe/Moscow');
	file_put_contents('/var/www/bitrix_logs/rabbitmq/order/asd.txt', print_r($data, true), 8);
	if ($data['ORIGIN_SITE'] === RABBITMQ_PREFIX) return;
	$prefix = RABBITMQ_PREFIX;
	//if($data['ACCOUNT_NUMBER'] != "127532/3059") return;
	$syncHelper = new SyncHelper();
	
	global $USER;

	if (!$USER || $USER->getID() == 0) {
		//$userID = $syncHelper->getUser();
		$USER = new SyncUser();
		/*if ($userID) {
			$_USER = new CUser;
			$_USER->Authorize($userID);
		}*/
	}
	
	$logger = new TsLogger("rabbitmq/order/");
	//if($data['ORDER_ID'] < 1001919) return;
	
	$arError = [];
	
	// маппинг
	$deliveryMap = $syncHelper->getMapping('delivery');
	$paySystemMap = $syncHelper->getMapping('paySystem');
	$personalTypeMap = $syncHelper->getMapping('personalType');
	$statusMap = $syncHelper->getMapping('status');

	Bitrix\Main\Loader::includeModule('sale');

	$email = "";
	foreach ($data['PROPERTIES'] as &$property) {
		if ($property['CODE'] == 'EMAIL') {
			if (!$property['VALUE']) {
				$property['VALUE'] = uniqid() . '_user@tempus.ru';
			}			
			$email = $property['VALUE'];
		}
	}
	unset($property);
	
	if (!$data['USER']['EMAIL']) {
		$data['USER']['EMAIL'] = $email;
	}
	
	$logger->log("DEBUG", "Начало обработки заказа", $data);

	try {
		$siteId = $data['LID'] ?? SITE_ID;
		$userId = $syncHelper->findOrCreateUser($data["USER"], $siteId);
		
		// пробуем найти по свойству номер заказа. 
		$orderID = $syncHelper->findOrderID($data['ORDER_ID']);
		if ($orderID) {
			$order = Order::load($orderID);
		} else {
			if ($data['ORDER_ID_SITE']) {
				$order = Order::load($data['ORDER_ID_SITE']);
			}
			
			if (!$order) {
				$order = Order::loadByAccountNumber($data['ACCOUNT_NUMBER']);
			}
		}
		
		
		$isNew = false;
        if (!$order) { // Создаем новый заказ
			$logger->log("DEBUG", "Создаем новый заказ", ['ACCOUNT_NUMBER' => $data['ACCOUNT_NUMBER']]);
			$isNew = true;
			
            // Создаем заказ
            $order = Order::create($siteId, $userId, $data['CURRENCY'] ?? 'BYN');

            $order->setPersonTypeId($personalTypeMap[$data["PERSON_TYPE_ID"]] ?? 1);
            
            // Создаем корзину
            $basket = Basket::create($siteId);
			$order->setBasket($basket);
            foreach ($data["ITEMS"] as $arItem) {
                if ($arItem["ARTICLE"] && $productId = $syncHelper->getProductID($arItem["ARTICLE"])) {
					$res = CIBlockElement::GetList(
						[],
						['IBLOCK_ID' => $syncHelper->IBLOCK_CATALOG_ID, "ID" => $productId],
						false,
						false,
						['IBLOCK_EXTERNAL_ID', 'XML_ID']
					);

					$arProduct = [];
					if ($element = $res->Fetch()) {
						$arProduct = [
							"CATALOG_XML_ID" => $element["IBLOCK_EXTERNAL_ID"],
							"PRODUCT_XML_ID" => $element["XML_ID"],
						];
					}
					
					$item = $basket->createItem('catalog', $productId);
					
                    $item->setFields([
                        'QUANTITY' => $arItem['QUANTITY'],
                        //'CURRENCY' => $arItem['CURRENCY'],
						'LID' => $siteId,
						//'CURRENCY' => Bitrix\Currency\CurrencyManager::getBaseCurrency(),
						'CURRENCY' => $order->getCurrency(),
                        'PRICE' => $arItem['PRICE'],
                        'BASE_PRICE' => $arItem['BASE_PRICE'],
                        'PRODUCT_PROVIDER_CLASS' => '\Bitrix\Catalog\Product\CatalogProvider',
                        //'PRODUCT_PROVIDER_CLASS' => 'CCatalogProductProvider',
                        'NAME' => $arItem['NAME'],
						'PRODUCT_XML_ID' => $arProduct["PRODUCT_XML_ID"] ?? $arItem['ARTICLE'],
						'CATALOG_XML_ID' => $arProduct["CATALOG_XML_ID"] ?? '',
						'NOTES' => $arItem['CURRENCY'],
						'CUSTOM_PRICE' => 'Y',
                    ]);
					
					$logger->log("DEBUG", "Добавляем товар", [$arItem, $arProduct]);
                } else {
                    $arError[] = "Товар не найден: " . $arItem['ARTICLE'];
					$logger->log("ERROR", "Товар не найден: " . $arItem['ARTICLE']);
                }
            }

			//$order->setBasket($basket);

			$isNew = true;
		}else{
			// корзина
			$basket = $order->getBasket();
			if ($basket->getFUserId() <= 0) {
				$basket->setFUserId(\Bitrix\Sale\Fuser::getId());
			}
			
			$currentItems = [];
			$basketItems = $basket->getBasketItems();
			
			// текущая корзина
			foreach ($basketItems as $item) {
				$productId = $item->getProductId();
				$currentItems[$productId] = [
					'ITEM' => $item,
					'QUANTITY' => $item->getQuantity(),
					'PRICE' => $item->getPrice(),
					'BASE_PRICE' => $item->getBasePrice(),
				];
			}

			if(is_array($data["ITEMS"]) && count($data["ITEMS"]) > 0){
				$currency = $order->getCurrency();
				//$basket = Basket::create($data["LID"]);
				$newItems = [];
				$itemsToUpdate = [];
				
				// новые элементы
				foreach($data["ITEMS"] as $arItem) {
					$productId = $syncHelper->getProductID($arItem["ARTICLE"]);
					if(!$productId) {
						$productId = $syncHelper->DEFAULT_PRODUCT_ID;
					}
					
					$newItems[$productId] = [
						'QUANTITY' => $arItem['QUANTITY'],
						'PRICE' => $arItem['PRICE'],
						'BASE_PRICE' => $arItem['BASE_PRICE'],
						'CURRENCY' => $arItem['CURRENCY'],
						'DATA' => $arItem
					];
				}
				
				// удаляем
				foreach ($currentItems as $productId => $itemData) {
					if (!isset($newItems[$productId])) {
						$logger->log("DEBUG", "Удаляем товар", [$productId, $currentItems[$productId]]);
						$itemData['ITEM']->delete();
						unset($currentItems[$productId]);
					}
				}
				
				// Добавляем или обновляем элементы
				foreach ($newItems as $productId => $newItem) {
					$arItem = $newItem['DATA'];

					$res = CIBlockElement::GetList(
						[],
						['IBLOCK_ID' => $syncHelper->IBLOCK_CATALOG_ID, "ID" => $productId],
						false,
						false,
						['IBLOCK_EXTERNAL_ID', 'XML_ID']
					);

					$arProduct = [];
					if ($element = $res->Fetch()) {
						$arProduct = [
							"CATALOG_XML_ID" => $element["IBLOCK_EXTERNAL_ID"],
							"PRODUCT_XML_ID" => $element["XML_ID"],
						];
					}

					if ($currentItems[$productId]) {
						// обновляем
						$basketItem = $currentItems[$productId]["ITEM"];
						
						$currentQuantity = $currentItems[$productId]['QUANTITY'];
						$currentPrice = $currentItems[$productId]['PRICE'];
						$currentBasePrice = $currentItems[$productId]['BASE_PRICE'];

						if (
							$currentQuantity != $newItem['QUANTITY'] || 
							$currentPrice != $newItem['PRICE'] || 
							$currency != $newItem['CURRENCY'] || 
							$currentBasePrice != $newItem['BASE_PRICE']
						) {
							/*$basketItem->setFields([
								'QUANTITY' => $newItem['QUANTITY'],
								'PRICE' => $newItem['PRICE'],
								'BASE_PRICE' => $newItem['BASE_PRICE'],
								'CURRENCY' => $currency,
								'NOTES' => $currency,
								'CUSTOM_PRICE' => 'Y'
							]);*/
							$l = [
								'productId' => $productId, 
								'newItem' => $newItem, 
								'currentQuantity' => $currentQuantity,
								'currentPrice' => $currentPrice,
								'currentBasePrice' => $currentBasePrice,
							];
							
							if ($currentQuantity != $newItem['QUANTITY']) {
								$basketItem->setFieldNoDemand('QUANTITY', $newItem['QUANTITY']);
							}
							
							if ($currentBasePrice != $newItem['BASE_PRICE']) {
                                $basketItem->setField(
                                    'BASE_PRICE',
                                    $newItem['BASE_PRICE']
                                );
							}
							
							if ($currentPrice != $newItem['PRICE']) {
                                $basketItem->setField('DISCOUNT_NAME', '');
                                $basketItem->setField('DISCOUNT_VALUE', '');
								
                                $basketItem->markFieldCustom('PRICE');
                                $basketItem->setField('PRICE', $newItem['PRICE']);
							}
							
							$logger->log("DEBUG", "Обновляем товар", $l);
						}
					} else {
						// добавляем
						$item = $basket->createItem('catalog', $productId);
						$item->setFields([
							'QUANTITY' => $arItem['QUANTITY'],
							'CURRENCY' => $currency,
							'LID' => $order->getSiteId(),
							'PRICE' => $arItem['PRICE'],
							'BASE_PRICE' => $arItem['BASE_PRICE'],
							'PRODUCT_PROVIDER_CLASS' => '\Bitrix\Catalog\Product\CatalogProvider',
							'NAME' => $arItem['NAME'],
							'PRODUCT_XML_ID' => $arProduct["PRODUCT_XML_ID"] ?? $arItem['ARTICLE'],
							'CATALOG_XML_ID' => $arProduct["CATALOG_XML_ID"] ?? '',
							'NOTES' => $currency,
							'CUSTOM_PRICE' => 'Y',
						]);
						$logger->log("DEBUG", "Добавляем товар", [$arItem, $arProduct]);
					}
				}
			}
			
			//$order->refreshData();
			
			$accNumber = $order->getField('ACCOUNT_NUMBER');
			if ($data['ACCOUNT_NUMBER'] && $accNumber != $data['ACCOUNT_NUMBER']) {
				$order->setField('ACCOUNT_NUMBER', $data['ACCOUNT_NUMBER']);
				$logger->log("LOG", "ACCOUNT_NUMBER не совпадает", [$accNumber, $data['ACCOUNT_NUMBER']]);
			}
		}
		
		if (isset($data['CANCELED'])) {
			$canceled = $order->isCanceled();
			if ($data['CANCELED'] && !$canceled) {
				// ставим отмену
				$order->setField("CANCELED", "Y");
			} elseif (!$data['CANCELED'] && $canceled) {
				// убираем отмену
				$order->setField("CANCELED", "N");
			}
		}
		
		// доп. поля
		$order->setField('USER_DESCRIPTION', $data["ADDITIONAL"]["USER_DESCRIPTION"] ?? '');
		$order->setField('COMMENTS', $data["ADDITIONAL"]["COMMENTS"] ?? '');
		
		// пользователь
		$order->setFieldNoDemand('USER_ID', $userId);
		
		//$order->setPersonTypeId(1);
		
		// статус
		if($statusMap[$data["STATUS_ID"]] && $statusMap[$data["STATUS_ID"]] != $order->getField('STATUS_ID')){
			$order->setField("STATUS_ID", $statusMap[$data["STATUS_ID"]]);
			$logger->log("DEBUG", "Меняем статус " . $data['ACCOUNT_NUMBER'] . " - " . $statusMap[$data["STATUS_ID"]]);
		}
		
		// оплаты
		$paymentCollection = $order->getPaymentCollection();

		if (is_array($data["PAYMENTS"]) && count($data["PAYMENTS"]) > 0) {
			$processedPayments = [];
			
			foreach ($data["PAYMENTS"] as $paymentData) {
				if (!$paymentID = $paySystemMap[$paymentData["PAYMENT_SYSTEM"]]) {
					continue;
				}
				
				$found = false;
				
				// Ищем существующий платеж с таким же PAY_SYSTEM_ID
				foreach ($paymentCollection as $existingPayment) {
					if ($existingPayment->getPaymentSystemId() == $paySystemMap[$paymentData["PAYMENT_SYSTEM"]]) {
						$found = true;
						
						// Обновляем только если платеж не оплачен
						if ($existingPayment->isPaid() != 'Y') {
							$existingPayment->setField('SUM', $paymentData['SUM']);
							$existingPayment->setField('PAID', $paymentData['PAID'] ? 'Y' : 'N');
						}
						
						$processedPayments[] = $existingPayment->getId();
						break;
					}
				}
				
				// Если не нашли существующий - создаем новый
				if (!$found) {
					$payment = $paymentCollection->createItem(
						Bitrix\Sale\PaySystem\Manager::getObjectById($paymentID)
					);
					
					$payment->setField('PAY_SYSTEM_ID', $paySystemMap[$paymentData["PAYMENT_SYSTEM"]]);
					$payment->setField('SUM', $paymentData['SUM']);
					$payment->setField('PAID', $paymentData['PAID'] ? 'Y' : 'N');
					
					$processedPayments[] = $payment->getId();
				}
			}
			
			// Удаляем платежи, которых нет во входящих данных (и которые не оплачены)
			foreach ($paymentCollection as $existingPayment) {
				if (!in_array($existingPayment->getId(), $processedPayments) && $existingPayment->isPaid() != 'Y') {
					$existingPayment->delete();
				}
			}
		} else {
			// Если нет платежей во входящих данных - удаляем все неоплаченные
			foreach ($paymentCollection as $existingPayment) {
				if ($existingPayment->isPaid() != 'Y') {
					$existingPayment->delete();
				}
			}
		}

		// доставка  && $deliveryMap[$data["DELIVERY_ID"]] != $order->getField('DELIVERY_ID')
		$shipmentCollection = $order->getShipmentCollection();

		if ($deliveryId = $deliveryMap[$data['DELIVERY']['ID']]) {
			$found = false;
			
			// Получаем объект службы доставки
			$deliveryService = \Bitrix\Sale\Delivery\Services\Manager::getObjectById($deliveryId);
			
			if ($deliveryService) {
				$baseFields = [
					'BASE_PRICE_DELIVERY'   => $data['DELIVERY']['PRICE_DELIVERY'],
					'PRICE_DELIVERY'        => $data['DELIVERY']['PRICE_DELIVERY'],
					'TRACKING_NUMBER'       => $data['DELIVERY']['TRACKING_NUMBER'],
					'CURRENCY'              => $data['CURRENCY'] ?? $order->getCurrency(),
					'DELIVERY_NAME'         => $deliveryService->getName(),
					'CUSTOM_PRICE_DELIVERY' => 'Y',
					'ALLOW_DELIVERY'        => 'Y',
				];

				// Логируем текущие отгрузки
				$currentShipments = [];
				foreach ($shipmentCollection as $shipment) {
					$currentShipments[] = [
						'ID' => $shipment->getId(),
						'DELIVERY_ID' => $shipment->getField('DELIVERY_ID'),
						'SYSTEM' => $shipment->isSystem()
					];
				}
				$logger->log("DEBUG", "Текущие отгрузки " . $data['ACCOUNT_NUMBER'], $currentShipments);

				// В коллекции всегда есть одна скрытая системная доставка
				// Если есть только системная доставка - создаем новую
				$nonSystemShipments = 0;
				foreach ($shipmentCollection as $shipment) {
					if (!$shipment->isSystem()) {
						$nonSystemShipments++;
					}
				}

				if ($nonSystemShipments === 0) {
					// Создаем новую отгрузку
					try {
						$shipment = $shipmentCollection->createItem($deliveryService);
						$shipment->setFields($baseFields);
						
						// Добавляем товары в отгрузку
						$shipmentItemCollection = $shipment->getShipmentItemCollection();
						foreach ($basket as $basketItem) {
							$shipmentItem = $shipmentItemCollection->createItem($basketItem);
							$shipmentItem->setQuantity($basketItem->getQuantity());
						}

						$logger->log("DEBUG", "Создали новую отгрузку " . $data['ACCOUNT_NUMBER'] . " - " . $shipment->getId());
						$found = true;
					} catch (Exception $e) {
						$logger->log("ERROR", "Ошибка создания отгрузки " . $data['ACCOUNT_NUMBER'] . " - " . $e->getMessage());
					}
				} else {
					// Обновляем существующие несистемные отгрузки
					foreach ($shipmentCollection as $shipment) {
						if (!$shipment->isSystem()) {
							if ($shipment->getField('DELIVERY_ID') == $deliveryId) {
								$shipment->setFields($baseFields);
								$found = true;
								$logger->log("DEBUG", "Обновили существующую отгрузку " . $data['ACCOUNT_NUMBER'] . " - " . $shipment->getId(), $baseFields);
								break;
							}
						}
					}

					// Если не нашли подходящую отгрузку - создаем новую
					if (!$found) {
						try {
							$shipment = $shipmentCollection->createItem($deliveryService);
							$shipment->setFields($baseFields);
							
							// Добавляем товары в отгрузку
							$shipmentItemCollection = $shipment->getShipmentItemCollection();
							foreach ($basket as $basketItem) {
								$shipmentItem = $shipmentItemCollection->createItem($basketItem);
								$shipmentItem->setQuantity($basketItem->getQuantity());
							}

							$logger->log("DEBUG", "Создали новую отгрузку (не нашли подходящую) " . $data['ACCOUNT_NUMBER'] . " - " . $shipment->getId());
							$found = true;
						} catch (Exception $e) {
							$logger->log("ERROR", "Ошибка создания отгрузки " . $data['ACCOUNT_NUMBER'] . " - " . $e->getMessage());
						}
					}
				}

				// Удаляем лишние несистемные отгрузки (кроме той, которую только что создали/обновили)
				$currentDeliveryId = $deliveryId;
				foreach ($shipmentCollection as $shipment) {
					if (!$shipment->isSystem() && $shipment->getField('DELIVERY_ID') != $currentDeliveryId) {
						$shipment->delete();
						$logger->log("DEBUG", "Удалили лишнюю отгрузку " . $data['ACCOUNT_NUMBER'] . " - " . $shipment->getId());
					}
				}

			} else {
				$logger->log("ERROR", "Служба доставки не найдена " . $data['ACCOUNT_NUMBER'] . " - " . $deliveryId);
			}

			// Логируем итоговые отгрузки
			$resultShipments = [];
			foreach ($shipmentCollection as $shipment) {
				$resultShipments[] = [
					'ID' => $shipment->getId(),
					'DELIVERY_ID' => $shipment->getField('DELIVERY_ID'),
					'SYSTEM' => $shipment->isSystem(),
					'PRICE' => $shipment->getField('PRICE_DELIVERY')
				];
			}
			
			$logger->log("DEBUG", "Итоговые отгрузки " . $data['ACCOUNT_NUMBER'], $resultShipments);
			
		} else {
			$logger->log("ERROR", "Не найдено соответствие для delivery_id " . $data['ACCOUNT_NUMBER'] . " - " . $data["DELIVERY_ID"]);
		}

		// свойства 
		$propertyCollection = $order->getPropertyCollection();
		$personTypeId = $order->getPersonTypeId();
		
		$propertyFields = [
			'id_order_tempusru' => $data['ORDER_ID'], // пишим в свойство id_order_tempusru
			'ACCOUNT_NUMBER' => $data['ACCOUNT_NUMBER'], // пишим в свойство ACCOUNT_NUMBER
			'DELIVERY_DATE' => $data['DELIVERY_DATE'], // Дата доставки
		];
		
		foreach ($data['PROPERTIES'] as $property) {
			$propertyFields[$property["CODE"]] = $property["VALUE"];
		}
		
		foreach ($propertyFields as $code => $value) {
			$prop = $propertyCollection->getItemByOrderPropertyCode($code);
			if ($prop) {
				$prop->setValue($value);
				$logger->log("DEBUG", $data['ACCOUNT_NUMBER'] . " установка свойства {$code} = {$value}");
			}
		}
		
		// сумма заказа
		$orderSumm = 0;
		$basketItems = $basket->getBasketItems();
		foreach($basketItems as $item) {
			$orderSumm += $item->getFinalPrice();
		}
			
		$deliverySumm = $order->getDeliveryPrice();
		$orderSumm += $deliverySumm;
		
		$oldPrice = $order->getField('PRICE');
		if ($oldPrice != $orderSumm) {
			$order->setField('PRICE', $orderSumm);
			$logger->log("LOG", "Сумма заказа {$oldPrice} -> {$orderSumm}");
		}

		$GLOBALS['GLOBAL_ACCOUNT_NUMBER'] = $data['ACCOUNT_NUMBER'];
		
		try {
			$result = $order->save();
			
			if (!$result->isSuccess()) {
				$logger->log("ERROR", "Ошибка обновления заказ", $data['ACCOUNT_NUMBER']);
				throw new Exception(implode(', ', $result->getErrorMessages()));
			} else {
				
				if ($isNew) {
					$orderID = $order->getId();
					$orderNew = Order::load($orderID);
					$accNumber = $orderNew->getField('ACCOUNT_NUMBER');
					if ($data['ACCOUNT_NUMBER'] && $accNumber != $data['ACCOUNT_NUMBER']) {
						//$orderNew->setField('ACCOUNT_NUMBER', $data['ACCOUNT_NUMBER']);
						$logger->log("ERROR", "ACCOUNT_NUMBER не совпадает после сохранения", [$accNumber, $data['ACCOUNT_NUMBER']]);
						//$r = $orderNew->save();
					}
				}
				
				$orderID = $order->getId();
				$accountNumber = $order->getField('ACCOUNT_NUMBER');
				
				$tradeBindingCollection = $order->getTradeBindingCollection();
				$tpId = null;
				
				foreach ($tradeBindingCollection as $item) {
					$tpId = $item->getField('TRADING_PLATFORM_ID');
					break;
				}
				
				$tradingPlatformId = $syncHelper->getTradingPlatformId($siteId, $statusMap[$data["STATUS_ID"]]);
				
				$logger->log("DEBUG", "Источник", ['Текущий' => $tpId, 'Новый' => $tradingPlatformId]);
				
				if (!$tpId) {
					$res = \Bitrix\Sale\TradingPlatform\OrderTable::add(array(
						"ORDER_ID" => $orderID,
						"TRADING_PLATFORM_ID" => $tradingPlatformId,
						"EXTERNAL_ORDER_ID" => $accountNumber
					));

					if (!$res->isSuccess()) {
						throw new Exception(implode(', ', $res->getErrorMessages()));
					}
				} else {
					if ($tpId != 15 && $tpId != $tradingPlatformId) {
						$syncHelper->setTradingPlatform($orderID, $tradingPlatformId);
					}
				}
			
				$logger->log("DEBUG", "Обновили заказ", $data['ACCOUNT_NUMBER']);
			}
		} catch (Exception $e) {
			$logger->log("ERROR", "Ошибка сохранения заказа", [$data['ACCOUNT_NUMBER'], $e->getMessage()]);
		}
		unset($GLOBALS['GLOBAL_ACCOUNT_NUMBER']);

	} catch (Exception $e) {
		$logger->log("ERROR", $data['ACCOUNT_NUMBER'] . " Ошибка обработки заказа", $e->getMessage());
    }
    /*$order->setFields([ 
        'ACCOUNT_NUMBER' => $orderData['ACCOUNT_NUMBER'],
        'PRICE' => $orderData['PRICE'],
        'CURRENCY' => $orderData['CURRENCY'],
    ]);
    
    $basket = \Bitrix\Sale\Basket::create(SITE_ID);
    foreach ($orderData['ITEMS'] as $item) {
        $basketItem = $basket->createItem('catalog', $item['product_id']);
        $basketItem->setFields([
            'QUANTITY' => $item['quantity'],
            'PRICE' => $item['price'],
            'CURRENCY' => $item['currency'] ?? 'RUB',
            'NAME' => $item['name'] ?? ''
        ]);
    }
    $order->setBasket($basket);
    
    $order->save();*/
}, true);