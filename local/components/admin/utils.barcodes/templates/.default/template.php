<?if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();
$this->setFrameMode(true);
global $USER;

use Bitrix\Main\Page\Asset;
//Asset::getInstance()->addJs("/bitrix/templates/admin_courier/js/datatables_all.js");
Asset::getInstance()->addCss("/bitrix/templates/admin_courier/css/datatables.min.css");



Asset::getInstance()->addJs("/bitrix/templates/admin_courier/js/jquery-ui.min.js");
Asset::getInstance()->addJs("/bitrix/templates/admin_courier/js/bootstrap.js");

//Asset::getInstance()->addCss("/bitrix/templates/admin_courier/css/datepicker.css");
Asset::getInstance()->addCss("/bitrix/templates/admin_courier/css/jquery-ui.min.css");

//Asset::getInstance()->addJs("/bitrix/templates/admin_panel/js/jquery-ui-timepicker-addon.js");
//Asset::getInstance()->addJs("/bitrix/templates/admin_panel/js/moment.min.js");


Asset::getInstance()->addCss("/bitrix/templates/admin_panel/assets/bulma-calendar-master/css/bulma-calendar.min.css");
Asset::getInstance()->addJs("/bitrix/templates/admin_panel/assets/bulma-calendar-master/js/bulma-calendar.min.js");


$activeTab = $_REQUEST['active_tab'] ?? 'FBS';

if ($_POST["cabinet"]) {
	if (is_array($_POST["cabinet"]))
		$activeCabinet = $_POST["cabinet"];
	else
		$activeCabinet = [$_POST["cabinet"]];
} else {
	if ($activeTab == 'BY') {
		$activeCabinet = [
			'WB_BY', 
		];
	} else {
		$activeCabinet = [
			'WB_WR', 
			'WB_IP', 
			'OZON_IP', 
			'YANDEX', 
		];
	}

}
?>
<div class="header" style="margin: 0 0 15px 0;">
	<h1 class="page-header" style="display: inline;">Сборка</h1>
	<div class="" style="display: inline;float: right;">
		<a href="/admin/utilities/barcodes_v1/scan_journal.php" target="_blank" class="btn btn-primary">Журнал</a>
		<button type="button" id="settingsBarcode" class="btn btn-settings">
			<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
				<circle cx="12" cy="12" r="3"></circle>
				<path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
			</svg>
			Настройки
		</button>
	</div>
</div>
<div class="col-sm-12 row">
	<div class="tabs">
		<div class="tab <?=($activeTab === 'FBS') ? 'active' : ''?>" data-tab="FBS">FBS</div>
		<div class="tab <?=($activeTab === 'FBO') ? 'active' : ''?>" data-tab="FBO">FBO</div>
		<div class="tab <?=($activeTab === 'BY') ? 'active' : ''?>" data-tab="BY">BY</div>
	</div>
	<div class="tab-content">
		<!-- Вкладка FBS -->
		<div class="tab-pane <?=($activeTab === 'FBS') ? 'active' : ''?>" id="fbs-tab">

			<div class="col-sm-12 row">
				<div class="col-sm-4 row">
					<form action="/admin/utilities/barcodes_v1/" method="POST">
						<input type="hidden" name="active_tab" value="<?=$activeTab?>">

						<div class="col-sm-12">
							<div class="col-sm-12 row">
								<textarea name="article_all" class="form-control" id="block_data" style="min-height: 150px;"><?=$_POST["article_all"]?></textarea>
								<input type="submit" class="btn btn-primary" name="art_wb_submit" style="margin:10px 0 10px 0;" value="Загрузить">
							</div>
						</div>

					</form>
				</div>
				<div class="col-sm-4 row">
					<div class="col-sm-12 row">
						<input type="text" class="form-control scan-barcode" name="scan_barcode" autocomplete="off" placeholder="Сканирование" value="">
					</div>
					<div class="col-sm-12 row scan-barcode-history"></div>
				</div>
				<div class="col-sm-4 row">
					<form action="/admin/utilities/barcodes_v1/" method="POST" style="float: right;">
						<div class="col-sm-12 row">
							<input type="text" name="date_to" value="<?=$arParams["date_to"]?>" id="datepickerDateFbs" class="form-control input is-hidden2" autocomplete="off" style="">
							<input type="hidden" name="active_tab" value="<?=$activeTab?>">
						</div>
						<div class="col-sm-12 row">
							<select name="cabinet[]" class="form-control select_w" style="width: 100%; margin: 5px 0 0 0;height: 140px;" multiple>
								<option value="SDEK" <?if(in_array("SDEK", $activeCabinet)):?>selected<?endif?>>SDEK</option>
								<option value="WB_WR" <?if(in_array("WB_WR", $activeCabinet)):?>selected<?endif?>>WB WR</option>
								<option value="WB_IP" <?if(in_array("WB_IP", $activeCabinet)):?>selected<?endif?>>WB IP</option>
								<option value="OZON_IP" <?if(in_array("OZON_IP", $activeCabinet)):?>selected<?endif?>>OZON IP</option>
								<option value="YANDEX" <?if(in_array("YANDEX", $activeCabinet)):?>selected<?endif?>>YANDEX</option>
								<option value="EXPRESS" <?if(in_array("EXPRESS", $activeCabinet)):?>selected<?endif?>>EXPRESS</option>
								<option value="AVITO" <?if(in_array("AVITO", $activeCabinet)):?>selected<?endif?>>AVITO</option>
							</select>
						</div>
						<div class="col-sm-12 row">
							<input type="checkbox" name="only_unsent" id="unsent" value="1" <?if($arResult["ONLY_UNSENT"]):?>checked<?endif?>><label for="unsent" style="margin: 0px 0 0 5px;">Не отправлены</label>
						</div>
						<div class="col-sm-12 row">
							<input type="submit" class="btn btn-primary" name="order_marketplace_submit" style="margin:10px 0 10px 0;" value="Загрузить">
						</div>
					</form>
				</div>
			</div>


		</div>
		<!-- Вкладка FBO -->
		<div class="tab-pane <?=($activeTab === 'FBO') ? 'active' : ''?>" id="fbo-tab">
			<div class="col-sm-12 row">
				<form action="/admin/utilities/barcodes_v1/" method="POST">
					<input type="hidden" name="active_tab" value="<?=$activeTab?>">
					<div class="col-sm-12">
						<div class="col-sm-3 row">
							<select name="cabinet" class="form-control select_w cabinet-market" style="width: 110px;">
								<option>--- Выбери кабинет ---</option>
								<option value="WB_WR" <?if($_POST["cabinet"] && $_POST["cabinet"] == "WB_WR"):?>selected<?endif?>>WB WR</option>
								<option value="WB_IP" <?if($_POST["cabinet"] && $_POST["cabinet"] == "WB_IP"):?>selected<?endif?>>WB IP</option>
								<option value="OZON_IP" <?if($_POST["cabinet"] && $_POST["cabinet"] == "OZON_IP"):?>selected<?endif?>>OZON IP</option>
								<option value="21_VEK" <?if($_POST["cabinet"] && $_POST["cabinet"] == "21_VEK"):?>selected<?endif?>>21 VEK</option>
							</select>

						</div>
						<div class="col-sm-6 row">
							<?/*<input type="text" class="form-control" name="fbo_orders" placeholder="Введите заказы FBO" value="<?=$_POST["fbo_orders"] ?? ''?>">*/?>
							<textarea name="fbo_orders" class="form-control" placeholder="Введите заказы FBO" style="min-height: 100px;"><?=$_POST["fbo_orders"]?></textarea>
							<input type="submit" class="btn btn-primary" name="fbo_submit" style="margin:10px 0 10px 0;" value="Загрузить FBO">
						</div>
					</div>
					<div class="col-sm-12">
						<div class="col-sm-12 row">
							<input type="text" class="form-control scan-barcode" name="scan_barcode" autocomplete="off" placeholder="Сканирование" value="">
						</div>
					</div>
					<div class="col-sm-12">
						<div class="col-sm-12 row scan-barcode-history"></div>
					</div>
				</form>
			</div>
		</div>
		
		<!-- Вкладка BY -->
		
		<div class="tab-pane <?=($activeTab === 'BY') ? 'active' : ''?>" id="by-tab">
			<div class="col-sm-12 row">
				<div class="col-sm-4 row">
					<form action="/admin/utilities/barcodes_v1/" method="POST">
						<input type="hidden" name="active_tab" value="<?=$activeTab?>">

						<div class="col-sm-12">
							<div class="col-sm-12 row">
								<textarea name="article_all" class="form-control" id="block_data" style="min-height: 150px;"><?=$_POST["article_all"]?></textarea>
								<input type="submit" class="btn btn-primary" name="art_wb_submit" style="margin:10px 0 10px 0;" value="Загрузить">
							</div>
						</div>

					</form>
				</div>
				<div class="col-sm-4 row">
					<div class="col-sm-12 row">
						<input type="text" class="form-control scan-barcode" name="scan_barcode" autocomplete="off" placeholder="Сканирование" value="">
					</div>
					<div class="col-sm-12 row scan-barcode-history"></div>
				</div>
				<div class="col-sm-4 row">
					<form action="/admin/utilities/barcodes_v1/" method="POST" style="float: right;">
						<input type="hidden" name="active_tab" value="<?=$activeTab?>">
						<div class="col-sm-12 row">
							<input type="text" name="date_to" value="<?=$arParams["date_to_by"]?>" id="datepickerDateBy" class="form-control input is-hidden" autocomplete="off" style="">
						</div>
						<div class="col-sm-12 row">
							<select name="cabinet[]" class="form-control select_w" style="width: 100%; margin: 5px 0 0 0;height: 140px;" multiple>
								<option value="WB_BY" <?if(in_array("WB_BY", $activeCabinet) || !$_POST["cabinet"]):?>selected<?endif?>>WB BY</option>
								<option value="OZON_BY" <?if(in_array("OZON_BY", $activeCabinet)):?>selected<?endif?>>OZ BY</option>
							</select>
						</div>
						<div class="col-sm-12 row">
							<input type="checkbox" name="only_unsent" id="unsent_by" value="1" <?if($arResult["ONLY_UNSENT"]):?>checked<?endif?>><label for="unsent_by" style="margin: 0px 0 0 5px;">Не отправлены</label>
						</div>
						<div class="col-sm-12 row">
							<input type="submit" class="btn btn-primary" name="order_marketplace_submit" style="margin:10px 0 10px 0;" value="Загрузить">
							<?
							$url = [
								'order_export_list' => 'Y',
								'cabinet' => $activeCabinet,
								'date_to' => $arParams["date_to_by"],
							];
							$queryString = http_build_query($url);
							?>
							<a href="/admin/utilities/barcodes_v1/?<?=$queryString?>" class="btn btn-primary" style="margin:10px 0 10px 0;">Export</a>
						</div>
					</form>
				</div>
			</div>
		</div>
	</div>

</div>
</div>
<hr>
<script>
$(document).ready(function(){
	var calendar1 = new bulmaCalendar('#datepickerDateFbs', {
		dateFormat: 'dd.MM.yyyy',
		showButtons: false,
		closeOnSelect: true,
		validateLabel: 'Применить',
		cancelLabel: 'Отмена',
		clearLabel: 'Очистить',
		todayLabel: 'Сегодня',
		nowLabel: 'Сегодня',
		timeFormat: 'HH:mm'
	});
	var calendar2 = new bulmaCalendar('#datepickerDateBy', {
		dateFormat: 'dd.MM.yyyy',
		showButtons: false,
		closeOnSelect: true,
		validateLabel: 'Применить',
		cancelLabel: 'Отмена',
		clearLabel: 'Очистить',
		todayLabel: 'Сегодня',
		nowLabel: 'Сегодня',
		timeFormat: 'HH:mm'
	});
});
</script>

<?/*if(is_array($arResult["PURCHASE_LIST"]) && count($arResult["PURCHASE_LIST"]) > 0):?>
<form action="/bitrix/components/adm/utils.barcodes_new2/purchase_list_xls.php" method="POST" target="_blank">
	<textarea name="purchase_list" style="display:none;"><?=serialize($arResult["PURCHASE_LIST"])?></textarea>
	<input type="submit" class="btn btn-primary" name="submit" style="margin:10px 0 10px 0;" value="Список">
</form>
<?endif*/?>
<?if(count($arResult["ERRORS"]) > 0):?>
	<p style="font-size: 16px;color: red;width: 100%;display: block;overflow: auto;margin: 0;">Есть ошибки</p>
	<?foreach($arResult["ERRORS"] as $article):?>
	<p style="color: red;width: 100%;display: block;overflow: auto;margin: 0;"><?=$article?></p>
	<?endforeach?>
