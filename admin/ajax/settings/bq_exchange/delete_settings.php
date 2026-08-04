<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
if(!CModule::IncludeModule('panel.manager') || $_SERVER['HTTP_X_REQUESTED_WITH'] != 'XMLHttpRequest') return;
$ID = intval($_POST["id"]);
global $DB;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $ID >= 0){
  $strSql = "DELETE FROM bq_exchange WHERE ID = '{$ID}'";
	$result = $DB->Query($strSql, false, $err_mess.__LINE__);
  if ($result->result === true) {
    $res = ["STATUS" => "succes", "TEXT" => "Отчет № {$ID} успешно удален!"];
  } else {
    $res = ["STATUS" => "error", "TEXT" => "Не корректный запрос"];
  }
}else{
	$res = ["STATUS" => "error", "TEXT" => "Не корректный запрос"];
}
echo json_encode($res, JSON_UNESCAPED_UNICODE);

header('Content-Type: application/json;charset=UTF-8');
die();
?>
