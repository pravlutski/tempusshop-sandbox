<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');

if(CModule::IncludeModule("panel.manager") && count($_POST["order"]) > 0){

	$order = new OrderService; 
	$arOrder = $order->getOrder(array(), array("ID" => $_POST["order"]));
	
	$objCourier = new CPanelCourier;
	$arCourier = $objCourier->getList();
	
	$objAgent = new CPanelEmployee;
	$arAgent = $objAgent->getList();
	?>
	<form action="/admin/order/delivery/print_cash.php" method="POST" name="" id="">
	<table id="printtable1" class="tbl-print" border="1">
		<thead>
			<tr><th>№</th><th align="center">Наименование</th>
			<th align="center">Вырученные ДС</th>
			<th align="center">Стоимость <br>услуг подрядчика</th><th align="center">Сумма</th>
			<th style="width: 40px;"></th>
			</tr>
		</thead>
		<tbody>
			<?
			$i = 1;
			$total = 0;
			?>
			<?foreach($arOrder as $arItem):?>
				<?foreach($arItem["BASKET"] as $key => $arBasket):?>
					<?if($arBasket["QUANTITY"] > 1):?>
						<?for($j = 1; $j <= $arBasket["QUANTITY"]; $j++):?>
							<tr>
								<td class='number' align="center"><?=$i?></td>
								<td align="left"><input type="hidden" name="item[<?=$arBasket["ID"]?>][name]" value="<?=$arBasket["NAME"]?>"><?=$arBasket["NAME"]?></td>
								<td align="center"><input type="text" name="item[<?=$arBasket["ID"]?>][price]" style="width: 100%;" value="<?=number_format($arBasket["PRICE"], 2, '.', ' ')?>"></td>
								<td align="center"><input type="text" name="item[<?=$arBasket["ID"]?>][price_taxi]" style="width: 100%;" value="0"></td>
								<td align="left" class="iprice"><span><?=number_format($arBasket["PRICE"], 2, '.', ' ')?></span></td>
								<td align="center"><a href="#" class="ico-delete"></a></td>
							</tr>
							<?$total += $arBasket["PRICE"];?>
						<?endfor?>
					<?else:?>
						<tr>
							<td class='number' align="center"><?=$i?></td>
							<td align="left"><input type="hidden" name="item[<?=$arBasket["ID"]?>][name]" value="<?=$arBasket["NAME"]?>"><?=$arBasket["NAME"]?></td>
							<td align="center"><input type="text" name="item[<?=$arBasket["ID"]?>][price]" style="width: 100%;" value="<?=number_format($arBasket["PRICE"], 2, '.', ' ')?>"></td>
							<td align="center"><input type="text" name="item[<?=$arBasket["ID"]?>][price_taxi]" style="width: 100%;" value="0"></td>
							<td align="left" class="iprice"><span><?=number_format($arBasket["PRICE"], 2, '.', ' ')?></span></td>
							<td align="center"><a href="#" class="ico-delete"></a></td>
						</tr>
						<?$total += $arBasket["PRICE"];?>
					<?endif?>
				<?$i++;?>
				<?endforeach?>
			<?endforeach?>
		</tbody>
		<thead>
			<tr>
				<td colspan="2"><b>Итого:</b></td>
				<td align="center" class="iprice" id="sum_price"><span><?=number_format($total, 2, '.', ' ')?></span></td>
				<td align="center" class="iprice" id="sum_price_taxi"><span>0,00</span></td>
				<td colspan="2" align="center" id="allmoney"><?=number_format($total, 2, '.', ' ')?></td>
			</tr>
			
		</thead>
	</table>
	<button id="btn_add_cash" type="button" class="btn btn-success" style="margin: 10px 0 10px 0;">+ Добавить</button>
	<table id="printtable2" class="tbl-print" border="1">
		<thead>
			<tr><th>№</th><th align="center">Наименование</th>
			<th align="center">Кол-во</th>
			<th align="center">Цена, включая<br>НДС</th>
			<th align="center">Сумма,<br>включая НДС</th>
			<th style="width: 40px;"></th>
			</tr>
		</thead>
		<tbody>
		</tbody>
		<thead>
			<tr>
				<td colspan="3"><b>Итого:</b></td>
				<td align="center" class="iprice" id="sum_return_price"><span>0,00</span></td>
				<td align="center" class="iprice" colspan="2" id="all_sum_return_price"><span>0,00</span></td>
			</tr>
		</thead>
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