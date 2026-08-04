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
<?
//if(!$arResult["ACCESS"]) return;
//$fileData = "/home/bitrix/logs/ms/tempPurchase/" . $USER->getID() . ".txt";


$start = debug_microtime_float();

$arResult = array();

$arResult["PROGNOSIS"] = intval($_POST["prognosis"]);
$arResult["MARGIN"] = intval($_POST["margin"]);
$arResult["MIN_PLAN"] = intval($_POST["min_plan"]);
$arResult["CURRENCY"] = $_POST["currency"];
$arResult["BRAND"] = $_POST["brand"];

$arResult["DATE_FROM"] = $_POST["date_from"];
$arResult["DATE_TO"] = $_POST["date_to"];


$arResult["MIN_DIFFERENCE"] = intval($_POST["min_difference"]);
$arResult["SUPPLIER"] = $_POST["supplier"];
//edit
if (count($_POST["login"]) > 1) {
  foreach ($_POST["login"] as $key => $value) {
    $arResult["LOGIN"][$key] = 1;
  }
} else {
  $arResult["LOGIN"][$_POST["login"][0]] = 1;
}


$arResult["KOEF"] = $_POST["pon_koef"];
if (isset($_POST['new_formula'])) {
  $arResult["NEW_FORMULA"] = 'Y';
} else {
  $arResult["NEW_FORMULA"] = 'N';
}
$login = $_POST["login"][0];

$fileData = "/home/bitrix/logs/ms/tempPurchase/cache/".$login."/purchase/".$_POST["date_from"]."%".$_POST["date_to"].".txt";
// сохраняем настройки
$arSettings = [
	"DATE_FROM" => $arResult["DATE_FROM"],
	"DATE_TO" => $arResult["DATE_TO"],
	"PROGNOSIS" => $arResult["PROGNOSIS"],
	"MARGIN" => $arResult["MARGIN"],
	"MIN_PLAN" => $arResult["MIN_PLAN"],
	"BRAND" => $arResult["BRAND"],
	"CURRENCY" => $arResult["CURRENCY"],
	"MIN_DIFFERENCE" => $arResult["MIN_DIFFERENCE"],
	"SUPPLIER" => $arResult["SUPPLIER"],
  "LOGIN" => $arResult["LOGIN"],
  "KOEF" => $arResult["KOEF"],
  "NEW_FORMULA" => $arResult["NEW_FORMULA"]
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


//$arParams["CACHE_TIME"] = 3600*24*30;

//$arParams["CACHE_PATH"] = "/".SITE_ID."/adm/order.list";

//$obCache = Cache::createInstance();

//$arCache = array($arResult["DATE_FROM"],$arResult["DATE_TO"]);
//$arParams["CACHE_ID"] = md5(serialize($arCache));

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
//prent($arResult);
//prent($arParams);
// && $obCache->initCache($arParams["CACHE_TIME"], $arParams["CACHE_ID"], $arParams["CACHE_PATH"])

$arData = file($fileData);
$arResult["ITEMS"] = [];
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
			//array_push($arResult["ITEMS"], $d);
			$arResult["ITEMS"] = array_merge($arResult["ITEMS"], $d);
		}

	}
}
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
	foreach($arResult["SUPPLIER_LIST"] as $arSup){
		$arResult["SUPPLIER_NAME"][$arSup["id"]] = $arSup["name"];
		$arResult["SUPPLIER_SORT"][$arSup["id"]] = $arSup["sort"];
		$settings = json_decode($arSup["settings"], true);
		foreach($settings["brand"] as $k => $v)
			$arResult["SUPP_BRAND_PRIORITY"][$arSup["id"]][$v["id"]] = $v["priority"];
	}
