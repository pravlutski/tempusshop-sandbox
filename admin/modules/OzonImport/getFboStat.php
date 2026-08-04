<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
set_time_limit(0);

use Bitrix\Main\Application,
	Bitrix\Main\Loader;

CModule::IncludeModule('panel.manager');

class OzonFBOStat
{
  private $api_url;
  private $client_id;
  private $token;
  private $items;
  private $arLog;

  public function __construct(){
		global $DB;

		$strSql = "SELECT * FROM wdhs_ozon_main_settings";
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		while ($row = $results->Fetch()){
		  $arSetting[$row['name']] = $row['value'];
		}

    $this->api_url = $arSetting['api_url'];
    $this->client_id = $arSetting['client_id'];
    $this->token = $arSetting['key'];
		$this->logPath = "/var/www/bitrix/data/www/tempusshop.ru/admin/modules/OzonImport/logs/stat/stock.txt";
	}

  public function run()
  {
		$this->writeLog('START');
    $this->getItems();
    $this->getStockFbo();
    $this->writeDB();
		$this->writeLog('END');
  }

  public function getItems()
  {
      $arSelect = Array("ID","IBLOCK_ID","IBLOCK_SECTION_ID","PROPERTY_OZON_ACTIVE","PROPERTY_CML2_ARTICLE","PROPERTY_OZSB_PRICE","PROPERTY_WBARTICLE","PROPERTY_TYPEOFSKLAD");
      $arFilter = Array(
        "IBLOCK_ID" => CProSet::IB_CATALOG,
        //"ID" => 5045,
        //"SECTION_ID" => 558
        "PROPERTY_OZON_ACTIVE_VALUE" => 'Да'
        //"ID" => 178901
      );
      //$arFilter["!ID"] = 14124;
      $result = CIBlockElement::GetList(array(), $arFilter, false, false, $arSelect);


      while ($el = $result->GetNext()){
        if (empty($el['PROPERTY_WBARTICLE_VALUE']) or $el['PROPERTY_WBARTICLE_VALUE'] == '') {
            $this->arLog['GET_ITEMS']['ERRORS']['NO_ARTICLE'][] = $el['ID'];
        }	else if (empty($el['PROPERTY_OZSB_PRICE_VALUE']) or $el['PROPERTY_OZSB_PRICE_VALUE'] == 0) {
            $this->arLog['GET_ITEMS']['ERRORS']['NO_PRICE'][] = $el['ID'];
        } else {
          // $arSection = getSectionsElement($el["ID"]);
          // if ($arSection[1]['ID'] == '558') {
          $this->items[$el["PROPERTY_WBARTICLE_VALUE"]] = [
            "ID" => $el["ID"],
            "ARTICLE" => $el['PROPERTY_CML2_ARTICLE_VALUE'],
            "OZON_ARTICLE" => $el["PROPERTY_WBARTICLE_VALUE"],
            "PRICE" => $el["PROPERTY_OZSB_PRICE_VALUE"],
          ];
        }
      }
      //print_r($this->items);
			if ( !empty($this->items) ){
				$this->writeLog('items: ' . count($this->items));
			}else{
				$this->writeLog('ERROR! GOT NO ITEMS FROM BITRIX');
			}
    }

    public function getStockFbo()
    {
      $fromMS = $this->getTurnover();
      $this->stockFbo = array();
      foreach ($this->items as $key => &$arItem) {
        $offerIDs[] = $arItem['OZON_ARTICLE'];
      }
      $offerIDsC = array_chunk($offerIDs, 1000);
      foreach ($offerIDsC as $key => $value) {
        $data = [
          'filter' => array('offer_id' => $value),
          'last_id' => "",
          'limit' => 1000,
        ];

        $ch = curl_init($this->api_url . '/v3/product/info/stocks');
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
          'Api-Key:' . $this->token,
          'Client-Id:' . $this->client_id,
          'Content-Type:application/json'
        ));
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data, JSON_UNESCAPED_UNICODE));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HEADER, false);
        $res = curl_exec($ch);
        curl_close($ch);
        $res = json_decode($res, true);

        if (!empty($res['result']['items'])) {
          foreach ($res['result']['items'] as $key => $value) {
            if ( intval($value['stocks'][1]['present']) - intval($value['stocks'][1]['reserved']) <= 0 ) continue;
            $art = explode('_', $value['offer_id'] )[2];
            $this->stockFbo[$art] = [
              'bitrixId' => intval($this->items[$value['offer_id']]['ID']),
              'sku' => intval($value['product_id']),
              'stock' => intval($value['stocks'][1]['present']) - intval($value['stocks'][1]['reserved']),
              'price' => intval($fromMS[$art]),
              'model' => $this->items[$value['offer_id']]['ARTICLE'],
              'account' => $value['stocks'][1]['type'],
              'date' => date('Y-m-d')
            ];
          }
        }


      }
      // var_dump($this->stockFbo);
			if ( !empty($this->stockFbo) ){
				$this->writeLog('items: ' . count($this->stockFbo));
			}else{
				$this->writeLog('ERROR! GOT NO STOCKS FROM OZON');
			}
    }

    private function getTurnover()
    {
      global $DB;
      $fromMS = array();
      $strSql = "SELECT * FROM current_cost_ms";
      $resultDB = $DB->Query($strSql, false, $err_mess.__LINE__);
      $fromMS = [];
      while( $row = $resultDB->Fetch() ){
        $fromMS[$row['model']] = $row['cost'];
      }
			if ( !empty($fromMS) ){
				$this->writeLog('turnover goods: ' . count($fromMS));
			}else{
				$this->writeLog('ERROR! GOT NO TURNOVER FROM DB');
			}
      return $fromMS;
    }

    private function writeDB()
    {
      $data = array_chunk(array_values($this->stockFbo), 100);
      foreach ($data as $key => $chunk) {
        $this->fuckYouBitrixORM('ozon_stock_fbo_stat',$chunk);
      }
			$this->writeLog('TABLE IS UP TO DATE');
    }

    private function writeLog($message)
    {
      if ( empty($this->logPath) ) return false;
      file_put_contents($this->logPath, date('d-m-Y G:i:s'). ' --- ' . $message . PHP_EOL, FILE_APPEND);
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

(new OzonFBOStat)->run();

 ?>
