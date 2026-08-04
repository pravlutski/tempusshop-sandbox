<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
require($_SERVER["DOCUMENT_ROOT"]."/local/cron/ms/config/statusDictionary.php");

CModule::IncludeModule("main");
CModule::IncludeModule("iblock");
CModule::IncludeModule('panel.manager');

$logger = new TsLogger("/ms/syncOrder/");
$triggers = new TsTriggers();
$workers = new WorkersChecker("SyncOrder");

if (!$workers->checkStatus()) {
	$logger->log("LOG", "Обработчик занят");
	exit();
}

$workers->updateStatus("Y");

// define('MOYSKLAD_API_URL', 'https://api.moysklad.ru/api/remap/1.2');
// define('MOYSKLAD_API_LOGIN', 'bitrix@tempusint');
// define('MOYSKLAD_API_PASSWORD', 'akkxbTO88yQR');
define('ORDERS_TO_SYNC', 3000);
set_time_limit(3600);

$CurDB = new DBPanel();
$moduleCur = 'syncOrders';

function updateStatus( string $code, array $arStat ):void
{
  $CurDB = new DBPanel();
  if ( empty($arStat) ) return;
  $strSql = "UPDATE ms_agents SET ";
  foreach ($arStat as $field => $value) {
    if ( array_key_last($arStat) == $field ){
      $str = "{$field} = '{$value}'";
    }else{
      $str = "{$field} = '{$value}', ";
    }
    $strSql .= $str;
  }
  $strSql .= " WHERE code = '{$code}'";
  try{
    $CurDB->query( $strSql );
  }catch( Throwable $ignored){
    print_r('Не удалось обновить статус' . $ignored . "\n");
  }
}

function getNewBitrixOrders() {
    $CurDB = new DBPanel();
    GLOBAL $DB;

    // $result = $CurDB->query("SELECT * FROM ms_orders WHERE bitrix_id = '706439'");
		$dateCreate = date( 'Y-m-d', strtotime('- 5 day') );
    $result = $CurDB->query("SELECT * FROM ms_orders WHERE (status = 1 OR answer = NULL)");
		$rows = $CurDB->fetchAll($result);
		foreach ($rows as $row) {
			$ordersBD[$row['bitrix_id']] = $row;
		}
    if (empty($ordersBD)) {
      print_r('Заказов нету');
      die();
    }
		unset($result);
		unset($rows);

    $filter = [
        'filter' => [
            'ID' => array_keys($ordersBD)
            // 'ID' => [683207]
        ],
        'select' => ['ID', 'DATE_INSERT', 'STATUS_ID', 'PRICE', 'CURRENCY', 'USER_DESCRIPTION', 'USER_ID','ACCOUNT_NUMBER', 'COMMENTS'],
        'order' => ['ID' => 'ASC'],
        'limit' => ORDERS_TO_SYNC
    ];

    $dbOrders = \Bitrix\Sale\Order::getList($filter);
    $orders = [];

    while ($order = $dbOrders->fetch()) {
        $orderObj = \Bitrix\Sale\Order::load($order['ID']);

        $order['DELIVERY_PRICE'] = $orderObj->getDeliveryPrice();
        try{
          $deliveryName = getDeliveryName( $orderObj );
          $comment = buildComment( $order['COMMENTS'] ?? '', $deliveryName );
        } catch( Throwable $e ){
          var_dump( "ERROR OCCURED IN COMMENT BLOCK: " . $e->getMessage() );
        }
        $order['COMMENT_MS'] = $comment;

        $order['DB'] = $ordersBD[$order['ID']];
        $orders[] = $order;
    }

    return $orders;
}

function getOrderProducts($orderId) {
    $order = \Bitrix\Sale\Order::load($orderId);
    $basket = $order->getBasket();
    $items = [];

    foreach ($basket as $basketItem) {
        $items[] = [
            'PRODUCT_ID' => $basketItem->getProductId(),
            'NAME' => $basketItem->getField('NAME'),
            'QUANTITY' => $basketItem->getQuantity(),
            'PRICE' => $basketItem->getPrice(),
            'CURRENCY' => $basketItem->getCurrency(),
            'PRODUCT_XML_ID' => $basketItem->getField('PRODUCT_XML_ID') // Артикул товара
        ];
    }

    return $items;
}

