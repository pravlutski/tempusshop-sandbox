<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
if($_SERVER['HTTP_X_REQUESTED_WITH'] != 'XMLHttpRequest' || !CModule::IncludeModule('panel.manager')) return;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

	$deviation = (float)$_REQUEST["price-deviation-foreign"];
	CProSet::setOption("PRICE_DEVIATION_FOREIGN", $deviation);

}