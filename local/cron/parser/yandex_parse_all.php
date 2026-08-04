#!/usr/bin/php
<?php

$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
set_time_limit(600);
		
CModule::IncludeModule("iblock");
CModule::IncludeModule("main");
CModule::IncludeModule("panel.manager");

CProSet::setOption("PARSER_s1", "start");
CProSet::setOption("YANDEX_PARSE_ALL", "");

$TsTriggers = new TsTriggers();

$host = '91.215.152.251';
$usr = 'root';
$pwd = 'hDfQ579M';
	 
$sftp = new SFTPConnection($host, 22);
$sftp->login($usr, $pwd);
$filelist = $sftp->scanFilesystem("/home/partner_yandex2/");
krsort($filelist);
$filelist = array_values($filelist);
$curFile = $filelist[0];

if(!$curFile){
	$TsTriggers->SetError(["Парсер yandex. Нет файлов на сервере"]);
	$TsTriggers->SendTriggerErrors();
	CProSet::setOption("PARSER_s1", "end");
	exit();
}
$lastFile = CProSet::getOption("YANDEX_LAST_FILE");
//if($curFile != $lastFile || 1==1){
	
	//копируем файл себе
	$localFile = $_SERVER["DOCUMENT_ROOT"] . "/upload/partner_yandex/{$curFile}";
	
	if(file_exists($localFile)){
		//chmod($localFile, 07777);
		rename($localFile, str_replace(".xlsx", "_" . date("Y_m_d_h_i_s") . ".xlsx", $localFile));
		//unlink($localFile);
	}

	$sftp->receiveFile("/home/partner_yandex2/{$curFile}", $localFile);
	//echo "sdfdsf\r\n";die;
	//$localFile = $_SERVER["DOCUMENT_ROOT"] . "/upload/partner_yandex/11-30-2022-07-01_competitor_prices_940142.xlsx";
	if(file_exists($localFile)){
		$obj = new CYandexParser($localFile);
		$res = $obj->parse();
		
		$auto = COption::GetOptionString("panel.manager", "PRICELIST_AUTO_SET_s1");
		CProSet::setOption("PARSER_s1", "end");
		//if($auto == "Y"){
			//запускаем обновление цен
//			$obj = new CPriceUpdate("s1");
//			$obj->setAllPrice();
		//}

		

	}else{
		$arLog = array(
			"event" => "E",
			"text" => "Yandex. файл не удалось скопировать",
			"detail" => array("file" => $localFile),
		);
		CLog::add2log($arLog);
		CProSet::setOption("YANDEX_PARSE_ALL", "ERROR_1");
		
		$TsTriggers->SetError(["Парсер yandex. Файл не удалось скопировать"]);
		$TsTriggers->SendTriggerErrors();
	}
//}






//require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_after.php");
?>