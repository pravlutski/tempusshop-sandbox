<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetPageProperty("description", "Сборка");
$APPLICATION->SetPageProperty("title", "Сборка");

LocalRedirect('/admin/utilities/barcodes_v1/');
?>
<?if(!CModule::IncludeModule('panel.manager'))return;?>
<div class="col-sm-12 row barcode-main blocked">
	<?$APPLICATION->IncludeComponent("admin:utils.barcodes_new2", "", array(), false);?>
</div>
<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>