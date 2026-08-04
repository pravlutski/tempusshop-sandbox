<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");?>
<?if(!CModule::IncludeModule('panel.manager'))return;?>
<?
global $USER;
AccessValidator::checkIfAllowed(); // Менеджер прав
use Bitrix\Main\Page\Asset;
Asset::getInstance()->addJs("/bitrix/templates/admin_courier/js/jquery-ui.min.js");
//Asset::getInstance()->addJs("/bitrix/templates/admin_courier/js/bootstrap.js");

Asset::getInstance()->addCss("/bitrix/templates/admin_courier/css/datepicker.css");
Asset::getInstance()->addCss("/bitrix/templates/admin_courier/css/jquery-ui.min.css");

Asset::getInstance()->addJs("/bitrix/templates/admin_panel/js/jquery-ui-timepicker-addon.js");

if (!class_exists('WildberriesAPI')) {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/local/classes/WildberriesAPI.php';
}

if(!CModule::IncludeModule("maxyss.wb")) return false;
?>
<h1 class="page-header">Установить статус заказа</h1>

<?
$obj = new OrderService;
$arResult["STATUS"] = $obj->getStatusOrderList();

$wb = new WildberriesAPI("WR");
$arSupplies = array_reverse($wb->getSupplies());
foreach($arSupplies as $arItem){
	if($arItem["done"] == false){
		$arResult["SUPPLIERS_WB"][] = $arItem;
	}
}

$wb = new WildberriesAPI("TL");
$arSupplies = array_reverse($wb->getSupplies());
foreach($arSupplies as $arItem){
	if($arItem["done"] == false){
		$arResult["SUPPLIERS_WB_TL"][] = $arItem;
	}
}

$wb = new WildberriesAPI("WB_BY");
$arSupplies = array_reverse($wb->getSupplies());
foreach($arSupplies as $arItem){
	if($arItem["done"] == false){
		$arResult["SUPPLIERS_WB_BY"][] = $arItem;
	}
}
//prent($arResult["SUPPLIERS_WB"]);
$objMS = new MoyskladAPI('s1');
foreach ($objMS->getWarehouses() as $key => $value) {
	$warehouses[] = [
		'name'=>$value['name'] . ' (RU)',
		'value' => $value['id']
	];
}

$objMS = new MoyskladAPI('s2');
foreach ($objMS->getWarehouses() as $key => $value) {
	$warehouses[] = [
		'name'=>$value['name'] . ' (BY)',
		'value' => $value['id']
	];
}

//prent($arResult["STATUS"]);

global $USER;
$arGroups = $USER->GetUserGroupArray();

if (!$USER->IsAdmin()  && !in_array_(12, $arGroups) && !in_array_(6, $arGroups) && !in_array_(19, $arGroups))
{
    $APPLICATION->AuthForm(GetMessage("PERMISION_DENIED"));
    return;
}

if($_SERVER["REQUEST_METHOD"] == "POST" && $_POST["settings_set_order_submit"] === "Y"){

	$ar = array(
		"DEMAND" => $_POST["status_demand"],
		"SALES_RETURN" => $_POST["status_salesreturn"],
	);
	CProSet::setOption("ORDER_STATUS_SEND_MS", json_encode($ar));
	//$arResult["YMARKET_SHOP_HIDE"] = json_decode(CProSet::getOption("ORDER_STATUS_SEND_MS"), true);
}
$arStatus = json_decode(CProSet::getOption("ORDER_STATUS_SEND_MS"), true);
?>
<script>
$(document).ready(function() {
	$("#source").on("change", function () {
		if($(this).val() == "wb"){
			$("#supplier-wb").show();
		}else{
			$("#supplier-wb").hide();
		}
	});
	$("#source").on("change", function () {
		if($(this).val() == "wb_tl"){
			$("#supplier-wb_tl").show();
		}else{
			$("#supplier-wb_tl").hide();
		}
		if($(this).val() == "wb_by"){
			$("#supplier-wb_by").show();
		}else{
			$("#supplier-wb_by").hide();
		}
	});
	$("#status").on("change", function () {
		if($(this).val() == "NZ"){
			$("#warehouse-ms").show();
		}else if ($(this).val() == "RD"){
			$("#warehouse-ms").show();
		}else{
			$("#warehouse-ms").hide();
		}
	});
	$("#source").on("change", function () {
		if( $(this).val() == "ozon" || $(this).val() == "ozon_by" ){
			$("#ozon-type").show();
		}else{
			$("#ozon-type").hide();
		}
	});
});
</script>
<div class="progress" style="display: none;">
	<div class="progress-bar progress-bar-striped active" role="progressbar" aria-valuenow="50" aria-valuemin="0" aria-valuemax="100" style="width: 0%;"></div>
