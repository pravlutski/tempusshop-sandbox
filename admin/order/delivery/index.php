<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetTitle("Список заказов");
?>

<?

$arParams = array(
	"FILTER" => Array("STATUS_ID" => array("CR"))
);
$APPLICATION->IncludeComponent("adm:order.list", "delivery", $arParams, false);

?>

<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>