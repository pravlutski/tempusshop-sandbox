<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

class PriceManager
{
  private ?DBPanel $db;
  private $headers;
  private array $priceData;

  public function __construct( string $cabinet )
  {
    if ( !in_array($cabinet, ['IP', 'TI']) ){
      throw new \Exception('CABINET IS FORBIDDEN');
    }
    $this->loadModules();
    $this->db = new DBPanel;
    $this->priceData = [];

    $api = $this->getApiData( $cabinet );
    $this->headers = [
      'Api-Key:' . $api['key'],
			'Client-Id:' . $api['client_id'],
			'Content-Type:application/json'
    ];
  }

  private function getOfferPriceData( string $offer_id ):bool
  {
    if ( empty($offer_id) ){
      throw new \Exception('PARARMETER offer_id CANNOT BE EMPTY. METHOD: ' . __FUNCTION__);
    }
    $data = [
  		"filter" => [
        "offer_id" => [$offer_id]
      ],
  		"limit" => 1000
  	];
    $url = "https://api-seller.ozon.ru/v5/product/info/prices";
    $res = $this->request( $url, $this->headers, json_encode($data) );

    if ( empty($res['items']) ) return false;

    foreach ( $res['items'] as $item ) {
      $our = $item['price']['price'];
      $sell = $item['price']['marketing_price'];
      $model = end( explode('_', $item['offer_id']) );

    	$this->priceData[ $item['offer_id'] ] = [
        'model' => $model,
        'our_price' => $our,
        'black_price' => $sell,
      ];
    }

    return true;
  }

  public function calculateSpp( string $offer_id ):int|float|bool
  {
    if ( empty($offer_id) ){
      throw new \Exception('PARARMETER offer_id CANNOT BE EMPTY. METHOD: ' . __FUNCTION__);
    }
    $this->getOfferPriceData( $offer_id );
    if ( !isset($this->priceData[$offer_id]) ) return false;

    $offer = $this->priceData[$offer_id];
    if ( empty($offer['our_price']) || empty($offer['black_price']) ) return false;

    $result = ($offer['our_price'] - $offer['black_price']) / $offer['our_price'] * 100 ;


    return $result;
  }

  public function calculateWishedPrice( int|float $wishedPrice, string $offer_id ):array|bool
  {
    if ( empty($offer_id) ){
      throw new \Exception('PARARMETER offer_id CANNOT BE EMPTY. METHOD: ' . __FUNCTION__);
    }
    if ( $wishedPrice <= 0 ){
      throw new \Exception('PARARMETER wishedPrice MSUT BE GREATER THAN ZERO. METHOD: ' . __FUNCTION__);
    }
    $spp = $this->calculateSpp( $offer_id );
    if ( !$spp ) return false;
    $price = $wishedPrice / ( 1 - $spp / 100 );

    if ( $price < 0 ) return false;

    $result = [
      'spp' => $spp,
      'price' => $price,
      'wishedPrice' => $wishedPrice,

    ];

    return $result;
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
      return [ 'error' => $error_msg ];
    }

    return json_decode( $res, true );
  }

  private function getApiData( string $cabinet ):array
  {
    $rows = $this->db->select(['*'], "ozon_main_settings_{$cabinet}")->make();
    $result = [];
    foreach ( $rows as $row ){
      $result[ $row['name'] ] = $row['value'];
    }

    return $result;
  }

  private function loadModules():void
  {
    CModule::IncludeModule('panel.manager');
  }
}



 ?>
