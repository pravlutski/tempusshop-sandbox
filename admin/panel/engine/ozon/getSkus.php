<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);
ob_implicit_flush( true );

require( $_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php" );


$panel = new DBPanel;

$skus = $panel->select(['*'], 'ozon_sku_dict_IP')->make();
$dict = array_column($skus, 'sku', 'model');

$json = file_get_contents('/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/ozon/jsonArr.json');
$items = json_decode($json, true);

$result = [];
$message = '';
foreach ( $items as $item ){
  if ( !isset($dict[$item]) ) continue;
  $result[] = $dict[$item];
  $message .= $dict[$item] . PHP_EOL;
}

file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/ozon/incorrectItems.txt", $message);

 ?>
