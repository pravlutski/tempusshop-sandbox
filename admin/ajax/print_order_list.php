<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');

if(CModule::IncludeModule("panel.manager") && count($_POST["order"]) > 0 && count($_POST["basket"]) > 0){

	$order = new OrderService; 
	$arOrder = $order->getOrder(array(), array("ID" => $_POST["order"]));

	//prent($_SERVER);  
	?>
	<table id="printtable" border="1">
		<thead>
			<tr><th>№</th><th align="center">Наименование</th>
			<th align="center">Кол-во</th><th align="center">Цена</th><th align="center">Сумма</th>
			</tr>
		</thead>
		<tbody>
			<?
			$i = 1;
			$total = $cnt = 0;
			?>
			<?foreach($arOrder as $arItem):?>
				<?foreach($arItem["BASKET"] as $key => $arBasket):?>
					<?if(in_array($arBasket["ID"], $_POST["basket"])):?>
					<tr>
						<td align="center"><?=$i?></td>
						<td align="left"><?=$arBasket["NAME"]?></td>
						<td align="center"><?=$arBasket["QUANTITY"]?></td>
						<td align="left"><?=round($arBasket["PRICE"], 2)?></td>
						<td align="left"><?=round($arBasket["PRICE"] * $arBasket["QUANTITY"], 2)?></td>
					</tr>
					<?
					$i++;
					$total += round($arBasket["PRICE"], 2);
					$cnt += $arBasket["QUANTITY"];
					?>
					<?endif?>
				<?endforeach?>
			<?endforeach?>
			<tr>
				<td align="left" colspan="2">Итого:</td>
				<td align="center"><?=$cnt?></td>
				<td align="center"></td>
				<td align="center"><?=$total?></td>
			</tr>
		</tbody>
	</table>
	<?
}else{
	?>
	<h2 class="color">Не удалось сформировать. Внутрення ошибка. Обновите страницу и попробуйте заново.</h2>
	<?
}
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_after.php');