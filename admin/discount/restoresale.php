<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");?>
<?$APPLICATION->SetTitle("Вернуть продажу");?>
<?if(!CModule::IncludeModule('panel.manager'))return;?>
<h1 class="page-header">Вернуть продажу</h1>
<a href="/admin/discount/" class="btn btn-default">Назад</a>
<?
$id = intval($_REQUEST["id"]);
global $USER;
$objDiscount = new CPanelDiscount;
if($objDiscount->restore_sale( $id )){
	$card_id = $objDiscount->getIDbySale( $id );
	$card = $objDiscount->getCard( $card_id );
	$code = $card['code'];
	$objDiscount->toLog(
		"Востановлена продажа для карты #$code. ID продажи #$id",
		$USER->getID()
	);
	LocalRedirect("/admin/discount/detail.php?id=" . $card_id);
}else{
	echo "<p class='col-sm-12 label label-danger' style='margin: 10px 0 0 0;'>Не найдена продажа</p>";
}
			
?>
<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>