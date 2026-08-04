<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');?>
<?
$id = intval($_POST["id"]);
if(CModule::IncludeModule("panel.manager")){
	$courier = new CPanelEmployee;
	$arResult = array();
	if($id > 0){
		$arResult = $courier->getDetail($id);
		//prent($arResult);
	}
	?>
	<?if($arResult["id"] > 0):?>
	<input type="hidden" name="id" value="<?=$arResult["id"]?>">
	<div style="margin: 10px 0 0 0;">
		<div class="col-lg-8 row">
			<input type="text" class="form-control" name="name" placeholder="ФИО" value="<?=$arResult["name"]?>">
		</div>
		<div class="col-lg-4 fl-right row">
			<input type="text" class="form-control" name="sort" placeholder="Сортировка" value="<?=$arResult["sort"]?>">
		</div>
		<div class="col-lg-12 row" style="margin: 10px 0px 0 0px;padding: 0;">
			<input type="text" class="form-control" name="full_name" placeholder="Полное имя" value="<?=$arResult["full_name"]?>">
		</div>
		<div class="col-lg-12 row">
			<button type="submit" class="btn btn-warning" style="margin: 10px 0 0 0;">Сохранить</button>
			<button class="btn btn-danger btn-courier-delete" style="margin: 10px 0 0 5px;" data-id="<?=$arResult["id"]?>" onclick="confirmAgentDelete(this);return false;">Удалить</button>
		</div>
	</div>
	<?else:?>
	<input type="hidden" name="add" value="Y">
	<div style="margin: 10px 0 0 0;">
		<div class="col-lg-8 row">
			<input type="text" class="form-control" name="name" placeholder="Название" value="">
		</div>
		<div class="col-lg-4 fl-right row">
			<input type="text" class="form-control" name="sort" placeholder="Сортировка" value="">
		</div>
		<div class="col-lg-12 row" style="margin: 10px 0px 0 0px;padding: 0;">
			<input type="text" class="form-control" name="full_name" placeholder="Полное имя" value="">
		</div>
		<div class="col-lg-12 row">
			<button type="submit" class="btn btn-warning" style="margin: 10px 0 0 0;">+ Добавить</button>
		</div>
	</div>
	<?endif?>
	<?
}else{
	?>
	<p>Произошла непредвиденная ошибка. Пожалуйста, обновите страницу и попробуйте позже</p>
	<?
}
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_after.php');