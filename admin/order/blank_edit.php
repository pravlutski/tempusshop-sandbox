<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetTitle("Заполнение бланка");
?>
<?php
global $USER;
CModule::IncludeModule("main");
CModule::IncludeModule("iblock");

if(!CModule::IncludeModule("panel.manager"))
	die("Непредвиденная ошибка");
if($_REQUEST["order"]){
	$ob = new OrderService;
	$arResult["ORDER"] = $ob->getOrder(array(), array("ID" => $_REQUEST["order"]));
//	prent($res);
//	prent($arResult["ORDER"]);
}
?>
<div class="col-sm-12 row" id="blank">
	<h2 class="blank_name">Заполнение бланка</h2>
		<a href="/admin/utilities/" class="btn btn-default">Назад</a>
	<hr>
	<?if(!$arResult["ORDER"]):?>
	<p style="color:red;">Не выбран заказ</a>
	<?return;?>
	<?endif?>
	<div class="twelve columns">
		<form class="twelve columns" method="post" action="/admin/order/pdf_blank.php" id="blanks">
			<?foreach($arResult["ORDER"] as $key => $arOrder):?>
			<fieldset class="col-sm-12 panel panel-default" style=""> 
				<fieldset id="recipient" class="validationEngineContainer" style="margin: 5px 0 5px 0;padding: 0 0 10px 0;">
					<legend>Данные получателя. Заказ №<?=$arOrder["ID"]?></legend>
					<div class="block" id="to_block_1">
						<div class="">
							<div class="two columns">
								  <label class="inline right">Кому</label>
							</div>
							<div class="ten columns left end">
								  <input id="to_name_1" type="text" placeholder="Жуков Максим Александрович" value="<?=$arOrder["FIO"]?>" rel="" name="PrintAdress[to][<?=$arOrder["ID"]?>][to_name]" class="form-control input_fix_w2">
							</div>
						</div>
						<div class="">
							<div class="two columns">
								<label class="inline right">Куда</label>
							</div>
							<div class="ten columns left end">
								<input id="to_adress_1" type="text" value="<?=$arOrder["ADDRESS"]?>" placeholder="г. Минск, ул. Немига, 3" rel="" name="PrintAdress[to][<?=$arOrder["ID"]?>][to_adress]" class="form-control input_fix_w2">
							</div>
						</div>
						<div class="">
							<div class="two columns">
								<label class="inline right">Индекс</label>
							</div>
							<div class="three columns left end">
								<input id="to_zip_1" type="text" placeholder="000000" value="" rel="" name="PrintAdress[to][<?=$arOrder["ID"]?>][to_zip]" class="form-control input_fix_w2" maxlength="6">
							</div>
						</div>
						<div class="">
							<div class="two columns">
								<label class="inline right">Сумма</label>
							</div>
							<div class="three columns left end">
								<input id="to_zip_1" type="text" placeholder="000000" value="<?=num2str($arOrder["PRICE"])?>" rel="" name="PrintAdress[to][<?=$arOrder["ID"]?>][sum]" class="form-control input_fix_w2">
							</div>
						</div>
						<input type="hidden" value="<?=($arOrder["PRICE"])?>" rel="" name="PrintAdress[to][<?=$arOrder["ID"]?>][sum_int]" class="form-control input_fix_w2">
					</div>
				</fieldset>
			</fieldset><!--начало в шаблоне отдельного бланка-->
			<?endforeach?>
			<input type="submit" name="Сохранить pdf">
		</form>
	</div>
</div>
<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>