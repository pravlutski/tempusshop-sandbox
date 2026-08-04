<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);
ob_implicit_flush(true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

use Bitrix\Main\Loader,
    Bitrix\Main\ModuleManager,
    Bitrix\Iblock,
    Bitrix\Catalog,
    Bitrix\Main\Localization\Loc,
    \Bitrix\Main\Config\Option,
    Bitrix\Currency,
    Bitrix\Currency\CurrencyManager,
    Bitrix\Sale\Order,
    Bitrix\Sale\Basket,
    Bitrix\Sale\Delivery,
    Bitrix\Sale\PaySystem,
    Bitrix\Highloadblock as HL,
    Bitrix\Main\Entity,
    Bitrix\Main\Application,
    Bitrix\Main\Type,
    Bitrix\Main\Web\Json;


Bitrix\Main\Loader::includeModule("main");
Bitrix\Main\Loader::includeModule("sale");
Bitrix\Main\Loader::includeModule("catalog");
Bitrix\Main\Loader::includeModule("iblock");

function processOrders():void
{
  $currency = strval($options['currency'] ?? 'RUB');
  $siteId = strval($options['site_id'] ?? 's1');

  $orderBX = \Bitrix\Sale\Order::create($siteId, 182118, $currency );

  // Работаем с корзиной
  $basket = \Bitrix\Sale\Basket::create( $siteId );
  $orderSum = 2000;

  $arItem = [
    'PRODUCT_ID' => 212139,
    'CATALOG_XML_ID' => 212139,
    'NAME' => 'Daniel Klein 13935-1',
    'BASE_PRICE' => 2000,
    'PRICE' => 2000,
    'CURRENCY' => $orderBX->getCurrency(),
    'QUANTITY' => floatval(1),
    'LID' => 's1',
    // 'PRODUCT_PROVIDER_CLASS' => 'CCatalogProductProvider',
    'CUSTOM_PRICE' => 'Y',
    'CAN_BUY' => 'Y',
  ];
  // $arItem = [
  //   'PRODUCT_ID' => 1002,
  //   'CATALOG_XML_ID' => 574,
  //   'NAME' => 'Casio GA-100-1A1',
  //   'BASE_PRICE' => 2000,
  //   'PRICE' => 2000,
  //   'CURRENCY' => $orderBX->getCurrency(),
  //   'QUANTITY' => 1,
  //   'LID' => 's1',
  //   'PRODUCT_PROVIDER_CLASS' => 'CCatalogProductProvider',
  //   'CUSTOM_PRICE' => 'Y',
  //   'CAN_BUY' => 'Y',
  // ];
  $currencyCode = $product['currency_code'];
  $basketItem = $basket->createItem( 'catalog', $arItem['PRODUCT_ID'] );
  $basketItem = $basketItem->setFields( $arItem );
  var_dump($basket);
  $orderBX->setBasket($basket);
  // Работаем с заказом
  $orderBX->setPersonTypeId( 1 );
  $orderBX->setFields([
  // 'CURRENCY' => strval( $currencyCode ),
  'USER_DESCRIPTION' => 'Заказ поступил с Ozon.',
  'USER_DESCRIPTION' => 'Ozon TEST',
  'STATUS_ID' => 'TA'
  ]);
  // $orderBX->setBasket( $basket );

  // Сохраненяем пользовательские свойства
  $propertyCollection = $orderBX->getPropertyCollection();

  $propertyFields = [
  'tpl_integration_type' => 'test',
  'OZON_NUMBER' => "00000000000-1111-1",
  'OZON_DATE' => date( 'd.m.Y' ),
  ];
  foreach ( $propertyFields as $code => $value ) {
    $prop = $propertyCollection->getItemByOrderPropertyCode( $code );
    if ( $prop ) $prop->setValue( $value );
  }

  // Добавляем службу доставки
  $shipmentCollection = $orderBX->getShipmentCollection();
  $shipment = $shipmentCollection->createItem();
  $service = Delivery\Services\Manager::getById( 68 );
  $shipment->setFields([
  'DELIVERY_ID' => $service['ID'],
  'DELIVERY_NAME' => $service['NAME'],
  ]);
  $shipmentItemCollection = $shipment->getShipmentItemCollection();
  foreach ($basket as $item) {
    var_dump( $item->getQuantity() );
    $shipmentItem = $shipmentItemCollection->createItem( $item );
    $shipmentItem->setQuantity( $item->getQuantity() );
  }

  // Добавляем платежную систему
  $paymentCollection = $orderBX->getPaymentCollection();
  $payment = $paymentCollection->createItem();
  $paySystemService = PaySystem\Manager::getObjectById( 34 );
  $payment->setFields([
  'PAY_SYSTEM_ID' => $paySystemService->getField('PAY_SYSTEM_ID'),
  'PAY_SYSTEM_NAME' => $paySystemService->getField('NAME'),
  'SUM' => $orderSum,
  ]);

  // Сохраняем заказ
  // $orderBX->doFinalAction(true);
  $result = $orderBX->save();
  //
  // if ( !$result->isSuccess() ){
  //   var_dump( 'Failed to save order: ' . $result->getError() );
  // }
}

processOrders();

// 212139
 ?>
