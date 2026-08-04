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
if(CModule::IncludeModule("panel.manager")){
	global $DB;
	$brand = new CPanelBrand;
	$arBrand = $brand->getList();

	$arSettings = CProSet::getOption("TOP_ITEMS_" . $website);
	$arSettings = json_decode($arSettings, true);
	//prent($arSettings);
	//prent($arBrand);
	$topWb = CProSet::getOption("WB_TOP_MIN_QUANTITY");
	//prent($topWb);
	//$topWb = $topWb["value"];
	?>

	<?if($website == "wb"){?>
	<p>WB минимальное кол-во за последние 6 месяцев (по всем контрагентам) <input type="number" class="form-control" name="wb_min_quantity" value="<?=$topWb?>"></p>
	<?}else if ($website == "s2"){?>
	<p style="margin: 15px 0 0 0;"><span style="float: left;margin: 5px 10px 0 0;">Дней закупки </span><input type="text" class="form-control" name="purchase_day" value="<?=$arSettings["purchase_day"]?>" style="width:50px; margin: 5px 10px 0 0;"></p>
	<table class="table" id="sup-list">
		<thead>
			<tr>
				<th>Бренд</th>
				<th>Активность</th>
				<th>Продаж за год >=</th>
			</tr>
		</thead>
		<tbody>

			<?foreach($arBrand as $k => $arItem):?>
			<?if ($arItem['id'] == 1){?>
			<?
			$sections = [];

			$arFilter = [
			    'IBLOCK_ID' => 16,
			    'SECTION_ID' => 223,
			];

			$rsSections = CIBlockSection::GetList([], $arFilter, false, ['ID', 'NAME']);
			while ($arSection = $rsSections->Fetch()) {
			    $sections[] = $arSection;
			}?>
			<tr>
				<td><?=$arItem["name"]?></td>
				<td><input type="checkbox" class="" name="brand[<?=$arItem["id"]?>][active]" <?if($arSettings["brand"][$arItem["id"]]["active"]):?>checked<?endif?> value="Y"></td>
				<td><input type="text" class="form-control" name="brand[<?=$arItem["id"]?>][min]" value="<?=$arSettings["brand"][$arItem["id"]]["min"]?>"></td>
			</tr>
			<?foreach ($sections as $key => $value) {?>
				<tr>

					<td style="width: 290px;"> &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;----- <?=$value["NAME"]?></td>
					<td style="width: 80px;"></td>
					<td style="width: 90px;"><input type="text" class="form-control" name="subbrand[<?=$value["ID"]?>][min]" value="<?=$arSettings["subbrand"][$value["ID"]]["min"]?>"></td>
				</tr>
			<?}?>

			<?}else{?>
			<tr>
				<td><?=$arItem["name"]?></td>
				<td><input type="checkbox" class="" name="brand[<?=$arItem["id"]?>][active]" <?if($arSettings["brand"][$arItem["id"]]["active"]):?>checked<?endif?> value="Y"></td>
				<td><input type="text" class="form-control" name="brand[<?=$arItem["id"]?>][min]" value="<?=$arSettings["brand"][$arItem["id"]]["min"]?>"></td>
			</tr>
			<?}?>
			<?endforeach?>
			<tr>
				<td colspan="3"><textarea name="additional" class="form-control input-sm" style="height: 140px;margin: 5px 0 0 0;"><?=$arSettings["additional"]?></textarea></td>

			</tr>
		</tbody>
	</table>
<? }else if ($website == "s1"){?>
<p style="margin: 15px 0 0 0;"><span style="float: left;margin: 5px 10px 0 0;">Дней закупки </span><input type="text" class="form-control" name="purchase_day" value="<?=$arSettings["purchase_day"]?>" style="width:50px; margin: 5px 10px 0 0;"></p>
<table class="table" id="sup-list">
	<thead>
		<tr>
			<th>Бренд</th>
			<th>Активность</th>
			<th>Продаж за год >=</th>
		</tr>
	</thead>
	<tbody>

		<?foreach($arBrand as $k => $arItem):?>
		<?if ($arItem['id'] == 1){?>
		<?
		$sections = [];

		$arFilter = [
				'IBLOCK_ID' => 16,
				'SECTION_ID' => 223,
		];

		$rsSections = CIBlockSection::GetList([], $arFilter, false, ['ID', 'NAME']);
		while ($arSection = $rsSections->Fetch()) {
				$sections[] = $arSection;
		}?>
		<tr>
			<td><?=$arItem["name"]?></td>
			<td><input type="checkbox" class="" name="brand[<?=$arItem["id"]?>][active]" <?if($arSettings["brand"][$arItem["id"]]["active"]):?>checked<?endif?> value="Y"></td>
			<td><input type="text" class="form-control" name="brand[<?=$arItem["id"]?>][min]" value="<?=$arSettings["brand"][$arItem["id"]]["min"]?>"></td>
		</tr>
		<?foreach ($sections as $key => $value) {?>
			<tr>

				<td style="width: 290px;"> &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;----- <?=$value["NAME"]?></td>
				<td style="width: 80px;"></td>
				<td style="width: 90px;"><input type="text" class="form-control" name="subbrand[<?=$value["ID"]?>][min]" value="<?=$arSettings["subbrand"][$value["ID"]]["min"]?>"></td>
			</tr>
		<?}?>

		<?}else{?>
		<tr>
			<td><?=$arItem["name"]?></td>
			<td><input type="checkbox" class="" name="brand[<?=$arItem["id"]?>][active]" <?if($arSettings["brand"][$arItem["id"]]["active"]):?>checked<?endif?> value="Y"></td>
			<td><input type="text" class="form-control" name="brand[<?=$arItem["id"]?>][min]" value="<?=$arSettings["brand"][$arItem["id"]]["min"]?>"></td>
		</tr>
		<?}?>
		<?endforeach?>
		<tr>
			<td colspan="3"><textarea name="additional" class="form-control input-sm" style="height: 140px;margin: 5px 0 0 0;"><?=$arSettings["additional"]?></textarea></td>

		</tr>
	</tbody>
</table>
<? } else {?>
	<p style="margin: 15px 0 0 0;"><span style="float: left;margin: 5px 10px 0 0;">Дней закупки </span><input type="text" class="form-control" name="purchase_day" value="<?=$arSettings["purchase_day"]?>" style="width:50px; margin: 5px 10px 0 0;"></p>
	<table class="table" id="sup-list">
		<thead>
			<tr>
				<th>Бренд</th>
				<th>Активность</th>
				<th>Продаж за год >=</th>
			</tr>
		</thead>
		<tbody>

			<?foreach($arBrand as $k => $arItem):?>
			<tr>
				<td><?=$arItem["name"]?></td>
				<td><input type="checkbox" class="" name="brand[<?=$arItem["id"]?>][active]" <?if($arSettings["brand"][$arItem["id"]]["active"]):?>checked<?endif?> value="Y"></td>
				<td><input type="text" class="form-control" name="brand[<?=$arItem["id"]?>][min]" value="<?=$arSettings["brand"][$arItem["id"]]["min"]?>"></td>
			</tr>
			<?endforeach?>
			<tr>
				<td colspan="3"><textarea name="additional" class="form-control input-sm" style="height: 140px;margin: 5px 0 0 0;"><?=$arSettings["additional"]?></textarea></td>

			</tr>
		</tbody>
	</table>
	<?}?>
	<p><a href="/admin/ajax/settings/toplist_get_xls.php?site_id=<?=$website?>">Скачать список</a></p>
	<?
}else{
	?>
	Произошла непредвиденная ошибка. Пожалуйста, обновите страницу и попробуйте позже
	<?
}
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_after.php');
