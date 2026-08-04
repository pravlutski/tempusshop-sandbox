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

  $startRow = 3; // Начинаем с 3-й строки
  $endRow = $sheet->getHighestRow(); // Определяем последнюю строку

  $ar = array();
  for ($row = $startRow; $row <= $endRow; $row++) {
      $rowObject = $sheet->getRowIterator($row)->current();
      $rowData = array();
      foreach ($rowObject->getCellIterator() as $cell) {
          $rowData[] = $cell->getValue();
      }
      $ar[] = $rowData;
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
  // if ( preg_match('/[0-9]+/', $row[$columns['code']]) ){
    if ( !empty($row[$columns['article']]) ){
      $suppsArts[]['artSupp'] = str_replace(' ', '', $row[$columns['article']]);
    }
  // }
}

$warningSigns = [];
$artsDB = [];
$artsDbInv = [];
$strSql = "SELECT * FROM ci_catalog_artnumbers";
$results = $DB->Query($strSql, false, $err_mess.__LINE__);
while ($row = $results->Fetch()){
  $artsDB[$row['alternative']] = $row["artnumber"];
  //На случай, если артикул поставщика не требуется преобразовывать (Иначе он потеряется)
  $artsDbInv[$row["artnumber"]] = $row['alternative'];
}
foreach ($suppsArts as &$value) {
  if ( !empty($artsDB[$value['artSupp']]) ){
    $value['artChronos'] = $artsDB[$value['artSupp']];
  }
  else if ( !empty($artsDbInv[$value['artSupp']]) ){
    $value['artChronos'] = $value['artSupp'];
  }
  else{
    $warningSigns['not_in_db'][] = $value['artSupp'];
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
      $chronosArts[$artNumber] = $row[$columns['count']];
    }
  }
}

$data = [];
foreach ($suppsArts as &$value){
  if ( !empty($chronosArts[$value['artChronos']]) ){
    $data[$value['artSupp']] = $chronosArts[$value['artChronos']];
  }
  else if ( !empty($chronosArts[$value['artSupp']]) ){
    $data[$value['artSupp']] = $chronosArts[$value['artSupp']];
  }
}
// var_dump( $data );
// die;

// $objReader = PHPExcel_IOFactory::createReader('xlsx');
// $objPHPExcel = $objReader->load($filenameS);
$objPHPExcel = PHPExcel_IOFactory::load($filenameS);
$sheet = $objPHPExcel->getActiveSheet();

for ( $i = 1; $i < count($arSupplier) - 1; $i++ ){
  $cellArt = trim( $sheet->getCell('B' . $i)->getValue() );
  if ( !empty($data[$cellArt]) ){
    $sheet->setCellValue( 'F' . $i, $data[$cellArt] );
  }
}
$modFile = 'Order_' . date('Ymd') . '.xlsx';
$filePath = '/var/www/bitrix/data/www/tempusshop.ru/admin/utilities/adapter2/export/' . $modFile;
$objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, "Excel2007");
$objWriter->save($filePath);

echo json_encode(['savelink' => '/admin/utilities/adapter2/export/' . $modFile]);

 ?>
