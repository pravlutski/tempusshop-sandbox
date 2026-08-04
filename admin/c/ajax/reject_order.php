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
file_put_contents("/var/www/bitrix_logs/courier/reject_order.txt", print_r($arLog, true), 8);
if($orderID > 0){

	$objCourier = new CCourier();
	$objDemand = new DemandCourier('s1');

	$arFilter = array(
		"ids" => array($orderID),
		"extendedStatus" => array("delivering"),
		"couriers" => array($objCourier->courierID),
	);

	$res = $objCourier->getOrderCrm($arFilter);

	if($res["statusCode"] == "200" && is_array($res["response"])){
		$order = $res["response"]["orders"][0];

		$resDemand = $objDemand->deleteDemand($order['number']);
		if ( !$resDemand ){
			$errDemand = $objDemand->getLog()['ERROR'][0];
			$arResponse["error"][] = $errDemand;
			$arResponse["success"] = 'N';

			echo json_encode( $arResponse );
			die;
		}

			$arOrder = array(
				"id" => $order["id"],
				"delivery" => array(
					// "data" => array(
					// 	// "courierId" => $objCourier->systemCourierID,
					// 	"courierId" => false,
					// )
				),
				"status" => "ready-to-delivery"
			);

			$res2 = $objCourier->setOrder($arOrder, "id", $order["site"]);

			if($res2["statusCode"] == "200" && $res2["response"]["success"] == true){
				if($res2["response"]["order"]["status"] == "ready-to-delivery" ){
				//&& $res2["response"]["order"]["delivery"]["data"]["courierId"] == $objCourier->systemCourierID

					$arResponse["success"] = "Y";
                    OrderService::setStatusOrderD7($order["number"], "CO", true);//Готов к доставке
				}else{
					$arResponse["error"][] = "Ошибка записи. Установлен курьер '" . $res2["response"]["order"]["delivery"]["data"]["firstName"] . "'";
					$arResponse["error"][] = "Ошибка записи. Статус в ЦРМ не изменился на Готов к доставке ";
					$arResponse["success"] = "N";//prent($res2);
				}
			}elseif($res2["statusCode"] == "400" && $res2["response"]["errorMsg"]){
				$arResponse["error"][] = $res2["response"]["errorMsg"];
				$arResponse["success"] = "N";
			}else{
				$arResponse["error"][] = "Ошибка сохранения";
				$arResponse["success"] = "N";
			}

	}elseif($res["statusCode"] == "400" && $res["response"]["errorMsg"]){
		$arResponse["error"][] = $res["response"]["errorMsg"];
		$arResponse["success"] = "N";
	}else{
		$arResponse["error"][] = "Заказ не найден. Или на заказ установлен другой курьер или статус заказа не 'Выдан курьеру на доставку'";
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
		"arFields" => $arFields,
		"file" => "reject_order.php",
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
