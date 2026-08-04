<?require_once($_SERVER['DOCUMENT_ROOT']. "/bitrix/modules/main/include/prolog_before.php");
use Bitrix\Main\Loader;
if(!Loader::includeModule('maxyss.wb'))return;

include_once($_SERVER['DOCUMENT_ROOT'] . "/bitrix/components/adm/order.assembly/wbtools.php");
include_once($_SERVER['DOCUMENT_ROOT'] . "/bitrix/components/adm/order.assembly/ozon_tools.php");

$action = trim(htmlspecialchars($_REQUEST["action"]));
switch($action){
	case "list": // кнопка Загрузить. просто список заказов из списка
		$arParams = [
			"SOURCE" => "list"
		];
		$APPLICATION->IncludeComponent("adm:order.assembly", "list", $arParams, false);
		break;
	case "minsk": // кнопка Минск
		$arParams = [
			"SOURCE" => "minsk"
		];
		$APPLICATION->IncludeComponent("adm:order.assembly", "minsk", $arParams, false);
		break;
	case "wb-new": // кнопка WB "Новые"
		$arParams = [
			"SOURCE" => "wb-new"
		];
		$APPLICATION->IncludeComponent("adm:order.assembly", "wb-new", $arParams, false);
		break;
	case "wb-supplies": // кнопка WB "На сборке"
		$arParams = [
			"SOURCE" => "wb-supplies"
		];
		$APPLICATION->IncludeComponent("adm:order.assembly", "wb-supplies", $arParams, false);
		break;
	case "wb-delivery": // кнопка WB "В доставке"
		$arParams = [
			"SOURCE" => "wb-delivery"
		];
		$APPLICATION->IncludeComponent("adm:order.assembly", "wb-delivery", $arParams, false);
		break;
	case "wb-info": // кол-во заказов. для плашек
		$res = [];
		if(empty($_SESSION["CABINET"])) {
			$_SESSION["CABINET"] = "WR";
		}
		$cabinet_init = $_SESSION["CABINET"];
		$wb = new WBTools($cabinet_init);
		$wb->changeApiUrl("https://marketplace.wildberries.ru");

		$cookie = "x-supplier-id-external={$wb->settings["UUID"]}; WBToken={$wb->WBToken}";
		//$cookie = "x-supplier-id-external={$wb->settings["UUID"]}; WBToken={$wb->settings["AUTHORIZATION"]}";
		$header = [
			"Cookie: " . $cookie
		];
		$resWB = $wb->send(action: "/ns/marketplace-app/marketplace-remote-wh/api/v3/portal/orders/count", header: $header);

		if(isset($resWB["data"]) && is_array($resWB["data"])) $res = $resWB["data"];

		$GLOBALS['APPLICATION']->RestartBuffer();
		echo json_encode($res, JSON_UNESCAPED_UNICODE);

		header('Content-Type: application/json;charset=UTF-8');
		die();
		break;
	case "wb-get-orders": // список заказов
		$res = [];
		$supplie_id = trim(htmlspecialchars($_REQUEST["id"]));

		if(empty($_SESSION["CABINET"])) {
			$_SESSION["CABINET"] = "WR";
		}
		$cabinet_init = $_SESSION["CABINET"];
		$wb = new WBTools($cabinet_init);

		$arItems = $wb->getSupplieItems($supplie_id);
		$res["data"] = array_column($arItems, "ID");
		//$res["orders"] = $arItems;

		$GLOBALS['APPLICATION']->RestartBuffer();
		echo json_encode($res, JSON_UNESCAPED_UNICODE);

		header('Content-Type: application/json;charset=UTF-8');
		die();
		break;
	case "wb-delete-supplie": // Удаляет поставку, если она активна и за ней не закреплено ни одно сборочное задание.
		$res = [];
		$supplie_id = trim(htmlspecialchars($_REQUEST["id"]));

		if(empty($_SESSION["CABINET"])) {
			$_SESSION["CABINET"] = "WR";
		}
		$cabinet_init = $_SESSION["CABINET"];
		$wb = new WBTools($cabinet_init);

		if($supplie_id && strlen($supplie_id) > 0){
			$rs = $wb->send(action: "/api/v3/supplies/{$supplie_id}", method: "DELETE");
			if($rs["status"] == 204){
				$res["status"] = "ok";
			}else{
				$res["status"] = "error";
				$res["data"] = $rs["error"];
			}
		}else{
			$res["status"] = "error";
			$res["data"] = "Не идентифицирован ID поставки";
		}

		$GLOBALS['APPLICATION']->RestartBuffer();
		echo json_encode($res, JSON_UNESCAPED_UNICODE);

		header('Content-Type: application/json;charset=UTF-8');
		die();
		break;
	case "wb-supplie-to-delivery": // Закрывает поставку и переводит все сборочные задания в ней в статус complete ("В доставке")
		$res = [];
		$supplie_id = trim(htmlspecialchars($_REQUEST["id"]));

		if(empty($_SESSION["CABINET"])) {
			$_SESSION["CABINET"] = "WR";
		}
		$cabinet_init = $_SESSION["CABINET"];
		$wb = new WBTools($cabinet_init);

		if($supplie_id && strlen($supplie_id) > 0){
			$rs = $wb->send(action: "/api/v3/supplies/{$supplie_id}/deliver", method: "PATCH");
			if($rs["status"] == 204){
				$res["status"] = "ok";
			}else{
				$res["status"] = "error";
				$res["data"] = $rs["error"];
			}
		}else{
			$res["status"] = "error";
			$res["data"] = "Не идентифицирован ID поставки";
		}

		$GLOBALS['APPLICATION']->RestartBuffer();
		echo json_encode($res, JSON_UNESCAPED_UNICODE);

		header('Content-Type: application/json;charset=UTF-8');
		die();
		break;
	case "ozon-awaiting-package": // Ожидают сборки
		$arParams = [
			"SOURCE" => "ozon-awaiting-package"
		];
		$APPLICATION->IncludeComponent("adm:order.assembly", "ozon-awaiting-package", $arParams, false);
		break;
	case "ozon-awaiting-delivery": // Ожидают отгрузки
		$arParams = [
			"SOURCE" => "ozon-awaiting-delivery"
		];
		$APPLICATION->IncludeComponent("adm:order.assembly", "ozon-awaiting-delivery", $arParams, false);
		break;
	case "ozon-arbitration": // Спорные
		$arParams = [
			"SOURCE" => "ozon-arbitration"
		];
		$APPLICATION->IncludeComponent("adm:order.assembly", "ozon-arbitration", $arParams, false);
		break;
	case "ozon-info": // кол-во заказов. для плашек

		$ozon = new OzonTools();

		$res = $ozon->getCountOrders();


		$GLOBALS['APPLICATION']->RestartBuffer();
		echo json_encode($res, JSON_UNESCAPED_UNICODE);

		header('Content-Type: application/json;charset=UTF-8');
		die();
		break;
	default:
		$arParams = [
			"SOURCE" => "list"
		];
		$APPLICATION->IncludeComponent("adm:order.assembly", "list", $arParams, false);
		break;
}
?>
