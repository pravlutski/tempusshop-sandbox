<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');

if(!CModule::IncludeModule("crm_courier") || !CModule::IncludeModule("panel.manager")) return;
global $USER;

$ID = intval($_POST["id"]);
?>
<?if($ID > 0 && ($USER->IsAuthorized() && (in_array("6",$USER->GetUserGroupArray())||in_array("12",$USER->GetUserGroupArray())||in_array("21",$USER->GetUserGroupArray())||$USER->isAdmin()))):?>

	<?
	$rsDelivery = new CCourierDelivery();
	$rsList = $rsDelivery->GetList(array(), array("ID" => $ID))[0];
	//prent($rsList);
	?>

	<?if($rsList):?>
		<?
		$order = $res["response"]["orders"][0];

		?>
		<div class="form-order-update" style="padding: 5px 5px 5px 4px;">
			<div class="alert-danger"></div>
			<div class="panel panel-default" style="padding: 5px 5px 5px 4px;">
				<p style="margin: 2px 0 5px 13px;">Выручка</p>
				<input type="number" class="form-control" name="price-order" style="" placeholder="<?=$rsList["PRICE_ORDER"]?>" value="<?=$rsList["PRICE_ORDER"]?>">
				<div class="clear"></div>
				<p style="margin: 2px 0 5px 13px;">Стоимость доставки</p>
				<input type="number" class="form-control" name="price-delivery" style="" placeholder="<?=$rsList["PRICE_DELIVERY"]?>" value="<?=$rsList["PRICE_DELIVERY"]?>">
				<div class="clear"></div>
				<p style="margin: 2px 0 5px 13px;">Комментарий</p>
				<textarea class="form-control search_input" name="comment" style="margin: 0 0 0 0px;width: 100%;"><?=$rsList["COMMENT"]?></textarea>
				<div class="clear"></div>
				<p style="margin: 2px 0 5px 13px;text-align: right;color: red;"><input type="checkbox" class="" name="bad-client" style="margin: 0 5px 0px 0;" value="Y" <?if($rsList["BAD_CLIENT"] == "Y"):?>checked<?endif?>>Без SMS</p>
				<div class="clear"></div>
				<select name="status" style="margin: 0 0 0 0px;width: 100%;    height: 30px;">
					<option <?if($rsList["STATUS"] == "delivery"):?>selected<?endif?> value="delivery">Доставлен</option>
					<option <?if($rsList["STATUS"] == "nodelivery"):?>selected<?endif?> value="nodelivery">Не доставлен</option>
					<option <?if($rsList["STATUS"] == "manual"):?>selected<?endif?> value="manual">Ручная запись</option>
				</select>
				<div class="clear"></div>
			</div>
			<button type="button" class="btn btn-success btn_big_width order-updated" data-id="<?=$ID?>">Сохранить</button>
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
