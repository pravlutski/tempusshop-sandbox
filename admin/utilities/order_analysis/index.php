<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetPageProperty("description", "Анализ маржинальности");
$APPLICATION->SetPageProperty("title", "Анализ маржинальности");

?>
<?if(!CModule::IncludeModule('panel.manager'))return;?>
<div id="order-main" class="col-sm-12 row">
<?$APPLICATION->IncludeComponent("adm:order.analysis", "", array(), false);?>
</div>
<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>