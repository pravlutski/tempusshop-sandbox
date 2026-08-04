<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetPageProperty("description", "MS ORDER");
$APPLICATION->SetPageProperty("title", "MS ORDER");

?>
<?if(!CModule::IncludeModule('panel.manager'))return;?>
<?AccessValidator::checkIfAllowed(); // Менеджер прав?>
<div id="settings-main" class="col-sm-12 row">
<?$APPLICATION->IncludeComponent("adm:utils.partners", "", array(), false);?>
</div>
<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>
