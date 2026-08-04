<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetPageProperty("description", "Сборка");
$APPLICATION->SetPageProperty("title", "Сборка");

?>
<?if(!CModule::IncludeModule('panel.manager'))return;?>
<div class="col-sm-12 row barcode-main blocked">
	<?php
	$APPLICATION->IncludeComponent(
		'admin:price.monitoring',
		'',
		[],
		false
	);
	?>
</div>
<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>