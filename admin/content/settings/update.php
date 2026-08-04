<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");?>
<?if(!CModule::IncludeModule('panel.manager'))return;?>
<div class="grid marginleft50" style="margin-top: 10px;" id="content-main">
<?
if(!CModule::IncludeModule('panel.manager') || !CModule::IncludeModule('iblock')) return;

$objContent = new CPanelContent;
$res = $objContent->syncProps();
if(is_array($res) && $res["status"] == "ok"){
	LocalRedirect("/admin/content/settings/");
}else{
	echo $res["text"];
}
?>
</div>
<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>