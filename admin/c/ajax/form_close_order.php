<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');

if(!CModule::IncludeModule("crm_courier") || !CModule::IncludeModule("panel.manager")) return;

$orderID = intval($_POST["order_id"]);
?>
<?if($orderID > 0):?>

	<?
	$objCourier = new CCourier();
	
	$arFilter = array(
		"extendedStatus" => array("delivering"),
		"ids" => array($orderID),
		"couriers" => array($objCourier->courierID),
	);

	$res = $objCourier->getOrderCrm($arFilter);
	?>
	
	<?if($res["statusCode"] == "200" && count($res["response"]["orders"]) == 1):?>
		<?
		$order = $res["response"]["orders"][0];
		$sum = $order["totalSumm"] - $order["prepaySum"];
		?>
		<div class="form-order-close" style="padding: 5px 5px 5px 4px;">
			<div class="alert-danger"></div>
			<div class="panel panel-default" style="padding: 5px 5px 5px 4px;">
				<p style="margin: 2px 0 5px 13px;">Выручка</p>
				<input type="number" class="form-control" name="price-order" style="" placeholder="<?=$sum?>" value="<?=$sum?>" data-value="<?=$sum?>">
				<div class="clear"></div>
				<p style="margin: 2px 0 5px 13px;">Стоимость доставки</p>
				<input type="number" class="form-control" name="price-delivery" style="" placeholder="0" value="0">
				<div class="clear"></div>
				<p style="margin: 2px 0 5px 13px;">Комментарий</p>
				<textarea class="form-control search_input" name="comment" style="margin: 0 0 0 0px;width: 100%;"></textarea>
				<div class="clear"></div>
				<p style="margin: 2px 0 5px 13px;text-align: right;color: red;"><input type="checkbox" class="" name="item-return" style="margin: 0 5px 0px 0;" value="Y"><span style="color:black;margin: 0 10px 0 0;">Ремонт/возврат/обмен</span><input type="checkbox" class="" name="bad-client" style="margin: 0 5px 0px 0;" value="Y">Без SMS</p>
				<p style="margin: 2px 0 5px 13px;text-align: right;"></p>
				<div class="clear"></div>
			</div>
			<button type="button" class="btn btn-success btn_big_width order-delivered" data-id="<?=$orderID?>">Вручен</button>
			<button type="button" class="btn btn-danger btn_big_width order-nodelivered" data-id="<?=$orderID?>">Не вручен</button>
		</div>
	<?elseif($res["statusCode"] == "400" && $res["response"]["errorMsg"]):?>
		<p><?=$res["response"]["errorMsg"]?></p>
	<?else:?>
		<p>Не найден ORDER_ID или статус заказа не позволяет его завершить или выбран другой курьер</p>
	<?endif?>
<?else:?>
	<p>Не найден ORDER_ID</p>
<?endif?>
<?require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_after.php');