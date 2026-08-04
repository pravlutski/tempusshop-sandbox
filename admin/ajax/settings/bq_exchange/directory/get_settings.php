<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');?>
<?
global $DB;
?>

<?
global $USER;
/*if(!$USER->isAdmin()){
    global $APPLICATION;
    $APPLICATION->AuthForm("Доступ запрещен");
    return;
}*/
if(!CModule::IncludeModule("panel.manager")) die;

$ID = intval($_REQUEST["ID"]);
//if($ID <= 0) die;

$strSql = "SELECT * FROM ci_ms_directory WHERE ID = '{$ID}'";
$results = $DB->Query($strSql, false, $err_mess.__LINE__);
$isNew = false;
if ($row = $results->Fetch()){
	$arExchange = $row;
	$arExchange["SETTINGS"] = unserialize($arExchange["settings"]);
}else{
	$isNew = true;
}



$arTypeData = BQ_DIRECTORY;
$arColumn = $arTypeData["METHOD"][$arExchange["SETTINGS"]["METHOD"]]["COLUMN"];
$arColumnPos = $arTypeData["METHOD"][$arExchange["SETTINGS"]["METHOD"]]["COLUMN_POS"];
//prent($arExchange);
?>
<?if($isNew === true):?>
	<input type="hidden" name="id" value="0">
<?else:?>
	<input type="hidden" name="id" value="<?=$arExchange["id"]?>">
<?endif?>
<?if($isNew === true):?>
<div class="col-md-12">
	<div class="row">
		<div class="col-md-6">
			<div class="form-group">
				<label for="name">Кабинет</label>
				<select name="login" class="form-control">
						<?foreach($arTypeData["LOGIN_LIST"] as $site_id => $login):?>
						<option value="<?=$site_id?>" <?if($arExchange["SETTINGS"]["LOGIN"] == $site_id):?>selected<?endif?>><?=$login?></option>
						<?endforeach?>
				</select>
			</div>
		</div>
		<div class="col-md-6">
			<div class="form-group">
				<label for="type_data">Тип справочника</label>
					<select name="type_data" id="type_data" class="form-control" style="display:block;">
						<?foreach(BQ_DIRECTORY["METHOD"] as $method => $arItem):?>
						<option value="<?=$method?>"><?=$arItem["NAME"]?></option>
						<?endforeach?>
					</select>
			</div>
		</div>
	</div>
</div>
<?elseif($arExchange):?>
<div class="col-md-12">
	<div class="row">
		<div class="col-md-3 row">
			<div class="form-group">
				<label for="active">Активен</label><br>
				<input type="checkbox" class="form-control1" name="active" <?if($arExchange["active"] == "Y"):?>checked<?endif?> value="Y">
			</div>
		</div>
		<div class="col-md-6 row">
			<div class="form-group">
				<label for="name">Название</label>
				<input type="text" class="form-control" name="name" placeholder="Название" value="<?=$arExchange["name"]?>">
			</div>
		</div>
		<div class="col-md-3">
			<div class="form-group">
				<label for="name">Кабинет</label>
				<select name="login" class="form-control">
						<?foreach($arTypeData["LOGIN_LIST"] as $site_id => $login):?>
							<option value="<?=$site_id?>" <?if($arExchange["site_id"] == $site_id):?>selected<?endif?>><?=$login?></option>
						<?endforeach?>
				</select>
			</div>
		</div>
	</div>
</div>
	<div class="col-md-12">
		<div class="row">
			<div class="form-group">
				<label for="column">Столбцы для выгрузки</label>
				<select multiple="" class="form-control multiple_select" name="column[]" style="height: 140px; width: 100%; overflow: auto;font-size: 11px;">
					<?foreach($arColumn as $column):?>
					<option value="<?=$column?>" <?if(is_array($arExchange["SETTINGS"]["COLUMN"]) && in_array($column, $arExchange["SETTINGS"]["COLUMN"])):?>selected<?endif?>><?=$column?></option>
					<?endforeach?>
				</select>
			</div>
		</div>
	</div>
	<div class="col-md-12">
		<div class="row">
			<div class="form-group">
				<label for="name_file">Название файла csv</label>
				<input type="text" class="form-control" name="name_file" placeholder="Название файла csv" value="<?=$arExchange["SETTINGS"]["NAME_FILE"]?>">
			</div>
		</div>
	</div>
	</div>
	<hr>
	<div class="col-md-12">
		<div class="row">
			<div class="form-group">
				<label for="bq_table">Название таблицы в BQ</label>
				<input type="text" class="form-control" name="bq_table" placeholder="Название таблицы в BQ" value="<?=$arExchange["SETTINGS"]["BQ_TABLE"]?>">
			</div>
		</div>
	</div>
<?endif?>
<?
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_after.php');
