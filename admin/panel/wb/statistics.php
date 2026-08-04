<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");?>
<?if(!CModule::IncludeModule('panel.manager'))return;?>
<?$APPLICATION->SetTitle('Статистика ФБО - WB модуль');?>
<link href="<?=SITE_TEMPLATE_PATH?>/css/settings.css" rel="stylesheet">
<script src="<?=SITE_TEMPLATE_PATH?>/js/settings.js"></script>
<?require('include/charts.php');?>
<?require('include/mobile.php');?>
<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>
