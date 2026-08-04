<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

$priceType = $_GET['priceType'];

if ( !in_array($priceType, ['RU', 'BY', 'OS', 'WB']) ){
  header('HTTP/1.1 400 Bad Request');
  die('invalidbody');
}

$db = \Bitrix\Main\Application::getConnection();
$strSql = "SELECT * FROM ci_price_set WHERE PRICE_TYPE = '{$priceType}'";
$rows = $db->query( $strSql );

$ar = [];
$i = 0;
while( $row = $rows->Fetch() ){
  if ( $i == 0 ){
    $ar[] = array_keys( $row );
    $ar[] = array_values( $row );
    $i++;
    continue;
  }
  $ar[] = array_values( $row );
}

$file = array_map( fn($item) => implode(',',$item), $ar );
$file = implode(PHP_EOL, $file);
$filename = "/var/www/bitrix/data/www/tempusshop.ru/admin/modules/forTest/export/ci_price_set.csv";
file_put_contents($filename, $file);

header('Content-Description: File Transfer');
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($filename));
readfile( $filename );
 ?>
