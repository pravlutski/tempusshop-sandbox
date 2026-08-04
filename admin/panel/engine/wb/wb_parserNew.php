<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
set_time_limit(0);

CModule::IncludeModule("iblock");
// CModule::IncludeModule("main");
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

if ( in_array($argv[1], ['WR', 'TL']) ){
  $cab = $argv[1];
}else{
  $cab = 'WR';
}

$arStat = [
  'status' => 'IN_PROCESS',
  'status_text' => 'Начало',
  'percent' => '0',
  'time_start' => date('Y.m.d G:i:s')
];
updateStatus('control_'.$cab, $arStat, $dbPanel);

$strSql = "SELECT * FROM wdhs_wb_main_settings";

$results = $DB->Query($strSql, false, $err_mess.__LINE__);
while ($row = $results->Fetch()){
  $arSetting[$row['cabinet']] = $row;
}
$arSetting = json_decode($arSetting[$cab]['settings'],true);

$arSebes = array();
updateStatus('control_'.$cab, ['status_text' => 'Получение товаров ci_price', 'percent' => 10], $dbPanel );
if ($cab == 'WR') {
  $strSql = "SELECT * FROM `ci_price` WHERE `active_wb` = 'Y'";
} else {
  $strSql = "SELECT * FROM `ci_price` WHERE `active_wbtl` = 'Y'";
}

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
    curl_setopt( $curl, CURLOPT_COOKIE, getAuthCookie() );
    $data = curl_exec( $curl );

    curl_close($curl);

    return $data;
}

function getAuthCookie():string
{
  $path = "/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/wb/configs/analytics_cookie.json";

  if ( !file_exists( $path ) ) die('Нет куки файла');

  $json = file_get_contents( $path );
  $cookieArray = json_decode( $json, true );

  return $cookieArray['cookie'];
}


function parseWBProductIds( $pages , $cab ) {
    $starttime = time();
    $log = ["Parser started..."];
    $result_set = [];
    $max = 4;

    for( $i = 1; $i < $pages; $i++ ) {
        for ( $attempt = 1; $attempt < $max; $attempt++ ){
          if ($cab == 'WR') {
            $data = makeRequest(sprintf( "https://u-catalog.wb.ru/sellers/v4/catalog?dest=123589362&page=%d&limit=300&sort=rate&supplier=724646", $i) );
          } else {
            $data = makeRequest(sprintf( "https://u-catalog.wb.ru/sellers/v4/catalog?dest=123589362&page=%d&limit=300&sort=rate&supplier=320313", $i) );
          }
          if ( strpos($data, '429 Too Many Requests') ){
            print_r("Page {$i}, attempt {$attempt}: Too Many Requests. Waiting..." . PHP_EOL);
            sleep( $attempt * $max * rand(1, 3) );
            continue;
          }
          break;
        }

        $log[] = sprintf( "Trying to parse page: %d", $i );

        $data_arr = json_decode( $data, true );
        print_r( "Page {$i}. Products: " . count($data_arr['products'] ?? []) . PHP_EOL );


        if( ! is_array( $data_arr ) ) {
            $log[] = sprintf( "Parse error of page %d... Skipping", $i );
            continue;
        }

        if( ! isset( $data_arr['products'] ) || ! isset( $data_arr['total'] ) ) {
            $log[] = "Unexpected array structure: ";
            $log[] = print_r( $data_arr, true );
            continue;
        }

        $log[] = sprintf( "Total count of products is: %d", $data_arr['total'] );
        $log[] = sprintf( "Count of products on current page is: %d", count( $data_arr['products'] ) );

        if( count( $data_arr['products'] ) === 0 ) {
            $log[] = "Exit.";
            break;
        }

        foreach( $data_arr['products'] as $product ) {
            if (!in_array($product['id'],$result_set)) {
              $result_set[] = $product['id'];
            } else {
              // echo 'DUP ID: '.$product['id'].'';
            }
        }
        sleep( rand(2, 5) );
    }
    $worktime = time() - $starttime;
    $log[] = "Spent {$worktime} seconds";
    file_put_contents( "debug.log", implode( PHP_EOL, $log ) . "\n", FILE_APPEND | LOCK_EX );

    return $result_set;
}

