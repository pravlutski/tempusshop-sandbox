<?if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();
$this->setFrameMode(true);
global $USER;

$_REQUEST["control_site_id"] = mb_strtoupper($_REQUEST["control_site_id"]);

if(isset($_REQUEST["control_site_id"]) && in_array($_REQUEST["control_site_id"], $arResult["TYPE_PRICES_ID"]))
	$control_site = $_REQUEST["control_site_id"];
else{
	$control_site = "RU";
}
?>
<h1 class="page-header">Анализ цен</h1>
<form action="#" name="form-analysis" id="form-analysis">
<div id="analysis">
<div id="test"></div>
	<select class="form-control select_w" id="s-website" name="website">
		<?foreach ($arResult["TYPE_PRICES"] as $price):?>
		<option value="<?=$price['id']?>" <?if($control_site == $price['id']):?>selected<?endif?>><?=$price['name']?></option>
		<?endforeach?>
	</select>
	<button type="button" class="btn btn-primary btn_big_width" data-toggle="modal" data-target="#modal_settings_cost" id="btn_modal_settings_cost">Настройки РРЦ</button>
	<button type="button" class="btn btn-primary btn_big_width" data-toggle="modal" data-target="#modal_control_rrc" id="btn_modal_control_rrc">Контроль РРЦ</button>
	<button type="button" class="btn btn-primary btn_big_width" data-toggle="modal" data-target="#modal_individual_markup" id="btn_modal_ind_markup">Индивидуальные наценки</button>
	<a href="/admin/analiz/test_update_price.php" class="btn btn-default btn_big_width">Тест updatePrice</a>
	<div class="row_multy_multiple">
		<div class="col-sm-4 row">
			<select multiple="" class="form-control multiple_select" id="s-brand" style="height: 234px;width: 48%;margin: 0;float: left;">
			<?foreach($arResult["BRAND_LIST"] as $key => $arItem):?>
				<option value="<?=$arItem["id"]?>" <?if(($_REQUEST["control_rrc"] == "Y" && in_array($arItem["id"], $arResult["CONTROL_RRC"][$control_site])) || $_REQUEST["control_rrc2"] == "Y" || (!$_REQUEST["control_rrc"] && !$_REQUEST["control_rrc2"])):?>selected<?endif?>><?=$arItem["name"]?></option>
			<?endforeach?>
			</select>
			<select multiple="" class="form-control multiple_select" id="s-supplier" style="height: 234px;width: 48%;margin: 0;float: right;">
			<?foreach($arResult["SUPPLIER_LIST"] as $key => $arItem):?>
				<option value="<?=$arItem["id"]?>" selected><?=$arItem["name"]?></option>
			<?endforeach?>
			</select>
			<div class="col-sm-12 row" style="padding: 30px 15px 0 15px;">

			</div>
		</div>
		<div class="col-sm-4">
			<div class="panel panel-default" style="padding: 16px; margin-bottom: 12px; display:flex; flex-direction: row">
				<div style="display: flex; flex-direction: column; gap: 15px;">
					<input type="checkbox" class="btn-checkbox" id="price-with-discount" checked/>
					<label for="price-with-discount" style="">С учетом скидки</label>
					<input type="checkbox" class="btn-checkbox" id="only-minimal-price" checked/>
					<label for="only-minimal-price" style="">Минимальная цена</label>
					<input type="checkbox" class="btn-checkbox" id="only-active" checked/>
					<label for="only-active" style="">Учитывать акт.</label>
				</div>
				<div style="display:flex; flex-direction: column; gap: 15px;">
					<input type="checkbox" class="btn-checkbox" id="tbl-change"/>
					<label for="tbl-change">Изменения</label>
					<input type="checkbox" class="btn-checkbox" id="hide-rrc" <?if($_REQUEST["control_rrc"] == "Y"):?>checked<?endif?>>
					<label for="hide-rrc">Убрать РРЦ</label>
				</div>
				<div class="clear"></div>
			</div>
			<div class="panel panel-default" style="padding: 10px 0 10px 4px;">
				<p style="padding-left:10px; font-weight: bolder;z">Конкуренты</p>
				<div style="display:flex; flex-direction: row; padding: 8px;">
					<div style="display:flex; flex-direction: row">
						<input type="checkbox" class="btn-checkbox" id="price-competitors" name="price-competitors" checked/>
						<label for="price-competitors" style="margin-top: 8px">Цены</label>
					</div>
					<div style="display:flex; flex-direction: row">
						<input type="checkbox" class="btn-checkbox" id="price-competitors-act" name="price-competitors-act"/>
						<label for="price-competitors-act" style="margin-top: 8px">Исключить</label>
					</div>
					<div style="display:flex; flex-direction: row">
						<input type="text" class="form-control search_input" style="" id="margin-platform" value="5" placeholder="РЦ">
					</div>
				</div>
			</div>
		</div>
		<div class="col-sm-4 row">
			<div class="col-sm-12">
			<div class="panel panel-default" style="padding: 10px 0 10px 4px;">
				<p style="font-weight:bolder; padding-left: 10px;">Поиск</p>
				<div style="display:flex; flex-direction: row">
					<div class="" style="display:flex; flex-direction: column">
						<p style="margin: 2px 0 5px 13px;">По цене</p>
						<div style="flex-direction: row">
							<input type="text" class="form-control search_input" style="width: 75px !important;" id="search-price-from" placeholder="От">
							<input type="text" class="form-control search_input" style="width: 75px !important;" id="search-price-to" placeholder="До">
						</div>
					</div>
					<div class="" style="display:flex; flex-direction: column">
						<p style="margin: 2px 0 5px 13px;">По артикулу</p>
						<input type="text" class="form-control search_input" id="search-model" style="width: 165px;" placeholder="По артикулу">
					</div>
				</div>
				<div class="clear"></div>
			</div>
			</div>
			<div class="right_search" style="padding-top: 0px;">
				<button type="button" class="btn btn-warning fl-right btn-all-get-price" style="margin: 10px 0 0 0; display:none">Установить РРЦ</button>
				<div class="clear"></div>
			</div>
		</div>
	<div style="display:flex; flex-direction:row; width: 100%">
		<select class="form-control select_w" id="page_size">
			<option value="50">50</option>
			<option value="100">100</option>
			<option value="200">200</option>
			<option value="500">500</option>
			<option value="1000">1000</option>
			<option value="100000">Все</option>
		</select>
		<button type="button" class="btn btn-danger fl-right btn_big_width" id="save-changes" style="margin-left: auto">Сохранить изменения в каталоге</button>
	</div>
	<div class="row_multy_multiple" style="padding: 0;width: 100%;overflow: auto;">

	</div>
	</div>

	<div class="custom_table custom_table_margin" id="tbl-analysis">
	</div>
