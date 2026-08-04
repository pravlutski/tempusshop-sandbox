<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
set_time_limit(0);

CModule::IncludeModule("iblock");
CModule::IncludeModule("main");
CModule::IncludeModule("panel.manager");
CModule::IncludeModule('maxyss.wb');

$objPricelist = new CPanelPricelist;
$objSupplier = new CPanelSupplier;
$objCurrency = new CPanelCurrency;
$objUtils = new CPanelUtils;
GLOBAL $DB;
$dbPanel = new DBPanel;

function updateStatus( string $code, array $arStat, $db ):void
{
  if ( empty($arStat) ) return;
  $strSql = "UPDATE wb_agents SET ";
  foreach ($arStat as $field => $value) {
    if ( array_key_last($arStat) == $field ){
      $str = "{$field} = '{$value}'";
    }else{
      $str = "{$field} = '{$value}', ";
    }
    $strSql .= $str;
  }
  $strSql .= " WHERE code = '{$code}'";
  try{
    $db->query( $strSql );
  }catch( Throwable $ignored){
    print_r('Не удалось обновить статус: ' . $ignored . "\n");
  }
}
$arStat = [
  'status' => 'IN_PROCESS',
  'status_text' => 'Начало',
  'percent' => '0',
  'time_start' => date('Y.m.d G:i:s')
];
updateStatus('control_WR', $arStat, $dbPanel);

$strSql = "SELECT * FROM wdhs_wb_main_settings";

$results = $DB->Query($strSql, false, $err_mess.__LINE__);
while ($row = $results->Fetch()){
  $arSetting[$row['cabinet']] = $row;
}
$arSetting = json_decode($arSetting['WR']['settings'],true);

$arSebes = array();
updateStatus('control_WR', ['status_text' => 'Получение товаров ci_price', 'percent' => 10], $dbPanel );
$strSql = "SELECT * FROM `ci_price` WHERE `active_wb` = 'Y'";

$results = $DB->Query($strSql, false, $err_mess.__LINE__);

while ($row = $results->Fetch()){
  $arSebesTmp[$row["model"]][] = $row["price"];
}

foreach($arSebesTmp as $key => $values) {
  $arSebes[$key] = min($values);
}

function makeRequest( $url ) {

    $curl = curl_init();
    curl_setopt( $curl, CURLOPT_URL, $url );
    curl_setopt( $curl, CURLOPT_RETURNTRANSFER, true );
    curl_setopt( $curl, CURLOPT_HEADER, false );
    $data = curl_exec( $curl );

    curl_close($curl);

    return $data;
}


function parseWBProductIds( $pages ) {
    $starttime = time();
    $log = ["Parser started..."];
    $result_set = [];

    for( $i = 1; $i < $pages; $i++ ) {
        $data = makeRequest(sprintf( "https://catalog.wb.ru/sellers/v2/catalog?appType=1&curr=rub&dest=-1257786&page=%d&sort=popular&spp=30&supplier=724646", $i) );

        $log[] = sprintf( "Trying to parse page: %d", $i );

        $data_arr = json_decode( $data, true );

        if( ! is_array( $data_arr ) ) {
            $log[] = sprintf( "Parse error of page %d... Skipping", $i );
            continue;
        }

        if( ! isset( $data_arr['data'] ) || ! isset( $data_arr['data']['products'] ) || ! isset( $data_arr['data']['total'] ) ) {
            $log[] = "Unexpected array structure: ";
            $log[] = print_r( $data_arr, true );
            continue;
        }

        $log[] = sprintf( "Total count of products is: %d", $data_arr['data']['total'] );
        $log[] = sprintf( "Count of products on current page is: %d", count( $data_arr['data']['products'] ) );

        if( count( $data_arr['data']['products'] ) === 0 ) {
            $log[] = "Exit.";
            break;
        }

        foreach( $data_arr['data']['products'] as $product ) {
            $result_set[] = $product['id'];
        }
    }
    $worktime = time() - $starttime;
    $log[] = "Spent {$worktime} seconds";
    //file_put_contents( "debug.log", implode( PHP_EOL, $log ) . "\n", FILE_APPEND | LOCK_EX );

    return $result_set;
}

$dateTime = date("d.m.Y H:i:s");
updateStatus('control_WR', ['status_text' => 'Парсинг ВБ', 'percent' => 40], $dbPanel );
$res =  parseWBProductIds( 4000 );


$strSql = "SELECT nmid, article FROM wdhs_wb_props WHERE cabinet = 'WR'" ;
$results = $DB->Query($strSql, false, $err_mess.__LINE__);
while ($row = $results->Fetch()){
  if (in_array($row['nmid'],$res)) {
    $wbSells[] = $row['article'];
  }
}

$strSql = "SELECT * FROM ci_wb_top";
$arIDs = array();
$results = $DB->Query($strSql, false, $err_mess.__LINE__);
while ($row = $results->Fetch()){
  if (!in_array($row["article"],$wbSells) && !empty($row["article"])) {
    $notsell[] = $row["article"];
  }
}

