<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetTitle("История закупок из MS");
?>
<?$APPLICATION->IncludeComponent("adm:ms.history", "", array(), false);?>

<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>