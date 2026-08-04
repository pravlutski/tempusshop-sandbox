#!/usr/bin/php
<?php
//#!/usr/local/php/bin/php -q
//
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
set_time_limit(3600);

CModule::IncludeModule("iblock");
CModule::IncludeModule("main");
CModule::IncludeModule("panel.manager");
CModule::IncludeModule('maxyss.wb');

foreach ((array)$_SERVER['argv'] as $v){
	list($k,$v) = explode("=",$v);
	if ($k && $v) $_REQUEST[$k] = $v;
}

if(in_array($_REQUEST["cabinet"], array("DEFAULT", "WR"))){
	$cabinet = $_REQUEST["cabinet"];
	if($cabinet == "WR")
		$pBarcode = "WBARTICLE2";
	else
		$pBarcode = "WBARTICLE3";
}else{
	$cabinet = "WR";
	$pBarcode = "WBARTICLE2";
}

// получаем файл с CARDID и грузим себе
CProSet::setOption("WB_UPDATE_CARDID", "");
CProSet::setOption("WB_ALL_CYCLE_PER", "50");

/*
$host = '91.215.152.251';
$usr = 'root';
$pwd = 'hDfQ579M';

$sftp = new SFTPConnection($host, 22);
$sftp->login($usr, $pwd);
$filelist = $sftp->scanFilesystem("/home/wb_article_numbers/");
krsort($filelist);
$filelist = array_values($filelist);

foreach($filelist as $key => $file){
	if ($cabinet == "WR" && preg_match("/ooo/i",  mb_strtolower($file))){
		$curFile = $file;
		break;
	}elseif ($cabinet == "DEFAULT" && preg_match("/ip/i",  mb_strtolower($file))){
		$curFile = $file;
		break;
	}
}

//копируем файл себе
$localFile = $_SERVER["DOCUMENT_ROOT"] . "/upload/wb_article_numbers/{$curFile}";

if(file_exists($localFile)){
	rename($localFile, str_replace(".csv", "_" . date("Y_m_d_h_i_s") . ".csv", $localFile));
}

$sftp->receiveFile("/home/wb_article_numbers/{$curFile}", $localFile);

if(file_exists($localFile)){
	$handle = fopen($localFile, "r");

	$array_line_full = array();
	while (($line = fgetcsv($handle, 0, "\t")) !== FALSE) {
		$arCsv[] = $line;
	}
	//
	fclose($handle); //Закрываем файл
	$arWB = array();
	foreach($arCsv as $key => $arItem){
		if(count($arItem) == 3){
			$article = trim($arItem[0]);
			if(!$arWB[$article]){
				$arWB[$article] = array(
					"BARCODE" => $arItem[0],
					"CARD_ID" => $arItem[1],
					"NM_ID" => $arItem[2],
				);
			}
		}
	}
	$i = $update = $not_find = 0;
	if(count($arWB) > 0){
		//получаем свойства для товаров из файла
		$arFilter = Array(
			"IBLOCK_ID"	=> 16,
			"PROPERTY_{$pBarcode}" => array_keys($arWB)
		);

		$rs = CIBlockElement::GetList(array(), $arFilter, false, false, array("ID", "PROPERTY_{$pBarcode}", "PROPERTY_PROP_MAXYSS_CARDID_WB"));
		while($ar = $rs->GetNext()){

			$bxItems[$ar["PROPERTY_{$pBarcode}_VALUE"]] = array(
				"ID" => $ar["ID"],
				"BARCODE" => $ar["PROPERTY_{$pBarcode}_VALUE"],
			);
			$arCard = array();
			foreach($ar["PROPERTY_PROP_MAXYSS_CARDID_WB_DESCRIPTION"] as $k => $v){
				if($ar["PROPERTY_PROP_MAXYSS_CARDID_WB_VALUE"][$k])
					$arCard[$v] = $ar["PROPERTY_PROP_MAXYSS_CARDID_WB_VALUE"][$k];
			}

			if($arCard){
				$bxItems[$ar["PROPERTY_{$pBarcode}_VALUE"]]["CARDID"] = $arCard;
			}

		}

		foreach($arWB as $key => $arItem){
			if($bxItems[$arItem["BARCODE"]]){

				$i++;

				if($bxItems[$arItem["BARCODE"]]["CARDID"][$cabinet] == $arItem["CARD_ID"]) continue;

				$bxItems[$arItem["BARCODE"]]["CARDID"][$cabinet] = $arItem["CARD_ID"];
				$ar_val = array();
				foreach($bxItems[$arItem["BARCODE"]]["CARDID"] as $_cab => $v){
					$ar_val[] = array("VALUE" => $v, "DESCRIPTION" => $_cab);
				}

				if($ar_val){
					CIBlockElement::SetPropertyValuesEx($bxItems[$arItem["BARCODE"]]["ID"], false, array("PROP_MAXYSS_CARDID_WB" => $ar_val));
					$update++;
				}

			}else{
				$not_find++;
			}
		}

	}
	CProSet::setOption("WB_UPDATE_CARDID", "CARDID обновлен у - " . $update . " из " . $i . ($not_find > 0 ? ". Не найдено - " . $not_find : ""));
}else{
	$arLog = array(
		"event" => "E",
		"text" => "WB. файл {$curFile} не удалось скопировать",
		"detail" => array("file" => $localFile),
	);
	CLog::add2log($arLog);
}
*/
/* end */

