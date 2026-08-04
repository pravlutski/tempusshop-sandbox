<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');?>
<?if(!CModule::IncludeModule('panel.manager'))return;?>
<?if(!$_REQUEST["type"] || !in_array($_REQUEST["type"], array("csv", "xls", "xml"))) die("No type file");?>
<div class="modal-header">
	<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
	<h2 class="modal-title">Настройки выгрузки <?=addslashes($_REQUEST["type"])?> файла</h2>
</div>
<?
global $DB;

$strSql = "SELECT * FROM ci_opt_settings WHERE USER_ID = '".$USER->getID()."' AND TYPE='{$_REQUEST["type"]}'";
$results = $DB->Query($strSql, false, $err_mess.__LINE__);
if ($row = $results->Fetch()){
	$arSettings = json_decode($row["SETTINGS"], true);
}else{
	//по умолчанию
	$arSettings = array(
		"column-1" => "price",
		"column-2" => "vendor",
		"column-3" => "article",
		"column-4" => "day_delivery",
	);
}
//prent($arSettings);
$brand = new CPanelBrand;
$arResult["BRANDS"] = $brand->getList();
//prent($arResult["BRANDS"]);
?>
<div class="modal-body modal_big_padding" style="float: left;width: 100%;padding: 10px 0 10px 15px;">
<form action="#" method="POST" class="form-horizontal" id="form-price-settings" name="form-price-settings">
	<input type="hidden" name="type" value="<?=addslashes($_REQUEST["type"])?>">
	<div style="" class="col-lg-12 ">
		<?for($i = 1; $i <= 8; $i++):?>
		<div class="col-lg-3">
			<span style="margin: 0 0 3px 0;display: block;">Колонка <?=$i?></span>
			<select class="form-control" name="column-<?=$i?>">
				<option value="0" <?if(!isset($arSettings["column-{$i}"])):?>selected<?endif?>>Не выбрано</option>
				<option value="name" <?if(isset($arSettings["column-{$i}"]) && $arSettings["column-{$i}"] == "name"):?>selected<?endif?>>Название</option>
				<option value="xml_id" <?if(isset($arSettings["column-{$i}"]) && $arSettings["column-{$i}"] == "xml_id"):?>selected<?endif?>>Внешний код</option>
				<option value="vendor" <?if(isset($arSettings["column-{$i}"]) && $arSettings["column-{$i}"] == "vendor"):?>selected<?endif?>>Производитель</option>
				<option value="article" <?if(isset($arSettings["column-{$i}"]) && $arSettings["column-{$i}"] == "article"):?>selected<?endif?>>Артикул</option>
				<option value="section_1" <?if(isset($arSettings["column-{$i}"]) && $arSettings["column-{$i}"] == "section_1"):?>selected<?endif?>>Тип товара</option>
				<option value="price" <?if(isset($arSettings["column-{$i}"]) && $arSettings["column-{$i}"] == "price"):?>selected<?endif?>>Цена</option>
				<option value="price_tempus" <?if(isset($arSettings["column-{$i}"]) && $arSettings["column-{$i}"] == "price_tempus"):?>selected<?endif?>>Цена tempus</option>
				<option value="day_delivery" <?if(isset($arSettings["column-{$i}"]) && $arSettings["column-{$i}"] == "day_delivery"):?>selected<?endif?>>Дней доставки</option>
				<option value="gender" <?if(isset($arSettings["column-{$i}"]) && $arSettings["column-{$i}"] == "gender"):?>selected<?endif?>>Гендер</option>
			</select>
		</div>
		<?endfor?>
	</div>
	
	<div style="" class="col-lg-12">
		<div class="col-lg-12" style="margin-top: 18px;">
		<select class="form-control" name="brands[]" multiple>
			<?foreach($arResult["BRANDS"] as $key => $arItem):?>
			<option value="<?=$arItem["id"]?>" <?if(is_array($arSettings["brands"]) && in_array($arItem["id"], $arSettings["brands"])):?>selected<?endif?>><?=$arItem["name"]?></option>
			<?endforeach?>
		</select>
		</div>
	</div>
	<div style="" class="col-lg-12">
		<div class="col-lg-12" style="margin-top: 18px;">
			<?if(in_array($_REQUEST["type"], array("csv", "xls"))):?>
			<p><input type="checkbox" id="show_column" name="show_column" <?if($arSettings["show_column"] === true):?>checked<?endif?>><label for="show_column" style="top: -3px;position: relative;left: 3px;">Показать название колонок на 1 строке</label></p>
			<?endif?>
			<?if($_REQUEST["type"] == "csv"):?>
			<span style="margin: 0 0 3px 0;display: block;">Разделитель</span>
			<select class="form-control" name="delimiter">
				<option value="comma" <?if(isset($arSettings["delimiter"]) && $arSettings["delimiter"] == "comma"):?>selected<?endif?>>Запятая (,)</option>
				<option value="semicolon" <?if(isset($arSettings["delimiter"]) && $arSettings["delimiter"] == "semicolon"):?>selected<?endif?>>Точка с запятой (;)</option>
				<option value="tab" <?if(isset($arSettings["delimiter"]) && $arSettings["delimiter"] == "tab"):?>selected<?endif?>>Символ табуляции</option>
			</select>
			<?endif?>
			<span style="margin: 0 0 3px 0;display: block;">Разделитель в цене</span>
			<select class="form-control" name="price_delimiter">
				<option value="comma" <?if(isset($arSettings["price_delimiter"]) && $arSettings["price_delimiter"] == "comma"):?>selected<?endif?>>Запятая (10,25)</option>
				<option value="dot" <?if(isset($arSettings["price_delimiter"]) && $arSettings["price_delimiter"] == "dot"):?>selected<?endif?>>Точка (10.25)</option>
			</select>
			<span style="margin: 0 0 3px 0;display: block;">Кодировка</span>
			<select class="form-control" name="charset">
				<option value="utf8" <?if(isset($arSettings["charset"]) && $arSettings["charset"] == "utf8"):?>selected<?endif?>>utf-8</option>
				<option value="windows1251" <?if(isset($arSettings["charset"]) && $arSettings["charset"] == "windows1251"):?>selected<?endif?>>windows-1251</option>
			</select>
		</div>
	</div>
	<div style="" class="col-lg-12">
		<div class="col-lg-12">
			<button type="submit" class="btn btn-warning" style="margin: 10px 0 0 0;">Сохранить</button>
		</div>
	</div>
	<div class="col-lg-12 row" style="margin: 10px 0 0 0;"><div class="col-lg-12 info-text"></div></div>
</form>
<?if($_REQUEST["type"] == "xml" || $_REQUEST["type"] == "xls"):?>
	<div class="col-lg-12  " style="overflow: auto;">
	<div class="alert alert-danger" style="overflow: auto;">
	Для фалов xml лучше использовать кодировку utf-8
	</div>
	</div>
<?endif?>
</div>
<?require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_after.php');