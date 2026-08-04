<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');?>
<?
global $DB;
//if(isset($_POST["website"]) && in_array($_POST["website"], array("s1", "s2")))
//	$psFilter["website"] = $_POST["website"];
//prent($psFilter);
//prent($_POST);die;
?>
<div class="row">
<?/*if(!in_array($psFilter["website"], array("s1", "s2"))):?>
	<p>Выберите сайт</p>
	<?die;?>
<?endif*/?>
<?
global $USER;
$arGroups = $USER->GetUserGroupArray();
if($USER->isAdmin() || in_array(6, $arGroups) || in_array(19, $arGroups)) $arResult["ACCESS"] = true;

if(in_array(12, $arGroups)){
    global $APPLICATION;
    $APPLICATION->AuthForm("Доступ запрещен");
    return;
}
//prent($arGroups,0,1);
$start = debug_microtime_float();

$arStockID = [44,47];
if(CModule::IncludeModule("panel.manager") && CModule::IncludeModule("iblock") && CModule::IncludeModule("catalog")){
	$arr = array();

	$objService = new OrderService;
	$objService->getPropOrderFlg = false;

	$objSupplier = new CPanelSupplier;
	$arResult["SUPPLIER_LIST"] = $objSupplier->getList();
	foreach($arResult["SUPPLIER_LIST"] as $arSup){
		$arResult["SUPPLIER_NAME"][$arSup["id"]] = $arSup["name"];
		$arResult["SUPPLIER_SORT"][$arSup["id"]] = $arSup["sort"];
	}
	$strSql = "SELECT * FROM ci_purchase WHERE active = 'Y'";// AND site_id = '".$psFilter["website"]."'";
	$results = $DB->Query($strSql, false, $err_mess.__LINE__);
	while ($row = $results->Fetch()){
		$arResult["ITEMS"][] = $row;
	}

	$arStampMS = [];
	$ids = $arOrder = $sFilter = array();
	foreach($arResult["ITEMS"] as $key => &$arItem){
		if($arItem["order_id"] > 0)
			$ids[] = $arItem["order_id"];

		$ms_data = unserialize($arItem["ms_data"]);
		if(in_array($arItem["supp_id"], array(44, 47))){
			$type = "supply_transfer";
		}else{
			$type = "supply";
		}
		if($ms_data[$type]["id"]){

			$arStampMS[$arItem["supp_id"]]["{$ms_data[$type]["timestamp"]}"] = $ms_data[$type]["timestamp"];
			$arItem["ms_timestamp"] = (string)$ms_data[$type]["timestamp"];
			$arItem["ms_cabinet"] = $ms_data[$type]["cabinet"];
			//$arItem["ms_data"]
		}

		//$sFilter[$arItem["model"]] = $arItem["model"];
		$sFilter[md5($arItem["model"].$arItem["supp_id"])] = "(model = '".addslashes($arItem["model"])."' AND supplier_id = '".addslashes($arItem["supp_id"])."')";
	}
	unset($arItem);
	//prent($arStampMS);
	foreach($arStampMS as $sId => $arTime){
		asort($arTime);
		//$arNumberSeq[$sId] = $arTime;
		$c = (is_array($arTime) ? count($arTime) : 0);
		$arNumberSeq[$sId] = array_combine($arTime, range(1, $c));
		//foreach($time => $v){

		//}
	}

	//prent($arResult["ITEMS"]);
	//prent($arNumberSeq);
	if(is_array($sFilter) && count($sFilter) > 0){
		//$add_where = implode(" OR ",$sFilter);
		$add_where = "((".implode(") OR (",$sFilter)."))";
		$strSql = "SELECT model FROM ci_price WHERE {$add_where} GROUP BY model";
		//prent($strSql);
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		while ($row = $results->Fetch()){
			$arResult["ITEMS_STOCK"][$row["model"]] = $row["model"];
		}

	}

//	$start = debug_microtime_float();
	//$tmp = $objService->getOrder(array(), $arFilter = array("ID" => $ids));
	$tmp = $objService->getOrderCache(array(), $arFilter = array("ID" => $ids));
	foreach($tmp as $key => $arItem){
		$arAllOrder[$arItem["ID"]] = $arItem;
	}

	if(is_array($arAllOrder))
		$arCrmID = $objService->getOrderCrmID(array("ORDER_ID" => array_keys($arAllOrder)));
	//prent($arCrmID);
	foreach($arResult["ITEMS"] as $key => &$arItem){
		$arItem["supp_name"] = $arResult["SUPPLIER_NAME"][$arItem["supp_id"]];

		if(isset($arAllOrder[$arItem["order_id"]])){
			$arItem["item_active"] = "N";
			$arOrder = $arAllOrder[$arItem["order_id"]];
			$arItem["order_status_id"] = $arOrder["STATUS_ID"];
			$arItem["order_canceled"] = $arOrder["CANCELED"];
			$arItem["order_number_id"] = $arOrder["ORDER_ID"];

			$tmp = explode(".", $arItem["order_basket_id"]);
			$order_basket_id = $tmp[0];
			//если товар отредактирован и товар удалил, то ставим флаг
			foreach($arOrder["BASKET"] as $k => $v){
				if($v["ID"] == $order_basket_id)
					$arItem["item_active"] = "Y";
			}
		}
		if($arResult["ITEMS_STOCK"][$arItem["model"]])
			$arItem["in_stock"] = "Y";
		else
			$arItem["in_stock"] = "N";


	}
	unset($arItem);
//$end = debug_microtime_float();
//prent($start - $end);

	$arSort = array();
	foreach($arResult["ITEMS"] as $key => $arItem){
//		$arSort[$arItem["supp_id"]] = $arItem["supp_id"];
		//$arSort[$arItem["supp_id"]] = $arResult["SUPPLIER_NAME"][$arItem["supp_id"]];
		$arSort[$arItem["supp_id"]] = $arResult["SUPPLIER_SORT"][$arItem["supp_id"]];
		$arResult["PRICE_GROUP"][$arItem["supp_id"]][] = $arItem;
	}
	//sort($arSort);
	asort($arSort);
	$tmp = array();
	foreach($arSort as $key => $val){
		//$tmp[$val] = $arResult["PRICE_GROUP"][$val];
		$tmp[$key] = $arResult["PRICE_GROUP"][$key];
	}
	$arResult["PRICE_GROUP"] = $tmp;

	foreach($arResult["PRICE_GROUP"] as $key => $arItem){
		foreach($arItem as $k => $v){
			$arResult["PRICE_GROUP_SUM"][$key] += $v["price"];
		}
	}

	//prent($arResult["PRICE_GROUP"]);

	//prent($arResult["PRICE_GROUP"]); 
	$txt_all = "";
	if($arResult["ACCESS"] === true) $cntCol = 6; else $cntCol = 4;
	?>
	<?/*<p><span class="btn-clipboard-list ico-clipboard" style="padding: 0 0 0 18px;" data-id="textarea-purchaselist-all" data-clipboard-target="#textarea-purchaselist-all">список</span></p>*/?>
	<?foreach($arResult["PRICE_GROUP"] as $key => $arItem):?>
		<div class="col-sm-12">

			<?$txt = "";?>
			<table class="table purchase-item" id="purchase-item-<?=$key?>" data-id="<?=$key?>">
				<thead>
					<tr>
						<th colspan="<?=$cntCol?>"><span class="btn-clipboard-list ico-clipboard" data-id="textarea-purchaselist-<?=$key?>" data-clipboard-target="#textarea-purchaselist-<?=$key?>"></span><a href="/admin/ajax/purchase/get_purchase_csv.php?supp_id=<?=$key?>" data-supp_id="<?=$key?>" style="cursor:pointer; color: #337ab7;" class="purchase-csv"><?=$arResult["SUPPLIER_NAME"][$key]?></a><span class="badge" style="margin: 0 0 0 5px;"><?=(is_array($arItem) ? count($arItem) : 0)?></span></th>
					</tr>
					<?if($arResult["ACCESS"] === true):?>
					<tr>
						<th colspan="3">Цена (<?=$arResult["PRICE_GROUP_SUM"][$key]?>)</th>
						<th colspan="3">
							<?if(in_array($key, $arStockID)):?>
							<button type="button" class="btn btn-primary " id="ms_create_supply" data-id="<?=$key?>" style="float: right;padding: 0px 3px 0 3px;font-size: 12px;">Создать перемещение</button>
              <?elseif ($key == 103):?>
              <button type="button" class="btn btn-primary " id="ms_create_supply_new103" data-id="<?=$key?>" style="float: right;padding: 0px 3px 0 3px;font-size: 12px;">Создать перемещение</button>
							<?else:?>
							<button type="button" class="btn btn-primary " id="ms_create_supply" data-id="<?=$key?>" style="float: right;padding: 0px 3px 0 3px;font-size: 12px;">Создать приемку</button>
							<?endif?>

						</th>
					</tr>
					<?endif?>
				</thead>
				<tbody>
				<?
				/*

				NZ Отказ на этапе доставки
				RD Возврат после вручения
				NA Не удалось дозвониться
				DB Дубль заказа
				AB Уже купил
				no Передумал/Ошибка
				OT Не устроили сроки
				OS Нет в наличии
				PO Zamówienie przetworzone Dostępność potwierdzona. Przygotowanie do wysyłki.
				CA Отменен (Клиент)
				CS Отменен (Магазин)

				"NZ","RD","NA","DB","AB","no","OT","OS","PO","CA","CS"

				PO Zamówienie przetworzone Dostępność potwierdzona. Przygotowanie do wysyłki.
				TA Самовывоз
				CO Готов к доставке
				SE Готов к отправке
				WT Ожидаем поступление

				"PO", "TA", "CO", "SE", "WT"
				*/
				//$txt_all .= $arResult["SUPPLIER_NAME"][$key] . "\r\n";

				$arTxt = [];
				?>
				<?foreach($arItem as $article => $arPrice):?>
					<?//$txt .= $arPrice["model"] . "\r\n";?>
					<?//$txt_all .= $arPrice["model"] . "\r\n";?>
					<?$arTxt[$arPrice["model"]] += 1;?>
					<?$arTxtAll[$arResult["SUPPLIER_NAME"][$key]][$arPrice["model"]] += 1;?>
						<?/*<tr class="<?if(!in_array($arPrice["order_status_id"], array("N", "DB", "NA"))):?>warning<?elseif($arPrice["order_canceled"] == "Y" || $arPrice["item_active"] == "N" || $arPrice["order_status_id"] == "NA" || $arPrice["order_status_id"] == "DB"):?>danger<?endif?>" data-orderbasketid="<?=$arPrice["order_basket_id"]?>">
						<tr class="<?if(($arPrice["order_id"] > 0 && !in_array($arPrice["order_status_id"], array("PO", "TA", "CO", "SE", "WT"))) || $arPrice["order_canceled"] == "Y" || $arPrice["item_active"] == "N"):?>danger<?elseif($arPrice["top_id"] > 0):?>warning<?endif?>" data-orderbasketid="<?=$arPrice["order_basket_id"]?>">*/?>
						<tr class="<?if(($arPrice["order_id"] > 0 && in_array($arPrice["order_status_id"], array("WT", "LP", "DP", "NZ", "RD", "NA", "DB", "AB", "NO", "OT", "OS", "CA", "CS"))) || $arPrice["order_canceled"] == "Y" || $arPrice["item_active"] == "N"):?>danger<?elseif($arPrice["top_id"] == 0 && ($arPrice["order_id"] > 0 && !in_array($arPrice["order_status_id"], array("CO", "SE", "TA", "PO")))):?>warning11<?elseif($arPrice["top_id"] > 0):?>success<?endif?>" data-orderbasketid="<?=$arPrice["order_basket_id"]?>" data-product_id="<?=$arPrice["product_id"]?>" data-article="<?=$arPrice["model"]?>" data-id="<?=$arPrice["id"]?>">
							<td><?=$arPrice["model"]?><?if($arResult["ACCESS"] === true && $arPrice["in_stock"] == "Y"):?><span class="delete-price" data-id="<?=$arPrice["id"]?>">x</span><?endif?><?if($arPrice["in_stock"] == "N"):?> *<?endif?></td>
							<?if($arResult["ACCESS"] === true):?><td><?=number_format($arPrice["price"], 2, ',', ' ')?></td><?endif?>
							<td><?if($arPrice["order_id"] > 0):?><a href="https://tempusshop.retailcrm.ru/orders/<?=$arCrmID[$arPrice["order_id"]]?>/edit" target="_blank" style="position: relative;"><span><?=$arPrice["order_number_id"]?></span></a><?else:?><?=$arPrice["order_number_id"]?><?endif?></td>
							<td><?=$arPrice["site_id"]?></td>
							<td><?=$arNumberSeq[$arPrice["supp_id"]][$arPrice["ms_timestamp"]]?></td>
							<?if($arResult["ACCESS"] === true):?>
							<td class="right" style="width:100px;text-align: right;">
								<button type="button" class="btn btn-danger delete-purchase" data-id="<?=$arPrice["id"]?>">Удалить</button>
							</td>
							<?endif?>
						</tr>
				<?endforeach?>
				<?//$txt_all .= "\r\n\r\n";?>
					<tr><td style="padding: 0; line-height: 0;" colspan="<?=$cntCol?>">&nbsp;</td></tr>
					<tr><td style="padding: 0; line-height: 0;" colspan="<?=$cntCol?>">&nbsp;</td></tr>
					<tr><td style="padding: 0; line-height: 0;" colspan="<?=$cntCol?>">&nbsp;</td></tr>
				</tbody>
			</table>
			<?
			//prent($arTxt);
			$txt = "";
			foreach($arTxt as $model => $cnt){
				$txt .= $model . " " . $cnt . "\r\n";
			}
			?>
			<textarea id="textarea-purchaselist-<?=$key?>" style="position: fixed;left: -9999px;display:none;"><?=$txt?></textarea>
		</div>
		<script>
			$(document).ready(function() {
				$('#purchase-item-<?=$key?> tbody').multiSelect({
					unselectOn: '',
					keepSelection: true
				});
			});
		</script>
	<?endforeach?>
	<?
	//prent($arTxt);
	/*$txt_all = "";
	foreach($arTxtAll as $supp => $ar){

		$txt_all .= $supp . "\r\n";
		foreach($ar as $model => $cnt){
			$txt_all .= $model . " " . $cnt . "\r\n";
		}
		$txt_all .= "\r\n\r\n";
	}
	?>
	<textarea id="textarea-purchaselist-all" style="position: fixed;left: -9999px;display:none;"><?=$txt_all?></textarea>*/?>
	<script>
		//new Clipboard('.btn-clipboard-list'); // Не забываем инициализировать библиотеку на нашей кнопке
	</script>
	<div class="col-sm-12 row">
	<form action="/admin/ajax/purchase/get_purchase_csv.php" style="position: relative;" method="GET">
		<select class="form-control select_w" name="site_id[]" style="height: 68px;margin: 0 0 0 16px;" id="purchase-csv-sel" multiple>
			<option value="s1">tempusshop.ru</option>
			<option value="s2">tempus.by</option>
			<option value="s3">tempusshop.pl</option>
		</select>
		<select class="form-control select_w" name="currency_list"  style="top: 0px;position: absolute;margin: 0 0px 0 8px;" id="purchase-currency-list">
			<option value="RUB">RUB</option>
			<option value="USD">USD</option>
			<option value="EUR">EUR</option>
			<option value="BYN">BYN</option>
			<option value="PLN">PLN</option>
		</select>
		<button type="submit" class="btn btn-primary " style="margin: 0 0 0 8px;position: absolute;bottom: 4px;">Список</button>
	</form>
	</div>
	<?/*<a href="/admin/ajax/purchase/get_purchase_csv.php" class="btn btn-sm btn-primary" style="margin: 0 0 0 10px;top: -22px;position: relative;" id="get_purchase_csv">Список</a>
	*/?>
	<?
	$arResult["ITEMS"] = array();
	$strSql = "SELECT * FROM ci_price WHERE supplier_id = '82'";// AND site_id = '".$psFilter["website"]."'";
	$results = $DB->Query($strSql, false, $err_mess.__LINE__);
	while ($row = $results->Fetch()){
		$arResult["ITEMS"][] = $row;
	}
	?>
	<?if(is_array($arResult["ITEMS"]) && count($arResult["ITEMS"]) > 0):?>
	<h4>Ожидание</h4>
		<div class="col-sm-12">
						<table class="table">
				<thead>
					<tr>
						<th style="width: 50%"><span class="btn-clipboard" style="cursor:pointer; color: #337ab7;"></span></th>
						<th style="width: 25%">Цена</th>
						<th style="width: 25%"></th>
					</tr>
				</thead>
				<tbody>
				<?foreach($arResult["ITEMS"] as $key => $arItem):?>
						<tr>
							<td><?=$arItem["model"]?></td>
							<td><?=$arItem["price"]?></td>
							<td class="right">

							</td>
						</tr>

				<?endforeach?>
				</tbody>
			</table>
		</div>
	<?endif?>
	<?
}else{
	?>
	<h2 class="color"><span>Не удалось получить список моделей(</span></h2>
	<p>Произошла непредвиденная ошибка. Пожалуйста, обновите страницу и попробуйте позже</p>
	<?
}

$end = debug_microtime_float();
//prent($end - $start, 0, 1);

?>
</div>
<?
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_after.php');
