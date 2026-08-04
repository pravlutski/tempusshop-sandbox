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

$strSql = "SELECT * FROM bq_exchange WHERE ID = '{$ID}'";
$results = $DB->Query($strSql, false, $err_mess.__LINE__);
$isNew = false;
if ($row = $results->Fetch()){
	$arExchange = $row;
	$arExchange["SETTINGS"] = unserialize($arExchange["SETTINGS"]);
}else{
	$isNew = true;
}

$strSql = "SELECT * FROM bq_exchange_type";
$results = $DB->Query($strSql, false, $err_mess.__LINE__);
while ($row = $results->Fetch()){
	$arResult["GROUP"][] = $row;
}


$arTypeData = BQ_EXCHANGE[$arExchange["TYPE"]];
$arColumn = $arTypeData["METHOD"][$arExchange["SETTINGS"]["METHOD"]]["COLUMN"];
$arColumnPos = $arTypeData["METHOD"][$arExchange["SETTINGS"]["METHOD"]]["COLUMN_POS"];
//prent($arExchange);
?>
<?if($isNew === true):?>
	<input type="hidden" name="id" value="0">
<?else:?>
	<input type="hidden" name="id" value="<?=$arExchange["ID"]?>">
<?endif?>
<?if($isNew === true):?>
<div class="col-md-12">
	<div class="row">
		<div class="col-md-6 row">
			<div class="form-group">
				<label for="group_report">Группа отчета</label>
				<select name="group_report" id="group_report" class="form-control">
					<?foreach($arResult["GROUP"] as $arItem):?>
					<option value="<?=$arItem["ID"]?>"><?=$arItem["NAME"]?></option>
					<?endforeach?>
				</select>
			</div>
		</div>
		<div class="col-md-6">
			<div class="form-group">
				<label for="type_data">Тип данных</label>
				<?foreach(BQ_EXCHANGE as $group_id => $arGroup):?>
					<select name="type_data" id="type_data" class="form-control" style="<?if($group_id == 1):?>display:block;<?else:?>display:none;<?endif?>">
						<?foreach($arGroup["METHOD"] as $method => $arItem):?>
						<option value="<?=$method?>"><?=$arItem["NAME"]?></option>
						<?endforeach?>
					</select>
				<?endforeach?>
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
				<input type="checkbox" class="form-control1" name="active" <?if($arExchange["ACTIVE"] == "Y"):?>checked<?endif?> value="Y">
			</div>
		</div>
		<div class="col-md-5 row">
			<div class="form-group">
				<label for="name">Название</label>
				<input type="text" class="form-control" name="name" placeholder="Название" value="<?=$arExchange["NAME"]?>">
			</div>
		</div>
		<div class="col-md-2">
			<div class="form-group">
				<label for="name">Сортировка</label>
				<input type="number" class="form-control" name="sort" placeholder="Сортировка" value="<?=$arExchange["SORT"]?>">
			</div>
		</div>
		<?if($arExchange["TYPE"] == 1):?>
		<div class="col-md-2">
			<div class="form-group">
				<label for="name">Кабинет</label>
				<select name="login" class="form-control">
					<?if ($arExchange['SETTINGS']['METHOD'] == 'getListDemandPur') {?>
							<option value="msk" <?if($arExchange["SETTINGS"]["LOGIN"] == 'msk'):?>selected<?endif?>>api@chronos</option>
							<option value="s1_opt" <?if($arExchange["SETTINGS"]["LOGIN"] == 's1_opt'):?>selected<?endif?>>api@tempusws</option>
					<?} else {?>
						<?foreach($arTypeData["LOGIN_LIST"] as $site_id => $login):?>
						<option value="<?=$site_id?>" <?if($arExchange["SETTINGS"]["LOGIN"] == $site_id):?>selected<?endif?>><?=$login?></option>
						<?endforeach?>
					<?}?>
				</select>
			</div>
		</div>
		<?endif?>
	</div>
