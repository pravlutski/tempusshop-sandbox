<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

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

class WBOrderStatus
{
    private $api; // Массив с данными для авторизации по апи
    private $options; // Массив с именами таблиц
    private $orders; // Массив заказов из базы данных
    private $db; // Экземпляр класса базы данных
    private $logStat; // Массив, c информацией о проделанной работе. Для лога

    public function __construct( $cabinet = 'WR')
    {
      if ( !in_array($cabinet, ['WT', 'WR', 'TL']) ){
        throw new InvalidArgumentException("Undefined cabinet");
      }

      global $DB;
      $this->db = $DB;
      $this->options = [
        'cabinet' => $cabinet,
        'status_table_name' => 'wdhs_wb_order_status', // Таблица с соответствиями статусов
        'order_table_name' => 'wdhs_wb_orders', // Таблица с данными о заказах
        'days' => '7', // За сколько дней от текущей даты брать заказы для обновления
        's_height' => '40', // Высота стикера (40/30)
        's_width' => '58', // Ширина стикера (58/40)
        's_type' => 'svg', // Тип стикера (svg/zplv/zplh/png)
        'log_path' => $_SERVER["DOCUMENT_ROOT"] . '/admin/panel/engine/wb/logs/orders/' . $cabinet. '/' . date('Y-m-d') . '_order_update.txt',
      ];

      switch ($cabinet) {
        case 'WR':
          $this->options['user_id'] = 135989;
          break;
        case 'TL':
          $this->options['user_id'] = 161898;
          break;
        case 'WT':
          $this->options['user_id'] = 191551;
          break;
        default:
          die("Invalid cabinet\n");
          break;
      }

      $this->logStat = [
        'fromDB' => 0,
        'afterCheck' => 0,
        'status' => 0,
        'sticker' => 0,
        'error' => 0,
      ];

      $strSql = "SELECT * FROM wdhs_wb_main_settings WHERE cabinet = '{$this->options['cabinet']}'";
      $results = $this->db->Query($strSql, false, $err_mess.__LINE__);
      $this->api = $results->Fetch();
    }

