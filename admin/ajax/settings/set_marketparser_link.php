<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');

if(!CModule::IncludeModule('panel.manager') || $_SERVER['HTTP_X_REQUESTED_WITH'] != 'XMLHttpRequest') return;
$company_id = intval($_POST["company-id"]);
$section_id = intval($_POST["sections"]);
if($company_id > 0 && $section_id > 0){
	$in = array(
		"company_id" => $company_id,
		"bitrix_id" => $section_id,
	);
	global $DB;
	$id = (int)$id;
	$strSql = "SELECT * FROM ci_marketparser WHERE company_id = '{$company_id}'";
	$results = $DB->Query($strSql, false, $err_mess.__LINE__);
	if ($row = $results->Fetch()){
		$DB->Update("ci_marketparser", $in, "WHERE company_id='".$company_id."'", $err_mess.__LINE__);
	}else{
		$DB->Insert("ci_marketparser", $in, $err_mess.__LINE__);
/*		$res = array(
			'status' => ($insert_id ? "ok" : "error"),
			'text' => ($insert_id ? "Настройки сохранены" : "Не удалось сохранить")
		);*/
	}
	$res = array(
		'status' => "ok",
		'text' => "Настройки сохранены"
	);
}else{
	$res = array(
		'status' => "error",
		'text' => "Не выбрана компания marketparser или раздел каталога"
	);
}
echo json_encode($res, JSON_UNESCAPED_UNICODE);

header('Content-Type: application/json;charset=UTF-8');
die();
