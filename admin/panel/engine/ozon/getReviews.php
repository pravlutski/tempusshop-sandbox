<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);
ob_implicit_flush( true );

require( $_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php" );

use \Bitrix\Main\Application;

class ReviewsWB
{
  private array $items = [];

  private $dbMain;
  private $dbPanel;

  private string $cabinet;

  private int $take = 100;
  private int $iteration = 10;
  private string $url = 'https://feedbacks-api.wildberries.ru/api/v1/feedbacks';
  private string $savePath = '/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/ozon/reviews/reviews.json';
  private string $reviewsHistoryPath = '/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/ozon/reviews/history.json';
  private string $logPath = '/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/ozon/reviews/log/reviews_errors.log';

  private int $sleep = 334;
  private int $limit = 10;

  public function __construct( $cabinet = 'IP' )
  {
    if ( !in_array($cabinet, ['IP', 'TI']) ) die( "Wrong cabinet\n" );
    CModule::IncludeModule('panel.manager');
    $this->dbMain = \Bitrix\Main\Application::getConnection();
    $this->dbPanel = new DBPanel;
    $this->cabinet = $cabinet;
  }

  public function run():void
  {
    try{
      $this->getReviewsInfo();
      $this->saveAsJson();
    }catch( Throwable $e ){
      file_put_contents(
        $this->logPath,
        print_r($e, true)
      );
    }
  }

  private function getHeaders():array
  {
    $table = "ozon_main_settings_{$this->cabinet}";
    $rows = $this->dbPanel->select(['*'], $table)->make();
    $api = [];

    foreach ( $rows as $row ){
      $api[$row['name']] = $row['value'];
    }

    return [
      'Api-Key:' . $api['key'],
      'Client-Id:' . $api['client_id'],
      'Content-Type:application/json'
    ];
  }

  private function getProducts( array $arSku ):array
  {
    $headers = $this->getHeaders();
    $chunks = array_chunk($arSku, 1000);
    $result = [];
    foreach( $chunks as $k => $chunk ){
      $data = [
        'sku' => $chunk
      ];
      $response = $this->request(
        'https://api-seller.ozon.ru/v3/product/info/list',
        $headers,
        json_encode($data)
      )['result'];

      if ( empty($response['items']) ){
        var_dump( $response );
        // throw new Exception("Сдох, не получив товары. Итерация: {$k}\n");
        continue;
      }

      foreach ( $response['items'] as $item){
        $result[ $item['sku'] ] = end( explode('_', $item['offer_id']) );
      }

      sleep( round(($k + 1) * 0.8) );
    }

    return $result ?? [];
  }

  private function getReviewsList():array
  {
    $headers = $this->getHeaders();
    $result = [];
    $data = [
      'limit' => 100,
      'statuc' => 'PROCESSED',
      'sort_dir' => 'DESC',
      'last_id' => '',
    ];
    $i = 0;
    while ( true ){
      $response = $this->request(
        'https://api-seller.ozon.ru/v2/review/list',
        $headers,
        json_encode($data)
      )['result'];

      var_dump($response);

      if ( empty($response['reviews']) ) break;

      foreach( $response['reviews'] as $review ){
        if ( $review['photos_amount'] == 0 ) continue;
        $result['reviews'][] = $review['id'];
        $result['products'][ $review['sku'] ] = $review['sku'];
      }

      if ( empty($response['last_id']) ) break;
      $data['last_id'] = $response['last_id'];
      // if ( $i == 30000 ) break;
      $i++;
    }

    $result['products'] = array_values( $result['products'] );
    var_dump( count($result['reviews']) );

    return $result;
  }

  private function getReviewsInfo():void
  {
    $data = $this->getReviewsList();
    $products = $this->getProducts( $data['products'] );
    $savedReviews = $this->getReviewsHistory();
    foreach ( $data['reviews'] as $sku => $review_id ){
      if ( in_array($review_id, $savedReviews) ) continue;

      try{
        $this->processReview($sku, $review_id, $products);
        $savedReviews[] = $review_id;
      }catch( Throwable $e ){
        $message = [
          'message' => $e->getMessage(),
          'line' => $e->getLine(),
          'trace' => $e->getTraceAsString(),
          'time' => date('Y-m-d G:i:s'),
        ];
        file_put_contents(
          $this->logPath,
          print_r($message, true),
          FILE_APPEND
        );
        sleep(2);
        continue;
      }

      sleep(2);
    }
    $this->saveReviews( $savedReviews );
  }

  private function processReview( $sku, $review_id, $products ):void
  {
    $headers = $this->getHeaders();
    $response = $this->request(
      'https://api-seller.ozon.ru/v1/review/info',
      $headers,
      json_encode( ['review_id' => $review_id] )
    );

    if ( empty($response['result']) ){
      file_put_contents(
        $this->logPath,
        print_r($response, true),
        FILE_APPEND
      );
      sleep(2);
      return;
    }
    $review = $response['result'];
    $sku = $review['sku'];
    if ( isset( $this->items[$products[$sku]]) && count($this->items[$products[$sku]]) == 200 ) return;
    if ( !static::checkConditions( $review ) ) return;

    $tmp = [
      'userName' => 'Пользователь маркетплейса',
      'productName' => $products[$sku],
      'text' => $review['text'],
      'rate' => $review['rating'],
      'published_at' => $review['published_at'],
      'review_id' => $review_id,
    ];

    foreach ($review['photos'] as $value) {
      $tmp['photos'][] = $value['url'];
    }

    $this->items[ $products[$sku] ][] = $tmp;
    sleep(1);
    file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/ozon/review_last.txt', $review_id);
  }

  private function saveReviews( array $reviews = [] ):void
  {
    file_put_contents(
      $this->reviewsHistoryPath,
      json_encode( $reviews )
    );
  }

  private function getReviewsHistory():array
  {
    if ( !file_exists($this->reviewsHistoryPath) ) return [];
    $json = file_get_contents($this->reviewsHistoryPath);
    if ( $json === false ) return [];
    $result = json_decode( $json, true );

    return $result;
  }

  private function saveAsJson():void
  {
    $json = json_encode( $this->items, JSON_UNESCAPED_UNICODE );

    file_put_contents( $this->savePath, $json );

  }


  private static function checkConditions( array $feedback ):bool
  {
    if ( empty($feedback['photos']) ) return false;
    if ( empty($feedback['text']) ) return false;
    if ( $feedback['rating'] < 4 ) return false;
    return true;
  }

  private function request( string $url, array $headers, string $data, string|bool $method = false ):array
  {
    $ch = curl_init( $url );
    curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers );

    curl_setopt( $ch, CURLOPT_POSTFIELDS, $data );
    if ( $method ){
      curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, $method );
    }
    curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
    curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
    curl_setopt( $ch, CURLOPT_HEADER, false );

    $res = curl_exec( $ch );
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close( $ch );
    $res = json_decode($res, true);

    return [
      'result' => $res,
      'code' => $code,
    ];
  }
}

( new ReviewsWB('IP') )->run();
 ?>
