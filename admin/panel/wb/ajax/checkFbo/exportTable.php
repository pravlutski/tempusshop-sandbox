<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
set_time_limit(0);

CModule::IncludeModule('panel.manager');

require $_SERVER['DOCUMENT_ROOT'] . '/local/vendor/autoload.php';

if (!class_exists('SpreadsheetReader')){
  require($_SERVER["DOCUMENT_ROOT"] . '/bitrix/php_interface/include/classes/excel/excel_reader.php');
  require($_SERVER["DOCUMENT_ROOT"] . '/bitrix/php_interface/include/classes/excel/SpreadsheetReader.php');
  require($_SERVER["DOCUMENT_ROOT"] . '/bitrix/php_interface/include/classes/phpexcel/PHPExcel/IOFactory.php');
}

$xls = new PHPExcel();
$xls->setActiveSheetIndex(0);
$sheet = $xls->getActiveSheet();
$sheet->setTitle('listOne');

$db = new DBPanel();
$strSql = "SELECT * FROM ms_turnover";
$res = $db->query( $strSql );
$rows = $db->fetchAll( $res );
$i = 1;
foreach ( $rows as $row ){
  $sheet->setCellValueExplicit("A{$i}", $row['model'], PHPExcel_Cell_DataType::TYPE_STRING);
  $sheet->setCellValueExplicit("B{$i}", $row['quantity'], PHPExcel_Cell_DataType::TYPE_STRING);
  $i++;
}
$objWriter = new PHPExcel_Writer_Excel2007($xls);
$dirPath = $_SERVER["DOCUMENT_ROOT"] . '/admin/panel/engine/wb/export/';
$filename = 'export_ms.xlsx';
$path = $dirPath . $filename;
$objWriter->save( $path );

if ( file_exists($path) ){
  // Убеждаемся, что до функции header() не было никакого вывода
  header('Content-Description: File Transfer');
  header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
  header('Content-Disposition: attachment; filename="' . basename($path) . '"');
  header('Expires: 0');
  header('Cache-Control: must-revalidate');
  header('Pragma: public');
  header('Content-Length: ' . filesize($path));
  // Убеждаемся, что нет пробелов перед readfile()
  readfile($path);
}else{
  echo 'Файл не найден';
}
 ?>
