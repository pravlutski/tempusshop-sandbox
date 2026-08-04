<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
global $DB;
$source = $_GET['source'];

$strSql = "SELECT * FROM individual_markups WHERE source = '{$source}'";
$res = $DB->Query($strSql, false, $err_mess.__LINE__);

$data = [];

while ( $row = $res->Fetch() ){
  $data[] = [
    'model' => $row['model'],
    'markup' => $row['markup'],
    'source' => $row['source'],
  ];
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
foreach ($data as $a) {
  $sheet->setCellValueExplicit("A" . $i, $a['model'], PHPExcel_Cell_DataType::TYPE_STRING);
  $sheet->setCellValueExplicit("B" . $i, $a['markup'], PHPExcel_Cell_DataType::TYPE_STRING);
  $sheet->setCellValueExplicit("C" . $i, $a['source'], PHPExcel_Cell_DataType::TYPE_STRING);
  $i++;
}

$objWriter = new PHPExcel_Writer_Excel2007($xls);
$dirPath = '/var/www/bitrix/data/www/tempusshop.ru/admin/modules/forTest/export/';
$filename = 'individual_markups_'.$source.'.xlsx';
$objWriter->save( $dirPath . $filename );

$path = $dirPath . $filename;

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
