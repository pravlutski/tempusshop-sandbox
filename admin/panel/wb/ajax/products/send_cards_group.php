<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
global $DB;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

if ( empty( $_POST ) ) trigger_error( '$_POST is empty', E_USER_ERROR );

print_r( "IMPORT TYPE: {$_POST['type']}\n" );
print_r( "IMPORT MODE: {$_POST['mode']}\n" );

$models = [];
if ( $_POST['type'] == 'lb-file' ){
  if ( empty( $_FILES ) ) trigger_error( '$_FILES is empty', E_USER_ERROR );
  $models = parseXlsx( $_FILES['data']['tmp_name'], $_POST['mode'] );
}else{
  $models = parseTextField( $_POST['data'], $_POST['mode'] );
}

if ( $_POST['mode'] == 'vendor_code' ){
  $before = count( $models );
  $models = getNmids( $models, $DB );
  print_r( "GOT NMIDS: " .count($models). "/" .$before. "\n" );
}

if ( count($models) > 30 ) trigger_error("Count of nmids must not be greater than 30");

$headers = getHeaders( $DB );

foreach ( $models as $model ){
  request( ["nmIDs" => [$model]], $headers );
  sleep(1);
}

request( ["nmIDs" => $models], $headers );


// $models = parseTextField( $_POST['data'], $_POST['mode'] );
// var_dump( $models );

function parseXlsx( string $filename, string $mode ):array
{
  if (!class_exists('SpreadsheetReader')){
    require($_SERVER["DOCUMENT_ROOT"] . '/bitrix/php_interface/include/classes/excel/excel_reader.php');
    require($_SERVER["DOCUMENT_ROOT"] . '/bitrix/php_interface/include/classes/excel/SpreadsheetReader.php');
    require($_SERVER["DOCUMENT_ROOT"] . '/bitrix/php_interface/include/classes/phpexcel/PHPExcel/IOFactory.php');
  }

  $xls = PHPExcel_IOFactory::load($filename);
  $xls->setActiveSheetIndex(0);
  $sheet = $xls->getActiveSheet();
  $result = [];

  foreach ( $sheet->toArray() as $row ) {
    if ( empty( $row[0] ) ) continue;
    $result[] = $row[0];
  }
  
  if ( $mode == 'nmid' ) $result = array_map( 'intval', $result );
  return $result;
}

function parseTextField( string $input, string $mode ):array
{
  $models = explode( "\r\n", $input );
  $models = array_map( 'trim', $models );
  if ( $mode == 'nmid' ) $models = array_map( 'intval', $models );

  return $models;
}

function getNmids( array $models, object $db ):array
{
  $preparedModels = implode( ',', $models );
  $preparedModels = array_map(function($item){
    return "'". $item ."'";
  }, $preparedModels);

  $strSql = "SELECT article, nmid FROM wdhs_wb_props WHERE article IN ({$preparedModels}) cabinet = 'WR'";
  $res = $db->Query( $strSql );
  $rows = [];
  while ( $row = $res->Fetch() ){
    $rows[] = $row['nmid'];
  }

  return $rows;
}

function getHeaders( object $db ):array
{
  $strSql = "SELECT * FROM wdhs_wb_main_settings WHERE cabinet = 'WR'";
  $res = $db->Query( $strSql );
  $api = $res->Fetch();
  $headers = [
    "Content-Type: application/json",
    "Authorization: {$api['api']}"
  ];

  return $headers;
}

function request( array $data, array $headers ):void
{
  $url = 'https://content-api.wildberries.ru/content/v2/cards/moveNm';
  $ch = curl_init( $url );
  curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers );
  curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
  curl_setopt( $ch, CURLOPT_POSTFIELDS, json_encode( $data ) );
  curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, 30 );
  $result = curl_exec( $ch );
  curl_close( $ch );
  $result = json_decode( $result, true );

  if ( !empty( $result['error'] ) ){
    echo $result['errorText'];
    echo '<br>';
  }
}
 ?>
