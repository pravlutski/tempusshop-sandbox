<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetTitle("Закупка");
?>
<?AccessValidator::checkIfAllowed(); // Менеджер прав?>
<?$APPLICATION->IncludeComponent("adm:opt.purchase.main", "", array(), false);?>
<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>