// отправляем артикулы в ВБ
CProSet::setOption("WB_ALL_CYCLE_PER", "75");

$bxItems = array();
$res = $DB->Query("SELECT IBLOCK_ELEMENT_ID,VALUE FROM b_iblock_element_prop_m16 WHERE IBLOCK_PROPERTY_ID = '2795' && DESCRIPTION = '{$cabinet}'");
while ($ar = $res->Fetch()){
	if(strlen($ar["VALUE"]) > 30)
		$bxItems[$ar["IBLOCK_ELEMENT_ID"]] = $ar["VALUE"];
}

$objPricelist = new CPanelPricelist;
$objSupplier = new CPanelSupplier;
$objCurrency = new CPanelCurrency;
$objUtils = new CPanelUtils;

CProSet::setOption("WB_GET_ARTICLES", "");
CProSet::setOption("WB_GET_ARTICLES_PER", "0");

$arFilter = Array(
	"IBLOCK_ID"	=> 16,
	//">PROPERTY_WBPRICE" => 0,
	//">CATALOG_QUANTITY" => 0,
	//"PROPERTY_PROP_MAXYSS_NMID_WB" => false,
);
//

$arFilter["!PROPERTY_AEN2"] = false;
if ($cabinet == "WR") {
	$arFilter["!PROPERTY_WBARTICLE2"] = false;
} else {
	$arFilter["!PROPERTY_WBARTICLE3"] = false;
}
$arFilter["ID"] = CMaxyssWb::getItemsWB();
// $arFilter["ID"] = [2964, 74131];

//prent($arFilter["ID"],0,1);


//$arFilter["ID"] = 36990;

$rs = CIBlockElement::GetList(array(), $arFilter, false, false, array("ID","NAME"));
$cntAll = $rs->SelectedRowsCount();

$arLogDetail = array();

$i = 0;
while($ar = $rs->GetNext()){
	$i++;

	if($i % 50 == 0){
		$per = ($i / $cntAll) * 100;
		$per = round($per, 2);
		CProSet::setOption("WB_GET_ARTICLES", "Обработано - " . $i . " товаров");
		CProSet::setOption("WB_GET_ARTICLES_PER", $per);

		$globalPer = 75 + ($per / 100 * 25);
		CProSet::setOption("WB_ALL_CYCLE_PER", round($globalPer, 2));
	}
	file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/local/cron/marketplace/wb/arrTest.txt", print_r($ar["ID"] , true).PHP_EOL, FILE_APPEND);
	if($bxItems[$ar["ID"]]) continue;

	$url = "https://tempusshop.ru/bitrix/tools/maxyss.wb/ajax.php?action=data_card&product_id={$ar["ID"]}&lk={$cabinet}&pass=bc051158c95a625d2f2d452ba7194b46";

	$res = json_decode(file_get_contents($url), true);

	$mess = "";
	$flg = false;
	if($res["success"] && $res["success"] == "MAXYSS_WB_DATA_SUCCESS"){
		$mess = $ar["ID"] . " - " . $ar["NAME"] . " успешно установлен";
		$flg = true;
	}else{
		$mess = "Ошибка " . $ar["ID"] . " - " . $ar["NAME"];
	}
	$arLogDetail[] = array(
		"mess" => $mess,
		"res" => $res,
	);


}

if(count($arLogDetail) > 0){
	file_put_contents("/home/bitrix/logs/wb/last_get_articles_{$cabinet}.txt", print_r($arLogDetail));
	$arLog = array(
		"event" => "WB",
		"text" => "Получение артикулов с WB",
		"detail" => $arLogDetail,
	);
	CLog::add2log($arLog);
}

CProSet::setOption("WB_GET_ARTICLES", "Отправлено - " . count($arLogDetail) . " из " . $i . " товаров");
CProSet::setOption("WB_GET_ARTICLES_PER", 100);
CProSet::setOption("WB_ALL_CYCLE_PER", 100);
//require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_after.php");
?>
