<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
use Bitrix\Main\Loader;

include_once($_SERVER['DOCUMENT_ROOT'] . "/bitrix/components/adm/order.assembly/ozon_tools.php");

$ozon = new OzonTools();

$arResult["ERROR"] = [];

$step = 1;

// Получаем от клиента номер итерации
$offset = $_REQUEST['offset'];

if(is_array($_REQUEST["order"]))
	$arOrder = array_slice($_REQUEST["order"], $offset, $step);

//$arOrder = ["32394812-0088-1"];
foreach($arOrder as $posting_number){
	$order = $ozon->getOrder($posting_number);
	//prent($order);
	if(is_array($order["result"])){
		$arSend = [];
		foreach($order["result"]["products"] as $k => $v){
			$arSend["packages"]["products"][] = [
				"product_id" => $v["sku"],
				"quantity" => $v["quantity"],
			];
		}
		
		$arSend["posting_number"] = $order["result"]["posting_number"];
		
		//$collect = $ozon->setOrderCollect($arSend);
		if(is_array($collect["result"])){
			
		}else{
			if(is_array($collect["error"])){
				$arResult["ERROR"][] = "<p style='color:red'>{$posting_number}. Ошибка при переводе статуса. {$collect["status"]} - {$collect["message"]}</p>";
			}else{
				$arResult["ERROR"][] = "<p style='color:red'>{$posting_number}. Ошибка при переводе статуса. Ошибка не определена</p>";
			}
		}
	}else{
		if(is_array($order["error"])){
			$arResult["ERROR"][] = "<p style='color:red'>{$posting_number}. Ошибка при запросе деталей резерва. {$order["status"]} - {$order["message"]}</p>";
		}else{
			$arResult["ERROR"][] = "<p style='color:red'>{$posting_number}. Ошибка при запросе деталей резерва. Ошибка не определена</p>";
		}
	}
}

//$arResult["ERROR"][] = "<p style='color:red'>$posting_number</p>";
//$arResult["ERROR"][] = "<p style='color:red'>".serialize($posting_number)."</p>";

// Проверяем, все ли строки обработаны
$offset = $offset + $step;


if ($offset >= $count) {
	$success = 1;
} else {
	$success = round($offset / $count, 2);
}

ob_end_clean();
// И возвращаем клиенту данные (номер итерации и сообщение об окончании работы скрипта)
$output = Array('offset' => $offset, 'success' => $success, 'error' => $arResult["ERROR"]);

echo json_encode($output, JSON_UNESCAPED_UNICODE);

header('Content-Type: application/json;charset=UTF-8');
die();

/*
if(!Loader::includeModule('maxyss.wb'))return;

include_once($_SERVER['DOCUMENT_ROOT'] . "/bitrix/components/adm/order.assembly/wbtools.php");

$wb = new WBTools();

$name = trim(htmlspecialchars($_REQUEST["name"]));

if(strlen($name) >= 1){
	$result = $wb->addSupplies($name);

	$res = array(
		'status' => ($result["error"] == false && strlen($result["supplyId"]) > 0 ? "ok" : "error"),
		'data' => serialize($result)
	);
}else{
	$res = array(
		'status' => "error",
		'data' => "<span class='label label-danger' style='display: block;'>Некорректное имя</span>"
	);
}

echo json_encode($res, JSON_UNESCAPED_UNICODE);

header('Content-Type: application/json;charset=UTF-8');
die();*/