<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');?>
<?
global $DB;
if(isset($_POST["website"]) && in_array($_POST["website"], array("s1", "s2", "s3")))
	$website = array($_POST["website"]);
else
	$website = array("s1", "s2", "s3");
?>
<div class="row">
<?
global $USER;
$arGroups = $USER->GetUserGroupArray();
if($USER->isAdmin() || in_array(6, $arGroups)) $arResult["ACCESS"] = true;

if(!$arResult["ACCESS"]) return;

$start = debug_microtime_float();

if(CModule::IncludeModule("panel.manager") && CModule::IncludeModule("iblock") && CModule::IncludeModule("catalog")){
	$arResult = $arTmp = array();
	$objService = new OrderService;
	$objService->getPropOrderFlg = false;
	//prent($arResult["SUPPLIER_NAME"]);
	$arFilter = array(
		"LID" => $website,//array("s1", "s2"),
		"STATUS_ID" => array("SE", "TA", "CO", "CL"),//array("N"),//, "WT"),
		"!CANCELED" => "Y",
	);

	//$arOrder = $objService->getOrder(array("LID" => "asc", "DATE_INSERT" => "DESC"), $arFilter);
	$arOrder = $objService->getOrderCache(array("LID" => "asc", "DATE_INSERT" => "DESC"), $arFilter);

	foreach($arOrder as $key => $arItem){
		foreach($arItem["BASKET"] as $k => $basket){
			for($i = 1; $i <= $basket["QUANTITY"]; $i++){
				$arResult["ITEMS"][] = array(
					"ID" => $arItem["ID"],
					"PRODUCT_ID" => $basket["PRODUCT_ID"],
					"SITE_ID" => $arItem["LID"],
					"COMMENTS" => $arItem["COMMENTS"],
					"DELIVERY_ID" => $arItem["DELIVERY_ID"],
					"ORDER_NUMBER_ID" => $arItem["ORDER_ID"],
				);
			}
		}
	}

	unset($arItem);
	foreach($arResult["ITEMS"] as $key => &$arItem){
		$objRes = CIBlockElement::GetList(array(), array('IBLOCK_ID' => CProSet::IB_CATALOG, 'ID' => $arItem["PRODUCT_ID"]), false, false, array('PROPERTY_CML2_ARTICLE'));
        if ($res = $objRes->GetNext()){
			$arItem["ARTICLE"] = $res["PROPERTY_CML2_ARTICLE_VALUE"];

		}
	}
	unset($arItem);

	//edit
	$arPrint = array();
	foreach ($arResult["ITEMS"] as $key => $value) {
		if (isset($arPrint[$value['ARTICLE']])) {
			$arPrint[$value['ARTICLE']] = $arPrint[$value['ARTICLE']] + 1;
		} else {
			$arPrint[$value['ARTICLE']] = 1;
		}
	}
	//print_r($arPrint);


	$objMS = new MoyskladAPI('s1');
	$msStore = array("51538bd5-6cf3-11ef-0a80-10ba001db77c");
	foreach($msStore as $store_id){
			//if(strlen($store_id) > 0) $filter = "store.id=" . $store_id; else $filter = "";
			//$objMS->getStock(0, $filter);
			if(strlen($store_id) > 0)
					$filter = "filter=store=https://api.moysklad.ru/api/remap/1.2/entity/store/{$store_id};stockMode=positiveOnly";
			else
					$filter = "";
			$objMS->getStock(0, $filter);

	}

	print_r($objMS->MSPosition);
	die();
	$arFilter = Array(
			"IBLOCK_ID" => CProSet::IB_CATALOG,
			"!PROPERTY_CML2_ARTICLE" => false,
			"XML_ID" => array_keys($objMS->MSPosition),
	);
	$result = CIBlockElement::GetList(Array(), $arFilter, false, false, array("ID","XML_ID", "PROPERTY_CML2_ARTICLE", "PROPERTY_BRAND"));

	while($arFields = $result->GetNext()){
		$arModels[$arFields['PROPERTY_CML2_ARTICLE_VALUE']] = $objMS->MSPosition[$arFields['XML_ID']];
	}

	$result = [];

	foreach ($arPrint as $model => $countOrder) {
	    if (isset($arModels[$model])) {
	        $countStock = $arModels[$model];
	        if ($countStock >= $countOrder) {
	            $result[$model] = $countOrder;
	            $arModels[$model] -= $countOrder;
	        } else {
	            $result[$model] = $countStock;
	            $arModels[$model] = 0;
	        }
	    }
	}


}else{
	?>
	<h2 class="color"><span>Не удалось получить список моделей(</span></h2>
	<p>Произошла непредвиденная ошибка. Пожалуйста, обновите страницу и попробуйте позже</p>
	<?
}

$end = debug_microtime_float();
prent($end - $start, 0, 1);

?>
</div>
<?
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_after.php');
