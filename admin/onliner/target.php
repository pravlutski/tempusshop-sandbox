<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');?>
<?
$ID = intval($_REQUEST["ID"]);
if($ID <= 0) return;

$rs = CIBlockElement::GetList(array("ID" => "DESC"), array('IBLOCK_TYPE' => "ns_catalog", "ID" => $ID), false, false, array("ID", "ACTIVE", "IBLOCK_ID", "DETAIL_PAGE_URL"));
$arBrand = array();
if ($ar = $rs->GetNext()){
	CIBlockElement::SetPropertyValueCode($ar["ID"], "PRODUCT_NO_PRICE", "Y");
	$el = new CIBlockElement;
	$arLoadProductArray = Array(
		"ACTIVE"	=> "Y",
	);
	$el->Update($ar["ID"], $arLoadProductArray);
	
	LocalRedirect($ar["DETAIL_PAGE_URL"]);
	
//	prent($ar);
	//
}
?>
<?require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_after.php');