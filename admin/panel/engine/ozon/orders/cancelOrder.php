<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
$workers = new WorkersChecker("panel_engine_ozon_orders_cancelOrder_php");
if (!$workers->checkStatus()) {
	exit;
}
$workers->updateStatus("Y");
CModule::IncludeModule("main");
CModule::IncludeModule("iblock");
CModule::IncludeModule('panel.manager');
CModule::IncludeModule('panel.manager');


$CurDB = new DBPanel();

try {
  GLOBAL $DB;

  //$result = $CurDB->query("SELECT * FROM ms_orders WHERE bitrix_id = '696368'");
  $result = $CurDB->query("SELECT * FROM ozon_cancel_orders WHERE status = '1'");
  $rows = $CurDB->fetchAll($result);
  foreach ($rows as $row) {
    $ordersBD[$row['bitrix_id']] = $row;
  }
  if (empty($ordersBD)) {
    print_r('Заказов нету');
    die();
  }
  unset($result);
  unset($rows);

  $res = $CurDB->select(['*'], "ozon_main_settings_IP")->make();
  foreach ( $res as $row ){
    $api[ $row['name'] ] = $row['value'];
  }

  $filter = [
      'filter' => [
          'ID' => array_keys($ordersBD)
      ],
      'select' => ['ID', 'DATE_INSERT', 'STATUS_ID', 'PRICE', 'CURRENCY', 'USER_DESCRIPTION', 'USER_ID','ACCOUNT_NUMBER'],
      'order' => ['ID' => 'ASC'],
  ];

  $dbOrders = \Bitrix\Sale\Order::getList($filter);
  $orders = [];

  while ($order = $dbOrders->fetch()) {
      $orderObj = \Bitrix\Sale\Order::load($order['ID']);
      $propertyCollection = $orderObj->getPropertyCollection();
      $ozonProperty = $propertyCollection->getItemByOrderPropertyCode('OZON_NUMBER');

      if ($ozonProperty) {
          $order['OZON_NUMBER'] = $ozonProperty->getValue();
      } else {
          $order['OZON_NUMBER'] = '';
      }
      $orders[] = $order;
  }


  foreach ($orders as $key => $value) {
    if (!empty($value['OZON_NUMBER'])) {
      $url = 'https://api-seller.ozon.ru/v2/posting/fbs/cancel';
      $headers = [
        'Api-Key:' . $api['key'],
        'Client-Id:' . $api['client_id'],
        'Content-Type:application/json'
      ];
      $data = [
        "cancel_reason_id" => 352,
        "cancel_reason_message" => "Product is out of stock",
        "posting_number" => $value['OZON_NUMBER']
      ];
      $body = json_encode( $data, JSON_UNESCAPED_UNICODE );
      $ch = curl_init( $url );
      curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers );
      curl_setopt( $ch, CURLOPT_POSTFIELDS, $body );
      curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
      curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
      curl_setopt( $ch, CURLOPT_HEADER, false );
      $res = curl_exec( $ch );
      if ( curl_errno( $ch ) ) {
        $error_msg = curl_error( $ch );
      }
      curl_close( $ch );

      if ( $error_msg ) {
        $this->writeLog('CUrl returned an error: ' . $error_msg);
        return false;
      }

      print_r(json_decode( $res, true ));
      //file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/ozon/orders/cancel.txt', print_r(json_decode( $res, true ),true). PHP_EOL,FILE_APPEND);
    }
  }



} catch (Exception $e) {
    echo "Ошибка: " . $e->getMessage();
    \Bitrix\Main\Diag\Debug::writeToFile($e->getMessage(), "Ошибка синхронизации", "moysklad_integration.log");
    exit(1);
}

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_after.php");
$workers->updateStatus("N");
