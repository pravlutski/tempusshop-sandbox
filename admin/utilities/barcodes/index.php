<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetPageProperty("description", "Сборка");
$APPLICATION->SetPageProperty("title", "Сборка");

LocalRedirect('/admin/utilities/barcodes_v1/');
?>
<?if(!CModule::IncludeModule('panel.manager'))return;?>
<div id="settings-main" class="col-sm-12 row">
<?$APPLICATION->IncludeComponent("adm:utils.barcodes", "", array(), false);?>
</div>
<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>