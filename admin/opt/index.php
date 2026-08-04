<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetTitle("Скачать прайс-лист tempus");
?>
<?AccessValidator::checkIfAllowed(); // Менеджер прав?>

<?//$APPLICATION->IncludeComponent("adm:price.opt", "", array(), false);?>
<?$APPLICATION->IncludeComponent("op:private.download", ".default", array(),false);?>
<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>
