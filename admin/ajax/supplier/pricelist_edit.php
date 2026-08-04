<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');

if(CModule::IncludeModule("panel.manager")){
	$objSupplier = new CPanelSupplier;
	$lists = array();
	$supp_id = intval($_POST["id"]);
	if($supp_id > 0){
		$arSupplier = $objSupplier->getDetail( $supp_id );
		$arSupplier["settings"] = json_decode( $arSupplier["settings"], true );
		$arSupplier["settings_pricelist"] = json_decode( $arSupplier["settings_pricelist"], true );
		$arSupplier["settings_pricelist_detail"] = json_decode( $arSupplier["settings_pricelist_detail"], true );
		
		$lists = explode(",", $arSupplier["settings_pricelist"]["num_lists"]);
		

		$arResult = array(
			'id'			=> $supp_id,
			'supplier'		=> $arSupplier["settings_pricelist"]["num_lists"],
		);
	}else{
		return false;
	}
	//prent($arSupplier["settings_pricelist_detail"]);
	?>
	<?if(is_array($lists) && count($lists) > 1):?>
	<div class="modal-header">
		<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
		<h4 class="modal-title" id="myModalLabel23232">Настройка листов</h4>
	</div>
	<form class="form-horizontal" id="apply-supplier-pricelist">
		<input type="hidden" name="supplier-id" value="<?=$supp_id?>">
		<div class="modal-body">
			<?foreach($lists as $key => $list):?>
			<div class="col-lg-12">
				<h5 class="modal-title" style="margin: 10px 0 10px 0;border-top: 1px solid black;padding-top: 5px;">Настройка <?=$list?> листа <input type="checkbox" name="list[<?=$list?>][active]" <?if($arSupplier["settings_pricelist_detail"][$list]["active"] == "Y"):?>checked="checked"<?endif?>></h5>
				<p style="font-weight: bold;margin: 7px 0 0 15px;">Столбец в прайсе</p>
				<div class="col-lg-2 ">
					<label style="font-size: 11px;line-height: 11px;">Бренд</label>
					<input type="number" class="form-control " name="list[<?=$list?>][col_brand]" value="<?if(isset($arSupplier["settings_pricelist_detail"][$list]["col_brand"])):?><?=$arSupplier["settings_pricelist_detail"][$list]["col_brand"]?><?endif?>">
				</div>
				<div class="col-lg-2 ">
					<label style="font-size: 11px;line-height: 11px;">Артикул</label>
					<input type="number" class="form-control " name="list[<?=$list?>][col_article]" value="<?if(isset($arSupplier["settings_pricelist_detail"][$list]["col_article"])):?><?=$arSupplier["settings_pricelist_detail"][$list]["col_article"]?><?endif?>">
				</div>
				<div class="col-lg-2 ">
					<label style="font-size: 11px;line-height: 11px;">Цена</label>
					<input type="number" class="form-control " name="list[<?=$list?>][col_price]" value="<?if(isset($arSupplier["settings_pricelist_detail"][$list]["col_price"])):?><?=$arSupplier["settings_pricelist_detail"][$list]["col_price"]?><?endif?>">
				</div>
				<div class="col-lg-2 ">
					<label style="font-size: 11px;line-height: 11px;">Кол/наличие</label>
					<input type="number" class="form-control " name="list[<?=$list?>][col_count]" value="<?if(isset($arSupplier["settings_pricelist_detail"][$list]["col_count"])):?><?=$arSupplier["settings_pricelist_detail"][$list]["col_count"]?><?endif?>">
				</div>
				<div class="col-lg-2 ">
					<label style="font-size: 11px;line-height: 11px;">Кратность</label>
					<input type="number" class="form-control " name="list[<?=$list?>][col_multiplicity]" value="<?if(isset($arSupplier["settings_pricelist_detail"][$list]["col_multiplicity"])):?><?=$arSupplier["settings_pricelist_detail"][$list]["col_multiplicity"]?><?endif?>">
				</div>

			</div>

			<div class="col-lg-12">
				<div class="col-lg-3 " id="col-quntity-flag" <?if($arSupplier["settings_pricelist_detail"][$list]["col_count"] <= 0):?>style="display: none;"<?endif?>>
					<label style="font-size: 11px;line-height: 11px;">Флаг наличия</label>
					<select class="form-control select_w" name="list[<?=$list?>][quntity_flag]">
						<option value="" disabled <?if(!$arSupplier["settings_pricelist_detail"][$list]["quntity_flag"]):?>selected<?endif?>> --- Выберите ---</option>
						<option value="str" <?if($arSupplier["settings_pricelist_detail"][$list]["quntity_flag"] == "str"):?> selected="selected"<?endif?>>Строка</option>
						<option value="int" <?if($arSupplier["settings_pricelist_detail"][$list]["quntity_flag"] == "int"):?> selected="selected"<?endif?>>Число</option>
					</select>
				</div>
				<div class="col-lg-4 " id="col-quntity-value" style="<?if((!$arSupplier["settings_pricelist_detail"][$list]["quntity_flag"] || $arSupplier["settings_pricelist_detail"][$list]["quntity_flag"] == "str") && $arSupplier["settings_pricelist_detail"][$list]["col_count"] > 0):?>display: block;<?else:?>display: none;<?endif?>">
					<label style="font-size: 11px;line-height: 11px;">Значение флага наличия</label>
					<input type="text" class="form-control " name="list[<?=$list?>][quntity_value]" style="width: 98px;" value="<?if(isset($arSupplier["settings_pricelist_detail"][$list]["quntity_value"])):?><?=$arSupplier["settings_pricelist_detail"][$list]["quntity_value"]?><?endif?>">
				</div>
				<div class="col-lg-4 " id="col-quntity-default" <?if($arSupplier["settings_pricelist_detail"][$list]["col_count"] > 0 && $arSupplier["settings_pricelist_detail"][$list]["quntity_flag"] == "int"):?>style="display: none;"<?endif?>>
					<label style="font-size: 11px;line-height: 11px;">Количество по умолчанию</label>
					<input type="number" class="form-control " name="list[<?=$list?>][count_default]" style="width: 70px;" value="<?if(isset($arSupplier["settings_pricelist_detail"][$list]["count_default"])):?><?=$arSupplier["settings_pricelist_detail"][$list]["count_default"]?><?endif?>">
				</div>
			</div>
			
			<div class="col-lg-12" style="">
				<div class="col-lg-3 ">
					<label style="font-size: 11px;line-height: 11px;">Первая строка</label>
					<input type="number" class="form-control " name="list[<?=$list?>][start_row]" placeholder="Начальная строка" value="<?if(isset($arSupplier["settings_pricelist_detail"][$list]["start_row"])):?><?=$arSupplier["settings_pricelist_detail"][$list]["start_row"]?><?endif?>">
				</div>
				<div class="col-lg-3 ">
					<label style="font-size: 11px;line-height: 11px;">Удалять пробелы из артикула</label>
					<input type="checkbox" name="list[<?=$list?>][clear_space]" <?if($arSupplier["settings_pricelist_detail"][$list]["clear_space"] == "Y"):?>checked="checked"<?endif?>>
				</div>
				<?/*
				<div class="col-lg-3 ">
					<label style="font-size: 11px;line-height: 11px;">Бренд из названия листа</label>
					<input type="checkbox" name="list[<?=$list?>][brand_from_list]" <?if($arSupplier["settings_pricelist_detail"][$list]["brand_from_list"] == "Y"):?>checked="checked"<?endif?>>
				</div>
*/?>
			</div>
			<?endforeach?>
		</div>
		<div class="modal-footer" style="display: flow-root;">
			<button type="button" class="btn btn-default"  data-dismiss="modal"  aria-label="Close">Close</button>
			<button disabled="" type="submit" class="btn btn-primary" id="supplier_price_submit">Сохранить</button>
		</div>
	</form>
	<?endif?>
			<?

}else{
	?>
	<p>Произошла непредвиденная ошибка. Пожалуйста, обновите страницу и попробуйте позже</p>
	<?
}
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_after.php');