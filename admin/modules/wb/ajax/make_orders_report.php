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

if ( empty($_POST['cabinet']) ){
  echo '<div class="download-btn error-msg">Ошибка! Не указан кабинет</div>';
  die;
}

$cabinet = $_POST['cabinet'];
$mode = empty($_POST['mode']) ? 'csv' : $_POST['mode'];

$strSql = "SELECT * FROM wdhs_wb_orders AS ord
JOIN wdhs_wb_order_products AS prod
ON ord.order_id = prod.order_id WHERE ord.cabinet = '{$cabinet}'";

if ( !empty($_POST['dateFrom']) ){
  // $dateFrom = strtotime( $_POST['dateFrom'] );
  $dateFrom = $_POST['dateFrom'];
  $strSql .= " AND ord.created_at >= '{$dateFrom}'";
}
if ( !empty($_POST['dateTo']) ){
  // $dateTo = strtotime( $_POST['dateTo'] );
  $dateTo = $_POST['dateTo'];
  $strSql .= " AND ord.created_at <= '{$dateTo}'";
}
// var_dump( $strSql );
$result = $DB->Query($strSql, false, $err_mess.__LINE__);
while ( $row = $result->Fetch() ){
  $exportData[] = [
    $row['order_bid'],
    $row['order_id'],
    $row['rid'],
    $row['uid'],
    $row['created_at'],
    $row['delivery_type'],
    $row['warehouse_id'],
    $row['status'],
    $row['bitrix_id'],
    $row['vendor_code'],
    $row['nmid'],
    $row['price'],
    $row['cost'],
    $row['quantity'],
  ];
}
// var_dump($exportData);
// die;
unset($row);

$header = [
  'ID Заказа Битрикс',
  'ID - Идентификатор сборочного задания в Маркетплейсе',
  'RID - Идентификатор сборочного задания в системе Wildberries',
  'UID - Идентификатор транзакции для группировки сборочных заданий',
  'Дата создания сборочного задания',
  'Тип доставки',
  'Идентификатор склада продавца',
  'Статус',
  'ID товара Битрикс',
  'Артикул',
  'NMID',
  'Цена',
  'Себестоимость',
  'Количество',
];
$alphabet = range('A','Z');

$link = "/admin/modules/wb/export/orders_{$cabinet}.{$mode}";
$filename = $_SERVER['DOCUMENT_ROOT'] . $link;
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
