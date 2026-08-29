<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
require_once $_SERVER['DOCUMENT_ROOT'] . '/local/classes/CronWorkerGuard.php';
if (!CronWorkerGuard::startFromArgv()) {
	exit;
}

use Bitrix\Main\Loader,
    Bitrix\Main\ModuleManager,
    Bitrix\Iblock,
    Bitrix\Catalog,
    Bitrix\Main\Localization\Loc,
    \Bitrix\Main\Config\Option,
    Bitrix\Currency,
    Bitrix\Currency\CurrencyManager,
    Bitrix\Sale\Order,
    Bitrix\Sale\Basket,
    Bitrix\Sale\Delivery,
    Bitrix\Sale\PaySystem,
    Bitrix\Highloadblock as HL,
    Bitrix\Main\Entity,
    Bitrix\Main\Application,
    Bitrix\Main\Type,
    Bitrix\Main\Web\Json;


Bitrix\Main\Loader::includeModule("main");
Bitrix\Main\Loader::includeModule("sale");
Bitrix\Main\Loader::includeModule("catalog");
Bitrix\Main\Loader::includeModule("iblock");

class OzonOrderStatus
{
    private $api; // Массив с данными для авторизации по апи
    private $orders; // Массив с заказами
    private $options; // Массив с именами таблиц
    private $db; // Экземпляр класса базы данных

    public function __construct( $cabinet )
    {
		if ( !in_array($cabinet, ["IP", "TI", "WT"]) ) die("WRONG CABINET\n");

		$this->lockFile = $_SERVER["DOCUMENT_ROOT"] . "/admin/panel/engine/ozon/orders/order_update_{$cabinet}.lock";

		global $DB;
		$this->db = $DB;
		CModule::IncludeModule('panel.manager');
		$this->dbPanel = new DBPanel;
		$res = $this->dbPanel->select(['*'], "ozon_main_settings_{$cabinet}")->make();
		foreach ( $res as $row ){
			$this->api[ $row['name'] ] = $row['value'];
		}

		if ( $cabinet == "IP" ){
			$this->options = [
				'status_table_name' => 'wdhs_ozon_order_status', // Таблица с соответствиями статусов
				'order_table_name' => 'wdhs_ozon_orders', // Таблица с данными о заказах
				'days' => '3', // За сколько дней от текущей даты брать заказы для обновления
				'log_path' => '/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/ozon/orders/logs/IP/' . date('Y-m-d') . '_order_update.txt'
			];
		}else if ($cabinet == 'TI'){
			$this->options = [
				'status_table_name' => 'wdhs_ozon_order_status_ti', // Таблица с соответствиями статусов
				'order_table_name' => 'wdhs_ozon_orders_ti', // Таблица с данными о заказах
				'days' => '5', // За сколько дней от текущей даты брать заказы для обновления
				'log_path' => '/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/ozon/orders/logs/TI/' . date('Y-m-d') . '_order_update.txt'
			];
		}else if ($cabinet == 'WT'){
			$this->options = [
				'status_table_name' => 'wdhs_ozon_order_status', // Таблица с соответствиями статусов
				'order_table_name' => 'wdhs_ozon_orders_wt', // Таблица с данными о заказах
				'days' => '5', // За сколько дней от текущей даты брать заказы для обновления
				'log_path' => '/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/ozon/orders/logs/WT/' . date('Y-m-d') . '_order_update.txt'
			];
		}
		$this->uniq = uniqid();
    }

	private function acquireLock(): bool
	{
		if (file_exists($this->lockFile)) {
			$lockTime = filemtime($this->lockFile);
			$currentTime = time();
			$timeDiff = $currentTime - $lockTime;

			if ($timeDiff > 3600) {
				@unlink($this->lockFile);
				$this->writeLog('LOCK: Removed stale lock file (older than 20 minutes)');
			} else {
				return false;
			}
		}

		$pid = getmypid();
		$result = file_put_contents($this->lockFile, $pid . '|' . date('Y-m-d H:i:s'));

		if ($result === false) {
			$this->writeLog('LOCK: Failed to create lock file');
			return false;
		}

		$this->writeLog('LOCK: Acquired (PID: ' . $pid . ')');
		return true;
	}

	private function releaseLock(): void
	{
		if (file_exists($this->lockFile)) {
			@unlink($this->lockFile);
			$this->writeLog('LOCK: Released');
		}
	}

    public function run()
    {

		if (!$this->acquireLock()) {
			$this->writeLog('SKIP: Another instance is already running');
			die('Another instance is already running');
		}

		try {
			$this->writeLog('START');
			$this->getOrdersDB(); // Получаем уже созданные заказы из БД
			foreach( $this->orders as $id => $order ){
				if ( $this->checkOrderStatus($order) ){ // Проверяем статус в битриксе
					$orderOzon = $this->loadOrderOzon( $order ); // Получаем текущий статус каждого заказа от озона
					if ( $orderOzon ) {
						$this->updateOrderStatus( $orderOzon ); // Обновляем статус в битриксе и в таблице
					}
					sleep(1);
				}
			}
			$this->writeLog('END');
		} finally {
			$this->releaseLock();
		}
    }

    public function request( $url, $headers = [], $body = '' )
    {
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

      return json_decode( $res, true );
    }

