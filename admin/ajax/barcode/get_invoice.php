<?php //require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
if(CModule::IncludeModule("panel.manager")){
	if(in_array($_REQUEST["website"], array("s1","s2"))) $website = $_REQUEST["website"]; else $website = "s1";
	$objMS = new MoyskladAPI($website);

	$org_id = htmlspecialchars($_REQUEST["id"]);

	if($org_id)
		$arResult["MS_INVOICE_ORG"] = $objMS->getInvoiceOrg($org_id);
	//prent($arResult["MS_INVOICE_ORG"]);
	?>
	<select class="form-control select_w" style="margin: 5px 0 0 0;" name="ms_invoice_org" id="ms_invoice_org">
		<option>-- выберите --</option>
		<?foreach($arResult["MS_INVOICE_ORG"]["rows"] as $k => $v):?>
			<option value="<?=$v["id"]?>" <?if($_POST["ms_organization"] == $v["id"]):?>selected<?endif?>><?=($v["bankName"] ? $v["bankName"] : $v["accountNumber"])?></option>
		<?endforeach?>
	</select>

	<?
}else{
	?>
	<p>Произошла непредвиденная ошибка. Пожалуйста, обновите страницу и попробуйте позже</p>
	<?
}
