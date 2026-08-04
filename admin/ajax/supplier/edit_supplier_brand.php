<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');

if(CModule::IncludeModule("panel.manager")){
	$objBrand = new CPanelBrand;
	$objSupplier = new CPanelSupplier;
	$objCurrency = new CPanelCurrency;
	
	global $USER;
	
	$arResult["SUPPLIER"] = $objSupplier->getDetail($_POST["supplierID"]);
	$arResult["BRAND"] = $objBrand->getDetail($_POST["brandID"]);
//prent($arResult["SUPPLIER"]["settings_brand_sale"]);
	$arResult["SUPPLIER"]["settings_brand_sale"] = json_decode($arResult["SUPPLIER"]["settings_brand_sale"], true);
//	$arResult["SUPPLIER"]["settings_brand_sale"] = unserialize($arResult["SUPPLIER"]["settings_brand_sale"]);
//prent($arResult["SUPPLIER"]["settings_brand_sale"]);
	$brandSale = $arResult["SUPPLIER"]["settings_brand_sale"][$arResult["BRAND"]["id"]];
//prent($arResult);
	if(!$arResult["SUPPLIER"] || !$arResult["BRAND"]){
		echo "Ошибка получения бренда или поставщика";
		return;
	}
	?>
	<div class="modal-header">
		<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
		<h4 class="modal-title">Поставщик - <?=$arResult["SUPPLIER"]["name"]?>, Бренд - <?=$arResult["BRAND"]["name"]?></h4>
	</div>
	<form class="form-horizontal" id="apply-supplier-brand">
		<input type="hidden" name="supplier-id" value="<?=$arResult["SUPPLIER"]["id"]?>">
		<input type="hidden" name="brand-id" value="<?=$arResult["BRAND"]["id"]?>">
		<div class="modal-body">
			<div class="row row_selects_modal">
				<div class="col-lg-2">
					<label>Акт.</label>
				</div>
				<div class="col-lg-8">
					<label>Регулярка</label>
				</div>
				<div class="col-lg-2">
					<label>Скидка</label>
				</div>
			</div>
			<div class="row row_selects_modal">
				<div id="wrap-brand-sales">
					<?foreach($brandSale as $key => $arBrand):?>
						<div class="list" data-key="<?=$key?>">
							<div class="col-lg-2">
								<input type="checkbox" name="brand[<?=$key?>][active]" value="Y" <?if($arBrand["active"]):?>checked<?endif?>>
							</div>
							<div class="col-lg-8">
								<input type="text" class="form-control" name="brand[<?=$key?>][regular]" value="<?=$arBrand["regular"]?>">
							</div>
							<div class="col-lg-2">
								<input type="text" class="form-control" name="brand[<?=$key?>][sale]" value="<?=$arBrand["sale"]?>">
							</div>
							<button type="button" class="close" style="right: -20px;top: 0px;"><span aria-hidden="true">×</span></button>
						</div>
					<?endforeach?>
				</div>
				<div class="col-lg-12">
					<button type="button" class="btn btn-primary btn-add-brand-sale">Добавить</button>
				</div>
			</div>
		</div>
		<div class="modal-footer" style="display: flow-root;">
			<button type="button" class="btn btn-default"  data-dismiss="modal"  aria-label="Close">Close</button>
			<button type="submit" class="btn btn-primary" id="edit_supplier_brand_submit">Сохранить</button>
		</div>
	</form>
			<?

}else{
	?>
	<p>Произошла непредвиденная ошибка. Пожалуйста, обновите страницу и попробуйте позже</p>
	<?
}
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_after.php');