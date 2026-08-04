<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');?>
<?
//$start = debug_microtime_float();
$barcode = trim(htmlspecialchars($_REQUEST["barcode"]));
?>
<?
if(CModule::IncludeModule("panel.manager") && strlen($barcode) > 0 && ctype_digit($barcode)){
	
	$filename = $_SERVER['DOCUMENT_ROOT'] . "/upload/barcodes/{$barcode}.png";
	
	require $_SERVER["DOCUMENT_ROOT"] . '/local/vendor/autoload.php';

	$redColor = [0, 0, 0];

	$generator = new Picqer\Barcode\BarcodeGeneratorPNG();
	file_put_contents($filename, $generator->getBarcode($barcode, $generator::TYPE_EAN_13, 3, 100, $redColor));

	if(file_exists($filename)){
		$res = array('status' => "ok", "barcodeURL" => "/upload/barcodes/{$barcode}.png");
	}else{
		$res = array('status' => "error", "error" => "Не удалось создать ШК");
	}
	
}else{
	$res = array('status' => "error", "error" => "ШК не получен");
}
echo json_encode($res, JSON_UNESCAPED_UNICODE);

header('Content-Type: application/json;charset=UTF-8');
die();