    private function getOrdersDB():void
    {
      $currentTimestamp = strtotime( date('Y-m-d 00:00:00') . "- {$this->options['days']} day" );
      $strSql = "SELECT *
                FROM {$this->options['order_table_name']}
                WHERE timestamp > {$currentTimestamp} AND delivery_type = 'fbs'";
      $results = $this->db->Query($strSql, false, $err_mess.__LINE__);
      $this->orders = [];
      if ( $results->SelectedRowsCount() <= 0 ){
        $this->writeLog("Table [{$this->options['order_table_name']}] is empty");
        die('Table empty');
      }
      while ( $row = $results->Fetch() ){
        $this->orders[ $row['order_id'] ] = [
          'posting_number' => $row['posting_number'],
          'order_id' => $row['order_id'],
          'order_bid' => $row['order_bid'],
          'in_process_at' => $row['in_process_at']
        ];
      }
    }

    private function checkOrderStatus( array $order ):bool
    {
      if ( empty($order) ){
        $this->writeLog('Method [checkOrderStatus] returned an exception (empty array). Check if gathered data is valid');
        throw new \Exception("No empty array allowed. method: checkOrderStatus");
        return false;
      }
      $orderBX = Order::load( $order['order_bid'] );
      if ( $orderBX->getField('STATUS_ID') == 'F' ){
        $this->writeLog("Order {$order['posting_number']} is completed (".$orderBX->getField('STATUS_ID')."). No need to update");
        return false;
      }
      return true;
    }

    private function loadOrderOzon( array $order ):array|bool
    {
      if ( empty($order) ){
        $this->writeLog('Method [checkOrderStatus] returned an exception (empty array). Check if gathered data is valid');
        throw new \Exception("No empty array allowed. method: checkOrderStatus");
        return false;
      }
      // Делаем поправку на то, что озон принимает время по гринвичу
      $data = [
        'filter' => [
          'order_id' => intval( $order['order_id'] ),
          'since' => date( 'Y-m-d\TG:i:s\Z', strtotime($order['in_process_at'] . '-3 hour') ),
          'to' => date( 'Y-m-d\TG:i:s\Z', strtotime($order['in_process_at']) )
        ],
        'limit' => 1000,
      ];
      $res = $this->request(
        $this->api['api_url'] . '/v3/posting/fbs/list',
        [
          'Api-Key:' . $this->api['key'],
          'Client-Id:' . $this->api['client_id'],
          'Content-Type:application/json'
        ],
        json_encode( $data, JSON_UNESCAPED_UNICODE )
      );
      if( ! is_array( $res ) || empty( $res['result']['postings'] ) ) {
        $this->writeLog('OZON had returned no orders. Check if request body is valid or there were no orders. RESPONSE: ' . print_r($res, true));
        sleep( 3 );
        return false;
      }

      $postings = $res['result']['postings'];
      $result = [];
      foreach ( $postings as $field){
        $result = [
          // 'status' => 'driver_pickup',
          'status' => $field['status'],
          'order_id' => $field['order_id'],
          'posting_number' => $field['posting_number']
        ];
      }
      return $result;
    }

    private function updateOrderStatus( array $order ):void // Метод отличается запросом к бд от того, что в OzonOrderMain
    {
      if ( empty($order) ){
        $this->writeLog('Method updateOrderStatus returned an exception (empty array). Check if gathered data is valid');
        throw new \Exception("No empty array allowed. method: updateOrderStatus");
      }
      if ( $statusBX = $this->mapStatus($order['status']) ){

        $strSql = "SELECT status, order_bid FROM {$this->options['order_table_name']} WHERE order_id = '{$order['order_id']}'";
        $result = $this->db->Query($strSql, false, $err_mess.__LINE__)->Fetch();

		if ($result['order_bid']) {
			$orderBX = Order::load( $result['order_bid'] );
			// if ( !empty($result['status']) && $result['status'] != $order['status'] ){
			if ( $result['status'] != $order['status'] || $orderBX->getField("STATUS_ID") != $statusBX ){
			  if ( $orderBX->getField('STATUS_ID') == 'F' ){
				$this->writeLog('Order ' . $order['posting_number'] . ' has status F - completed and cannot be updated in auto mode');
				return;
			  }
			  $orderBX->setField('STATUS_ID', $statusBX);
			  $strSql = "UPDATE {$this->options['order_table_name']} SET status = '{$order['status']}' WHERE order_id = '{$order['order_id']}'";
			  $result = $this->db->Query($strSql, false, $err_mess.__LINE__);

			  $resultObj = $orderBX->save();
			  if ( $resultObj->isSuccess() ){
				$this->writeLog('Status for ' . $order['posting_number'] . ' is updated ('.$orderBX->getField('STATUS_ID') .' --> '.$statusBX.')');
			  }else{
				$this->writeLog('Status update for ' . $order['posting_number'] . ' failed: ' . $result->getError() );
			  }
			}else{
			  $this->writeLog("Status for '{$order['posting_number']}' is already up to date");
			}
		} else {

		}


      }else{
        $this->writeLog('Match for the status ['.$order['status'].'] got from OZON is not set. ' . $order['posting_number'] . ' will not be updated');
      }
    }

    private function mapStatus( string $statusOZ ):string|bool
    {
      if ( empty($statusOZ) ) return false;
      $strSql = "SELECT status_bx FROM {$this->options['status_table_name']} WHERE status_oz = '{$statusOZ}'";
      $result = $this->db->Query($strSql, false, $err_mess.__LINE__);
      if ( $result->SelectedRowsCount() < 0 ){
        return false;
      }
      $statusBX = $result->Fetch()['status_bx'] ?? false;

      return $statusBX;
    }

    private function writeLog( string $message ):void
    {
      file_put_contents( $this->options['log_path'], date('Y-m-d G:i:s') . ' --- ' . $this->uniq . ' - ' . $message . PHP_EOL, FILE_APPEND );
    }

}

(new OzonOrderStatus( $argv[1] ?? "IP" ) )->run();

 ?>
