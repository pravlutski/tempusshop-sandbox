<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
require $_SERVER['DOCUMENT_ROOT'] . '/local/vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

CModule::IncludeModule('panel.manager');
$dbPanel = new DBPanel;

$strSql = "SELECT * FROM ozon_model_collection ORDER BY code ASC";
$res = $dbPanel->query( $strSql );

$rows = $dbPanel->fetchAll( $res );

$data = [];
foreach ( $rows as $row ){
  $data[] = [
    'model' => $row['model'],
    'code' => $row['code']
  ];
}

if (!class_exists('SpreadsheetReader')){
  require($_SERVER["DOCUMENT_ROOT"] . '/bitrix/php_interface/include/classes/excel/excel_reader.php');
  require($_SERVER["DOCUMENT_ROOT"] . '/bitrix/php_interface/include/classes/excel/SpreadsheetReader.php');
  require($_SERVER["DOCUMENT_ROOT"] . '/bitrix/php_interface/include/classes/phpexcel/PHPExcel/IOFactory.php');
}

$xls = new PHPExcel();
$xls->setActiveSheetIndex(0);
$sheet = $xls->getActiveSheet();
$sheet->setTitle('listOne');
$sheet->setCellValueExplicit("A1", 'Модель', PHPExcel_Cell_DataType::TYPE_STRING);
$sheet->setCellValueExplicit("B1", 'Склейка', PHPExcel_Cell_DataType::TYPE_STRING);

$sheet->getStyle("A1")->getFont()->setBold(true);
$sheet->getStyle("B1")->getFont()->setBold(true);
$sheet->getColumnDimension("A")->setWidth(25);

$i = 2;
foreach ( $data as $row ){
  $sheet->setCellValueExplicit("A" . $i, $row['model'], PHPExcel_Cell_DataType::TYPE_STRING);
  $sheet->setCellValueExplicit("B" . $i, $row['code'], PHPExcel_Cell_DataType::TYPE_STRING);
  $i++;
}

$objWriter = new PHPExcel_Writer_Excel2007($xls);
$dirPath = '/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/ozon/analytics/export/';
$filename = 'Groups_OZON.xlsx';
$objWriter->save( $dirPath . $filename );
$path = $dirPath . $filename;

if ( file_exists($path) ){

  header('Content-Description: File Transfer');
  header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
  header('Content-Disposition: attachment; filename="' . basename($path) . '"');
  header('Expires: 0');
  header('Cache-Control: must-revalidate');
  header('Pragma: public');
  header('Content-Length: ' . filesize($path));

  readfile($path);
}else{
  echo 'Файл не найден';
}
 ?>
