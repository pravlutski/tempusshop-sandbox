<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');?>
<?
$id = intval($_POST["analysis_id"]);
$brand_id = intval($_POST["brand_id"]);
$price_id = false;
if(isset($_POST["website"]) && in_array($_POST["website"], array("ru","by","pl","ya","os","wb","av", "sb", "kz","ozkz","ozti")))
	$price_id = $_POST["website"];

//prent($_POST);
//
?>
<?
if(CModule::IncludeModule("panel.manager") && $price_id){

	$settingsAll = json_decode(CProSet::getOption("SETTINGS_RRC"), true);
	$settingsAll[$price_id]["price_type"] = $_POST['price_type'];
	CProSet::setOption("SETTINGS_RRC", json_encode($settingsAll));

	prent($settingsAll);
	//prent($settings);
}
?>
