<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
use Bitrix\Main\Loader;

include_once($_SERVER['DOCUMENT_ROOT'] . "/bitrix/components/adm/order.assembly/ozon_tools.php");

$ozon = new OzonTools();
/*
$data = array(
	"company_id" => "211514",
	"filter" => [
		"status_alias" => ["awaiting_deliver", "awaiting_registration"],
		"cutoff_from" => "2023-09-23T21:00:00Z",
		"cutoff_to" => "2024-02-21T20:59:59Z",
		"delivery_schema" => "fbs",
		"cutoff_to" => "2024-02-21T20:59:59Z",
		"company_id" => "211514",
		
	],
	
	"lang" => "RU",
);

$ozon->changeApiUrl("https://seller.ozon.ru");

$cookie = "__Secure-access-token=3.66938841.FLc2kDC5RLKrmkL8UPIIaA.9.l8cMBQAAAABlX6UDAAAAAKN3ZWKsMzc1MjkxMTkyOTUyAICQoA.20210506123936.20231123211619.CgK5Mj_j3n3PzDljPzJsiO6sOzylgKEHnnPMCqfbOTM";

$header = [
	"Cookie: " . $cookie,
	"X-O3-Company-Id: 211514"
];

$data = [
	"status_alias" => ["awaiting_packaging","awaiting_deliver","awaiting_registration","sent_by_seller","not_accepted","client_arbitration","arbitration","delivering","driver_pickup"],
	"processed_at_from" => date("Y-m-d\T21:00:00\Z", strtotime("-3 months")),
	"processed_at_to" => date("Y-m-d\T21:00:00\Z", time()),
	"company_id" => 211514
];

$res = $ozon->send(action: "/api/posting-service/v2/fbs/posting/count/by-status-alias", method: "POST", data: $data, header: $header);
prent($res);
*/

$res = $ozon->getCountOrders();prent($res);