function getDeliveryName( object $order ):string
{
  $shipmentCollection = $order->getShipmentCollection();

  if ( empty($shipmentCollection) ) return '';
  $result = '';
  try{
    foreach ( $shipmentCollection as $shipment ){
      $delivery = $shipment->getDelivery();
      $result = $delivery->getName() ?? '';
      if ( !empty($result) ) break;
    }
  }catch( Throwable $e ){

  }

  return $result;
}

function buildComment( string $orderComment, string $deliveryName ):string
{
  if ( empty($orderComment) && empty($deliveryName) ) return 'Комментарий к заказу: не указан; \nСпособ доставки: не указан';
  $comment = "Комментарий к заказу: %s; \nСпособ доставки: %s";
  $text1 = empty($orderComment) ? 'не указан' : $orderComment;
  $text2 = empty($deliveryName) ? 'не указан' : $deliveryName;

  return sprintf( $comment, $text1, $text2 );
}

function findCounterparty($userId,$source) {
    $ms = new MoyskladAPI($source);

    $user = \Bitrix\Main\UserTable::getById($userId)->fetch();

    $name = $user['LAST_NAME'] . ' ' . $user['NAME'] . ' ' . $user['SECOND_NAME'];
    // $name = str_replace(" ", "%20", trim($name));
    $name = urlencode(trim($name));

    $agent = $ms->searchAgent($name);

    print_r('FUSER');
    if (!empty($agent)) {
      if (count($agent) > 0) {
        $response = $agent[0]['meta'];
      } else {
        $response = false;
      }
    } else {
      $response = false;
    }

    return $response;
}

function createCounterparty($userId,$source) {

    $ms = new MoyskladAPI($source);

    $user = \Bitrix\Main\UserTable::getById($userId)->fetch();


    // $name = urlencode(trim($name));
    // if (empty($user['PERSONAL_PHONE'])) {
    //   $user['PERSONAL_PHONE'] = '+799999999';
    // }

    if (empty($user['LAST_NAME']) && empty($user['NAME']) && empty($user['SECOND_NAME'])) {
      $name = $user['EMAIL'];
    } else {
        $name = $user['LAST_NAME'] . ' ' . $user['NAME'] . ' ' . $user['SECOND_NAME'];
    }

    $userData = [
      'name' => $name,
      'phone' => $user['PERSONAL_PHONE'] ?? '',
      'email' => $user['EMAIL'] ?? '',
    ];

    print_r('CUSER');
    $newCounter = $ms->createCounterparty($userData);

    if (!empty($newCounter)) {
      $response = $newCounter['meta'];
    } else {
      $response = false;
    }
    return $response;
}

function findOrder($orderId,$source) {

    $ms = new MoyskladAPI($source);

    $order = $ms->searchOrder($orderId);

    if (!empty($order)) {
      $response = $order[0]['id'];
    } else {
      $response = false;
    }
    return $response;
}

function findProduct($id,$source) {
    GLOBAL $DB;
    $strSql = "SELECT MS_ID FROM ci_ms_assortment WHERE BX_ID = '{$id}' AND SITE_ID = '{$source}'";
		$results = $DB->query($strSql, false, $err_mess.__LINE__);
		while ($row = $results->Fetch()){
      $msId = $row['MS_ID'];
    }

    if (!empty($msId)) {
        $data['meta'] = [
          "href" => "https://api.moysklad.ru/api/remap/1.2/entity/product/{$msId}",
          "type" => "product",
          "mediaType" => "application/json"
        ];

        return $data;
    }

    return false;
}

