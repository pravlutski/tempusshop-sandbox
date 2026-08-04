<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
global $DB, $USER;
if(!CModule::IncludeModule('panel.manager') || $_SERVER['HTTP_X_REQUESTED_WITH'] != 'XMLHttpRequest' || !$USER->IsAuthorized()) return;
if(!$_REQUEST["type"] || !in_array($_REQUEST["type"], array("csv", "xls", "xml"))) {
	echo json_encode(array('status' => "error",'text' => "No type file"), JSON_UNESCAPED_UNICODE);
	header('Content-Type: application/json;charset=UTF-8');
	die();
}
//prent($_POST);
$USER_ID = $USER->getID();
$arSettings = array();
$arAvail = array("name", "xml_id", "price", "price_tempus", "vendor", "article", "day_delivery", "gender", "charset", "delimiter", "price_delimiter","section_1");
$num_column = 1;//номер колонки. если будут пропускать пустые

if(isset($_POST["column-1"]) && in_array($_POST["column-1"], $arAvail)){
	$arSettings["column-{$num_column}"] = $_POST["column-1"];
	$num_column++;
}
if(isset($_POST["column-2"]) && in_array($_POST["column-2"], $arAvail)){
	$arSettings["column-{$num_column}"] = $_POST["column-2"];
	$num_column++;
}
if(isset($_POST["column-3"]) && in_array($_POST["column-3"], $arAvail)){
	$arSettings["column-{$num_column}"] = $_POST["column-3"];
	$num_column++;
}
if(isset($_POST["column-4"]) && in_array($_POST["column-4"], $arAvail)){
	$arSettings["column-{$num_column}"] = $_POST["column-4"];
	$num_column++;
}
if(isset($_POST["column-5"]) && in_array($_POST["column-5"], $arAvail)){
	$arSettings["column-{$num_column}"] = $_POST["column-5"];
	$num_column++;
}
if(isset($_POST["column-6"]) && in_array($_POST["column-6"], $arAvail)){
	$arSettings["column-{$num_column}"] = $_POST["column-6"];
	$num_column++;
}
if(isset($_POST["column-7"]) && in_array($_POST["column-7"], $arAvail)){
	$arSettings["column-{$num_column}"] = $_POST["column-7"];
	$num_column++;
}
if(isset($_POST["column-8"]) && in_array($_POST["column-8"], $arAvail)){
	$arSettings["column-{$num_column}"] = $_POST["column-8"];
	$num_column++;
}
if(isset($_POST["show_column"]) && $_POST["show_column"]){
	$arSettings["show_column"] = true;
}
if(isset($_POST["charset"]) && in_array($_POST["charset"], array("utf8", "windows1251"))){
	$arSettings["charset"] = $_POST["charset"];
}
if(isset($_POST["delimiter"]) && in_array($_POST["delimiter"], array("comma", "semicolon", "tab"))){
	$arSettings["delimiter"] = $_POST["delimiter"];
}
if(isset($_POST["price_delimiter"]) && in_array($_POST["price_delimiter"], array("comma", "dot"))){
	$arSettings["price_delimiter"] = $_POST["price_delimiter"];
}
if(isset($_POST["brands"])){
	$arSettings["brands"] = $_POST["brands"];
}

$strSql = "SELECT * FROM ci_opt_settings WHERE USER_ID = '".$DB->ForSql($USER_ID)."' AND TYPE='".$DB->ForSql($_REQUEST["type"])."'";
$results = $DB->Query($strSql, false, $err_mess.__LINE__);
if ($row = $results->Fetch()){
	//обновляем
	if(is_array($arSettings) && count($arSettings) < 2){
		//по умолчанию
		$arSettings = array(
			"column-1" => "price",
			"column-2" => "vendor",
			"column-3" => "article",
			"column-4" => "day_delivery",
		);
	}
	$DB->Update("ci_opt_settings", array("SETTINGS" => "'".addslashes(json_encode($arSettings))."'"), "WHERE USER_ID='".$USER_ID."' AND TYPE='{$_REQUEST["type"]}'", $err_mess.__LINE__);
	$res = array(
		'status' => "ok",
		'text' => "Настройки сохранены"
	);
}else{
	//добавляем
	$in = array(
		"USER_ID" => "'".addslashes($USER_ID)."'",
		"SETTINGS" => "'".addslashes(json_encode($arSettings))."'",
		"TYPE" => "'{$_REQUEST["type"]}'",
	);
	$ID = $DB->Insert("ci_opt_settings", $in, $err_mess.__LINE__);
	if($ID > 0){
		$res = array(
			'status' => "ok",
			'text' => "Настройки сохранены"
		);
	}else{
		$res = array(
			'status' => "error",
			'text' => "Ошибка добавления"
		);	
	}
}

echo json_encode($res, JSON_UNESCAPED_UNICODE);
header('Content-Type: application/json;charset=UTF-8');
die();