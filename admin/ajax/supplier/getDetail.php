<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
if($_SERVER['HTTP_X_REQUESTED_WITH'] != 'XMLHttpRequest' || !CModule::IncludeModule('panel.manager')) return;

$objSupplier = new CPanelSupplier;
$objBrand = new CPanelBrand;
$supp_id = intval($_POST["id"]);
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $supp_id > 0) {
	if($supplier = $objSupplier->getDetail( $supp_id )){
		$supplier["settings"] = json_decode( $supplier["settings"], true );
		$supplier["settings_pricelist"] = json_decode( $supplier["settings_pricelist"], true );
		
		if(isset($supplier["settings"]["brand"])){
			foreach($supplier["settings"]["brand"] as &$brand){
				if($res = $objBrand->getDetail( $brand["id"] )){
					$brand["name"] = $res["name"];
				}
			}
			unset($brand);
		}
		//тут с сортировкой херня. пока так.
		$tmp = array();
		$arBrand = $objBrand->getList();
		foreach($arBrand as $key => $arItem){
			if($supplier["settings"]["brand"][$arItem["id"]])
				$tmp[$arItem["id"]] = $supplier["settings"]["brand"][$arItem["id"]];
		}
		
		$tmp = array_values($tmp);
		$supplier["settings"]["brand"] = $tmp;
		
		$res = array(
			'status'	=> "ok",
			'data'		=> $supplier
		);
	}else{
		$res = array(
			'status'	=> "error",
			'data'		=> "Сохранить не удалось"
		);
	}
}else{
	$res["status"] = "error";
	$res["data"] = "Не корректный запрос";
}

echo json_encode($res, JSON_UNESCAPED_UNICODE);
header('Content-Type: application/json;charset=UTF-8');
die();
