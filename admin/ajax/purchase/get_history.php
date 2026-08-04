<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');?>
<?
if(isset($_POST["search_text"]))
	$search_text = trim($_POST["search_text"]);
if(strlen($search_text) <= 3){
	die;
}
//prent($psFilter);die;
?>

<?
if(CModule::IncludeModule("panel.manager")){

	$objSupplier = new CPanelSupplier;

	$arResult["SUPPLIER_LIST"] = $objSupplier->getList();
	foreach($arResult["SUPPLIER_LIST"] as $arSup)
		$arResult["SUPPLIER_NAME"][$arSup["id"]] = $arSup["name"];
		

	$strSql = "SELECT * FROM ci_purchase WHERE active = 'N' AND model LIKE '%".addslashes($search_text)."%' ORDER BY timestamp desc LIMIT 0,500";
//prent($strSql);
	$results = $DB->Query($strSql, false, $err_mess.__LINE__);
	while ($row = $results->Fetch()){
		$arResult["ITEMS"][] = $row;
	}
	?>
	<?if(is_array($arResult["ITEMS"]) && count($arResult["ITEMS"]) > 0):?>
			<?foreach($arResult["ITEMS"] as $key => $arItem):?>
				<tr>
					<td><?=$arItem["model"]?></td>
					<td><?=$arItem["site_id"]?></td>
					<td style="display: none;"><?if($arItem["status"] == "N" && $arItem["tmp_order_id"] > 0):?><?=$arItem["tmp_order_id"]?><?elseif($arItem["status"] == "T"):?>ТОП<?endif?></td>
					<td><?=$arItem["price"]?></td>
					<td><?=$arResult["SUPPLIER_NAME"][$arItem["supp_id"]]?></td>
					<td><?=$arItem["timestamp"]?></td>
				</tr>
			<?endforeach?>
	<?endif?>
	
	<?
	//prent($price);

}else{
	?>
	<h2 class="color"><span>Не удалось получить список моделей(</span></h2>
	<p>Произошла непредвиденная ошибка. Пожалуйста, обновите страницу и попробуйте позже</p>
	<?
}
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_after.php');