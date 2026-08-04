#!/usr/bin/php
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


CProSet::setOption("PARSER_s3", "start");

list($usec, $sec) = explode(" ", microtime());
$time_start = ((float)$usec + (float)$sec);
$txt = "";

foreach ((array)$_SERVER['argv'] as $v){
	list($k,$v) = explode("=",$v);
	if ($k && $v) $_REQUEST[$k] = $v;
}
global $DB;
if(CProSet::getOption("PARSE_CENEO_URI") == "Y" || $_REQUEST["force"] == "Y"){
	$lastID = 0;
	if(floatval(CProSet::getOption("PARSE_CENEO_URI_PER")) > 0 && floatval(CProSet::getOption("PARSE_CENEO_URI_PER")) < 100){
		$lastID = CProSet::getOption("PARSE_CENEO_URI_LAST_ID") - 101;
		if($lastID < 0) $lastID = 0;
	}
	CProSet::setOption("PARSE_CENEO_URI_PER", "0");
	$obj = new CCeneoParserURI();
	$update = true;

	do {
		$up = $obj->parse($lastID);
		//usleep(1000);
		$lastID = $up["LAST_ID"];
		if(CProSet::getOption("PARSE_CENEO_URI") == "Y"){
			$txt = "Прервано ceneo на - " . CProSet::getOption("PARSE_CENEO_URI_PER") . " %. ";
			CLog::add2log(array("event" => "PC", "text" => $txt));
			$update = false;
		}
		if($up["PERCENT"] >= 100 || $up === false){
			$update = false;
		}
		//$update = false;
	} while ($update == true);

	$strSql = "SELECT COUNT(*) as cnt FROM ci_ceneo_price";
	$results = $DB->Query($strSql, false, $err_mess.__LINE__);
	if ($row = $results->Fetch()){
		CProSet::setOption("PARSE_CATALOG_CENEO_URI", $row["cnt"]);
	}
	//CLog::add2log(array("event" => "PC", "text" => $txt));
}

CProSet::setOption("PARSER_s3", "end");

//require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_after.php");
?>