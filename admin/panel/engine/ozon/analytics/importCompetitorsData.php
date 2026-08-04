<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
CModule::IncludeModule('panel.manager');

function writeLog()
{
  $logPath = $_SERVER["DOCUMENT_ROOT"] . "/admin/panel/engine/ozon/logs/competitors_log.txt";
  // file_put_contents( $logPath, date('Y-m-d G:i:s') . ' --- ' . $message . PHP_EOL, FILE_APPEND );
}

function fuckYouBitrixORM($tableName , $arrayData)
{
  $dbPanel = new DBPanel;

  $cardSample = $arrayData[0];
  $fields = [];
  foreach ($cardSample as $key => $value) {
    $fields[] = $key;
  }
  if (empty($fields) || count($fields) < 2) return false;
  $strSql = "INSERT INTO {$tableName} " . '(';

  $i = 0;
  foreach ($fields as $fname) {
    $strSql .= (count($fields) - 1 != $i) ? "{$fname}," : $fname;
    $i++;
  }
  $strSql .= ') VALUES ';
  $c = 0;
  foreach ($arrayData as $card){
    $strSql .= '(';
    $k = 0;
    foreach ($card as $field) {
      $strSql .= (count($card) - 1 != $k) ? "'{$field}'," : "'{$field}'";
      $k++;
    }
    $strSql .= ( count($arrayData) - 1 != $c ) ? '),' : ')';
    $c++;
  }
  $dbPanel->query( $strSql );
}

function getAltArt( string $model ):string
{
  global $DB;
  $strSql = "SELECT artnumber FROM ci_catalog_artnumbers WHERE artnumber = '{$model}' OR alternative = '{$model}'";

  $res = $DB->Query( $strSql, false, $err_mess.__LINE__ );
  if ( $res->SelectedRowsCount() > 0 ){
    $result = $res->Fetch();
    return $result['artnumber'];
  }
  return $model;
}

function getBlackPrice( array $models ):array
{
  $arFilter = [
    'IBLOCK_ID' => 16,
    'PROPERTY_CML2_ARTICLE' => $models,
    '!PROPERTY_WBARTICLE' => false,
  ];
  $arSelect = ['PROPERTY_WBARTICLE', 'PROPERTY_CML2_ARTICLE'];
  $res = CIBlockElement::GetList( array(), $arFilter, false, false, $arSelect );
  $items = [];
  while ( $row = $res->GetNext() ){
    $items[ $row['PROPERTY_CML2_ARTICLE_VALUE'] ] = $row['PROPERTY_WBARTICLE_VALUE'];
  }
  unset($row);

  CModule::IncludeModule('panel.manager');
  $dbPanel = new DBPanel;
  $result = $dbPanel->query("SELECT * FROM ozon_main_settings_TI");
  $rows = $dbPanel->fetchAll( $result );
  foreach ( $rows as $row ) {
    $arSettings[$row['name']] = $row['value'];
  }
  $headers = [
    'Api-Key:' . $arSettings['key'],
    'Client-Id:' . $arSettings['client_id'],
    'Content-Type:application/json'
  ];

  $data = [
    "filter" => [
      "offer_id" => array_values($items)
    ],
    "limit" => 1000
  ];
  $url = "https://api-seller.ozon.ru/v4/product/info/prices";
  $res = request( $url, $headers, json_encode($data) );

  foreach ( $res['result']['items'] as $item ) {
    $model = end( explode('_', $item['offer_id']) );
    $itemPricec[ $model ] = $item['price']['marketing_price'];
  }
  // var_dump($res);
  return $itemPricec;
}

function request( $url, $headers = [], $body = '' )
{
  $ch = curl_init( $url );
  curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers );
  curl_setopt( $ch, CURLOPT_POSTFIELDS, $body );
  curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
  curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
  curl_setopt( $ch, CURLOPT_HEADER, false );
  $res = curl_exec( $ch );
  if ( curl_errno( $ch ) ) {
    $error_msg = curl_error( $ch );
  }
  curl_close( $ch );

  if ( $error_msg ) {
    var_dump( 'Не получены наши цены' );
    return false;
  }

  return json_decode( $res, true );
}

// writeLog("Старт");
$now = date('d.m.Y');
// $now = '15.01.2025';
$filename = "/home/bitrix/python/price_{$now}.csv";
// var_dump($filename);
$lastChangeDate = date( 'Y-m-d', filectime($filename) );
if ( !file_exists($filename) ){
  // $triggers = new TsTriggers();
  // $triggers->SetError(["Не найден файл c ценами конкурентов [{$filename}]\n"]);
  // $triggers->SendTriggerErrors();
  die('Файл не существует или еще не обновлен');

}

try{
  $raw = fopen( $filename, 'r' );
}
catch ( Throwable $e ) {
  // writeLog("Ошибка чтения файла: {$e}");
  die("Ошибка чтения файла: {$e}");
}

$counter = 0;
$arComps = [];
$patterns = [
  '/[A-Z]+\-+[A-Z\d]+\-[A-Z\d]+/',
];

try{
  while ( $row = fgetcsv($raw, null, ',') ){

    if ( $counter == 0 ){
      $counter++;
      continue;
    }
    // 0 -> link
    // 1 -> seller
    // 2 -> name
    // 3 -> black price
    // 4 -> green price
    foreach ( $patterns as $pattern ){
      if ( preg_match($pattern, $row[2], $match) ){
        $model = getAltArt( $match[0] );
        $arComps[] = [
          'link' => $row[0],
          'seller' => $row[1],
          'model' => $model,
          'our' => 0,
          'b_price' => $row[3] == 'Нет в наличии' ? 0 : $row[3],
          'g_price' => $row[4] == 'Нет в наличии' ? 0 : $row[4],
          'date' => date('Y-m-d'),
          // 'date' => '2025-01-15',
        ];
      }
    }
    $counter++;
  }
}
catch ( Throwable $e){
  // writeLog("Ошибка формирования массива: {$e}");
  die("Ошибка формирования массива: {$e}");
}
$toGet = [];
foreach ( $arComps as $m ){
  $toGet[] = $m['model'];
}
try{
  $ourPrices = getBlackPrice( $toGet );

}catch ( Throwable $e ){
  die('Ошибка метода getBlackPrice. Данные не сохранены. ' . $e);
}

foreach ($arComps as &$arr) {
  if ( isset($ourPrices[$arr['model']]) ){
    $arr['our'] = intval($ourPrices[$arr['model']]);
  }
}



try{
  // var_dump($arComps);
  fuckYouBitrixORM( 'ozon_competitors', array_values($arComps) );
}
catch ( Throwable $e) {
  $triggers = new TsTriggers();
  $triggers->SetError(["Конкуренты ozon: Ошибка записи в таблицу [{$filename}]\n"]);
  $triggers->SendTriggerErrors();
  // writeLog("Ошибка записи в таблицу: {$e}");
  die("Ошибка записи в таблицу: {$e}");
}
 ?>
