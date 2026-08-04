<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');?>
<?
global $DB;
global $USER;
//$arGroups = $USER->GetUserGroupArray();
//if($USER->isAdmin() || in_array(6, $arGroups)) $arResult["ACCESS"] = true;
if(!CModule::IncludeModule('panel.manager'))return;

use \Bitrix\Main\Data\Cache;
?>
<div class="row">
<h3>План закупок</h3>
<?
//if(!$arResult["ACCESS"]) return;
//$fileData = "/var/www/bitrix/data/www/logs/ms/tempPurchase/" . $USER->getID() . ".txt";


$start = debug_microtime_float();

$arResult = array();

$arResult["PROGNOSIS"] = intval($_POST["prognosis"]);
$arResult["MARGIN"] = intval($_POST["margin"]);
$arResult["MIN_PLAN"] = intval($_POST["min_plan"]);
$arResult["CURRENCY"] = $_POST["currency"];
$arResult["BRAND"] = $_POST["brand"];

$arResult["DATE_FROM"] = $_POST["date_from"];
$arResult["DATE_TO"] = $_POST["date_to"];
if (!empty($_POST["date_pre"])) {
	$arResult["DATE_PRE"] = $_POST["date_pre"];
}else {
	$arResult["DATE_PRE"] = date('Y-m-d');
}
$arResult["MIN_DIFFERENCE"] = intval($_POST["min_difference"]);
$arResult["SUPPLIER"] = $_POST["supplier"];
//edit

// if (count($arResult["LOGIN"]) > 1) {
//   foreach ($arResult["LOGIN"] as $key => $value) {
//     $arResult["LOGIN"][$key] = 1;
//   }
// } else {
//   $arResult["LOGIN"][$arResult["LOGIN"][0]] = 1;
// }

$arResult["LOGIN"] = array('s1', 'msk');



$arSet = array('s1' => 1, 'msk' => 1);
// сохраняем настройки
$arSettings = [
	"DATE_FROM" => $arResult["DATE_FROM"],
	"DATE_TO" => $arResult["DATE_TO"],
  "DATE_PRE" => $arResult["DATE_PRE"],
	"PROGNOSIS" => $arResult["PROGNOSIS"],
	"MARGIN" => $arResult["MARGIN"],
	"MIN_PLAN" => $arResult["MIN_PLAN"],
	"BRAND" => $arResult["BRAND"],
	"CURRENCY" => $arResult["CURRENCY"],
	"MIN_DIFFERENCE" => $arResult["MIN_DIFFERENCE"],
	"SUPPLIER" => $arResult["SUPPLIER"],
  "LOGIN" => $arSet,
];

$strSql = "SELECT * FROM ci_opt_settings WHERE USER_ID = '".$USER->getID()."' AND TYPE = 'purchase'";
$results = $DB->Query($strSql, false, $err_mess.__LINE__);

if ($row = $results->Fetch()){
	$DB->Update("ci_opt_settings", array("SETTINGS" => "'".addslashes(json_encode($arSettings))."'"), "WHERE ID='".$row["ID"]."'", $err_mess.__LINE__);
}else{
	$in = array(
		"USER_ID" => "'".addslashes($USER->getID())."'",
		"SETTINGS" => "'".addslashes(json_encode($arSettings))."'",
		"TYPE" => "'purchase'",
	);
	$ID = $DB->Insert("ci_opt_settings", $in, $err_mess.__LINE__);
}

if($arResult["PROGNOSIS"] <= 0){
	$arResult["ERROR"][] = "Колонка 'Прогноз' должна быть больше 0";
}
if($arResult["MIN_PLAN"] <= 0){
	$arResult["ERROR"][] = "Колонка 'Минимум' должна быть больше 0";
}

$arResult["DATE_DIFF"] = floor((strtotime($arResult["DATE_TO"]) - strtotime($arResult["DATE_FROM"])) / (60 * 60 * 24));
if($arResult["DATE_DIFF"] <= 0){
	$arResult["ERROR"][] = "Заполните корректные даты ОТ-ДО";
}

//new edit
//if (count($arResult["LOGIN"]) > 1) {

$ms = new MoyskladAPI('msk');

function getNamePosition($tId) {
	global $DB;
	$strSql = "SELECT item_number FROM ci_ms_directory_products WHERE product_id='".$tId."'";
	$results = $DB->Query($strSql, false, $err_mess.__LINE__);
	while ($row = $results->Fetch()){
		$code = $row['item_number'];
	}
	return $code;
}



