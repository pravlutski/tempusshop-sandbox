<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');?>
<?
global $DB;
global $USER;
$id = intval($_POST["id"]);
$order_id = intval($_POST["order_id"]);
$website = false;
if(isset($_POST["website"]) && in_array($_POST["website"], array("s1", "s2", "s3")))
	$website = $_POST["website"];

?>
<?
function deleteDocMS($productID = 0, $ms_data = [], $type = "supply"){
	if(!$ms_data["cabinet"] || !$ms_data["id"] || $productID <= 0) return false;
	global $DB;
	//file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/ajax/purchase/asd.txt", print_r($ms_data,true), FILE_APPEND);
	$result = false;
	$ms = new MoyskladAPI($ms_data["cabinet"]);
		
	$strSql = "SELECT MS_ID FROM ci_ms_assortment WHERE BX_ID = '{$productID}' AND SITE_ID = '{$ms_data["cabinet"]}'";
	$results = $DB->Query($strSql, false, $err_mess.__LINE__);
	if ($row = $results->Fetch()){
		$msAssortmentID = $row["MS_ID"];
	}

	$resPos = $ms->send("/entity/{$type}/{$ms_data["id"]}/positions", "GET");
//file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/ajax/purchase/asd.txt", "resPos " . print_r($resPos,true), FILE_APPEND);
//file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/ajax/purchase/asd.txt", "msAssortmentID " . print_r($msAssortmentID,true), FILE_APPEND);
	$msPositionID = false;
	$canEdit = false;
	foreach($resPos["rows"] as $k => $v){
		$assortment_id = end(explode("/", $v["assortment"]["meta"]["href"]));
				
		if($msAssortmentID == $assortment_id){
			$msPositionID = $v["id"];
						
			if($v["quantity"] > 1){
				$canEdit = true;
				$arEdit = $v;
			}
			break;
		}
	}
			
	if($msPositionID){
		if($canEdit === true){
					
			$newQuantity = $arEdit["quantity"] - 1;
			$newPrice = ($arEdit["price"] / $arEdit["quantity"]) * $newQuantity;
							
			$data = [
				"quantity" => (float)($arEdit["quantity"] - 1),
				"price" => (float)$arEdit["price"],
			];

			$resEdit = $ms->send("/entity/{$type}/{$ms_data["id"]}/positions/{$msPositionID}", "PUT", $data, ["Content-Type" => "application/json"]);
//file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/ajax/purchase/asd.txt", "resEdit" . print_r($resEdit,true), FILE_APPEND);
			if($resEdit["quantity"] == $newQuantity){
				$result = true;
			}
					
		}else{
			$r = $ms->send("/entity/{$type}/{$ms_data["id"]}/positions/{$msPositionID}", "DELETE");
//file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/ajax/purchase/asd.txt", "DELETE {$type}" . print_r($r,true), FILE_APPEND);
//file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/ajax/purchase/asd.txt", "DELETE LAST_STATUS_CODE" . print_r($ms->LAST_STATUS_CODE,true), FILE_APPEND);
		}
		if($ms->LAST_STATUS_CODE == 200){
			$result = true;
		}
				
	}elseif(isset($resPos["rows"]) && !$resPos["rows"]){
		$result = true;
	}
			
	return $result;
}

