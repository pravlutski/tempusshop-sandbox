<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
if(!CModule::IncludeModule('panel.manager'))return;

if ( empty($_POST) ) throw new Exception("Empty Data");
if ( empty($_POST['orders']) ) throw new Exception("Empty orders Data");

$data = [
  'orders' => $_POST['orders'],
];

die("WRONG PROCESSOR");

$url = 'https://tempus.ru/local/ajax/order_arrive_notify/';
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
  "X-PUPA-OR-LUPA: d546623077cc9c70d62c06a776ea9becc064cf134221fef3ab170f019e701b4d",
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");

$res = curl_exec( $ch );

curl_close( $ch );
// echo $res;

$result = json_decode($res, true);

if ( $result['status'] != 'OK' ) throw new Exception("Error code: {$result['code']}");

global $USER;
$login = $USER->getLogin();

$dict = [
  'Already set' => 'Значение уже установлено',
  'Success' => 'Значение успешно установлено',
  'Error' => 'Произошла внутренняя ошибка',
];

$dateTime = date('Y.m.d G:i');

$message = '';

foreach ( $result['orders'] as $number => $info ){
  $color = ($info['status'] == 'BAD') ? 'red' : 'black';
  $msg = $dict[$info['message']];
  $message .= "<p class='log-row' style='color: {$color}'><b>{$number}</b>: {$msg} (<b>Пользователь</b>: {$login}, <b>Время</b>: {$dateTime})</p>" . PHP_EOL;
}

$dateFileName = date('Y-m-d');

file_put_contents(
  "/var/www/bitrix/data/www/tempusshop.ru/admin/utilities/order_arrive_notify/logs/log_{$dateFileName}.txt",
  $message . '<hr>' . PHP_EOL,
  FILE_APPEND
);
 ?>
