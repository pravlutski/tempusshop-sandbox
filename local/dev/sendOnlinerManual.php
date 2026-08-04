<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);
die;
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) {
    die('NOT INCLUDED 1');
}

use Bitrix\Main\Loader;
use Bitrix\Sale;
use Bitrix\Main\UserTable;

if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) {
    die('NOT INCLUDED 2');
}

// Проверяем инициализацию ядра Bitrix
if (!Loader::includeModule('sale') || !Loader::includeModule('main')) {
    die('Не удалось загрузить необходимые модули');
}

class SendOnlinerManual
{
    private $orderId;

    public function __construct($orderId)
    {
        $this->orderId = $orderId;
    }

    public function sendOrder()
    {
        return $this->processOrder('https://tempus.by/local/rest/getonliner.php');
    }

    private function processOrder($endpoint)
    {
        $order = Sale\Order::load($this->orderId);
        if (!$order) {
            return false;
        }


        // User data
        $userId = $order->getUserId();
        $user = UserTable::getById($userId)->fetch();
        $propertyCollection = $order->getPropertyCollection();


        file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/local/dev/ON2.txt', print_r('1',true). PHP_EOL,FILE_APPEND);
        file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/local/dev/ON2.txt', print_r('---',true). PHP_EOL,FILE_APPEND);
        // Get cost data
        $basket = $order->getBasket();
        $basePrice = $basket->getBasePrice();
        $basketPrice = $basket->getPrice();

        // Delivery data
        $shipmentCollection = $order->getShipmentCollection();
        $deliveryBasePrice = 0;
        $deliveryPrice = 0;
        foreach ($shipmentCollection as $shipment) {
            if (!$shipment->isSystem()) {
                $deliveryBasePrice += $shipment->getField('BASE_PRICE_DELIVERY');
                $deliveryPrice += $shipment->getPrice();
            }
        }
        file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/local/dev/ON2.txt', print_r('2',true). PHP_EOL,FILE_APPEND);
        file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/local/dev/ON2.txt', print_r('---',true). PHP_EOL,FILE_APPEND);
        // Payment data
        $paymentCollection = $order->getPaymentCollection();
        $sumPaid = 0;
        foreach ($paymentCollection as $payment) {
            if ($payment->isPaid()) {
                $sumPaid += $payment->getSum();
            }
        }
        file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/local/dev/ON2.txt', print_r('2/3',true). PHP_EOL,FILE_APPEND);
        file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/local/dev/ON2.txt', print_r('---',true). PHP_EOL,FILE_APPEND);
        try {
        $orderData = [
            'ID' => $order->getId(),
            'DATE_INSERT' => $order->getDateInsert()->toString(),
            'STATUS_ID' => $order->getField('STATUS_ID'),
            'PRICE' => $order->getPrice(),
            'ACCOUNT_NUMBER' => $order->getField('ACCOUNT_NUMBER'),
            'CURRENCY' => $order->getCurrency(),
            'USER_ID' => $order->getUserId(),
			//'USER_DESCRIPTION' => $propertyCollection->getItemByOrderPropertyCode('COMMENT')->getValue(),
			      'COMMENTS' => $order->getField('COMMENTS'),
            'BASKET_BASE_PRICE' => $basePrice,
            'BASKET_PRICE' => $basketPrice,
            'DELIVERY_BASE_PRICE' => $deliveryBasePrice,
            'DELIVERY_PRICE' => $deliveryPrice,
            'SUM_PAID' => $sumPaid,
            'USER' => [],
            'BASKET' => [],
            'SHIPMENT' => [],
            'PAYMENT' => [],
        ];

        $orderData['USER'] = [
            'NAME' => $user['NAME'],
            'LAST_NAME' => $user['LAST_NAME'],
            'EMAIL' => $propertyCollection->getItemByOrderPropertyCode('EMAIL')->getValue(),
            'PERSONAL_PHONE' => $propertyCollection->getItemByOrderPropertyCode('PHONE')->getValue(),
            'FIO' => $propertyCollection->getItemByOrderPropertyCode('FIO')->getValue(),
            'LOCATION' => $propertyCollection->getItemByOrderPropertyCode('LOCATION')->getValue(),
            'ADDRESS' => $propertyCollection->getItemByOrderPropertyCode('ADDRESS')->getValue()
        ];
      } catch (Exception $e) {
          file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/local/dev/ON2.txt', "Basket error: ".$e->getMessage()."\n---\n", FILE_APPEND);
          throw $e;
      }
        file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/local/dev/ON2.txt', "3\n---\n", FILE_APPEND);

  // Basket items
  try {
      file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/local/dev/ON2.txt', "Getting basket...\n", FILE_APPEND);
      $basket = $order->getBasket();
      file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/local/dev/ON2.txt', "Basket items count: ".count($basket)."\n", FILE_APPEND);

      $counter = 0;
      foreach ($basket as $basketItem) {
          $counter++;
          file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/local/dev/ON2.txt', "Processing item $counter\n", FILE_APPEND);

          try {
              $productId = $basketItem->getProductId();
              file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/local/dev/ON2.txt', "Product ID: $productId\n", FILE_APPEND);

              $article = '';
              $arFilter = array('IBLOCK_ID' => 16, 'ID' => $productId);
              $arSelect = array('ID', 'PROPERTY_CML2_ARTICLE');

              file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/local/dev/ON2.txt', "Calling CIBlockElement::GetList\n", FILE_APPEND);
              $res = CIBlockElement::GetList(array(), $arFilter, false, false, $arSelect);
              file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/local/dev/ON2.txt', "CIBlockElement::GetList completed\n", FILE_APPEND);

              if ($ob = $res->GetNext()) {
                  $article = $ob['PROPERTY_CML2_ARTICLE_VALUE'];
                  file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/local/dev/ON2.txt', "Article found: $article\n", FILE_APPEND);
              }

              $orderData['BASKET'][] = [
                  'PRODUCT_ID' => $productId,
                  'NAME' => $basketItem->getField('NAME'),
                  'QUANTITY' => $basketItem->getQuantity(),
                  'PRICE' => $basketItem->getPrice(),
                  'BASE_PRICE' => $basketItem->getBasePrice(),
                  'DISCOUNT_PRICE' => $basketItem->getDiscountPrice(),
                  'ARTICLE' => $article,
              ];

              file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/local/dev/ON2.txt', "Item $counter processed successfully\n", FILE_APPEND);
          } catch (Exception $e) {
              file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/local/dev/ON2.txt', "Error in item $counter: ".$e->getMessage()."\n---\n", FILE_APPEND);
              continue;
          }
      }

      file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/local/dev/ON2.txt', "All basket items processed\n", FILE_APPEND);
  } catch (Exception $e) {
      file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/local/dev/ON2.txt', "Basket error: ".$e->getMessage()."\n---\n", FILE_APPEND);
      throw $e;
  }

  file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/local/dev/ON2.txt', "4\n---\n", FILE_APPEND);
        // Shipment data
        $shipmentCollection = $order->getShipmentCollection();
        foreach ($shipmentCollection as $shipment) {
            if (!$shipment->isSystem()) {
                $orderData['SHIPMENT'][] = [
                    'DELIVERY_ID' => $shipment->getDeliveryId(),
                    'DELIVERY_NAME' => $shipment->getDeliveryName(),
                    'PRICE_DELIVERY' => $shipment->getField('PRICE_DELIVERY'),
                ];
            }
        }
        file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/local/dev/ON1.txt', print_r($orderData,true). PHP_EOL,FILE_APPEND);
        // Payment data
        $paymentCollection = $order->getPaymentCollection();
        foreach ($paymentCollection as $payment) {
            $orderData['PAYMENT'][] = [
                'PAY_SYSTEM_ID' => $payment->getPaymentSystemId(),
                'PAY_SYSTEM_NAME' => $payment->getPaymentSystemName(),
                'SUM' => $payment->getSum(),
            ];
        }
        file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/local/dev/ON1.txt', print_r($orderData,true). PHP_EOL,FILE_APPEND);

        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(['order_data' => json_encode($orderData)]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        curl_close($ch);

        if ($response) {
            $responseObj = json_decode($response, true);
            return $responseObj['new_order_id'] ?? false;
        }

        return false;
    }
}
$o = new SendOnlinerManual( $argv[1] );
var_dump( $o->sendOrder() );
// Пример использования:
// $orderSender = new OrderSender($orderId);
// $resultRu = $orderSender->sendOrderRu(); // Для RU версии
// $resultBy = $orderSender->sendOrderBy(); // Для BY версии