</div>
<input id="offset" name="offset" type="hidden">

<form action="#" method="post" id="form-set-status">
	<div class="page_header_selects clearfix">
		<div class="page_header_select" style=" width: 45%;margin: 0;">
			<label style="display: block;">Список заказов (Номера заказов)</label>
			<textarea class="form-control select_w" name="list_order" id="list_order" style="width: 90%;height: 200px;"></textarea>
		</div>
		<div class="page_header_select" style="    width: 50%;">
			<label style="display: block;">Статус</label>
			<select class="form-control select_w" name="status" id="status">
				<option>--- Выберите статус ---</option>
				<?foreach($arResult["STATUS"] as $key => $arItem):?>
				<option value="<?=$arItem["ID"]?>"><?=$arItem["NAME"]?></option>
				<?endforeach?>
			</select>
			<label style="display: block;    margin: 10px 0 0 0;">Источник</label>
			<select class="form-control select_w" name="source" id="source">
				<option value="site" selected>Сайт</option>
				<option value="site_tn" >Сайт (Трек-код)</option>
				<option value="wb">WB (WR)</option>
				<option value="wb_tl">WB (TL)</option>
				<option value="ozon">OZON</option>
				<option value="sber">SBER</option>
				<option value="yandex">YANDEX</option>
				<option value="id">ID</option>
				<option value="wb_by">WB (BY)</option>
        <option value="ozon_by">OZON (BY)</option>
			</select>
			<?if(!empty($arResult["SUPPLIERS_WB"])):?>
			<div id="supplier-wb" style="display:none;    margin: 0 0 10px 0;">
				<p style="display: block;margin: 0;">Выберите поставку WB (WR)</p>
				<select class="form-control select_w" name="supplier-wb">
					<option>--- Выберите статус ---</option>
					<option value="create">Создать новую поставку</option>
					<?foreach($arResult["SUPPLIERS_WB"] as $key => $arItem):?>
					<option value="<?=$arItem["id"]?>"><?=$arItem["id"]?> - <?=$arItem["name"]?></option>
					<?endforeach?>
				</select>
			</div>
			<?endif?>
			<?if(!empty($arResult["SUPPLIERS_WB_TL"])):?>
			<div id="supplier-wb_tl" style="display:none;    margin: 0 0 10px 0;">
				<p style="display: block;margin: 0;">Выберите поставку WB (TL)</p>
				<select class="form-control select_w" name="supplier-wb_tl">
					<option>--- Выберите статус ---</option>
					<option value="create">Создать новую поставку</option>
					<?foreach($arResult["SUPPLIERS_WB_TL"] as $key => $arItem):?>
					<option value="<?=$arItem["id"]?>"><?=$arItem["id"]?> - <?=$arItem["name"]?></option>
					<?endforeach?>
				</select>
			</div>
			<?endif?>
			<?if(!empty($arResult["SUPPLIERS_WB_BY"])):?>
			<div id="supplier-wb_by" style="display:none; margin: 0 0 10px 0;">
				<p style="display: block;margin: 0;">Выберите поставку WB BY</p>
				<select class="form-control select_w" name="supplier-wb_by">
					<option>--- Выберите статус ---</option>
					<option value="create">Создать новую поставку</option>
					<?foreach($arResult["SUPPLIERS_WB_BY"] as $key => $arItem):?>
					<option value="<?=$arItem["id"]?>"><?=$arItem["id"]?> - <?=$arItem["name"]?></option>
					<?endforeach?>
				</select>
			</div>
			<?endif?>
			<div id="warehouse-ms" style="display:none;    margin: 0 0 10px 0;">
				<p style="display: block;margin: 0;">Выберите склад в МС</p>
				<select class="form-control select_w" name="warehouse">
					<option>--- Выберите склад ---</option>
					<?foreach($warehouses as $key => $arItem):?>
					<option value="<?=$arItem["value"]?>" ><?=$arItem["name"]?></option>
					<?endforeach?>
				</select>
			</div>
			<div id="ozon-type" style="display:none;    margin: 0 0 10px 0;">
				<p style="display: block;margin: 0;">Выберите индификатор заказа</p>
				<select class="form-control select_w" name="typeNumber">
					<option>--- Выберите индификатор заказа ---</option>
					<option value="number" >Номер заказа</option>
					<option value="barcode" >Нижний штрихкод</option>
				</select>
			</div>
			<?/*<label style="display: block;    margin: 0;">Создать в MS</label>
			<select class="form-control select_w" name="send_ms" id="send_ms">
				<option>--- Выберите ---</option>
				<option value="demand">Отгрузки</option>
				<option value="salesreturn">Возвраты</option>
			</select>*/?>
			<p><button type="button" class="btn btn-primary btn_big_width" data-toggle="modal" data-target="#settings_set_order" id="btn_settings_set_order">Настройки</button></p>
		</div>
	</div>

	<a href="#" id="runScript"  class="btn btn-primary btn_big_width" data-action="run">Установить</a>
