<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
require($_SERVER["DOCUMENT_ROOT"]."/admin/modules/descGen/classes/DescriptionGenerator.php");

CModule::IncludeModule('panel.manager');

function getDeliveryName( object $order ):string
{
  $shipmentCollection = $order->getShipmentCollection();

  if ( empty($shipmentCollection) ) return '';

  foreach ( $shipmentCollection as $shipment ){
    $delivery = $shipment->getDelivery();

    return $delivery->getName();
  }

  return '';
}

function buildComment( string $orderComment, string $deliveryName ):string
{
  if ( empty($orderComment) && empty($deliveryName) ) return 'Нет ничего ууу';
  $comment = "Комментарий к заказу: %s; \nСпособ доставки: %s";
  $text1 = empty($orderComment) ? 'не указан' : $orderComment;
  $text2 = empty($deliveryName) ? 'не указан' : $deliveryName;

  return sprintf( $comment, $text1, $text2 );
}

$filter = [
    'filter' => [
        // 'ID' => array_keys($ordersBD)
        'ID' => [718330]
        // 'ID' => [718435]
    ],
    'select' => ['ID', 'DATE_INSERT', 'STATUS_ID', 'PRICE', 'CURRENCY', 'USER_DESCRIPTION', 'USER_ID','ACCOUNT_NUMBER', 'COMMENTS'],
    'order' => ['ID' => 'ASC'],
    'limit' => 1000
];

$dbOrders = \Bitrix\Sale\Order::getList($filter);
$orders = [];

while ($row = $dbOrders->fetch()) {
  // var_dump($row);
  $order = \Bitrix\Sale\Order::load($row['ID']);
  $deliveryName = getDeliveryName( $order );
  $res = buildComment( $row['COMMENTS'], $deliveryName );
}

print_r( $res );

?>