$dateConst = date("Y-m-d");
$dateP = new DateTime($dateConst);
$datePreP = new DateTime($arResult["DATE_PRE"]);
$period = new DatePeriod($dateP, new DateInterval('P1D'), $datePreP);
$arrayOfDates = array_map(
		function($item){return $item->format('Y-m-d');},
		iterator_to_array($period)
);
$arrayOfDates = array_flip($arrayOfDates);
$fileDataPre = "/var/www/bitrix/data/www/logs/ms/tempPurchase/cache/msk/post/".$arResult["DATE_PRE"].".txt";

if (file_exists($fileDataPre)) {

	$arTmpPre = file_get_contents($fileDataPre);
	$arPre = json_decode($arTmpPre,true);

	foreach ($arPre as $key => $value) {
	       $arDataPre[$key] = $value;
	}

} else {
	$resZakaz = $ms->customRequest("https://api.moysklad.ru/api/remap/1.2/entity/purchaseorder");
	foreach ($resZakaz['rows'] as $key => $value) {
		$d = explode(' ',$value['moment']);
		$d = $d[0];
		if (isset($arrayOfDates[$d])) {
			$tmpReq = $ms->customRequest($value['meta']['href']);
			$tmpPos = $ms->customRequest($tmpReq['positions']['meta']['href']);
			foreach ($tmpPos['rows'] as $k => $v) {
				$tId = explode('https://api.moysklad.ru/api/remap/1.2/entity/product/', $v['assortment']['meta']['href']);
				$tId = $tId[1];
				$code = getNamePosition($tId);
				$q = $v['quantity'];
				$tmpOut[$code][$d] = $q;
			}
		}
	}
	$arDataPre = $tmpOut;
	$tmpOutJson = json_encode($tmpOut);
	file_put_contents($fileDataPre, $tmpOutJson);
}

$arResult["DIFF_PRE"] = floor((strtotime($arResult["DATE_PRE"]) - strtotime($dateConst)) / (60 * 60 * 24));
$arResOst = [];
$fileDataOst = "/var/www/bitrix/data/www/logs/ms/tempPurchase/cache/msk/ost/".$dateConst.".txt";

if (file_exists($fileDataOst)) {

	$arTmpOst = file_get_contents($fileDataOst);
	$arOst = json_decode($arTmpOst,true);

	foreach ($arOst as $key => $value) {
	       $arResOst[$key] = $value;
	}


} else {


	$store = '&store=https://api.moysklad.ru/api/remap/1.2/entity/store/83c00532-0f74-11ee-0a80-143a0014a102;store=https://api.moysklad.ru/api/remap/1.2/entity/store/2bcc228b-173a-11ee-0a80-0fdd000ddd83';


	$resStock = $ms->customRequest("https://api.moysklad.ru/api/remap/1.2/report/stock/all?filter=moment={$dateConst}+23:59:59{$store}");

	if (count($resStock['rows']) > 0) {
		foreach ($resStock['rows'] as $k => $v) {
			if ($v['name'] != 'Коробки' or $v['name'] != 'Коробка') {
				$arStockDays[$v['article']] = $v['stock'];
			}
		}
	}

	$strOst = json_encode($arStockDays);
	file_put_contents($fileDataOst, $strOst);

	foreach ($arStockDays as $key => $value) {
				 $arResOst[$key] = $value;
	}

}


