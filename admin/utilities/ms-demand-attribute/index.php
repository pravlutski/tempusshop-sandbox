<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetPageProperty("description", "Установка атрибуов для отгрузок");
$APPLICATION->SetPageProperty("title", "Установка атрибуов для отгрузок");

?>
<?AccessValidator::checkIfAllowed(); // Менеджер прав?>
<?if(!CModule::IncludeModule('panel.manager'))return;?>
<div id="settings-main" class="col-sm-12 row">
<?$APPLICATION->IncludeComponent("adm:utils.ms.demand.att", "", array(), false);?>
</div>
<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>