<?endif?>
<div id="stat-line" style="display: block;float: left;width: 100%;margin: 0;">
	<span class='all'></span>
	<span class='print'></span>
</div>
<form action="/admin/utilities/barcodes_v1/" method="POST">
	<textarea name="article_all" style="min-height: 150px;display:none;"><?=$_POST["article_all"]?></textarea>
	<table class="table table-<?=$activeTab?>" id="barcode" data-source="<?=implode(";", $arResult["ORDER_SOURCE"])?>">
		<thead>
			<tr>
				<th data-orderable="false">Фото</th>
				<?/*<th>Артикул WB</th>*/?>
				<th>Артикул</th>
				<th data-orderable="false">ШК</th>
				<th data-orderable="false">
					<?if($activeTab == 'FBS'):?>
					<button type="button" class="btn btn-primary" id="get_all_barcode">ALL</button>
					<?endif?>
				</th>
				<th>Sticker</th>
				<th></th>
				<th>№ заказа</th>
				<th data-orderable="false"></th>
				<th data-orderable="false"></th>
			</tr>
		</thead>
		<tbody>
			<?$i = 0;?>
			<?foreach($arResult['PURCHASE_ITEMS'] as $order_wb => $arMarket):?>
				<?
				$arItem = $arResult['ITEMS'][$arMarket["PRODUCT_ID"]];

				?>
				<tr>
					<td><a href="<?=$arItem["DETAIL_PAGE_URL"]?>" target="_blank"><?if($arItem["PICTURE_SRC"]):?><img data-src="<?=$arItem["PICTURE_SRC"]?>" class="lazy img-item"><?else:?><?=$arItem["NAME"]?><?endif?></a></td>
					<?/*<td><?=$arItem["PROPERTY_WBARTICLE_VALUE"]?></td>*/?>
					<td><?=$arItem["NAME"]?></td>
					<td>
						<input type="hidden" name="product_id[]" value="<?=$arItem["ID"]?>">
						<input type="hidden" name="barcode_original[<?=$arItem["ID"]?>]" value="<?=$arItem["PROPERTY_AEN_VALUE"]?>" id="barcode_original_<?=$arItem["ID"]?>">
						<?$prop_barcode = ($arResult["BARCODE_ACTIVE"][$arItem["ID"]] ? $arResult["BARCODE_ACTIVE"][$arItem["ID"]] : $arItem["PROPERTY_AEN_VALUE"]);?>
						<input type="text" class="form-control barcode" name="barcode[<?=$arItem["ID"]?>]" placeholder="<?//=$prop_barcode?>" value="" id="barcode_<?=$arItem["ID"]?>" >
						<?/*if($prop_barcode):?><p style="margin: 0;"><?=substr($prop_barcode, 0, -3);?><span style="font-size: 22px;margin: 0 0 0 3px;"><?=substr($prop_barcode, -3);?></span></p><?endif*/?>
						<?if(count_($arResult["BARCODE"][$arItem["ID"]]) > 0):?>
							<?foreach($arResult["BARCODE"][$arItem["ID"]] as $k => $b):?>
							<?//if($prop_barcode && $prop_barcode == $b) continue;?>
							<p style="margin: 0;"><?=substr($b, 0, -3);?><span style="font-size: 22px;margin: 0 0 0 3px;"><?=substr($b, -3);?></span></p>
							<?endforeach?>
						<?endif?>
						<button type="submit" class="btn btn-primary set_barcode" data-id="<?=$arItem["ID"]?>">Save</button>
					</td>
					<td>
						<button type="submit" class="btn btn-primary get_barcode" name="get_barcode[<?=$arItem["ID"]?>]" data-id="<?=$arItem["ID"]?>">Наш ШК</button>
						<button type="submit" class="btn btn-primary print_barcode" name="print_barcode[<?=$arItem["ID"]?>]" data-id="<?=$arItem["ID"]?>" data-article="<?=$arItem["PROPERTY_WBARTICLE_VALUE"]?>">Печать</button>
					</td>
					<td>
						<?if($arMarket["STICKER"]):?>
							<span style="font-size: 12px;display: block;" class="sticker6" ><?=$arMarket["STICKER"]["ORDER_ID_WB"]?></span>
							<span style="font-size: 12px;display: block;" class="sticker6" ><?=$arMarket["STICKER"]["STICKER_PART_A"]?></span>
							<span style="font-size: 30px;display: block;<?if($arResult["COUNT_ITEMS"][$arMarket["STICKER"]["STICKER_PART_B"]] > 1):?>color:red;<?endif?>"><?=$arMarket["STICKER"]["STICKER_PART_B"]?></span>
						<?endif?>
					</td>
					<td></td>
					<td><span style="font-size: 30px;display: block;"><?=$arMarket["ORDER_NUMBER_ID"]?></span></td>
					<td>
						<?=$arMarket["ORDER_COMMENTS"]?>
					</td>
					<td>
						<p style=""><b>Основной склад (<?=intval($arItem["SKLAD_CNT"])?>)</b></p>
						<?if(count_($arItem["LAST_PURCHASE"]) > 0):?>
							<?foreach($arItem["LAST_PURCHASE"] as $k => $arPurchase):?>
							<p style="margin: 0;"><b><?if($arPurchase["TYPE"] == "SALES_RETURN"):?>Возврат<?else:?><?=$arPurchase["AGENT"]?><?endif?></b>(<?=$arPurchase["QUANTITY"]?>)<br><?=date("d-m-Y H-i", strtotime($arPurchase["TIMESTAMP"]))?></p>
							<?endforeach?>
						<?endif?>
						<hr class="seporate-purchase">
						<?if($arResult["PURCHASE"][$arItem["ID"]]):?>
							<?foreach($arResult["PURCHASE"][$arItem["ID"]] as $k => $arPurchase):?>
							<?/*<p class="purchase"><b><?=$arResult["SUPPLIER_NAME"][$arPurchase["supp_id"]]?></b></br><?=date("d-m-Y H-i", strtotime($arPurchase["timestamp"]))?></p>*/?>
							<p class="purchase"><b><?=$arResult["SUPPLIER_NAME"][$arPurchase["supp_id"]]?></b> (<?=$arPurchase["count"]?>)</p>
							<?endforeach?>
						<?endif?>

					</td>

				</tr>
				<?$i++;?>
			<?endforeach?>

			<?$ii = 0;?>
			<?foreach($arResult['ORDER_ITEMS_MARKET'] as $key => $arMarket):?>
				<?
				$arItem = $arResult['ITEMS'][$arMarket["PRODUCT_ID"]];
				if ($arMarket['SOURCE'] == 'ozon_fbo') {
					$barcode = $arItem["PROPERTY_AEN_VALUE"];
				} elseif ($arMarket['SOURCE'] == 'wb_fbo') {
					$barcode = $arItem["PROPERTY_AEN2_VALUE"];
				} elseif ($arMarket['SOURCE'] == '21_vek') {
					if ($arItem["PROPERTY_BARCODES_VALUE"]) {
						$barcode = trim(explode(",", $arItem["PROPERTY_BARCODES_VALUE"])[0]);
					}
				}
				$ii++;
				?>
				<tr data-cnt="<?=$ii?>" class="<?if($arMarket["STICKER_PRINT"]):?>print_sticker<?endif?>"
					data-order-id="<?=$arMarket["ID"]?>"
					data-order-number-id="<?=$arMarket["ORDER_NUMBER_ID"]?>"
					data-order-source="<?=$arMarket["SOURCE"]?>"
					data-order-cabinet="<?=$arMarket["CABINET"]?>"
					data-order-market-number="<?=$arMarket["ORDER_MARKET_NUMBER"]?>"
					data-sticker="<?=($arMarket["STICKER"] ? true : false)?>"
					data-article="<?=$arMarket["ARTICLE"]?>"
					data-order-isgroup="<?=$arMarket["IS_GROUP"]?>"
					data-productId="<?=$arMarket["PRODUCT_ID"]?>"
					data-barcode-market="<?=$barcode?>"
					data-print-sticker="<?=$arMarket["STICKER_PRINT"]?>"
					data-numberId="<?=$arMarket["NUMBER_ID"]?>"
				>
					<td style="position: relative;">
						<a href="<?=$arItem["DETAIL_PAGE_URL"]?>" target="_blank"><?if($arItem["PICTURE_SRC"]):?><img data-src="<?=$arItem["PICTURE_SRC"]?>" class="lazy img-item"><?else:?><?=$arItem["NAME"]?><?endif?></a>
						<?if($arResult['EXCLUSIVE_LIST'][$arItem["PROPERTY_CML2_ARTICLE_VALUE"]]):?><span style="color: red;font-size: 107px;position: absolute;top: 0;">!</span><?endif?>
					</td>
					<td data-order="<?=$arMarket["SORT_ARTICLE"]?>"><?=$arMarket["ARTICLE"]?></td>
					<td>
						<input type="hidden" name="product_id[]" value="<?=$arItem["ID"]?>">
						<input type="hidden" name="barcode_original[<?=$arItem["ID"]?>]" value="<?=$arItem["PROPERTY_AEN_VALUE"]?>" id="barcode_original_<?=$arItem["ID"]?>">
						<?$prop_barcode = ($arResult["BARCODE_ACTIVE"][$arItem["ID"]] ? $arResult["BARCODE_ACTIVE"][$arItem["ID"]] : $arItem["PROPERTY_AEN_VALUE"]);?>
						<input type="text" class="form-control barcode" name="barcode[<?=$arItem["ID"]?>]" placeholder="<?//=$prop_barcode?>" value="" id="barcode_<?=$arItem["ID"]?>" >
						<?/*if($prop_barcode):?><p style="margin: 0;"><?=substr($prop_barcode, 0, -3);?><span style="font-size: 22px;margin: 0 0 0 3px;"><?=substr($prop_barcode, -3);?></span></p><?endif*/?>
						<?if(count_($arResult["BARCODE"][$arItem["ID"]]) > 0):?>
							<?foreach($arResult["BARCODE"][$arItem["ID"]] as $k => $b):?>
							<?//if($prop_barcode && $prop_barcode == $b) continue;?>
							<p style="margin: 0;"><?=substr($b, 0, -3);?><span style="font-size: 22px;margin: 0 0 0 3px;"><?=substr($b, -3);?></span></p>
							<?endforeach?>
						<?endif?>
						<button type="submit" class="btn btn-primary set_barcode" data-id="<?=$arItem["ID"]?>">Save</button>
					</td>
					<td>
						<?if($activeTab == 'FBS'):?>
							<button type="submit" class="btn-sm btn-primary get_barcode" name="get_barcode[<?=$arItem["ID"]?>]" data-id="<?=$arItem["ID"]?>">Наш ШК</button>
							<button type="submit" class="btn-sm btn-primary print_barcode" name="print_barcode[<?=$arItem["ID"]?>]" data-id="<?=$arItem["ID"]?>" data-article="<?=$arItem["PROPERTY_WBARTICLE_VALUE"]?>">Печать</button>
							<?if($arMarket["STICKER"]):?>
								<?if($arMarket["SOURCE"] == "wb"):?>
									<a href="/admin/utilities/barcodes_v1/print.php?source=wb&type_scan=manual&order_market_number=<?=$arMarket["ORDER_MARKET_NUMBER"]?>&order_id=<?=$arMarket["ID"]?>&product_id=<?=$arMarket["PRODUCT_ID"]?>&number_id=<?=$arMarket["NUMBER_ID"]?>" class="btn-sm btn-primary print_barcode_market" target="_blank">Печать WB</a>
								<?elseif($arMarket["SOURCE"] == "ozon"):?>
									<a href="/admin/utilities/barcodes_v1/print.php?source=ozon&type_scan=manual&order_market_number=<?=$arMarket["ORDER_MARKET_NUMBER"]?>&order_id=<?=$arMarket["ID"]?>&product_id=<?=$arMarket["PRODUCT_ID"]?>&number_id=<?=$arMarket["NUMBER_ID"]?>" class="btn-sm btn-primary print_barcode_market" target="_blank">Печать OZ</a>
								<?elseif($arMarket["SOURCE"] == "yandex"):?>
									<a href="/admin/utilities/barcodes_v1/print.php?source=yandex&type_scan=manual&order_market_number=<?=$arMarket["ORDER_MARKET_NUMBER"]?>&order_id=<?=$arMarket["ID"]?>&product_id=<?=$arMarket["PRODUCT_ID"]?>&number_id=<?=$arMarket["NUMBER_ID"]?>" class="btn-sm btn-primary print_barcode_market" target="_blank">Печать YA</a>
								<?elseif($arMarket["SOURCE"] == "sdek"):?>
									<a href="/admin/utilities/barcodes_v1/print.php?source=sdek&type_scan=manual&order_market_number=<?=$arMarket["ORDER_MARKET_NUMBER"]?>&order_id=<?=$arMarket["ID"]?>&product_id=<?=$arMarket["PRODUCT_ID"]?>&number_id=<?=$arMarket["NUMBER_ID"]?>" class="btn-sm btn-primary print_barcode_market" target="_blank">Печать SDEK</a>
								<?endif?>
							<?endif?>
							<?if($arMarket["SOURCE"] == "avito"):?>
								<a href="/admin/utilities/barcodes_v1/print.php?source=avito&type_scan=manual&order_market_number=<?=$arMarket["ORDER_MARKET_NUMBER"]?>&order_id=<?=$arMarket["ID"]?>&product_id=<?=$arMarket["PRODUCT_ID"]?>&number_id=<?=$arMarket["NUMBER_ID"]?>" class="btn-sm btn-primary print_barcode_market" target="_blank">Печать AV</a>
							<?endif?>
						<?elseif($activeTab == 'FBO'):?>
							<a href="/admin/utilities/barcodes_v1/print.php?source=fbo&type_scan=manual&barcode=<?=$barcode?>&order_id=<?=$arMarket["ID"]?>&product_id=<?=$arMarket["PRODUCT_ID"]?>&number_id=<?=$arMarket["NUMBER_ID"]?>&cabinet=<?=$_POST["cabinet"]?>" class="btn-sm btn-primary" target="_blank">Печать</a>
						<?elseif($activeTab == 'BY'):?>
							<?if($arMarket["STICKER"]):?>
								<?if($arMarket["SOURCE"] == "wb_by"):?>
									<a href="/admin/utilities/barcodes_v1/print.php?source=wb&type_scan=manual&order_market_number=<?=$arMarket["ORDER_MARKET_NUMBER"]?>&order_id=<?=$arMarket["ID"]?>&product_id=<?=$arMarket["PRODUCT_ID"]?>&number_id=<?=$arMarket["NUMBER_ID"]?>" class="btn-sm btn-primary print_barcode_market" target="_blank">Печать WB</a>
								<?endif?>
							<?endif?>
						<?endif?>
						<?/*if($arResult["ORDER_PRINT_HISTORY"][$arMarket["ID"]]):?>
							<?foreach($arResult["ORDER_PRINT_HISTORY"][$arMarket["ID"]] as $v):?>
								<p style="margin: 0;font-size: 11px;"><?=$v["TYPE_SCAN"]?> - <?=$v["TIMESTAMP"]?></p>
							<?endforeach?>
						<?endif*/?>
						<div class="info-history-order">
						<?if($arMarket["STICKER_PRINT_HISTORY"]):?>
							<?foreach($arMarket["STICKER_PRINT_HISTORY"] as $v):?>
								<p style="margin: 0;font-size: 11px;"><?=$v["TYPE_SCAN"]?> - <?=$v["TIMESTAMP"]?><span class="delete_print_item" data-id="<?=$v["ID"]?>" title="Удалить">❌</span></p>
							<?endforeach?>
						<?endif?>
						</div>
					</td>
					<td>
					<?if($arMarket["SOURCE"] == "wb" || $arMarket["SOURCE"] == "wb_by"):?>
						<?if($arMarket["STICKER"]):?>
							<span style="font-size: 12px;display: block;" class="sticker6 order-number" ><?=$arMarket["STICKER"]["ORDER_ID_WB"]?></span>
							<span style="font-size: 12px;display: block;" class="sticker6" ><?=$arMarket["STICKER"]["STICKER_PART_A"]?></span>
							<span style="font-size: 30px;display: block;<?if($arResult["COUNT_ITEMS"][$arMarket["STICKER"]["STICKER_PART_B"]] > 1):?>color:red;<?endif?>"><?=$arMarket["STICKER"]["STICKER_PART_B"]?></span>
						<?endif?>
					<?elseif($arMarket["SOURCE"] == "ozon"):?>
						<span class="inlight order-number"><?=$arMarket["OZON_NUMBER"]?></span>
					<?elseif($arMarket["SOURCE"] == "yandex"):?>
						<span class="inlight order-number"><?=$arMarket["ORDER_MARKET_NUMBER"]?></span>
					<?elseif($arMarket["SOURCE"] == "sdek"):?>
						<span class="inlight order-number"><?=$arMarket["ORDER_MARKET_NUMBER"]?></span>
					<?endif?>
					</td>
					<td><?if($arMarket["MAXYSS_OP_STICKER"]):?><span style="font-size: 12px;display: block;" class="sticker6" ><?=$arMarket["MAXYSS_OP_STICKER"]?></span><?endif?></td>
					<td data-order="<?=$arMarket["ORDER_NUMBER_ID"]?>">
						<span style="font-size: 16px;display: block;"><?=$arMarket["ORDER_NUMBER_ID"]?></span>
						<?if($arMarket["INSERT_HOUR"] > 96):?>
							<?$class = "danger-2";?>
						<?elseif($arMarket["INSERT_HOUR"] > 48):?>
							<?$class = "danger";?>
						<?else:?>
							<?$class = "";?>
						<?endif?>
						<span class="order-insert-date <?=$class?>"><?=$arMarket["INSERT_DATE"]?></span>
					</td>
					<td>
						<div class="info-history-control-mark">
						<?if($arMarket["STICKER_PRINT_HISTORY"]):?>
							<?
							$controlMarks = array_column($arMarket["STICKER_PRINT_HISTORY"], 'CONTROL_MARK');
							$controlMarks = array_diff($controlMarks, array(''));
							?>
							<?if(is_array($controlMarks) && count($controlMarks) > 0):?>
								<?=implode("<br>", $controlMarks)?>
							<?endif?>
						<?endif?>
						</div>
						<?=nl2br($arMarket["ORDER_COMMENTS"])?>
					</td>
					<td>
						<p style="margin: 0;">
							<b>Основной склад (<?=intval($arItem["SKLAD_CNT"])?>)</b>
						</p>
						<?if($arMarket["SOURCE"] == "wb_by"):?>
							<?$reserve = $arResult['RESERVED'][$arMarket["PRODUCT_ID"]];?>
							<?if($reserve):?>
								<p style="margin: 0;">(Резерв: <?=intval($reserve["RESERVED_s2"])?>)</p>
							<?endif?>
						<?endif?>
						<?if(count_($arItem["LAST_PURCHASE"]) > 0):?>
							<p style="margin: 5px 0 0 0;"></p>
							<?foreach($arItem["LAST_PURCHASE"] as $k => $arPurchase):?>
							<p style="margin: 0;"><b><?if($arPurchase["TYPE"] == "SALES_RETURN"):?>Возврат<?else:?><?=$arPurchase["AGENT"]?><?endif?></b>(<?=$arPurchase["QUANTITY"]?>)<br><?=date("d-m-Y H-i", strtotime($arPurchase["TIMESTAMP"]))?></p>
							<?endforeach?>
						<?endif?>
						<hr class="seporate-purchase">
						<?if($arMarket["SOURCE"] != "wb_by" && $arResult["PURCHASE"][$arItem["ID"]]):?>
							<?foreach($arResult["PURCHASE"][$arItem["ID"]] as $k => $arPurchase):?>
							<?/*<p class="purchase"><b><?=$arResult["SUPPLIER_NAME"][$arPurchase["supp_id"]]?></b></br><?=date("d-m-Y H-i", strtotime($arPurchase["timestamp"]))?></p>*/?>
							<p class="purchase"><b><?=$arResult["SUPPLIER_NAME"][$arPurchase["supp_id"]]?></b> (<?=$arPurchase["count"]?>)</p>
							<?endforeach?>
						<?endif?>
					</td>

				</tr>
				<?$i++;?>
			<?endforeach?>
			<?foreach($arResult['ORDER_ITEMS'] as $product_id => $ar):?>
				<?foreach($ar as $key => $order):?>
				<?//print_r($order);?>
				<?$arItem = $arResult["ITEMS"][$product_id];?>
				<tr data-order-id="<?=$order["ORDER_NUMBER_ID"]?>">
					<td><a href="<?=$arItem["DETAIL_PAGE_URL"]?>" target="_blank"><?if($arItem["PICTURE_SRC"]):?><img data-src="<?=$arItem["PICTURE_SRC"]?>" class="lazy img-item"><?else:?><?=$arItem["NAME"]?><?endif?></a></td>
					<td><?=$arItem["PROPERTY_CML2_ARTICLE_VALUE"]?></td>
					<td>
						<input type="hidden" name="product_id[]" value="<?=$arItem["ID"]?>">
						<input type="hidden" name="barcode_original[<?=$arItem["ID"]?>]" value="<?=$arItem["PROPERTY_AEN_VALUE"]?>" id="barcode_original_<?=$arItem["ID"]?>">
						<?$prop_barcode = ($arResult["BARCODE_ACTIVE"][$arItem["ID"]] ? $arResult["BARCODE_ACTIVE"][$arItem["ID"]] : $arItem["PROPERTY_AEN_VALUE"]);?>
						<input type="text" class="form-control barcode" name="barcode[<?=$arItem["ID"]?>]" placeholder="<?//=$prop_barcode?>" value="" id="barcode_<?=$arItem["ID"]?>" >

						<?/*if($prop_barcode):?><p style="margin: 0;"><?=substr($prop_barcode, 0, -3);?><span style="font-size: 22px;margin: 0 0 0 3px;"><?=substr($prop_barcode, -3);?></span></p><?endif*/?>
						<?if(count_($arResult["BARCODE"][$arItem["ID"]]) > 0):?>
							<?foreach($arResult["BARCODE"][$arItem["ID"]] as $k => $b):?>
							<?//if($prop_barcode && $prop_barcode == $b) continue;?>
							<p style="margin: 0;"><?=substr($b, 0, -3);?><span style="font-size: 22px;margin: 0 0 0 3px;"><?=substr($b, -3);?></span></p>
							<?endforeach?>
						<?endif?>
						<button type="submit" class="btn btn-primary set_barcode" data-id="<?=$arItem["ID"]?>">Save</button>
					</td>
					<td>
						<button type="submit" class="btn btn-primary get_barcode" name="get_barcode[<?=$arItem["ID"]?>]" data-id="<?=$arItem["ID"]?>">Наш ШК</button>
						<button type="submit" class="btn btn-primary print_barcode" name="print_barcode[<?=$arItem["ID"]?>]" data-id="<?=$arItem["ID"]?>" data-article="<?=$arItem["PROPERTY_WBARTICLE_VALUE"]?>">Печать</button>
					</td>
					<td>
					<?if ($order['SOURCE'] == 'OZ') {?>
						<span class="inlight"><?=$order["STICKER"]?></span>
					<?} else {?>
						<?=$order["STICKER"]?>
					<?}?>
					</td>
					<td></td>
					<td data-order="<?=$order["ORDER_NUMBER_ID"]?>">
						<?if ($order['SOURCE'] == 'OZ'): ?>
							<span style="font-size: 16px;display: block;<?if($order["ORDER_QUANTITY"] > 1 || $arResult["COUNT_ITEMS"][$order["ORDER_NUMBER_ID"]] > 1):?>color:red;<?endif?>"><?=$order["ORDER_NUMBER_ID"]?></span>
						<?else:?>
							<span style="font-size: 30px;display: block;<?if($order["ORDER_QUANTITY"] > 1 || $arResult["COUNT_ITEMS"][$order["ORDER_NUMBER_ID"]] > 1):?>color:red;<?endif?>"><?=$order["ORDER_NUMBER_ID"]?></span>
						<?endif?>
						<?if($order["INSERT_HOUR"] > 96):?>
							<?$class = "danger-2";?>
						<?elseif($order["INSERT_HOUR"] > 48):?>
							<?$class = "danger";?>
						<?else:?>
							<?$class = "";?>
						<?endif?>
						<span class="order-insert-date <?=$class?>"><?=$order["INSERT_DATE"]?></span>
					</td>
					<td>
							<?=$order["ORDER_COMMENTS"]?>
					</td>
					<td>
						<p style=""><b>Основной склад (<?=intval($arItem["SKLAD_CNT"])?>)</b></p>
						<?if(count_($arItem["LAST_PURCHASE"]) > 0):?>
							<?foreach($arItem["LAST_PURCHASE"] as $k => $arPurchase):?>
							<p style="margin: 0;"><b><?if($arPurchase["TYPE"] == "SALES_RETURN"):?>Возврат<?else:?><?=$arPurchase["AGENT"]?><?endif?></b>(<?=$arPurchase["QUANTITY"]?>)<br><?=date("d-m-Y H-i", strtotime($arPurchase["TIMESTAMP"]))?></p>
							<?endforeach?>
						<?endif?>
						<hr class="seporate-purchase">
						<?if($arResult["PURCHASE"][$arItem["ID"]]):?>
							<?foreach($arResult["PURCHASE"][$arItem["ID"]] as $k => $arPurchase):?>
							<?/*<p class="purchase"><b><?=$arResult["SUPPLIER_NAME"][$arPurchase["supp_id"]]?></b></br><?=date("d-m-Y H-i", strtotime($arPurchase["timestamp"]))?></p>*/?>
							<p class="purchase"><b><?=$arResult["SUPPLIER_NAME"][$arPurchase["supp_id"]]?></b> (<?=$arPurchase["count"]?>)</p>
							<?endforeach?>
						<?endif?>

					</td>
				</tr>
				<?$i++;?>
				<?endforeach?>
			<?endforeach?>
		</tbody>
	</table>

	<div id="group-orders"></div>
	<input type="submit" class="btn btn-primary" name="barcodes_submit" style="margin: 10px 0 0 0;" value="Сохранить">