</div>
<?if ($arExchange['SETTINGS']['METHOD'] != 'getListDemandPur' and $arExchange['SETTINGS']['METHOD'] != 'getSupply') {?>
		<div class="col-md-12">
			<div class="row">
				<div class="col-md-8 row">
					<label for="period">Период</label>
					<div class="form-group">
						<input class="form-check-input check-period" type="radio" name="period" id="period1month" value="1month" <?if($arExchange["SETTINGS"]["PERIOD"] == "1month"):?>checked<?endif?>>
						<label class="form-check-label" for="period1month">Месяц</label>

						<input class="form-check-input check-period" type="radio" name="period" id="period2month" value="2month" <?if($arExchange["SETTINGS"]["PERIOD"] == "2month"):?>checked<?endif?>>
						<label class="form-check-label" for="period2month">2 месяца</label>

						<input class="form-check-input check-period" type="radio" name="period" id="period1year" value="1year" <?if($arExchange["SETTINGS"]["PERIOD"] == "1year"):?>checked<?endif?>>
						<label class="form-check-label" for="period1year">Год</label>

						<input class="form-check-input check-period" type="radio" name="period" id="period_from_date" value="from_date" <?if($arExchange["SETTINGS"]["PERIOD"] == "from_date"):?>checked<?endif?>>
						<label class="form-check-label" for="period_from_date">От даты:</label>
					</div>
				</div>

				<div class="col-md-4 row">
					<label for="period"></label>
					<div id="block_first_date" class="form-group" <?if($arExchange["SETTINGS"]["PERIOD"] != "from_date"):?>style="display:none;"<?endif?>>

						<input type="text" class="form-control" id="first_date" name="first_date" autocomplete="off" value="<?=$arExchange["SETTINGS"]["FIRST_DATE"]?>">

					</div>
					<script>
					$(function() {
						$( "#first_date").datetimepicker({
							//timeFormat: "hh:mm",
							//altFormat: "dd.mm.yy",
							dateFormat: 'dd.mm.yy',
							timeFormat: '',
							showTime: false
							//pickTime: false
						});
					});
					</script>
				</div>
			</div>
		</div>

		<div class="col-md-12">
			<div class="row">
				<div class="form-group">
					<label for="add_filter">Дополнительный фильтр</label>
					<input type="text" class="form-control" name="add_filter" placeholder="Дополнительный фильтр" value="<?=$arExchange["SETTINGS"]["ADD_FILTER"]?>">
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
<?}?>
<?if ($arExchange['SETTINGS']['METHOD'] != 'getListDemandPur') {?>
<div class="col-md-12">
	<div class="row">
		<div class="form-group">
			<label for="name_file">Название файла csv (без расширения)</label>
			<input type="text" class="form-control" name="name_file" placeholder="Название файла csv" value="<?=$arExchange["SETTINGS"]["NAME_FILE"]?>">
		</div>
	</div>
</div>
<?if ($arExchange['SETTINGS']['METHOD'] == 'getStocksCount' or $arExchange['SETTINGS']['METHOD'] == 'getSupply') {?>
<hr>
<div class="col-md-12">
	<div class="row">
		<div class="form-group">
			<label for="bq_table">Название датасета в BQ</label>
			<input type="text" class="form-control" name="dataset" placeholder="Название датасета в BQ" value="<?=$arExchange["SETTINGS"]["DATASET"]?>">
		</div>
	</div>
</div>
<?}?>
<hr>
<div class="col-md-12">
	<div class="row">
		<div class="form-group">
			<label for="bq_table">Название таблицы в BQ</label>
			<input type="text" class="form-control" name="bq_table" placeholder="Название таблицы в BQ" value="<?=$arExchange["SETTINGS"]["BQ_TABLE"]?>">
		</div>
	</div>
</div>
<?} else { ?>
	<div class="col-md-12">
		<div class="row">
			<div class="form-group">
				<label for="column">Столбцы для выгрузки (отгрузки)</label>
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
				<label for="column">Столбцы для выгрузки (позиции)</label>
				<select multiple="" class="form-control multiple_select" name="column_pos[]" style="height: 140px; width: 100%; overflow: auto;font-size: 11px;">
					<?foreach($arColumnPos as $column):?>
					<option value="<?=$column?>" <?if(is_array($arExchange["SETTINGS"]["COLUMN_POS"]) && in_array($column, $arExchange["SETTINGS"]["COLUMN_POS"])):?>selected<?endif?>><?=$column?></option>
					<?endforeach?>
				</select>
			</div>
		</div>
	</div>
	<div class="col-md-12">
		<div class="row">
			<div class="form-group">
				<label for="name_file">Название файла csv (отгрузки) (без расширения)</label>
				<input type="text" class="form-control" name="name_file" placeholder="Название файла csv (отгрузки)" value="<?=$arExchange["SETTINGS"]["NAME_FILE"]?>">
			</div>
		</div>
	</div>
	<div class="col-md-12">
		<div class="row">
			<div class="form-group">
				<label for="name_file">Название файла csv (позиции) (без расширения)</label>
				<input type="text" class="form-control" name="name_file_pos" placeholder="Название файла csv (позиции)" value="<?=$arExchange["SETTINGS"]["NAME_FILE_POS"]?>">
			</div>
		</div>
	</div>
	<hr>
	<div class="col-md-12">
		<div class="row">
			<div class="form-group">
				<label for="bq_table">Название таблицы в BQ (отгрузки)</label>
				<input type="text" class="form-control" name="bq_table" placeholder="Название таблицы в BQ (отгрузки)" value="<?=$arExchange["SETTINGS"]["BQ_TABLE"]?>">
			</div>
		</div>
	</div>
	<div class="col-md-12">
		<div class="row">
			<div class="form-group">
				<label for="bq_table">Название таблицы в BQ (позиции)</label>
				<input type="text" class="form-control" name="bq_table_pos" placeholder="Название таблицы в BQ (позиции)" value="<?=$arExchange["SETTINGS"]["BQ_TABLE_POS"]?>">
			</div>
		</div>
	</div>
<? }?>
<?endif?>
<?
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_after.php');
