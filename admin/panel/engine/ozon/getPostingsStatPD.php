<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
$workers = new WorkersChecker("admin_panel_engine_ozon_getPostingsStatPD_php");
if (!$workers->checkStatus()) {
	exit;
}
$workers->updateStatus("Y");


class OzonPostingsPD
{
  private $api_url;
  private $client_id;
  private $token;
  private $warehouses;
  private $items;
  private $shares;
  private $dayFrom = 2;
  private $dayTo = 1;
  private $dbPanel;

  function __construct( $cabinet )
  {

    if ( !in_array( $cabinet, ['TI', 'IP'] ) ) die("WRONG CABINET");
    $this->cabinet = $cabinet;

    CModule::IncludeModule('panel.manager');
    $this->dbPanel = new DBPanel;

    if ( $this->cabinet == "IP" ){
      $this->rfbsWH = 1020005000505202;
    }else{
      $this->rfbsWH = 1020001970733000;
    }

    $this->table_name = "ozon_postings_shares_{$this->cabinet}";

    global $DB;
    $res = $this->dbPanel->select(['*'], "ozon_main_settings_{$this->cabinet}")->make();
		foreach ( $res as $row ){
		  $arSetting[$row['name']] = $row['value'];
		}

    $this->api_url = $arSetting['api_url'];
    $this->client_id = $arSetting['client_id'];
    $this->token = $arSetting['key'];
		$this->logPath = '/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/ozon/logs/IP/stat/postingsPD.txt';
    // foreach (TYPE_SKLAD_CONST_TI as $key => $value) {
    //   if ( in_array($key, ['ХГ-экспресс', 'HG-Comfort']) ) continue;
    //   $this->warehouses[$key] = $value;
    // }
    var_dump( 'finish construct');
  }

  public function run()
  {
    $this->getPostingsFBS();
    $this->getPostingsFBO();
    $this->writeDB();
  }

  private function getPostingsFBS()
  {
      $data = [
        'filter' =>[
          'since' => date( 'Y-m-d\T21:00:00\Z', strtotime('- ' . $this->dayFrom . ' day') ),
          'to' => date( 'Y-m-d\T20:59:59\Z', strtotime('- ' . $this->dayTo . ' day') ) ,
          // 'since' => '2024-10-24T21:00:00Z',
          // 'to' => '2024-10-25T20:59:59Z',
          // 'warehouse_id' => array_values($this->warehouses),
        ],
        'limit' => 1000,
      ];

      $ch = curl_init($this->api_url . '/v3/posting/fbs/list');
      curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Api-Key:' . $this->token,
        'Client-Id:' . $this->client_id,
        'Content-Type:application/json'
      ));
      curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
      curl_setopt($ch, CURLOPT_HEADER, false);
      $res = curl_exec($ch);
      curl_close($ch);
      $result = json_decode($res, true);

      if ( isset($result['code']) ){
        $this->writeLog('Не удалось получить отправления фбс и рфбс');
        return false;
      }
      foreach ( $result['result']['postings'] as $posting ){
        foreach ($posting['products'] as $product) {
          if( $posting['delivery_method']['warehouse_id'] == $this->rfbsWH ){
            $this->items['rfbs'][] = [
              'model' => end( explode('_', $product['offer_id']) ),
              'quantity' => $product['quantity'],
              'price' => $product['price'],
              'date' => $posting['in_process_at']
            ];
          }else{
            $this->items['fbs'][] = [
              'model' => end( explode('_', $product['offer_id']) ),
              'quantity' => $product['quantity'],
              'price' => $product['price'],
              'date' => $posting['in_process_at']
            ];
          }
        }
      }
      var_dump('got fbs and rfbs');
	  //var_dump( count($this->items['fbs']) );
	  //var_dump( count($this->items['rfbs']) );
  }

  private function getPostingsFBO()
  {
      $data = [
        'filter' =>[
          'since' => date( 'Y-m-d\T21:00:00\Z', strtotime('- ' . $this->dayFrom . ' day') ),
          'to' => date( 'Y-m-d\T20:59:59\Z', strtotime('- ' . $this->dayTo . ' day') ) ,
          // 'since' => '2024-10-24T21:00:00Z',
          // 'to' => '2024-10-25T20:59:59Z',
        ],
        'limit' => 1000,
      ];
      $ch = curl_init($this->api_url . '/v2/posting/fbo/list');
      curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Api-Key:' . $this->token,
        'Client-Id:' . $this->client_id,
        'Content-Type:application/json'
      ));
      curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
      curl_setopt($ch, CURLOPT_HEADER, false);
      $res = curl_exec($ch);
      curl_close($ch);
      $result = json_decode($res, true);

      if ( isset($result['code']) ){
        $this->writeLog('Не удалось получить отправления фбо');
        return false;
      }
      foreach ( $result['result'] as $posting ){
        foreach ($posting['products'] as $product) {
          $this->items['fbo'][] = [
            'model' => end( explode('_', $product['offer_id']) ),
            'quantity' => $product['quantity'],
            'price' => $product['price'],
            'date' => $posting['in_process_at']
          ];
        }
      }
      var_dump('got fbo');
      var_dump( count($this->items['fbo']) );
  }

  private function writeDB()
  {
    if ( empty($this->items) ){
      $this->writeLog('Нечего записать в БД');
      return false;
    }
    $data = $this->prepareData();
    // $this->fuckYouBitrixORM( $this->tableName, $data );
    $this->dbPanel->insert( $this->table_name, $data );
    $this->writeLog('Таблица обновлена');
    var_dump('table ' . $this->table_name . ' updated');
  }

  private function writeLog($message)
  {
    file_put_contents($this->logPath, date('d-m-Y G:i:s'). ' --- ' . $message . PHP_EOL, FILE_APPEND);
  }

  private function prepareData()
  {
    $dataImport = [];
    foreach ($this->items as $type => $arModels) {
      foreach ($arModels as $model) {
        $dataImport[] = [
          'model' => $model['model'],
          'quantity' => $model['quantity'],
          'price' => $model['price'],
          'type' => $type,
          'date' => date('Y-m-d', strtotime($model['date'])),
          // 'date' => '2024-10-25',
        ];
      }
    }
    return $dataImport;
  }

  function fuckYouBitrixORM($tableName , $arrayData)
  {
    $cardSample = $arrayData[0];
    $fields = [];
    foreach ($cardSample as $key => $value) {
      $fields[] = $key;
    }
    if (empty($fields) || count($fields) < 2) return false;
    $strSql = "INSERT INTO {$tableName} " . '(';

    $i = 0;
    foreach ($fields as $fname) {
      $strSql .= (count($fields) - 1 != $i) ? "{$fname}," : $fname;
      $i++;
    }
    $strSql .= ') VALUES ';
    $c = 0;
    foreach ($arrayData as $card){
      $strSql .= '(';
      $k = 0;
      foreach ($card as $field) {
        $strSql .= (count($card) - 1 != $k) ? "'{$field}'," : "'{$field}'";
        $k++;
      }
      $strSql .= ( count($arrayData) - 1 != $c ) ? '),' : ')';
      $c++;
    }
    // var_dump($strSql);
    $this->dbPanel->query( $strSql );
  }
}
( new OzonPostingsPD( $argv[1] ?? "IP" ) )->run();


$workers->updateStatus("N");
 ?>
