<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');

if(CModule::IncludeModule("panel.manager")){
	$objSupplier = new CPanelSupplier;
	$objCurrency = new CPanelCurrency;

	$arResult["CURRENCY_LIST"] = $objCurrency->getList();//список валют
	$arResult["SUPPLIER_LIST"] = $objSupplier->getList($arFilter);//список поставщиков
	$arResult["PRICE_DEVIATION_ORDER"] = CProSet::getOption("PRICE_DEVIATION_ORDER");
	$arResult["PRICE_DEVIATION_TOP"] = CProSet::getOption("PRICE_DEVIATION_TOP");
	$arResult["PRICE_DEVIATION_FOREIGN"] = CProSet::getOption("PRICE_DEVIATION_FOREIGN");
	
	$arResult["TRANSIT_DAYS_RU"] = json_decode(CProSet::getOption("TRANSIT_DAYS_RU"), true);
	$arResult["TRANSIT_DAYS_BY"] = json_decode(CProSet::getOption("TRANSIT_DAYS_BY"), true);
	
	?>

	<script type="text/javascript">
		$(document).on('click', '.supp-type-btn', function(e){
			e.preventDefault();
			$('.local').hide();
			$('.foreign').hide();
			$('.' + $(this).val()).show();
			$('.supp-type-btn').removeClass('selected');
			$(this).addClass('selected');
		})
	</script>

	<style media="screen">
		.foreign{
			display: none;
		}
		.supp-type-btn{
			width: 50%;
			height: 40px;
			background-color: #337ab7;
			border: none;
			color: white;
		}
		.supp-type-btn:hover{
			font-weight: bolder;
		}
		.selected{
			background-color: #f0ad4e;
			color: black;
			font-weight: bolder;
		}
	</style>
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
				<h4 class="modal-title" id="myModalLabel3434">Список поставщиков</h4>
			</div>
			<form class="form-horizontal" id="apply-settings">
				<div class="modal-body modal_big_padding">
					<div style="width:100%; display:flex; flex-direction: row">
						<button class="supp-type-btn selected" value="local">Локальные</button>
						<button class="supp-type-btn" value="foreign">Иностранные</button>
					</div>
					<table class="table" id="sup-list">
						<thead>
							<tr>
								<th>Поставщик</th>
								<th></th>
							</tr>
						</thead>
						<tbody>
							<?foreach($arResult["SUPPLIER_LIST"] as $key => $arItem):?>
							<tr id="sup-tr-<?=$arItem["id"]?>" class="<?echo $arItem['opt_supplier'] == 'Y' ? 'foreign' : 'local';?>">
								<td><?=$arItem["name"]?> (<?=$arItem["id"]?>)</td>
								<td class="right">
								<button type="button" class="btn btn-primary btn-supplier-edit" data-toggle="modal" data-target="#modal_edit_suppliers" data-id="<?=$arItem["id"]?>">Изменить</button>
								<button class="btn btn-danger btn-supplier-delete" data-id="<?=$arItem["id"]?>" onClick='confirmDelete(this);return false;'>Удалить</button>
								</td>
							</tr>
							<?endforeach?>
						</tbody>
					</table>
					<button type="button" class="btn btn-primary btn_add_suppliers btn-supplier-edit"  data-toggle="modal" data-target="#modal_edit_suppliers">Добавить</button>
					<div class="exchange_rates">
						<div class="row">
							<div class="col-lg-4">
								<p>Дни перемещений</p>
							</div>
							<div class="col-lg-4">
								<p style="font-size: 11px;">Москва-Минск</p>
								<select class="form-control" name="transit-days-ru[]" multiple style="width:100%;height:150px;">
									<?foreach(getDaysWeek() as $k => $v):?>
										<option value="<?=$v["id"]?>" <?if(is_array($arResult["TRANSIT_DAYS_RU"]) && in_array($v["id"], $arResult["TRANSIT_DAYS_RU"])):?>selected="selected"<?endif?>><?=$v["name"]?></option>
									<?php endforeach; ?>
								</select>
							</div>
							<div class="col-lg-4">
								<p style="font-size: 11px;">Минск-Москва</p>
								<select class="form-control" name="transit-days-by[]" multiple style="width:100%;height:150px;">
									<?foreach(getDaysWeek() as $k => $v):?>
										<option value="<?=$v["id"]?>" <?if(is_array($arResult["TRANSIT_DAYS_BY"]) && in_array($v["id"], $arResult["TRANSIT_DAYS_BY"])):?>selected="selected"<?endif?>><?=$v["name"]?></option>
									<?php endforeach; ?>
								</select>
							</div>
						</div>
						<div class="row">
							<div class="col-lg-12">
								<label>Автоматиеское распределение по приоритету</label>
							</div>
						</div>
						<div class="row">
							<div class="col-lg-6">
								<p>Заказы</p>
							</div>
							<div class="col-lg-6">
								<p style="font-size: 11px;">Максимальное отклонение</p>
								<input type="text" class="form-control input_fix_w" name="price-deviation-order" placeholder="<?=$arResult["PRICE_DEVIATION_ORDER"]?>" value="<?=$arResult["PRICE_DEVIATION_ORDER"]?>">
							</div>
						</div>
						<div class="row">
							<div class="col-lg-6">
								<p>Запасы</p>
							</div>
							<div class="col-lg-6">
								<p style="font-size: 11px;">Максимальное отклонение</p>
								<input type="text" class="form-control input_fix_w" name="price-deviation-top" placeholder="<?=$arResult["PRICE_DEVIATION_TOP"]?>" value="<?=$arResult["PRICE_DEVIATION_TOP"]?>">
							</div>
						</div>
						<div class="row">
							<div class="col-lg-6">
								<p>Иностранные поставщики</p>
							</div>
							<div class="col-lg-6">
								<p style="font-size: 11px;">Максимальное отклонение</p>
								<input type="text" class="form-control input_fix_w" name="price-deviation-foreign" placeholder="<?=$arResult["PRICE_DEVIATION_FOREIGN"]?>" value="<?=$arResult["PRICE_DEVIATION_FOREIGN"]?>">
							</div>
						</div>
					</div>
					<div class="exchange_rates">
						<label>Курсы валют</label>
						<?if($arResult["CURRENCY_LIST"]):?>
						<?foreach($arResult["CURRENCY_LIST"] as $key => $arItem):?>
						<div class="row">
							<div class="col-lg-6">
								<input type="text" disabled class="form-control select_w" value="<?=$arItem["amount"]?> <?=$arItem["id"]?>">
							</div>
							<div class="col-lg-6">
								<input required="" type="text" class="form-control input_fix_w" name="currency[<?=$arItem["id"]?>]" placeholder="<?=$arItem["rate"]?>" value="<? echo str_replace('.', ',', $arItem["rate"]);?>">RUR
							</div>
						</div>
						<?endforeach?>
						<?endif?>
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-default"  data-dismiss="modal"  aria-label="Close">Close</button>
					<button disabled="" type="submit" class="btn btn-primary" id="settings_submit">Сохранить</button>
				</div>
			</form>
			<?

}else{
	?>
	<p>Произошла непредвиденная ошибка. Пожалуйста, обновите страницу и попробуйте позже</p>
	<?
}
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_after.php');
