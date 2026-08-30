<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
$workers = new WorkersChecker("engine_ozon_reportStock_php_IP_AVTO");
if (!$workers->checkStatus()) {
	exit;
}
$workers->updateStatus("Y");
set_time_limit(0);
require_once '/var/www/bitrix/data/www/tempusshop.ru/bitrix/php_interface/include/classes/phpexcel_1.8/PHPExcel.php';
//require_once 'importProductsControl.php';
CModule::IncludeModule('panel.manager');
use Bitrix\Main\Application,
	Bitrix\Main\Loader;

if (isset($_GET['cabinet']) || !empty($_GET['cabinet'])) {
	$CABINET = $_GET['cabinet'];
} else if (isset($argv[1])) {
	$CABINET = $argv[1];
} else {
	die('WRONG CABINET');
}

if (isset($_GET['source']) || !empty($_GET['source'])) {
	$SOURCE = $_GET['source'];
} else if (isset($argv[2])) {
	$SOURCE = $argv[2];
} else {
	$SOURCE = 'undefine';
}

if (empty($CABINET)) {
	die('WRONG CABINET');
}

if ($CABINET == 'TI') {
	$prefix_old = '_new';
	$price_prop = 'PROPERTY_PRICE_OZTI';
	$ci_price_filter = "active_ozti";
} else {
	$prefix_old = '';
	$price_prop = 'PROPERTY_OZSB_PRICE';
	$ci_price_filter = "active_os";
}


$CurDB = new DBPanel();

$timeStart = date('Y.m.d G:i:s');


$in = array(
	"source	" => "'".$SOURCE."'",
	"script	" => "'control_TI'",
	"time	" => "'".$timeStart."'",
	"status	" => "'RUN'",
);

$fields = implode(",", array_keys($in));
$values = implode(",",$in);

$sql = "INSERT INTO ozon_tech_log ($fields) VALUES ($values)";
$CurDB->query($sql);

$module = 'control_'.$CABINET;

function updateStatus( string $code, array $arStat, $db ):void
{
  if ( empty($arStat) ) return;
  $strSql = "UPDATE ozon_agents SET ";
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
		print_r('Не удалось обновить статус' . $ignored . "\n");
	}
}

//Агент-Инфо
$arStat = [
	'status' => 'PROCESS',
	'status_text' => 'Запуск скрипта',
	'percent' => 0,
	'time_start' => $timeStart
];
updateStatus( $module, $arStat, $CurDB );

$result = $CurDB->query("SELECT * FROM ozon_main_settings_{$CABINET}");
$rows = $CurDB->fetchAll($result);
foreach ($rows as $row) {
	$arSetting[$row['name']] = $row['value'];
}
$url = $arSetting['api_url'];
$client_id = $arSetting['client_id'];
$token = $arSetting['key'];
unset($result);
unset($rows);

$result = $CurDB->query("SELECT * FROM ozon_control_exclude WHERE cabinet = '{$CABINET}'");
$rows = $CurDB->fetchAll($result);
foreach ($rows as $row) {
  $modelsExclude = json_decode($row['models']);
}
unset($result);
unset($rows);

// $result = $CurDB->Query("SELECT * FROM ozon_fbo_stock_{$CABINET}");
// $rows = $CurDB->fetchAll($result);
// foreach ($rows as $row) {
// 	$models[] = $row['article'];
// }
// unset($result);
// unset($rows);

//
function getProductList_core( $settings )
{
	require('lib/core.php');

	$api = new ApiManager( $settings );
	$request = [
	  'filter' => [
	    'visibility' => 'ARCHIVED'
	  ],
	  'limit' => 1000
	];
	$flag = true;
	$archived = [];

	while ( $flag ){

	  $response = $api->getProductList( $request );
	  $data = $response->getData()->decode();

	  if ( count($data['result']['items']) < $request['limit'] ) $flag = false;

	  foreach ( $data['result']['items'] as $item ){
	    $model = end( explode('_', $item['offer_id']) );
	    $archived[$model] = 1;
	  }
	  if ( empty($data['result']['last_id']) ){
	    var_dump( 'last page or error' );
	    break;
	  }
	  $request['last_id'] = $data['result']['last_id'];
	  sleep( 2 );
	}

	return $archived;
}


