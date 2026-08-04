<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
CModule::IncludeModule("panel.manager");
$objMS = new MoyskladAPI('s1');

$msStore = ['79ed7d71-0aa6-11ea-0a80-004200039aa4'];

foreach($msStore as $store_id){
    //if(strlen($store_id) > 0) $filter = "store.id=" . $store_id; else $filter = "";
    //$objMS->getStock(0, $filter);
    if(strlen($store_id) > 0)
        $filter = "filter=store=https://api.moysklad.ru/api/remap/1.2/entity/store/{$store_id}";
    else
        $filter = "";
    $objMS->getStock(0, $filter);

}
var_dump($objMS->MSPosition);
var_dump($objMS->LAST_ERROR);

 ?>
