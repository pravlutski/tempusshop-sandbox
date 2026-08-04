<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetTitle("Закупка");
?>
<?$APPLICATION->IncludeComponent("adm:purchase.main_new", "", array(), false);?>
<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>