function createMoySkladOrder($bitrixOrder, $products, $msUserSettings) {
    $msDefault = $msUserSettings;
    $source = $bitrixOrder['DB']['source'];
    $ms = new MoyskladAPI($source);

    if ($source == 's1') {
      $orderStatuses = STATUS_DICTIONARY['RU'];
    } else {
      $orderStatuses = STATUS_DICTIONARY['BY'];
    }

    if ( $bitrixOrder['USER_ID'] == 182118 ){
      // $counterparty = '';
      $counterparty = [
        'href' => "https://api.moysklad.ru/api/remap/1.2/entity/counterparty/3268f56e-3595-11f0-0a80-03f70028a80c",
      ];
    }elseif( $bitrixOrder['USER_ID'] == 135989 ){
      // $counterparty = 'dd5b00b5-2a6e-11ec-0a80-019e000baf07';
      $counterparty = [
        'href' => "https://api.moysklad.ru/api/remap/1.2/entity/counterparty/dd5b00b5-2a6e-11ec-0a80-019e000baf07",
      ];
    }elseif( $bitrixOrder['USER_ID'] == 161898 ){
      // $counterparty = 'dd5b00b5-2a6e-11ec-0a80-019e000baf07';
      $counterparty = [
        'href' => "https://api.moysklad.ru/api/remap/1.2/entity/counterparty/61a6c35f-a0c3-11ee-0a80-0b7600478a41",
      ];
    }elseif( $bitrixOrder['USER_ID'] == 81140 ){
      // $counterparty = 'dd5b00b5-2a6e-11ec-0a80-019e000baf07';
      $counterparty = [
        'href' => "https://api.moysklad.ru/api/remap/1.2/entity/counterparty/a22b6df1-e19c-11eb-0a80-04a6000ec37b",
      ];
    }elseif( $bitrixOrder['USER_ID'] == 191551 ){
      // $counterparty = 'dd5b00b5-2a6e-11ec-0a80-019e000baf07';
      $counterparty = [
        'href' => "https://api.moysklad.ru/api/remap/1.2/entity/counterparty/bae0093a-dbe3-11f0-0a80-008f001bcdcd",
      ];
    }elseif( $bitrixOrder['USER_ID'] == 193181 ){
      // $counterparty = 'dd5b00b5-2a6e-11ec-0a80-019e000baf07';
      $counterparty = [
        'href' => "https://api.moysklad.ru/api/remap/1.2/entity/counterparty/13d669dc-eb08-11f0-0a80-0b1a0080c9fa",
      ];
    }else{
      $counterparty = findCounterparty($bitrixOrder['USER_ID'],$bitrixOrder['DB']['source']);
      if (empty($counterparty)) {
        $counterparty = createCounterparty($bitrixOrder['USER_ID'],$bitrixOrder['DB']['source']);
        // print_r($bitrixOrder);
      }
    }


    $positions = [];
    foreach ($products as $product) {

        $msProduct = findProduct($product['PRODUCT_ID'],$source);

        if (!$msProduct) {
            continue;
        }

        $positions[] = [
            'quantity' => (float)$product['QUANTITY'],
            'price' => (float)$product['PRICE'] * 100,
            'assortment' => [
                'meta' => $msProduct['meta']
            ]
        ];

        if (!empty($bitrixOrder['DELIVERY_PRICE'])) {
          if ($source == 's2') {
             $dostId = 'https://api.moysklad.ru/api/remap/1.2/entity/service/72c8db37-6dd7-11ea-0a80-00e10006fc27';
          } else {
             $dostId = 'https://api.moysklad.ru/api/remap/1.2/entity/service/7fe63217-0b1c-11ea-0a80-03b100002fa0';
          }

          $positions[] = [
              'quantity' => 1,
              'price' => (float)$bitrixOrder['DELIVERY_PRICE'] * 100,
              'assortment' => [
                  'meta' => [
                    "href" => $dostId,
                    "type" => "service",
                    "mediaType" => "application/json"
                  ]
              ]
          ];
        }
    }
    if ($source == 's2') {
       $dComp = [ "meta" => [
          "href" => "https://api.moysklad.ru/api/remap/1.2/entity/organization/6812f6e0-aa06-11ee-0a80-138b002c126e",
          "metadataHref" => "https://api.moysklad.ru/api/remap/1.2/entity/organization/metadata",
          "type" => "organization",
          "mediaType" => "application/json"
        ]];
       $dStore = ["meta" => [
          "href" => "https://api.moysklad.ru/api/remap/1.2/entity/store/6f6d2169-180c-11ea-0a80-00b30004eaef",
          "type" => "store",
          "mediaType" => "application/json"
        ]];
    } else {
       $dComp = $msDefault['defaultCompany'];
       $dStore = $msDefault['defaultPlace'];
    }

    $orderData = [
        'name' => $bitrixOrder['ACCOUNT_NUMBER'],
        'organization' => $dComp,
        'store' =>$dStore,
        'agent' => [
            'meta' => [
              "href" => $counterparty['href'],
              "type" => "counterparty",
              "mediaType" => "application/json"
            ]
        ],
        'positions' => $positions,
        'description' => $bitrixOrder['COMMENT_MS'] ?? '',
    ];
    if ( !empty($orderStatuses[ $bitrixOrder["STATUS_ID"] ])  ){
      $orderData['state'] = [
        'meta' => [
          "href" => "https://api.moysklad.ru/api/remap/1.2/entity/customerorder/metadata/states/{$orderStatuses[$bitrixOrder["STATUS_ID"]]}",
          "type" => "state",
          "mediaType" => "application/json"
        ]
      ];
    }
    //file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/local/cron/ms/TRUE_DEBUG.txt", print_r($orderData, true).PHP_EOL,FILE_APPEND);
    // print_r($orderData);

    $newId = $ms->createOrder($orderData);

    if (!isset($newId['errors'])) {
      $response = $newId;
    }

    return $newId;
}

