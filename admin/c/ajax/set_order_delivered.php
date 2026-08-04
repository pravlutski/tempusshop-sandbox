<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');

if(!CModule::IncludeModule("crm_courier") || !CModule::IncludeModule("panel.manager")) return;

$orderID = intval($_POST["order_id"]);
global $USER;
$arLog = [
	"date" => date("Y-m-d H:i:s"),
	"USER" => $USER->getID(),
	"_POST" => $POST,
];
file_put_contents("/var/www/bitrix_logs/courier/set_order_delivered.txt", print_r($arLog, true), 8);
if($orderID > 0){

	$objCourier = new CCourier();
	
	$arFilter = array(
		"ids" => array($orderID),
		"extendedStatus" => array("delivering"),
		"couriers" => array($objCourier->courierID),
	);

	$res = $objCourier->getOrderCrm($arFilter);
	//prent($res);
	if($res["statusCode"] == "200" && is_array($res["response"])){
		$order = $res["response"]["orders"][0];
		$arFields = array(
			"USER_ID"       		=> CCourierDelivery::GetUserID(),
			"COURIER_ID"			=> $objCourier->courierID,
//			"WORHSHIFT_ID"			=> CWorkshift::getWorkshiftID($objCourier->courierID),
			"ORDER_ID"  			=> $order["externalId"],
			"ORDER_NUMBER" 			=> $order["number"],
			"PRICE_ORDER"  			=> (float)$_POST["price_order"],
			"PRICE_DELIVERY"		=> (float)$_POST["price_delivery"],
			"COMMENT"         		=> $_POST["comment"],
			"STATUS" 				=> "delivery",//"delivery",
			"BASKET" 				=> serialize($order["items"]),
			"MANAGER_COMMENT" 		=> $order["managerComment"],
			"BAD_CLIENT"			=> ($_POST["bad_client"] == "Y" ? "Y" : "N"),
		);
		
//AddMessage2Log($order);
//AddMessage2Log($arFields);
		$nosms = ($_POST["bad_client"] == "Y" ? 1 : 0);
		$item_return = ($_POST["item_return"] == "Y" ? true : false);
		
		$change_price = ((float)$_POST["price_order"] == (float)$_POST["price_order_orig"] ? false : true);
		$change_price = true;
		foreach($order["items"] as $key => $arItem){
			if($arItem["offer"]["externalId"] == 135080)
				$change_price = true;
		}
		
		if($item_return === true) {
			$change_price = true;
			$arFields["COMMENT"] = $arFields["COMMENT"] . " ремонт/возврат/обмен";
		}
			
		$arOrder = array(
			"id" => $order["id"],
			"status" => ($change_price === false ? "complete" : "delivededbycourier"),//"delivededbycourier",
			"statusComment" => "Сумма выручки {$arFields["PRICE_ORDER"]} и сумму стоимости доставки {$arFields["PRICE_DELIVERY"]}. " . $arFields["COMMENT"],
			"customFields" => array("nosms" => $nosms),
		);
		
		/******************************************************/
		/*
		$arLog = array(
			"_POST" => $_POST,
			"arOrder" => $arOrder,
		);
		ob_start();
		print_r($arLog);
		$content = ob_get_contents();
		ob_end_clean();
		$f = "/userscripts/logs/courier/" . date("Y_m_d_H_i_s") . ".txt";
		file_put_contents($f, $content); */
		/******************************************************/
		
		$res2 = $objCourier->setOrder($arOrder, "id", $order["site"]);
//AddMessage2Log($res2);
		if($res2["statusCode"] == "200" && $res2["response"]["success"] == true){
			$arResponse["success"] = "Y";

			$st = ($change_price === false ? "F" : "DK");
			//OrderService::setStatusOrder($order["externalId"], "DK");//Доставлен курьером
            OrderService::setStatusOrderD7($order["number"], $st, true);//Выполнен
			
			$rsDelivery = new CCourierDelivery();
			$filter = array(
			//	"USER_ID" 		=> CCourierDelivery::GetUserID(),
			//	"COURIER_ID"	=> $objCourier->courierID,
				"ORDER_ID" 		=> $order["externalId"],
			);
//AddMessage2Log($filter);
//AddMessage2Log($arFields);
			$rsList = $rsDelivery->GetList(false, $filter);
			if(!$rsList) { //  || 1==1
				$nID = $rsDelivery->Add($arFields);
//				AddMessage2Log($nID);
				if($nID > 0){
					
				}else{
//					AddMessage2Log($rsDelivery->LAST_ERROR);
					$arResponse["error"][] = "Запись создать не удалось. " . $rsDelivery->LAST_ERROR;
					$arResponse["success"] = "N";
				}
			}else{
//				AddMessage2Log("Запись по этому заказу уже существует");
				$arResponse["error"][] = "Запись по этому заказу уже существует";
				$arResponse["success"] = "N";
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
//prent($arResponse);
if(is_array($arResponse["error"]) && count($arResponse["error"]) > 0){
	$arLog = array(
		"date" => date("Y-m-d H:i:s"),
		"_POST" => $_POST,
		"arOrder" => $arOrder,
		"res" => $res,
		"res2" => $res2,
		"arResponse" => $arResponse,
		"arFields" => $arFields,
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