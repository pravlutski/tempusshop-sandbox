<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
require $_SERVER['DOCUMENT_ROOT'] . '/local/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
if (!class_exists('SpreadsheetReader')){
  require($_SERVER["DOCUMENT_ROOT"] . '/bitrix/php_interface/include/classes/excel/excel_reader.php');
  require($_SERVER["DOCUMENT_ROOT"] . '/bitrix/php_interface/include/classes/excel/SpreadsheetReader.php');
  require($_SERVER["DOCUMENT_ROOT"] . '/bitrix/php_interface/include/classes/phpexcel/PHPExcel/IOFactory.php');
}

$cabinet = $_POST['cabinet'];
$mode = empty($_POST['mode']) ? 'csv' : $_POST['mode'];
$link = "/admin/modules/ozon2/export/orders_ti.{$mode}";
$filename = $_SERVER['DOCUMENT_ROOT'] . $link;

$strSql = "SELECT * FROM wdhs_ozon_orders_ti AS ord
JOIN wdhs_ozon_order_products_ti AS prod
ON ord.posting_number = prod.posting_number";

if ( !empty($_POST['dateFrom']) ){
  $dateFrom = $_POST['dateFrom'];
  $strSql .= " AND ord.in_process_at >= '{$dateFrom}'";
}
if ( !empty($_POST['dateTo']) ){
  $dateTo = $_POST['dateTo'];
  $strSql .= " AND ord.in_process_at <= '{$dateTo}'";
}

$result = $DB->Query($strSql, false, $err_mess.__LINE__);
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

$header = [
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
$alphabet = range('A','Z');

if ( $mode == 'csv'){
  $output = fopen($filename,'w');
  fputcsv( $output, $header );
  foreach ( $exportData as $row ){
    fputcsv( $output, $row );
  }
  fclose($output);
}else{
  $xls = new PHPExcel();
  $xls->setActiveSheetIndex(0);
  $sheet = $xls->getActiveSheet();
  $sheet->setTitle('Report');
  foreach ( $header as $key => $name ){
    $sheet->setCellValueExplicit("{$alphabet[$key]}1", $name, PHPExcel_Cell_DataType::TYPE_STRING);
  }
  $index = 2;
  foreach ($exportData as $row) {
    foreach ($row as $key => $value) {
      $sheet->setCellValueExplicit("{$alphabet[$key]}{$index}", $value, PHPExcel_Cell_DataType::TYPE_STRING);
    }
    $index++;
  }
  $objWriter = new PHPExcel_Writer_Excel2007($xls);
  $objWriter->save( $filename );
}

 ?>
<? if ( $mode == 'csv' ):?>
  <a href="<?echo $link;?>" class="download-btn btn btn-primary" download>Сохранить CSV</a>
<?else:?>
  <a href="<?echo $link;?>" class="download-btn btn btn-primary" download>Сохранить XLSX</a>
<?endif;?>