if(CModule::IncludeModule("panel.manager") && $id > 0){
	$ar = false;
	$strSql = "SELECT * FROM ci_purchase WHERE id = {$id} AND active = 'Y' LIMIT 1";
	$results = $DB->Query($strSql, false, $err_mess.__LINE__);
	if ($row = $results->Fetch()){
		$ar = $row;
	}
	if($ar){
		$flgDelete = true;
		$ms_data = unserialize($ar["ms_data"]);
		// удаляем документы
		foreach($ms_data as $type => $arMS){
			if($type == "supply_transfer") $type = "supply";
			deleteDocMS($ar["product_id"], $arMS, $type);
		}
		
		/*
		if($ms_data["supply"]["id"]){
			$flgDelete = false;
			$ms = new MoyskladAPI($ms_data["supply"]["cabinet"]);
		
			$strSql = "SELECT MS_ID FROM ci_ms_assortment WHERE BX_ID = '{$ar["product_id"]}' AND SITE_ID = '{$ms_data["supply"]["cabinet"]}'";
			$results = $DB->Query($strSql, false, $err_mess.__LINE__);
			if ($row = $results->Fetch()){
				$msAssortmentID = $row["MS_ID"];
			}

			$resPos = $ms->send("/entity/supply/{$ms_data["supply"]["id"]}/positions", "GET");
			
			$msPositionID = false;
			$canEdit = false;
			foreach($resPos["rows"] as $k => $v){
				$assortment_id = end(explode("/", $v["assortment"]["meta"]["href"]));
				
				if($msAssortmentID == $assortment_id){
					$msPositionID = $v["id"];
					
					if($v["quantity"] > 1){
						$canEdit = true;
						$arEdit = $v;
					}
					break;
				}
			}
			
			if($msPositionID){
				if($canEdit === true){
					
					$newQuantity = $arEdit["quantity"] - 1;
					$newPrice = ($arEdit["price"] / $arEdit["quantity"]) * $newQuantity;
					
					$data = [
						"quantity" => (float)($arEdit["quantity"] - 1),
						"price" => (float)$arEdit["price"],
					];

					$resEdit = $ms->send("/entity/supply/{$ms_data["supply"]["id"]}/positions/{$msPositionID}", "PUT", $data, ["Content-Type" => "application/json"]);
					
					if($resEdit["quantity"] == $newQuantity){
						$flgDelete = true;
					}
					
				}else{
					$ms->send("/entity/supply/{$ms_data["supply"]["id"]}/positions/{$msPositionID}", "DELETE");
				}
				if($ms->LAST_STATUS_CODE == 200){
					$flgDelete = true;
				}
				
			}elseif(isset($resPos["rows"]) && !$resPos["rows"]){
				$flgDelete = true;
			}
			
		}
		// удалям возврат
		if($ms_data["purchasereturn"]["id"]){
			$flgDelete = false;
			$ms = new MoyskladAPI($ms_data["purchasereturn"]["cabinet"]);
		
			$strSql = "SELECT MS_ID FROM ci_ms_assortment WHERE BX_ID = '{$ar["product_id"]}' AND SITE_ID = '{$ms_data["purchasereturn"]["cabinet"]}'";
			$results = $DB->Query($strSql, false, $err_mess.__LINE__);
			if ($row = $results->Fetch()){
				$msAssortmentID = $row["MS_ID"];
			}

			$resPos = $ms->send("/entity/purchasereturn/{$ms_data["purchasereturn"]["id"]}/positions", "GET");
			
			$msPositionID = false;
			$canEdit = false;
			foreach($resPos["rows"] as $k => $v){
				$assortment_id = end(explode("/", $v["assortment"]["meta"]["href"]));
				
				if($msAssortmentID == $assortment_id){
					$msPositionID = $v["id"];
					
					if($v["quantity"] > 1){
						$canEdit = true;
						$arEdit = $v;
					}
					break;
				}
			}
			
			if($msPositionID){
				if($canEdit === true){
					
					$newQuantity = $arEdit["quantity"] - 1;
					$newPrice = ($arEdit["price"] / $arEdit["quantity"]) * $newQuantity;
					
					$data = [
						"quantity" => (float)($arEdit["quantity"] - 1),
						"price" => (float)$arEdit["price"],
					];

					$resEdit = $ms->send("/entity/supply/{$ms_data["purchasereturn"]["id"]}/positions/{$msPositionID}", "PUT", $data, ["Content-Type" => "application/json"]);
					
					if($resEdit["quantity"] == $newQuantity){
						$flgDelete = true;
					}
					
				}else{
					$ms->send("/entity/supply/{$ms_data["purchasereturn"]["id"]}/positions/{$msPositionID}", "DELETE");
				}
				if($ms->LAST_STATUS_CODE == 200){
					$flgDelete = true;
				}
				
			}elseif(isset($resPos["rows"]) && !$resPos["rows"]){
				$flgDelete = true;
			}
			
		}
		*/
		$flgDelete = true;
//		$ID = $DB->Insert("ci_purchase", $in, $err_mess.__LINE__);
//		$DB->Query("DELETE from ci_purchase WHERE id = '".$id."'", false, $err_mess.__LINE__);
		if($flgDelete){
			$DB->Update("ci_purchase", array("active" => "'N'", "status" => "'D'", "ms_need_sync" => "'Y'"), "WHERE id='".$id."'", $err_mess.__LINE__);
			$res = array(
				'status' => "ok",
				'text' => "Запись удалена",
			);
		}else{
			$res = array(
				'status' => "error",
				'text' => "Не удалось отменить в MS"
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
		'text' => "Не удалось сохранить. Не корректные данные"
	);
}
echo json_encode($res, JSON_UNESCAPED_UNICODE);

header('Content-Type: application/json;charset=UTF-8');
die();
?>