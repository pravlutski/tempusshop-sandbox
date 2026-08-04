<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
if($_SERVER['HTTP_X_REQUESTED_WITH'] != 'XMLHttpRequest' || !CModule::IncludeModule('panel.manager')) return;
$id = intval($_POST["id"]);
$objPricelist = new CPanelPricelist;
$objProduct = new CPanelProduct;
$objExchange = new CExchange;
global $DB;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $id > 0) {
	$strSql = "SELECT * FROM ci_price WHERE id = '{$id}'";
	$results = $DB->Query($strSql, false, $err_mess.__LINE__);
	if ($row = $results->Fetch()){
		$model = $row["model"];
		$result = $objPricelist->deleteProduct( $id );
		$objPricelist->updateDateDisappear($model);//обновляем дату когда пропала модель
		if($result === true){
			$b_id = CPanelProduct::findArticle($model);
			if($b_id > 0){
				//$ex = $objExchange->updateProduct($b_id);
				CExchange::updateProduct($b_id);
				
				CPanelPricelist::updateProps([$b_id]);
				
				if($ex === true){
//					$cache_manager = Bitrix\Main\Application::getInstance()->getTaggedCache();
//					$cache_manager->ClearByTag("iblock_id_".CProSet::IB_CATALOG);
				}
			}
			
			CExchange::forceYmarket("s1");
			CExchange::forceYmarket("s2");
			
			$res["status"] = "ok";
			$res["data"] = array("id" => $id);
		}else{
			$res["status"] = "error";
			$res["data"] = "Удалить не удалось";
		}
	}else{
		$res["status"] = "error";
	}

}else{
	$res["status"] = "error";
	$res["data"] = "Не корректный запрос";
}

echo json_encode($res, JSON_UNESCAPED_UNICODE);
header('Content-Type: application/json;charset=UTF-8');
die();