///

//Агент-Инфо
updateStatus( $module, ['status_text' => 'Создание отчета от ozon', 'percent' => 10], $CurDB );

$data = [
	"language" => "DEFAULT",
	"visibility" => "ALL"
];
$data_string = json_encode($data);
$ch = curl_init($url . '/v1/report/products/create');
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
	'Api-Key:' . $token,
	'Client-Id:' . $client_id,
	'Content-Type:application/json'
));
curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_HEADER, false);
$res = curl_exec($ch);
curl_close($ch);

$res = json_decode($res, true);

$code = $res['result']['code'];
unset($res);

//Агент-Инфо
updateStatus( $module, ['status_text' => 'Получение отчета от ozon', 'percent' => 25], $CurDB );

sleep(60);
//$code = 'REPORT_seller_products_1471902_1733927524_0193b622-57e4-7cce-984d-3dd6df2e701e';




$data = [
	"code" => $code,
];
$data_string = json_encode($data);
$ch = curl_init($url . '/v1/report/info');
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
	'Api-Key:' . $token,
	'Client-Id:' . $client_id,
	'Content-Type:application/json'
));
curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_HEADER, false);
$res = curl_exec($ch);
curl_close($ch);

$res = json_decode($res, true);


$fileUrl = $res['result']['file'];

$fileContent = file_get_contents($fileUrl);
print_r( $fileUrl . PHP_EOL );
//Агент-Инфо
updateStatus( $module, ['status_text' => 'Распаршиваем отчет', 'percent' => 50], $CurDB );

if ($fileContent === false) {
    die("Не удалось скачать файл.");
}

$rows = str_getcsv($fileContent, "\n"); // Разбиваем строки
$parsedData = [];
foreach ($rows as $row) {
    $parsedData[] = str_getcsv($row, ";");
}
// print_r($parsedData);
unset($parsedData[0]);
foreach ($parsedData as $key => $value) {
	if ($value[7] == 'Продается') {
		$arResult[] = [
			'ozon article' => str_replace('\'','',$value[0]),
			'ozon id' => $value[1],
			'fbo' => $value[17],
			'fbs' => $value[20],
			'see' => $value[7],
		];
		$ozonModels[] = str_replace('\'','',$value[0]);
	}
	if (!empty($value[12])) {
		$erroOzon[str_replace('\'','',$value[0])] = [
			'reason' => $value[11]
		];
	}

	$ozonAll[trim(str_replace('\'','',$value[0]))] = trim(str_replace('\'','',$value[0]));
}

//print_r($ozonAll);
file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/ozon/logs/{$CABINET}/reportStock/log.txt", print_r(json_encode($arResult), true));

//Агент-Инфо
updateStatus( $module, ['status_text' => 'Собираем отчет по моделям', 'percent' => 75], $CurDB );

$arSelect = Array("ID","IBLOCK_ID","IBLOCK_SECTION_ID","PROPERTY_OZON_ACTIVE","PROPERTY_CML2_ARTICLE");
$arFilter = Array(
	"IBLOCK_ID" => CProSet::IB_CATALOG,
	"=PROPERTY_WBARTICLE" => $ozonModels,
);
//$arFilter["!ID"] = 14124;
$result = CIBlockElement::GetList(array(), $arFilter, false, false, $arSelect);

while ($el = $result->GetNext()){
	$excludeModels[] = $el["PROPERTY_CML2_ARTICLE_VALUE"];
}

$archived = getProductList_core( $arSetting );

$strSql = "SELECT DISTINCT model FROM ci_price WHERE {$ci_price_filter} = 'Y' AND active = 'Y'" ;
$results = $DB->Query($strSql, false, $err_mess.__LINE__);
while ($row = $results->Fetch()){
	if(!in_array($row['model'], $excludeModels)) {
		$models[] = $row['model'];
	}
}

