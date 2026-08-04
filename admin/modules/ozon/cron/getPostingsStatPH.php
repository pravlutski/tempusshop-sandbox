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
		$this->logPath = '/var/www/bitrix/data/www/tempusshop.ru/admin/modules/OzonImport/logs/stat/postingsPH.txt';
    foreach (TYPE_SKLAD_CONST as $key => $value) {
      if ( in_array($key, ['ХГ-экспресс', 'HG-comfort']) ) continue;
      $this->warehouses[$key == 'Express 7D' ? 'E7D' : $key] = $value;
    }
  }

  public function run()
  {
    $this->getPostingsFBS();
    $this->getPostingsFBO();
    $this->countShares();
    // $this->fuckYouBitrixORM('ozon_posting_shares', [$this->shares]);
    // var_dump($this->items);
    // var_dump($this->shares);
  }

  private function getPostingsFBS()
  {
      $data = [
        'filter' =>[
          'since' => date('Y-m-d\TG:i:s\Z',strtotime("- 4 hour")),
          'to' => date('Y-m-d\TG:i:s\Z'),
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
        // $this->writeLog('Не удалось получить отправления фбс и рфбс');
        return false;
      }
      var_dump($result['result']['postings']);
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
          'since' => date('Y-m-d\TG:i:s\Z',strtotime("- 4 hour")),
          'to' => date('Y-m-d\TG:i:s\Z'),
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
        // $this->writeLog('Не удалось получить отправления фбс и рфбс');
        return false;
      }
      var_dump($result['result']);
      foreach ( $result['result'] as $posting ){
        foreach ($posting['products'] as $product) {
          $this->items['fbo'][] = end( explode('_', $product['offer_id']) );
        }
      }
  }

  private function countShares()
  {
    if( empty($this->items) ){
      $this->shares['Нет данных'] = 100;
    }
    foreach ($this->items as $type => $arValues){
      if ( isset($this->items['rfbs']) ){
        $this->shares[$type] = count($arValues ?? []) / ( count($this->items['fbs'] ?? [1]) + count($this->items['rfbs'] ?? [1]) + count($this->items['fbo'] ?? [1]) ) * 100;
      }else{
        $this->shares[$type] = count($arValues ?? []) / ( count($this->items['fbs'] ?? [1]) + count($this->items['fbo'] ?? [1]) ) * 100;
      }
    }
    if ( !empty($this->shares['fbo']) && empty($this->shares['fbs']) && empty($this->shares['rfbs']) ){
      $this->shares['fbo'] = 100;
      $this->shares['fbs'] = 0;
      $this->shares['rfbs'] = 0;
    }
    elseif( empty($this->shares['fbo']) && !empty($this->shares['fbs']) && empty($this->shares['rfbs']) ){
      $this->shares['fbo'] = 0;
      $this->shares['fbs'] = 100;
      $this->shares['rfbs'] = 0;
    }
    elseif( empty($this->shares['fbo']) && empty($this->shares['fbs']) && !empty($this->shares['rfbs']) ){
      $this->shares['fbo'] = 0;
      $this->shares['fbs'] = 0;
      $this->shares['rfbs'] = 100;
    }
    $this->shares['date'] = date('Y-m-d G:i:s');
    file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/admin/modules/ozon/stat/perHour.json', json_encode($this->shares));
  }
}
(new OzonPostingsPH())->run();


 ?>
