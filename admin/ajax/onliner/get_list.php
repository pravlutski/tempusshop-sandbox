<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');?>
<?
$search_text = false;
if(isset($_POST["search_text"]) && strlen($_POST["search_text"]) >= 3)
	$search_text = trim($_POST["search_text"]);
//prent($psFilter);
//prent($_POST);die;
?>
<?
use Bitrix\Main\Loader;
Loader::includeModule('iblock');
global $DB;
$arResult = array();
$arResult["ITEMS"] = [];
require_once($_SERVER["DOCUMENT_ROOT"] . "/bitrix/php_interface/include/classes/api_onliner.php");


$arArticle = array();

if($_POST["item-from-onliner"] == "Y"){
	$obj = new Onliner_API;
	$report = $obj->report_pricelist(CProSet::getOption("UPDATE_ONLINER"));
	$report = json_decode($report, true);
	//prent($report);
	$arID = array();
	foreach($report as $key => $arItem){
		//if($arItem["values"]["article"] && count($arItem["errors"]) > 0){
		
		$flg = false;
		if($arItem["values"]["id"] > 0){
			if(is_array($arItem["errors"]) && count($arItem["errors"]) > 0){
				//foreach($arItem["errors"]["model"] as $k => $v){
					//if($v["code"] == "ERROR_MODEL_NOT_FOUND") $flg = true;
					//ERROR_MODEL_OUTDATED
				//	$flg = true;
				//}
				$flg = true;
				//$arArticle[] = $arItem["values"]["article"];
			}
			/*if(is_array($arItem["warnings"]) && count($arItem["warnings"]) > 0){
				//foreach($arItem["warnings"]["article"] as $k => $v){
				//	if($v["code"] == "ERROR_MODEL_NOT_FOUND") $flg = true;
				//}
				$flg = true;
			}*/
			if($flg)
				$arID[] = $arItem["values"]["id"];
		}


	}
	$arFilter["ID"] = $arID;
	//$arFilter["PROPERTY_ARTICLE_ONLINER"] = false;
}else{
	
}

$rs = CIBlockElement::GetList(array("NAME" => "ASC"), array('IBLOCK_ID' => CProSet::IB_BRANDS), false, false, array("ID", "NAME"));
$arBrand = array();
while ($ar = $rs->GetNext()){
	$arResult["BRAND_LIST"][$ar["ID"]] = $ar["NAME"];
}

$arFilter["IBLOCK_ID"] = CProSet::IB_CATALOG;
//$arFilter["PROPERTY_MODEL_ONLINER"] = false;
//$arFilter["PROPERTY_BRAND_ONLINER"] = false;
//$arFilter["!PROPERTY_CML2_ARTICLE"] = false;

//$arFilter["PROPERTY_CML2_ARTICLE"] = $arArticle;

if(isset($_POST["onliner-brand"])){
	$arFilter["PROPERTY_BRAND"] = $_POST["onliner-brand"];
}else{
	$arFilter["!PROPERTY_BRAND"] = false;
}

if(isset($_POST["search-model"]) && strlen($_POST["search-model"]) > 3){
	$arFilter["%NAME"] = $_POST["search-model"];
}
if(isset($_POST["limit-cnt"]) && $_POST["limit-cnt"] > 0){
	$arNavStartParams["nPageSize"] = $_POST["limit-cnt"];
}else{
	$arNavStartParams["nPageSize"] = 200;
}

if(isset($_POST["limit-page"]) && $_POST["limit-page"] > 0){
	$arNavStartParams["iNumPage"] =  $_POST["limit-page"];
}else{
	$arNavStartParams["iNumPage"] = 1;
}
//
$rs = CIBlockElement::GetList(array("ID" => "ASC"), $arFilter, false, $arNavStartParams, array("ID", "NAME", "PROPERTY_CML2_ARTICLE", "PROPERTY_BRAND", "PROPERTY_ARTICLE_ONLINER"));
$arBrand = array();
while ($ar = $rs->GetNext()){
	$arResult["ITEMS"][] = $ar;
}
//prent($arResult["ITEMS"]);
//unset($arFilter["PROPERTY_BRAND"]);
$rsAll = CIBlockElement::GetList(array("ID" => "ASC"), $arFilter);
$allCnt = $rsAll->SelectedRowsCount();
	
//prent($allCnt);
?>
<tr>
	<td colspan="6"><b>Всех элементов - <?=count($arResult["ITEMS"])?> из <?=$allCnt?></b></td>
</tr>
<?foreach($arResult["ITEMS"] as $key => $arItem):?>
	<tr id="tr-onliner-<?=$arItem["ID"]?>" data-id="<?=$arItem["ID"]?>">
		<td style="font-size: 11px;"><?=$arItem["NAME"]?>
		<a href="#" class="copy-name" data-model="<?=$arResult["BRAND_LIST"][$arItem["PROPERTY_BRAND_VALUE"]]?>" data-text="<?if($_POST["remove-last-symbol"] == "Y" && !is_numeric(substr($arItem["PROPERTY_CML2_ARTICLE_VALUE"], -1))):?><?=substr($arItem["PROPERTY_CML2_ARTICLE_VALUE"], 0, -1)?><?else:?><?=$arItem["PROPERTY_CML2_ARTICLE_VALUE"]?><?endif?>" title="<?=$arItem["NAME"]?>" style="font-size: 14px;margin: 0px 0 0 5px;display: inline-block;line-height: 9px;font-weight: bold;">>></a>
		</td>
		<td><input type="text" name="find-onliner" data-model="<?=$arResult["BRAND_LIST"][$arItem["PROPERTY_BRAND_VALUE"]]?>" class="find-onliner"></td>
		<?/*<td class="i-section"></td>*/?>
		<td class="i-brand"></td>
		<td class="i-model"></td>
		<td class="i-name"></td>
		<td class="i-submit">
			<form action="#" method="POST" class="set-onliner-prop">
				<input type="hidden" name="name" class="name" value="">
				<input type="hidden" name="product_id" class="product_id" value="">
				<input type="hidden" name="onliner_id" class="onliner_id" value="">
				<input type="submit" name="submit" value="Загрузить" class="btn btn-primary set-onliner-item" style="display: none">
			</form>
		</td>
	</tr>
<?endforeach?>
<?
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_after.php');