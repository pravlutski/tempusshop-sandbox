<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");?>
<?if(!CModule::IncludeModule('panel.manager'))return;?>
<script src="/bitrix/templates/admin_panel/js/clipboard.min.js"></script>
<div id="settings-main" class="col-sm-12 row">
<?$APPLICATION->IncludeComponent("adm:settings.main", "", array(), false);?>
</div>
<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>