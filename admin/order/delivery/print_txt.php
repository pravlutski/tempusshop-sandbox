<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");?>
<?php
global $USER;
CModule::IncludeModule("main");
CModule::IncludeModule("iblock");

if(!CModule::IncludeModule("panel.manager"))
	return false;

if((count($_REQUEST["order"]) <= 0 && count($_REQUEST["payment_card"]) <= 0) || count($_REQUEST["basket"]) <= 0)
	return false;
/*
формируем массив нал/БН
*/
$ar["ORDER"] = $_REQUEST["order"];
$ar["ORDER_BN"] = $_REQUEST["payment_card"];
if(is_array($ar["ORDER_BN"]) && count($ar["ORDER_BN"]) > 0){
	foreach($ar["ORDER_BN"] as $key => $order_id){
		if(($k = array_search($order_id, $ar["ORDER"])) !== FALSE){
			unset($ar["ORDER"][$k]);
		}
	}
}

$order = new OrderService; 
//$arOrder = $order->getOrder(array(), array("ID" => $_REQUEST["order"]));
$arOrder = $order->getOrder(array(), array("ID" => $ar["ORDER"]));
$arOrderBN = $order->getOrder(array(), array("ID" => $ar["ORDER_BN"]));
	//prent($_REQUEST["basket"]);
	
if(!$arOrder && !$arOrderBN) return;

?>
<style>
.page-break {
	page-break-after: always;
}
</style>
  
<h1 style="text-align:center;font-size: 16px;">Информационный курьерский лист</h1>
<?if(is_array($arOrder) && count($arOrder) > 0):?>
	<table border="1" cellpadding="5" cellspacing="0" style="width: 100%;">
	<tr style="font-size: 8px;">
	<td style="width: 10%;">№</td>
	<td style="text-align:center;width: 10%;">Цена</td>
	<td style="text-align:center;width: 15%;">Модель</td>
	<td style="text-align:center;width: 15%;">Адрес</td>
	<td style="text-align:center;width: 15%;">Имя</td>
	<td style="text-align:center;width: 15%;">Телефон</td>
	<td style="text-align:center;width: 20%;">Комментарий</td>
	</tr>
	<?foreach($arOrder as $arItem):?>
		<?foreach($arItem["BASKET"] as $key => $arBasket):?>
			<?if(in_array($arBasket["ID"], $_REQUEST["basket"])):?>
				<?
				$txt_coomment = "";
				if($arItem["USER_DESCRIPTION"])
					$txt_coomment = "Клиент: " . $arItem["USER_DESCRIPTION"] . "<br>";
				if($arItem["COMMENTS"])
					$txt_coomment .= "Менеджер: " . $arItem["COMMENTS"];
					
				//$fio = str_replace(array("В"),'',$arItem["FIO"]);
				$fio = $arItem["FIO"];
				?>
				<tr style="font-size: 8px;">
					<td style="width: 10%;"><?=$arItem["ORDER_ID"]?></td>
					<td style="text-align:center;width: 10%;"><?=number_format($arBasket["PRICE"], 2, ',', ' ')?></td>
					<td style="text-align:center;width: 15%;"><?=$arBasket["NAME"]?></td>
					<td style="text-align:center;width: 15%;"><?=$arItem["ADDRESS"]?></td>
					<td style="text-align:center;width: 15%;"><?=$fio?></td>
					<td style="text-align:center;width: 15%;"><?=$arItem["PHONE"]?></td>
					<td style="text-align:left;width: 20%;"><?=$txt_coomment?></td>
				</tr>
			<?endif?>
		<?endforeach?>
	<?endforeach?>
	</table>
<?endif;?>
<?
/* БН */
if(is_array($arOrderBN) && count($arOrderBN) > 0):?>
	<p>Оплата картой</p><table border="1" cellpadding="5" cellspacing="0" style="width: 100%;">
	<tr style="font-size: 8px;">
	<td style="width: 10%;">№</td>
	<td style="text-align:center;width: 10%;">Цена</td>
	<td style="text-align:center;width: 15%;">Модель</td>
	<td style="text-align:center;width: 15%;">Адрес</td>
	<td style="text-align:center;width: 15%;">Имя</td>
	<td style="text-align:center;width: 15%;">Телефон</td>
	<td style="text-align:center;width: 20%;">Комментарий</td>
	</tr>
	<?foreach($arOrderBN as $arItem):?>
		<?foreach($arItem["BASKET"] as $key => $arBasket):?>
			<?if(in_array($arBasket["ID"], $_REQUEST["basket"])):?>
				<?
				$txt_coomment = "";
				if($arItem["USER_DESCRIPTION"])
					$txt_coomment = "Клиент: " . $arItem["USER_DESCRIPTION"] . "<br>";
				if($arItem["COMMENTS"])
					$txt_coomment .= "Менеджер: " . $arItem["COMMENTS"];
				?>	

				<tr style="font-size: 8px;">
					<td style="width: 10%;"><?=$arItem["ORDER_ID"]?></td>
					<td style="text-align:center;width: 10%;"><?=number_format($arBasket["PRICE"], 2, ',', ' ')?></td>
					<td style="text-align:center;width: 15%;"><?=$arBasket["NAME"]?></td>
					<td style="text-align:center;width: 15%;"><?=$arItem["ADDRESS"]?></td>
					<td style="text-align:center;width: 15%;"><?=str_replace(array("я","в","ч","с","а","В"),'',$arItem["FIO"])?></td>
					<td style="text-align:center;width: 15%;"><?=$arItem["PHONE"]?></td>
					<td style="text-align:left;width: 20%;"><?=$txt_coomment?></td>
				</tr>
			<?endif?>
		<?endforeach?>
	<?endforeach?>
	</table>