$strSql = "SELECT * FROM ci_reserved";
$results = $DB->Query($strSql, false, $err_mess.__LINE__);
while ($row = $results->Fetch()){
	if($row["RESERVED"] >= $row["AVAILABLE_RU"]) {
		$RESERVED[] = $row["ARTICLE"];
	}
}

$arSelect = Array("ID","IBLOCK_ID","IBLOCK_SECTION_ID","PROPERTY_WBARTICLE","PROPERTY_CML2_ARTICLE",'PROPERTY_PRICE_OZTI','PROPERTY_OZSB_PRICE',"PROPERTY_OZON_ACTIVE","DATE_CREATE");
$arFilter = Array(
	"IBLOCK_ID" => CProSet::IB_CATALOG,
	"=PROPERTY_CML2_ARTICLE" => $models,
);
//$arFilter["!ID"] = 14124;

$result = CIBlockElement::GetList(array(), $arFilter, false, false, $arSelect);
$i = 0;
$k = 0;
while ($el = $result->GetNext()){

	$firstReason = '';

	if (isset($erroOzon[$el['PROPERTY_WBARTICLE_VALUE']])) {
		$firstReason = $erroOzon[$el['PROPERTY_WBARTICLE_VALUE']]['reason'];
	}
	else if ( isset( $archived[ $el["PROPERTY_CML2_ARTICLE_VALUE"] ] ) ) {
		$firstReason = 'Товар в архиве';
	}
	else if (!isset($ozonAll[$el["PROPERTY_WBARTICLE_VALUE"]])) {
		$firstReason = 'Товар отсутствует на ozon';
	}
	else if (in_array($el["PROPERTY_CML2_ARTICLE_VALUE"],$RESERVED)) {
		$firstReason = 'Товар в резерве';
	}
	else {
		if (!empty($el["PROPERTY_WBARTICLE_VALUE"])) {
			$arModelDopControl[] = $el["PROPERTY_WBARTICLE_VALUE"];
		}
	}
	if (!in_array($el["PROPERTY_CML2_ARTICLE_VALUE"],$modelsExclude)) {
		$badModels[$el["PROPERTY_CML2_ARTICLE_VALUE"]] =
		[
			'price' => $el[$price_prop.'_VALUE'],
			'wbarticle' => $el['PROPERTY_WBARTICLE_VALUE'],
			'active_ozon' => $el['PROPERTY_OZON_ACTIVE_VALUE'],
			'dateCreate' => $el['DATE_CREATE'],
			'firstReason' => $firstReason
		];
	}
	//$excludeModels[] = $el["PROPERTY_CML2_ARTICLE"];
}
//print_r($badModels);
//
// $iStock = new OzonImportProducts();
// $resStock = $iStock->run($arModelDopControl);
// print_r($resStock);

foreach ($arModelDopControl as $offid) {
	$data = [
		"offer_id" => $offid
	];
	$data_string = json_encode($data);
	file_put_contents('/var/www/bitrix_logs/ozon/timing.txt', print_r(['arModelDopControl', date('Y-m-d H:i:s')], true), 8);
	$ch = curl_init($url . '/v2/product/info');
	curl_setopt($ch, CURLOPT_HTTPHEADER, array(
		'Api-Key:' . $token,
		'Client-Id:' . $client_id,
		'Content-Type:application/json'
	));
	curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($ch, CURLOPT_HEADER, false);
	$res = curl_exec($ch);
	curl_close($ch);

	$res = json_decode($res, true);
	if (!empty($res['result'])) {
		$reasons[$offid] = $res['result']['status']['item_errors'];
	}
	unset($res);
}
foreach ($badModels as $k => $v) {
	if (isset($reasons[$v['wbarticle']])) {
		$badModels[$k]['REASONS'] = $reasons[$v['wbarticle']];
	}
}
file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/ozon/logs/{$CABINET}/reportStock/bad.txt", print_r(json_encode($badModels), true));

//Агент-Инфо
$timeEnd = date('Y.m.d G:i:s');
$arStat = [
	'status' => 'COMPLETE',
	'status_text' => 'Завершено',
	'percent' => 100,
	'time_end' => $timeEnd
];
updateStatus( $module, $arStat, $CurDB );
$workers->updateStatus("N");
