<?php

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

CModule::IncludeModule("iblock");
CModule::IncludeModule("panel.manager");

class OzonBidsUpdate{
  protected $authPeformance;
  protected $activeGoods;
  protected $adverts;
  protected $competitiveBids;

  public function run(){
    $this->getSKUFromCamps();
    $this->getcompetitiveBids();
    // var_dump($this->activeGoods);
    $this->updateBids();
  }

  public function __construct(){
    $this->getAuthPerformance();
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

      $url = "https://performance.ozon.ru:443/api/client/campaign/{$value['id']}/objects";

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
      $this->activeGoods[$value['id']] = $result['list'];
    }

    foreach ($this->activeGoods as &$advert) {
      foreach ($advert as &$value) {
        $value = $value['id'];
      }
    }
  }

  public function getcompetitiveBids(){
    foreach ( $this->activeGoods as $key => $value ){
      $data = [
        'skus' => $value
      ];

      $url = "https://performance.ozon.ru:443/api/client/campaign/{$key}/products/bids/competitive";

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
      curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
      curl_setopt($ch,CURLOPT_CONNECTTIMEOUT, 30);
      $result = json_decode(curl_exec($ch), 1);

      curl_close($ch);
      sleep(1);
      if ( $result['error'] ){
        die($result['error']);
      }

      $this->competitiveBids[$result['campaignId']] = $result['bids'];
      var_dump($this->competitiveBids);
      die;
    }
  }

  public function updateBids(){

    foreach ( $this->competitiveBids as $key => $value ){

      $data = [
        'bids' => $value
      ];

      $url = "https://performance.ozon.ru:443/api/client/campaign/{$key}/products";

      var_dump($url);
      var_dump($bids);
    }


    return false;
  }

}

$objBids = new OzonBidsUpdate();
$objBids->run();

 ?>
