<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');?>
<?
if(isset($_POST["website"]) && in_array($_POST["website"], array("s1", "s2", "s3", "wb", "s1_nkz")))
	$website = $_POST["website"];
?>
<?if(!in_array($website, array("s1", "s2", "s3", "wb", "s1_nkz"))):?><?
	?>Выберите сайт<?
	?><?die;?>
<?endif?>
<?
global $USER;
if(CModule::IncludeModule("panel.manager") && CModule::IncludeModule("iblock") && ($USER->isAdmin() || $USER->GetId() == 152160)){
	global $DB;
	$arItems = explode("\r\n", trim($_POST["items"]));
	$arItems = array_diff($arItems, array(''));
//	$arItems = array_unique($arItems);
	//prent($_POST);

	if($website == "wb"){
		CProSet::setOption("WB_TOP_MIN_QUANTITY", $_POST["wb_min_quantity"]);
	}else{
		$ar = $_POST;
		unset($ar["wb_min_quantity"]);
		CProSet::setOption("TOP_ITEMS_" . $website, json_encode($ar));
	}

	/*$strSql = "SELECT * FROM ci_purchase WHERE active = 'Y' AND status = 'T' AND site_id = '".$DB->ForSql($website)."'";
	$results = $DB->Query($strSql, false, $err_mess.__LINE__);
	if ($row = $results->Fetch()){
		echo "<p style='color: red'>Очистите список на странице закупок</p>";die;
	}
	if(count($arItems) > 0){
		$DB->Query("DELETE FROM ci_top_models WHERE site_id = '".$DB->ForSql($website)."'", false, $err_mess.__LINE__);
		$arFilter = Array(
			"IBLOCK_ID" => CProSet::IB_CATALOG,
			"PROPERTY_CML2_ARTICLE" => $arItems,
		);
		$result = CIBlockElement::GetList(Array(), $arFilter, false, false, array("PROPERTY_CML2_ARTICLE", "ID"));
		while($arFields = $result->GetNext()){
			$models[$arFields['PROPERTY_CML2_ARTICLE_VALUE']] = $arFields["ID"];
		}
		$cnt_update = 0;
		foreach ($arItems as $art){
			if($models[$art]){
				$in = array(
					"model" => "'".addslashes($art)."'",
					"site_id" => "'".$website."'",
					"bitrix_id" => "'".$models[$art]."'",
				);
				$DB->Insert("ci_top_models", $in, $err_mess.__LINE__);
				$cnt_update++;
			}else{
				echo "<p style='color: red'>Не корректные данные {$art}</p>";
			}
		}

		$ar = array(
			"event" => "R",
			"text" => "Обновление TOP листа {$website}",
			"detail" => json_encode($arItems, JSON_UNESCAPED_UNICODE)
		);
		CLog::add2log($ar);

		echo "<p>Добавлено {$cnt_update} из " . count($arItems) . "</p>";
	}else{
		echo "<p style='color: red'>Нету данных для загрузки</p>";
	}*/
	//prent($items);
	?>
	<?
}else{
	?>
	Произошла непредвиденная ошибка. Пожалуйста, обновите страницу и попробуйте позже
	<?
}
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_after.php');