</form>

<div id="settingsBarcodeModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Настройки сборки</h2>
            <span class="close">×</span>
        </div>
        <div class="modal-body">
            <form id="settingsBarcodeForm">
                <div class="settings-section">
                    <h3>Настройки</h3>
                    <div class="form-group">
                        <table class="table">
                            <tbody>
                                <tr>
                                    <td>Кол-во секунд до закрытия окна при печати</td>
                                    <td><input type='text' class='form-control' name='autoclose-print-window' value='<?=$arResult["SETTINGS"]['autoclose-print-window']?>'></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="form-group">
                        <p>Приоритет печати</p>
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Код площадки</th>
                                    <th>Название</th>
                                    <th>Приоритет</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($arResult["TRADING_PRIORITY_LIST"] as $code): ?>
								<?
								$data = $arResult["SETTINGS"]['trading_priority'][$code];
								?>
                                <tr>
                                    <td>
                                        <strong><?=htmlspecialchars($code)?></strong>
                                    </td>
                                    <td>
                                        <input type="text" 
                                               class="form-control" 
                                               name="trading[<?=htmlspecialchars($code)?>][name]" 
                                               value="<?=htmlspecialchars($data['name'])?>"
                                               placeholder="Введите название">
                                    </td>
                                    <td>
                                        <input type="number" 
                                               class="form-control" 
                                               name="trading[<?=htmlspecialchars($code)?>][priority]" 
                                               value="<?=htmlspecialchars($data['priority'])?>"
                                               min="0"
                                               step="1"
                                               placeholder="Приоритет">
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" id="saveSettingsBtn" class="btn btn-primary">Сохранить</button>
            <button type="button" id="cancelSettingsBtn" class="btn btn-default">Отмена</button>
        </div>
    </div>
