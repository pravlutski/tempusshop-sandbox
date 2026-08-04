<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
CModule::IncludeModule('panel.manager');

global $DB;

$strSql = "SELECT * FROM current_cost_ms";
$results = $DB->Query($strSql, false, $err_mess.__LINE__);
$dataId = [];
while ($row = $results->Fetch()){
  $dataId[$row['model']] = $row['cost'];
}

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

$i = 1;
foreach ($dataId as $key => $value) {
  $sheet->setCellValueExplicit("A" . $i, $key, PHPExcel_Cell_DataType::TYPE_STRING);
  $sheet->setCellValueExplicit("B" . $i, $value, PHPExcel_Cell_DataType::TYPE_STRING);
  $i++;
}

$objWriter = new PHPExcel_Writer_Excel2007($xls);
$dirPath = '/var/www/bitrix/data/www/tempusshop.ru/admin/modules/forTest/export/';
$filename = 'costMS.xlsx';
$objWriter->save( $dirPath . $filename );

 ?>
