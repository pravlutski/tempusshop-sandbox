<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

CModule::IncludeModule('panel.manager');
$dbPanel = new DBPanel;

if (!class_exists('SpreadsheetReader')){
  require($_SERVER["DOCUMENT_ROOT"] . '/bitrix/php_interface/include/classes/excel/excel_reader.php');
  require($_SERVER["DOCUMENT_ROOT"] . '/bitrix/php_interface/include/classes/excel/SpreadsheetReader.php');
  require($_SERVER["DOCUMENT_ROOT"] . '/bitrix/php_interface/include/classes/phpexcel/PHPExcel/IOFactory.php');
}

if ( $_FILES['file']['error'] === UPLOAD_ERR_OK ) {
  $filename = $_FILES['file']['tmp_name'];
}else{
  die('Ошибка загрузки файла');
}

$xls = PHPExcel_IOFactory::load($filename);
$xls->setActiveSheetIndex(0);
$sheet = $xls->getActiveSheet();

$arImportCandidates = [];
foreach ( $sheet->toArray() as $row ) {
  $arImportCandidates[ mb_strtoupper($row[0]) ] = $row[1];
}

$models = array_map( function($item){
  return "'" . $item . "'";
}, array_keys($arImportCandidates) );
$modelsStr = implode( ',', $models );

$strSql = "SELECT * FROM ozon_model_collection WHERE model IN ({$modelsStr})";
$res = $dbPanel->query( $strSql );
$rows = $dbPanel->fetchAll( $res );

$modelsDB = [];
foreach( $rows as $row ){
  $modelsDB[] = $row['model'];
}

$arImport = [];
$arUpdate = [];
foreach( $arImportCandidates as $model => $code ){
  if ( in_array($model, $modelsDB) ){
    $arUpdate[$model] = $code;
  }else{
    $arImport[] = [
      'model' => $model,
      'code' => $code
    ];
  }
}

if ( !empty($arUpdate) ){
  $arWhere[] = [
    'column' => 'model',
    'operator' => '=',
    'value' => ''
  ];
  foreach ( $arUpdate as $model => $code ) {
    $arWhere[0]['value'] = $model;
    $dbPanel->update('ozon_model_collection', ['code' => $code], $arWhere);
  }
}

if ( !empty($arImport) ){
  $dbPanel->insert('ozon_model_collection', $arImport);
}
echo 'ok';
 ?>