<?endif;?>
<p class="page-break"></p>
<?
$order = new OrderService; 
$arOrder = $order->getOrder(array(), array("ID" => $_REQUEST["order"]));

if(is_array($arOrder) && count($arOrder) <= 0) return;
$obj = new OrderService;
foreach($arOrder as $key => $order){
//	if($order["STATUS_ID"] != "CR")
//		$obj->setStatusOrder($order["ID"], "CR");
}
?>

<table border="0" cellpadding="0" cellspacing="0" width="100%">
	<tr>
		<td width="80%"><br><br><span style="text-align:left;font-size: 10px;line-height:12px;"><?=date("d") . ' ' . getNameMonth(date("n")) . ' ' . date("Y")?></span></td>
		<td width="20%" style="text-align: left;"><span style="text-align:rigth;font-size: 10px;line-height:12px;">Приложение №1 <br>к договору №11 <br>от «23» июня 2014 г.</span></td>
	</tr>
</table>

<h1 style="text-align:center;font-size: 16px;">АКТ<br><span style="text-align:center;font-size: 14px;">приема-передачи товара</span></h1>
<p style="text-align:justify;font-size: 10px;line-height:14px;">ООО «Роялтайм», в лице директора Шевцова Андрея Анатольевича, действующего на основании устава, именуемое в дальнейшем Заказчик, с одной стороны и ".$_POST["selcourier"].", именуемый в дальнейшем Подрядчик, с другой стороны (в дальнейшем вместе именуемые «Стороны» и по отдельности «Сторона»), составили настоящий Акт о нижеследующем: </p>
<p style="text-align:justify;font-size: 10px;line-height:14px;">1. В соответствии с п. 1.2.1 Договора между Сторонами № 11 от «23» июня 2014 года Заказчик передает, а Подрядчик принимает Товар для транспортировки и реализации следующего ассортимента и количества:</p><br>
<table border="1" cellpadding="5" cellspacing="0" style="width: 100%;">
<thead>
	<tr style="font-size: 9px;">
		<th style="text-align:center;width: 5%;">№</th>
		<th style="text-align:center;width: 55%;">Наименование</th>
		<th style="text-align:center;width: 10%;">Кол-во</th>
		<th style="text-align:center;width: 15%;">Цена, включая НДС</th>
		<th style="text-align:center;width: 15%;">Сумма, включая НДС</th>
	</tr>
</thead>
<tbody>
<?
$i = 1;
$total = $cnt = 0;
?>
<?foreach($arOrder as $arItem):?>
	<?foreach($arItem["BASKET"] as $key => $arBasket):?>
		<?if(in_array($arBasket["ID"], $_REQUEST["basket"])):?>
			<tr>
				<td style="text-align:center;width: 5%;"><?=$i?></td>
				<td style="text-align:left;width: 55%;"><?=$arBasket["NAME"]?></td>
				<td style="text-align:center;width: 10%;"><?=$arBasket["QUANTITY"]?></td>
				<td style="text-align:left;width: 15%;"><?=number_format($arBasket["PRICE"], 2, ',', ' ')?></td>
				<td style="text-align:left;width: 15%;"><?=number_format($arBasket["PRICE"] * $arBasket["QUANTITY"], 2, ',', ' ')?></td>
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
		<td align="left"><?=number_format($total, 2, ',', ' ')?></td>
	</tr>
</tbody>
</table>
<p style="text-align:justify;font-size: 10px;line-height:14px;">Стоимость Товара поставленного в соответствии с условиями Договора составляет <?=number_format($total, 2, '.', ' ')?> руб. (<?=num2str($total)?>) с учетом НДС.</p>
<p style="text-align:justify;font-size: 10px;line-height:14px;">2. Настоящий Акт составлен в двух экземплярах, имеющих равную юридическую силу, по одному экземпляру для каждой из Сторон и является неотъемлемой частью Договора между Сторонами.</p>

<table border="0" cellpadding="0" cellspacing="0" width="100%">
	<tr>
		<td width="50%">Уполномоченный представитель заказчика</td>
		<td width="50%" style="text-align: left;">Подрядчик</td>
	</tr>
	<tr>
		<td width="50%"></td>
		<td width="50%" style="text-align: left;"></td>
	</tr>
	<tr>
		<td width="50%">______________________________ <?=addslashes($_POST["selagent"])?></td>
		<td width="50%" style="text-align: left;">____________________________ <?=addslashes($_POST["selcourier"])?></td>
	</tr>
</table>

<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_after.php");?>