</form>
<div id="text-status"></div>

<div class="col-sm-12 row" style="">
	<p style="font-size: 14px;margin: 10px 0 5px 0;"><span style="float: left;margin: 5px 5px 0 0;">Дата лога ОТ</span><input type="text" name="date_log" id="date_log" value="<?=($_POST["date_log"] ? $_POST["date_log"] : date("d.m.Y") . " 00:00")?>" class="form-control " autocomplete="off" style="width: 200px;margin: 10px 20px 0 0;"></p>

</div>
<div id="log-block"></div>

<script>
function getLogStatusOrder(){
	var date = $("#date_log").val();

	$.ajax({
		type: "POST",
		data: "date=" + date,
		url: "/admin/ajax/get_log_order_status.php",
		async: false,
		success: function(data) {
			$("#log-block").html(data);
		},
		error:function(data) {
			alert("Не удалось получить лог");
			$("#log-block").html("");
		},
		complete: function(xhr, textStatus) {}
	});
	return false;
}
$(function() {
	$( "#date_log").datetimepicker({
		dateFormat: "dd.mm.yy",
		onSelect: function() {
			getLogStatusOrder();
		}
	});
});
getLogStatusOrder();
</script>
<hr>
<div id="log-block"><?=$log?></div>
<script>
function setCookie (offset){

    	var ws=new Date();
		if (!offset) {
			ws.setMinutes(10-ws.getMinutes());
		} else {
			ws.setMinutes(10+ws.getMinutes());
		}

		document.cookie="scriptOffsetOffset="+offset+";expires="+ws.toGMTString();

}

function getCookie(name) {
		var cookie = " " + document.cookie;
		var search = " " + name + "=";
		var setStr = null;
		var offset = 0;
		var end = 0;
		if (cookie.length > 0) {
			offset = cookie.indexOf(search);
			if (offset != -1) {
				offset += search.length;
				end = cookie.indexOf(";", offset)
				if (end == -1) {
					end = cookie.length;
				}
				setStr = unescape(cookie.substring(offset, end));
			}
		}
		return(setStr);
	}

