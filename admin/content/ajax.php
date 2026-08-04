<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");?>
<?$json = (array) json_decode(file_get_contents("php://input"));?>

<?$APPLICATION->IncludeComponent("adm:content.main", "", $json, false);?>

<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_after.php");?>