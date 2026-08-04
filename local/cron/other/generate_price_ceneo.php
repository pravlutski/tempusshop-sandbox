#!/usr/bin/php
<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.pl";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
CModule::IncludeModule("main");
CModule::IncludeModule("iblock");
CModule::IncludeModule("catalog");
	
set_time_limit(0);


$APPLICATION->IncludeComponent("op:ceneo.market", ".default", array(), false);

die;

$path_tmp_file = $DOCUMENT_ROOT . "/prices/tmp_ceneo.xml";
$path_file = $DOCUMENT_ROOT . "/prices/ceneo.xml";

$text = file_get_contents("https://tempusshop.pl/prices/ceneo.php");
file_put_contents($path_tmp_file, $text);
if(filesize($path_tmp_file) > 200){
	if (!copy($path_tmp_file, $path_file)) {
		echo "не удалось скопировать $path_file...\n";
	}
}else{
	echo "Размер xml очень маленький\n";
}

?>