<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
if(!CModule::IncludeModule("main") || !CModule::IncludeModule("iblock") || !CModule::IncludeModule('panel.manager')) return;
$objUtils = new CPanelUtils;
$objProduct = new CPanelProduct;

$arItems = explode("\n", $_POST["an_list"]);
$arLog = false;
foreach($arItems as $key => $list){
	
	$str = str_replace(array("\t"), ";", $list);
	
	$tmp = explode(";", $str);
	$article = trim($tmp[0]);
	$article = htmlentities($article);
	$article = str_replace("&nbsp;", "", $article);
	
	if($altArt = $objUtils->getArtnumber($article)){
		$article = $altArt;
	}

	$barcode = trim($tmp[1]);
	$barcode = htmlentities($barcode);
	$barcode = str_replace("&nbsp;", "", $barcode);
	if( empty($article) or empty($barcode) ) {
		$arLog .= "<p style='color:red;'>{$article} - {$barcode} . Заполнены не все поля.</p>";
	}elseif( !( CPanelProduct::findArticle( $article ) ) ){
		$arLog .= "<p style='color:red;'>{$article} - {$barcode} . Такой артикул не существует на сайте.</p>";
	}elseif( $objUtils->checkArtBarcode($article, $barcode) ){
		if($objUtils->LAST_ERROR)
			$arLog .= "<p style='color:red;'>" . $objUtils->LAST_ERROR . "</p>";
		else
			$arLog .= "<p style='color:red;'>Ошибка добавления артикула</p>";
	}elseif( $objUtils->addAltBarcode($article, $barcode) ){
		$arLog .= "<p style='color:green;'>{$article} - {$barcode} добавлен</p>";
	}else{
		$arLog .= "<p style='color:red;'>{$article} - {$barcode} ошибка</p>";
	}
}
if($arLog)
	echo $arLog;

