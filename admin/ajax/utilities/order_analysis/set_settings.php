<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');?>
<?
if(CModule::IncludeModule("panel.manager")){
	CProSet::setOption("TRADING_SETTINGS", json_encode($_POST));
	
	//$filename = "/userscripts/logs/set_control_rrc.log";
	//file_put_contents($filename, date("Y-m-d H:i:s") . " - " . serialize($_POST) . "\r\n", FILE_APPEND);
	
	$res = array(
		'status' => "ok",
		'text' => "Настройки сохранены",
	);
	//BXClearCache(true, "/s1/adm/analiz.bad.items/");
//	prent($res);
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