<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

$main = \Bitrix\Main\Application::getConnection();

$strSql = "SELECT PRODUCT_ID, ARTICLE, BARCODE, TIMESTAMP FROM ci_catalog_barcode";
$rows = $main->query( $strSql );

$result[] = ["PRODUCT_ID", "ARTICLE", "BARCODE", "TIMESTAMP"];

while( $row = $rows->fetch() ){
  $result[] = array_values($row);
}


$content = array_map( fn($e) => implode(';', $e), $result );
$content = implode(PHP_EOL, $content);

file_put_contents(
  "/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/common/sheetsExport/barcodesExport.csv",
  $content
);
 ?>
