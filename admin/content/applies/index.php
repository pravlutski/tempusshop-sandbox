<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");?>
<?if(!CModule::IncludeModule('panel.manager'))return;?>
<div class="grid marginleft50" style="margin-top: 10px;" id="content-main">
	<?$APPLICATION->IncludeComponent("adm:content.applies", "", array(), false);?>
</div>
<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>