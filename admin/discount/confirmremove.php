<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");?>
<?$APPLICATION->SetTitle("Удаление карты");?>
<?if(!CModule::IncludeModule('panel.manager'))return;?>
<?
$arParams = array("ID" => intval($_REQUEST["id"]));
?>
<?$APPLICATION->IncludeComponent("adm:discount.remove", "", $arParams , false);?>
<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>