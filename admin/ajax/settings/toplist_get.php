<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');?>
<?
if(isset($_POST["website"]) && in_array($_POST["website"], array("s1", "s2")))
	$website = $_POST["website"];
?>
<?if(!in_array($website, array("s1", "s2"))):?><?
	?>Выберите сайт<?
	?><?die;?>
<?endif?>
<?
if(CModule::IncludeModule("panel.manager")){
	global $DB;
	$str = "";
	
	$settings = CProSet::getOption("TOP_ITEMS_" . $website);
	//prent($settings);
	$strSql = "SELECT * FROM ci_top_models WHERE site_id = '".$DB->ForSql($website)."'";
	$results = $DB->Query($strSql, false, $err_mess.__LINE__);
	while ($row = $results->Fetch()){
		$arResult["ITEMS"][] = $row["model"];
		$str .= $row["model"] . "\r\n";
	}
	echo $str;
	?>
	<?
}else{
	?>
	Произошла непредвиденная ошибка. Пожалуйста, обновите страницу и попробуйте позже
	<?
}
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_after.php');