$dateTime = date("d.m.Y H:i:s");
updateStatus('control_'.$cab, ['status_text' => 'Парсинг ВБ', 'percent' => 40], $dbPanel );

$res =  parseWBProductIds( 12 , $cab);

print_r('Got from WB (raw): ' . count($res ?? []) . PHP_EOL);

print_r(count($res));
print_r('###');

$wbSells = [];

$strSql = "SELECT nmid, article FROM wdhs_wb_props WHERE cabinet = '{$cab}'" ;
$results = $DB->Query($strSql, false, $err_mess.__LINE__);
$i = 0;
while ($row = $results->Fetch()){
  $i++;
  if (in_array($row['nmid'],$res)) {
    $wbSells[] = $row['article'];
  }
}

print_r($i);
print_r('@@@@@');

print_r(count($wbSells));
print_r('@@@@@');

$strSql = "SELECT * FROM ci_wb_top";
$arIDs = array();
$results = $DB->Query($strSql, false, $err_mess.__LINE__);
print_r(PHP_EOL . 'Got from WB (match): ' . count($wbSells ?? []) . PHP_EOL);
while ($row = $results->Fetch()){
  if (!in_array($row["article"],$wbSells) && !empty($row["article"])) {
    $notsell[] = $row["article"];
  }
}
print_r('start result: ' . count($notsell ?? []) . PHP_EOL);
$arSebes = array();

if ($cab == 'WR') {
  $strSql = "SELECT * FROM `ci_price` WHERE `active_wb` = 'Y'";
} else {
  $strSql = "SELECT * FROM `ci_price` WHERE `active_wbtl` = 'Y'";
}

$results = $DB->Query($strSql, false, $err_mess.__LINE__);

while ($row = $results->Fetch()){
  $arSebesTmp[$row["model"]][] = $row["price"];
}

foreach($arSebesTmp as $key => $values) {
  $arSebes[$key] = min($values);
}


$arSelect = Array("ID","IBLOCK_ID","IBLOCK_SECTION_ID","PROPERTY_CML2_ARTICLE","PROPERTY_WBPRICE","PROPERTY_WBTL_PRICE","PROPERTY_WBARTICLE2","DATE_CREATE", "PROPERTY_WBARTICLE");
$arFilter = Array(
	"IBLOCK_ID" => CProSet::IB_CATALOG,
	"=PROPERTY_CML2_ARTICLE" => $notsell,
);

$result = CIBlockElement::GetList(array(), $arFilter, false, false, $arSelect);

while ($el = $result->GetNext()){
  if ($cab == 'WR') {
    $sprav[$el['PROPERTY_CML2_ARTICLE_VALUE']] = [
      'article' => $el['PROPERTY_CML2_ARTICLE_VALUE'],
      'price' => $el['PROPERTY_WBPRICE_VALUE'],
      'sebes' => $arSebes[$el['PROPERTY_CML2_ARTICLE_VALUE']],
      'wb_article' => $el['PROPERTY_WBARTICLE2_VALUE'],
      'date' => $el['DATE_CREATE']
    ];
  } else {
    $sprav[$el['PROPERTY_CML2_ARTICLE_VALUE']] = [
      'article' => $el['PROPERTY_CML2_ARTICLE_VALUE'],
      'price' => $el['PROPERTY_WBTL_PRICE_VALUE'],
      'sebes' => $arSebes[$el['PROPERTY_CML2_ARTICLE_VALUE']],
      'wb_article' => $el['PROPERTY_WBARTICLE_VALUE'],
      'date' => $el['DATE_CREATE']
    ];
  }

}

