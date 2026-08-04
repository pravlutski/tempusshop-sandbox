<?php
$data = $_POST;
$token = "2399b3100adc0e617eb1757b7bf974e8f1ab18782fc6ca4cffc576f7fb9f938b";

if ( $_SERVER['HTTP_X_KEY'] !== $token ) {
  header('HTTP/1.1 403 Forbidden');
  die;
}

if ( empty($data['cookie']) ) {
  header('HTTP/1.1 400 Bad Request');
  die('invalidbody');
}

$cookieChunks = explode('.', $data['cookie']);

if ( count($cookieChunks) != 5 ){
  header('HTTP/1.1 402 Bad Request');
  die('invalidbodycontent');
}

$dataInsert = [
  "cookie" => "x_wbaas_token=" . $data['cookie'],
  "expires_at" => "2026-08-01",
  "date_inset" => date('Y-m-d G:i:s'),
];

file_put_contents(
  "/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/wb/configs/analytics_cookie.json",
  json_encode($dataInsert)
);
 ?>
