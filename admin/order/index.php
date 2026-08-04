<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetTitle("Список заказов");//return;
?>
<div class="col-sm-12 row">
<?$APPLICATION->IncludeComponent("adm:order.list", "", $arParams, false);?>
</div>
<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>