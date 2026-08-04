<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');

if(!CModule::IncludeModule("crm_courier") || !CModule::IncludeModule("panel.manager")) return;

global $USER;
global $DB;
$ID = intval($_POST["id"]);

if($ID > 0 && ($USER->IsAuthorized() && (in_array("6",$USER->GetUserGroupArray())||in_array("12",$USER->GetUserGroupArray())||in_array("21",$USER->GetUserGroupArray())||$USER->isAdmin()))){

	$rsDelivery = new CCourierDelivery();
	$filter = array(
		"ID" 		=> $ID,
	);
	//AddMessage2Log($_POST);
	$rsList = $rsDelivery->GetList(false, $filter)[0];
	//
	if($rsList) {
		$arFields = array(
			'USER_ID' => $USER->getID(),
			'PRICE_ORDER' => $_POST["price_order"],
			'PRICE_DELIVERY' => $_POST["price_delivery"],
			'COMMENT' => $_POST["comment"],
			'STATUS' => $_POST["status"],
			//'TIMESTAMP_X' => date("Y-m-d H:i:s"),
			'BAD_CLIENT' => ($_POST["bad_client"] == "Y" ? "Y" : "N"),
		);
		//AddMessage2Log($arFields);
		$res = $rsDelivery->Update($ID, $arFields);
		if($res === true){
			$arResponse["success"] = "Y";
		}else{
			$arResponse["error"][] = "Запись создать не удалось. " . $rsDelivery->LAST_ERROR;
			$arResponse["success"] = "N";
		}
	}else{
		$arResponse["error"][] = "Запись не найдена";
		$arResponse["success"] = "N";
	}

}else{
	$arResponse["error"][] = "Не предвиденная ошибка. Обновите страницу и попробуйте повторить";
	$arResponse["success"] = "N";
}
if(is_array($arResponse["error"]) && count($arResponse["error"]) > 0){
	$arLog = array(
		"_POST" => $_POST,
		"arOrder" => $arOrder,
		"res" => $res,
		"res2" => $res2,
		"arResponse" => $arResponse,
		"arFields" => $arFields,
		"file" => "set_order_updated.php",
	);
	ob_start();
	print_r($arLog);
	$content = ob_get_contents();
	ob_end_clean();
	$f = "/var/www/bitrix_logs/courier/" . date("Y_m_d") . ".txt";
	file_put_contents($f, $content);
}
//prent($arResponse);
header('Content-Type: application/json;charset=UTF-8');
echo json_encode($arResponse);


require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_after.php');
