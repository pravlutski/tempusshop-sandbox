<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetTitle("Список заказов");

global $USER;
if ($USER->getID() == 126770) {
	$template = 'new';
} else {
	$template = '';
}

?>
<?AccessValidator::checkIfAllowed(); // Менеджер прав?>
<div class="col-sm-12 row" id="order-open">
	<div id="blackout"></div>
	<?$APPLICATION->IncludeComponent("adm.courier:order.list", $template, $arParams, false);?>
</div>
<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>
