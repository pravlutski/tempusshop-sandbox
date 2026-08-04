<?php

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
// var_dump($_SERVER["DOCUMENT_ROOT"]);
// die;

CModule::IncludeModule("iblock");
CModule::IncludeModule("panel.manager");

class OzonAdvUpdate
{
  private $authPeformance = [];
  private $authSeller = [];
  private $toUpdateGoods = [];
  private $toDeleteGoods = [];
  private $preparedGoods = [];
  private $ozonGoods = [];
  private $adverts = [];

  public function __construct()
  {
    $this->getAuthPerformance();
    $this->getAuthSeller();
  }

  public function run()
  {
    $this->getSKUFromCamps();
    $this->deleteActiveSKUs();
    $this->getTopGoodsMS();
    $this->prepareData();
    $this->updateSKUs();
    // var_dump($this->toDeleteGoods);
  }

  private function getAuthPerformance()
  {
    $dataAuth = [
      'client_id' => '24736798-1709726614726@advertising.performance.ozon.ru',
      'client_secret' => '3pgdkqlWpKavNxEl98fNVfD417ymdvrRjGC9qoI3G0DX9M860ZHgAG3iy-BzIJ8hXoeTlH_TsntDPlSvOA',
      'grant_type' => 'client_credentials'
    ];
    $url = 'https://performance.ozon.ru/api/client/token';

    $ch = curl_init($url);
    curl_setopt(
          $ch,
          CURLOPT_HTTPHEADER,
          array(
            'Host: performance.ozon.ru',
            'Content-Type:application/json',
            'Accept: application/json'
          )
        );
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($dataAuth));
    curl_setopt($ch,CURLOPT_CONNECTTIMEOUT, 30);
    $auth = json_decode(curl_exec($ch), 1);
    curl_close($ch);
    $this->authPeformance = $auth;
  }

  private function getAuthSeller()
  {
    global $DB;
    $strSql = "SELECT * FROM wdhs_ozon_main_settings";
    $results = $DB->Query($strSql, false, $err_mess.__LINE__);
    while ($row = $results->Fetch()){
      $this->authSeller[$row['name']] = $row['value'];
    }
  }

  public function getSKUFromCamps()
  {
    $url = 'https://performance.ozon.ru:443/api/client/campaign?advObjectType=SKU&state=CAMPAIGN_STATE_RUNNING';

    $ch = curl_init($url);
    curl_setopt(
    			$ch,
    			CURLOPT_HTTPHEADER,
    			array(
            'Authorization: ' . $this->authPeformance['token_type'] . ' '. $this->authPeformance['access_token'],
            'Host: performance.ozon.ru',
            'Content-Type:application/json',
            'Accept: application/json'
    			)
    		);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch,CURLOPT_CONNECTTIMEOUT, 30);
    $result = json_decode(curl_exec($ch), 1);
    curl_close($ch);
    if ( $result['error'] ){
      die($result['error']);
    }
    $goods = [];
    foreach ($result['list'] as $value) {
      $this->adverts[] = $value['id'];

      $url = 'https://performance.ozon.ru:443/api/client/campaign/' . $value['id'] . '/objects';

      $ch = curl_init($url);
      curl_setopt(
      			$ch,
      			CURLOPT_HTTPHEADER,
            array(
              'Authorization: ' . $this->authPeformance['token_type'] . ' '. $this->authPeformance['access_token'],
              'Host: performance.ozon.ru',
              'Content-Type:application/json',
              'Accept: application/json'
      			)
      		);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch,CURLOPT_CONNECTTIMEOUT, 30);
      $result = json_decode(curl_exec($ch), 1);
      curl_close($ch);
      sleep(1);
      if ( $result['error'] ){
        die($result['error']);
      }
      $this->toDeleteGoods[$value['id']] = $result['list'];
    }

    foreach ($this->toDeleteGoods as &$advert) {
      foreach ($advert as &$value) {
        $value = $value['id'];
      }
    }
  }

  public function getTopGoodsMS()
  {
    $objMS = new MoyskladAPI('msk');

    $dates = ['momentTo' => date("Y-m-d"), 'momentFrom' => date("Y-m-d", strtotime("-3 month"))];
    $fromMS = $objMS->getListProfitNew($dates);

    $asqData = []; // articles sales quantity data
    foreach ($fromMS as $value) {
      $asqData[ $value["assortment"]["article"] ] = [
        "sellQuantity" => $value["sellQuantity"],
        "sellSum" => $value["sellSum"],
        "article" => $value["assortment"]["article"],
      ];
    }

    $arOrder = array_column($asqData, 'sellQuantity');
    array_multisort($asqData, SORT_DESC, $arOrder);
    $asqData = array_chunk($asqData, 800, true)[0];

    $arFilter = Array(
      "IBLOCK_ID"	=> 16,
      "PROPERTY_CML2_ARTICLE" => array_keys( $asqData )
    );

    $arSelect = array("ID", "IBLOCK_ID", "PROPERTY_CML2_ARTICLE", "PROPERTY_OZON_ID");
    $rs = CIBlockElement::GetList( array(), $arFilter, false, false, $arSelect );

    $goods = [];
    while ( $item = $rs->getNext() ) {
      if( $item['PROPERTY_OZON_ID_VALUE'] != 0 ){
        $goods[ (int)$item['PROPERTY_OZON_ID_VALUE'] ] = [
          'product_id' => $item['PROPERTY_OZON_ID_VALUE'],
          'sellSum' => $asqData[$item['PROPERTY_CML2_ARTICLE_VALUE']]['sellSum'],
          'sellQuantity' => $asqData[$item['PROPERTY_CML2_ARTICLE_VALUE']]['sellQuantity'],
          'article' => $item['PROPERTY_CML2_ARTICLE_VALUE']
        ];
      }
    }

    $url = 'https://api-seller.ozon.ru/v2/product/info/list';

    $data = [
      'product_id' => array_keys( array_chunk($goods, 800, true)[0] )
    ];
    // var_dump($data);
    // die;
    $ch = curl_init($url);
    curl_setopt(
      $ch,
      CURLOPT_HTTPHEADER,
      array(
        'Client-Id: ' . $this->authSeller['client_id'],
        'Api-Key:' . $this->authSeller['key'],
        'Content-Type:application/json',
        'Accept: application/json'
      )
    );
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch,CURLOPT_CONNECTTIMEOUT, 30);
    curl_close($ch);

    $result = json_decode(curl_exec($ch), 1);
    if ( $result['message'] ){
      die($result['message']);
    }

    foreach ($result['result']['items'] as $value) {
      $this->ozonGoods[] = [
        'sku' => $value['sku'],
        'fbo_sku' => $value['fbo_sku'],
        'fbs_sku' => $value['fbs_sku'],
        'stock' => $value['stocks']['present'],
        'hasStock' => $value['visibility_details']['has_stock']
      ];
    }
  }

  public function prepareData()
  {

    foreach ($this->ozonGoods as $value) {
      if ( $value['stock'] > 0 ){
        if ( $value['sku'] != 0 )
        $this->toUpdateGoods[] = $value['sku'];
      }
      if ( $value['fbo_sku'] != 0 ){
        $this->toUpdateGoods[] = $value['fbo_sku'];
      }
      if ( $value['fbs_sku'] != 0 ){
        $this->toUpdateGoods[] = $value['fbs_sku'];
      }
    }
  }

  public function updateSKUs()
  {
    $this->toUpdateGoods = array_map(function($elem){
      return (string)$elem;
    }, $this->toUpdateGoods);

    $dataChunks = array_chunk( $this->toUpdateGoods, 250 );
    $i = 0;
    foreach ($dataChunks as $value){
      $data = [
        'bids' => ['sku' => $value]
      ];
      $url = 'https://performance.ozon.ru:443/api/client/campaign/' . $this->adverts[$i] . '/products';
      var_dump($url);
      var_dump($data);
      //CURL REQUEST
      $i++;
    }

    return false;
  }

  public function deleteActiveSKUs()
  {
    foreach ($this->toDeleteGoods as $advert => $activeGoods){

      $data = [
        'sku' => $activeGoods
      ];
      $url = 'https://performance.ozon.ru:443/api/client/campaign/' . $advert . '/products/delete';
      var_dump($url);
      var_dump($data);
      //CURL REQUEST

    }
    return false;
  }

}

$objAuto = new OzonAuto();
$objAuto->run();
 ?>