function showProcess (sucsess, offset, action) {


	$('.progress-bar').text(parseFloat(sucsess * 100).toFixed(2) + '%');
	$('.progress-bar').css('width', sucsess * 100 + '%');
	//setCookie(offset);

	//$('#runScript').click(function(){
	//	document.location.href=document.location.href
	//});

	scriptOffset(offset, action);

}

function scriptOffset (offset, action) {

	//if(action == "stop") return;
	//var action = $('#runScript').data('action');

	var dataScript = $("#form-set-status").serialize() + "&action=" + action + "&offset=" + offset;
	console.log(dataScript);
	$.ajax({
		url: "/admin/utilities/set_status_order_ajax.php",
		type: "POST",
		data: dataScript,
		dataType: "json",
		success: function(data){
			console.log(data);
			var textStatus = "";

			if(data.error !== null && data.error.length !== null){
				$.each(data.error, function(key, value){
					textStatus += value;
				});
			}
				//
			if(data.info !== null && data.info.length !== null){
				$.each(data.info, function(key, value){
					textStatus += value;
				});
			}

			$("#text-status").append(textStatus);

			if(data.sucsess < 1) {
				showProcess(data.sucsess, data.offset, action);
			} else {
				//setCookie();
				$('.progress-bar').css('width','100%');
				$('.progress-bar').text('OK');
				$('#runScript').text('Установить');
				$('#runScript').attr("data-action", "run");
				$('#runScript').removeAttr("disabled");
			}

		},
		error: function(data){
			console.log(data);
		}
	});
}

$(document).ready(function() {

	$('#runScript').click(function() {
		$('#runScript').attr("disabled", "disabled");

		$('.progress').show();
		$('.progress-bar').css('width','0%');

		var action = $('#runScript').data('action');

		if($(this).attr("data-action") == "stop"){

			//$(this).text('Установить');
			$(this).attr("data-action", "run");
			scriptOffset(0, "stop");
		}else{
			$('#runScript').attr("disabled", "disabled");
			$("#text-status").html("");
			//$(this).text('Стоп!');
			$(this).attr("data-action", "stop");
			scriptOffset(0, "run");
		}





		return false;

	});

});
</script>

<div class="modal fade" id="settings_set_order" tabindex="-1" role="dialog" aria-labelledby="settings_set_order" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="myModalLabel">Настройки</h4>
            </div>
			<form class="form-horizontal" id="apply-settings-brand" action="<?=$APPLICATION->GetCurPage();?>" method="POST">
				<div class="modal-body modal_big_padding">
					<table class="table" id="brand-list">
						<tbody>
							<tr>
								<td>Отгрузки</td>
								<td>
									<select class="form-control select_w" name="status_demand[]" style="width: 215px;" multiple>
										<option>--- Выберите статус ---</option>
										<?foreach($arResult["STATUS"] as $key => $arItem):?>
										<option value="<?=$arItem["ID"]?>" <?if(in_array_($arItem["ID"], $arStatus["DEMAND"])):?>selected<?endif?>><?=$arItem["NAME"]?></option>
										<?endforeach?>
									</select>
								</td>
							</tr>
							<tr>
								<td>Возвраты</td>
								<td>
									<select class="form-control select_w" name="status_salesreturn[]" style="width: 215px;" multiple>
										<option>--- Выберите статус ---</option>
										<?foreach($arResult["STATUS"] as $key => $arItem):?>
										<option value="<?=$arItem["ID"]?>" <?if(in_array_($arItem["ID"], $arStatus["SALES_RETURN"])):?>selected<?endif?>><?=$arItem["NAME"]?></option>
										<?endforeach?>
									</select>
								</td>
							</tr>
						</tbody>
					</table>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-default"  data-dismiss="modal"  aria-label="Close">Close</button>
					<button type="submit" class="btn btn-primary" name="settings_set_order_submit" value="Y">Сохранить</button>
				</div>
			</form>
        </div>
    </div>
</div>

<style>
#log-block p{
	margin: 0;
    font-size: 12px;
}
</style>
<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>
