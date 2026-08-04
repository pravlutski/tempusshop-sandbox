<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
CModule::IncludeModule('panel.manager');

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
if (!class_exists('SpreadsheetReader')){
  require($_SERVER["DOCUMENT_ROOT"] . '/bitrix/php_interface/include/classes/excel/excel_reader.php');
  require($_SERVER["DOCUMENT_ROOT"] . '/bitrix/php_interface/include/classes/excel/SpreadsheetReader.php');
  require($_SERVER["DOCUMENT_ROOT"] . '/bitrix/php_interface/include/classes/phpexcel/PHPExcel/IOFactory.php');
}

$xls = new PHPExcel();
$xls->setActiveSheetIndex(0);
$sheet = $xls->getActiveSheet();
$sheet->setTitle('listOne');

$json = file_get_contents( '/var/www/bitrix/data/www/tempusshop.ru/admin/modules/forTest/moyskladTest/file.txt' );
$items = json_decode( $json, 1 );

$i = 0;
foreach( $items as $model => $barcodes ){
  $arBarcodes = array_filter( $barcodes );
  $import = implode( ',', $arBarcodes );
  $sheet->setCellValueExplicit("A" . $i, $model, PHPExcel_Cell_DataType::TYPE_STRING);
  $sheet->setCellValueExplicit("B" . $i, $import, PHPExcel_Cell_DataType::TYPE_STRING);
  $i++;
}

$objWriter = new PHPExcel_Writer_Excel2007($xls);
$dirPath = '/var/www/bitrix/data/www/tempusshop.ru/admin/modules/forTest/moyskladTest/';
$filename = 'table_barcodes.xlsx';
$objWriter->save( $dirPath . $filename );

 ?>