$arResult["ITEMS"] = [];
foreach ($arResult["LOGIN"] as $login) {
  if ($login == 'msk') {
    $fileDataDif = "/var/www/bitrix/data/www/logs/ms/tempPurchase/cache/msk/dif/".$_POST["date_from"]."%".$_POST["date_to"].".txt";
    $nameSklad = 'Хронос';
  } else if ($login == 's1'){
    $nameSklad = 'TempusInt';
  }
  $fileData = "/var/www/bitrix/data/www/logs/ms/tempPurchase/cache/".$login."/purchase/".$_POST["date_from"]."%".$_POST["date_to"].".txt";


  $arData = file($fileData);
  if(is_array($arData) && count($arData) > 0){
  	foreach($arData as $key => $str){
  		if($key == 0) {
  			$date = explode(";", $str);
  			//prent($date[1]);
  			//prent($arResult["DATE_TO"]);//$date[0] != $arResult["DATE_FROM"] ||
  			if(trim($date[1]) != $arResult["DATE_TO"] || trim($date[0]) != $arResult["DATE_FROM"]){
  				$arResult["ERROR"][] = "Запрашиваемые даты и даты в кеше не совпадают. Нажмите 'Загрузить'. Даты кеша {$date[0]} - {$date[1]}";
  			}
  			continue;
  		}
  		$d = unserialize(base64_decode($str));
  		if(is_array($d)){
        foreach ($d as $key => $v) {
          if (isset($arResult["ITEMS"][$v['assortment']['name']])) {
						if($login == 's1') {
							$arResult["ITEMS"][$v['assortment']['name']]['sellQuantity'] = intval($arResult["ITEMS"][$v['assortment']['name']]['sellQuantity']) +intval($v['sellQuantity']);
						}

             $arResult["ITEMS"][$v['assortment']['name']]['sklad'][$login]['quant'] = $v['sellQuantity'];
          } else {
						if($login == 's1') {
							$arResult["ITEMS"][$v['assortment']['name']] = $v;
						}

             $arResult["ITEMS"][$v['assortment']['name']]['sklad'][$login]['quant'] = $v['sellQuantity'];

          }
        }
  		}


  	}
  }
}
$arDataDif = file_get_contents($fileDataDif);
$arResDif = json_decode($arDataDif,true);
//
// foreach ( $arResult["ITEMS"] as $key => &$item) {
//     if (isset($arResDif[$item['assortment']['name']])) {
//       if (isset($item['sklad']['msk'])) {
//         $item['sklad']['msk']['quant'] = intval($item['sklad']['msk']['quant']) - intval($arResDif[$item['assortment']['name']]);
//       }
//
//
//
//
//       $item['sellQuantity'] = intval($item['sellQuantity']) - intval($arResDif[$item['assortment']['name']]);
//     }
// }



//}
// else {
//   $login = $arResult["LOGIN"][0];
//   $fileData = "/var/www/bitrix/data/www/logs/ms/tempPurchase/cache/".$login."/purchase/".$_POST["date_from"]."%".$_POST["date_to"].".txt";
//   $fileDataOst = "/var/www/bitrix/data/www/logs/ms/tempPurchase/cache/".$login."/ost/".$_POST["date_from"]."%".$_POST["date_to"].".txt";
//   $arData = file($fileData);
//   $arResult["ITEMS"] = [];
//
//   if(is_array($arData) && count($arData) > 0){
//   	foreach($arData as $key => $str){
//   		if($key == 0) {
//   			$date = explode(";", $str);
//   			//prent($date[1]);
//   			//prent($arResult["DATE_TO"]);//$date[0] != $arResult["DATE_FROM"] ||
//   			if(trim($date[1]) != $arResult["DATE_TO"] || trim($date[0]) != $arResult["DATE_FROM"]){
//   				$arResult["ERROR"][] = "Запрашиваемые даты и даты в кеше не совпадают. Нажмите 'Загрузить'. Даты кеша {$date[0]} - {$date[1]}";
//   			}
//   			continue;
//   		}
//   		$d = unserialize(base64_decode($str));
//   		if(is_array($d)){
//   			//array_push($arResult["ITEMS"], $d);
//   			$arResult["ITEMS"] = array_merge($arResult["ITEMS"], $d);
//   		}
//
//   	}
//   }
// }
//$arParams["CACHE_TIME"] = 3600*24*30;

//$arParams["CACHE_PATH"] = "/".SITE_ID."/adm/order.list";

//$obCache = Cache::createInstance();

//$arCache = array($arResult["DATE_FROM"],$arResult["DATE_TO"]);
//$arParams["CACHE_ID"] = md5(serialize($arCache));


//prent($arResult);
//prent($arParams);
// && $obCache->initCache($arParams["CACHE_TIME"], $arParams["CACHE_ID"], $arParams["CACHE_PATH"])

//prent($arResult["ITEMS"]);

