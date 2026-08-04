<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');?>
<?
global $DB;
//if(isset($_POST["website"]) && in_array($_POST["website"], array("s1", "s2")))
//	$psFilter["website"] = $_POST["website"];
//prent($psFilter);
//prent($_POST);die;
?>
<div class="row">
<?/*if(!in_array($psFilter["website"], array("s1", "s2"))):?>
	<p>Выберите сайт</p>
	<?die;?>
<?endif*/?>
<?
if(CModule::IncludeModule("panel.manager") && CModule::IncludeModule("iblock") && CModule::IncludeModule("catalog")){
	$arResult = $arr = array();
	$objService = new OrderService;
	$objSupplier = new CPanelSupplier;
	$arResult["SUPPLIER_LIST"] = $objSupplier->getList();
	foreach($arResult["SUPPLIER_LIST"] as $arSup)
		$arResult["SUPPLIER_NAME"][$arSup["id"]] = $arSup["name"];
	$strSql = "SELECT * FROM ci_purchase WHERE active = 'Y'";// AND site_id = '".$psFilter["website"]."'";
	$results = $DB->Query($strSql, false, $err_mess.__LINE__);
	while ($row = $results->Fetch()){
		$arResult["ITEMS"][] = $row;
	}
	
	foreach($arResult["ITEMS"] as $key => &$arItem){
		$arItem["supp_name"] = $arResult["SUPPLIER_NAME"][$arItem["supp_id"]];
		$arItem["item_active"] = "N";
		if($arItem["order_id"] > 0){
			$arFilter = array(
				"ID" => $arItem["order_id"],
			);
			$tmp = $objService->getOrder(array(), $arFilter, array("nTopCount" => 1));
			$arOrder = $tmp[0];
			$arItem["order_status_id"] = $arOrder["STATUS_ID"];
			$arItem["order_canceled"] = $arOrder["CANCELED"];
			
			$tmp = explode(".", $arItem["order_basket_id"]);
			$order_basket_id = $tmp[0];
			//если товар отредактирован и товар удалил, то ставим флаг
			foreach($arOrder["BASKET"] as $k => $v){
				if($v["ID"] == $order_basket_id)
					$arItem["item_active"] = "Y";
			}
		}
		//prent($arOrder);prent($arItem);
	}
	//prent($arResult["ITEMS"]);
	unset($arItem);
	$arSort = array();
	foreach($arResult["ITEMS"] as $key => $arItem){
//		$arSort[$arItem["supp_id"]] = $arItem["supp_id"];
		$arSort[$arItem["supp_id"]] = $arResult["SUPPLIER_NAME"][$arItem["supp_id"]];
		$arResult["PRICE_GROUP"][$arItem["supp_id"]][] = $arItem;
	}
	//sort($arSort);
	asort($arSort);
	$tmp = array();
	foreach($arSort as $key => $val){
		//$tmp[$val] = $arResult["PRICE_GROUP"][$val];
		$tmp[$key] = $arResult["PRICE_GROUP"][$key];
	}
	$arResult["PRICE_GROUP"] = $tmp;
	
	foreach($arResult["PRICE_GROUP"] as $key => $arItem){
		foreach($arItem as $k => $v){
			$arResult["PRICE_GROUP_SUM"][$key] += $v["price"];
		}
	}
	
//	prent($arResult["PRICE_GROUP"]);

//	prent($arResult["PRICE_GROUP"]);
	foreach($arResult["PRICE_GROUP"] as $key => $arItem):?>
		<div class="col-sm-12">
			<table class="table">
				<thead>
					<tr> 
						<th style="width: 40%"><?=$arResult["SUPPLIER_NAME"][$key]?><span class="badge" style="margin: 0 0 0 5px;"><?=count($arItem)?></span></th>
						<th style="width: 25%">Цена (<?=$arResult["PRICE_GROUP_SUM"][$key]?>)</th>
						<th style="width: 10%"></th>
						<th style="width: 25%"></th>
					</tr>
				</thead>
				<tbody>
				<?foreach($arItem as $article => $arPrice):?>
						<tr class="<?if($arPrice["order_status_id"] != "N"):?>warning<?elseif($arPrice["order_canceled"] == "Y" || $arPrice["item_active"] == "N"):?>danger<?endif?>" data-orderbasketid="<?=$arPrice["order_basket_id"]?>">
							<td><?=$arPrice["model"]?></td>
							<td><?=number_format($arPrice["price"], 2, ',', ' ')?></td>
							<td><?=$arPrice["site_id"]?></td>
							<td class="right">
								<button type="button" class="btn btn-danger delete-purchase" data-id="<?=$arPrice["id"]?>">Удалить</button> 
							</td>
						</tr>
				<?endforeach?>
				</tbody>
			</table>
		</div>
	<?endforeach?>
	<?
	//prent($price);

}else{
	?>
	<h2 class="color"><span>Не удалось получить список моделей(</span></h2>
	<p>Произошла непредвиденная ошибка. Пожалуйста, обновите страницу и попробуйте позже</p>
	<?
}
?>
</div>
<?
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_after.php');