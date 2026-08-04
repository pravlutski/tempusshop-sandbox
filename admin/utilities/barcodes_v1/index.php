<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetPageProperty("description", "Сборка");
$APPLICATION->SetPageProperty("title", "Сборка");

?>
<?if(!CModule::IncludeModule('panel.manager'))return;?>
<?AccessValidator::checkIfAllowed(); // Менеджер прав?>
<div class="col-sm-12 row barcode-main blocked">
	<?$APPLICATION->IncludeComponent("admin:utils.barcodes", "", array(), false);?>
</div>
<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>
