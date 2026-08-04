<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
if(!CModule::IncludeModule("main") || !CModule::IncludeModule("iblock") || $_SERVER['HTTP_X_REQUESTED_WITH'] != 'XMLHttpRequest' || !CModule::IncludeModule('panel.manager')) return;
$objUtils = new CPanelUtils;
$objProduct = new CPanelProduct;
$an = trim( $_POST['an'] );
$an = htmlentities($an);
$an = str_replace("&nbsp;", "", $an);

$alt = trim( $_POST['alt'] );
$alt = htmlentities($alt);
$alt = str_replace("&nbsp;", "", $alt);
if( empty($an) or empty($alt) ) {
	$res["status"] = "error";
	$res["data"] = "Заполнены не все поля.";
}elseif( !( CPanelProduct::findArticle( $an ) ) ){
	$res["status"] = "error";
	$res["data"] = "Такой артикул не существует на сайте.";
}elseif( $objUtils->checkAltRule($an, $alt) ){
	$res["status"] = "error";
	$res["data"] = "Такое правило уже существует";
}elseif( $objUtils->addAltAn($an, $alt, 'manual') ){
	$res["status"] = "ok";
}else{
	$res["status"] = "error";
	$res["data"] = "Внутрення ошибка сервера. Попробуйте позже.";
}
echo json_encode($res, JSON_UNESCAPED_UNICODE);
header('Content-Type: application/json;charset=UTF-8');
die();