function updateMoySkladOrder($bitrixOrder, $products, $msId) {
    $source = $bitrixOrder['DB']['source'];
    $ms = new MoyskladAPI($source);

    if ($source == 's1') {
      $orderStatuses = STATUS_DICTIONARY['RU'];
    } else {
      $orderStatuses = STATUS_DICTIONARY['BY'];
    }

    $orderData = [];

   if (!empty($orderStatuses[$bitrixOrder["STATUS_ID"]])) {
       $orderData['state'] = [
           'meta' => [
               "href" => "https://api.moysklad.ru/api/remap/1.2/entity/customerorder/metadata/states/{$orderStatuses[$bitrixOrder["STATUS_ID"]]}",
               "type" => "state",
               "mediaType" => "application/json"
           ]
       ];
   }

   $currentOrder = $ms->getOrderPosition($msId);

   $positionsChanged = false;
   $newPositions = [];

   foreach ($products as $product) {
       $msProduct = findProduct($product['PRODUCT_ID'], $source);
       if (!$msProduct) continue;

       $newPositions[] = [
           'quantity' => (float)$product['QUANTITY'],
           'price' => (float)$product['PRICE'] * 100,
           'assortment' => ['meta' => $msProduct['meta']]
       ];
   }

   if (!empty($bitrixOrder['DELIVERY_PRICE'])) {
       $dostId = ($source == 's2')
           ? 'https://api.moysklad.ru/api/remap/1.2/entity/service/72c8db37-6dd7-11ea-0a80-00e10006fc27'
           : 'https://api.moysklad.ru/api/remap/1.2/entity/service/7fe63217-0b1c-11ea-0a80-03b100002fa0';

       $newPositions[] = [
           'quantity' => 1,
           'price' => (float)$bitrixOrder['DELIVERY_PRICE'] * 100,
           'assortment' => [
               'meta' => [
                   "href" => $dostId,
                   "type" => "service",
                   "mediaType" => "application/json"
               ]
           ]
       ];
   }

   if (isset($currentOrder['rows'])) {
       if (count($currentOrder['rows']) != count($newPositions)) {
           $positionsChanged = true;
       } else {
           foreach ($currentOrder['rows'] as $i => $currentPos) {
               $newPos = $newPositions[$i];
               if ($currentPos['quantity'] != $newPos['quantity'] ||
                   $currentPos['price'] != $newPos['price'] ||
                   $currentPos['assortment']['meta']['href'] != $newPos['assortment']['meta']['href']
               ) {
                   $positionsChanged = true;
                   break;
               }
           }
       }
   } else {
       $positionsChanged = true;
   }
   if ($positionsChanged && empty($newPositions)) {
      $bot = new TGNotifier;
      $bot->sendMessage("Модуль выгрузки заказов МС:</b>\n\n❌При обновлении заказа {$bitrixOrder['ACCOUNT_NUMBER']} зафиксирована попытка установить пустую корзину.<b></b>");
   }
   if ($positionsChanged && !empty($newPositions)) {
       $orderData['positions'] = $newPositions;
   }
   $orderData['description'] = $bitrixOrder['COMMENT_MS'] ?? '';
   //file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/local/cron/ms/TRUE_DEBUG.txt", print_r($orderData, true).PHP_EOL,FILE_APPEND);
   return $ms->updateOrder($orderData, $msId);
}

