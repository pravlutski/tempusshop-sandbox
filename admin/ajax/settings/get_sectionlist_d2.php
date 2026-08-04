<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');?>
<?
if(CModule::IncludeModule("panel.manager") && CModule::IncludeModule("iblock")){
	global $DB;
	$rsSect = \CIBlockSection::GetList(array('left_margin' => 'asc'), array(
      'IBLOCK_ID' => CProSet::IB_CATALOG,
      "DEPTH_LEVEL" => 2
    ));

	while($arSect = $rsSect->Fetch()){
		$arResult["SECTIONS"][] = $arSect;
	}
	$company_id = intval($_POST["company_id"]);
	$s_select = 0;//выбранный раздел битрикса для company_id
	$strSql = "SELECT * FROM ci_marketparser WHERE company_id = '{$company_id}'";
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		if ($row = $results->Fetch()){
			$s_select = $row["bitrix_id"];
		}
	?>
	<option value="">--- Выберите раздел ---</option>
	<?if(is_array($arResult["SECTIONS"]) && count($arResult["SECTIONS"]) > 0):?>
		<?foreach($arResult["SECTIONS"] as $key =>$arItem):?>
			<option value="<?=$arItem['ID']?>" <?if($arItem['ID'] == $s_select):?>selected="selected"<?endif?>><?=$arItem['NAME']?></option>
		<?endforeach?>
	<?endif?>
	<?
}else{
	die;
}
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_after.php');