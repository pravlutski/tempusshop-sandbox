<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetPageProperty("description", "Сборка");
$APPLICATION->SetPageProperty("title", "Сборка");

?>
<?if(!CModule::IncludeModule('panel.manager'))return;?>
<div id="order-main" class="col-sm-12 row">
<?$APPLICATION->IncludeComponent("adm:order.assembly", "", array(), false);?>
</div>
<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>