</div>

<div id="groupOrderModal" class="modal">
    <div class="modal-content" style="max-width: 600px;">
        <div class="modal-header">
            <h2>Групповой заказ #<span id="groupOrderId"></span></h2>
            <span class="close-group-modal">×</span>
        </div>
        <div class="modal-body">
            <div class="group-order-info">
                <p><strong>Отсканированные товары:</strong></p>
                <div id="groupOrderItemsList" class="items-list">
                </div>
                <div class="group-order-summary">
                    <p><strong>Всего товаров: <span id="groupOrderTotalItems">0</span></strong></p>
                    <p><strong>Уникальных артикулов: <span id="groupOrderUniqueArticles">0</span></strong></p>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" id="closeGroupModalBtn" class="btn btn-primary">Закрыть</button>
        </div>
    </div>
</div>
<div id="controlMarkModal" class="modal" style="display: none;">
    <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header">
            <h2>Контрольная марка</h2>
            <span class="close">×</span>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <input type="text" 
                       id="controlMarkInput" 
                       class="form-control" 
                       placeholder="Введите 30-значную контрольную марку" 
                       maxlength="30"
                       autocomplete="off">
            </div>
            <div id="controlMarkError" style="color: red; display: none;"></div>
        </div>
        <div class="modal-footer">
            <button type="button" id="confirmControlMarkBtn" class="btn btn-primary">Отправить на печать</button>
            <button type="button" id="cancelControlMarkBtn" class="btn btn-default">Отмена</button>
        </div>
    </div>
</div>
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script>

jQuery.extend( jQuery.fn.dataTableExt.oSort, {
	"currency-pre": function ( a ) {
		//a = a.replace(/<\/?[^>]+(>|$)/g, "");
		//a = a.replace(/(<([^>]+)>)/ig, "");
		//a = a.replace(/<span[\s\w"\=-]*class="sticker6"[\s\w"\=-]*>[\s\w]*<\/span>/g, "");
		a = a.replace(/<span.*?class=(?:"|"(?:[^"]*)\s)sticker6(?:"|\s(?:[^"]*)").*?>(.*?)<\/span>/g,'');
		a = a.replace(/<\/?[^>]+(>|$)/g, "");

		a = (a==="-" || a==="") ? 0 : a.replace( /[^\d\-\.]/g, "" );
		return parseFloat( a );
	},

	"currency-asc": function ( a, b ) {
		return a - b;
	},

	"currency-desc": function ( a, b ) {
		return b - a;
	}
});

$(document).ready(function() {
    table = $('table#barcode').DataTable({
		searching: false,
        scrollCollapse: false,
		fixedHeader: false,
		fixedColumns: false,
		columnDefs : [
			{ targets: [5], type: 'currency' }
		],
        "paging":   false,
        "ordering": true,
		"order": [[ 1, "asc" ]],
        "info":     false,
		dom: 'Bfrtip',
		buttons: [
			{
				extend : 'excel',
				exportOptions: {
					columns: [1, 6, 7, 8]
				},
				"fnCellRender": function ( sValue, iColumn, nTr, iDataIndex ) {
					if ( iColumn === 2 ) {
						//feel free to modify the value here
						return sValue +" TableTools";
					}
					return sValue;
				}
			},
            'copy', 'csv', 'pdf', 'print'
        ],
        "language": {
		    "decimal":        ",",
			"thousands":      ".",
            "zeroRecords": "Nothing found - sorry",
            "info": "Показана страница _PAGE_ из _PAGES_",
            "sPrevious": "No records available",
            "infoFiltered": "(filtered from _MAX_ total records)"
        },
        /*initComplete: function() {
            addSeparateOrderClass(this);
        },
        drawCallback: function() {
            addSeparateOrderClass(this);
        }*/
    });
    // добавления separate-order
    /*function addSeparateOrderClass(table) {
		var order = table.order();
		var columnIndex = order[0][0];

        $('#barcode tbody tr').removeClass('separate-order');

		if (columnIndex === 6) {
			var rows = $('#barcode tbody tr');
			var prevOrderId = null;

			rows.each(function() {
				var currentOrderId = $(this).attr('data-order-id');

				if (prevOrderId !== null && currentOrderId !== prevOrderId) {
					$(this).addClass('separate-order');
				}

				prevOrderId = currentOrderId;
			});
		}

    }*/
	// Обработка сортировки
	table.on('order.dt', function () {
		$('#barcode tbody tr').removeClass('separate-order');

		let order = table.order();
		let sortColumnIndex = order[0][0];

		if (sortColumnIndex === 6) {
			let previousOrderId = null;

			table.rows({ order: 'applied' }).every(function () {
				let node = this.node();
				let currentOrderId = $(node).data('order-id');

				if (previousOrderId !== null && currentOrderId !== previousOrderId) {
					$(node).addClass('separate-order');
				}

				previousOrderId = currentOrderId;
			});
		} else if (sortColumnIndex === 1) {
			/*let previousOrderId = null;
			let currentGroupStartIndex = -1;
			let rows = table.rows({ order: 'applied' }).nodes();

			$(rows).each(function(index) {
				let currentOrderId = $(this).data('order-id');

				if (currentOrderId !== previousOrderId) {
					console.log('currentOrderId', currentOrderId, previousOrderId, index, currentGroupStartIndex);
					if (previousOrderId !== null && (index - currentGroupStartIndex) > 1) {

						//console.log('currentGroupStartIndex', currentGroupStartIndex);
						$(this).addClass('separate-order');
					}
					// новая группа
					currentGroupStartIndex = index;
				}

				previousOrderId = currentOrderId;
			});*/
			
			let previousOrderId = null;
			let rows = table.rows({ order: 'applied' }).nodes();

			$(rows).each(function(index) {
				let currentOrderId = $(this).data('order-id');
				let currentIsGroup = $(this).data('order-isgroup') == '1';
				
				if (currentOrderId !== previousOrderId && currentIsGroup) {
					$(this).addClass('separate-order');
				}
				
				previousOrderId = currentOrderId;
			});
		}

		var source = $('#barcode').attr("data-source");
		if (source == 'ozon' || source == 'wb') {
			var order_number_list = [];
			//
			table.rows({ order: 'applied' }).every(function () {
				let node = this.node();
				let val = $(node).find('.order-number').html();
				if (val && val.length > 0) {
					order_number_list.push(val);
				}
			});

			$("#order_market_number").val(order_number_list.join(","));
			console.log(order_number_list);
		}
	});

	$('table#barcode').trigger('order.dt');
});

document.addEventListener('DOMContentLoaded', function() {
  const lazyImages = document.querySelectorAll('.img-item.lazy');

  if ('IntersectionObserver' in window) {

    const imageObserver = new IntersectionObserver((entries, observer) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          const img = entry.target;

          img.src = img.dataset.src;

          img.classList.remove('lazy');

          observer.unobserve(img);

          img.onload = function() {
            img.style.opacity = 1;
          };

          img.onerror = function() {
            console.error('Ошибка загрузки изображения: ' + img.dataset.src);
          };
        }
      });
    }, {
      rootMargin: '100px',
      threshold: 0.1
    });

    //наблюдение за всеми lazy изображениями
    lazyImages.forEach(img => {
      img.style.opacity = 0;
      img.style.transition = 'opacity 0.3s';
      imageObserver.observe(img);
    });
  } else {
    lazyImages.forEach(img => {
      img.src = img.dataset.src;
      img.classList.remove('lazy');
    });
  }
});