?>
<?if(!$arResult["ERROR"] && count($arResult["ITEMS"]) > 0):?>
	<?
	//$vars = $obCache->getVars();

	$objPricelist = new CPanelPricelist;
	$objSupplier = new CPanelSupplier;

	$arResult["PRICE_DEVIATION"] = CProSet::getOption("PRICE_DEVIATION_FOREIGN");

	$objCurrency = new CPanelCurrency;
	if($arResult["CURRENCY"] != "RUB"){
		$currency = $objCurrency->getDetail($arResult["CURRENCY"]);//курс валюты
		//prent($currency);
		$arResult["RATE"] = $currency["rate"];
	}else{
		$arResult["RATE"] = 1;
	}

	$arResult["SUPPLIER_LIST"] = $objSupplier->getList();
	//print_r($arResult["SUPPLIER_LIST"]);
	foreach($arResult["SUPPLIER_LIST"] as $keysup => &$arSup){
		// if(is_array($arResult["SUPPLIER"]) && !in_array($keysup, $arResult["SUPPLIER"])) {
		// 	unset($arResult["SUPPLIER_LIST"][$keysup]);
		// } else {
			$arResult["SUPPLIER_NAME"][$arSup["id"]] = $arSup["name"];
			$arResult["SUPPLIER_SORT"][$arSup["id"]] = $arSup["sort"];
			$settings = json_decode($arSup["settings"], true);
			foreach($settings["brand"] as $k => $v)
				$arResult["SUPP_BRAND_PRIORITY"][$arSup["id"]][$v["id"]] = $v["priority"];
		// }
	}
	// print_r($arResult["SUPPLIER_LIST"]);
