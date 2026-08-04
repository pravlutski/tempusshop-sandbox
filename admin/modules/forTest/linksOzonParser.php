<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
CModule::includeModule('panel.manager');

$dbPanel = new DBPanel;
global $DB;
$strSql = "SELECT * FROM wdhs_ozon_main_settings_new";
$results = $DB->Query($strSql, false, $err_mess.__LINE__);
while ( $row = $results->Fetch() ){
  $auth[ $row['name'] ] = $row['value'];
}

$result = $dbPanel->query("SELECT * FROM ozon_sales_pi_TI WHERE pi_sets = 'main'");
$rows = $dbPanel->fetchAll($result);
foreach ($rows as $row) {
  $tops = json_decode($row['tops']);
}

$strSql = "SELECT * FROM ozon_competitors WHERE seller = 'TEMPUS - Наручные часы'";
$result = $dbPanel->query($strSql);
$rows = $dbPanel->fetchAll($result);
$knownLinks = [];
foreach ( $rows as $row ){
  $knownLinks[$row['model']] = $row['model'];
}

$arDiff = array_diff( $tops ,array_values($knownLinks) );

$arFilter = Array(
  "IBLOCK_ID"	=> 16,
  "PROPERTY_CML2_ARTICLE" => array_values( $arDiff )
);

$arSelect = array("ID", "IBLOCK_ID", "PROPERTY_CML2_ARTICLE", "PROPERTY_WBARTICLE");
$rs = CIBlockElement::GetList( array(), $arFilter, false, false, $arSelect );
$items = [];
while ( $row = $rs->GetNext() ){
  $items[$row['PROPERTY_CML2_ARTICLE_VALUE']] = $row['PROPERTY_WBARTICLE_VALUE'];
}

$data = array(
  'offer_id' => array_values( $items )
);

$ch = curl_init('https://api-seller.ozon.ru/v3/product/info/list');
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
  'Api-Key:' . $auth['key'],
  'Client-Id:' . $auth['client_id'],
  'Content-Type:application/json'
));
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data, JSON_UNESCAPED_UNICODE));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_HEADER, false);
$res = curl_exec($ch);
curl_close($ch);

$res = json_decode($res, true);
$need = [];
foreach ( $res['items'] as $card ){
  $need[] = [
    'sku' => $card['sources'][0]['sku'],
    'name' => $card['name'],
    'seller' => 'TEMPUS - Наручные часы',
    'link' => 'https://www.ozon.ru/product/'.$card['sources'][0]['sku']
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
foreach ($need as $a) {
  $sheet->setCellValueExplicit("A" . $i, $a['link'], PHPExcel_Cell_DataType::TYPE_STRING);
  $sheet->setCellValueExplicit("B" . $i, $a['seller'], PHPExcel_Cell_DataType::TYPE_STRING);
  $sheet->setCellValueExplicit("C" . $i, $a['name'], PHPExcel_Cell_DataType::TYPE_STRING);
  $i++;
}

$objWriter = new PHPExcel_Writer_Excel2007($xls);
$dirPath = '/var/www/bitrix/data/www/tempusshop.ru/admin/modules/forTest/export/';
$filename = 'links.xlsx';
$objWriter->save( $dirPath . $filename );

 ?>
