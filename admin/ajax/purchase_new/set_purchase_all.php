<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');?>
<?
global $DB;
global $USER;
if(!CModule::IncludeModule("panel.manager")) return;

?>
<?

$object = json_decode($_POST['items'], true); 
//prent($object);
//$fido = (object)$_POST["items"];
//$arItem = json_decode($_POST["items"]);
foreach($object as $key => &$arItem){
	//$arItem = (array)$arItem;prent($arItem);
	
	$id = intval($arItem["id"]);
	$order_id = intval($arItem["order_id"]);
	$tmp = explode(".", $arItem["order_basket_id"]);
	
	$product_id = intval($arItem["product_id"]);
	
	$order_basket_id = $tmp[0];
	$order_basket_cnt = $tmp[1];
	if($id > 0 && $order_id > 0 && $order_basket_id > 0 && $order_basket_cnt > 0){
		$objService = new OrderService;
		$ar = false;
		$strSql = "SELECT * FROM ci_price WHERE id = '".$DB->ForSql($id)."' LIMIT 1";
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		if ($row = $results->Fetch()){
			$ar = $row;
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
//				$tmp = $objService->getOrder(array(), $arFilter, array("nTopCount" => 1));
				$tmp = $objService->getOrderCache(array(), $arFilter, array("nTopCount" => 1));
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
								"price" => "'".$ar["price"]."'",
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
}
unset($arItem);
echo json_encode($res, JSON_UNESCAPED_UNICODE);

header('Content-Type: application/json;charset=UTF-8');
die();
?>