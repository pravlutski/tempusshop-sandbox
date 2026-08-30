<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
$workers = new WorkersChecker("admin_panel_engine_ozon_getPostingsStatPH_php");
if (!$workers->checkStatus()) {
	exit;
}
$workers->updateStatus("Y");
CModule::IncludeModule('panel.manager');

class OzonPostingsPH
{
  private $api_url;
  private $client_id;
  private $token;
  private $warehouses;
  private $items;
  private $shares;
  private $totalSum = 0;
  private $cabinet;

  function __construct( $cabinet )
  {

    if ( !in_array( $cabinet, ['TI', 'IP'] ) ) die("WRONG CABINET");
    $this->cabinet = $cabinet;

    if ( $this->cabinet == "IP" ){
      $this->rfbsWH = 1020005000505202;
    }else{
      $this->rfbsWH = 1020001970733000;
    }

    $dbPanel = new DBPanel;
    $res = $dbPanel->select(['*'], "ozon_main_settings_{$this->cabinet}")->make();
		foreach ( $res as $row ){
		  $arSetting[$row['name']] = $row['value'];
		}

    $this->api_url = $arSetting['api_url'];
    $this->client_id = $arSetting['client_id'];
    $this->token = $arSetting['key'];
		$this->logPath = $_SERVER['DOCUMENT_ROOT'] . "/admin/panel/engine/ozon/logs/{$this->cabinet}/stat/postingsPH.txt";
    foreach (TYPE_SKLAD_CONST_TI as $key => $value) {
      if ( in_array($key, ['ХГ-экспресс', 'HG-comfort']) ) continue;
      $this->warehouses[$key] = $value;
    }
    $this->items['fbo'] = 0;
    $this->items['fbs'] = 0;
    $this->items['rfbs'] = 0;
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
          'since' => date('Y-m-d\T21:00:00\Z',strtotime("- 1 day")),
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
      // var_dump($result['result']['postings']);
      foreach ( $result['result']['postings'] as $posting ){
        foreach ($posting['products'] as $product) {
          if( $posting['delivery_method']['warehouse_id'] == 1020002803288000){
            $this->items['rfbs'] += $product['quantity'] * $product['price'];
            $this->totalSum += $product['quantity'] * $product['price'];
          }else{
            $this->items['fbs'] += $product['quantity'] * $product['price'];
            $this->totalSum += $product['quantity'] * $product['price'];
          }
        }
      }
  }

  private function getPostingsFBO()
  {
      $data = [
        'filter' =>[
          'since' => date('Y-m-d\TG:i:s\Z',strtotime("- 1 day")),
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
      // var_dump($result['result']);
      foreach ( $result['result'] as $posting ){
        foreach ($posting['products'] as $product) {
          $this->items['fbo'] += $product['quantity'] * $product['price'];
          $this->totalSum += $product['quantity'] * $product['price'];
        }
      }
  }

  private function countShares()
  {
    if ( empty($this->items) ){
      $this->shares['Нет_данных'] = 100;
    }
    $sharesTmp = [];
    $typeTmp;
    foreach ( $this->items as $type => $typeSum ){
      $this->shares[ $type ] = $typeSum / $this->totalSum * 100;
      if ( $this->shares[ $type ] > 100 ) $this->shares[ $type ] = 100;
    }

    $this->shares['date'] = date('Y.m.d G:i:s');
    file_put_contents($_SERVER['DOCUMENT_ROOT'] . "/admin/panel/engine/ozon/logs/{$this->cabinet}/stat/perHour.json", json_encode($this->shares));
  }

  private function countSharesOld()
  {
    if( empty($this->items) ){
      $this->shares['Нет_данных'] = 100;
    }
    $sharesTmp = [];
    foreach ($this->items as $type => $arValues){
      if ( isset($this->items['rfbs']) ){
        $sharesTmp[$type] = count($arValues ?? []) / ( count($this->items['fbs'] ?? [1]) + count($this->items['rfbs'] ?? [1]) + count($this->items['fbo'] ?? [1]) ) * 100;
      }else{
        $sharesTmp[$type] = count($arValues ?? []) / ( count($this->items['fbs'] ?? [1]) + count($this->items['fbo'] ?? [1]) ) * 100;
      }
    }
    $this->shares = [
      'fbs' => $sharesTmp['fbs'] ?? 0,
      'fbo' => $sharesTmp['fbo'] ?? 0,
      'rfbs' => $sharesTmp['rfbs'] ?? 0,
    ];
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
    $this->shares['date'] = date('Y.m.d G:i:s');
    file_put_contents($_SERVER['DOCUMENT_ROOT'] . "/admin/panel/engine/ozon/logs/{$this->cabinet}/stat/perHour.json", json_encode($this->shares));
  }
}
( new OzonPostingsPH( $argv[1] ?? "IP" ) )->run();


$workers->updateStatus("N");
 ?>