try {
	$logger->log("LOG", "Запуск");
	$timeStart = date('Y.m.d G:i:s');
	$arStat = [
		'status' => 'PROCESS',
		'status_text' => 'Запуск скрипта',
		'percent' => 0,
		'time_start' => $timeStart
	];
	updateStatus($moduleCur, $arStat);
	$logger->log("LOG", "arStat", $arStat);

	$orders = getNewBitrixOrders();
	$logger->log("LOG", "Получили заказы");

    if ($orders) {
		$logger->log("LOG", "Обрабатываем заказы начало");
		updateStatus($moduleCur, ['status_text' => 'Обрабатываем заказы', 'percent' => 20]);
		$logger->log("LOG", "Обрабатываем заказы конец");

		if (is_array($orders)) {
			$countOrder = count($orders);
			$step = round(80 / $countOrder);
			$i = 1;
			$percent = 20;
		}

		$logger->log("LOG", "Обрабатываем заказы конец", ['countOrder' => $countOrder,]);
		foreach ($orders as $order) {
			$logger->log("LOG", "Обрабатываем заказ {$order['ID']}");
			$percent = $percent + $step;
			updateStatus($moduleCur, ['status_text' => 'Обрабатываем заказ '.$i.' из ' .$countOrder, 'percent' => $percent]);
			$ms = new MoyskladAPI($order['DB']['source']);

			$msUserSettings  = $ms->getDefault();

			echo "Обработка заказа #{$order['ID']}...\n";
			$msOrder = '';
			$products = getOrderProducts($order['ID']);


			if (empty($products)) {
				echo "В заказе #{$order['ID']} нет товаров, пропускаем.\n";
				$in = array(
					"date_change" => "'".date('Y-m-d H:i:s')."'",
					"action" => "'ERROR'",
					"answer" => "'".addslashes(json_encode("В заказе #{$order['ID']} нет товаров, пропускаем", JSON_UNESCAPED_UNICODE))."'",
					"status" => "'0'",
				);

				$setParts = array();
				foreach ($in as $key => $value) {
					$setParts[] = "$key = $value";
				}
				$setClause = implode(", ", $setParts);

				$sql = "UPDATE ms_orders SET $setClause WHERE bitrix_id = '".$order['ID']."'";
				$CurDB->query($sql);
				continue;
			}
			$logger->log("LOG", "Обрабатываем заказ {$order['ID']} step2");

			$ms_id = findOrder($order['ACCOUNT_NUMBER'],$order['DB']['source']);
			$logger->log("LOG", "Обрабатываем заказ {$order['ID']} step3");

			if (!empty($ms_id)) {
				echo "Найден в мс ОБВНОЛЯЕМ \n";
				$msOrder = updateMoySkladOrder($order, $products, $ms_id);
				$logger->log("LOG", "Обрабатываем заказ {$order['ID']} step4");
				//file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/local/cron/ms/TRUE_DEBUG.txt", print_r('ОТВЕТ МС', true).PHP_EOL,FILE_APPEND);
				//file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/local/cron/ms/TRUE_DEBUG.txt", print_r($msOrder, true).PHP_EOL,FILE_APPEND);
				if (!isset($msOrder['errors'])) {
					echo "Заказ #{$order['ID']} успешно обновлен в Мой Склад (ID: {$msOrder['id']})\n";

					$in = array(
						"date_change" => "'".date('Y-m-d H:i:s')."'",
						"action" => "'DONE'",
						"answer" => "'".addslashes(json_encode($msOrder, JSON_UNESCAPED_UNICODE))."'",
						"status" => "'0'",
					);

					$setParts = array();
					foreach ($in as $key => $value) {
						$setParts[] = "$key = $value";
					}
					$setClause = implode(", ", $setParts);

					$sql = "UPDATE ms_orders SET $setClause WHERE bitrix_id = '".$order['ID']."'";
					$CurDB->query($sql);
				} else {
					echo "Ошибка при создании обновлении #{$order['ID']} \n";
					$in = array(
						"date_change" => "'".date('Y-m-d H:i:s')."'",
						"action" => "'ERROR'",
						"answer" => "'".addslashes(json_encode($msOrder, JSON_UNESCAPED_UNICODE))."'",
						"status" => "'1'",
					);

					$setParts = array();
					foreach ($in as $key => $value) {
						$setParts[] = "$key = $value";
					}
					$setClause = implode(", ", $setParts);

					$sql = "UPDATE ms_orders SET $setClause WHERE bitrix_id = '".$order['ID']."'";
					$CurDB->query($sql);
				}
			} else {
				echo "Не найден в мс СОЗДАЕМ \n";
				$msOrder = createMoySkladOrder($order, $products, $msUserSettings);
				$logger->log("LOG", "Обрабатываем заказ {$order['ID']} step4.1");
				if (!isset($msOrder['errors'])) {
					echo "Заказ #{$order['ID']} успешно создан в Мой Склад (ID: {$msOrder['id']})\n";

					$in = array(
						"date_insert_ms" => "'".date('Y-m-d H:i:s')."'",
						"date_change" => "'".date('Y-m-d H:i:s')."'",
						"action" => "'DONE'",
						"answer" => "'".addslashes(json_encode($msOrder, JSON_UNESCAPED_UNICODE))."'",
						"status" => "'0'",
					);

					$setParts = array();
					foreach ($in as $key => $value) {
						$setParts[] = "$key = $value";
					}
					$setClause = implode(", ", $setParts);

					$sql = "UPDATE ms_orders SET $setClause WHERE bitrix_id = '".$order['ID']."'";
					$CurDB->query($sql);
				} else {
					echo "Ошибка при создании заказа #{$order['ID']} \n";
					$in = array(
						"date_change" => "'".date('Y-m-d H:i:s')."'",
						"action" => "'ERROR'",
						"answer" => "'".addslashes(json_encode($msOrder, JSON_UNESCAPED_UNICODE))."'",
						"status" => "'1'",
					);

					$setParts = array();
					foreach ($in as $key => $value) {
						$setParts[] = "$key = $value";
					}
					$setClause = implode(", ", $setParts);

					$sql = "UPDATE ms_orders SET $setClause WHERE bitrix_id = '".$order['ID']."'";
					$CurDB->query($sql);
				}
			}
		}
		$logger->log("LOG", "Синхронизация завершена. Обработано заказов: " . count($orders));

		echo "Синхронизация завершена. Обработано заказов: " . count($orders) . "\n";

		$timeEnd = date('Y.m.d G:i:s');
		$arStat = [
			'status' => 'COMPLETE',
			'status_text' => 'Завершено',
			'percent' => 100,
			'time_end' => $timeEnd
		];
		updateStatus($moduleCur, $arStat);

    }
} catch (Exception $e) {
    echo "Ошибка: " . $e->getMessage();
    \Bitrix\Main\Diag\Debug::writeToFile($e->getMessage(), "Ошибка синхронизации", "moysklad_integration.log");
}

$logger->log("LOG", "Конец");
$workers->updateStatus("N");

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_after.php");
