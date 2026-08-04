<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");?>
<?if(!CModule::IncludeModule('panel.manager'))return;?>
<?$APPLICATION->IncludeComponent("adm:content.task.remove", "", array(), false);?>
<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>