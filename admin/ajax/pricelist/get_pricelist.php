<?php 
$start = microtime(true);
require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');?>
<?
$arLog['1'] = microtime(true) - $start;
global $USER;
if(isset($_POST["search_text"]))
	$psFilter["search_text"] = trim($_POST["search_text"]);
if(strlen($psFilter["search_text"]) <= 3){
	die;
}
$arLog['2'] = microtime(true) - $start;
?>

<?
if(CModule::IncludeModule("panel.manager") && CModule::IncludeModule("iblock") && CModule::IncludeModule("catalog")){
	$arLog['3'] = microtime(true) - $start;
	$objPricelist = new CPanelPricelist;
	$objSupplier = new CPanelSupplier;

	$arGroups = $USER->GetUserGroupArray();
	$arLog['4'] = microtime(true) - $start;
	$showOpt = false;
	if($USER->isAdmin() || in_array(18, $arGroups)){
		$showOpt = true;
	}


	if($showOpt !== true){
		$arFilter = array("opt_supplier" => "N");
	}else{
		if ($_POST["showmode"] == 'OPT') {
			$tmp = $objSupplier->getList(array("opt_supplier" => "N"));
			foreach($tmp as $arItem){
				$psFilter["!supplier_id"][] = $arItem["id"];
			}
		}else if ($_POST["showmode"] == 'ROZ') {
			$tmp = $objSupplier->getList(array("opt_supplier" => "Y"));
			foreach($tmp as $arItem){
				$psFilter["!supplier_id"][] = $arItem["id"];
			}
		}
	}

	$arLog['5'] = microtime(true) - $start;
	$arSupllier = $objSupplier->getList($arFilter);
	$arSupName = array();

	$arLog['6'] = microtime(true) - $start;
	foreach($arSupllier as &$arItem){
		$arSupName[$arItem["id"]] = $arItem["name"];
		$arItem["settings"] = json_decode($arItem["settings"], true);
		$arItem["settings_pricelist"] = json_decode($arItem["settings_pricelist"], true);

		$arSuppOpt[$arItem["id"]] = $arItem["settings_pricelist"]["opt_price"];
	}

	unset($arItem);

	$arLog['7'] = microtime(true) - $start;
	if($showOpt !== true){
		$tmp = $objSupplier->getList(array("opt_supplier" => "Y"));
		foreach($tmp as $arItem){
			$psFilter["!supplier_id"][] = $arItem["id"];
		}
	}

	//prent($psFilter);
	if($_POST["view"] == "Y") $page_size = 2000; else $page_size = 20;

	$arLog['8'] = microtime(true) - $start;
	$view_opt = false;
	$optSettings = [];
	//если выбран оптовик
	if($_POST["opt_price"] > 0){
		$view_opt = true;

		$objCurrency = new CPanelCurrency;

		global $DB;
		$strSql = "SELECT * FROM ci_opt WHERE USER_ID = '".$DB->ForSql($_POST["opt_price"])."'";
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		if ($row = $results->Fetch()){
			$optSettings = json_decode($row["SETTINGS"], true);
			if($optSettings["CURRENCY"] != "RUB"){
				$arCurrency = $objCurrency->getDetail($optSettings["CURRENCY"]);
			}else{
				$arCurrency = array(
					"id" => "RUB",
					"amount" => 1,
					"rate" => 1
				);
			}
		}
		//prent($optSettings);
		foreach($arSupllier as $key => $arItem){
			$opt_price = json_decode((string)$arItem["settings_pricelist"], true)["opt_price"];
			//удаляем из прайслиста
			if($opt_price == "Y"){
				$ar[] = $arItem["id"];
			}

		}
		if(is_array($ar) && count($ar) > 0){
			$psFilter["supplier_id"] = $ar;
		}else{
			$psFilter["supplier_id"] = "-1";
		}
	}

	$arLog['9'] = microtime(true) - $start;
	$arPrice = $objPricelist->getPriceByFilter($psFilter);
	$arLog['10'] = microtime(true) - $start;
	//prent($arPrice);
	$count = count($arPrice);
	$arPrice = array_slice($arPrice, 0, $page_size);
	$arMin = array();
	
	foreach($arPrice as $key => &$arItem){
		if($view_opt === true){
			if($optSettings["PRICE_VAT"] && $optSettings["PRICE_VAT"] == "Y"){
				$col_price = "price_n";
			}else{
				$col_price = "price";
			}
			$arItem["price"] = $arItem[$col_price] + intval($arItem[$col_price]) * intval($optSettings["MARGIN"]) / 100;
			$arItem["price"] = $arItem["price"] / $arCurrency["rate"];
			$arItem["price"] = round($arItem["price"], 2);
		}
		$arItem["supplier_name"] = $arSupName[$arItem["supplier_id"]];
		$arItem["priority"] = $arSupllier[$arItem["supplier_id"]]['settings']['brand'][$arItem["brand_id"]]['priority'];
		if(!$arMin[$arItem["model"]] || $arItem["price"] < $arMin[$arItem["model"]]) $arMin[$arItem["model"]] = $arItem["price"];

		$arItem["opt"] = $arSuppOpt[$arItem["supplier_id"]];

	}
	unset($arItem);
	//prent($arPrice);

	$arLog['11'] = microtime(true) - $start;
	$arArticle = (is_array($arMin) ? array_keys($arMin) : 0);
	if (is_array($arArticle) && count($arArticle) > 0) {
		$strSql = "SELECT * FROM ci_reserved WHERE ARTICLE IN ('" . implode("','", $arArticle) . "')";
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		while ($row = $results->Fetch()){
			if($row["ARTICLE"]){
				$arReserved[$row["ARTICLE"]] = $row["RESERVED"];
				$arReservedSite[$row["ARTICLE"]]["s1"] = $row["RESERVED_s1"];
				$arReservedSite[$row["ARTICLE"]]["s2"] = $row["RESERVED_s2"];
				$arReservedSite[$row["ARTICLE"]]["s3"] = $row["RESERVED_s3"];
				//prent($row);
			}
		}

		$strSql = "SELECT * FROM ci_price_quarantine WHERE ARTICLE IN ('" . implode("','", $arArticle) . "')";
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		while ($row = $results->Fetch()){
			if($row["ARTICLE"]){
				$arQuarantine[$row["ARTICLE"]] = true;
			}
		}

		$service = PanelManager::getOrderReservedManager();
		$arReserve = $service->getReservedBySupplier($arArticle);
		//prent($arReserve); 
	}
	//prent($arQuarantine);

	$arLog['12'] = microtime(true) - $start;
	//prent($arCurrency["id"]);
	//prent($tmp);
	//prent($arPrice);

//	prent($all);
/*
	foreach($price as $key => &$arItem){
		if($arItem["model"]){
			$res = CIBlockElement::getList(array(), array('ACTIVE' => 'Y', 'IBLOCK_ID' => CProSet::IB_CATALOG, "PROPERTY_CML2_ARTICLE" => $arItem["model"]),false, false,array("ID", "CATALOG_GROUP_{$type_price}"));
			if ($row = $res->getNext()) {
				$arItem["b_price"] = $row["CATALOG_PRICE_{$type_price}"];
				$arItem["b_price_cur"] = $row["CATALOG_CURRENCY_{$type_price}"];
			}else
				unset($price[$key]);
		}else
			unset($price[$key]);
	}
*/
//prent($arPrice);
	//[supplier_id] => 45
	$arPrice = sort_nested_arrays($arPrice, array('model' => 'asc', 'price' => 'asc'));

	$arLog['13'] = microtime(true) - $start;
	foreach($arPrice as $key => &$arItem){
		if($key == 0) {$arItem["class"] = "btn-success"; continue;}

		//$tmp = $arItem["price"] * 0.05;
		$diff = $arItem["price"] / $arMin[$arItem["model"]] * 100 - 100;
		if($diff <= 3){
			$arItem["class"] = "btn-success";
		}elseif($diff <= 5){
			$arItem["class"] = "btn-warning";
		}elseif($diff <= 10){
			$arItem["class"] = "btn-danger";
		}else{
			$arItem["class"] = "btn-dark";
		}

		$arItem["diff_min"] = $diff;
	}
	unset($arItem);

	$arLog['14'] = microtime(true) - $start;
	?>
	<?if(is_array($arPrice) && count($arPrice) > 0):?>
		<div style="padding: 0px 15px 0 11px;">
			<table class="table" style="margin:0;">
			<thead>
				<tr>
					<th></th>
					<th>Артикул</th>
					<th>Поставщик</th>
					<th style="width: 120px;">Цена</th>
					<th style="width: 100px;">Цена(+НДС)</th>
					<th style="width: 100px;">Цена прайса</th>
					<th>Валюта прайса</th>
					<?if($arCurrency["id"] && $arCurrency["id"] != "RUB"):?><th>Валюта Опт</th><?endif?>
					<th>Количество</th>
					<th>Приоретет</th>
					<th>Зарезервировано</th>
					<th style="min-width:100px;">Резерв</th>
					<th>Дата обновления</th>
					<th></th>
				</tr>
			</thead>
			<tbody>
			<?foreach($arPrice as $key => $arItem):?>
			<?//print_r($arItem);?>
			<?
			$stockDays = $arItem['stock_days'] != NULL ? '<span style="color:red">('.$arItem['stock_days'].')</span>' : '';
			?>
				<tr id="tr-product-<?=$arItem["id"]?>">
					<td>
						<?if($arQuarantine[$arItem["model"]]):?>
							<span style="color:red">Q</span><br>
						<?endif?>
						<?if($arItem["active_ru"] == "Y" && $arItem["active_by"] == "Y" && $arItem["active_pl"] == "Y" && $arItem["active_ya"] == "Y" && $arItem["active_os"] == "Y" && 
						$arItem["active_wb"] == "Y" && $arItem["active_wbby"] == "Y"):?>
						ALL<br>
                        <?else:?>
                            <?if($arItem["active_ru"] == "Y"):?>RU<br><?endif?>
                            <?if($arItem["active_by"] == "Y"):?>BY<br><?endif?>
                            <?if($arItem["active_ya"] == "Y"):?>YA<br><?endif?>
                            <?if($arItem["active_os"] == "Y"):?>OS<br><?endif?>
                            <?if($arItem["active_wb"] == "Y"):?>WB<br><?endif?>
                            <?if($arItem["active_wbby"] == "Y"):?>WBBY<br><?endif?>
                        <?endif?>
						<?if($arItem["active_opt"] == "Y"):?>ОПТ<?endif?>
					</td>
					<td><?=$arItem["model"]?></td>
					<td><?=$arItem["supplier_name"]?></td>
					<td><span class="<?=$arItem["class"]?>" data-toggle="tooltip" title="<?=round($arItem["diff_min"], 2)?>" style="width: 10px;height: 10px;display: inline-block;border-radius: 50%;margin: 0 5px 0 0;"><?//=round($arItem["diff_min"], 2)?></span><?=$arItem["price"]?> <?=$stockDays?></td>
					<td><?=$arItem["price_n"]?></td>
					<td><?=$arItem["priceСurrency"]?></td>
					<td><?=$arItem["currency"]?></td>
					<?if($arCurrency["id"] && $arCurrency["id"] != "RUB"):?><td><?=$arCurrency["id"]?></td><?endif?>
					<td><?=$arItem["count"]?></td>
					<td><?=$arItem["priority"]?></td>
					<td>
					<span style="display: block;font-size: 11px;">RU - <?=($arReservedSite[$arItem["model"]]["s1"] ? $arReservedSite[$arItem["model"]]["s1"] : 0);?></span>
					<span style="display: block;font-size: 11px;">BY - <?=($arReservedSite[$arItem["model"]]["s2"] ? $arReservedSite[$arItem["model"]]["s2"] : 0);?></span>
					<span style="display: block;font-size: 11px;">ALL - <?=($arReserved[$arItem["model"]] ? $arReserved[$arItem["model"]] : 0);?></span>
					</td>
					<td>
						<?if($arReserve[$arItem["supplier_id"]][$arItem["model"]]):?>
							<?
							$reserve = sort_nested_arrays($arReserve[$arItem["supplier_id"]], ['SITE_ID' => 'asc', 'TRADING_PLATFORM_ID' => 'asc']);
							$sum = 0;
							$names = [
								's1' => 'RU',
								's2' => 'BY',
							];
							?>
							<?foreach($arReserve[$arItem["supplier_id"]][$arItem["model"]] as $item):?>
								<span style="display: block;font-size: 11px;"><?=($names[$item['SITE_ID']] ? $names[$item['SITE_ID']] : $item['SITE_ID'])?> (<?=$item['TRADING_PLATFORM_NAME']?>) - <?=$item['RESERVED']?></span>
								<?
								$sum += $item['RESERVED'];
								?>
							<?endforeach?>
						<?endif?>
						<?if($arReserve[0][$arItem["model"]]):?>
							<?
							$sumUnallocated = 0;
							?>
							<?foreach($arReserve[0][$arItem["model"]] as $item):?>
								<?
								$sumUnallocated += $item['RESERVED'];
								?>
							<?endforeach?>
							<?if($sumUnallocated):?>
								<span style="display: block;font-size: 11px;">Не распределено - <?=$sumUnallocated;?></span>
							<?endif?>
						<?endif?>
					</td>
					<td><?=$arItem["timestamp"]?></td>
					<td>
						<button class="btn btn-danger btn_form_clear btn-product-delete" data-id="<?=$arItem["id"]?>">Удалить</button>
						<button class="btn btn_form_clear btn-product-edit" data-toggle="modal" data-target="#modal_edit_item" data-id="<?=$arItem["id"]?>">Редактировать</button>
					</td>
				</tr>
			<?endforeach?>
			<?if($count > $page_size):?>
				<tr>
					<td colspan="6" class="warning">Показаны <?=$page_size?> из <?=$count?></td>
				</tr>
			<?endif?>
			</tbody>
		</table>
	<?endif?>
	<?
	$end = microtime(true) - $start;
	if ($end > 2) {
		$arLog['TIME'] = date('Y-m-d H:i:s');
		$arLog['REQUEST'] = $_REQUEST;
		file_put_contents('/var/www/bitrix_logs/debug/get_pricelist.txt', print_r($arLog, true) , 8);
	}
	prent($arLog);
	?>
<script>
// после загрузки страницы
$(function(){
    // инициализации подсказок для всех элементов на странице, имеющих атрибут data-toggle="tooltip"
    $('[data-toggle="tooltip"]').tooltip();
});
</script>
	<?
	//prent($price);

}else{
	?>
	<h2 class="color"><span>Не удалось получить список моделей(</span></h2>
	<p>Произошла непредвиденная ошибка. Пожалуйста, обновите страницу и попробуйте позже</p>
	<?
}
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_after.php');
