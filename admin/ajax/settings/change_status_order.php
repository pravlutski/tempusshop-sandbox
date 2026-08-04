<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');?>
<?
die;
if(CModule::IncludeModule("panel.manager")){
	
	global $DB;
	
	$strSql = "SELECT ord.ID as ORDER_ID, ch.DATA, ch.DATE_CREATE
	FROM b_sale_order ord 
	LEFT JOIN b_sale_order_change ch ON ord.ID=ch.ORDER_ID 
	WHERE ord.LID = 's2' 
	AND ord.STATUS_ID = 'Pr' AND ord.CANCELED = 'N' AND 
	ch.TYPE = 'ORDER_STATUS_CHANGED' AND ch.DATE_CREATE < (CURDATE() - 2) ORDER BY ch.DATE_CREATE ASC";
	$results = $DB->Query($strSql, false, $err_mess.__LINE__);
	while ($row = $results->Fetch()){

		$arResult["ORDER"][$row["ORDER_ID"]] = array(
			"ORDER_ID" => $row["ORDER_ID"],
			"DATA" => unserialize($row["DATA"]),
			"DATE_CREATE" => $row["DATE_CREATE"],
		);
	}

	foreach($arResult["ORDER"] as $key => $arItem){
        OrderService::setStatusOrderD7($arItem["ORDER_ID"], "F");
	}
	prent($arResult["ORDER"]);
	unset($arItem);

	die;
	?>
	<?
}else{
	?>
	Произошла непредвиденная ошибка. Пожалуйста, обновите страницу и попробуйте позже
	<?
}
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_after.php');