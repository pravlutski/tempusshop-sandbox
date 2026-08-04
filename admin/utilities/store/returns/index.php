<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetPageProperty("description", "Возвраты с Немиги");
$APPLICATION->SetPageProperty("title", "Возвраты с Немиги");

?>
<?AccessValidator::checkIfAllowed(); // Менеджер прав?>
<?if(!CModule::IncludeModule('panel.manager'))return;?>
<div class="col-sm-12 row market-returns">
	<?$APPLICATION->IncludeComponent("admin:store.returns", "", array(), false);?>
</div>
<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>
