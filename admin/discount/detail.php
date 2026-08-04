<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetTitle("Детальный просмотр");
?>
<?
$arParams = array("ID" => intval($_REQUEST["id"]));
?>
<?$APPLICATION->IncludeComponent("adm:discount.detail", "", $arParams, false);?>

<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>