$(document).ready(function() {
    var resetTimer;
    var resetTimerScan;
    var inputTimer;
    var lastInputTime = 0;

	var isPasting = false;
	var barcodeBuffer = '';
	var lastKeyTime = 0;
	if (!localStorage.getItem('groupOrders')) {
        localStorage.setItem('groupOrders', JSON.stringify({}));
    }


	var isScannerInput = false;

	$('.scan-barcode').on('paste', function(e) {
		e.preventDefault();
		isPasting = true;

		var pastedData = e.originalEvent.clipboardData.getData('text');
		pastedData = pastedData.replace(/\r?\n|\r|\t/g, '').trim();

		$(this).val(pastedData);

		if (pastedData.length >= 8) {
			handleInput(pastedData);
			$(this).val('');
		}

		isPasting = false;
	});

	$('.scan-barcode').on('input', function() {
		if (isPasting || isScannerInput) return;

		var barcode = $(this).val();
		barcode = barcode.replace(/\r?\n|\r|\t/g, '').trim();

		if (!barcode) return;

		lastInputTime = Date.now();
		clearTimeout(inputTimer);

		inputTimer = setTimeout(function() {
			if (Date.now() - lastInputTime > 500) {
				handleInput(barcode);
				$('.scan-barcode').val('');
			}
		}, 500);
	});

	$('.scan-barcode').on('keydown', function(e) {
		// Блокируем Enter
		if (e.key === 'Enter') {
			e.preventDefault();

			if (barcodeBuffer.length >= 8) {
				isScannerInput = true;
				handleInput(barcodeBuffer);
				barcodeBuffer = '';
				$(this).val('');
				isScannerInput = false;
			}
			return false;
		}

		if (e.key.length > 1 || e.ctrlKey || e.altKey || e.metaKey) return;

		barcodeBuffer += e.key;
		lastKeyTime = Date.now();

		// Помечаем как ввод от сканера
		isScannerInput = true;

		clearTimeout(inputTimer);
		inputTimer = setTimeout(function() {
			if (barcodeBuffer.length >= 8 && Date.now() - lastKeyTime > 100) {
				handleInput(barcodeBuffer);
				barcodeBuffer = '';
				$('.scan-barcode').val('');
				isScannerInput = false;
			}
		}, 150);
	});

	$('.scan-barcode').on('focus', function() {
		barcodeBuffer = '';
		isScannerInput = false;
		$(this).val('');
	});



    function handleInput(barcode) {
        clearTimeout(resetTimer);
        clearTimeout(resetTimerScan);
        processBarcode(barcode);
    }

    function processBarcode(barcode) {
		var type_order = $('.tab.active').attr('data-tab');
		var selectedCabinets = $('.tab-pane.active select[name="cabinet[]"]').val();
        $.ajax({
            url: '/local/components/admin/utils.barcodes/actions.php',
            method: 'POST',
            data: {
				action: 'scan_barcode',
				barcode: barcode,
				cabinets: selectedCabinets,
				type_order: type_order
			},
            dataType: 'json',
            success: function(response) {
                if(response.status === "ok") {
					console.log('processBarcode', response);
					if (type_order == 'FBS') {
						var $rows = $('#barcode tr[data-article="' + response.article + '"][data-sticker="1"]');
						$current_row = null;
						if (response.current_order) {
							var $current_row = $('#barcode tr[data-order-id="' + response.current_order.ID + '"][data-article="' + response.article + '"][data-sticker="1"]');
						}
						if ($current_row && $current_row.length) {
							checkRowsForPrint($current_row, 0, barcode);
						} else if ($rows.length && (!response.orders || response.orders.length == 0)) {
							console.log('processBarcode 1', response);
							checkRowsForPrint($rows, 0, barcode);
						} else {
							console.log('processBarcode 2', response);
							findOrderByBarcode(barcode);
						}
					} else if (type_order == 'FBO') {
						var $rows = $('#barcode tr[data-article="' + response.article + '"]');
						checkRowsForPrintFbo($rows, 0, barcode);
					} else if (type_order == 'BY') {
						var $rows = $('#barcode tr[data-article="' + response.article + '"][data-sticker="1"]');

						if ($rows.length && (!response.orders || response.orders.length == 0)) {
							checkRowsForPrint($rows, 0, barcode);
						} else {
							findOrderByBarcode(barcode);
						}
					}
                } else {
					addToHistoryFile({
						message: 'Баркода ' + barcode + ' нет в системе',
						type: 'error'
					});
                    highlightError();
                    startResetTimer();
                }
            },
            error: function(xhr, status, error) {
                highlightError();
                startResetTimer();
                console.error('Ошибка:', error);
            }
        });
    }

	function checkRowsForPrintFbo($rows, index, barcode) {
		if ($rows.length == 0) {
			showNotification('Не найден товар', 'error');
		}
		if(index >= $rows.length) {
			showNotification('Печать не требуется', 'error');
			return;
		}
		// кол-во отсканированных [data-sticker="1"]

		var $row = $rows.eq(index);
		var printSticker = $row.attr('data-print-sticker');

		console.log('printSticker', printSticker);
		if (printSticker == '1') {
			checkRowsForPrintFbo($rows, index + 1, barcode);
			return;
		}

		var marketNumber = $row.attr('data-order-market-number');
		var orderSource = $row.attr('data-order-source');
		var orderId = $row.attr('data-order-id');
		var orderNumberId = $row.attr('data-order-number-id');
		var article = $row.attr('data-article');
		var productId = $row.attr('data-productId');
		var numberId = $row.attr('data-numberId');

		$.ajax({
			url: '/local/components/admin/utils.barcodes/actions.php',
			method: 'POST',
			data: {
				type_order: 'FBO',
				action: 'check_print_sticker',
				order_id: orderId,
				product_id: productId,
				number_id: numberId
			},
			dataType: 'json',
			success: function(response) {
				if(response.status === "ok") {
					if(response.print_sticker !== true) {
						printOrder($row);
					} else {
						checkRowsForPrintFbo($rows, index + 1, barcode);
					}
				} else {
					addToHistoryFile({
						message: 'Ошибка проверки стикера для заказа ' + orderId,
						type: 'error'
					});
					checkRowsForPrintFbo($rows, index + 1, barcode);
				}
			},
			error: function(xhr, status, error) {
				console.error('Ошибка проверки стикера:', error);
				addToHistoryFile({
					message: 'Ошибка проверки стикера для заказа ' + orderId + ' ' + error,
					type: 'error'
				});
				checkRowsForPrintFbo($rows, index + 1, barcode);
			}
		});
	}

	function checkRowsForPrint($rows, index, barcode) {

		if(index >= $rows.length) {
			findOrderByBarcode(barcode);
			return;
		}

		var $row = $rows.eq(index);
		var orderSource = $row.attr('data-order-source');
		
		//if (orderSource === "wb_by") {
		//	checkRowsForPrint($rows, index + 1, barcode);
		//	return;
		//}
		
		var marketNumber = $row.attr('data-order-market-number');
		var orderId = $row.attr('data-order-id');
		var orderNumberId = $row.attr('data-order-number-id');
		var article = $row.attr('data-article');
		var isGroupOrder = $row.attr('data-order-isgroup') == true;
		var productId = $row.attr('data-productId');
		var numberId = $row.attr('data-numberId');

		$.ajax({
			url: '/local/components/admin/utils.barcodes/actions.php',
			method: 'POST',
			data: {
				type_order: $('.tab.active').attr('data-tab'),
				action: 'check_print_sticker',
				order_id: orderId,
				product_id: productId,
				number_id: numberId
			},
			dataType: 'json',
			success: function(response) {
				console.log('checkRowsForPrint response', response);
				if(response.status === "ok") {
					if(response.print_sticker !== true) {
						if (isGroupOrder) {
							handleGroupOrder($row, barcode);
						} else {
							clearAllGroupOrders();
							printOrder($row);
						}
					} else {
						checkRowsForPrint($rows, index + 1, barcode);
					}
				} else {
					addToHistoryFile({
						message: 'Ошибка проверки стикера для заказа ' + orderId,
						type: 'error'
					});
					checkRowsForPrint($rows, index + 1, barcode);
				}
			},
			error: function(xhr, status, error) {
				console.error('Ошибка проверки стикера:', error);
				addToHistoryFile({
					message: 'Ошибка проверки стикера для заказа ' + orderId + ' ' + error,
					type: 'error'
				});
				checkRowsForPrint($rows, index + 1, barcode);
			}
		});
	}

	function handleGroupOrder($row, barcode) {
		var orderId = $row.attr('data-order-id');
		var article = $row.attr('data-article');

		var groupOrders = JSON.parse(localStorage.getItem('groupOrders') || '{}');

		// Если заказа еще нет в очереди - инициализируем 
		if (!groupOrders[orderId]) {
			groupOrders = {};
			// Получаем все товары этого заказа для подсчета количества
			var $allOrderRows = $(`#barcode tr[data-order-id="${orderId}"][data-sticker="1"]`);
			var articleQuantities = {};

			$allOrderRows.each(function() {
				var art = $(this).attr('data-article');
				articleQuantities[art] = (articleQuantities[art] || 0) + 1;
			});

			groupOrders[orderId] = {
				orderId: orderId,
				articles: [],
				requiredArticles: articleQuantities, // {артикул: количество}
				rowData: {
					marketNumber: $row.attr('data-order-market-number'),
					orderSource: $row.attr('data-order-source'),
					orderNumberId: $row.attr('data-order-number-id'),
					article: article
				}
			};
		}

		// Проверяем, сколько уже отсканировано этого товара
		var currentScanned = groupOrders[orderId].articles.filter(art => art === article).length;
		var required = groupOrders[orderId].requiredArticles[article] || 0;

		// Добавляем отсканированный артикул только если еще не достигли нужного количества
		if (currentScanned < required) {
			groupOrders[orderId].articles.push(article);
			addToHistoryFile({
				message: `Добавлен в очередь заказа ${orderId}: ${article}`,
				type: 'info'
			});
		} else {
			addToHistoryFile({
				message: `Лимит товара ${article} для заказа ${orderId} достигнут`,
				type: 'warning'
			});
		}

		localStorage.setItem('groupOrders', JSON.stringify(groupOrders));

		// Обновляем отображение
		updateGroupOrdersDisplay();

		checkGroupOrderCompletion(orderId);
	}

	function checkGroupOrderCompletion(orderId) {
		var groupOrders = JSON.parse(localStorage.getItem('groupOrders') || '{}');
		var order = groupOrders[orderId];

		if (!order) return;

		// Подсчитываем количество отсканированных товаров по артикулам
		var scannedCounts = {};
		order.articles.forEach(article => {
			scannedCounts[article] = (scannedCounts[article] || 0) + 1;
		});

		// Проверяем, все ли товары заказа отсканированы в нужном количестве
		var allScanned = true;
		for (var article in order.requiredArticles) {
			if (order.requiredArticles.hasOwnProperty(article)) {
				var required = order.requiredArticles[article];
				var scanned = scannedCounts[article] || 0;

				if (scanned < required) {
					allScanned = false;
					break;
				}
			}
		}

		if (allScanned) {
			// Все отсканировано в нужном количестве
			printGroupOrder(order);

			delete groupOrders[orderId];
			localStorage.setItem('groupOrders', JSON.stringify(groupOrders));
			updateGroupOrdersDisplay();
		}
	}

	function printGroupOrder(order) {
		// временный row для печати
		var $tempRow = $('<div>').attr({
			'data-order-market-number': order.rowData.marketNumber,
			'data-order-source': order.rowData.orderSource,
			'data-order-number-id': order.rowData.orderNumberId,
			'data-article': order.rowData.article,
			'data-group-order': true,
			'data-order-id': order.orderId
		});

		printOrder($tempRow);

		// Обновляем статус печати для всех товаров заказа
		$(`#barcode tr[data-order-id="${order.orderId}"][data-sticker="1"]`).each(function() {
			var $row = $(this);
			$row.addClass('print_sticker');
			//setPrintOrder($row.attr('data-order-id'), true);

			var countPrint = $('tr.print_sticker').length;
			$('#stat-line span.print').text("Напечатано стикеров - " + countPrint);
		});

		addToHistoryFile({
			message: `Групповой заказ ${order.orderId} отправлен на печать`,
			type: 'success'
		});
		
		speakText("Распечатана этикетка группового заказа");
		showGroupOrderModal(order);
	}

    function startResetTimer(timer = 5000) {
        clearTimeout(resetTimerScan);
        resetTimerScan = setTimeout(function() {
            resetBarcodeState();
        }, 1);

        clearTimeout(resetTimer);
        resetTimer = setTimeout(function() {
            resetInfoState();
        }, timer);
    }

    function findOrderByBarcode(barcode) {
		var selectedCabinets = $('.tab-pane.active select[name="cabinet[]"]').val();
        $.ajax({
            url: '/local/components/admin/utils.barcodes/actions.php',
            method: 'POST',
            data: {
				type_order: $('.tab.active').attr('data-tab'),
				action: 'get_orders',
				cabinets: selectedCabinets,
                barcode: barcode
            },
            dataType: 'json',
            success: function(response) {
                if(response.status === "ok") {
					if (response.orders && response.orders.length > 0) {
						var outputText = '<p>' + response.article + "<br>";
						response.orders.forEach(order => {
							//outputText += `${order.ID} ${order.TRADING_PLATFORM} - ${order.QUANTITY}` + "<br>";
							outputText += `${order.ID} ${order.TRADING_PLATFORM} (${order.SHELF_NAME})` + "<br>";
						});
						outputText += '</p>';
						addToHistoryFile({
							message: outputText,
							type: 'info'
						});
						addToInfo(outputText);

						startResetTimer();
					} else {
						addToHistoryFile({
							message: response.article + ' нет активных заказов',
							type: 'error'
						});
						startResetTimer(1);
					}

                } else {
                    highlightError();
                    startResetTimer();
					addToHistoryFile({
						message: 'При запросе ' + barcode + ' произошла ошибка',
						type: 'error'
					});
                }
            },
            error: function(xhr, status, error) {
                highlightError();
                startResetTimer();
                console.error('Ошибка:', error);
            }
        });
    }

	function addToInfo(html) {
		$("#scan-barcode-info").html(html);
	}

    function resetInfoState() {
		addToInfo('');
    }

	/*function addToHistory(html, maxEntries = 10) {
		const $historyBlock = $("#scan-barcode-history");

		if (!$historyBlock.length) return;

		if ($historyBlock.children().length >= maxEntries) {
			$historyBlock.children().last().remove();
		}

		$historyBlock.prepend(html).find("p:first").hide().fadeIn(300);
	}*/

	function viewHistory(limit = 10, filter = {}) {
		$.ajax({
			url: '/local/components/admin/utils.barcodes/actions.php',
			method: 'POST',
			data: {
				type_order: $('.tab.active').attr('data-tab'),
				action: 'get_history',
				limit: limit,
				filter: filter
			},
			dataType: 'json',
			success: function(response) {
				if (response.status === "ok" && response.history) {
					const $historyBlock = $(".tab-pane.active .scan-barcode-history");
					$historyBlock.empty();

					response.history.forEach(item => {
						const html = `<p class="${item.type || 'info'}">${item.message}</p>`;
						$historyBlock.append(html);
					});
				} else {
					console.error('Ошибка получения истории:', response.message);
				}
			},
			error: function(xhr, status, error) {
				console.error('Ошибка при получении истории:', error);
			}
		});
	}

	function addToHistoryFile(params) {
		const defaultParams = {
			type: 'info',
			type_order: $('.tab.active').attr('data-tab')
		};

		const messageData = {...defaultParams, ...params};

		$.ajax({
			url: '/local/components/admin/utils.barcodes/actions.php',
			method: 'POST',
			data: {
				action: 'add_history',
				data: messageData
			},
			dataType: 'json',
			success: function(response) {
				if (response.status !== "ok") {
					console.error('Ошибка сохранения истории:', response.message);
				}
				viewHistory(10);
			},
			error: function(xhr, status, error) {
				console.error('Ошибка при сохранении истории:', error);
				viewHistory(10);
			}
		});
	}

	function displayGroupOrders() {
		const groupOrders = JSON.parse(localStorage.getItem('groupOrders') || '{}');
		const $container = $('#group-orders');
		
		if (!$container.length) {
			$('<div id="group-orders"></div>').appendTo('body');
			initializeGroupOrders();
			return;
		}
		
		let totalOrders = Object.keys(groupOrders).length;
		let totalScannedItems = 0;
		
		for (const [orderId, orderData] of Object.entries(groupOrders)) {
			totalScannedItems += orderData.articles.length;
		}
		
		$('#group-orders-title').html(`
			Групповые заказы
			${totalScannedItems > 0 ? `<span id="group-orders-count">${totalScannedItems}</span>` : ''}
		`);
		
		if (totalScannedItems === 0) {
			$container.addClass('empty');
			if ($container.hasClass('expanded')) {
				$container.removeClass('expanded');
			}
		} else {
			$container.removeClass('empty');
		}
		
		if (totalScannedItems > 0 && $container.hasClass('expanded')) {
			let html = '<div id="group-orders-content">';
			
			const ordersWithScannedItems = {};
			
			for (const [orderId, orderData] of Object.entries(groupOrders)) {
				if (orderData.articles.length > 0) {
					const scannedCounts = {};
					orderData.articles.forEach(article => {
						scannedCounts[article] = (scannedCounts[article] || 0) + 1;
					});
					
					ordersWithScannedItems[orderId] = {
						...orderData,
						scannedCounts: scannedCounts
					};
				}
			}
			
			if (Object.keys(ordersWithScannedItems).length === 0) {
				html += '<p style="color: #666; text-align: center;">Нет отсканированных товаров</p>';
			} else {
				html += '<table class="table" style="width:100%; font-size: 12px;"><thead><tr><th>Заказ</th><th>Товар</th><th>Отсканировано</th><th>Нужно</th><th>Статус</th><th></th></tr></thead><tbody>';
				
				for (const [orderId, orderData] of Object.entries(ordersWithScannedItems)) {
					for (const [article, required] of Object.entries(orderData.requiredArticles)) {
						const scanned = orderData.scannedCounts[article] || 0;
						const progress = Math.min((scanned / required) * 100, 100);
						const isComplete = scanned >= required;
						
						html += `
							<tr style="${isComplete ? 'background:#e6ffe6;' : ''}">
								<td><strong>${orderId}</strong></td>
								<td>${article}</td>
								<td>${scanned}</td>
								<td>${required}</td>
								<td>
									<div style="background:#f0f0f0;border-radius:3px;height:16px;width:100px;display:inline-block;vertical-align:middle;">
										<div style="background:${isComplete ? '#28a745' : '#007bff'};height:100%;width:${progress}%;border-radius:3px;"></div>
									</div>
									<span style="margin-left:5px;font-size:11px;">${scanned}/${required}</span>
								</td>
								<td>
									${scanned > 0 ? `
									<button class="btn btn-xs btn-danger remove-order-btn" data-order-id="${orderId}" data-article="${article}" title="Удалить" style="padding:1px 5px;font-size:11px;">
										✕
									</button>
									` : ''}
								</td>
							</tr>
						`;
					}
				}
				
				html += '</tbody></table>';
			}
			
			html += '</div>';
			$('#group-orders-content').replaceWith(html);
			
			$('.remove-order-btn').on('click', function() {
				const orderId = $(this).data('order-id');
				const article = $(this).data('article');
				removeArticleFromGroupOrder(orderId, article);
			});
		}
	}
	
	function initializeGroupOrders() {
		const $container = $('#group-orders');
		
		$container.html(`
			<div id="group-orders-header">
				<div id="group-orders-title">
					Групповые заказы
				</div>
				<span id="group-orders-toggle">▼</span>
			</div>
			<div id="group-orders-content"></div>
		`);
		
		$('#group-orders-header').on('click', function() {
			const $container = $('#group-orders');
			if ($container.hasClass('expanded')) {
				$container.removeClass('expanded');
			} else {
				$container.addClass('expanded');
				displayGroupOrders();
			}
		});
	}

	function removeArticleFromGroupOrder(orderId, article) {
		const groupOrders = JSON.parse(localStorage.getItem('groupOrders') || '{}');

		if (groupOrders[orderId]) {
			// Удаляем все вхождения этого артикула
			groupOrders[orderId].articles = groupOrders[orderId].articles.filter(art => art !== article);

			localStorage.setItem('groupOrders', JSON.stringify(groupOrders));
			updateGroupOrdersDisplay();

			addToHistoryFile({
				message: `Товар ${article} удален из заказа ${orderId}`,
				type: 'info'
			});
		}
	}

	/**
	 * Обновляем отображение очереди при сканировании
	 */
	function updateGroupOrdersDisplay() {
		const $container = $('#group-orders');
		if ($container.length && $container.hasClass('expanded')) {
			displayGroupOrders();
		} else {
			displayGroupOrders();
		}
	}
	function removeOrderFromGroupOrders(orderId) {
		const groupOrders = JSON.parse(localStorage.getItem('groupOrders') || '{}');
		delete groupOrders[orderId];
		localStorage.setItem('groupOrders', JSON.stringify(groupOrders));

		updateGroupOrdersDisplay();
		addToHistoryFile({
			message: `Заказ #${orderId} удален из очереди`,
			type: 'info'
		});
	}

	/**
	 * очистки всей очереди
	 */
	function clearAllGroupOrders() {
		const groupOrders = JSON.parse(localStorage.getItem('groupOrders') || '{}');

		let totalOrders = Object.keys(groupOrders).length;

		localStorage.setItem('groupOrders', JSON.stringify({}));
		if ($('#group-orders-container').length) {
			$('#group-orders-container').html('<p>Нет заказов в очереди</p>');
		}
		
		if (totalOrders > 0) {
			addToHistoryFile({
				message: 'Очередь групповых заказов очищена',
				type: 'info'
			});
		}

		updateGroupOrdersDisplay();
	}

	function playScanSound(text) {
		try {
			if ('speechSynthesis' in window) {
				const utterance = new SpeechSynthesisUtterance(text);
				utterance.lang = 'ru-RU';
				utterance.volume = 0.8;
				
				speechSynthesis.speak(utterance);
			} else {
				playFallbackSound();
			}
		} catch (e) {
			console.log('Speech synthesis not supported');
			playFallbackSound();
		}
	}

	function playFallbackSound() {
		try {
			const context = new (window.AudioContext || window.webkitAudioContext)();
			const oscillator = context.createOscillator();
			const gainNode = context.createGain();
			
			oscillator.connect(gainNode);
			gainNode.connect(context.destination);
			
			oscillator.frequency.value = 300;
			gainNode.gain.value = 0.1;
			oscillator.start(0);
			
			setTimeout(() => {
				oscillator.stop();
			}, 200);
		} catch (e) {
			console.log('Audio not supported');
		}
	}
	
	function printOrder(row) {
		var type_order = $('.tab.active').attr('data-tab');
		var marketNumber = row.attr('data-order-market-number');
		var orderSource = row.attr('data-order-source');
		var orderCabinet = row.attr('data-order-cabinet');
		var orderId = row.attr('data-order-id');
		var orderNumberId = row.attr('data-order-number-id');
		var article = row.attr('data-article');
		var productId = row.attr('data-productid');
		var numberId = row.attr('data-numberId');
		var isGroupOrder = row.attr('data-group-order');
		//var numberLine = row.attr('data-cnt');

		if (orderSource === "wb_by") {
			showControlMarkModal(row);
			return;
		}
	
		var printUrl = '';

		if (type_order == 'FBS') {
			if (orderSource === "wb") {
				printUrl = '/admin/utilities/barcodes_v1/print.php?source=wb&type_scan=scanner&order_market_number=' + marketNumber + '&order_id=' + orderId + '&product_id=' + productId + '&number_id=' + numberId + '&is_group_order=' + isGroupOrder;
			} else if (orderSource === "ozon") {
				printUrl = '/admin/utilities/barcodes_v1/print.php?source=ozon&type_scan=scanner&order_market_number=' + marketNumber + '&order_id=' + orderId + '&product_id=' + productId + '&number_id=' + numberId + '&is_group_order=' + isGroupOrder;
			} else if (orderSource === "yandex") {
				printUrl = '/admin/utilities/barcodes_v1/print.php?source=yandex&type_scan=scanner&order_market_number=' + marketNumber + '&order_id=' + orderId + '&product_id=' + productId + '&number_id=' + numberId + '&is_group_order=' + isGroupOrder;
			} else if (orderSource === "sdek") {
				printUrl = '/admin/utilities/barcodes_v1/print.php?source=sdek&type_scan=scanner&order_market_number=' + marketNumber + '&order_id=' + orderId + '&product_id=' + productId + '&number_id=' + numberId + '&is_group_order=' + isGroupOrder;
			} else if (orderSource === "avito") {
				printUrl = '/admin/utilities/barcodes_v1/print.php?source=avito&type_scan=scanner&order_market_number=' + marketNumber + '&order_id=' + orderId + '&product_id=' + productId + '&number_id=' + numberId + '&is_group_order=' + isGroupOrder;
			}
		} else if (type_order == 'FBO') {
			var barcode = row.attr('data-barcode-market');
			var cabinet = $('#fbo-tab .cabinet-market').val();

			console.log('cabinetcabinetcabinetcabinetcabinet', cabinet);
			printUrl = '/admin/utilities/barcodes_v1/print.php?source=fbo&type_scan=scanner&barcode=' + barcode + '&order_id=' + orderId + '&product_id=' + productId + '&number_id=' + numberId + '&cabinet=' + cabinet;
		} else if (type_order == 'BY') {

		} 

		if (printUrl) {
			if (orderCabinet && orderCabinet == 'WB_IP') {
				playScanSound('ВБ IP');
			}
			openPrintWindow(printUrl, row, orderSource, orderNumberId, article, orderId);
		} else {
			console.error('Неизвестный источник заказа');
			addToHistoryFile({
				message: 'Неизвестный источник заказа',
				type: 'error'
			});
		}
		/*if (printUrl) {
			try {
				var printWindow = window.open(printUrl, '_blank');
				if (printWindow) {
					printWindow.onload = function() {
						try {
							printWindow.print();
							setTimeout(function() {
								printWindow.close();
								
								if (orderSource === "sdek") {
									var commentColumn = row.find('td').eq(7);
									if (commentColumn.length) {
										var commentText = commentColumn.text().trim();
										if (commentText) {
											alert('Комментарий к заказу SDEK:\n' + commentText);
										}
									}
								}
							}, <?=intval($arResult["SETTINGS"]['autoclose-print-window']) * 1000;?>);
							
							//setPrintOrder(orderId, true);

							row.addClass('print_sticker');
							row.attr('data-print-sticker', '1');

							addToHistoryFile({
								message: article + ' ' + orderNumberId,
								type: 'print'
							});

							var countPrint = $('tr.print_sticker').length;
							$('#stat-line span.print').text("Напечатано стикеров - " + countPrint);
							

						} catch(e) {
							console.error('Ошибка печати:', e);
							addToHistoryFile({
								message: 'Ошибка печати',
								type: 'error'
							});
						}
					};

					printWindow.onerror = function() {
						console.error('Ошибка открытия окна печати');
						addToHistoryFile({
							message: 'Ошибка открытия окна печати',
							type: 'error'
						});
					};

					setTimeout(function() {
						if (printWindow.closed || typeof printWindow.print !== 'function') {
							console.error('Окно печати было заблокировано');
							addToHistoryFile({
								message: 'Окно печати было заблокировано',
								type: 'error'
							});
						}
					}, 500);
				} else {
					addToHistoryFile({
						message: 'Не удалось открыть окно печати',
						type: 'error'
					});
					console.error('Не удалось открыть окно печати');
				}
			} catch(e) {
				console.error('Ошибка при попытке печати:', e);
				addToHistoryFile({
					message: 'Ошибка при попытке печати',
					type: 'error'
				});
			}
		} else {
			console.error('Неизвестный источник заказа');
			addToHistoryFile({
				message: 'Неизвестный источник заказа',
				type: 'error'
			});
		}*/
	}
	
	function printOrderWithControlMark(row, controlMark) {
		var type_order = $('.tab.active').attr('data-tab');
		var marketNumber = row.attr('data-order-market-number');
		var orderSource = row.attr('data-order-source');
		var orderCabinet = row.attr('data-order-cabinet');
		var orderId = row.attr('data-order-id');
		var orderNumberId = row.attr('data-order-number-id');
		var article = row.attr('data-article');
		var productId = row.attr('data-productid');
		var numberId = row.attr('data-numberId');
		var isGroupOrder = row.attr('data-group-order');
		
		var printUrl = '/admin/utilities/barcodes_v1/print.php?source=wb&type_scan=scanner' +
			'&order_market_number=' + encodeURIComponent(marketNumber) +
			'&order_id=' + encodeURIComponent(orderId) +
			'&product_id=' + encodeURIComponent(productId) +
			'&number_id=' + encodeURIComponent(numberId) +
			'&is_group_order=' + encodeURIComponent(isGroupOrder) +
			'&control_mark=' + encodeURIComponent(controlMark);
		
		if (printUrl) {
			if (orderCabinet && orderCabinet == 'WB_IP') {
				playScanSound('ВБ IP');
			}
			openPrintWindow(printUrl, row, orderSource, orderNumberId, article, orderId);
		}
	}

	function openPrintWindow(printUrl, row, orderSource, orderNumberId, article, orderId) {
		try {
			var printWindow = window.open(printUrl, '_blank');
			if (printWindow) {
				printWindow.onload = function() {
					try {
						printWindow.print();
						setTimeout(function() {
							printWindow.close();
							
							if (orderSource === "sdek") {
								var commentColumn = row.find('td').eq(7);
								if (commentColumn.length) {
									var commentText = commentColumn.text().trim();
									if (commentText) {
										alert('Комментарий к заказу SDEK:\n' + commentText);
									}
								}
							}
						}, <?=$arResult["SETTINGS"]['autoclose-print-window'] * 1000;?>);
						
						row.addClass('print_sticker');
						row.attr('data-print-sticker', '1');

						addToHistoryFile({
							message: article + ' ' + orderNumberId + (orderSource === "wb_by" ? ' (с контрольной маркой)' : ''),
							type: 'print'
						});

						var countPrint = $('tr.print_sticker').length;
						$('#stat-line span.print').text("Напечатано стикеров - " + countPrint);
						
						// Для групповых заказов
						if (row.attr('data-group-order')) {
							speakText("Распечатана этикетка группового заказа");
						}

					} catch(e) {
						console.error('Ошибка печати:', e);
						addToHistoryFile({
							message: 'Ошибка печати',
							type: 'error'
						});
					}
				};

				printWindow.onerror = function() {
					console.error('Ошибка открытия окна печати');
					addToHistoryFile({
						message: 'Ошибка открытия окна печати',
						type: 'error'
					});
				};

				setTimeout(function() {
					if (printWindow.closed || typeof printWindow.print !== 'function') {
						console.error('Окно печати было заблокировано');
						addToHistoryFile({
							message: 'Окно печати было заблокировано',
							type: 'error'
						});
					}
				}, 500);
			} else {
				addToHistoryFile({
					message: 'Не удалось открыть окно печати',
					type: 'error'
				});
				console.error('Не удалось открыть окно печати');
			}
		} catch(e) {
			console.error('Ошибка при попытке печати:', e);
			addToHistoryFile({
				message: 'Ошибка при попытке печати',
				type: 'error'
			});
		}
	}
	
	function showControlMarkModal(row) {
		var orderId = row.attr('data-order-id');
		var orderNumberId = row.attr('data-order-number-id');
		
		$('#controlMarkInput').val('');
		$('#controlMarkError').hide().empty();
		
		$('#controlMarkModal')
			.data('row', row)
			.show();
		
		$('#controlMarkInput').focus();
		
		$('.scan-barcode').attr('disabled', 'disabled');
	}

	function setPrintOrder(orderId, print_sticker = true) {
		$.ajax({
			url: '/local/components/admin/utils.barcodes/actions.php',
			method: 'POST',
			data: {
				type_order: $('.tab.active').attr('data-tab'),
				action: 'set_print_sticker',
				order_id: orderId,
				print_sticker: print_sticker,
			},
			dataType: 'json',
			success: function(response) {
				if (!response || response.status != "ok") {
					console.error('Ошибка установки флага');
					addToHistoryFile({
						message: 'Ошибка установки флага ' + orderId,
						type: 'error'
					});
				}
			},
			error: function(xhr, status, error) {
				console.error('Ошибка установки флага:', error);
				addToHistoryFile({
					message: 'Ошибка установки флага:' + error,
					type: 'error'
				});
			}
		});
	}

	function updatePrintHistory() {
		const orderIds = [];
		const tableRows = document.querySelectorAll('#barcode tr[data-order-id]');

		tableRows.forEach(row => {
			const orderId = row.getAttribute('data-order-id');
			if (orderId && orderId.trim() !== '') {
				orderIds.push(orderId);
			}
		});

		$.ajax({
			url: '/local/components/admin/utils.barcodes/actions.php',
			method: 'POST',
			data: {
				action: 'get_print_history',
				orders: orderIds
			},
			dataType: 'json',
			success: function(response) {
				if (response.status === "ok") {
					updateTablePrintHistory(response.history);
				} else {
					console.error('Ошибка получения истории сканирования');
				}
			},
			error: function(xhr, status, error) {
				console.error('Ошибка при получении истории:', error);
			}
		});
	}

	function updateTablePrintHistory(history) {
		const tableRows = document.querySelectorAll('#barcode tr');

		const historyMap = new Map();
		const historyMapTmp = new Map();

		if (history && Array.isArray(history)) {
			history.forEach(item => {
				const key = `${item.ORDER_ID}-${item.PRODUCT_ID}-${item.NUMBER_ID}`;

				if (!historyMap.has(key)) {
					historyMap.set(key, []);
				}
				historyMap.get(key).push(item);

				if (item.PRODUCT_ID == null && item.NUMBER_ID == null) {
					const key = `${item.ORDER_ID}`;
					if (!historyMapTmp.has(key)) {
						historyMapTmp.set(key, []);
					}
					historyMapTmp.get(key).push(item);
				}
			});
		}
		console.log(historyMapTmp, historyMap);
		tableRows.forEach(row => {
			const orderId = row.getAttribute('data-order-id');
			const productId = row.getAttribute('data-productid');
			const numberId = row.getAttribute('data-numberid');

			if (!orderId || !productId || !numberId) return;

			const key = `${orderId}-${productId}-${numberId}`;

			if (historyMap.has(key)) {
				const historyItems = historyMap.get(key);

				let infoDiv = row.querySelector('div.info-history-order');
				let contolMarkDiv = row.querySelector('div.info-history-control-mark');

				infoDiv.innerHTML = '';
				contolMarkDiv.innerHTML = '';
				historyItems.forEach(item => {
					/*
					const p = document.createElement('p');
					p.style.margin = '0';
					p.style.fontSize = '11px';
					p.textContent = `${item.TYPE_SCAN} - ${item.TIMESTAMP}`;
					infoDiv.appendChild(p);
					*/
					const p = document.createElement('p');
					p.style.margin = '0';
					p.style.fontSize = '11px';
					p.style.display = 'flex';
					p.style.alignItems = 'center';
					p.style.gap = '5px';

					const textSpan = document.createElement('span');
					textSpan.textContent = `${item.TYPE_SCAN} - ${item.TIMESTAMP} - ${item.USER_ID}`;

					const deleteIcon = document.createElement('span');
					deleteIcon.className = 'delete_print_item';
					deleteIcon.innerHTML = '❌';
					deleteIcon.title = 'Удалить';
					deleteIcon.setAttribute('data-id', item.ID);

					textSpan.appendChild(deleteIcon);
					p.appendChild(textSpan);
					//p.appendChild(deleteIcon);

					infoDiv.appendChild(p);
					
					if (item.CONTROL_MARK && item.CONTROL_MARK.length > 0) {
						const p2 = document.createElement('p');
						p2.style.margin = '0';
						p2.style.fontSize = '11px';
						p2.style.display = 'flex';
						p2.style.alignItems = 'center';
						p2.style.gap = '5px';
						p2.innerHTML = `${item.CONTROL_MARK}`;
						contolMarkDiv.appendChild(p2);
					}

				});

				row.classList.add('print_sticker');
				row.setAttribute('data-print-sticker', '1');

			} else {

				row.classList.remove('print_sticker');
				row.removeAttribute('data-print-sticker');

				// Очищаем info-history-order
				let infoDiv = row.querySelector('div.info-history-order');
				if (infoDiv) {
					infoDiv.innerHTML = '';
				}
				
				let contolMarkDiv = row.querySelector('div.info-history-control-mark');
				if (contolMarkDiv) {
					contolMarkDiv.innerHTML = '';
				}
			}

			const keyTmp = `${orderId}`;
			if (historyMapTmp.has(keyTmp)) {
				const historyItems = historyMapTmp.get(keyTmp);

				let infoDiv = row.querySelector('div.info-history-order');
				let contolMarkDiv = row.querySelector('div.info-history-control-mark');
				
				infoDiv.innerHTML = '';
				contolMarkDiv.innerHTML = '';
				historyItems.forEach(item => {
					const p = document.createElement('p');
					p.style.margin = '0';
					p.style.fontSize = '11px';
					p.textContent = `${item.TYPE_SCAN} - ${item.TIMESTAMP}`;
					infoDiv.appendChild(p);
					
					const p2 = document.createElement('p');
					p2.style.margin = '0';
					p2.style.fontSize = '11px';
					p2.textContent = `${item.TYPE_SCAN} - ${item.TIMESTAMP}`;
					
					contolMarkDiv.appendChild(p2);
				});

				row.classList.add('print_sticker');
				row.setAttribute('data-print-sticker', '1');

			}
		});
	}

	updatePrintHistory();

	setInterval(updatePrintHistory, 30000);

	document.addEventListener('visibilitychange', function() {
		if (document.hidden) {
			console.log('Страница скрыта, обновление приостановлено');
		} else {
			// При возвращении на страницу обновляем
			updatePrintHistory();
			console.log('Страница активна, обновление возобновлено');
		}
	});

    function highlightError() {
        $('.scan-barcode').addClass('error');
    }

    function resetBarcodeState() {
        $('.scan-barcode').removeClass('error').val('');
    }

    // Сброс таймера при новом вводе
    $('.scan-barcode').on('focus click', function() {
        clearTimeout(resetTimer);
    });

	$('.print_barcode_market').on('click', function() {
        var orderId = $(this).closest('tr').attr('data-order-id');
		$(`#barcode tr[data-order-id="${orderId}"][data-sticker="1"]`).each(function() {
			var $row = $(this);
			$row.addClass('print_sticker');
			var countPrint = $('tr.print_sticker').length;
			$('#stat-line span.print').text("Напечатано стикеров - " + countPrint);
		});

        var orderNumberId = $(this).closest('tr').attr('data-order-number-id');
        var article = $(this).closest('tr').attr('data-article');

		addToHistoryFile({
			message: article + ' ' + orderNumberId,
			type: 'print'
		});
    });

	// удаление отсканированого товара. истории
	$(document).on('click', '.delete_print_item', function() {
		if (!confirm('Вы действительно хотите удалить запись о печати?')) {
			return;
		}
		var item = $(this);
		const id = $(item).data('id');

		$.ajax({
			url: '/local/components/admin/utils.barcodes/actions.php',
			method: 'POST',
			data: {
				action: 'delete_print_item',
				record_id: id
			},
			dataType: 'json',
			success: function(response) {
				if (response.status === "ok") {
					//$(item).closest('p').remove();
					showNotification('Запись удалена', 'info');
					updatePrintHistory();
				} else {
					showNotification(response.message, 'error');
				}
			},
			error: function(xhr, status, error) {
				showNotification('Ошибка при удалении истории', 'error');
			}
		});

		return false;
    });

    function showNotification(message, type = 'info') {
        const existingNotifications = document.querySelectorAll('.custom-notification');
        existingNotifications.forEach(notification => notification.remove());

        const notification = document.createElement('div');
        notification.className = `custom-notification ${type}`;
        notification.textContent = message;

        notification.style.position = 'fixed';
        notification.style.top = '20px';
        notification.style.right = '20px';
        notification.style.padding = '12px 20px';
        notification.style.borderRadius = '4px';
        notification.style.color = 'white';
        notification.style.zIndex = '10000';
        notification.style.boxShadow = '0 4px 12px rgba(0,0,0,0.15)';
        notification.style.maxWidth = '400px';

        if (type === 'error') {
            notification.style.background = '#f44336';
        } else if (type === 'success') {
            notification.style.background = '#4caf50';
        } else {
            notification.style.background = '#2196f3';
        }

        document.body.appendChild(notification);

        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 5000);
    }

	function speakText(text) {
		if ('speechSynthesis' in window) {
			speechSynthesis.cancel();
			
			const utterance = new SpeechSynthesisUtterance(text);
			utterance.lang = 'ru-RU';
			utterance.rate = 1.0; // Скорость
			utterance.pitch = 1.0; // Тон
			utterance.volume = 1.0; // Громкость
			
			speechSynthesis.speak(utterance);
		} else {
			console.log('Браузер не поддерживает синтез речи');
		}
	}
	
	function showGroupOrderModal(order) {
		$('.scan-barcode').attr('disabled', 'disabled');
		$('#groupOrderId').text(order.orderId);
		
		const itemsList = $('#groupOrderItemsList');
		itemsList.empty();
		
		const articleCounts = {};
		order.articles.forEach(article => {
			articleCounts[article] = (articleCounts[article] || 0) + 1;
		});
		
		let totalItems = 0;
		let uniqueArticles = 0;
		
		for (const [article, count] of Object.entries(articleCounts)) {
			const itemElement = $('<div class="group-order-item"></div>');
			itemElement.html(`
				<span class="article">${article}</span>
				<span class="quantity">×${count}</span>
			`);
			itemsList.append(itemElement);
			
			totalItems += count;
			uniqueArticles++;
		}
		
		$('#groupOrderTotalItems').text(totalItems);
		$('#groupOrderUniqueArticles').text(uniqueArticles);
		
		$('#groupOrderModal').show();
	}

	// Закрытие модального окна группового заказа
	$('.close-group-modal, #closeGroupModalBtn').on('click', function() {
		$('#groupOrderModal').hide();
		$('.scan-barcode').removeAttr('disabled');
	});

	// Закрытие при клике вне окна
	$(window).on('click', function(event) {
		if ($(event.target).is('#groupOrderModal')) {
			$('#groupOrderModal').hide();
			$('.scan-barcode').removeAttr('disabled');
		}
	});
	
	// контрольная марка
    $('.close-control-mark, #cancelControlMarkBtn').on('click', function() {
        $('#controlMarkModal').hide();
        $('.scan-barcode').removeAttr('disabled');
        $('#controlMarkInput').val('');
        $('#controlMarkError').hide().empty();
    });
    
    $('#confirmControlMarkBtn').on('click', function() {
        var controlMark = $('#controlMarkInput').val().trim();
        
        if (controlMark.length !== 30) {
            $('#controlMarkError')
                .text('Контрольная марка должна содержать ровно 30 символов. Введено: ' + controlMark.length)
                .show();
            return;
        }
        
        var row = $('#controlMarkModal').data('row');
        
        if (row) {
            $('#controlMarkModal').hide();
            $('.scan-barcode').removeAttr('disabled');
            
            printOrderWithControlMark(row, controlMark);
        }
    });
    
    $('#controlMarkInput').on('input', function() {
        var value = $(this).val().trim();
        if (value.length === 30) {
            $('#confirmControlMarkBtn').trigger('click');
        }
    });
    
    // закрытие контрольной марки
    $(window).on('click', function(event) {
        if ($(event.target).is('#controlMarkModal')) {
            $('#controlMarkModal').hide();
            $('.scan-barcode').removeAttr('disabled');
            $('#controlMarkInput').val('');
            $('#controlMarkError').hide().empty();
        }
    });
	
	
    //setInterval(updateGroupOrdersDisplay, 30000);

	initializeGroupOrders();
	displayGroupOrders();
	viewHistory();
});

