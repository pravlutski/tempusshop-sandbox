<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');?>
<?
global $DB;
global $USER;

$arResult = array();

$arResult["PROGNOSIS"] = intval($_POST["prognosis"]);
$arResult["MARGIN"] = intval($_POST["margin"]);
$arResult["MIN_PLAN"] = intval($_POST["min_plan"]);
$arResult["CURRENCY"] = $_POST["currency"];
$arResult["BRAND"] = $_POST["brand"];

$arResult["DATE_FROM"] = $_POST["date_from"];
$arResult["DATE_TO"] = $_POST["date_to"];
$arResult["DATE_PRE"] = $_POST["date_pre"];

$arResult["MIN_DIFFERENCE"] = intval($_POST["min_difference"]);
$arResult["SUPPLIER"] = $_POST["supplier"];
//edit
// foreach ($_POST["login"] as $key => $value) {
//   $arResult["LOGIN"][$value] = 1;
// }
$arResult["LOGIN"] = array('s1' =>1, 'msk' => 1);

// сохраняем настройки
$arSettings = [
	"DATE_FROM" => $arResult["DATE_FROM"],
	"DATE_TO" => $arResult["DATE_TO"],
  "DATE_PRE" => $arResult["DATE_PRE"],
	"PROGNOSIS" => $arResult["PROGNOSIS"],
	"MARGIN" => $arResult["MARGIN"],
	"MIN_PLAN" => $arResult["MIN_PLAN"],
	"BRAND" => $arResult["BRAND"],
	"CURRENCY" => $arResult["CURRENCY"],
	"MIN_DIFFERENCE" => $arResult["MIN_DIFFERENCE"],
	"SUPPLIER" => $arResult["SUPPLIER"],
  "LOGIN" => $arResult["LOGIN"],
];


$strSql = "SELECT * FROM ci_opt_settings WHERE USER_ID = '".$USER->getID()."' AND TYPE = 'purchase'";
$results = $DB->Query($strSql, false, $err_mess.__LINE__);
if ($row = $results->Fetch()){
	$DB->Update("ci_opt_settings", array("SETTINGS" => "'".addslashes(json_encode($arSettings))."'"), "WHERE ID='".$row["ID"]."'", $err_mess.__LINE__);
}else{
	$in = array(
		"USER_ID" => "'".addslashes($USER->getID())."'",
		"SETTINGS" => "'".addslashes(json_encode($arSettings))."'",
		"TYPE" => "'purchase'",
	);
	$ID = $DB->Insert("ci_opt_settings", $in, $err_mess.__LINE__);
}
