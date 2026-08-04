<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetTitle("Список заказов");
?>
<?AccessValidator::checkIfAllowed(); // Менеджер прав?>
<div class="col-sm-12 row" id="order-open">
	<div id="blackout"></div>
	<?$APPLICATION->IncludeComponent("adm.courier:storekeeper.list", "", $arParams, false);?>
</div>
<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>
