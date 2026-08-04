<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');

if(CModule::IncludeModule("panel.manager") && count($_POST["order"]) > 0){

	$element_id = intval($_POST["ID"]);
	$name = htmlspecialchars(stripslashes(trim($_POST['NAME'])));
	$phone = htmlspecialchars(stripslashes(trim($_POST['PHONE'])));
	$email = htmlspecialchars(stripslashes(trim($_POST['EMAIL'])));
	$title = htmlspecialchars(stripslashes(trim($_POST['FORM_TITLE'])));

	$order = new OrderService; 
	$arOrder = $order->getOrder(array(), array("ID" => $_POST["order"]));
	
	$objCourier = new CPanelCourier;
	$arCourier = $objCourier->getList();
	
	$objAgent = new CPanelEmployee;
	$arAgent = $objAgent->getList();
	//prent($arOrder);
				?>
	<form action="/admin/order/delivery/print.php" method="POST" name="" id="">
	<table id="printtable" class="tbl-print" border="1">
		<thead>
			<tr><th>№</th><th>Оплата картой</th><th align="center">Наименование</th>
			<th align="center">Кол-во</th><th align="center">Цена</th><th align="center">Сумма</th>
<?/*			<th></th>*/?>
			</tr>
		</thead>
		<tbody>
			<?
			$i = 1;
			?>
			<?foreach($arOrder as $arItem):?>
				<input type="hidden" name="order[]" value="<?=$arItem["ID"]?>">
				<?foreach($arItem["BASKET"] as $key => $arBasket):?>
				<tr>
					<input type="hidden" name="basket[]" value="<?=$arBasket["ID"]?>">
					<td align="center"><?=$i?></td>
					<td align="center"><?if($key == 0):?><input type="checkbox" name="payment_card[]" value="<?=$arItem["ID"]?>"><?endif?></td>
					<td align="left"><?=$arBasket["NAME"]?></td>
					<td align="center"><?=$arBasket["QUANTITY"]?></td>
					<td align="left"><?=round($arBasket["PRICE"], 2)?></td>
					<td align="left"><?=round($arBasket["PRICE"] * $arBasket["QUANTITY"], 2)?></td>
<?/*					<td align="center"><a href="#" class="ico-delete"></a></td>*/?>
				</tr>
				<?$i++;?>
				<?endforeach?>
			<?endforeach?>
		</tbody>
	</table>
	<div style="margin: 8px 0 2px 0;">
	<label for="selagent">Выберите представителя:</label>
	<select id="selagent" name="selagent">
		<?foreach($arAgent as $key =>$arItem):?>
			<option value="<?=$arItem['name']?>"><?=$arItem['name']?></option>
		<?endforeach?>
	</select>
	</div>
	<div class="">
	<label for="selcourier">Выберите подрядчика:</label>
	<select id="selcourier" name="selcourier">
		<?foreach($arCourier as $key =>$arItem):?>
			<option value="<?=$arItem['name']?>"><?=$arItem['name']?></option>
		<?endforeach?>
	</select>
	</div>
	</form>
			<?

}else{
	?>
	<h2 class="color"><span>Отправить письмо не удалось(</span></h2>
	<p>Произошла непредвиденная ошибка. Пожалуйста, обновите страницу и попробуйте позже</p>
	<?
}
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_after.php');