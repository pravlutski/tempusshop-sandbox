<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");


if ( empty($_GET['advertId']) ){
  header('HTTP/1.1 400 Bad Request');
  die;
}

$panel = new DBPanel;

$rows = $panel->select(['*'], 'am_campaign_products')->where('advertId', $_GET['advertId'])->make();

$productIds = array_map( fn($item) => $item['platform_product_id'], $rows );
$text = implode( PHP_EOL, $productIds );

$path = "{$_SERVER['DOCUMENT_ROOT']}/admin/panel/common/adverts/export/export.csv";
file_put_contents( $path, $text );

// Убеждаемся, что до функции header() не было никакого вывода
header('Content-Description: File Transfer');
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="export_'.$_GET['advertId'].'.csv"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($path));
// Убеждаемся, что нет пробелов перед readfile()
readfile($path);
 ?>
