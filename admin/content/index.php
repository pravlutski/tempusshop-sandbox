<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");?>
<?if(!CModule::IncludeModule('panel.manager'))return;?>
<?AccessValidator::checkIfAllowed(); // Менеджер прав?>
<?$APPLICATION->IncludeComponent("adm:content.main", "", $json, false);?>

<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>
