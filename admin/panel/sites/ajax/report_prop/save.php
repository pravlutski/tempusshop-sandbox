<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
if(!CModule::IncludeModule('panel.manager'))return;
if($_POST['property']) {
	$options = json_encode($_POST['property']);
	CProSet::setOption("PROP_LOG_ANALYSIS", $options);
}
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_after.php');
