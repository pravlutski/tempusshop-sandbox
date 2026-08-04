<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
$pricelist_id = intval($_POST["id"]);
if(CModule::IncludeModule("panel.manager") && $pricelist_id > 0){
	$objBrand = new CPanelBrand;
	$objSupplier = new CPanelSupplier;
	$objCurrency = new CPanelCurrency;
	
	$strSql = "SELECT log FROM ci_pricelist WHERE id = '{$pricelist_id}'";
	$results = $DB->Query($strSql, false, $err_mess.__LINE__);?>
	<?if ($row = $results->Fetch()):?>
	<div class="modal-header" style="max-height: 300px;overflow: scroll;">
		<?=$row["log"]?>
	</div>
	<?else:?>
	<div class="modal-header">
		Прайслист не найден
	</div>
	<?endif?>

	<?

}else{
	?>
	<p>Произошла непредвиденная ошибка. Пожалуйста, обновите страницу и попробуйте позже</p>
	<?
}
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_after.php');