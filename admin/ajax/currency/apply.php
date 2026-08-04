<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
if($_SERVER['HTTP_X_REQUESTED_WITH'] != 'XMLHttpRequest' || !CModule::IncludeModule('panel.manager')) return;

$objCurrency = new CPanelCurrency;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$data['currency'] = array_map(function($item){
		return str_replace( ',', '.', $item );
	}, $_POST['currency']);

	$result = $objCurrency->apply( $data );

	$objPricelist = new CPanelPricelist;
	$objPricelist->convertCurrencyPrice();

	if($result === true){
		$res["status"] = "ok";
		$res["data"] = "ok";
	}elseif(is_int($result)){
		$res["status"] = "ok";
		$res["data"] = "new";
	}else{
		$res["status"] = "error";
		$res["data"] = "Сохранить не удалось";
	}

	//AddMessage2Log($_POST);

	$log = [];
	$priceDO_old = CProSet::getOption("PRICE_DEVIATION_ORDER");
	$priceDT_old = CProSet::getOption("PRICE_DEVIATION_TOP");
	$priceDF_old = CProSet::getOption("PRICE_DEVIATION_FOREIGN");

	if(isset($_POST["price-deviation-order"])){
		$price = (float) $_POST["price-deviation-order"];
		CProSet::setOption("PRICE_DEVIATION_ORDER", $price);
	}else{
		CProSet::setOption("PRICE_DEVIATION_ORDER", "");
	}

	$priceDO_new = CProSet::getOption("PRICE_DEVIATION_ORDER");
	if ( (float)$priceDO_old != (float)$priceDO_new ){
		$log[] = "Заказы: {$priceDO_old} --> {$priceDO_new}";
	}

	if(isset($_POST["price-deviation-top"])){
		$price = (float) $_POST["price-deviation-top"];
		CProSet::setOption("PRICE_DEVIATION_TOP", $price);
	}else{
		CProSet::setOption("PRICE_DEVIATION_TOP", "");
	}

	$priceDT_new = CProSet::getOption("PRICE_DEVIATION_TOP");
	if ( (float)$priceDT_old != (float)$priceDT_new ){
		$log[] = "Запасы: {$priceDT_old} --> {$priceDT_new}";
	}

	if(isset($_POST["price-deviation-foreign"])){
		$price = (float) $_POST["price-deviation-foreign"];
		CProSet::setOption("PRICE_DEVIATION_FOREIGN", $price);
	}else{
		CProSet::setOption("PRICE_DEVIATION_FOREIGN", "");
	}

	$priceDF_new = CProSet::getOption("PRICE_DEVIATION_FOREIGN");
	if ( (float)$priceDF_old != (float)$priceDF_new ){
		$log[] = "Ин. поставщики: {$priceDF_old} --> {$priceDF_new}";
	}

	/* сохраняем Дни перемещений. Москва-Минск. почему то тут */
	if(isset($_POST["transit-days-ru"])){
		$transit_days = json_encode($_POST["transit-days-ru"]);
		CProSet::setOption("TRANSIT_DAYS_RU", $transit_days);
	}else{
		CProSet::setOption("TRANSIT_DAYS_RU", "");
	}
	/* сохраняем Дни перемещений. Минск-Москва. почему то тут */
	if(isset($_POST["transit-days-by"])){
		$transit_days = json_encode($_POST["transit-days-by"]);
		CProSet::setOption("TRANSIT_DAYS_BY", $transit_days);
	}else{
		CProSet::setOption("TRANSIT_DAYS_BY", "");
	}

}else{
	$res["status"] = "error";
	$res["data"] = "Не корректный запрос";
}

if ( !empty($log) ){
	global $USER;
	$triggers = new TsTriggers;
	$message = "Изменены настройки автоматического распределения по приоритетеу\n";
	$message .= implode(PHP_EOL, $log) . "\n";
	$message .= "Пользователь: " . $USER->GetLogin(). "\n";
	$message .= "Дата: " . date('Y.m.d G:i:s') . "\n";

	$triggers->SetError([$message]);
	$triggers->SendTriggerErrors();
}

echo json_encode($res, JSON_UNESCAPED_UNICODE);
header('Content-Type: application/json;charset=UTF-8');
die();