$arSebes = array();
$strSql = "SELECT * FROM `ci_price` WHERE `active_wb` = 'Y'";

$results = $DB->Query($strSql, false, $err_mess.__LINE__);

while ($row = $results->Fetch()){
  $arSebesTmp[$row["model"]][] = $row["price"];
}

foreach($arSebesTmp as $key => $values) {
  $arSebes[$key] = min($values);
}


$arSelect = Array("ID","IBLOCK_ID","IBLOCK_SECTION_ID","PROPERTY_CML2_ARTICLE","PROPERTY_WBPRICE","PROPERTY_WBARTICLE2","DATE_CREATE");
$arFilter = Array(
	"IBLOCK_ID" => CProSet::IB_CATALOG,
	"=PROPERTY_CML2_ARTICLE" => $notsell,
);

$result = CIBlockElement::GetList(array(), $arFilter, false, false, $arSelect);

while ($el = $result->GetNext()){
	$sprav[$el['PROPERTY_CML2_ARTICLE_VALUE']] = [
    'article' => $el['PROPERTY_CML2_ARTICLE_VALUE'],
    'price' => $el['PROPERTY_WBPRICE_VALUE'],
    'sebes' => $arSebes[$el['PROPERTY_CML2_ARTICLE_VALUE']],
    'wb_article' => $el['PROPERTY_WBARTICLE2_VALUE'],
    'date' => $el['DATE_CREATE']
  ];
}

//print_r($sprav);
$strSql = "SELECT DISTINCT model FROM ci_price WHERE active_wb = 'Y' AND active = 'Y'" ;
$results = $DB->Query($strSql, false, $err_mess.__LINE__);
while ($row = $results->Fetch()){
		$activeCiPrice[] = $row['model'];
}


foreach ($notsell as $key => $value) {
  if (!in_array($value,$activeCiPrice)) {
    // $badModels[] = [
    //   'article' => $value,
    //   'price' => $sprav[$value]['price'],
    //   'sebes' => $sprav[$value]['sebes'],
    //   'date' => $sprav[$value]['date'],
    //   'wb_article' => $sprav[$value]['wb_article'],
    //   'reason' => 'Не доступен в CI_PRICE для WB'
    // ];
    unset($notsell[$key]);
  }
}
unset($key);
unset($value);

$strSql = "SELECT DISTINCT ARTICLE FROM ci_price_quarantine WHERE PRICE_ID = 'WB'" ;
$results = $DB->Query($strSql, false, $err_mess.__LINE__);
while ($row = $results->Fetch()){
		$qurantine[$row['ARTICLE']] = $row['ARTICLE'];
}


foreach ($notsell as $key => $value) {
  if (in_array($value,$qurantine)) {
    unset($notsell[$key]);
  }
}
unset($key);
unset($value);


$strSql = "SELECT ARTICLE, AVAILABLE_RU, RESERVED_s1 FROM ci_reserved";
$results = $DB->Query($strSql, false, $err_mess.__LINE__);
$arReserved = array();

while ($row = $results->Fetch()) {
    $difference = $row['AVAILABLE_RU'] - $row['RESERVED_s1'];
    if ($difference <= 0) {
        $arReserved[$row['ARTICLE']] = $difference;
    }
}
foreach ($notsell as $key => $value) {
  if (isset($arReserved[$value])) {
    unset($notsell[$key]);
  }
}
unset($key);
unset($value);

$min = intval($arSetting['minSebes']);
$max = intval($arSetting['maxSebes']);

foreach ($notsell as $key => $value) {
  if (intval($sprav[$value]['sebes']) < $min || intval($sprav[$value]['sebes']) > $max) {
    // $badModels[] = [
    //   'article' => $value,
    //   'price' => $sprav[$value]['price'],
    //   'sebes' => $sprav[$value]['sebes'],
    //   'date' => $sprav[$value]['date'],
    //   'wb_article' => $sprav[$value]['wb_article'],
    //   'reason' => 'Не проходят по условиям себеса  min = '.$min.'  max = '.$max.' sebes = '.$sprav[$value]['sebes'].'',
    // ];
    unset($notsell[$key]);
  }
}
updateStatus('control_WR', ['status_text' => 'Сохранение результата', 'percent' => 70], $dbPanel );
foreach ($notsell as $key => $value) {
  $badModels[] = [
    'article' => $value,
    'price' => $sprav[$value]['price'],
    'sebes' => $sprav[$value]['sebes'],
    'date' => $sprav[$value]['date'],
    'wb_article' => $sprav[$value]['wb_article'],
    'reason' => 'Причина не установлена',
  ];
}
$arStat = [
  'status' => 'COMPLETED',
  'status_text' => 'Выполнено',
  'percent' => 100,
  'time_end' => date('Y.m.d G:i:s')
];
updateStatus('control_WR', $arStat, $dbPanel );

echo 'ready';
//file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/wb/logs/reportStock/bad.txt", print_r(json_encode($badModels), true));
