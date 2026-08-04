<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
use Bitrix\Main\Loader;

if(Loader::includeModule('panel.manager')){
	$objBrand = new CPanelBrand;
	$objSupplier = new CPanelSupplier;
	$objCurrency = new CPanelCurrency;
	
	$service = PanelManager::getPriceManager();
	$typePrices = $service->getTypePrices(fullList: true);

	global $USER;

	$arCurrency = $objCurrency->getList();
	$supp_id = intval($_POST["id"]);
	$arBrands = $objBrand->getList();
	if($supp_id > 0){
		$arSupplier = $objSupplier->getDetail( $supp_id );

		if(!$USER->isAdmin() && $arSupplier["opt_supplier"] == "Y"){
			return;
		}
		$arSupplier["settings"] = json_decode( $arSupplier["settings"], true );
		$arSupplier["settings_pricelist"] = json_decode( $arSupplier["settings_pricelist"], true );
		$arSupplier["settings_type_sklad"] = json_decode( $arSupplier["settings_type_sklad"], true );
		$arResult = array(
			'id'			=> $supp_id,
			'new'			=> false,
			'supplier'		=> $arSupplier,
			'currency'		=> $arCurrency, 
			'brands'		=> $arBrands
		);
	}else{
		$arResult = array(
			'id'			=> false,
			'new'			=> true,
			'supplier'		=> array("name" => "Новый поставщик"),
			'currency'		=> $arCurrency,
			'brands'		=> $arBrands
		);
	}//prent($arResult);
	$arResult["supplier"]["settings_brand_sale"] = json_decode($arResult["supplier"]["settings_brand_sale"], true);
	
	$correctPrice = $arResult["supplier"]["settings"]["correct_price"] ?? [];
	?>
	<div class="modal-header">
		<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
		<h4 class="modal-title" id="myModalLabel23232">Поставщики - <?=$arResult["supplier"]["name"]?> (ID: <?=$arResult["supplier"]["id"]?>) </h4>
	</div>
	<form class="form-horizontal" id="apply-supplier">
		<input type="hidden" name="supplier-id" value="<?if($arResult["new"] === false):?><?=$id?><?endif?>">
		<div class="modal-body">
			<div class="col-lg-12">
				<div class="col-lg-8 row" style="margin: 0 0 10px 10px;">
					<?//if($arResult["new"] === true):?>
						<input required="" type="text" class="form-control input_fix_w2" name="name" placeholder="Введите имя" value="<?if($arResult["new"] === false):?><?=$arResult["supplier"]["name"]?><?endif?>">
					<?//endif?>
				</div>
				<div class="col-lg-4 row" style="">
					<input type="text" class="form-control input_fix_w2" name="sort" placeholder="Сортировка" value="<?=$arResult["supplier"]["sort"]?>">
				</div>
			</div>
			
			<div class="row row_selects_modal">
				<div class="col-lg-2">
					<label>Бренд</label>
				</div>
				<div class="col-lg-1" style="margin-left: -20px;margin-right: 20px;">
					<label>Приоритет</label>
				</div>
				<div class="col-lg-2">
					<label>Скидкa</label>
				</div>
				<?foreach($typePrices as $price):?>
					<div class="brand-col">
						<label><?=$price['id']?></label>
					</div>
				<?endforeach?>
			</div>
			<div class="row row_selects_modal" style="border-bottom: 1px solid black;margin-bottom: 10px;">
				<div class="col-lg-5"></div>
				<?foreach($typePrices as $price):?>
					<?
					$column_active = $price['column_active'];
					?>
					<div class="brand-col">
						<input type="checkbox" name="<?=$column_active?>" class="brand-change-all" <?if($arSupplier[$column_active] == "Y"):?>checked="checked"<?endif?>>
					</div>
				<?endforeach?>
			</div>
			<div class="row row_selects_modal">
				<div id="wrap-brand-list" class="test-12">
					<?if(isset($arResult["supplier"]["settings"]["brand"]) && count($arResult["supplier"]["settings"]["brand"]) > 0):?>
						<?foreach($arResult["supplier"]["settings"]["brand"] as $key => $arBrand):?>
						<div class="list" data-key="<?=$key?>">
							<div class="col-lg-2">
								<select class="form-control select_w" name="brand[<?=$key?>][id]">
									<?foreach($arResult["brands"] as $arItem):?>
										<option value="<?=$arItem["id"]?>" <?if($arBrand["id"] == $arItem["id"]):?>selected="selected"<?endif?>><?=$arItem["name"]?></option>
									<?endforeach?>
								</select>
							</div>
							<div class="col-lg-1"  style="margin-left: -20px;margin-right: 20px;">
								<select class="form-control select_w" name="brand[<?=$key?>][priority]" style="width: 80px;">
									<option value="0" <?if($arBrand["priority"] == 0):?>selected="selected"<?endif?>>0</option>
									<option value="1" <?if($arBrand["priority"] == 1):?>selected="selected"<?endif?>>1</option>
									<option value="2" <?if($arBrand["priority"] == 2):?>selected="selected"<?endif?>>2</option>
									<option value="3" <?if($arBrand["priority"] == 3):?>selected="selected"<?endif?>>3</option>
									<option value="4" <?if($arBrand["priority"] == 4):?>selected="selected"<?endif?>>4</option>
									<option value="5" <?if($arBrand["priority"] == 5):?>selected="selected"<?endif?>>5</option>
									<option value="6" <?if($arBrand["priority"] == 6):?>selected="selected"<?endif?>>6</option>
									<option value="7" <?if($arBrand["priority"] == 7):?>selected="selected"<?endif?>>7</option>
									<option value="8" <?if($arBrand["priority"] == 8):?>selected="selected"<?endif?>>8</option>
									<option value="9" <?if($arBrand["priority"] == 9):?>selected="selected"<?endif?>>9</option>
									<option value="10" <?if($arBrand["priority"] == 10):?>selected="selected"<?endif?>>10</option>
								</select>
							</div>
							<div class="col-lg-2">
								<input type="text" class="form-control"  style="width: 45px;" name="brand[<?=$key?>][sale]" placeholder="<?=$arBrand["sale"]?>" value="<?=$arBrand["sale"]?>">
							</div>
							<?foreach($typePrices as $price):?>
								<?
								$column_active = $price['column_active'];
								?>
								<div class="brand-col">
									<input type="checkbox" name="brand[<?=$key?>][<?=$column_active?>]" class="check_<?=$column_active?>" <?if($arBrand[$column_active] == "Y"):?>checked="checked"<?endif?>>
								</div>
							<?endforeach?>
							<?if($id > 0):?>
							<button type="button" style="position: absolute;right: -5px;top: 0px;padding: 0;cursor: pointer;border: 0;float: left;<?if(!$arResult["supplier"]["settings_brand_sale"][$arBrand["id"]]):?>background: 0 0;<?endif?>" class="btn-edit-supplier-brand" data-toggle="modal" data-target="#modal_edit_supplier_brand" data-brandID="<?=$arBrand["id"]?>" data-supplierID="<?=$id?>">
								<img src="/bitrix/templates/admin_panel/images/pencil.png" style="width:15px;">
							</button>
							<?endif?>
							<button type="button" class="close" style="right: -20px;top: 0px;"><span aria-hidden="true">×</span></button>
						</div>
						<?endforeach?>
					<?endif?>
				</div>
				<div class="col-lg-12">
					<button type="button" class="btn btn-primary btn_add_suppliers btn-add-brand">Добавить</button>
				</div>
			</div>
			
			<div class="col-lg-12 panel panel-default" style="padding-bottom: 10px;margin: 10px 0 0 0;">
				<p style="font-weight: bold;margin: 7px 0 0 15px;">Корректировка цен</p>
				<?foreach($typePrices as $price):?>
				<div class="col-lg-1 ">
					<label style="font-size: 11px;line-height: 11px;"><?=$price['id']?></label>
					<input type="number" class="form-control " style="width: 60px;" name="correct_price[<?=$price['id']?>]" value="<?if(isset($correctPrice[$price['id']])):?><?=$correctPrice[$price['id']]?><?endif?>">
				</div>
				<?endforeach?>
			</div>
			<div class="col-lg-12">
				<p style="font-weight: bold;margin: 7px 0 0 15px;">Столбец в прайсе</p>
				<div class="col-lg-2 ">
					<label style="font-size: 11px;line-height: 11px;">Бренд</label>
					<input type="number" class="form-control " name="col_brand" value="<?if(isset($arResult["supplier"]["settings_pricelist"]["col_brand"])):?><?=$arResult["supplier"]["settings_pricelist"]["col_brand"]?><?endif?>">
				</div>
				<div class="col-lg-2 ">
					<label style="font-size: 11px;line-height: 11px;">Артикул</label>
					<input type="number" class="form-control " name="col_article" value="<?if(isset($arResult["supplier"]["settings_pricelist"]["col_article"])):?><?=$arResult["supplier"]["settings_pricelist"]["col_article"]?><?endif?>">
				</div>
				<div class="col-lg-2 ">
					<label style="font-size: 11px;line-height: 11px;">Цена</label>
					<input type="number" class="form-control " name="col_price" value="<?if(isset($arResult["supplier"]["settings_pricelist"]["col_price"])):?><?=$arResult["supplier"]["settings_pricelist"]["col_price"]?><?endif?>">
				</div>
				<div class="col-lg-2 ">
					<label style="font-size: 11px;line-height: 11px;">Кол/наличие</label>
					<input type="number" class="form-control " name="col_count" value="<?if(isset($arResult["supplier"]["settings_pricelist"]["col_count"])):?><?=$arResult["supplier"]["settings_pricelist"]["col_count"]?><?endif?>">
				</div>
				<div class="col-lg-2 ">
					<label style="font-size: 11px;line-height: 11px;">Кратность</label>
					<input type="number" class="form-control " name="col_multiplicity" value="<?if(isset($arResult["supplier"]["settings_pricelist"]["col_multiplicity"])):?><?=$arResult["supplier"]["settings_pricelist"]["col_multiplicity"]?><?endif?>">
				</div>


			</div>


			<div class="col-lg-12">
				<div class="col-lg-3 " id="col-quntity-flag" <?if($arResult["supplier"]["settings_pricelist"]["col_count"] <= 0):?>style="display: none;"<?endif?>>
					<label style="font-size: 11px;line-height: 11px;">Флаг наличия</label>
					<select class="form-control select_w" name="quntity_flag">
						<option value="" disabled <?if(!$arResult["supplier"]["settings_pricelist"]["quntity_flag"]):?>selected<?endif?>> --- Выберите ---</option>
						<option value="str" <?if($arResult["supplier"]["settings_pricelist"]["quntity_flag"] == "str"):?> selected="selected"<?endif?>>Строка</option>
						<option value="int" <?if($arResult["supplier"]["settings_pricelist"]["quntity_flag"] == "int"):?> selected="selected"<?endif?>>Число</option>
					</select>
				</div>
				<div class="col-lg-4 " id="col-quntity-value" style="<?if((!$arResult["supplier"]["settings_pricelist"]["quntity_flag"] || $arResult["supplier"]["settings_pricelist"]["quntity_flag"] == "str") && $arResult["supplier"]["settings_pricelist"]["col_count"] > 0):?>display: block;<?else:?>display: none;<?endif?>">
					<label style="font-size: 11px;line-height: 11px;">Значение флага наличия</label>
					<input type="text" class="form-control " name="quntity_value" style="width: 98px;" value="<?if(isset($arResult["supplier"]["settings_pricelist"]["quntity_value"])):?><?=$arResult["supplier"]["settings_pricelist"]["quntity_value"]?><?endif?>">
				</div>
				<div class="col-lg-4 " id="col-quntity-default" <?if($arResult["supplier"]["settings_pricelist"]["col_count"] > 0 && $arResult["supplier"]["settings_pricelist"]["quntity_flag"] == "int"):?>style="display: none;"<?endif?>>
					<label style="font-size: 11px;line-height: 11px;">Количество по умолчанию</label>
					<input type="number" class="form-control " name="count_default" style="width: 70px;" value="<?if(isset($arResult["supplier"]["settings_pricelist"]["count_default"])):?><?=$arResult["supplier"]["settings_pricelist"]["count_default"]?><?endif?>">
				</div>
				<div class="col-lg-2 " id="col-priority-default">
					<label style="font-size: 11px;line-height: 11px;">Приоритет по умолчанию</label>
					<select class="form-control select_w" name="priority_default" style="width: 120px;">
						<option value="" <?if(!isset($arResult["supplier"]["settings_pricelist"]["priority_default"])):?>selected="selected"<?endif?>>--- не выбрано ---</option>
						<option value="0" <?if(isset($arResult["supplier"]["settings_pricelist"]["priority_default"]) && $arResult["supplier"]["settings_pricelist"]["priority_default"] == 0):?>selected="selected"<?endif?>>0</option>
						<option value="1" <?if($arResult["supplier"]["settings_pricelist"]["priority_default"] == 1):?>selected="selected"<?endif?>>1</option>
						<option value="2" <?if($arResult["supplier"]["settings_pricelist"]["priority_default"] == 2):?>selected="selected"<?endif?>>2</option>
						<option value="3" <?if($arResult["supplier"]["settings_pricelist"]["priority_default"] == 3):?>selected="selected"<?endif?>>3</option>
						<option value="4" <?if($arResult["supplier"]["settings_pricelist"]["priority_default"] == 4):?>selected="selected"<?endif?>>4</option>
						<option value="5" <?if($arResult["supplier"]["settings_pricelist"]["priority_default"] == 5):?>selected="selected"<?endif?>>5</option>
						<option value="6" <?if($arResult["supplier"]["settings_pricelist"]["priority_default"] == 6):?>selected="selected"<?endif?>>6</option>
						<option value="7" <?if($arResult["supplier"]["settings_pricelist"]["priority_default"] == 7):?>selected="selected"<?endif?>>7</option>
						<option value="8" <?if($arResult["supplier"]["settings_pricelist"]["priority_default"] == 8):?>selected="selected"<?endif?>>8</option>
						<option value="9" <?if($arResult["supplier"]["settings_pricelist"]["priority_default"] == 9):?>selected="selected"<?endif?>>9</option>
						<option value="10" <?if($arResult["supplier"]["settings_pricelist"]["priority_default"] == 10):?>selected="selected"<?endif?>>10</option>
					</select>
				</div>
				<div class="col-lg-2 " id="col-priority-default">
					<label style="font-size: 11px;line-height: 11px;">Склад для магазина</label>
					<select class="form-control select_w" name="store_id" style="width: 120px;">
						<option value="" <?if(!$arResult["supplier"]["store_id"]):?>selected="selected"<?endif?>>--- не выбрано ---</option>
						<option value="1" <?if($arResult["supplier"]["store_id"] == 1):?>selected="selected"<?endif?>>Немига-3</option>
						<option value="2" <?if($arResult["supplier"]["store_id"] == 2):?>selected="selected"<?endif?>>Новокузнецкая</option>
					</select>
				</div>
			</div>

			<div class="col-lg-12" style="">
				<div class="col-lg-3 ">
					<label style="font-size: 11px;line-height: 11px;">Первая строка</label>
					<input type="number" class="form-control " name="start_row" placeholder="Начальная строка" value="<?if(isset($arResult["supplier"]["settings_pricelist"]["start_row"])):?><?=$arResult["supplier"]["settings_pricelist"]["start_row"]?><?endif?>">
				</div>
				<div class="col-lg-3 " style="display:none">
					<label style="font-size: 11px;line-height: 11px;">Удалять пробелы из артикула</label>
					<input type="checkbox" name="clear_space" <?if($arResult["supplier"]["settings_pricelist"]["clear_space"] == "Y"):?>checked="checked"<?endif?>>
				</div>
				<div class="col-lg-3 ">
					<label style="font-size: 11px;line-height: 11px;">Бренд из названия листа</label>
					<input type="checkbox" name="brand_from_list" <?if($arResult["supplier"]["settings_pricelist"]["brand_from_list"] == "Y"):?>checked="checked"<?endif?>>
				</div>
				<div class="col-lg-3 ">
					<label style="font-size: 11px;line-height: 11px;">Обрабатываемые листы (через запятую)</label>
					<input type="text" class="form-control " name="num_lists" placeholder="Обрабатываемые листы (через запятую)" value="<?if(isset($arResult["supplier"]["settings_pricelist"]["num_lists"])):?><?=$arResult["supplier"]["settings_pricelist"]["num_lists"]?><?endif?>">
					<?if(isset($arResult["supplier"]["settings_pricelist"]["num_lists"])):?>
						<?
						$lists = explode(",", $arResult["supplier"]["settings_pricelist"]["num_lists"]);
						?>
						<?if(is_array($lists) && count($lists) > 1):?>
						<button type="button" class="btn btn-sm btn-primary btn_pricelist_lists" data-toggle="modal" data-target="#modal_pricelist_edit" data-id="<?=$supp_id?>">Настройка</button>
						<?endif?>
					<?endif?>
				</div>
			</div>
			<div class="container panel panel-default" style="width: 100%;float: left;margin: 10px 0 0 0;">
			<div class="col-lg-12" style="">
				<p style="font-weight: bold;margin: 7px 0 0 15px;">Доставка</p>
				<div class="col-lg-3 " id="col-quntity-default">
					<label style="font-size: 11px;line-height: 11px;">Готовность заказа, дней</label>
					<input type="number" class="form-control " name="day_delivery" value="<?if(isset($arResult["supplier"]["settings_pricelist"]["day_delivery"])):?><?=$arResult["supplier"]["settings_pricelist"]["day_delivery"]?><?endif?>">
				</div>
				<div class="col-lg-3 " id="col-quntity-default">
					<label style="font-size: 11px;line-height: 11px;">Местоположение</label>
					<select class="form-control" name="location" style="width:100%;">
						<option value="moscow" <?if($arResult["supplier"]["settings_pricelist"]["location"] == "moscow"):?>selected="selected"<?endif?>>Москва</option>
						<option value="minsk" <?if($arResult["supplier"]["settings_pricelist"]["location"] == "minsk"):?>selected="selected"<?endif?>>Минск</option>
					</select>
				</div>
				<?/*<div class="col-lg-3 " id="col-quntity-default">
					<label style="font-size: 11px;line-height: 11px;">Дней доставки по BY</label>
					<input type="number" class="form-control " name="day_delivery_by" value="<?if(isset($arResult["supplier"]["settings_pricelist"]["day_delivery_by"])):?><?=$arResult["supplier"]["settings_pricelist"]["day_delivery_by"]?><?endif?>">
				</div>
				<div class="col-lg-3 " id="col-quntity-default">
					<label style="font-size: 11px;line-height: 11px;">Дней доставки по PL</label>
					<input type="number" class="form-control " name="day_delivery_pl" value="<?if(isset($arResult["supplier"]["settings_pricelist"]["day_delivery_pl"])):?><?=$arResult["supplier"]["settings_pricelist"]["day_delivery_pl"]?><?endif?>">
				</div>*/
				?>
				<div class="col-lg-3 " id="col-quntity-default">
					<label style="font-size: 11px;line-height: 11px;">Дни работы</label>
					<select class="form-control" name="working_week[]" multiple style="width:100%;height:150px;">
						<?foreach(getDaysWeek() as $k => $v):?>
							<option value="<?=$v["id"]?>" <?if(is_array($arResult["supplier"]["settings_pricelist"]["working_week"]) && in_array($v["id"], $arResult["supplier"]["settings_pricelist"]["working_week"])):?>selected="selected"<?endif?>><?=$v["name"]?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="col-lg-3 " id="col-quntity-default">
					<label style="font-size: 11px;line-height: 11px;">Время работы</label>
					<select class="form-control select_w" name="working_time">
						<?for($i = 6; $i <= 22; $i++):?>
							<option value="<?=$i?>" <?if(isset($arResult["supplier"]["settings_pricelist"]["working_time"]) && $arResult["supplier"]["settings_pricelist"]["working_time"] == $i):?>selected="selected"<?endif?>><?=$i?></option>
						<?endfor?>
					</select>
				</div>
			</div>

			<div class="col-lg-12" style="margin-bottom:50px;">
				<p style="font-weight: bold;margin: 7px 0 0 15px;">Тип склада</p>
				<div class="col-lg-6" style="">
					<select class="form-control" id="type-sklad-select" name="type[]" multiple style="width:100%;height:150px;">
						<?php foreach (TYPE_SKLAD_CONST as $key => $value): ?>
							<option value="<?=$key?>" <?if (in_array($key,$arSupplier["settings_type_sklad"] ?? [])) { echo 'selected';}?>><?=$key?></option>
						<?php endforeach; ?>
					</select>
		  	</div>
				<div class="col-lg-6" style="">
					<textarea readonly style="height: 150px;resize: none;" class="form-control input_fix_w2" type="text"  id="type-sklad-input"></textarea>
				</div>
				<script>
				  var select = document.getElementById("type-sklad-select");
				  var input = document.getElementById("type-sklad-input");
					var selectedOptions = Array.from(select.selectedOptions).map(function(option) {
						return option.value;
					});
					input.value = selectedOptions.join(", ");
				  select.addEventListener("change", function() {
				    var selectedOptions = Array.from(select.selectedOptions).map(function(option) {
				      return option.value;
				    });
				    input.value = selectedOptions.join(", ");
				  });
				</script>
			</div>

			</div>
			<?/*<div class="col-lg-12" style="">
				<p style="font-weight: bold;margin: 7px 0 0 15px;">Автопарсер</p>
				<div class="col-lg-4 ">
					<label style="font-size: 11px;line-height: 11px;">Файл</label>
					<input type="input" class="form-control " name="filename" placeholder="Файл" value="<?if(isset($arResult["supplier"]["settings_pricelist"]["filename"])):?><?=$arResult["supplier"]["settings_pricelist"]["filename"]?><?endif?>">
				</div>
			</div>
			<hr class="line_form_full">*/?>
			<div class=" col-lg-12" style="margin: 10px 0 0 0;">
				<div class="col-lg-3">
					<label>Наценка</label>
					<input type="input" class="form-control " name="margin" placeholder="Наценка" value="<?if(isset($arResult["supplier"]["settings_pricelist"]["margin"])):?><?=$arResult["supplier"]["settings_pricelist"]["margin"]?><?endif?>">
				</div>
				<?
				/*
				<div class="col-lg-3">
					<label style="position: relative;">Округление<span class="badge" style="font-size: 10px;position: absolute;top: -7px;right: -20px;background: black;color: white;cursor: pointer;" data-toggle="tooltip" data-placement="top" data-html="true"
						data-original-title="Кратность округления после применения наценки.<br>
						Пример для 1123.8393: <br>'-2' = 1100; <br>'-1' = 1120; <br>'0' = 1124; <br>'1' = 1123.8;
						<br>'2' = 1123.84 <br>'3' = 1123.839">?</span>
					</label>
					<input type="number" class="form-control " style="width: 70px;" name="margin_round" placeholder="Округление после применения наценки" value="<?if(isset($arResult["supplier"]["settings_pricelist"]["margin_round"])):?><?=$arResult["supplier"]["settings_pricelist"]["margin_round"]?><?endif?>">
				</div>
				*/?>
				<div class="col-lg-3">
					<label style="display: block;">Валюта</label>
					<select class="form-control select_w" name="currency">
						<option value="RUB" <?if(isset($arResult["supplier"]["settings"]["currency"]) && $arResult["supplier"]["settings"]["currency"] == "RUB"):?>selected="selected"<?endif?>>RUB</option>
						<?foreach($arResult["currency"] as $arItem):?>
							<option value="<?=$arItem["id"]?>" <?if(isset($arResult["supplier"]["settings"]["currency"]) && $arResult["supplier"]["settings"]["currency"] == $arItem["id"]):?>selected="selected"<?endif?>><?=$arItem["id"]?></option>
						<?endforeach?>
					</select>
				</div>
				<div class="col-lg-3" style="display:none">
					<label>Валюта списка</label>
					<select class="form-control select_w" name="currency_list">
						<option value="RUB" <?if(!$arResult["supplier"]["settings"]["currency_list"] || $arResult["supplier"]["settings"]["currency_list"] == "RUB"):?>selected="selected"<?endif?>>RUB</option>
						<?foreach($arResult["currency"] as $arItem):?>
							<option value="<?=$arItem["id"]?>" <?if(isset($arResult["supplier"]["settings"]["currency_list"]) && $arResult["supplier"]["settings"]["currency_list"] == $arItem["id"]):?>selected="selected"<?endif?>><?=$arItem["id"]?></option>
						<?endforeach?>
					</select>
				</div>

			</div>

			<div class="container panel panel-default" style="width: 100%;float: left;margin: 10px 0 0 0;">
				<div class="col-lg-12" style="">
					<div class="row">
						<p style="font-weight: bold;margin: 7px 0 0 15px;">MC</p>
						<div class="col-lg-12" style="margin: 0 0 10px 10px;">
							<div class="row">
							<?//if($arResult["new"] === true):?>
								<input required="" type="text" class="form-control input_fix_w2" name="mc_name" placeholder="Название МС" value="<?=$arResult["supplier"]["settings"]["mc_name"]?>">
							<?//endif?>
							</div>
						</div>
						<div class="col-lg-12 " id="col-quntity-default">
							<div class="row">
							<input type="checkbox" name="mc_return" <?if($arResult["supplier"]["settings"]["mc_return"] == "Y"):?>checked="checked"<?endif?> value="Y">Возможность возврата

							</div>
						</div>
						<div class="col-lg-12 " id="col-quntity-default">
							<div class="row">
								<input type="checkbox" name="opt_supplier" <?if($arResult["supplier"]["opt_supplier"] == "Y"):?>checked="checked"<?endif?>>Иностранный поставщик
							</div>
						</div>
						<div class="col-lg-12 " id="col-quntity-default">
							<div class="row">
								<input type="checkbox" name="nds" <?if($arResult["supplier"]["nds"] == "Y"):?>checked="checked"<?endif?>>НДС
							</div>
						</div>
						<div class="col-lg-12 " id="col-quntity-default">
							<div class="row">
								<input type="checkbox" name="is_warehouse" <?if($arResult["supplier"]["is_warehouse"] == "Y"):?>checked="checked"<?endif?>>Является складом
							</div>
						</div>
					</div>
				</div>

			</div>
			<?/*<div class=" col-lg-12" style="margin: 10px 0 0 0;">
				<div class="col-lg-4 ">
					<label style="font-size: 11px;line-height: 11px;">Участвует в опт прайсе</label>
					<input type="checkbox" name="opt_price" <?if($arResult["supplier"]["settings_pricelist"]["opt_price"] == "Y"):?>checked="checked"<?endif?>>
				</div>
			</div>*/?>
<!-- Настройки снижения себестоимости -->

			<!-- <div id="suppliers-discount-settings" class="panel panel-default sds-set">
				<h3>Снижение себестоимости</h2>
				<div id="sds-default" class="">

				</div>

				<div id="sds-profiles">
					<h4>Профили</h3>
						<div class="sds-buttons">
							<button type="button" class="btn btn-primary" id="add-profile-sds">Добавить профиль</button>
							<button type="button" class="btn btn-primary" id="save-settings-sds">Применить</button>
						</div>
					<div class="sds-list">

					</div>
				</div>
			</div> -->

		</div>
		<div class="modal-footer" style="display: flow-root;">
			<button type="button" class="btn btn-default"  data-dismiss="modal"  aria-label="Close">Close</button>
			<button disabled="" type="submit" class="btn btn-primary" id="supplier_submit">Сохранить</button>
		</div>
	</form>
<script>
$(document).ready(function(){
    $('#modal_edit_suppliers [data-toggle="tooltip"]').tooltip();
});

</script>
<style>
#modal_edit_suppliers .tooltip {
    width: 250px!important;
}
#wrap-brand-list > div > div:nth-child(2) > select {

}
.flex-el div{
	width: 40px;
	  align-items: center;
	  display: flex;
	  text-align: center;
}
.brand-col{
	display: inline-block;
	width: 35px;
	text-align: center;
	margin: 0 5px 0 5px;
}
</style>
			<?

}else{
	?>
	<p>Произошла непредвиденная ошибка. Пожалуйста, обновите страницу и попробуйте позже</p>
	<?
}
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_after.php');
