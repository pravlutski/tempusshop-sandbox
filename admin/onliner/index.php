<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetTitle("Анализ цен");
?>
<?AccessValidator::checkIfAllowed(); // Менеджер прав?>
<?$APPLICATION->IncludeComponent("adm:onliner.main", "", array(), false);?>

<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>
