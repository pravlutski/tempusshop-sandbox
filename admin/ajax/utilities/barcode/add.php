<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
if(!CModule::IncludeModule("main") || !CModule::IncludeModule("iblock") || $_SERVER['HTTP_X_REQUESTED_WITH'] != 'XMLHttpRequest' || !CModule::IncludeModule('panel.manager')) return;

$objUtils = new CPanelUtils;
$objProduct = new CPanelProduct;

$article = trim( $_POST['an'] );
$article = htmlentities($article);
$article = str_replace("&nbsp;", "", $article);

if($altArt = $objUtils->getArtnumber($article)){
	$article = $altArt;
}

$barcode = trim( $_POST['alt'] );
$barcode = htmlentities($barcode);
$barcode = str_replace("&nbsp;", "", $barcode);
if( empty($article) or empty($barcode) ) {
	$res["status"] = "error";
	$res["data"] = "Заполнены не все поля.";
}elseif( !( CPanelProduct::findArticle($article) ) ){
	$res["status"] = "error";
	$res["data"] = "Такой артикул не существует на сайте.";
}elseif( $objUtils->checkArtBarcode($article, $barcode) ){
	$res["status"] = "error";
	if($objUtils->LAST_ERROR)
		$res["data"] = $objUtils->LAST_ERROR;
	else
		$res["data"] = "Ошибка добавления артикула1";
}elseif( $objUtils->addAltBarcode($article, $barcode) ){
	$res["status"] = "ok";
}else{
	$res["status"] = "error";
	$res["data"] = "Внутрення ошибка сервера. Попробуйте позже.";
}
echo json_encode($res, JSON_UNESCAPED_UNICODE);
header('Content-Type: application/json;charset=UTF-8');
die();