function addScrollToTopButton() {
    const scrollButton = $('<button>', {
        id: 'scroll-to-top-btn',
        html: '<i class="fa fa-arrow-up"></i>',
        title: 'Наверх',
        css: {
            position: 'fixed',
            bottom: '20px',
            right: '20px',
            width: '40px',
            height: '40px',
            borderRadius: '50%',
            background: '#4a6fa5',
            color: 'white',
            border: 'none',
            cursor: 'pointer',
            display: 'none',
            zIndex: '99999',
            boxShadow: '0 2px 5px rgba(0,0,0,0.3)',
            fontSize: '18px'
        }
    });

    $('body').append(scrollButton);

    $(window).scroll(function() {
        if ($(this).scrollTop() > 300) {
            scrollButton.fadeIn();
        } else {
            scrollButton.fadeOut();
        }
    });

    scrollButton.click(function() {
        $('html, body').animate({scrollTop: 0}, 300);
		$('.scan-barcode').focus();
        return false;
    });
}
$(document).ready(function() {
    addScrollToTopButton();

    if (!$('link[href*="font-awesome"]').length) {
        //$('head').append('<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">');
    }

	$("#stat-line span.all").text("Строк - <?=$i;?>");
	var countPrint = $('tr.print_sticker').length;
	$('#stat-line span.print').text("Напечатано стикеров - " + countPrint);


});

