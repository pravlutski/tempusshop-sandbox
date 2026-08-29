#!/usr/bin/php
<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
require_once $_SERVER['DOCUMENT_ROOT'] . '/local/classes/CronWorkerGuard.php';
if (!CronWorkerGuard::startFromArgv()) {
	exit;
}

exec("df -h",$output,$code); 

$tmp = explode(" ", $output[1]);
$tmp = array_diff($tmp, array('', '/'));

$tmp = array_values($tmp);
if(intval($tmp[3]) <= 1){
	$message = "На сервере занято более " . $tmp[4] . ". Свободно " . $tmp[3];
	$arFields = array(
		"SUBJECT" => "На сервере занято более " . $tmp[4],
		"MESSAGE" => $message,
	);
	//CEvent::SendImmediate("NEW_FORUM_PRIV", array("s1"), $arFields, "N", 224);
	CEvent::SendImmediate("IM_NEW_MESSAGE", array("s1"), $arFields, "N", 405);
}
//require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_after.php");
?>