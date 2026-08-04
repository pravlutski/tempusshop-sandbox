<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');?>
<?
if(CModule::IncludeModule("panel.manager")){
	$courier = new CPanelCourier;
	$arResult = $courier->getList();
	?>
	<option value="">--- Выберите курьера ---</option>
	<?if(is_array($arResult) && count($arResult) > 0):?>
		<?foreach($arResult as $key =>$arItem):?>
			<option value="<?=$arItem['id']?>"><?=$arItem['name']?></option>
		<?endforeach?>
	<?endif?>
	<?
}else{
	die;
}
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_after.php');