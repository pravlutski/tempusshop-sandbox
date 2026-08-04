<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');?>
<?
//$start = debug_microtime_float();
$product_id = intval($_POST["id"]);
$barcode = trim(htmlspecialchars($_POST["barcode"]));
$barcode_original = trim(htmlspecialchars($_POST["barcode_original"]));
?>
<?

if(CModule::IncludeModule("panel.manager") && $product_id > 0 && strlen($barcode) > 0){
	
	$objUtils = new CPanelUtils;
	$objProduct = new CPanelProduct;
	$article = $objProduct->findByID($product_id);
	if( $objUtils->checkArtBarcode($article, $barcode) ){
		$res["status"] = "error";
		if($objUtils->LAST_ERROR)
			$res["error"] = $objUtils->LAST_ERROR;
		else
			$res["error"] = "Ошибка установки";
	}elseif( $objUtils->addAltBarcode($article, $barcode, $product_id) ){
		$res["status"] = "ok";
		
		if($objUtils->LAST_ERROR)
			$res["error"] = $objUtils->LAST_ERROR;
		else
			$res["error"] = "Ошибка установки2";
	}else{
		$res["status"] = "error";
		$res["error"] = "ШК не установлен";
	}
	
	//CIBlockElement::SetPropertyValuesEx($product_id, false, array("AEN" => $barcode));
	//пишим баркод в таблицу. Сохранение будет выполняться при формировании файла для ВБ
	/*
	if($barcode != $barcode_original){
		$in = array(
			"PRODUCT_ID" => intval($product_id),
			"BARCODE" => "'".addslashes($barcode_original)."'",
			"BARCODE_UPDATE" => "'".addslashes($barcode)."'",
		);
		$DB->Insert("ci_barcode_update", $in, $err_mess.__LINE__);
	}
	
			
	$res = array(
		'status' => "ok",
	);
	*/

}else{
	$res = array(
		'status' => 'error',
		'error' => "Не удалось сохранить"
	);
}
//$end = debug_microtime_float();
//$txt = "Время выполнения - " . ($end - $start);
//AddMessage2Log($txt);
echo json_encode($res, JSON_UNESCAPED_UNICODE);

header('Content-Type: application/json;charset=UTF-8');
die();