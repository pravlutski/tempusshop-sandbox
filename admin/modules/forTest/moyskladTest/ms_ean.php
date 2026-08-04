<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
CModule::IncludeModule('panel.manager');

$ms = new MoyskladAPI('s1');

// $res = $ms->send("/entity/product/", "GET", [], ["Content-Type" => "application/json"], true);

$filename = "/var/www/bitrix/data/www/tempusshop.ru/admin/modules/forTest/moyskladTest/resms.json";
$json = file_get_contents($filename);
$res = json_decode( $json, true);

$arItems = [];

foreach ( $res as $product ){
  $arItems[ $product['code'] ] = [
    'model' => end( explode(' ', $product['name']) ),
    'xml_id' => $product['externalCode'],
  ];
  foreach ( $product['barcodes'] as $barcode ) {
    foreach ( $barcode as $type ){
      if ( substr( $type, 0, 1 ) != 2 ){
        $arItems[ $product['code'] ]['barcodes'][] = $type;
      }
    }
  }
}

$arFilter = [
  "IBLOCK_ID" => 16,
  // "PROPERTY_CML2_ARTICLE" => ["BG-6903-2E"]
];

$arSelect = ["ID", "IBLOCK_ID", "XML_ID", "PROPERTY_CML2_ARTICLE", "PROPERTY_barcodes"];

$res = CIBlockElement::GetList( [], $arFilter, false, false, $arSelect );

$arImport = [];
while ( $row = $res->GetNext() ){

  if ( stripos( $row['PROPERTY_BARCODES_VALUE'], ',' ) ){
    $barcodesRow = explode( ',', $row['PROPERTY_BARCODES_VALUE'] );
  }else{
    $barcodesRow = [ $row['PROPERTY_BARCODES_VALUE'] ];
  }


  $barcodesRow = array_map( function($item){
    return trim( $item );
  }, $barcodesRow );

  $barcodesSTART = $barcodesRow;
  $barcodesRow = array_filter( $barcodesRow );


  foreach ( $arItems[ $row['XML_ID'] ]['barcodes'] as $barcode ){
    if ( !in_array($barcode, $barcodesRow) ){
      $barcodesRow[] = $barcode;
    }
  }

  // if ( $row["PROPERTY_CML2_ARTICLE_VALUE"] == 'BG-6903-2E' ){
  //   var_dump( $row['PROPERTY_BARCODES_VALUE'] );
  //   var_dump( $barcodesSTART );
  //   var_dump( $barcodesRow );
  //   var_dump( !empty( array_diff($barcodesRow, $barcodesSTART) ) );
  // }

  if ( !empty( array_diff($barcodesRow, $barcodesSTART) ) ){
    $arImport[ $row['PROPERTY_CML2_ARTICLE_VALUE'] ] = $barcodesRow;
  }

}

file_put_contents( '/var/www/bitrix/data/www/tempusshop.ru/admin/modules/forTest/moyskladTest/file.txt', json_encode($arImport) );


 ?>