</div>
</form>

<div class="modal fade" id="modal_settings_cost" tabindex="-1" role="dialog" aria-labelledby="modal_settings_cost" aria-hidden="true">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
				<h4 class="modal-title" id="myModalLabel3434">Настройки РРЦ</h4>
			</div>
				<div class="modal-body modal_big_padding">
					<form class="form-horizontal" action="#" method="POST" name="settings-price" id="settings-price">
					</form>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-default"  data-dismiss="modal"  aria-label="Close">Выйти</button>
				</div>
		</div>
	</div>
</div>

<div class="modal fade" id="modal_edit_profile" tabindex="-1" role="dialog" aria-labelledby="modal_edit_profile" aria-hidden="true" style="display: none;">
	<div class="modal-dialog">
		<div class="modal-content">
		</div>
	</div>
</div>

<div class="modal fade" id="modal_control_rrc" tabindex="-1" role="dialog" aria-labelledby="modal_control_rrc" aria-hidden="true">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
				<h4 class="modal-title" id="myModalLabel3434">Бренды для которых производить контроль РРЦ</h4>
			</div>
			<div class="modal-body modal_big_padding" style="float: left;width: 100%;padding: 10px 0 10px 15px;">
				<form class="form-horizontal" action="#" method="POST" name="form-control-rrc" id="form-control-rrc">
					<div class="col-sm-3">
						<p>tempusshop.ru</p>
						<select multiple="" class="form-control multiple_select" id="s-control-brand" name="s-control-brand[RU][]" style="height: 140px;width: 100%;margin: 0;">
						<?foreach($arResult["BRAND_LIST"] as $key => $arItem):?>
							<option value="<?=$arItem["id"]?>" <?if(in_array($arItem["id"], (array)$arResult["CONTROL_RRC"]["RU"])):?>selected<?endif?>><?=$arItem["name"]?></option>
						<?endforeach?>
						</select>
					</div>
					<div class="col-sm-3">
						<p>tempus.by</p>
						<select multiple="" class="form-control multiple_select" id="s-control-brand" name="s-control-brand[BY][]" style="height: 140px;width: 100%;margin: 0;">
						<?foreach($arResult["BRAND_LIST"] as $key => $arItem):?>
							<option value="<?=$arItem["id"]?>" <?if(in_array($arItem["id"], (array)$arResult["CONTROL_RRC"]["BY"])):?>selected<?endif?>><?=$arItem["name"]?></option>
						<?endforeach?>
						</select>
					</div>
					<div class="col-sm-3">
						<p>tempusshop.pl</p>
						<select multiple="" class="form-control multiple_select" id="s-control-brand" name="s-control-brand[PL][]" style="height: 140px;width: 100%;margin: 0;">
						<?foreach($arResult["BRAND_LIST"] as $key => $arItem):?>
							<option value="<?=$arItem["id"]?>" <?if(in_array($arItem["id"], (array)$arResult["CONTROL_RRC"]["PL"])):?>selected<?endif?>><?=$arItem["name"]?></option>
						<?endforeach?>
						</select>
					</div>
					<div class="col-sm-3">
						<p>WB</p>
						<select multiple="" class="form-control multiple_select" id="s-control-brand" name="s-control-brand[WB][]" style="height: 140px;width: 100%;margin: 0;">
						<?foreach($arResult["BRAND_LIST"] as $key => $arItem):?>
							<option value="<?=$arItem["id"]?>" <?if(in_array($arItem["id"], (array)$arResult["CONTROL_RRC"]["WB"])):?>selected<?endif?>><?=$arItem["name"]?></option>
						<?endforeach?>
						</select>
					</div>
					<div class="col-sm-3">
						<p>WBTL</p>
						<select multiple="" class="form-control multiple_select" id="s-control-brand" name="s-control-brand[WBTL][]" style="height: 140px;width: 100%;margin: 0;">
						<?foreach($arResult["BRAND_LIST"] as $key => $arItem):?>
							<option value="<?=$arItem["id"]?>" <?if(in_array($arItem["id"], (array)$arResult["CONTROL_RRC"]["WBTL"])):?>selected<?endif?>><?=$arItem["name"]?></option>
						<?endforeach?>
						</select>
					</div>
					<div class="col-sm-3">
						<p>WBBY</p>
						<select multiple="" class="form-control multiple_select" id="s-control-brand" name="s-control-brand[WBBY][]" style="height: 140px;width: 100%;margin: 0;">
						<?foreach($arResult["BRAND_LIST"] as $key => $arItem):?>
							<option value="<?=$arItem["id"]?>" <?if(in_array($arItem["id"], (array)$arResult["CONTROL_RRC"]["WBBY"])):?>selected<?endif?>><?=$arItem["name"]?></option>
						<?endforeach?>
						</select>
					</div>
					<div class="col-sm-3">
						<p>YA</p>
						<select multiple="" class="form-control multiple_select" id="s-control-brand" name="s-control-brand[YA][]" style="height: 140px;width: 100%;margin: 0;">
						<?foreach($arResult["BRAND_LIST"] as $key => $arItem):?>
							<option value="<?=$arItem["id"]?>" <?if(in_array($arItem["id"], (array)$arResult["CONTROL_RRC"]["YA"])):?>selected<?endif?>><?=$arItem["name"]?></option>
						<?endforeach?>
						</select>
					</div>
					<div class="col-sm-3">
						<p>OZON/SBER</p>
						<select multiple="" class="form-control multiple_select" id="s-control-brand" name="s-control-brand[OS][]" style="height: 140px;width: 100%;margin: 0;">
						<?foreach($arResult["BRAND_LIST"] as $key => $arItem):?>
							<option value="<?=$arItem["id"]?>" <?if(in_array($arItem["id"], (array)$arResult["CONTROL_RRC"]["OS"])):?>selected<?endif?>><?=$arItem["name"]?></option>
						<?endforeach?>
						</select>
					</div>
					<div class="col-sm-3">
						<p>SBER</p>
						<select multiple="" class="form-control multiple_select" id="s-control-brand" name="s-control-brand[SB][]" style="height: 140px;width: 100%;margin: 0;">
							<?foreach($arResult["BRAND_LIST"] as $key => $arItem):?>
								<option value="<?=$arItem["id"]?>" <?if(in_array($arItem["id"], (array)$arResult["CONTROL_RRC"]["SB"])):?>selected<?endif?>><?=$arItem["name"]?></option>
							<?endforeach?>
						</select>
					</div>
					<div class="col-sm-3">
						<p>AVITO</p>
						<select multiple="" class="form-control multiple_select" id="s-control-brand" name="s-control-brand[AV][]" style="height: 140px;width: 100%;margin: 0;">
						<?foreach($arResult["BRAND_LIST"] as $key => $arItem):?>
							<option value="<?=$arItem["id"]?>" <?if(in_array($arItem["id"], (array)$arResult["CONTROL_RRC"]["AV"])):?>selected<?endif?>><?=$arItem["name"]?></option>
						<?endforeach?>
						</select>
					</div>
					<div class="col-sm-3">
						<p>KZ</p>
						<select multiple="" class="form-control multiple_select" id="s-control-brand" name="s-control-brand[KZ][]" style="height: 140px;width: 100%;margin: 0;">
							<?foreach($arResult["BRAND_LIST"] as $key => $arItem):?>
								<option value="<?=$arItem["id"]?>" <?if(in_array($arItem["id"], (array)$arResult["CONTROL_RRC"]["KZ"])):?>selected<?endif?>><?=$arItem["name"]?></option>
							<?endforeach?>
						</select>
					</div>
					<div class="col-sm-3">
						<p>KZ OZON</p>
						<select multiple="" class="form-control multiple_select" id="s-control-brand" name="s-control-brand[OZKZ][]" style="height: 140px;width: 100%;margin: 0;">
							<?foreach($arResult["BRAND_LIST"] as $key => $arItem):?>
								<option value="<?=$arItem["id"]?>" <?if(in_array($arItem["id"], (array)$arResult["CONTROL_RRC"]["OZKZ"])):?>selected<?endif?>><?=$arItem["name"]?></option>
							<?endforeach?>
						</select>
					</div>
					<div class="col-sm-3">
						<p>KZ TI</p>
						<select multiple="" class="form-control multiple_select" id="s-control-brand" name="s-control-brand[OZTI][]" style="height: 140px;width: 100%;margin: 0;">
							<?foreach($arResult["BRAND_LIST"] as $key => $arItem):?>
								<option value="<?=$arItem["id"]?>" <?if(in_array($arItem["id"], (array)$arResult["CONTROL_RRC"]["OZTI"])):?>selected<?endif?>><?=$arItem["name"]?></option>
							<?endforeach?>
						</select>
					</div>
					<br>
					<button type="submit" class="btn btn-primary" id="control-rrc-save" style="margin: 10px 0 0 0;">Сохранить</button>
				</form>
			</div>
			<div class="modal-footer">
				<div class="fl-left" style="margin: 3px 0 0 0;"><div class="info-text"></div></div>
				<button type="button" class="btn btn-default"  data-dismiss="modal"  aria-label="Close">Выйти</button>
			</div>
		</div>
	</div>
</div>

<div class="modal fade" id="modal_changes_log" tabindex="-1" role="dialog" aria-labelledby="modal_changes_log" aria-hidden="true" style="display: none;">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
				<h4 class="modal-title">Лог</h4>
			</div>
			<div class="modal-body modal_big_padding" style="max-height: 350px;overflow: auto;"></div>
			<div class="modal-footer">
				<button type="button" class="btn btn-default"  data-dismiss="modal"  aria-label="Close">Выйти</button>
			</div>
		</div>
	</div>
</div>

<? //require('indiv_markup_modal.php'); ?>

<?if($_REQUEST["control_rrc"] == "Y"):?>
<script>
	$("#price-with-discount").click();
</script>
<?endif?>
<script>
	getAnalysList();
</script>