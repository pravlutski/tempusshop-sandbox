<?php
require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');

global $USER;
if(!in_array(12, $USER->GetUserGroupArray()) && !in_array(6, $USER->GetUserGroupArray()) && !$USER->isAdmin()){
// if( !$USER->isAdmin() ){
	die();
}
if( !CModule::IncludeModule("panel.manager") || !CModule::IncludeModule("sale") ){
	echo '<p>Произошла непредвиденная ошибка. Пожалуйста, обновите страницу и попробуйте позже</p>';
}

$type = trim($_POST["type"]);
$website = trim($_POST["website"]);

if(!in_array($website, array("s1", "s2"))) die("Сайт неопределен");
if(!in_array($type, array("coupon", "certificate"))) die("Неопределен тип");
if(strlen($_POST["comment"]) <= 3) $arResult["ERROR"][] = "Введите комментарий";

$discountID = false;

$arDiscount = array(
	"coupon" => array(
		"s1" => array(
			5 => 90,
			10 => 91,
			15 => 100
		),
		"s2" => array(
			5 => 92,
			10 => 93,
			15 => 101
		),
	),
	"certificate" => array(
		"s1" => array(
			1500 => 79,
			3000 => 80,
			6000 => 81,
			9000 => 82,
			15000 => 83,
			30000 => 84,
			50000 => 1119,
		),
		"s2" => array(
			50 => 85,
			100 => 86,
			200 => 87,
			300 => 88,
			500 => 89,
		),
	)
);

if($type == "coupon"){

	$nominal = $_POST["sale"];

}elseif($type == "certificate"){

	if($website == "s1"){
		$nominal = $_POST["nominal_s1"];
	}elseif($website == "s2"){
		$nominal = $_POST["nominal_s2"];
	}

}

$discountID = $arDiscount[$type][$website][$nominal];

if(!$discountID){
	$arResult["ERROR"][] = "ID скидки неопределена";
}

if(!$arResult["ERROR"]){
	$descrition = $_POST["comment"] . " " . $USER->GetLogin();

	//$coupon = \Bitrix\Sale\Internals\DiscountCouponTable::generateCoupon(true);
	if ( !empty($_POST['coupon']) ){
		$coupon = $_POST['coupon'];
	}else{
		$coupon = generateCoupon();
	}
	$fields = array(
		'DISCOUNT_ID' => $discountID,
		'ACTIVE_FROM' => 0,
		'ACTIVE_TO' => 12,
		'TYPE' => \Bitrix\Sale\Internals\DiscountCouponTable::TYPE_ONE_ORDER,
		'MAX_USE' => 1,
		'DESCRIPTION' => $descrition,
		'COUPON' => $coupon,
	);
	//prent($fields);
	// $data = [
	// 	"fields" => $fields,
	// 	"source" => 'coupon_generator',
	// ];
	// $ch = curl_init('https://tempus.ru/local/rest/coupon/addCoupon.php');
	// curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
	// curl_setopt( $ch, CURLOPT_POSTFIELDS, $fields );
	// $result = curl_exec($ch);
	// curl_close($ch);
	//
	// var_dump( curl_getinfo( $ch ) );
	//
	// $res = json_decode( $result, 1 );
	// var_dump($res);
	// die;
	// $result = \Bitrix\Sale\Internals\DiscountCouponTable::add($fields);

	$log = $fields;

	$log['CREATED_AT'] = date('Y-m-d G:i:s');
	$log['USER'] = $USER->GetLogin();

	file_put_contents( $_SERVER['DOCUMENT_ROOT'] . "/admin/ajax/coupon/logs/log.txt", json_encode($log) . PHP_EOL, FILE_APPEND );
	file_put_contents( $_SERVER["DOCUMENT_ROOT"] . "/admin/ajax/coupon/coupon.json", json_encode( $fields ) );
	try{
		$res =	exec( "php /var/www/bitrix/data/www/tempus.ru/local/rest/coupon/addCoupon.php", $output );
		$json = $output[0];
	}catch ( Throwable $e ){
		echo "<p style='color:red;margin:5px 0 0 0;'>" . $e->getMessage() . "</p>";
		die;
	}

	$result = json_decode( $json, true );
	
	if ( $result['status'] == 'error' ){
		echo "<p style='color:red;margin:5px 0 0 0;'>{$result['message'][0]}</p>";
		die;
	}
	echo "<p style='color:black;margin:5px 0 0 0;font-size: 20px;font-weight: bold;'>" . $result['message'] . "</p>";

	die;

}else{
	foreach($arResult["ERROR"] as $error){
		echo "<p style='color:red;margin:5px 0 0 0;'>{$error}</p>";
	}
}

/*
BY: 64 - 5%, 94 - 10%
RU: 45 - 5%, 90 -10%

Подарочные сертификаты
BY: 95 - 50р, 88 - 100р, 96 - 200р, 97 - 300р, 98 - 500р
RU: 101 - 1500р, 99 - 3000р, 100 - 6000р, 102 - 9000р, 103 - 15 000р
*/

require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_after.php');
