<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');?>
<?
global $DB;
global $USER;
$id = intval($_POST["id"]);
$order_id = intval($_POST["order_id"]);
$tmp = explode(".", $_POST["order_basket_id"]);
$order_basket_id = $tmp[0];
$order_basket_cnt = $tmp[1];

$product_id = intval($_POST["product_id"]);
?>
<?
if(CModule::IncludeModule("panel.manager") && $id > 0 && $order_id > 0 && $order_basket_id > 0 && $order_basket_cnt > 0){
	$objService = new OrderService;

	$objCurrency = new CPanelCurrency;
	$arCurrency = $objCurrency->getList();

	$ar = false;
	$strSql = "SELECT * FROM ci_price WHERE id = '".$DB->ForSql($id)."' LIMIT 1";
	$results = $DB->Query($strSql, false, $err_mess.__LINE__);
	if ($row = $results->Fetch()){
		$ar = $row;
		if ($row["currency"] == 'RUB') {
			$newPriceCheck = $row["priceСurrency"];
		} else {
			$newPriceCheck = $row["priceСurrency"] * $arCurrency[$row["currency"]]["rate"];
		}
		$ar['NEWPRICECHECK'] = $newPriceCheck;

		// if ( !empty($row['discPrice']) && $row['discPrice'] > 0 ){
		// 	$discount = intval( $row['discPrice'] );
		// 	$ar['NEWPRICECHECK'] = $ar['NEWPRICECHECK'] / (1 - $discount / 100); // Восстанавливаем цену до скидки ДЦ
		// 	if ( $row['model'] == 'GBD-200LM-1E' ){
		// 		$test = [
		// 			'model' => $row['model'],
		// 			'price' => $row['price'],
		// 			'discPrice' => $row['discPrice'],
		// 			'discount' => $discount,
		// 			'arNewPriceCheck' => $newPriceCheck,
		// 			'restored' =>$ar['NEWPRICECHECK'],
		// 		];
		// 		file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/admin/ajax/purchase/restoreCheck.txt', print_r($test, true) );
		// 	}
		// }
	}
	if($ar){
		$flg = false;
		$str = $order_basket_id . "." . $order_basket_cnt;
		$strSql = "SELECT * FROM ci_purchase WHERE order_basket_id = '".$DB->ForSql($str)."' AND active = 'Y'";
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		if ($row = $results->Fetch()){
			$flg = true;
		}
		if($flg === false){
			$arFilter = array(
				"ID" => $order_id,
			);
			$tmp = $objService->getOrder(array(), $arFilter, array("nTopCount" => 1));
			if($tmp)
				$arOrder = $tmp[0];
			if($arOrder){
				if($arOrder["CANCELED"] != "Y"){
					$flg = false;
					//ищем товар в заказе. вдруг был удален
					foreach($arOrder["BASKET"] as $key => $basket)
						if($basket["ID"] == $order_basket_id)
							$flg = true;
					if($flg === true){
						$in = array(
							"active" => "'Y'",
							"site_id" => "'".$arOrder["LID"]."'",
							"model" => "'".addslashes($ar["model"])."'",
							"order_id" => $order_id,
							"order_basket_id" => $order_basket_id . "." . $order_basket_cnt,//$order_basket_id,
							"user_id" => $USER->getID(),
							//"price" => "'".$ar["price"]."'",
							"price" => "'".$ar["NEWPRICECHECK"]."'",
							"status" => "'N'",
							"supp_id" => $ar["supplier_id"],
							"product_id" => $product_id,
						);

						$ID = $DB->Insert("ci_purchase", $in, $err_mess.__LINE__);
						$res = array(
							'status' => ($ID > 0 ? "ok" : "error"),
							'text' => ($ID > 0 ? "Запись добавлена" : "Не удалось сохранить"),
							'asd' => $in
						);
					}else{
						$res = array(
							'status' => "error",
							'text' => "Товар в заказе не найден"
						);
					}
				}else{
					$res = array(
						'status' => "error",
						'text' => "Заказ отменен"
					);
				}
			}else{
				$res = array(
					'status' => "error",
					'text' => "Заказ не найден"
				);
			}
		}else{
			$res = array(
				'status' => "error",
				'text' => "Закупка по данному заказу уже создана"
			);
		}
	}else{
		$res = array(
			'status' => "error",
			'text' => "Не найдена запись в прайслисте. Обновите страницу."
		);
	}

}else{
	$res = array(
		'status' => 'error',
		'text' => "Не удалось сохранить. Некорректные данные"
	);
}
echo json_encode($res, JSON_UNESCAPED_UNICODE);

header('Content-Type: application/json;charset=UTF-8');
die();
?>
