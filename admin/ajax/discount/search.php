<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
if(!CModule::IncludeModule("main") || !CModule::IncludeModule("iblock") || $_SERVER['HTTP_X_REQUESTED_WITH'] != 'XMLHttpRequest' || !CModule::IncludeModule('panel.manager')) return;

$str = trim($_POST["str"]);
$obj = new CPanelDiscount;
$rs = $obj->find($str);

if(is_array($rs) && count($rs) > 0){
	$res["data"] = false;
	foreach($rs as $key => $row){
		$res["data"][] = array(
			"id" => $row["id"],
			"code" => substr($row['code'], 0, 3) . ' ' . substr($row['code'], 3, 3),
			"fio" => $row["fullname"],
			"phone" => RawToFormatted($row['phone']),
			"sum" => number_format($obj->getAmount( $row['id'] ), 2, '.', ' '),
			"discount" => $obj->getDiscount( $row['id'] ).' %',
			"datetime" => $row['datetime'],
		);
	}
	$res["status"] = "ok";
}else{
	$res["status"] = "error";
	$res["data"] = false;
}
//prent($res);
echo json_encode($res, JSON_UNESCAPED_UNICODE);
header('Content-Type: application/json;charset=UTF-8');
die();