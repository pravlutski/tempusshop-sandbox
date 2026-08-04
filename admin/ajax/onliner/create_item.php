<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
if($_SERVER['HTTP_X_REQUESTED_WITH'] != 'XMLHttpRequest' || !CModule::IncludeModule('iblock') || !CModule::IncludeModule('main')) return;
global $DB;
$product_id = intval($_POST["product_id"]);
$onliner_id = intval($_POST["onliner_id"]);
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $product_id > 0 && $onliner_id > 0) {
	$flg_add = false;
	$rs = CIBlockElement::GetList(array(), array("IBLOCK_ID" => CProSet::IB_CATALOG, "ID" => $product_id), false, false, array("ID", "IBLOCK_ID"));
	$arBrand = array();
	if ($ar = $rs->GetNext()){
	
		$strSql = "SELECT * FROM ci_onliner_articles WHERE id = '" . $onliner_id . "'";
		
		$resDB = $DB->Query($strSql, false, $err_mess.__LINE__);
		if($row = $resDB->Fetch()){
//			CIBlockElement::SetPropertyValueCode($ar["ID"], "MODEL_ONLINER", $row["article"]);
//			CIBlockElement::SetPropertyValueCode($ar["ID"], "BRAND_ONLINER", $row["vendor"]);
			$arProp = [];
			if($row["article"])
				$arProp["ARTICLE_ONLINER"] = $row["article"];
			
			if($row["name"])
				$arProp["MODEL_ONLINER"] = $row["name"];
			
			if($row["vendor"])
				$arProp["BRAND_ONLINER"] = $row["vendor"];
			
			CIBlockElement::SetPropertyValuesEx($ar["ID"], CProSet::IB_CATALOG, $arProp);
			
/*			$el = new CIBlockElement;
			$arLoadProductArray = Array(
				"ACTIVE"	=> "Y",
			);
			$rs = $el->Update($ar["ID"], $arLoadProductArray);*/
			$res["data"] = "Товар с кодом {$product_id} обновлен";// {$ar["ID"]} - {$row["vendor"]}
			$res["status"] = "ok";
		}else{
			$res["data"] = "Товар с кодом {$product_id} обновить не удалось";
			$res["status"] = "error";
		}

		
	}else{
		$res["status"] = "error";
		$res["data"] = "Товар с кодом {$product_id} не найден";
	}
	
}else{
	$res["status"] = "error";
	$res["data"] = "Не корректный запрос";
}

echo json_encode($res, JSON_UNESCAPED_UNICODE);
header('Content-Type: application/json;charset=UTF-8');
die();
