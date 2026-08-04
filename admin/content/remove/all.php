<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");?>
<?if(!CModule::IncludeModule('panel.manager'))return;?>
<?
global $USER;
if($USER->isAdmin()){
	$content = new CPanelContent;
	$content->removeAllTask();
	header("Location: /admin/content/");
}
?>
<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>