document.addEventListener('DOMContentLoaded', function() {
    const barcodeMain = document.querySelector('.barcode-main');

    window.addEventListener('load', function() {
        if (barcodeMain) {
            barcodeMain.classList.remove('blocked');
        }
    });

    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', function() {

            const target = form.getAttribute('target');
            const isTargetBlank = target && (target === '_blank' || target === 'new');

            if (barcodeMain && !isTargetBlank) {
                barcodeMain.classList.add('blocked');
            }
        });
    });
});

document.addEventListener('DOMContentLoaded', function() {
    const tabs = document.querySelectorAll('.tab');
    const tabPanes = document.querySelectorAll('.tab-pane');

    tabs.forEach(tab => {
        tab.addEventListener('click', function() {
            const tabName = this.getAttribute('data-tab');

            tabs.forEach(t => t.classList.remove('active'));
            tabPanes.forEach(pane => pane.classList.remove('active'));

            this.classList.add('active');

            document.querySelector(`.tab-pane#${tabName.toLowerCase()}-tab`).classList.add('active');

            // Обновляем скрытое поле active_tab во всех формах
            document.querySelectorAll('input[name="active_tab"]').forEach(input => {
                input.value = tabName;
            });

			$('#barcode').html('');
        });
    });

	const settingsButton = document.getElementById('settingsBarcode');
    const settingsBarcodeModal = document.getElementById('settingsBarcodeModal');
    const closeModal = document.querySelector('.close');
    const cancelSettingsBtn = document.getElementById('cancelSettingsBtn');
    const saveSettingsBtn = document.getElementById('saveSettingsBtn');
    const settingsBarcodeForm = document.getElementById('settingsBarcodeForm');

	settingsButton.addEventListener('click', function() {
        settingsBarcodeModal.style.display = 'block';
    });

    // закрываем настройки
    function closeSettingsModal() {
        settingsBarcodeModal.style.display = 'none';
    }
    closeModal.addEventListener('click', closeSettingsModal);
    cancelSettingsBtn.addEventListener('click', closeSettingsModal);

    // Закрытие при клике вне модального окна
    window.addEventListener('click', function(event) {
        if (event.target === settingsBarcodeModal) {
            closeSettingsModal();
        }
    });

    // Сохранение настроек
    /*saveSettingsBtn.addEventListener('click', function() {
        const formData = new FormData(settingsBarcodeForm);
        const settings = {};

        for (let [key, value] of formData.entries()) {
			settings[key] = value;
        }
        console.log(settings);

		$.ajax({
			url: '/local/components/admin/utils.barcodes/actions.php',
			method: 'POST',
			data: {
				action: 'set_settings',
				settings: settings
			},
			dataType: 'json',
			success: function(response) {
				if (response && response.status == "ok") {
					alert('Настройки сохранены');
					//setTimeout(() => window.location.reload(), 500);
				} else {
					alert('Ошибка при сохранении настроек');
				}
			},
			error: function(xhr, status, error) {
				alert('Ошибка при сохранении настроек', 'error');
			}
		});
    });*/
	saveSettingsBtn.addEventListener('click', function() {
		const formArray = $(settingsBarcodeForm).serializeArray();
		const settings = {};
		const tradingData = {};

		formArray.forEach(item => {
			const key = item.name;
			const value = item.value;
			
			if (key.startsWith('trading[')) {
				const matches = key.match(/trading\[([^\]]+)\]\[([^\]]+)\]/);
				if (matches) {
					const code = matches[1];
					const field = matches[2];
					
					if (!tradingData[code]) {
						tradingData[code] = {};
					}
					tradingData[code][field] = value;
				}
			} else {
				settings[key] = value;
			}
		});
		
		settings.trading_priority = tradingData;
		
		console.log('Отправляемые настройки:', settings);

		$.ajax({
			url: '/local/components/admin/utils.barcodes/actions.php',
			method: 'POST',
			data: {
				action: 'set_settings',
				settings: settings
			},
			dataType: 'json',
			success: function(response) {
				if (response && response.status == "ok") {
					alert('Настройки сохранены');
				} else {
					alert('Ошибка при сохранении настроек');
				}
			},
			error: function(xhr, status, error) {
				console.error('AJAX Error:', error);
				alert('Ошибка при сохранении настроек', 'error');
			}
		});
	});
});
</script>