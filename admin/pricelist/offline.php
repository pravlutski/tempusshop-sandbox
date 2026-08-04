<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetTitle("Прайс-листы");?>
<?AccessValidator::checkIfAllowed(); // Менеджер прав?>
<?$APPLICATION->IncludeComponent("adm:offline.pricelist", "", array(), false);?>

<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>
