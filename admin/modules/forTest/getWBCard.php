<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);
ob_implicit_flush(true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

$cabinet = $argv[1];
$wbarticle = $argv[2];

if ( empty($cabinet) || empty($cabinet) ) die("One of parameters' missing\n");
if ( !in_array( $cabinet, ['WR', 'WT', 'TL'] ) ) die("Forbidden cabinet value\n");

$db = \Bitrix\Main\Application::getConnection();
$strSql = "SELECT api FROM wdhs_wb_main_settings WHERE cabinet = '{$cabinet}'";
$key = $db->Query($strSql)->Fetch()['api'];


$data = [
  'settings' => [
    'sort' => ['ascending' => false],
    'cursor' => ['limit' => 5],
    'filter' => ['withPhoto' => -1, 'textSearch' => strval( $wbarticle )],
  ],
];
$res = request(
  'https://content-api.wildberries.ru/content/v2/get/cards/list?locale=ru',
  ["Content-Type: application/json", "Authorization: {$key}"],
  json_encode( $data ),
  'POST'
);

var_dump( $res );

function request( $url, $headers = [], $body = '', $method = 'GET' )
{
  $options = [
    CURLOPT_HTTPHEADER => $headers,
    CURLOPT_POSTFIELDS => $body,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST => $method,
  ];

  $ch = curl_init( $url );
  curl_setopt_array( $ch, $options );
  $res = curl_exec( $ch );

  curl_close( $ch );

  return json_decode( $res, true );
}
 ?>
