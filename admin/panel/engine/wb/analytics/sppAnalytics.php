<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);
ob_implicit_flush(true);
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
$workers = new WorkersChecker("panel_engine_wb_analytics_sppAnalytics_php");
if (!$workers->checkStatus()) {
	exit;
}
$workers->updateStatus("Y");

class TopAnalyticsWB
{
  private $dbPanel;
  private $dbLeg;
  private $items = [];
  private $goods = [];
  private $cabinet;
  private $clubDiscount = 0;
  private $baseUrl = '';

  private $filename = '';
  private $stockFbo = [];

  public function __construct( $cab = 'WR' )
  {
    CModule::IncludeModule('panel.manager');
    $this->dbPanel = new DBPanel;
    global $DB;
    $this->dbLeg = $DB;
    if ( in_array($cab, ["WR","TL"]) )
    $strSql = "SELECT * FROM wdhs_wb_main_settings WHERE cabinet = '{$cab}'";
    $results = $this->dbLeg->Query($strSql, false, $err_mess.__LINE__);
    if ( $results->SelectedRowsCount() < 1 ){
      $this->writeLog('No API settings for cabinet.');
      die;
    }
    $this->api = $results->Fetch();
    $this->baseUrl = 'https://www.wildberries.by/__internal/u-card/cards/v4/list';
    $this->filename = "/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/wb/export/analytics.json";
    if ( file_exists($this->filename) ){
      $json = file_get_contents($this->filename);
      $this->stockFbo = json_decode($json, true);
    }
  }

  public function run(){
    var_dump("getItems starts");
    $this->getItems();
    var_dump("getClubDiscount starts");
    $this->getClubDiscount();
    var_dump("getFinalPrice starts");
    $this->getFinalPrice();
    var_dump("getStartPrice starts");
    $this->getStartPrice();
    var_dump("writeInDatabase starts");
    $this->writeInDatabase();
  }

  private function getItems():void
  {
    // Здесь мы будем получать список товаров, по которым будем собирать статистику
    $result = $this->dbPanel->query("SELECT * FROM wb_top_models");
    $rows = $this->dbPanel->fetchAll($result);
    foreach ($rows as $row) {
      $tops[] = $row['model'];
    }
    $topModels = array_map(function($item){
      return "'".$item."'";
    },$tops);
    $topModels = implode(',', $topModels);
    $strSql = "SELECT * FROM wdhs_wb_props WHERE cabinet = 'WR' AND article IN ({$topModels})";
    $res = $this->dbLeg->Query($strSql, false, $err_mess.__LINE__);
    while ( $row = $res->Fetch() ){
      $this->items[ $row['nmid'] ] = $row['article'];
    }
    if ( count($this->items) != count($tops) ){
      var_dump('MISMATCH BETWEEN this->items AND topModels');
    }
  }

  private function getClubDiscount():void
  {
    $josn = file_get_contents('https://static-basket-01.wbbasket.ru/vol1/global-payment/default-payment.json');
    $wallet = json_decode($josn, true)['data'];
    foreach ( $wallet as $val ){
      if ( $val['wc_type'] == 'Незалогиненный кошелёк' && $val['is_active'] == true ){
        $this->clubDiscount = $val['discount_value'];
      }
    }
  }

  private function getAuthCookie():string
  {
    $path = "/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/wb/configs/analytics_cookie.json";

    if ( !file_exists( $path ) ) die('Нет куки файла');

    $json = file_get_contents( $path );
    $cookieArray = json_decode( $json, true );
    $this->checkIfCookieExpired( $cookieArray['expires_at'] );

    return $cookieArray['cookie'];
  }

  private function checkIfCookieExpired( string $expires_at ):bool
  {
    $today_ts = time();
    $expires_ts = strtotime( $expires_at );

    $diff = $expires_ts - $today_ts;

    $days_left = $diff / 60 / 60 / 24;

    if ( $diff < 0 ){
      // $this->bot->sendMessage("⚠<b>WB. Куки для аналитики просрочена. Данные не получены</b>\n\n");
      throw new Exception( "Cookie is outdated. Please, refresh it\n" );
    }

    if ( $days_left < 3 ){
      // $this->bot->sendMessage("⚠<b>WB. Срок жизни куки подходит к концу. Замените до {$expires_at}</b>\n\n");
    }

    return true;
  }

