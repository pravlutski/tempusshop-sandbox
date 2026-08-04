<?require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');?>
<?
$APPLICATION->IncludeComponent("op:private.download", ".default", array("MODE" => "AJAX", "TYPE" => $_REQUEST["type"]),false);
?>
<?//require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>