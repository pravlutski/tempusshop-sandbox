<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

use Bitrix\Iblock\PropertyEnumerationTable;
use Bitrix\Main\Application,
	Bitrix\Main\Loader;

class OzonAddBarcodes
{
  private $api_url;
  private $client_id;
  private $token;
  private $db;
  private $logPath;
  private $items;

  function __construct()
  {
    global $DB;
    $this->db = $DB;

    $strSql = "SELECT * FROM wdhs_ozon_main_settings";
    $results = $this->db->Query($strSql, false, $err_mess.__LINE__);
    while ($row = $results->Fetch()){
      $arSetting[$row['name']] = $row['value'];
    }

    $this->api_url = $arSetting['api_url'];
    $this->client_id = $arSetting['client_id'];
    $this->token = $arSetting['key'];
    $this->logPath = '/var/www/bitrix/data/www/tempusshop.ru/admin/modules/forTest/logs/barcodesLog.txt';
  }

  public function run()
  {
    $this->getItems();
    $this->getSKUs();
    $this->addBarcodes();
  }

  private function loadModules(){
    Loader::includeModule("main");
    Loader::includeModule("iblock");
    }

  private function getItems()
  {
    $this->loadModules();
    $strSql = "SELECT model FROM ci_price WHERE active_os = 'Y'";
    $results = $this->db->Query($strSql, false, $err_mess.__LINE__);
    while ($row = $results->Fetch()){
      $models[]  = $row['model'];
    }

    $arSelect = Array("ID","IBLOCK_ID","PROPERTY_WBARTICLE", "PROPERTY_AEN2");

    // $chunk = array_chunk($models, 100)[0];
    $arFilter = Array(
      "IBLOCK_ID" => CProSet::IB_CATALOG,
      "PROPERTY_OZON_ACTIVE_VALUE" => 'Да',
      "PROPERTY_CML2_ARTICLE" => $models,
      "!PROPERTY_AEN_VALUE" => false
      // "ID" => [1002, 207333, 13263, 4760],
    );

    $result = CIBlockElement::GetList(array(), $arFilter, false, false, $arSelect);
    $this->items = [];
    while ($el = $result->GetNext()){
      $this->items[ $el['PROPERTY_WBARTICLE_VALUE'] ] = ['barcode' => $el['PROPERTY_AEN2_VALUE']];
    }
  }

  private function getSKUs()
  {
    $arOffers = array_chunk( array_keys($this->items), 1000 );
    foreach ($arOffers as $key => $chunk){
      $data = [
        'offer_id' => $chunk
      ];
      $ch = curl_init($this->api_url . '/v2/product/info/list');
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
      $result = json_decode($res, 1);
      if ( empty($result['code']) && empty($result['message']) ){
        foreach ( $result['result']['items'] as $card ){
          if ( !empty($card['sku']) ){
            $this->items[$card['offer_id']]['sku'] = $card['sku'];
          }elseif( !empty($card['fbo_sku']) ){
            $this->items[$card['offer_id']]['sku'] = $card['fbo_sku'];
          }elseif( !empty($card['fbs_sku']) ){
            $this->items[$card['offer_id']]['sku'] = $card['fbs_sku'];
          }else{
            $this->writeLog('SKU not found');
          }
        }
      }else{
          $this->writeLog('Error occured in chunk '. $key . "!\n\r" . $res);
        }
    }
  }

  private function addBarcodes()
  {
    // var_dump($this->items);
    $data = array_chunk( array_values($this->items), 100);
    foreach ($data as $key => $chunk) {
      var_dump($chunk);
    }

    // $ch = curl_init($this->api_url . '/v2/product/info');
    // curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    //   'Api-Key:' . $this->token,
    //   'Client-Id:' . $this->client_id,
    //   'Content-Type:application/json'
    // ));
    // curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    // curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    // curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    // curl_setopt($ch, CURLOPT_HEADER, false);
    // $res = curl_exec($ch);
    // curl_close($ch);

  }

  private function writeLog()
  {
    file_put_contents($this->logPath, date('d-m-Y G:i:s'). ' --- ' . $message . PHP_EOL, FILE_APPEND);
  }

}

(new OzonAddBarcodes())->run();

 ?>
