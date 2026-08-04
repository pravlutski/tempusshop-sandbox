<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

if(!CModule::IncludeModule('panel.manager')) throw new Exception("Panel.Manager is disabled");
if(!CModule::IncludeModule('crm_courier')) throw new Exception("CRM module is disabled");

if ( empty($_POST) ) throw new Exception("Empty Data");
if ( empty($_POST['orders']) ) throw new Exception("Empty orders Data");

$crm = new CCourier();

$filter = [
  'numbers' => $_POST['orders'],
];

for ($i = 0; $i < 5; $i++){
  $response = $crm->getOrderCrm( $filter );

  if ( $response['statusCode'] != 200 ){
    $date = date('Y-m-d');
    file_put_contents(
      "/var/www/bitrix/data/www/tempusshop.ru/admin/utilities/order_arrive_notify/errorLogs/error_{$date}.log",
      print_r($response). PHP_EOL,
      FILE_APPEND
    );
    throw new Exception("Cannot get CRM orders");
  }

  $orders = $response['response']['orders'] ?? [];
  if ( count($orders) == count($_POST['orders']) ) break;
  
  sleep( 2 );
}


if ( empty($orders) ) throw new Exception("Order(s) not found");

global $USER;
$login = $USER->getLogin();

$dateTime = date('Y.m.d G:i');
$messageTemplate = "<p class='log-row' style='color: %s'><b>%s</b>: %s (<b>Пользователь</b>: {$login}, <b>Время</b>: {$dateTime})</p>";

$dict = [
  'AlreadySet' => 'Значение уже установлено',
  'Success' => 'Значение успешно установлено',
  'Error' => 'Произошла внутренняя ошибка',
  'NotFound' => 'Заказ не найден в CRM',
];

$log = [];

$orders = array_column( $orders, null, 'number' );

foreach ( $_POST['orders'] as $number ){
  if ( $orders[$number] ) continue;
  $log[] = sprintf( $messageTemplate, 'red' ,$number, $dict['NotFound'] );
}


foreach ( $orders as $number => $order ){
  if ( $order['customFields']['instock'] == true ) {
    $log[] = sprintf( $messageTemplate, 'red', $number, $dict['AlreadySet'] );
    continue;
  }
  $data = [
    'id' => $order['id'],
    'customFields' => [
      'instock' => true,
    ],
  ];

  $response = $crm->setOrder($data, 'id', 'tempus-ru');
  if ( $response['statusCode'] != 200 ){
    $log[] = sprintf( $messageTemplate, 'red', $number, $dict['Error'] );
    continue;
  }
  $log[] = sprintf( $messageTemplate, 'black', $number, $dict['Success'] );
  usleep( 500000 );
}

$message = implode('', $log);
$dateFileName = date('Y-m-d');

file_put_contents(
  "/var/www/bitrix/data/www/tempusshop.ru/admin/utilities/order_arrive_notify/logs/log_{$dateFileName}.txt",
  $message . '<hr>' . PHP_EOL,
  FILE_APPEND
);
 ?>
