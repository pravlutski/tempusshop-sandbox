<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');

if(!CModule::IncludeModule("crm_courier") || !CModule::IncludeModule("panel.manager")) return;
require($_SERVER['DOCUMENT_ROOT'] . "/local/classes/DemandCourier.php" );

$orderID = intval($_POST["order_id"]);
global $USER;
$arLog = [
	"date" => date("Y-m-d H:i:s"),
	"USER" => $USER->getID(),
	"_POST" => $POST,
];
file_put_contents("/var/www/bitrix_logs/courier/accept_order.txt", print_r($arLog, true), 8);
if($orderID > 0){

	$objCourier = new CCourier();
	$objDemand = new DemandCourier('s1');

	$arFilter = array(
		"ids" => array($orderID),
	);

	$res = $objCourier->getOrderCrm($arFilter);

	if($res["statusCode"] == "200" && is_array($res["response"])){
		$order = $res["response"]["orders"][0];
		$resDemand = $objDemand->createDemand($order['number']);
		if ( !$resDemand ){
			$errDemand = $objDemand->getLog()['ERROR'][0];
			$arResponse["error"][] = $errDemand;
			$arResponse["success"] = 'N';
			echo json_encode($arResponse);
			die;
		}
//		if(!$order["delivery"]["data"]["id"] || $order["delivery"]["data"]["id"] == $objCourier->systemCourierID){
		if($order["status"] == "ready-to-delivery"){
			$arOrder = array(
				"id" => $order["id"],
				"delivery" => array(
					"data" => array(
						"courierId" => $objCourier->courierID,
					)
				),
				"status" => "delivering"
			);
			file_put_contents(
				'/var/www/bitrix/data/www/tempusshop.ru/admin/c/ajax/acceptBody_'.$order["site"].'.txt',
				print_r($arOrder, true),
				FILE_APPEND
			);
			$res2 = $objCourier->setOrder($arOrder, "id", $order["site"]);
			if($res2["statusCode"] == "200" && $res2["response"]["success"] == true){
				if($res2["response"]["order"]["delivery"]["data"]["courierId"] == $objCourier->courierID){
					$arResponse["success"] = "Y";
                    OrderService::setStatusOrderD7($order["number"], "CR", true);//Выдан курьеру на доставку

					//для s2 отправляем смс
					if($res2["response"]["order"]["site"] == "tempus-by"){
						$firstName = $res2["response"]["order"]["delivery"]["data"]["firstName"];
						$courier_phone = $res2["response"]["order"]["delivery"]["data"]["phone"]["number"];
						$client_phone = $res2["response"]["order"]["phone"];

						//$message = "Заказ №{$res2["response"]["order"]["number"]} доставит курьер {$firstName} {$courier_phone}\r\ntempus.by +375293449966";

						$template = COption::GetOptionString("panel.manager", "SMS_ORDER_ACCEPT_s2");
						$message = str_replace(array("#ORDER_NUMBER#", "#COURIER_NAME#", "#COURIER_PHONE#"), array($res2["response"]["order"]["number"], $firstName, $courier_phone), $template);

						$sms = sendSMS($client_phone, $message);

						if($sms === false){
							$arResponse["error"][] = "Заказ принят, но смс клиенту отправить не удалось";
							$arResponse["success"] = "N";
						}elseif($sms !== true && strlen($sms) > 0){
							$arResponse["error"][] = "Заказ принят, но смс клиенту отправить не удалось";
							$arResponse["error"][] = $sms;
							$arResponse["success"] = "N";
						}
					}

				}else{
					$arResponse["error"][] = "Ошибка записи. Установлен курьер '" . $res2["response"]["order"]["delivery"]["data"]["firstName"] . "'";
					$arResponse["success"] = "N";//prent($res2);
				}
			}elseif($res2["statusCode"] == "400" && $res2["response"]["errorMsg"]){
				$arResponse["error"][] = $res2["response"]["errorMsg"];
				$arResponse["success"] = "N";
			}else{
				$arResponse["error"][] = "Ошибка сохранения";
				$arResponse["success"] = "N";
			}
		}else{
			//$arResponse["error"][] = "Заказ уже принят '" . $order["delivery"]["data"]["firstName"] . "'";
			$arResponse["error"][] = "Ошибка. Статус заказа '" . $order["status"] . "'. Курьер - '" . $order["delivery"]["data"]["firstName"] . "'";
			$arResponse["success"] = "N";
		}
	}elseif($res["statusCode"] == "400" && $res["response"]["errorMsg"]){
		$arResponse["error"][] = $res["response"]["errorMsg"];
		$arResponse["success"] = "N";
	}else{
		$arResponse["error"][] = "Не предвиденная ошибка";
		$arResponse["success"] = "N";
	}
//prent($arResponse);

}else{
	$arResponse["error"][] = "Не предвиденная ошибка. Обновите страницу и попробуйте повторить";
	$arResponse["success"] = "N";
}
if(is_array($arResponse["error"]) && count($arResponse["error"]) > 0){
	$arLog = array(
		"date" => date("Y-m-d H:i:s"),
		"_POST" => $_POST,
		"arOrder" => $arOrder,
		"res" => $res,
		"res2" => $res2,
		"arResponse" => $arResponse,
		"file" => "accept_order.php",
	);
	ob_start();
	print_r($arLog);
	$content = ob_get_contents();
	ob_end_clean();
	$f = "/var/www/bitrix_logs/courier/" . date("Y_m_d") . ".txt";
	file_put_contents($f, $content, 8);
}
header('Content-Type: application/json;charset=UTF-8');
echo json_encode($arResponse);


require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_after.php');
