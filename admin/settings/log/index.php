<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");?>
<?if(!CModule::IncludeModule('panel.manager'))return;?>
<div id="settings-main">
<?$APPLICATION->IncludeComponent("adm:settings.log", "", array(), false);?>
</div>
<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>