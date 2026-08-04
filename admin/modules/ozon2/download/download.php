<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");


$strSql = "SELECT * FROM wdhs_ozon_orders AS ord
JOIN wdhs_ozon_order_products AS prod
ON ord.posting_number = prod.posting_number";

$result = $DB->Query($strSql, false, $err_mess.__LINE__);
$headCsv = [
  'ID Заказа Битрикс',
  'ID Заказа OZON',
  'Номер заказа OZON',
  'Номер отправления',
  'Дата создания',
  'Дата доставки',
  'Статус',
  'ID товара Битрикс',
  'Артикул',
  'Цена',
  'Себестоимость',
  'Количество',
  'Код валюты',
];
$exportData = [];
while ( $row = $result->Fetch() ){
  $exportData[] = [
    $row['order_bid'],
    $row['order_id'],
    $row['order_number'],
    $row['posting_number'],
    $row['in_process_at'],
    $row['shipment_date'],
    $row['status'],
    $row['bitrix_id'],
    $row['vendor_code'],
    $row['price'],
    $row['cost'],
    $row['quantity'],
    $row['currency_code']
  ];
}
// var_dump($exportData);
// die;
unset($row);

$filename = '/var/www/bitrix/data/www/tempusshop.ru/admin/modules/ozon2/download/orders_ip.csv';
$link = '/admin/modules/ozon2/download/orders_ip.csv';
$output = fopen($filename,'w');
fputcsv( $output, $headCsv );
foreach ( $exportData as $row ){
  fputcsv( $output, $row );
}
fclose($output);
 ?>

 <a href="<?echo $link;?>" download>ТЫК</a>
