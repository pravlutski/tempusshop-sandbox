<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');?>
<?
//$start = debug_microtime_float();
$purchase_list = unserialize($_REQUEST["purchase_list"]);
prent($_REQUEST);prent($purchase_list); 
?>
<?
if(CModule::IncludeModule("panel.manager") && is_array($purchase_list) && count($purchase_list) > 0){
	
	$filename = $_SERVER['DOCUMENT_ROOT'] . "/upload/barcodes/{$barcode}.png";
	
	require($_SERVER["DOCUMENT_ROOT"] . '/local/classes/SimpleXLSXGen.php');

	$GLOBALS['APPLICATION']->RestartBuffer();
	$xlsx = new SimpleXLSXGen();

	mb_internal_encoding('latin1');
	$purchase_list = array_values($purchase_list);
	$xlsx->addSheet($purchase_list, "tempus");

	$fileName = "purchase_list.xlsx";
	$xlsx->downloadAs($fileName);

}
die();