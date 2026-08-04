<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetPageProperty("description", "Сборка");
$APPLICATION->SetPageProperty("title", "Сборка");

?>
<?AccessValidator::checkIfAllowed(); // Менеджер прав?>
<?if(!CModule::IncludeModule('panel.manager'))return;?>
<div class="col-sm-12 row market-returns">
	<?$APPLICATION->IncludeComponent("admin:market.returns", "", array(), false);?>
</div>
<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>
