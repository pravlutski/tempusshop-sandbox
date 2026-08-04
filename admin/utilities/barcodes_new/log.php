<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetPageProperty("description", "Сборка");
$APPLICATION->SetPageProperty("title", "Сборка");

?>
<?if(!CModule::IncludeModule('panel.manager'))return;?>
<div class="col-sm-12 row barcode-main blocked">
	<?$APPLICATION->IncludeComponent(
		"adm:utils.barcodes.log",
		"",
		array(
			"LOG_DIR" => "/var/www/bitrix_logs/barcode/logs/",
			"ITEMS_PER_PAGE" => "200",
		)
	);?>
</div>
<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>