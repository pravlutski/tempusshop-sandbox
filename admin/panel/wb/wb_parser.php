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
    //file_put_contents( "debug.log", implode( PHP_EOL, $log ) . "\n", FILE_APPEND | LOCK_EX );

    return $result_set;
}

$dateTime = date("d.m.Y H:i:s");
$res =  parseWBProductIds( 4000 );
print_r($res);
die();
