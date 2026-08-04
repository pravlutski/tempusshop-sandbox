<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetTitle("Прайс-листы");?>

<?$APPLICATION->IncludeComponent("adm:pricelist.main", "", array(), false);?>

<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>