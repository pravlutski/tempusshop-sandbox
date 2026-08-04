<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
if($_SERVER['HTTP_X_REQUESTED_WITH'] != 'XMLHttpRequest' || !CModule::IncludeModule('panel.manager')) return;

$objSupplier = new CPanelSupplier;
$triggers = new TsTriggers();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

	global $USER;
	$ar = array(
		"date" => date("Y-m-d H:i:s"),
		"USER_ID" => $USER->getID(),
		"POST" => $_POST
	);
	// $file_log = "/var/www/bitrix/data/www/tempusshop.ru/admin/log/supplier_apply_" . date("Y-m-d") . ".txt";
	// file_put_contents($file_log, serialize($ar) . "\r\n", FILE_APPEND | LOCK_EX);
	// file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/ajax/supplier/rowArray.txt", print_r($_POST, 1));
	// file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/ajax/supplier/jsonArray.txt", json_encode($_POST, 1) );

	$ar = $_POST;
	$uid = $USER->getID();
	$uname = $USER->getLogin();

	$activity = [
	  "active_ru" => "RU",
	  "active_by" => "BY",
	  "active_ya" => "YA",
	  "active_os" => "OZIP",
	  "active_wb" => "WB",
	  "active_wbtl" => "WBTL",
	  "active_wbby" => "WBBY",
	  "active_opt" => "ОПТ",
	  "active_av" => "AV",
	  "active_sb" => "SB",
	  "active_ozti" => "OZTI"
	  ];

	$supplier = new CPanelSupplier;
	$suppSet = $supplier->getDetail( $ar['supplier-id'] );

	$activated = [];
	$deactivated = [];
	$alreadyActive = [];

	// foreach ( $activity as $type => $name ){
	//   $suppSet[$type] = 'N';
	// }

	foreach ( $activity as $type => $name ){
	  if ( isset( $ar[$type] ) ){
	    $activated[] = $name;
	  }else{
	    $deactivated[] = $name;
	  }
	  if ( $suppSet[$type] == 'Y' ){
	    $alreadyActive[] = $type;
	  }
	}

	if ( empty($alreadyActive) && !empty($activated) ){
	  $message = "Поставщик [{$ar['name']}] активирован пользователем {$uid} [{$uname}].\n Активированные каналы продаж: " . implode(', ', $activated);
		$triggers->SetError([$message]);
		$triggers->SendTriggerErrors();
		// echo $message;
	}

	if ( (count( $deactivated ) == count( $activity )) && ( !empty($alreadyActive) )  ){
	  $message = "Поставщик [{$ar['name']}] деактивирован пользователем {$uid} [{$uname}].";
		$triggers->SetError([$message]);
		$triggers->SendTriggerErrors();
		// echo $message;
	}

	$corrections = json_decode( $suppSet['settings'], true )['correct_price'] ?? [];
	$log = [];
	foreach ( $_POST['correct_price'] as $channel => $value ){
		if ( $corrections[$channel] == $value ) continue;
		$old = empty($corrections[$channel]) ? 'Нет' : $corrections[$channel];
		$new = empty($value) ? 'Нет' : $value;

		$log[] = "{$channel}: {$old} --> {$new}";
	}

	if ( !empty($log) ){
		$message = "Изменены наценки поставщика {$ar['name']}\n";
		$message .= implode(PHP_EOL, $log) . "\n";
		$message .= "Пользователь: {$uname}\n";
		$message .= "Дата: " . date('Y.m.d G:i:s');

		$triggers->SetError([$message]);
		$triggers->SendTriggerErrors();
	}


	$result = $objSupplier->apply($_POST);
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
}else{
	$res["status"] = "error";
	$res["data"] = "Не корректный запрос";
}

echo json_encode($res, JSON_UNESCAPED_UNICODE);
header('Content-Type: application/json;charset=UTF-8');
die();
