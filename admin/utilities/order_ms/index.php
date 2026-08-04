<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetPageProperty("description", "MS ORDER");
$APPLICATION->SetPageProperty("title", "MS ORDER");

?>
<?AccessValidator::checkIfAllowed(); // Менеджер прав?>
<?if(!CModule::IncludeModule('panel.manager'))return;?>
<div id="settings-main" class="col-sm-12 row">
<?$APPLICATION->IncludeComponent("adm:utils.ms.order", "", array(), false);?>
</div>
<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>
