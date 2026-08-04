<?
if (!class_exists('OzonAPI')) {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/local/classes/OzonAPI.php';
}
if (!class_exists('WildberriesAPI')) {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/local/classes/WildberriesAPI.php';
}

function setStatusOzon($posting_number, $cabinet = "IP"){
	$ozon = new OzonAPI($cabinet);
	$logger = new TsLogger("/ozon/setStatusOzon/");
	//$task_id = $ozon->createTaskSticker([$order_number]);
	$order = $ozon->getOrder($posting_number);
	if(is_array($order["result"])){
		$logger->log("LOG", "Получили заказ {$posting_number}.");
		
		$arSend = [
			"packages" => [
				[
					"products" => []
				]
			],
			"posting_number" => $order["result"]["posting_number"]
		];

		foreach($order["result"]["products"] as $k => $v){
			$arSend["packages"][0]["products"][] = [
				"product_id" => (int)$v["sku"],
				"quantity" => (int)$v["quantity"],
			];
		}

		//prent($arSend);
		$logger->log("LOG", "{$posting_number}. Массив заказа", $arSend);
		$collect = $ozon->setOrderCollect($arSend);
		//prent($collect);
		if(is_array($collect["result"])){
			
		}else{
			if(is_array($collect["error"])){
				$arResult["ERROR"][] = "<p style='color:red'>{$posting_number}. Ошибка при переводе статуса. {$collect["status"]} - {$collect["message"]}</p>";
			}else{
				$arResult["ERROR"][] = "<p style='color:red'>{$posting_number}. Ошибка при переводе статуса. {$collect["error"]}</p>";
			}
			$logger->log("ERROR", "{$posting_number}. Ошибка при переводе статуса.", $collect);
		}
	}else{
		$logger->log("ERROR", "Ошибка получения {$posting_number}", $order);
		if(is_array($order["error"])){
			$arResult["ERROR"][] = "<p style='color:red'>{$posting_number}. Ошибка при запросе деталей резерва. {$order["status"]} - {$order["message"]}</p>";
		}else{
			$arResult["ERROR"][] = "<p style='color:red'>{$posting_number}. Ошибка при запросе деталей резерва. Ошибка не определена</p>";
		}
	}
	return $arResult;
}

function setStatusWB($posting_numbers = [], $cabinet = "DEFAULT"){
	$wb = new WildberriesAPI();
	$wb->changeApiUrl("https://marketplace-api.wildberries.ru");
	// /api/v3/supplies/{supplyId}/orders/{orderId}
}
?>