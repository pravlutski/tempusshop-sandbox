<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
if(!CModule::IncludeModule("main") || !CModule::IncludeModule("iblock") || 
$_SERVER['HTTP_X_REQUESTED_WITH'] != 'XMLHttpRequest' || !CModule::IncludeModule('panel.manager')) return;

if(is_array($_POST["order"]) && count($_POST["order"]) > 0){
	$order = new OrderService;
	$ar = array();
	foreach($_POST["order"] as $order_id){
		$order_id = intval($order_id);
		if($order_id > 0)
			$ar[] = $order->updateOrder($order_id, array("ALLOW_DELIVERY" => "Y"));
	}
	$res = array(
		'status' => (in_array(false, $ar) ? "error" : "ok")
	);
}else{
	$res = array(
		'status' => 'error',
	);
}

/*
            $res = array(
                'status' => 'ok',
                'data'   => $_POST
            );
*/
echo json_encode($res, JSON_UNESCAPED_UNICODE);

header('Content-Type: application/json;charset=UTF-8');
die();
