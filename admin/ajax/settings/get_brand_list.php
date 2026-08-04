<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');?>
<?
if(CModule::IncludeModule("panel.manager")){
	$brand = new CPanelBrand;
	$arResult = $brand->getList();
	?>
	<option value="">--- Выберите бренд ---</option>
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