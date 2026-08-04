<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
if($_SERVER['HTTP_X_REQUESTED_WITH'] != 'XMLHttpRequest' || !CModule::IncludeModule('panel.manager')) return;
$user_id = intval($_POST["USER_ID"]);
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user_id > 0) {
	global $DB;
	$arFilter = array(
		"ID" => $user_id,
		"GROUPS_ID"	=> array(9),
	);
	$dbRes = CUser::GetList($by = 'ID', $order = 'ASC', $arFilter, array("SELECT"=>array()));
	if($arUser = $dbRes->Fetch()){
		$arResult["ID"] = $arUser["ID"];
		$arResult["LOGIN"] = $arUser["LOGIN"];
	}else{
		die("Пользователь не найден");
	}

	$strSql = "SELECT * FROM ci_opt WHERE USER_ID = '{$arResult["ID"]}'";
	$results = $DB->Query($strSql, false, $err_mess.__LINE__);

	$arr["MARGIN"] = $_POST["MARGIN"];
	$arr["CURRENCY"] = $_POST["CURRENCY"];
	//$arr["VAT"] = $_POST["VAT"];
	$arr["PRICE_VAT"] = ($_POST["PRICE_VAT"] && $_POST["PRICE_VAT"] == "on" ? "Y" : "N");
	$arr["EXCLUDE_ARTICLES"] = $_POST["EXCLUDE_ARTICLES"];
	$arr["TRADING_PLATFORM"] = intval($_POST["TRADING_PLATFORM"]);
	$arr = json_encode( $arr, JSON_UNESCAPED_UNICODE );
	$in = array(
		"SETTINGS" => "'".$arr."'",
	);
	if ($row = $results->Fetch()){
		$DB->Update("ci_opt", $in, "WHERE USER_ID='".$arResult["ID"]."'", $err_mess.__LINE__);
	}else{
		$in["USER_ID"] = $arResult["ID"];
		$ID = $DB->Insert("ci_opt", $in, $err_mess.__LINE__);
	}
	$res["status"] = "ok";
	$res["data"] = "ok";
	$res["data"] = "Настройки сохранены";
//	$res["data"] = serialize($_POST);
}else{
	$res["status"] = "error";
	$res["data"] = "Не корректный запрос";
}

echo json_encode($res, JSON_UNESCAPED_UNICODE);
header('Content-Type: application/json;charset=UTF-8');
die();
