<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);
ob_implicit_flush( true );

require( $_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php" );
require_once $_SERVER['DOCUMENT_ROOT'] . '/local/classes/CronWorkerGuard.php';
if (!CronWorkerGuard::startFromArgv()) {
	exit;
}

use \Bitrix\Main\Application;

class ReviewsWB
{
  private array $items = [];

  private $dbMain;
  private $dbPanel;

  private string $cabinet;

  private int $take = 5000;
  private int $iteration = 5;
  private string $url = 'https://feedbacks-api.wildberries.ru/api/v1/feedbacks';
  private string $savePath = '/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/wb/reviews/reviews.json';
  private string $reviewsHistoryPath = '/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/wb/reviews/history.json';
  private string $logPath = '/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/wb/reviews/reviews_errors.log';
  private string $errLogPath = "/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/wb/reviews/error_log.log";

  private int $sleep = 400000;
  private int $limit = 75;

  public function __construct( $cabinet = 'WR' )
  {
    if ( !in_array($cabinet, ['WR', 'TL']) ) die( "Wrong cabinet\n" );
    CModule::IncludeModule('panel.manager');
    $this->dbMain = \Bitrix\Main\Application::getConnection();
    $this->dbPanel = new DBPanel;
    $this->cabinet = $cabinet;
    $this->savePath = '/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/wb/reviews/reviews.json';

    $this->reasons = [
      'nophoto' => 0,
      'notext' => 0,
      'lowValue' => 0,
      'alreadyParsed' => 0
    ];
  }

  public function run():void
  {
    // die( 'GERE' . PHP_EOL );

    file_put_contents(
      $this->errLogPath,
      PHP_EOL,
    );

    try{
      $this->buildReviewBase();
    }catch( Throwable $e ){
      file_put_contents(
        $this->logPath,
        print_r($e) . PHP_EOL
      );
    }
    $this->saveAsJson();
    var_dump($this->reasons);
    exec("php /var/www/bitrix/data/www/tempus.ru/local/custom/importReviewsMP.php WB");
  }

  private function getHeaders( $cabinet ):array
  {
    $strSql = "SELECT api FROM wdhs_wb_main_settings WHERE cabinet = '{$cabinet}'";
    $res = $this->dbMain->Query( $strSql );

    return [
      'Content-Type: application/json',
      'Authorization: ' . $res->Fetch()['api'],
    ];
  }

  private function getItems():array
  {
    $items = [];
    $strSql = "SELECT wwp.article, wwp.nmid
               FROM wdhs_wb_props AS wwp
               JOIN ci_wb_top as cwt
               ON wwp.article = cwt.article
               WHERE wwp.cabinet = '{$this->cabinet}'";

    $res = $this->dbMain->Query( $strSql );

    while ( $row = $res->Fetch() ){
      // if ( $row['article'] != 'A-158WA-1' ) continue;
      $items[] = ['model' => $row['article'], 'nmid' => $row['nmid']];
    }

    return $items;
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

  private function buildReviewBase():void
  {
    $nmids = $this->getItems();
    $history = $this->getReviewsHistory();
    $items = [];
    foreach ( $nmids as $model ){
      try{
        $items[] = $this->getReviews( $model['nmid'], $history, true );
        $items[] = $this->getReviews( $model['nmid'], $history, false );
      } catch( Throwable $e ){
        $this->writeLog( $e );
      }
    }
    $this->saveReviews( $history );
    $this->items = $items;
  }

  private function saveAsJson():void
  {
    $json = json_encode( $this->items, JSON_UNESCAPED_UNICODE );
    file_put_contents( $this->savePath, $json );
  }

  private function getReviews( int $nmid, array &$history, bool $isAnswered ):array
  {
    $feedbacks = [];

    for ( $skip = 0; $skip < $this->take * $this->iteration; $skip += $this->take ){
      $data = [
        'isAnswered' => $isAnswered,
        'nmId' => strval( $nmid ),
        'take' => $this->take,
        'skip' => $skip,
        'order' => 'dateDesc',
        'dateFrom' => strtotime('-1 year'),
      ];

      $query = http_build_query( $data );

      $res = $this->request(
        url: "{$this->url}?{$query}",
        headers: $this->getHeaders( $this->cabinet ),
        data: '',
        method: "GET"
      );

      if ( $res['code'] != 200 ){
        file_put_contents(
          $this->errLogPath,
          "skip step: {$skip}. Error\n",
          FILE_APPEND
        );
        file_put_contents(
          $this->errLogPath,
          print_r($res, true) . PHP_EOL,
          FILE_APPEND
        );
        sleep( 2 );
        continue;
      }

      file_put_contents(
        "/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/wb/rewRes.txt",
        print_r($res, true)
      );
      var_dump( count($res['result']['data']['feedbacks']) );
      foreach ( $res['result']['data']['feedbacks'] as $fb ){
        if ( !$this->checkConditions($fb) ) {
          file_put_contents(
            $this->errLogPath,
            "{$nmid}: Review {$fb['id']} does not match requirements\n",
            FILE_APPEND
          );
          continue;
        }
        if ( in_array( $fb['id'], $history ) ){
          file_put_contents(
            $this->errLogPath,
            "{$nmid}: Review {$fb['id']} was already parsed\n",
            FILE_APPEND
          );
          $this->reasons['alreadyParsed'] += 1;
          continue;
        }
        $text = $fb['text'];

        if ( empty($text) && (!empty($fb['pros']) && $fb['pros'] != "") ){
          $text = $fb['pros'];
        }

        $tmp = [
          'userName' => $fb['userName'],
          'productName' => end( explode(' ', $fb['productDetails']['supplierArticle']) ),
          'text' => $text,
          'video' => $fb['video']['link'],
          'rate' => $fb['productValuation'],
          'published_at' => $fb['createdDate'],
          'review_id' => $fb['id'],
        ];

        foreach ( $fb['photoLinks'] as $elem ){
          $tmp['photo'][] = $elem['fullSize'];
        }

        $feedbacks[] = $tmp;

        if ( count($feedbacks) == $this->limit ) break 2;

        $history[] = $fb['id'];
      }

      usleep( $this->sleep );

      if ( empty($res['result']) || count($res['result']['data']['feedbacks']) < $this->take ){
        file_put_contents(
          $this->errLogPath,
          "Got answered reviews count ".count($res['result']['data']['feedbacks'])." lower than limit. Cycle is broken" . PHP_EOL,
          FILE_APPEND
        );
        break;
      }
    }

    return $feedbacks;
  }

  private function checkConditions( array $feedback ):bool
  {
    if ( empty($feedback['photoLinks']) ) {
      $this->reasons['nophoto'] += 1;
      return false;
    }
    if ( empty($feedback['text']) && empty($feedback['pros']) ) {
      $this->reasons['notext'] += 1;
      return false;
    }
    if ( intval($feedback['productValuation']) < 4 ) {
      $this->reasons['lowValue'] += 1;
      return false;
    }

    return true;
  }

  private function request( string $url, array $headers, string $data, string $method ):array
  {
    $ch = curl_init( $url );
    curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers );

    curl_setopt( $ch, CURLOPT_POSTFIELDS, $data );
    curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, $method );
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

  private function writeLog( mixed $message ):void
  {
    $date = date('Y-m-d G:i:s');
    $message = print_r( $message, true );

    file_put_contents(
      $this->logPath,
      "{$date} --- {$message}" . PHP_EOL,
      FILE_APPEND
    );
  }
}

( new ReviewsWB('WR') )->run();
 ?>