    public function run()
    {
      $this->writeLog('START');
      $this->getOrdersDB(); // Получаем уже созданные заказы из БД
      $this->getIncompleteOrders(); // Отсеиваем уже завершенные заказы
      $this->loadOrderStatuses(); // Получаем статусы заказов от ВБ
      $this->processOrders(); // Обновляем где надо и что надо
      $this->generateFinalMessage(); // Логгируем краткий отчет о проделанной работе
      $this->writeLog('END');
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

    private function getOrdersDBOld():void // Не забыть удалить
    {
      $fewDays = strtotime( date('Y-m-d 00:00:00') . "- {$this->options['days']} day" );
      $strSql = "SELECT *
                FROM {$this->options['order_table_name']}
                WHERE timestamp > {$fewDays} AND cabinet = '{$this->options['cabinet']}'";
      $results = $this->db->Query($strSql, false, $err_mess.__LINE__);
      $this->orders = [];
      if ( $results->SelectedRowsCount() <= 0 ){
        $this->writeLog("Table [{$this->options['order_table_name']}] is empty");
        die('Table empty');
      }
      while ( $row = $results->Fetch() ){
        $this->orders[ $row['order_id'] ] = [
          'order_id' => $row['order_id'],
          'order_bid' => $row['order_bid'],
          'currentStatus' => $row['status'],
          'has_sticker' => $row['has_sticker'],

        ];
      }
      $this->writeLog( "Got orders from DB: " . count($this->orders) );
      $this->logStat['fromDB'] = count($this->orders);
    }

    private function getOrdersDB():void
    {
      $parameters = [
        'filter' => [
          'USER_ID' => $this->options['user_id'],
          '!STATUS_ID' => ['F', 'no', 'CA', 'CS'],
          '>=DATE_INSERT' =>\Bitrix\Main\Type\Date::createFromTimestamp( strtotime("-{$this->options['days']} days") )
        ],
        'order' => ["DATE_INSERT" => "ASC"],
        'limit' => 1000
      ];

      $orders = \Bitrix\Sale\Order::GetList( $parameters );

      $ordersData = [];
      while ( $row = $orders->Fetch() ){
        // Получаем подробную информацию по каждому заказу для получения их свойств
        $orderObj = \Bitrix\Sale\Order::load( $row['ID'] );
        $propertyCollection = $orderObj->getPropertyCollection();

        // Формируем итоговый массив
        $wb_order_id = $propertyCollection->getItemByOrderPropertyCode('MAXYSS_WB_NUMBER')->getValue();
        $ordersData[ $wb_order_id ] = [
          'order_id' => $wb_order_id,
          'order_bid' => $row['ID'],
          'currentStatus' => $row['STATUS_ID'],
        ];
      }

      $this->orders = $ordersData;

      $this->writeLog( "Got orders from DB: " . count($this->orders) );
      $this->logStat['fromDB'] = count($this->orders);

      // var_dump( count($this->orders) );
      // die;
    }

    private function checkOrderStatus( array $order ):bool
    {
      if ( empty($order) ){
        $this->writeLog('Method checkOrderStatus returned an exception (empty array). Check if gathered data is valid');
        $this->logStat['error']++;
        throw new \Exception("No empty array allowed. method: checkOrderStatus");
        return true;
      }
      $orderBX = Order::load( $order['order_bid'] );
      if ( $orderBX->getField('STATUS_ID') == 'F' ){
        $this->writeLog("Order {$order['order_id']} is completed. No need to update");
        return true;
      }
      return false;
    }

    private function getIncompleteOrders():void
    {
      if ( empty($this->orders) ){
        $this->writeLog("The table '{$this->options['order_table_name']}' is empty or the data does not meet the conditions");
        return;
      }
      $ordersTmp = [];
      foreach ( $this->orders as $id => $order ){
        if ( $this->checkOrderStatus($order)  ){
          continue;
        }
        if ( empty($order['order_id']) ){
          continue;
        }
        $ordersTmp[$id] = $order;
      }
      $this->orders = $ordersTmp;
      //file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/wb/orders/statusDefbug2.log', print_r($this->orders, 1));
      $this->writeLog( "Orders got filtered. Current count: " . count($this->orders) );
      $this->logStat['afterCheck'] = count($this->orders);
      var_dump( count($this->orders) );
    }

    private function loadOrderStatuses():void
    {
      if ( empty($this->orders) ){
        $this->writeLog('Method loadOrderStatus returned an exception (empty array). Check if gathered data is valid');
        $this->logStat['error']++;
        throw new \Exception("No empty array allowed. method: loadOrderStatus");
        return;
      }
      $data = [
        'orders' => array_keys($this->orders)
      ];
      //file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/wb/orders/statusDefbug.log', print_r($data, 1));


      $res = $this->request(
        'https://marketplace-api.wildberries.ru/api/v3/orders/status',
        [
          "Content-Type: application/json",
          "Authorization: " . $this->api['api']
        ],
        json_encode( $data, JSON_UNESCAPED_UNICODE )
      );
      // var_dump( $res );
      // die;
      if( ! is_array( $res ) || empty( $res['orders'] ) ) {
        $this->writeLog('WB had returned no orders. Check if request body is valid. More: ' . print_r($res, true));
        return;
      }

      foreach ( $res['orders'] as $field ) {
        // var_dump($field);
        $this->orders[ $field['id'] ]['wbStatus'] = $field['wbStatus'];
        $this->orders[ $field['id'] ]['supplierStatus'] = $field['supplierStatus'];
      }
    }

    private function updateOrderStatus( object $orderBX, array $orderData ):void
    {
      if ( empty($orderData) ){
        $this->writeLog('Method updateOrderStatus returned an exception (empty array). Check if gathered data is valid');
        throw new \Exception("No empty array allowed. method: updateOrderStatus");
      }
      if ( empty($orderData['wbStatus']) ){
        $this->writeLog( "WB had returned no status for {$orderData['order_id']}. Probably order is new" );
        return;
      }
      // Проверяем установлено ли соответствие статуса
      if ( $statusBX = $this->mapStatus($orderData['wbStatus']) ){
        // Проверяем текущий статус заказа
        if ( $orderBX->getField('STATUS_ID') != $statusBX ){
          $orderBX->setField('STATUS_ID', $statusBX);
          $resultObj = $orderBX->save();

          if ( $resultObj->isSuccess() ){
            $strSql = "UPDATE {$this->options['order_table_name']} SET status = '{$orderData['wbStatus']}' WHERE order_id = '{$orderData['order_id']}'";
            $result = $this->db->Query($strSql, false, $err_mess.__LINE__);

            $this->writeLog('Status for ' . $orderData['order_id'] . ' is updated');
            $this->logStat['status']++;
          }else{
            $this->writeLog('Status update for ' . $orderData['order_id'] . ' failed: ' . $result->getError() );
            $this->logStat['error']++;
          }
        }

      }else{
        $this->writeLog('Match for the status ['.$orderData['wbStatus'].'] got from WB is not set. ' . $orderData['order_id'] . ' will not be updated');
      }
    }

    private function processOrders():void
    {
      // Проверям массив на пустоту и выбрасываем исключение
      if ( empty($this->orders) ){
        $this->writeLog('Method processOrders returned an exception (empty array). Check if gathered data is valid');
        throw new \Exception("No empty array allowed. method: processOrders");
      }
      foreach ( $this->orders as $order ){
          $orderBX = Order::load( $order['order_bid'] );
          $this->updateOrderStatus( $orderBX, $order);
      }
    }

    private function mapStatus( string $statusWB ):string|bool
    {
      if ( empty($statusWB) ) return false;
      $strSql = "SELECT status_bx FROM {$this->options['status_table_name']} WHERE status_WB = '{$statusWB}'";
      $result = $this->db->Query($strSql, false, $err_mess.__LINE__);
      if ( $result->SelectedRowsCount() < 0 ){
        return false;
      }
      $statusBX = $result->Fetch()['status_bx'] ?? false;

      return $statusBX;
    }

    private function generateFinalMessage():void
    {
      $message = "";
      $message .= "Orders got from DB: {$this->logStat['fromDB']}. " . PHP_EOL;
      $orderCountF = $this->logStat['fromDB'] - $this->logStat['afterCheck'];
      $message .= "Completed orders that were skipped: {$orderCountF}" . PHP_EOL;
      $message .= "Orders which status was updated: {$this->logStat['status']}" . PHP_EOL;
      $message .= "Orders which sticker was updated: {$this->logStat['sticker']}" . PHP_EOL;
      $message .= "Errors/Warnings occured: {$this->logStat['error']}";
      $this->writeLog( $message );
    }

    private function writeLog( string $message ):void
    {
      file_put_contents( $this->options['log_path'], date('Y-m-d G:i:s') . ' --- ' . $message . PHP_EOL, FILE_APPEND );
    }

}
$cab = $argv[1];
(new WBOrderStatus($cab) )->run();

 ?>
