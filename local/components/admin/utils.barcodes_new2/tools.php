<?
require_once($_SERVER['DOCUMENT_ROOT'] . "/local/classes/OzonAPI.php");

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
				$arResult["ERROR"][] = "<p style='color:red'>{$posting_number}. Ошибка при переводе статуса. Ошибка не определена</p>";
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
function setStatusWB($arOrder, $cabinet = "DEFAULT"){

	if(!is_array($arOrder) || count($arOrder) <= 0) return false;
	if(!CModule::IncludeModule("maxyss.wb")) return false;

	$arResult = [
		"ITEMS" => [],
		"ERRORS" => [],
	];
	
	if ( $cabinet == 'DEFAULT' ){
		$cabinet = 'TL';
	}

	$arStickerID = [];
	foreach($arOrder as $id){
		$arStickerID[] = intval($id);
	}
	
	global $DB;
	$strSql = "SELECT * FROM wdhs_wb_main_settings WHERE cabinet = '{$cabinet}'";
	$res = $DB->Query($strSql);
  $arSettings['AUTHORIZATION'] = $res->Fetch()['api'];
//$arOrder = array(185576637);

	$width = 58;
	$height = 40;
	$path = '/api/v3/orders/stickers?type=' . str_replace('.', '', FILE_TYPE_STIKER) . '&width=' . $width . '&height=' . $height;

	$arChunks = array_chunk($arStickerID, 100);
	foreach ($arChunks as $ids_stiker_get) {
		
		$data_string = \Bitrix\Main\Web\Json::encode(array('orders' => $ids_stiker_get));
		$api = new RestClient([
			'base_url' => "https://marketplace-api.wildberries.ru",
			'curl_options' => array(
				CURLOPT_SSL_VERIFYPEER => false,
				CURLOPT_SSL_VERIFYHOST => false,
				CURLOPT_POSTFIELDS => $data_string,
				CURLOPT_HEADER => TRUE,
				CURLOPT_CUSTOMREQUEST => 'POST',
				CURLOPT_HTTPHEADER => array(
					'Content-Type: application/json',
					'Content-Length: ' . strlen($data_string),
					'Authorization: ' . $arSettings["AUTHORIZATION"],
				)
			)
		]);
		$str_result = $api->post($path, []);
		//prent($str_result);prent($path);
		if ($str_result->info->http_code == 200 && strlen($str_result->response) > 0) {
			$res_stickers = \Bitrix\Main\Web\Json::decode($str_result->response);
			//prent($res_stickers);
			foreach($res_stickers["stickers"] as $key => $arItem){
				$arResult["ITEMS"][$arItem["orderId"]] = array(
					"ORDER_ID_WB" => $arItem["orderId"],
					"STICKER_PART_A" => $arItem["partA"],
					"STICKER_PART_B" => $arItem["partB"],
					"STICKER_ENCODING" => $arItem["barcode"],
					"BARCODE" => $arItem["barcode"],
				);

				if(filesize($_SERVER["DOCUMENT_ROOT"] . "/upload/wb/{$arItem["orderId"]}.svg") < 10){
					$image = base64_decode($arItem["file"]);
					//prent($arItem);die;
					$FPName = $arItem["orderId"] . '.svg';
					$FPPath = $_SERVER["DOCUMENT_ROOT"] . '/upload/wb/' . $FPName;
					file_put_contents($FPPath, $image, LOCK_EX);

					//prent($arItem["wbStickerSvgBase64"]);
				} else {
					$arResult["ERRORS"][] = "WB. {$arItem["orderId"]} ошибка";
				}

			}
			
			//prent(count($res_stickers["stickers"]));
			//prent(count($ids_stiker_get));
			if (is_array($res_stickers["stickers"]) && count($res_stickers["stickers"]) != count($ids_stiker_get)) {
				$arderIDs = array_column($res_stickers["stickers"], 'orderId');
			//prent($arderIDs);
				foreach ($ids_stiker_get as $order_id) {
					if (!in_array($order_id, $arderIDs)) {
						$arResult["ERRORS"][] = "WB. Не получены для {$order_id}";
					}
				}
			}
		} else {
			$arResult["ERRORS"][] = "WB. Ошибка api. Не получены для " . serialize($ids_stiker_get);
		}
		//prent($arResult["ITEMS"],0,1); die;
		//return $result;
	}

	return $arResult["ITEMS"];
}
?>