<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');?>
<?
use Bitrix\Main\Loader;
use Bitrix\Sale;

Loader::includeModule('sale');

$barcode = intval($_POST["barcode"]);
$find_order = $_POST["find_order"] ?? false;
?>
<?
if(CModule::IncludeModule("panel.manager") && $barcode > 0){
	//$utils = new CPanelUtils();
	
	//$article = $utils->getArtnumber($barcode);
	global $DB;
	$strSql = "SELECT * FROM ci_catalog_barcode WHERE BARCODE = '".$barcode."'";
	$results = $DB->Query($strSql, false, $err_mess.__LINE__);
	if ($row = $results->Fetch()){
		$article = $row["ARTICLE"];
		$productID = $row["PRODUCT_ID"];
	}
		

	if (!$find_order) {
		if ($article) {
			$res = array(
				'status' => "ok",
				'article' => $article,
			);
		} else {
			$res = array(
				'status' => "error",
			);
		}

	} else {
		if ($productID) {
			$res = array(
				'status' => "ok",
				'article' => $article,
				'productID' => $productID,
				'orders' => [],
			);

			$statuses = ['SE', 'CL'];
			$statuses = ['F'];
			$tradingPlatformsSkip = ['wb', 'ozon'];
			
			$strSql = "SELECT DISTINCT 
				o.ID AS ORDER_ID,
				o.ACCOUNT_NUMBER AS ACCOUNT_NUMBER,
				o.LID AS LID,
				o.DATE_INSERT,
				o.PRICE,
				o.STATUS_ID,
				tp.CODE AS TRADING_PLATFORM,
				tp.ID AS TRADING_PLATFORM_ID
			FROM 
				b_sale_order o
				INNER JOIN b_sale_basket b ON b.ORDER_ID = o.ID
				INNER JOIN b_sale_tp_order tpo ON tpo.ORDER_ID = o.ID
				INNER JOIN b_sale_tp tp ON tp.ID = tpo.TRADING_PLATFORM_ID
			WHERE 
				b.PRODUCT_ID IN ({$productID})
				AND o.STATUS_ID IN ('F')
				AND tp.CODE NOT IN ('yandex', 'ozon')
				AND o.CANCELED != 'Y'
			ORDER BY 
				o.DATE_INSERT DESC";
			$results = $DB->Query($strSql, false, $err_mess.__LINE__);
			while ($row = $results->Fetch()){
				if ($row['TRADING_PLATFORM'] == "sites" && $row['LID'] == "s2") continue;
				$res['orders'][] = [
					'ID' => $row['ORDER_ID'],
					'TRADING_PLATFORM' => $row['TRADING_PLATFORM'],
				];
			}
			// Получаем заказы
			/*$orders = Sale\Order::getList([
				'filter' => [
					'=BASKET.PRODUCT_ID' => $productID,
					'=STATUS_ID' => $statuses,
					'!TRADING_PLATFORM.CODE' => $tradingPlatformsSkip,
				],
				'select' => ['ID', 'TRADING_PLATFORM'],
				'order' => ['DATE_INSERT' => 'DESC'],
				'runtime' => [
					new \Bitrix\Main\Entity\ReferenceField(
						'TRADING_PLATFORM',
						'\Bitrix\Sale\TradingPlatformTable',
						['=this.ID' => 'ref.ORDER_ID'],
						['join_type' => 'inner']
					)
				]
			]);

			while ($order = $orders->fetch()) {
				$res['orders'][] = [
					'ID' => $order['ID'],
					'TRADING_PLATFORM' => $order['TRADING_PLATFORM'],
				];
			}*/
		}

	}
}else{
	$res = array(
		'status' => 'error',
		'text' => "Не удалось сохранить"
	);
}

//AddMessage2Log($txt);
echo json_encode($res, JSON_UNESCAPED_UNICODE);

header('Content-Type: application/json;charset=UTF-8');
die();