/*Период - месяц
Прогноз - 60
Минимум - 5

За последний месяц мы продали 50шт модели Casio GA-2100-1A1
Продаж в день - (50шт/30 дней) = 1,66
план закупки - (60 * 1,66) = 100
Под фильтрацию по параметру "минимум" не попадаем.*/
//prent($vars);


  $arData = array();
  foreach($arResult["ITEMS"] as $key => &$arItem){
		$buyDay = $arItem["sellQuantity"] / $arResult["DATE_DIFF"];

		//print_r($arItem);
		$planPurchase = $arResult["PROGNOSIS"] * $buyDay;
    if (isset($arItem['sklad']['msk'])) {
			$arItem["Q_SELL_MSK"] = 'N/A';
    //   $arItem["Q_SELL_MSK"] = $arItem['sklad']['msk']['quant'];
    // } else {
    //   $arItem["Q_SELL_MSK"] = 'N/A';
    }
    if (isset($arItem['sklad']['s1'])) {
      $arItem["Q_SELL_S1"] = $arItem['sklad']['s1']['quant'];
    } else {
      $arItem["Q_SELL_S1"] = 'N/A';
    }
		$arItem["BUY_DAY"] = round($buyDay, 2);
		if($planPurchase > $arResult["MIN_PLAN"]){

			if($arItem["assortment"]["article"]){
				$arItem["planPurchase"] = $planPurchase;
				$arData[$arItem["assortment"]["article"]] = $arItem;
				$arModel[$arItem["assortment"]["code"]] = $arItem["assortment"]["article"];
				$arXML_ID[] = $arItem["assortment"]["code"];

			}
		}
	}
	unset($arItem);
	//print_r($arResult["ITEMS"]);



	if(is_array($arData) && count($arData) > 0){
		$psFilter = [];


		$tmp = $objSupplier->getList(["opt_supplier" => "Y"]);
		foreach($tmp as $arItem){
			$exCludeOpt[] = $arItem["id"];
			if(in_array($arItem["id"], $arResult["SUPPLIER"])) {
				$psFilter["supplier_id"][] = $arItem["id"];
			}
		}
		$psFilter["model"] = $arModel;
	}

	if($_POST["brand"] && $arXML_ID){
		$arFilter = Array(
			"IBLOCK_ID"	=> 16,
			"PROPERTY_BRAND" => $_POST["brand"],
			"XML_ID" => $arXML_ID,
		);

		$rs = CIBlockElement::GetList(array(), $arFilter, false, false, array("ID","XML_ID"));
		while($ar = $rs->GetNext()){
			$arActive[$ar["XML_ID"]] = $ar["XML_ID"];
		}

		//prent($arActive);prent($arModel);
		foreach($arModel as $k => $v){
			if(!$arActive[$k]) {
				unset($arModel[$k]);
			}
		}

	//	$psFilter["brand_id"] = $_POST["brand"];
	}


	$tmp = $objPricelist->getPriceByFilter($psFilter, false, false, "price desc");
	//prent($tmp);
  	//prent($arModel);
	// print_r('<br><br><b>===Временный лог===</b><br>');
	foreach($tmp as $k => $v){
		$price[$v["model"]] = $v;

		if(isset($arResult["SUPP_BRAND_PRIORITY"][$v["supplier_id"]][$v["brand_id"]])){
			$v["priority"] = $arResult["SUPP_BRAND_PRIORITY"][$v["supplier_id"]][$v["brand_id"]];
		}else
			$v["priority"] = 10;

		$priceAll[$v["model"]][] = $v;
		$arMinPrice[$v["model"]] = $v;

	}



	//prent($priceAll);
	/*
	3) Необходимо добавить столбец, который будет показывать насколько цена иностранного поставщика выгоднее
	локального.
	Цену локального поставщика берем исходя из условий
	3.1) активного для s1
	3.2) не учитывая Склад Москва, Склад Минск и Склад Польша
	3.3) не учитывая резервы

	Например LRW-200H-7B у самого дешевого локального поставщика стоит 1415 https://disk.yandex.ru/i/2r97X6iO7Mrk1g , а у иностранного 972 https://disk.yandex.ru/i/2r97X6iO7Mrk1g .
	Получается в последнем столбце должно быть ((1415-972)/972)*100 Получаем в %, округляем до целых = 45%

	При клике на процент необходимо выводить подсказку с тремя самыми дешевыми поставщиками из пункта 3 в формате Название - Цена - Количество
	https://disk.yandex.ru/i/kd_Tmro_iBYkYg

	Важно!
	1) Цену иностранного поставщика берем из этой же таблицы, а не из прайс-листа.
	2) Цена локального поставщика должна конвентироваться в валюту отчета
	*/

	$exNoActive = $objSupplier->getList(["opt_supplier" => "N","active_ru" => "N"]);
	foreach ($exNoActive as $kkk => $vvv) {
		$exCludeOpt[] = $vvv["id"];
	}
	$vsFilter = [];
	$vsFilter["!supplier_id"] = $exCludeOpt;
	array_push($vsFilter["!supplier_id"], '47', '44', '71', '82', '103','126','128','129','116',);
	$vsFilter["model"] = $psFilter["model"];
	//$vsFilter["website"] = "s1";
	$priceLocal = [];
	$tmp = $objPricelist->getPriceByFilter($vsFilter, false, false);
	foreach($tmp as $k => $v){
		$priceLocal[$v["model"]][] = $v;
	}

	$vsFilter = [];
	$vsFilter["supplier_id"] = 103;
	$vsFilter["model"] = $psFilter["model"];
	//$vsFilter["website"] = "s1";

	$tmp = $objPricelist->getPriceByFilter($vsFilter, "model", array("SUM(count) as count, model as model"));
	foreach($tmp as $k => $v){
		$sumStock[$v["model"]] = $v["count"];
	}
	// var_dump($sumStock);
	//prent($vsFilter);
	//prent($sumStock);
	prent("PRICE_DEVIATION - " . $arResult["PRICE_DEVIATION"]);
	//prent($priceAll);

	//$arModel = array('LRW-200H-7B');
	$mspArray = array();
	foreach($arModel as $article){
		$el = false;
		if($priceAll[$article]){
			$arPrice = sort_nested_arrays($priceAll[$article], array("priority" => "asc", "price" => "asc"));
			$arPriceMin = sort_nested_arrays($priceAll[$article], array("price" => "asc", "priority" => "asc"));

			$mspArray[$article] = $arPriceMin;
			//$minPrice = $arMinPrice[$article]["price"];
			//$minPriority = $arMinPrice[$article]["priority"];
			$minPrice = $arPriceMin[0]["price"];
			$minPriority = $arPriceMin[0]["priority"];
			$minID = $arPriceMin[0]["id"];
			$el = $arPriceMin[0];

			// print_r($arPrice);
			$flgBreak = false;
			foreach($arPrice as $k => $v){

				if($v["id"] == $minID || !$minPrice) continue;

				$diff = $v["price"] / $minPrice * 100 - 100;

				if($minPriority > $v["priority"]){

					if($diff <= $arResult["PRICE_DEVIATION"]){
						$el = $v;
						$mspArray[$article][] = $v;
						$flgBreak = true;
					}


				}elseif($diff <= $arResult["PRICE_DEVIATION"]){
					if($flgBreak == true) break;
					//$el = $v;
				}
			}
		}

	}


	$needArray = array();

	foreach($arModel as $article){
		if (isset($arResOst[$article])) {
			$needArray[$article]["Q_TODAY"] = $arResOst[$article];
		} else {
			$needArray[$article]["Q_TODAY"] = 0;
		}
		$needArray[$article]['TEXT_PRE'] = '';
		if ($arDataPre[$article]) {
			$qtd = $needArray[$article]["Q_TODAY"];
			$last_date = $dateConst;
			foreach ($arDataPre[$article] as $dateM => $quant) {
				$dif = floor((strtotime($dateM) - strtotime($last_date)) / (60 * 60 * 24));
				$qtd = $qtd - ($arData[$article]["BUY_DAY"] * $dif);
				if ($qtd < 0) {
					$qtd = 0;
				}
				$qtd = $qtd + $quant;
				$last_date = $dateM;
			}

			$dif = floor((strtotime($arResult['DATE_PRE']) - strtotime($last_date)) / (60 * 60 * 24));
			$qtd = $qtd - ($arData[$article]["BUY_DAY"] * $dif);
			if ($qtd < 0) {
				$qtd = 0;
			}

			$needArray[$article]["Q_PRE"] = round($qtd);
		} else {
			$needArray[$article]["Q_PRE"] = $needArray[$article]["Q_TODAY"] - ($arData[$article]["BUY_DAY"] * $arResult["DIFF_PRE"]);
			$needArray[$article]["Q_PRE"] = round($needArray[$article]["Q_PRE"]);
		}
		if ($needArray[$article]["Q_PRE"] < 0) {
			$needArray[$article]["Q_PRE"] = 0;
		}

		$needArray[$article]["planPurchase"] = round($arData[$article]["planPurchase"], 0);
		$needArray[$article]["ostPlan"] = round($arData[$article]["planPurchase"], 0);
		$needArray[$article]["BUY_DAY"] = $arData[$article]["BUY_DAY"];
		$needArray[$article]["Q_SELL_MSK"] = $arData[$article]["Q_SELL_MSK"];
		$needArray[$article]["Q_SELL_S1"] = $arData[$article]["Q_SELL_S1"];
		$needArray[$article]["SKLAD_DAY"] = $arData[$article]["SKLAD_DAY"];


		//$arResult["PRICE_GROUP"][$el["supplier_id"]][] = $el;

	}

	foreach ($needArray as $model => &$need) {
	  if (isset($mspArray[$model])) {
	    foreach ($mspArray[$model] as $suppModel) {
			if (intval($need['ostPlan']) != 0) {
	      $elSort["model"] = $model;
				$elSort["Q_PRE"] = $need["Q_PRE"];
				$elSort["Q_TODAY"] = $need["Q_TODAY"];
	      $elSort["price"] = round($suppModel["price"] / $arResult["RATE"], 2);

	      $elSort["planPurchase"] = $need["planPurchase"];
				$elSort["dQUANT"] = $suppModel['count'];

	      if (($need["ostPlan"] - $suppModel['count']) >= 0) {
	        $need["ostPlan"] = $need["ostPlan"] - $suppModel['count'];
	        $elSort['QUANT'] = $suppModel['count'];
	      } else if (($need["ostPlan"] - $suppModel['count']) < 0) {
	        $elSort['QUANT'] = $need["ostPlan"];
	        $need["ostPlan"] = 0;
	      }
	      $elSort["priceInvoice"] = $elSort["price"];
				$elSort["priceSumInvoice"] = round($elSort["price"] * $elSort['QUANT'], 2);
	      $elSort["priceMargin"] = $elSort["price"] + $elSort["price"] * $arResult["MARGIN"] / 100;
	      $elSort["priceMargin"] = round($elSort["priceMargin"], 2);
	      $elSort["priceSumMargin"] = round($elSort["priceMargin"] * $elSort['QUANT'], 2);
	      $elSort["BUY_DAY"] = $need["BUY_DAY"];
	      $elSort["Q_SELL_MSK"] = $need["Q_SELL_MSK"];
	      $elSort["Q_SELL_S1"] = $need["Q_SELL_S1"];
	      $elSort["SKLAD_DAY"] = $need["SKLAD_DAY"];

				if($priceLocal[$article]){
					$pLocal = sort_nested_arrays($priceLocal[$article], array('price' => 'asc'));

					$elSort["PRICE_DIFF"] = (($pLocal[0]["price"]/$arResult["RATE"] - $elSort["priceMargin"]) / $elSort["priceMargin"]) * 100;

					$elSort["PRICE_DIFF"] = round($elSort["PRICE_DIFF"], 2);

					$elSort["PRICE_LOCAL"] = $pLocal;

				}
				if($elSort["PRICE_DIFF"] && $arResult["MIN_DIFFERENCE"] < $elSort["PRICE_DIFF"]) {
					$arResult["PRICE_GROUP"][$suppModel["supplier_id"]][$model] = $elSort;
				}

	      unset($elSort);
			}
	    }
	  }
	}
	//print_r($needArray);

	?>
	<?
	foreach($arResult["PRICE_GROUP"] as $key => $ar):?>
		<?
		if(is_array($arResult["SUPPLIER"]) && !in_array($key, $arResult["SUPPLIER"])) continue;
		$arItem = sort_nested_arrays($ar, array('planPurchase' => 'desc'));
		$sumPlan = $sumPrice = 0;
		$sumHeaderPriceFix = 0;
    unset($sumHeaderCount);
    unset($sumHeaderPrice);
		unset($sumHeaderInvoice);

    foreach($arItem as $article => $arPrice) {
     $cntPurchase = $arPrice["QUANT"];
     $sumHeaderCount += $arPrice["QUANT"];
     $sumHeaderPrice += $arPrice["priceMargin"] * $arPrice["QUANT"];
		 $cntPurchaseFix = $arPrice["QUANT"];
		 if($arPrice["multiplicity"] > 1){
			 $cntPurchaseFix = round($cntPurchaseFix / $arPrice["multiplicity"]) * $arPrice["multiplicity"];
		 }
		 $sumHeaderPriceFix += $cntPurchaseFix *  $arPrice["priceMargin"];
		 $sumHeaderInvoice += $cntPurchaseFix *  $arPrice["priceInvoice"];
    }
		?>
		<div class="col-sm-12" style="background-color: rgba(40, 96, 144, 0.1); border-radius: 20px;margin-top: 15px;">
        <div class="row header_table">
            <div class="title_header_table">
              <span><?=$arResult["SUPPLIER_NAME"][$key]?></span>
                (Общие кол-во: <?=number_format($sumHeaderCount, 0, '.', ' ');?>, общая сумма: <?=number_format($sumHeaderPriceFix, 2, '.', ' ')?>, стоимость инвойса: <?=number_format($sumHeaderInvoice, 2, '.', ' ')?>)
            </div>
            <div class="arrow-4">
                <span class="arrow-4-left"></span>
                <span class="arrow-4-right"></span>
            </div>
						<!-- <button class="btn btn-primary export-table-btn" value="<?=$arResult["SUPPLIER_NAME"][$key]?>" style="margin: 0px 20px 0px auto">Экспорт</button> -->
        </div>
			<table class="table" id="<?=$arResult["SUPPLIER_NAME"][$key]?>">
				<thead>
					<tr>
						<th style="width: 20%">Артикул<br><span style="font-weight:400;font-size: 12px;">(Продаж в день, ШТ / Кол-во продаж, ШТ / Потребность, ШТ)</span></th>
						<th style="width: 20%">Доступное кол-во /<br> Закупаемое кол-во </th>
						<th style="width: 10%">Цена</th>
						<th style="width: 10%">Стоимость</th>
						<th style="width: 10%">Цена (инвойса)</th>
						<th style="width: 10%">Стоимость (инвойса)</th>
						<th style="width: 30%">Дельта, %</th>
						<!-- <th style="width: 5%">Текущий остаток</th>
						<th style="width: 5%">Остаток на дату</th> -->
					</tr>
				</thead>
				<tbody>
				<?foreach($arItem as $article => $arPrice):?>

					<?
          file_put_contents('/var/www/bitrix/data/www/ext_www/tempusshop.ru/admin/ajax/purchase_opt/????.txt', print_r($arPrice["PRICE_LOCAL"], true) . "\r\n");
					$sumPlan += $arPrice["QUANT"];

					$skip = false;
					$cntPurchase = $arPrice["QUANT"];
					if($arPrice["multiplicity"] > 1){
						$cntPurchase = round($cntPurchase / $arPrice["multiplicity"]) * $arPrice["multiplicity"];
						if($cntPurchase == 0)
							$skip = true;

						$textPurcase = "<span class='s-tooltip' data-toggle='tooltip' data-placement='top' style='color: green;' title='".round($arPrice["planPurchase"], 0).", кр. - {$arPrice["multiplicity"]}'>{$cntPurchase}</span>";
					}else{
						$textPurcase = $cntPurchase;
					}
					if($skip === true) continue;
					$sumPrice += $arPrice["priceMargin"] * $cntPurchase;
					$sumInvoice += $arPrice["priceInvoice"] * $cntPurchase;
					?>
					<tr class="">
						<td><b><?=$arPrice["model"]?></b><br><span style="font-weight:400;font-size: 12px;">(<?=$arPrice["BUY_DAY"]?> / <?=$arPrice["Q_SELL_S1"]?> / <?=$needArray[$arPrice["model"]]['planPurchase']?>)</span></td>
						<td><?=$arPrice["dQUANT"]?> / <?=$arPrice["QUANT"]?></td>
						<td><?=$arPrice["priceMargin"]?></td>
						<td><?=$arPrice["priceSumMargin"]?></td>
						<td><?=$arPrice["priceInvoice"]?></td>
						<td><?=$arPrice["priceSumInvoice"]?> </td>
						<td>
						<?if($arPrice["PRICE_DIFF"]):?>
						<?=$arPrice["PRICE_DIFF"]?> %
						<?foreach($arPrice["PRICE_LOCAL"] as $price):?>
            <?//print_r($arResult["SUPPLIER_NAME"]);?>
						<p style="font-size: 10px;margin: 0 0 0 0;<?if($price["active"] == "N"):?>color:grey;<?endif?>"><?=$arResult["SUPPLIER_NAME"][$price["supplier_id"]];?> - <?=print_r(round($price["price"] / $arResult["RATE"], 2));?> - <?=$price["count"];?></p>
						<?endforeach?>
						<?endif?>
						</td>
						<!-- <td><?=$arPrice["Q_TODAY"]?></td>
						<td><?=$arPrice["Q_PRE"]?></td> -->
					</tr>
				<?endforeach?>
					<tr class="">
						<td></td>
						<td><?=number_format($sumPlan, 0, '.', ' ');?></td>
						<td></td>
						<td><?=number_format($sumPrice, 2, '.', ' ')?></td>
            <td></td>
						<td><?=number_format($sumInvoice, 2, '.', ' ')?></td>
						<td></td>
						<td></td>
            <td></td>
						<td></td>
					</tr>
				</tbody>
			</table>
		</div>
	<?endforeach?>
	</div>

	<div class="row">
		<div class="col-sm-12" style="background-color: rgba(234, 231, 195, 0.47); border-radius: 0px;margin-top: 15px;">
        <div class="row header_table">
            <div class="title_header_table">
              <span>СВОДНАЯ ТАБЛИЦА</span>
            </div>
            <div class="arrow-4">
                <span class="arrow-4-left"></span>
                <span class="arrow-4-right"></span>
            </div>
						<!-- <button class="btn btn-primary export-table-btn" value="<?=$arResult["SUPPLIER_NAME"][$key]?>" style="margin: 0px 20px 0px auto">Экспорт</button> -->
        </div>
			<table class="table">
				<thead>
					<tr>
						<th style="width: 30%">Артикул</th>
						<th style="width: 10%">Продаж в день, ШТ</th>
						<th style="width: 10%">Кол-во продаж, ШТ</th>
						<th style="width: 10%">Потребность, ШТ</th>
						<th style="width: 20%">Закрытая потребность</th>
						<th style="width: 20%">Оставшаяся потребность</th>
					</tr>
				</thead>
				<?foreach($needArray as $art => $body):?>
					<tbody>
						<tr class="">
							<td><b><?=$art?></b></td>
							<td><?=$body['BUY_DAY']?></td>
							<td><?=$body['Q_SELL_S1']?></td>
							<td><?=$body['planPurchase']?></td>
							<td><?php echo ($body['planPurchase'] - $body['ostPlan']); ?></td>
							<td>
								<?if (intval($body['ostPlan']) == 0) {?>
									<span style="color: green;"><?=$body['ostPlan']?></span>
								<?} else {?>
									<span style="color: red;"><?=$body['ostPlan']?></span>
								<?}?>
							</td>
						</tr>
					</tbody>
				<?endforeach?>
			</table>
		</div>
	</div>
	<?

	?>
<?elseif($arResult["ERROR"]):?>
	<?foreach($arResult["ERROR"] as $error):?>
		<p style='color:red;'><?=$error?></p>
	<?endforeach?>
	</div>
<?else:?>
	<h2 class="color"><span>Не удалось получить список моделей</span></h2>
	<p>Пожалуйста, выберите период и нажмите "Загрузить из МС". После этого повторите попытку</p>
	</div>
<?endif?>

<script>
$(document).ready(function(){
    $('[data-toggle="tooltip"]').tooltip();
});
</script>
<?
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_after.php');
