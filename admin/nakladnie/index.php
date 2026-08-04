<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetTitle("Бухгалтерия");
?>

<?$APPLICATION->IncludeComponent("adm:reports.commision", "", array(), false);?>
<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>
