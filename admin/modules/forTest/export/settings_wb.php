<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
require($_SERVER["DOCUMENT_ROOT"]."/admin/modules/descGen/classes/DescriptionGenerator.php");

CModule::IncludeModule('panel.manager');
CModule::IncludeModule("crm_courier");
use Bitrix\Sale\Order;
//
// $json = file_get_contents("/var/www/bitrix/data/www/tempusshop.ru/local/cron/ms/barcodesMS.txt");
//
// $arMs = json_decode( $json, true );
//
// foreach ( $arMs as $product){
//   if ( $product['externalCode'] == 207832){
//     $test[] = $product;
//   }
// }

// syncBarcodes( $test );

// function syncBarcodes( $array ){
//   // if ( $this->site_id != 's1' ) return false;
//   $res = $array;
//   // file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/local/cron/ms/barcodesMS.txt', print_r(json_encode($res), true));
//   // $this->triggers->SetError(["Началась синхронизация бакркодов с МС"]);
//   // $this->triggers->SendTriggerErrors();
//   // if ( empty( $res ) ){
//   //   $this->triggers->SetError(["Синхронизация баркодов: пустой массив"]);
//   //   $this->triggers->SendTriggerErrors();
//   //   return false;
//   // }
//
//   $arItems = [];
//   $xml_ids = [];
//   foreach ( $res as $product ){
//     $arItems[ $product['code'] ] = [
//       'xml_id' => $product['externalCode'],
//     ];
//     $xml_ids[] = $product['externalCode'];
//     foreach ( $product['barcodes'] as $barcode ) {
//       foreach ( $barcode as $type ){
//         if ( substr( $type, 0, 1 ) != 2 && is_numeric( $type ) ){
//           $arItems[ $product['code'] ]['barcodes'][] = $type;
//         }
//       }
//     }
//   }
//   var_dump( 'Got items from MS -- ' . count($arItems) );
//
//   $arFilter = ["IBLOCK_ID" => 16, "XML_ID" => $xml_ids];
//   $arSelect = ["ID", "IBLOCK_ID", "XML_ID", "PROPERTY_CML2_ARTICLE", "PROPERTY_barcodes"];
//   $res = CIBlockElement::GetList( [], $arFilter, false, false, $arSelect );
//
//   $arImport = [];
//   $arDebug = [];
//   while ( $row = $res->GetNext() ){
//     $arDebug[] = [
//       "model" => $row['PROPERTY_CML2_ARTICLE_VALUE'],
//       'xml_id' => $row['XML_ID'],
//       'barcodes' => $row["PROPERTY_barcodes_VALUE"],
//     ];
//     if ( stripos( $row['PROPERTY_BARCODES_VALUE'], ',' ) ){
//       $barcodesRow = explode( ',', $row['PROPERTY_BARCODES_VALUE'] );
//     }else{
//       $barcodesRow = [ $row['PROPERTY_BARCODES_VALUE'] ];
//     }
//
//     $barcodesRow = array_map( function($item){
//       return trim( $item );
//     }, $barcodesRow );
//
//     var_dump( $arItems[ $row['XML_ID'] ]['barcodes'] );
//     $barcodesSTART = $barcodesRow;
//     $barcodesRow = array_filter( $barcodesRow );
//
//     foreach ( $arItems[ $row['XML_ID'] ]['barcodes'] as $barcode ){
//       if ( !in_array($barcode, $barcodesRow) ){
//         $barcodesRow[] = $barcode;
//       }
//     }
//
//     if ( !empty( array_diff($barcodesRow, $barcodesSTART) ) ){
//       $arImport[ intval($row['ID']) ] = implode(', ', $barcodesRow);
//     }
//   }
//   var_dump( explode(',', array_values($arImport)[0]) );
//   var_dump( 'Will be imported -- ' . count($arImport) );
//   // file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/local/cron/ms/barcodesBX.txt', print_r(json_encode($arDebug), true));
//   // file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/local/cron/ms/barcodesImport.txt', print_r(json_encode($arImport), true));
//   foreach ( $arImport as $id => $barcodesStr ){
//     // $r1 = CIBlockElement::SetPropertyValueCode( $id, "2823", ['VALUE' => $barcodesStr] );
//   }
//   // CProSet::setOption( 'SYNC_BARCODES', count($arImport) );
// }

$arFilter = ["IBLOCK_ID" => 16];
$arSelect = ["ID", "IBLOCK_ID", "XML_ID", "PROPERTY_CML2_ARTICLE", "PROPERTY_barcodes"];
$res = CIBlockElement::GetList( [], $arFilter, false, false, $arSelect );

$arex = [];
while ( $row = $res->GetNext() ){
  if ( empty($row['PROPERTY_BARCODES_VALUE']) ) continue;

  if ( stripos( $row['PROPERTY_BARCODES_VALUE'], ',' ) ){
    $barcodesRow = explode( ',', $row['PROPERTY_BARCODES_VALUE'] );
  }else{
    $barcodesRow = [ $row['PROPERTY_BARCODES_VALUE'] ];
  }
  $barcodesRow = array_map( function($item){
    return trim( $item );
  }, $barcodesRow );
  foreach ( $barcodesRow as $value ){
    if ( !is_numeric( trim($value) ) ){
      $flag = true;
      break;
    }
  }


  if ( $flag ) {
    $bcStr = '';
    $bcAr = [];
    foreach ($barcodesRow as $value) {
      if ( is_numeric( trim($value) ) ){
        $bcAr[] = $value;
      }
    }

    $arex[] = [
      'ID' => $row['ID'],
      'MODEL' => $row['PROPERTY_CML2_ARTICLE_VALUE'],
      'BARCODES' => implode(', ', $barcodesRow),
      'BARCODES_FIXED' => implode(', ', $bcAr),
    ];
  }
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
var_dump( count($arex) );

foreach ($arex as $key => $d) {
  $sheet->setCellValueExplicit("A" . $i, $d['ID'], PHPExcel_Cell_DataType::TYPE_STRING);
  $sheet->setCellValueExplicit("B" . $i, $d['MODEL'], PHPExcel_Cell_DataType::TYPE_STRING);
  $sheet->setCellValueExplicit("C" . $i, $d['BARCODES'], PHPExcel_Cell_DataType::TYPE_STRING);
  $sheet->setCellValueExplicit("D" . $i, $d['BARCODES_FIXED'], PHPExcel_Cell_DataType::TYPE_STRING);
  $i++;
}

$objWriter = new PHPExcel_Writer_Excel2007($xls);
$dirPath = $_SERVER["DOCUMENT_ROOT"] . '/admin/modules/forTest/export/';
$filename = 'barcodesFIX.xlsx';
$objWriter->save( $dirPath . $filename );

// var_dump( $arex );
?>
