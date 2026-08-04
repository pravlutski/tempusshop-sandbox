<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
if($_SERVER['HTTP_X_REQUESTED_WITH'] != 'XMLHttpRequest' || !CModule::IncludeModule('panel.manager')) return;


$user_id = $_POST['USER_ID'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user_id > 0) {
	global $DB;

  $profiles = $_POST['profile'];
	$arr = json_encode( $profiles, JSON_UNESCAPED_UNICODE );
	$in = array(
		"PRICE_SETTINGS" => "'".$arr."'",
	);
	if (!empty($profiles)){
		$DB->Update("ci_opt", $in, "WHERE USER_ID='".$user_id."'", $err_mess.__LINE__);
    $res["status"] = "ok";
  	$res["data"] = "ok";
  	$res["data"] = "Настройки сохранены";
	}else{
  	$res["status"] = "error";
  	$res["data"] = "Отсутстует поставщик в БД";
  }

//	$res["data"] = serialize($_POST);
}else{
	$res["status"] = "error";
	$res["data"] = "Не корректный запрос";
}

echo json_encode($res, JSON_UNESCAPED_UNICODE);
header('Content-Type: application/json;charset=UTF-8');
die();
