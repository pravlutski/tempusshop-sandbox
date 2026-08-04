<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
CModule::IncludeModule('panel.manager');

class OzonPostingsPH
{
  private $api_url;
  private $client_id;
  private $token;
  private $warehouses;
  private $items;
  private $shares;

  function __construct()
  {
    global $DB;
    $strSql = "SELECT * FROM wdhs_ozon_main_settings";
    $results = $DB->Query($strSql, false, $err_mess.__LINE__);
		while ($row = $results->Fetch()){
		  $arSetting[$row['name']] = $row['value'];
		}

    $this->api_url = $arSetting['api_url'];
    $this->client_id = $arSetting['client_id'];
    $this->token = $arSetting['key'];
		$this->logPath = '/var/www/bitrix/data/www/tempusshop.ru/admin/modules/OzonImport/logs/stat/postingsPD.txt';
    foreach (TYPE_SKLAD_CONST as $key => $value) {
      if ( in_array($key, ['ХГ-экспресс', 'HG-comfort']) ) continue;
      $this->warehouses[$key == 'Express 7D' ? 'E7D' : $key] = $value;
    }
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
          'since' => date( 'Y-m-d\T21:00:00\Z', strtotime('- 2 day') ),
          'to' => date( 'Y-m-d\T20:59:59\Z', strtotime(' - 1 day') ),
          // 'since' => '2024-07-18T0:00:00Z',
          // 'to' => '2024-07-18T23:59:59Z',
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
      var_dump($res);
      curl_close($ch);
      $result = json_decode($res, true);
      if ( isset($result['code']) ){
        $this->writeLog('Не удалось получить отправления фбс и рфбс');
        return false;
      }
      foreach ( $result['result']['postings'] as $posting ){
        foreach ($posting['products'] as $product) {
          if( $posting['delivery_method']['warehouse'] == 'Express_7D'){
            $this->items['rfbs'][] = end( explode('_', $product['offer_id']) );
          }else{
            $this->items['fbs'][] = end( explode('_', $product['offer_id']) );
          }
        }
      }
  }

  private function getPostingsFBO()
  {
      $data = [
        'filter' =>[
          'since' => date( 'Y-m-d\T21:00:00\Z', strtotime('- 2 day') ),
          'to' => date( 'Y-m-d\T20:59:59\Z', strtotime(' - 1 day') ),
          // 'since' => '2024-07-18T0:00:00Z',
          // 'to' => '2024-07-18T23:59:59Z',
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
      var_dump($res);
      curl_close($ch);
      $result = json_decode($res, true);
      if ( isset($result['code']) ){
        $this->writeLog('Не удалось получить отправления фбо');
        return false;
      }
      foreach ( $result['result'] as $posting ){
        foreach ($posting['products'] as $product) {
          $this->items['fbo'][] = end( explode('_', $product['offer_id']) );
        }
      }
  }

  private function writeDB()
  {
    if ( empty($this->items) ){
      $this->writeLog('Нечего записать в БД');
      return false;
    }
    $data = $this->prepareData();
    $this->fuckYouBitrixORM('ozon_postings_shares', $data);
    $this->writeLog('Таблица обновлена');
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
          'date' => date('Y-m-d', strtotime('- 1 day')),
          // 'date' => '2024-07-23',
          'type' => $type,
          'model' => $model,
        ];
      }
    }
    return $dataImport;
  }

  function fuckYouBitrixORM($tableName , $arrayData)
  {
    global $DB;
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
    $DB->Query($strSql, false, $err_mess.__LINE__);
  }
}
(new OzonPostingsPH())->run();


 ?>
