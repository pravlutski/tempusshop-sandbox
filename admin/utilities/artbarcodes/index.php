<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");?>
<?if(!CModule::IncludeModule('panel.manager'))return;?>
<div id="settings-main" class="col-sm-12 row">
<?$APPLICATION->IncludeComponent("adm:utils.artbarcodes", "", array(), false);?>
</div>
<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>