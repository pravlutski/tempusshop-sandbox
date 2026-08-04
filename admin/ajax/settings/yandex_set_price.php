<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
//
if(!CModule::IncludeModule('panel.manager')|| $_SERVER['HTTP_X_REQUESTED_WITH'] != 'XMLHttpRequest' ||
	!CModule::IncludeModule("main") || !CModule::IncludeModule("iblock") || !CModule::IncludeModule("catalog")) return;

$res["status"] = "error";
$res["text"] = "";

if(is_array($_POST["yandex-brand"]) && count($_POST["yandex-brand"]) > 0){
	global $DB;
	$api = new MParserAPI;

	$arActiveCompany = array(14955);

	$arSection = $_REQUEST["yandex-brand"];

	$arIDs = array();
	if(!$_POST["set_all"] && $_POST["set_all"] != "Y"){
		$strSql = "SELECT BITRIX_ID FROM ci_marketparser_id";

		$results = $DB->Query($strSql, false, $err_mess.__LINE__);


		while ($arFields = $results->Fetch()){
			$arIDs[] = $arFields["BITRIX_ID"];
		}
	}
	/* TODO */
	$arIDs = [1062];
	$arIDs = []; 
	$strSql = "SELECT bitrix_id, source, article as model FROM ci_wb_top";
	$results = $DB->Query($strSql, false, $err_mess.__LINE__);
	while ($row = $results->Fetch()){
		$arIDs[] = $row['bitrix_id'];
	}
	/* TODO */ 
	
	//массив с брендами
	$arFilter = Array(
		"IBLOCK_ID" => CProSet::IB_BRANDS,
		"ACTIVE" => "Y",
	);

	$resEl = CIBlockElement::GetList(Array(), $arFilter, false, false, array("ID", "NAME"));
	$arBitrixBrand = array();
	while($obEl = $resEl->GetNextElement()){
		$arEl = $obEl->GetFields();
		$arBitrixBrand[$arEl["ID"]] = $arEl["NAME"];
	}

	foreach($arActiveCompany as $key => $c_id){
		$arResult = array();
		$arFilter = Array(
			"IBLOCK_ID" => CProSet::IB_CATALOG,
			"PROPERTY_BRAND" => $arSection,
			"INCLUDE_SUBSECTIONS" => "Y",
			"!PROPERTY_CML2_ARTICLE" => false,
			//"PROPERTY_AVAILABILITY_RU" => 512
		);
		if(is_array($arIDs) && count($arIDs) > 0)
			$arFilter["ID"] = $arIDs;
		//AddMessage2Log($arFilter);
		$resEl = CIBlockElement::GetList(Array(), $arFilter, false, false, array("ID", "NAME", "IBLOCK_SECTION_ID", "CODE", "DETAIL_PAGE_URL", "PROPERTY_CML2_ARTICLE", "PROPERTY_BRAND", "PROPERTY_MINIMUM_PRICE"));
//		$arResult = array();
		while($obEl = $resEl->GetNextElement()){
			$arEl = $obEl->GetFields();

			if($arBitrixBrand[$arEl["PROPERTY_BRAND_VALUE"]]){
				$name = $arBitrixBrand[$arEl["PROPERTY_BRAND_VALUE"]] . " " . $arEl["PROPERTY_CML2_ARTICLE_VALUE"];
			}else{
				$name = $arEl["NAME"];
			}

			$arResult["ITEMS"][] = array(
				"name" => $name,//$arEl["PROPERTY_CML2_ARTICLE_VALUE"],
				"cost" => $arEl["PROPERTY_MINIMUM_PRICE_VALUE"],
				//"id" => $arEl["CODE"],
				"id" => $arEl["ID"],
				"custom" => array(
					"custom-field-1" => "http://tempusshop.ru" . $arEl["DETAIL_PAGE_URL"],
					"custom-field-2" => $arEl["PROPERTY_CML2_ARTICLE_VALUE"],
				),
				//"vendor_code" => $arEl["PROPERTY_CML2_ARTICLE_VALUE"],
			);

		}
		//prent($arResult["ITEMS"]);die;
		$priceData = array('products' => $arResult["ITEMS"]);//prent($priceData);die;
		$price = $api->setPriceCompany($c_id, $priceData);
		//prent($price);
	}
}
echo json_encode($res, JSON_UNESCAPED_UNICODE);
header('Content-Type: application/json;charset=UTF-8');
die();
?>