/*Период - месяц
Прогноз - 60
Минимум - 5

За последний месяц мы продали 50шт модели Casio GA-2100-1A1
Продаж в день - (50шт/30 дней) = 1,66
план закупки - (60 * 1,66) = 100
Под фильтрацию по параметру "минимум" не попадаем.*/
//prent($vars);
	foreach($arResult["ITEMS"] as $key => &$arItem){
		$buyDay = $arItem["sellQuantity"] / $arResult["DATE_DIFF"];
		$planPurchase = $arResult["PROGNOSIS"] * $buyDay;
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


	if(is_array($arData) && count($arData) > 0){
		$psFilter = [];


		$tmp = $objSupplier->getList(["opt_supplier" => "Y"]);
		foreach($tmp as $arItem){
			$psFilter["supplier_id"][] = $arItem["id"];
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
	$vsFilter = [];
	$vsFilter["!supplier_id"] = $psFilter["supplier_id"];
	array_push($vsFilter["!supplier_id"], '47', '44', '71', '82', '103');
	$vsFilter["model"] = $psFilter["model"];
	//$vsFilter["website"] = "s1";

	$priceLocal = [];
	$tmp = $objPricelist->getPriceByFilter($vsFilter, false, false);
	foreach($tmp as $k => $v){
		$priceLocal[$v["model"]][] = $v;
	}
	//prent($arModel);
	$vsFilter = [];
	$vsFilter["supplier_id"] = 103;
	$vsFilter["model"] = $psFilter["model"];
	//$vsFilter["website"] = "s1";

	$tmp = $objPricelist->getPriceByFilter($vsFilter, "model", array("SUM(count) as count, model as model"));
	foreach($tmp as $k => $v){
		$sumStock[$v["model"]] = $v["count"];
	}
	//prent($vsFilter);
	//prent($sumStock);
	prent("PRICE_DEVIATION - " . $arResult["PRICE_DEVIATION"]);
	//prent($priceAll);
	foreach($arModel as $article){
		$el = false;
		if($priceAll[$article]){
			$arPrice = sort_nested_arrays($priceAll[$article], array("priority" => "asc", "price" => "asc"));
			$arPriceMin = sort_nested_arrays($priceAll[$article], array("price" => "asc", "priority" => "asc"));

			//$minPrice = $arMinPrice[$article]["price"];
			//$minPriority = $arMinPrice[$article]["priority"];
			$minPrice = $arPriceMin[0]["price"];
			$minPriority = $arPriceMin[0]["priority"];
			$minID = $arPriceMin[0]["id"];
			$el = $arPriceMin[0];

if($article == "EFR-552D-1A"){
	//prent($minPrice);
	//prent("minPrice - " . $minPrice);
	//prent("minPriority - " . $minPriority);
	//prent($el);
}
			$flgBreak = false;
			foreach($arPrice as $k => $v){
				if($v["id"] == $minID || !$minPrice) continue;
				$diff = $v["price"] / $minPrice * 100 - 100;

if($article == "EFR-552D-1A"){
	//prent($diff);prent($v);
}
				if($minPriority > $v["priority"]){

					if($diff <= $arResult["PRICE_DEVIATION"]){
						$el = $v;
						$flgBreak = true;
					}


				}elseif($diff <= $arResult["PRICE_DEVIATION"]){
					if($flgBreak == true) break;
					//$el = $v;
				}
			}
		}
if($article == "EFR-552D-1A"){
	//prent("Выбрали");
	//prent($el);
}
		//if($price[$article]){
		if($el){
			//$el = $price[$article];
			//if($_POST["brand"] && !in_array($el["brand_id"], $_POST["brand"])){
			//	continue;
			//}

			$el["price"] = round($el["price"] / $arResult["RATE"], 2);

			$el["planPurchase"] = $arData[$article]["planPurchase"];
			$el["priceMargin"] = $el["price"] + $el["price"] * $arResult["MARGIN"] / 100;
			$el["priceMargin"] = round($el["priceMargin"], 2);
			//$el["priceSumMargin"] = round($el["priceMargin"] * $el["planPurchase"], 2);
			$el["priceSumMargin"] = round($el["priceMargin"] * $el["planPurchase"], 2);
			$el["BUY_DAY"] = $arData[$article]["BUY_DAY"];

			if($priceLocal[$article]){
				$pLocal = sort_nested_arrays($priceLocal[$article], array('price' => 'asc'));
				//$pLocal = sort_nested_arrays($priceLocal[$article], array('active' => 'desc', 'price' => 'asc'));
				//prent($pLocal);
				$el["PRICE_DIFF"] = (($pLocal[0]["price"] - $el["priceMargin"]) / $el["priceMargin"]) * 100;
				$el["PRICE_DIFF"] = round($el["PRICE_DIFF"], 2);
				//prent($el["PRICE_DIFF"]);
				$el["PRICE_LOCAL"] = $pLocal;
				//prent($pLocal[0]["price"]);
				//prent($el["priceMargin"]);
			//prent($el["PRICE_DIFF2"]);
			}

			$arResult["PRICE_GROUP"][$el["supplier_id"]][] = $el;
		}else{
			$arResult["PRICE_NO_SUPP"][] = $article;
		}
	}
//prent($arResult["PRICE_GROUP"]);
	foreach($arResult["PRICE_GROUP"] as $key => $ar):?>
		<?
		if(is_array($arResult["SUPPLIER"]) && !in_array($key, $arResult["SUPPLIER"])) continue;
		$arItem = sort_nested_arrays($ar, array('planPurchase' => 'desc'));
		$sumPlan = $sumPrice = 0;
		?>
		<div class="col-sm-12">
			<table class="table">
				<thead>
					<tr>
						<th style="width: 25%"><?=$arResult["SUPPLIER_NAME"][$key]?></span></th>
						<th style="width: 15%">Продаж в день</th>
						<th style="width: 10%">На складе</th>
						<th style="width: 10%">Кол.</th>
						<th style="width: 10%">Цена</th>
						<th style="width: 30%"></th>
					</tr>
				</thead>
				<tbody>
				<?foreach($arItem as $article => $arPrice):?>
					<?
					if($arResult["MIN_DIFFERENCE"] > 0 && $arPrice["PRICE_DIFF"] && $arResult["MIN_DIFFERENCE"] > $arPrice["PRICE_DIFF"]) continue;
					$sumPlan += $arPrice["planPurchase"];
					$sumPrice += $arPrice["priceMargin"] * $arPrice["planPurchase"];

					$skip = false;
					$cntPurchase = round($arPrice["planPurchase"], 0);
					if($arPrice["multiplicity"] > 1){
						$cntPurchase = round($cntPurchase / $arPrice["multiplicity"]) * $arPrice["multiplicity"];
						if($cntPurchase == 0)
							$skip = true;

						$textPurcase = "<span class='s-tooltip' data-toggle='tooltip' data-placement='top' style='color: green;' title='".round($arPrice["planPurchase"], 0).", кр. - {$arPrice["multiplicity"]}'>{$cntPurchase}</span>";
					}else{
						$textPurcase = $cntPurchase;
					}
					if($skip === true) continue;
					?>
					<tr class="">
						<td><?=$arPrice["model"]?></td>
						<td><?=$arPrice["BUY_DAY"]?></td>
						<td><?=$sumStock[$arPrice["model"]]?></td>
						<td><?=$textPurcase?></td>
						<td><?=$arPrice["priceMargin"]?></td>
						<td>
						<?if($arPrice["PRICE_DIFF"]):?>
						<?=$arPrice["PRICE_DIFF"]?> %
						<?foreach($arPrice["PRICE_LOCAL"] as $price):?>
						<p style="font-size: 10px;margin: 0 0 0 0;<?if($price["active"] == "N"):?>color:grey;<?endif?>"><?=$arResult["SUPPLIER_NAME"][$price["supplier_id"]];?> - <?=$price["price"];?> - <?=$price["count"];?></p>
						<?endforeach?>
						<?endif?>
						</td>
					</tr>
				<?endforeach?>
					<tr class="">
						<td></td>
						<td></td>
						<td></td>
						<td><?=number_format($sumPlan, 0, '.', ' ');?></td>
						<td><?=number_format($sumPrice, 2, '.', ' ')?></td>
					</tr>
				</tbody>
			</table>
		</div>
	<?endforeach?>
	<?if(is_array($arResult["PRICE_NO_SUPP"]) && count($arResult["PRICE_NO_SUPP"]) > 0):?>
		<div class="col-sm-12">
			<table class="table">
				<thead>
					<tr>
						<th style="width: 100%">Нет у поставщиков</th>
					</tr>
				</thead>
				<?foreach($arResult["PRICE_NO_SUPP"] as $key => $article):?>
					<tbody>
						<tr class="">
							<td><?=$article?></td>
						</tr>
					</tbody>
				<?endforeach?>
			</table>
		</div>
	<?endif?>
	<?

	?>
<?elseif($arResult["ERROR"]):?>
	<?foreach($arResult["ERROR"] as $error):?>
		<p style='color:red;'><?=$error?></p>
	<?endforeach?>
<?else:?>
	<h2 class="color"><span>Не удалось получить список моделей</span></h2>
	<p>Пожалуйста, выберите период и нажмите "Загрузить из МС". После этого повторите попытку</p>
<?endif?>
</div>
<script>
$(document).ready(function(){
    $('[data-toggle="tooltip"]').tooltip();
});
</script>
<?
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_after.php');