//print_r($sprav);
if ($cab == 'WR') {
  $strSql = "SELECT DISTINCT model FROM ci_price WHERE active_wb = 'Y' AND active = 'Y'" ;
} else {
  $strSql = "SELECT DISTINCT model FROM ci_price WHERE active_wbtl = 'Y' AND active = 'Y'" ;
}

$results = $DB->Query($strSql, false, $err_mess.__LINE__);
while ($row = $results->Fetch()){
		$activeCiPrice[] = $row['model'];
}
$count = 0;
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
    $count++;
    unset($notsell[$key]);
  }
}
print_r(PHP_EOL . 'Filtered by ci_price: ' . $count . PHP_EOL );
unset($key);
unset($value);
$qurantine = [];
$strSql = "SELECT DISTINCT ARTICLE FROM ci_price_quarantine WHERE PRICE_ID = 'WB'" ;
$results = $DB->Query($strSql, false, $err_mess.__LINE__);
while ($row = $results->Fetch()){
		$qurantine[$row['ARTICLE']] = $row['ARTICLE'];
}

$count = 0;
foreach ($notsell as $key => $value) {
  if (in_array($value,$qurantine)) {
    $count++;
    unset($notsell[$key]);
  }
}
print_r('Filtered by quarantine: ' . $count . PHP_EOL );
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
$count = 0;
foreach ($notsell as $key => $value) {
  if (isset($arReserved[$value])) {
    unset($notsell[$key]);
    $count++;
  }
}
print_r('Filtered by reserves: ' . $count . PHP_EOL );
unset($key);
unset($value);

$min = intval($arSetting['minSebes']);
$max = intval($arSetting['maxSebes']);

$count = 0;
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
    $count++;
  }
}
print_r('Filtered by price requiremetns: ' . $count . PHP_EOL);
print_r('final result: ' . count($notsell ?? []) . PHP_EOL);
updateStatus('control_'. $cab, ['status_text' => 'Сохранение результата', 'percent' => 70], $dbPanel );



