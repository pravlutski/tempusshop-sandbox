<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');?>
<?
global $DB;
global $USER;
?>
<?
global $USER;
if(CModule::IncludeModule("panel.manager")){
	$ar = false;
	$userID = $USER->getID();
	$strSql = "SELECT * FROM ci_purchase WHERE active = 'Y'";
	$results = $DB->Query($strSql, false, $err_mess.__LINE__);
	while ($row = $results->Fetch()){
		$ar[] = $row;
	}
	foreach($ar as $key => $arItem){
		$DB->Update("ci_purchase", array("active" => "'N'", "tmp_order_id" => $arItem["order_id"], "order_id" => false, "user_modify" => "'{$userID}'"), "WHERE id='".$arItem["id"]."'", $err_mess.__LINE__);
	}
	$res = array(
		'status' => "ok",
		'text' => "Записи сохранены",
	);

}else{
	$res = array(
		'status' => 'error',
		'text' => "Не удалось сохранить. Не корректные данные"
	);
}
echo json_encode($res, JSON_UNESCAPED_UNICODE);

header('Content-Type: application/json;charset=UTF-8');
die();
?>