<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetTitle("Анализ цен");
?>

<?$APPLICATION->IncludeComponent("adm:price.analysis", "", array(), false);?>

<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>