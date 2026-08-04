<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
CModule::IncludeModule("panel.manager");

require $_SERVER['DOCUMENT_ROOT'] . '/local/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

global $DB;

if (!class_exists('SpreadsheetReader')){
  require($_SERVER["DOCUMENT_ROOT"] . '/bitrix/php_interface/include/classes/excel/excel_reader.php');
  require($_SERVER["DOCUMENT_ROOT"] . '/bitrix/php_interface/include/classes/excel/SpreadsheetReader.php');
  require($_SERVER["DOCUMENT_ROOT"] . '/bitrix/php_interface/include/classes/phpexcel/PHPExcel/IOFactory.php');
}

if ($_FILES['supplier']['error'] === UPLOAD_ERR_OK && $_FILES['chronos']['error'] === UPLOAD_ERR_OK) {
    $filenameS = $_FILES['supplier']['tmp_name'];
    $filenameC = $_FILES['chronos']['tmp_name'];
  }
  else{
    die('Ошибка загрузки файла');
  }

function parseXLS($filename){
  $xls = PHPExcel_IOFactory::load($filename);
  $xls->setActiveSheetIndex(0);
  $sheet = $xls->getActiveSheet();
  $ar = array();
  foreach ($sheet->toArray() as $row) {
    $ar[] = $row;
  }

  $arUp = [];
  foreach($ar as $key => $row){
    //$arUp[] = array_map('strtoupper', $row);
    $arUp[] = array_map(function($str){
      return mb_strtoupper($str, "UTF-8");
    }, $row);
  }
  return $arUp;
}

//Парсим файл поставщика и получаем преобразованные артикулы
$arSupplier = parseXLS($filenameS);

$columns = ['code' => 0, 'article' => 1, 'count' => 5];
$suppsArts = [];
foreach ($arSupplier as $row) {
  // var_dump($row);
  // if (array_search('НАИМЕНОВАНИЕ ТОВАРА', $row)){
  //   $columns['code'] = 0;
  //   $columns['article'] = 1;
  //   $columns['count'] = 4;
  // }
  // var_dump($row[]);
  if ( preg_match('/[0-9]+/', $row[$columns['code']]) ){
    if ( !empty($row[$columns['article']]) ){
      $suppsArts[]['artSupp'] = str_replace(' ', '', $row[$columns['article']]);
    }
  }
}

//Парсим файл Хроноса
$arChronos = parseXLS($filenameC);
$columns = ['code' => 0, 'article' => 0, 'count' => 1];
$chronosArts = [];
foreach ($arChronos as $row) {
  if ( preg_match('/[A-Za-z\&]+\s[A-Za-z0-9^(^)\W]+/', $row[$columns['article']]) ){
    if ( !empty($row[$columns['article']]) ){
      $artNumber = explode(' ', $row[$columns['article']])[1];
      $artNumber = str_replace('.', '', $artNumber);
      $chronosArts[$artNumber] = $row[$columns['count']];
    }
  }
}

$objReader = PHPExcel_IOFactory::createReaderForFile($filenameS);
$objPHPExcel = $objReader->load($filenameS);
$sheet = $objPHPExcel->getActiveSheet();
for ( $i = 1; $i < count($arSupplier) - 1; $i++ ){
  $cellArt = trim( $sheet->getCell('B' . $i)->getValue() );
  if ( !empty($chronosArts[$cellArt]) ){
    $sheet->setCellValue( 'F' . $i, $chronosArts[$cellArt] );
  }
}
// var_dump( get_class_methods($sheet) ); die;
$sheet->setAutoFilter('');
$modFile = 'Order_' . date('Ymd') . '.xls';
$filePath = '/var/www/bitrix/data/www/tempusshop.ru/admin/utilities/adapter/export/' . $modFile;
// $objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, "Excel2007");
$objWriter = new PHPExcel_Writer_Excel2007( $objPHPExcel );
$objWriter->save($filePath);

echo json_encode(['savelink' => '/admin/utilities/adapter/export/' . $modFile]);

 ?>
