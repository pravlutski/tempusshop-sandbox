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
    file_put_contents( "debug.log", implode( PHP_EOL, $log ) . "\n", FILE_APPEND | LOCK_EX );

    return $result_set;
}

$dateTime = date("d.m.Y H:i:s");
$res =  parseWBProductIds( 4000 );
print_r($res);
die();

$inControl = array(
  "datetime" => "'".$dateTime."'",
);

$resControl = $DB->Insert("whds_control_wb_parser", $inControl, $err_mess.__LINE__);

file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/local/control/wb/parser/".$resControl.".txt", print_r(json_encode($res), true));

sleep(5);

$strSql = "SELECT * FROM whds_control_wb_parser ORDER BY id DESC LIMIT 1";
$results = $DB->Query($strSql, false, $err_mess.__LINE__);
while ($row = $results->Fetch()){
	$last_parsr_wb_id  =  $row;
}

$datetime = $last_parsr_wb_id['datetime'];
$date_have = $datetime;
$fileParse = file_get_contents('/var/www/bitrix/data/www/tempusshop.ru/local/control/wb/parser/'.$last_parsr_wb_id['id'].'.txt');
$arrayParse = json_decode($fileParse,true);


$strSql = "SELECT * FROM whds_control_wb WHERE datetime < '$datetime' ORDER BY id DESC LIMIT 1";
$results = $DB->Query($strSql, false, $err_mess.__LINE__);
while ($row = $results->Fetch()){
	$last_upload_stock_wb_id  =  $row;
}

$date_up = $last_upload_stock_wb_id['datetime'];
$fileStock = file_get_contents('/var/www/bitrix/data/www/tempusshop.ru/local/control/wb/stock/'.$last_upload_stock_wb_id['id'].'.txt');
$arrayStock = json_decode($fileStock,true);


$base_url = WB_BASE_URL;
$path = "/api/v1/supplier/stocks";

$data_string = array('dateFrom' => '2020-01-01');
$author = CMaxyssWb::get_setting_wb("AUTHORIZATION", "WR");
$api = new RestClient([
	'base_url' => 'https://statistics-api.wildberries.ru',
	'curl_options' => array(
			CURLOPT_POST => true,
			CURLOPT_SSL_VERIFYPEER => false,
			CURLOPT_SSL_VERIFYHOST => false,
			CURLOPT_RETURNTRANSFER => TRUE,
			CURLOPT_HEADER => TRUE,
			CURLOPT_CUSTOMREQUEST => 'GET',
			CURLOPT_HTTPHEADER => array(
					'Content-Type: application/json',
					'Authorization: ' . $author,
			)
	)
]);
$path = '/api/v1/supplier/stocks?dateFrom=2020-01-01';
$str_result = $api->post($path, []);
//print_r(json_decode($str_result->response,true));
$arRes = json_decode($str_result->response,true);

if (!empty($arRes)) {

foreach ($arRes as $key => $value) {
	if (isset($stockFbo[$value['supplierArticle']])) {
		$stockFbo[$value['supplierArticle']] =  intval($stockFbo[$value['supplierArticle']]) + intval($value['quantity']);
	} else {
		$stockFbo[$value['supplierArticle']] =  intval($value['quantity']);
	}
}

foreach ($stockFbo as $key => $value) {
	if ($value == 0 or $value == '0') {
		unset($stockFbo[$key]);
	}
}

$arFilter = Array(
      "IBLOCK_ID"  => 16,
      "PROPERTY_WBARTICLE2" => array_keys($stockFbo)
    );

    $arSelect = array("ID", "IBLOCK_ID", "XML_ID", "PROPERTY_CML2_ARTICLE","PROPERTY_AEN2" ,"PROPERTY_PROP_MAXYSS_NMID_CREATED_WB", "PROPERTY_TYPE", "PROPERTY_WBARTICLE2");
    $rs = CIBlockElement::GetList( array(), $arFilter, false, false, $arSelect );

    $filtered = [];
     while($art = $rs->GetNext()){
      if (!empty($arrayStock['UPLOAD_NOT_NULL'])) {
      	if (in_array($art['PROPERTY_AEN2_VALUE'],$arrayStock['UPLOAD_NOT_NULL'])) {

  	      foreach ($art['PROPERTY_PROP_MAXYSS_NMID_CREATED_WB_DESCRIPTION'] as $key => $value) {
  	          if ($value == 'WR') {
  	            $nmid = $art['PROPERTY_PROP_MAXYSS_NMID_CREATED_WB_VALUE'][$key];
  	          }
  			   }
  				 $nmIdexlude[$nmid] = $art['PROPERTY_AEN2_VALUE'];
  				 unset($stockFbo[$art['PROPERTY_WBARTICLE2_VALUE']]);
   			 }
       }
			 unset($nmid);
		 }
		 foreach ($arrayParse as $key => $value) {
		 	if (isset($nmIdexlude[$value])) {
				//unset($arrayParse[$key]);
			}
		 }
	 $arResult['CONTROLLER']['OPERTAION_KEY'] = 1;
} else {
	$arResult['CONTROLLER']['OPERTAION_KEY'] = 0;
}
$coun = 0;
foreach ($arrayStock['ITEMS_PREPARE'] as $key => $value) {
  $coun =  $coun + count($value);
}
print_r($coun);
print_r('<br>');
print_r('EXCLUD_FBO_SCRIPT');
print_r('<br>');
print_r($arrayStock['EXCLUD_FBO_SCRIPT']);
print_r('<br>');
print_r('NOT_NULL');
print_r('<br>');
print_r($arrayStock['UPLOAD_NOT_NULL']);
print_r('<br>');
print_r('UPLOAD_NULL_PRICE_FILTER');
print_r('<br>');
print_r($arrayStock['UPLOAD_NULL_PRICE_FILTER']);
print_r('<br>');
print_r('UPLOAD_NULL_NOT_AVAIL');
print_r('<br>');
print_r($arrayStock['UPLOAD_NULL_NOT_AVAIL']);
print_r('<br>');
$parseCount = count($arrayParse);
if (!empty($arrayStock['UPLOAD_NOT_NULL'])) {
  $updateCount = count($arrayStock['UPLOAD_NOT_NULL']);
} else {
  $updateCount = 0;
}
$fboCount = count($stockFbo);
$arResult['CONTROLLER']['HAVE'] = $parseCount - $fboCount;
$arResult['CONTROLLER']['UP'] = $updateCount;

$inControl = array(
	"have" => "'".$arResult['CONTROLLER']['HAVE']."'",
	"up" => "'".$arResult['CONTROLLER']['UP']."'",
  "fbo" => "'".$fboCount."'",
  "date_up" => "'".$date_up."'",
  "date_have" => "'".$date_have."'",
	"OPERTAION_KEY" => "'".$arResult['CONTROLLER']['OPERTAION_KEY']."'",
);
$resControl = $DB->Update("whds_control_panel", $inControl, "WHERE id = 1", $err_mess.__LINE__);
print_r($resControl);
//$resControl = $DB->Insert("whds_control_panel", $inControl, $err_mess.__LINE__);