if ($cab == 'WR') {
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
} else {
  $excludeByOwner = array("EFR-539L-7C","EFR-S108D-7A","GA-2000S-7A","EFV-540D-1A","EFV-600L-2A","EFV-C100D-1B","EFR-526L-7A","EFV-540DC-1A","EF-539D-1A","EFR-539D-1A2","GA-100-1A2","EFV-540D-1A2","EFV-C100D-1A","EFV-C100D-2A","GA-2000-1A9","A-158WEA-1E","GA-700-1B","EFR-539L-1A","EF-539D-1A2","EFR-539BK-1A","EFV-600D-2A","GM-110-1A","EFR-552D-1A","EFR-526D-1A","EFV-550D-1A","EFR-552D-1A2","EFR-S108D-1A","GA-100B-7A","GA-100-1A4","EFV-550D-2A","GA-2100SU-1A","GA-2100-1A","EFR-526L-1A","EF-527D-1A","EFR-571D-1A","GA-100-1A1","GA-2000S-1A","EFR-556D-1A","ECB-900DB-1B","EFR-539D-1A","EFR-539L-5A","GA-100CF-1A","GA-2100-1A1","GA-700-7A","GA-110GB-1A","GBD-800-1B","GMA-S2100BA-2A1","SHE-3517L-1A","GMA-S2100-1A","EFV-C120L-8A","GM-110MF-1A","MTP-RS105M-3B","MTS-RS100L-1A","EFV-100D-8A","GM-6900GDA-9E","A-1000MA-7E","GMD-S5610IT-4B","GM-S110B-8A","MTP-B145D-3A","EFV-640D-5A","GM-700-1A","EFR-526D-2A","GMA-S140PP-4A","PRG-340-1E","EFV-640DC-1A","PRJ-B001-1E","MTP-B146G-9A","ABL-100WE-1A","GD-B500-1E","MTP-E735B-7A","EFV-640D-2B","GM-5600MF-2E","GA-2100BM-7A5","DW-6900U-1E","DW-5610UU-8E","MTS-RS100D-5A","GA-010-5A","ECB-S10D-8A","DW-5600TLS-8E","GA-110TLS-8A","EFV-150DC-1A","DWE-5600HG-1E","MTD-140D-2A","GM-S2110-4A","GM-S5600MF-6E","EFR-526L-2A","A-1000MGA-5E","LTP-2024VMG-7C","EQS-900DB-1A","A-1000M-1B","BGA-290RA-4A","GA-2100FL-1A","PRG-340L-5E","EFR-575L-7A","MTS-RS100D-3A","GM-2110D-7A","EFR-575CL-3A","DW-5610SU-3E","GMA-P2100M-7A","EFB-109D-1A","PRG-330-1E","GA-700HD-8A","MTP-1302PGC-5A","GBM-2100-1A","GMD-S5610PP-4E","G-9300-1E","GMA-S2140RX-7A","EFR-575D-1A","GD-010CE-5E","EFR-556DB-1A","A-1000D-7E","MTP-1302PGC-3A","GA-2100BM-7A8","MTP-M305L-1A2","GBX-100S-1E","GM-S2110-7A6","MTP-1302PL-5A","EFR-526D-3A","DW-5600UE-1E","GA-010-1A1","GA-110WD-1A","PRG-340B-3E","DW-5600UHR-1E","EFV-650CL-5A","EFB-730D-1A","MTP-B125MR-8A","EFB-710D-1A","LTP-1302PRG-2A","GBA-800-1A","G-7900-1E","LTP-E176D-1A","ECB-2000TP-1A","MTP-M305D-1A","BA-130-4A","GA-900SKE-8A","GA-400-1B","EFS-S570DB-2A","GM-2100G-1A9","EQS-920DB-1A","BA-130-7A1","GMA-S120GS-3A","EFR-S108D-3A","MTP-B145D-2A2","GA-2300-1A","GBA-900-7A","EFV-C110D-2B","EFV-600CL-3A","GD-B500-7E","MTP-M305D-1A2","GG-1000-1A5","EFV-C110D-1A4","EF-129D-1A","EFR-526L-2C","GMA-S2100-4A","G-5600UE-1E","GM-110BB-1A","EFV-620D-2A","EF-527D-3A","EFV-610D-1A","EFV-610DC-1A","EFV-640DC-3A","EFV-C110D-1A3","EFR-S567D-2A","EF-527D-2A","EFB-108D-1A","AMW-870-1A","EFV-640D-1A","EFR-S108D-2A","GA-B2100-1A","EFR-571MDC-1A","GM-2100-1A","GM-2100BB-1A","EFB-108D-7A","GA-2100SKE-7A","AMW-870D-1A","ECB-30P-1A","GMA-S120GS-8A","GW-B5600BC-1B","GBD-200SM-1A6","EFR-526D-7A","ECB-900DB-1A","GA-B2100-1A1","EFV-640D-2A","EFS-S570DC-1A","EFV-630D-1A","GM-5600-1E","PRG-340-3E","GBX-100NS-1E","PRG-340T-7E","EFV-620L-1A","GA-B2100-2A","GBX-100-1E","GBA-900UU-3A","GA-2100RC-1A","GBA-900-1A","ECB-30D-1A","GA-2000-1A2","GA-2100-7A","GA-2100-7A7","ECB-2000D-1A","EFV-550L-1A","GA-2100-1A2","MWA-100HD-7A","GBA-900-1A6","EFV-620D-1A4","GX-56BB-1E","GBD-200UU-1E","GA-2110SU-3A","DW-5600BB-1E","GMA-S2100-7A","ECB-900MDC-1A","GMA-S2100-4A2","GMA-S2100MD-4A","DW-5600MW-7E","GBD-200-1E","EFV-550GY-8A","BA-110XCP-4A","GM-2100B-3A","GA-2100VB-1A","GW-5000HS-1E","ECB-S10DB-1A","GD-010-1A1","EFS-S640DC-1A","MTP-M305M-8A","ECB-2200RC-1A9","GMD-B300SC-4E","GMA-P2100PP-4A","DWE-5600JB-1A9","GM-5600UB-1E","EFB-730D-2A","GAX-100MSA-3A","GMA-B800-1A","BGA-280SW-6A","GM-110VG-1A9","BGA-10-7A","EFB-109D-2A","SHE-4562GM-4A","GA-100FL-8A","GM-S5600U-1E","GA-2100RL-1A","GMD-S5610IT-3E","BGA-10-6A","EQS-960DC-1A","MTD-125D-2A3","EFV-610DE-2A","MSG-400G-7A","EFR-575D-2A","BGA-10-3A","EFV-560D-2A","GMA-P2125W-6A","GST-B600-1A","GM-110D-8A","BA-110XSW-7A","EFV-610ECL-1A","ECB-950MP-1A","MTP-M300L-7A","ECB-2200RC-1A3","EFV-610DE-3A","GMA-P2100ST-7A","DW-6900WD-1E","GA-100FL-1A","EFV-610EL-5A","GA-110AS-2A","GM-2110D-2B","MTP-M105L-1A","DW-6900TR-1E","GST-B400-1A","BGA-280TD-2A","DW-6900TR-4E","LTP-1165PA-7C2","GA-700HDS-7A","BG-169CH-9E","GA-2100BM-7A2","GD-350GB-1E","DW-5000R-1A","BGA-290RA-7A","GA-700BBR-1A","GMD-S5610IT-4A","LA-700WEM-4A","LA-670WETG-9E","EFV-650D-2B","GW-5000HS-7E","GM-S2110PG-1A4","GA-110AS-5A","GM-S2110-7A9","EFV-100D-2B","LTP-E306R-2A","GMA-P2100M-2A","EFS-S630DC-2A","GM-2110D-3A","EFV-650L-7A","GA-010CE-2A","BGD-565GC-2E","ECB-S10D-2A","GA-2100HDS-7A","GM-S5600UGB-1E","EFV-650D-4A","ECB-2200CB-2A","GA-2100AS-2A","GMD-B300SC-7E","GM-2100YM-8A","DW-5600RL-1E","GMA-P2100M-4A","GMD-B300SC-2E","BGA-280TD-7A","SHE-3066PGL-1A","MTP-M300D-2A","GMD-S5610IT-6E","BGA-280TD-4A","GA-110RL-1A","GM-700G-9A","DW-5610UU-3E","GA-2100AS-5A","BGA-150EF-7B","DW-6900RL-1E","GM-5600YMG-9E","DW-6900TR-9E","GMD-S5610IT-1E","GA-B2100BBR-1A","G-9100-1E","GM-S5600G-7E","GBD-300-9E","MTP-B175-1B","GA-B2100LUU-8A","GM-S2110-3A","BG-169CH-4E","GM-5600YM-8E","GD-B500S-8E","GA-2100FL-8A","GM-110BD-1A9","GBX-100S-2E","GM-S2110B-8A","GA-B2100-3A","ABL-100WEPC-1B","MTS-RS100D-1A","GA-B2100LUU-5A","BGA-10-4A","EQS-950DC-2A","BGA-320-4A1","EFB-109D-7A");
  foreach ($notsell as $key => $value) {
    if (in_array($value,$excludeByOwner)) {
      $badModels[] = [
        'article' => $value,
        'price' => $sprav[$value]['price'],
        'sebes' => $sprav[$value]['sebes'],
        'date' => $sprav[$value]['date'],
        'wb_article' => $sprav[$value]['wb_article'],
        'reason' => 'Причина не установлена',
      ];
    }
  }
}


$arStat = [
  'status' => 'COMPLETED',
  'status_text' => 'Выполнено',
  'percent' => 100,
  'time_end' => date('Y.m.d G:i:s')
];
updateStatus('control_'.$cab, $arStat, $dbPanel );

// echo 'ready';
file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/wb/logs/reportStock/{$cab}/bad.txt", print_r(json_encode($badModels), true));
