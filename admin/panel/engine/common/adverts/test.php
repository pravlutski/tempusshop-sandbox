<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
require("lib/bootstrap.php");

$config = Loader::loadConfig( 'wb' );
Config::init( $config );

$api = new WBApiManager;
$auth['key'] = '';
$api->setAuthData( $auth );

$budget = new WBFinanceService( $api );
$a = $budget->calculateCashbackRefill();
$refillSum = Config::instance()->getBudgetSettings('refill');
var_dump($a);
var_dump($refillSum);
 ?>
