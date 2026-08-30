<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
$workers = new WorkersChecker("engine_ozon_importFBOGroup_php_IP_AVTO");
if (!$workers->checkStatus()) {
	exit;
}
$workers->updateStatus("Y");
set_time_limit(0);

use Bitrix\Main\Application,
	Bitrix\Main\Loader;

CModule::IncludeModule('panel.manager');

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
$time = date('Y.m.d G:i:s');
$CurDB = new DBPanel();

$in = array(
	"source	" => "'".$SOURCE."'",
	"script	" => "'checkFbo_".$CABINET."'",
	"time	" => "'".$time."'",
	"status	" => "'RUN'",
);

$fields = implode(",", array_keys($in));
$values = implode(",",$in);

$sql = "INSERT INTO ozon_tech_log ($fields) VALUES ($values)";
$CurDB->query($sql);



require_once 'checkFboNew.php';
require_once 'importPricesFBO.php';
require_once 'importStockFBO.php';

(new checkFBONEW($CABINET))->run();
// (new OzonImportPricesFBO($CABINET))->run();
// (new OzonImportStocksFBO($CABINET))->run();
$workers->updateStatus("N");
