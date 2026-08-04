<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
//
if(!CModule::IncludeModule('panel.manager')|| $_SERVER['HTTP_X_REQUESTED_WITH'] != 'XMLHttpRequest' || 
	!CModule::IncludeModule("main") || !CModule::IncludeModule("iblock") || !CModule::IncludeModule("catalog")) return;

$res["status"] = "error";
$res["text"] = "";

if(count($_POST["yandex-company"]) > 0){
	global $DB;
	$api = new MParserAPI;
	$arCompany = $api->getCompanyList();
/*	
	$arSection = array(
		"14956"	=> 283, //Orient
		"14955" => 223, //Casio
		"14954" => 269, //Armani
		"14751" => 252, //Diesel
		"14750" => 258, //DKNY
		"14749" => 274, //Fossil
		"14720" => 309, //Skagen
		"16976" => 380, //Anne Klein
		"15731" => 206, //Adriatica
		"15729" => 413, //Frederique Constant
	);
*/
	$arResult["TEXT_CPMARKET"] = array();//массив с дополнительным текстом
	$rsSect = \CIBlockSection::GetList(array("depth_level" => "asc"), array('IBLOCK_ID' => CProSet::IB_CATALOG), false, array("UF_TYPE_CPMARKET"));
	
	while($arSect = $rsSect->Fetch()){
		if(!$arSect["UF_TYPE_CPMARKET"] && isset($arResult["TEXT_CPMARKET"][$arSect["IBLOCK_SECTION_ID"]]))
			$arResult["TEXT_CPMARKET"][$arSect["ID"]] = $arResult["TEXT_CPMARKET"][$arSect["IBLOCK_SECTION_ID"]];
		else
			$arResult["TEXT_CPMARKET"][$arSect["ID"]] = $arSect["UF_TYPE_CPMARKET"];
	}

	
	//сопоставление компании и ID Раздела на сайте
	$strSql = "SELECT * FROM ci_marketparser";
	$results = $DB->Query($strSql, false, $err_mess.__LINE__);
	while ($row = $results->Fetch()){
		$arSection[$row["company_id"]] = $row["bitrix_id"];
	}
	foreach($arCompany["response"]["campaigns"] as $key => $company){
		if(in_array($company["id"], $_POST["yandex-company"]) && isset($arSection[$company["id"]]))
			$arActiveCompany[$company["id"]] = $arSection[$company["id"]];
	}
	foreach($arActiveCompany as $c_id => $section_id){
		$arResult = array();
		$arFilter = Array(
			"IBLOCK_ID" => CProSet::IB_CATALOG, 
			"SECTION_ID" => $section_id, 
			"INCLUDE_SUBSECTIONS" => "Y",
			"!PROPERTY_CML2_ARTICLE" => false,
			"PROPERTY_AVAILABILITY_RU" => 512
		);
		$resEl = CIBlockElement::GetList(Array(), $arFilter, false, false, array("ID", "NAME", "IBLOCK_SECTION_ID", "CODE", "DETAIL_PAGE_URL", "PROPERTY_CML2_ARTICLE", "PROPERTY_MINIMUM_PRICE"));
//		$arResult = array();
		while($obEl = $resEl->GetNextElement()){
			$arEl = $obEl->GetFields();
//			if(isset($arResult["TEXT_CPMARKET"][$arEl["IBLOCK_SECTION_ID"]]) && strlen($arResult["TEXT_CPMARKET"][$arEl["IBLOCK_SECTION_ID"]]) > 0)
//				$name = $arResult["TEXT_CPMARKET"][$arEl["IBLOCK_SECTION_ID"]] . " " . $arEl["NAME"];
//			else
				$name = $arEl["NAME"];
/*			if($section_id == 269) {
				$name = str_replace("Emporio", "", $arEl["NAME"]);
				$name = trim($name);
			}*/
			$arResult["ITEMS"][] = array(
				"name" => $name,//$arEl["PROPERTY_CML2_ARTICLE_VALUE"],
				"cost" => $arEl["PROPERTY_MINIMUM_PRICE_VALUE"],
				"id" => $arEl["CODE"],
				"custom" => array(
					"custom-field-1" => "http://tempusshop.ru" . $arEl["DETAIL_PAGE_URL"],
				),
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