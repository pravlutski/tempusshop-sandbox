<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
if(!CModule::IncludeModule("main") || !CModule::IncludeModule("iblock") || !CModule::IncludeModule('panel.manager')) return;
$objUtils = new CPanelUtils;
$objProduct = new CPanelProduct;

$arItems = explode("\n", $_POST["an_list"]);
$arLog = false;
foreach($arItems as $key => $list){

	$str = str_replace(array("\t"), ";", $list);

	$tmp = explode(";", $str);
	$an = trim($tmp[0]);
	$an = htmlentities($an);
	$an = str_replace("&nbsp;", "", $an);

	$alt = trim($tmp[1]);
	$alt = htmlentities($alt);
	$alt = str_replace("&nbsp;", "", $alt);
	if( empty($an) or empty($alt) ) {
		$arLog .= "<p style='color:red;'>{$an} - {$alt} . Заполнены не все поля.</p>";
	}elseif( !( CPanelProduct::findArticle( $an ) ) ){
		$arLog .= "<p style='color:red;'>{$an} - {$alt} . Такой артикул не существует на сайте.</p>";
	}elseif( $objUtils->checkAltRule($an, $alt) ){
		$arLog .= "<p style='color:red;'>{$an} - {$alt} . Такое правило уже существует или Артикул=Альтернативный</p>";
	}elseif( $objUtils->addAltAn($an, $alt, 'manual') ){
		$arLog .= "<p style='color:green;'>{$an} - {$alt} добавлен</p>";
	}else{
		$arLog .= "<p style='color:red;'>{$an} - {$alt} ошибка</p>";
	}
}
if($arLog)
	echo $arLog;
