<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');?>
<?
$start = debug_microtime_float();
$id = intval($_POST["id"]);
?>
<?
if(CModule::IncludeModule("panel.manager") && $id > 0){
	
	$arFilter = Array(
		"IBLOCK_ID"	=> 16,
		"ID" => $id,
	);
	$rs = CIBlockElement::GetList(array(), $arFilter, false, false, array("ID","PROPERTY_AEN"));

	$barcode = "";
	
	if($ob = $rs->GetNextElement()){
		$arFields = $ob->GetFields();

		$barcode = $arFields["PROPERTY_AEN_VALUE"];
		
	}
	$end = debug_microtime_float();
	$txt = "Время выполнения - " . ($end - $start);
	$res = array(
		'status' => "ok",
		'barcode' => $barcode,
		'time' => $txt,
	);
	

}else{
	$res = array(
		'status' => 'error',
		'text' => "Не удалось сохранить"
	);
}

//AddMessage2Log($txt);
echo json_encode($res, JSON_UNESCAPED_UNICODE);

header('Content-Type: application/json;charset=UTF-8');
die();