  private function calcClubPrice( int $price ):float
  {
    return $price * ( 1 - $this->clubDiscount / 100 );
  }

  private function getFinalPrice():void
  {
    $chunks = array_chunk( array_keys($this->items), 30, true );
    $arQuery = [
      'appType' => '1',
      'curr' => 'rub',
      'dest' => '1259570984',
      'spp' => '30',
      'hide_dtype' => '10',
      'ab_testing' => 'false',
      'nm' => '',
    ];

    $headers = [
      'Sec-GPC:1',
      'deviceid:site_aoaoaoaoaoa',
      'Accept-Language:en-US,en;q=0.9',
      'Accept-Encoding:gzip, deflate, br, zstd',
    ];

    foreach ( $chunks as $chunk ){
      $aq = $arQuery;
      $aq['nm'] = implode(';', $chunk);

      $query = http_build_query($aq);
      $url = $this->baseUrl . '?' . $query;

      $ch = curl_init($url);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch,CURLOPT_CONNECTTIMEOUT, 30);
      curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers );
      curl_setopt( $ch, CURLOPT_ENCODING, 'gzip, deflate' );
      curl_setopt( $ch, CURLOPT_COOKIE, $this->getAuthCookie() );
      $resCurl = curl_exec($ch);
      curl_close($ch);
      $result[] = json_decode($resCurl,true);
    }
    foreach ( $result as $chunk ){
      foreach ( $chunk['products'] as $card ){
        $this->goods[ $card['id'] ] = [
          'nmid' => $card['id'],
          'model' => $this->items[ $card['id'] ],
          'our_price' => 0,
          'black_price' => $card['sizes'][0]['price']['product'] / 100,
          'sell_price' => $this->calcClubPrice( $card['sizes'][0]['price']['product'] / 100 ),
          'stock_fbo' => $this->stockFbo[ $this->items[ $card['id'] ] ] ?? 0,
          'hour' => date('G'),
          'date' => date('Y-m-d'),
        ];
      }
    }
  }

  private function getStartPrice():void
  {
    $url = 'https://discounts-prices-api.wildberries.ru/api/v2/list/goods/filter';
    foreach ( $this->items as $nmid => $model ){
      $query = '?limit=10&offset=0&filterNmID='.$nmid;
      $ch = curl_init( $url . $query );
      curl_setopt(
      			$ch,
      			CURLOPT_HTTPHEADER,
      			array(
      				"Content-Type: application/json",
      				"Authorization: {$this->api['api']}"
      			)
      		);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch,CURLOPT_CONNECTTIMEOUT, 30);
      $res = curl_exec($ch);
      curl_close($ch);
      $result[] = json_decode( $res, true )['data']['listGoods'];
      sleep(2);
    }
    foreach( $result as $card ){
      if ( isset($this->goods[ $card[0]['nmID'] ]) ){
        $this->goods[ $card[0]['nmID'] ]['our_price'] = $card[0]['sizes'][0]['discountedPrice'];
      }
    }
  }

  private function calculateSpp():array
  {
    $result = [];
    foreach ( $this->goods as $nmid => $arItem ){

    }
  }

  public function writeInDatabase():void
  {
    $this->fuckYouBitrixORM( 'wb_spp_analytics_by_hour', array_values($this->goods) );
  }

  private function fuckYouBitrixORM($tableName , $arrayData)
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
    try{
      $this->dbPanel->query( $strSql );
    }catch ( Throwable $e){
      print_r( $e );
      print_r( $strSql );
    }
  }

  private function writeLog( string $message ):void
  {

  }

}

(new TopAnalyticsWB)->run();
 ?>
