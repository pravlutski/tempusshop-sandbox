<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetPageProperty("description", "Утилита для формирования FBO коробов для маркетплейсов");
$APPLICATION->SetPageProperty("title", "FBO Короба");

?>
<?if(!CModule::IncludeModule('panel.manager'))return;?>
<?AccessValidator::checkIfAllowed(); // Менеджер прав?>
<div class="col-sm-12 row">
	<?$APPLICATION->IncludeComponent("admin:market.fbo.boxes", "", array(), false);?>
</div>
<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>
