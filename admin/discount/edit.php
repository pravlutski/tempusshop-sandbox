<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetTitle("Редактирование дисконтной карты");
?>
<?
$arParams = array("ID" => intval($_REQUEST["id"]));
?>
<?$APPLICATION->IncludeComponent("adm:discount.edit", "", $arParams, false);?>

<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>