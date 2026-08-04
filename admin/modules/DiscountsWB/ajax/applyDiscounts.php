<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
CModule::IncludeModule("panel.manager");
CModule::IncludeModule("maxyss.wb");

require $_SERVER['DOCUMENT_ROOT'] . '/local/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

if (!class_exists('SpreadsheetReader')){
  require($_SERVER["DOCUMENT_ROOT"] . '/bitrix/php_interface/include/classes/excel/excel_reader.php');
  require($_SERVER["DOCUMENT_ROOT"] . '/bitrix/php_interface/include/classes/excel/SpreadsheetReader.php');
  require($_SERVER["DOCUMENT_ROOT"] . '/bitrix/php_interface/include/classes/phpexcel/PHPExcel/IOFactory.php');
}

// var_dump($_POST['cabinet']);
// die;

if ( !empty($_POST['cabinet']) ){
  if ( $_POST['cabinet'] == 'WR' ){
    $arSettings = CMaxyssWb::settings_wb('WR');
  }else{
    $arSettings = CMaxyssWb::settings_wb('DEFAULT');
  }
}else{
  die('Не получен кабинет из формы');
}
$auth = $arSettings['AUTHORIZATION'];

$uploadFlag = $_POST['flag'];
if ($uploadFlag == 'illiquid'){
  $pathToFile = '/var/www/bitrix/data/www/tempusshop.ru/admin/modules/DiscountsWB/temp/illiquid_discounts.xlsx';
}
else if($uploadFlag == 'default'){
  $pathToFile = '/var/www/bitrix/data/www/tempusshop.ru/admin/modules/DiscountsWB/temp/default_discounts.xlsx';
}
else {
  die('Ошибка выгрузки');
}
$xls = PHPExcel_IOFactory::load($pathToFile);
$xls->setActiveSheetIndex(0);
$sheet = $xls->getActiveSheet();
$ar = array();
$logPath = '/var/www/bitrix/data/www/tempusshop.ru/admin/modules/DiscountsWB/logs/' . $uploadFlag . '_log.txt';
file_put_contents($logPath, 'LAST DISCOUNTS UPLOAD ('.$_POST['cabinet'].'): ' . date('Y-m-d G:i:s') . PHP_EOL);
foreach ($sheet->toArray() as $key => $row) {
  $ar[] = ['nmID' => intval($row[0]), 'discount' => intval($row[1])];
  file_put_contents($logPath, $row[0] . ' -> ' . $row[1] . PHP_EOL, FILE_APPEND);
}
$ar = array_chunk($ar, 1000);
foreach ($ar as $chunk) {

  // var_dump(['data' => $chunk]);

  $url = 'https://discounts-prices-api.wb.ru/api/v2/upload/task';
  $headers = [
    "Content-Type: application/json",
    "Authorization: {$auth}"
  ];

  $data = ['data' => $chunk];
  // var_dump($data);
  $ch = curl_init($url);
  curl_setopt($ch,CURLOPT_HTTPHEADER, $headers);
  curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch,CURLOPT_CONNECTTIMEOUT, 30);
  $resCurl = curl_exec($ch);
  curl_close($ch);
  $result[] = json_decode($resCurl,1);
}
foreach ($result as $value) {
  if ($value == null || $value['error'] == false) {
    $response['good'] = 1;
  }else{
    $response['error'] = $value;
  }
}
echo json_encode($response);
 ?>
