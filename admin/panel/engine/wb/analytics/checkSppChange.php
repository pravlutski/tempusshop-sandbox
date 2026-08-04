<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

class CheckSppChange
{
  private $itemsToday = [];
  private $itemsYesterday = [];
  private $topList = [];

  private $dbPanel;
  private $bot;

  private $log_path;

  public function __construct()
  {
    CModule::IncludeModule('panel.manager');
    $this->dbPanel = new DBPanel;
    $this->bot = new TGNotifier;

    $this->log_path = "{$_SERVER['DOCUMENT_ROOT']}/admin/panel/engine/wb/analytics/spp_log.txt";
  }

  public function run()
  {
    try{
      $this->writeLog('START');

      $this->getTopList();
      $this->writeLog('Got top list');

      $this->getItemsToday();
      $this->writeLog('Got today\'s state');

      $this->getItemsYesterday();
      $this->writeLog('Got yesterday\'s state');

      $this->createMessage();
      $this->writeLog('Message was formed');

      $this->sendMessage();
      $this->writeLog('Message was sent');

      $this->writeLog('END');
    }
    catch( Throwable $e ){
      $errorMessage = sprintf(
        PHP_EOL . 'ERROR: %s in %s:%d\nStack trace: %s',
        $e->getMessage(),
        $e->getFile(),
        $e->getLine(),
        $e->getTraceAsString()
      );
      $this->writeLog( $errorMessage );
      throw $e;
    }
  }

  private function getItemsToday():void
  {
    $res = $this->dbPanel->select( ['*'], 'wb_top_analytics' )->where( 'date', date('Y-m-d') )->make();

    foreach ( $res as $row ){
      $spp = 'Нет данных';
      $c_spp = 'Нет данных';
      if ( $row['our_price'] != 0 ){
        $spp = round( ($row['our_price'] - $row['black_price']) / $row['our_price'] * 100 );
        $c_spp = round( ($row['our_price'] - $row['sell_price']) / $row['our_price'] * 100 );
      }
      $this->itemsToday[ $row['model'] ] = floor( $c_spp );
    }

  }

  private function getItemsYesterday():void
  {
    $res = $this->dbPanel->select( ['*'], 'wb_top_analytics' )->where( 'date', date('Y-m-d', strtotime('-1 day') ) )->make();

    foreach ( $res as $row ){
      $spp = 'Нет данных';
      $c_spp = 'Нет данных';
      if ( $row['our_price'] != 0 ){
        $spp = round( ($row['our_price'] - $row['black_price']) / $row['our_price'] * 100 );
        $c_spp = round( ($row['our_price'] - $row['sell_price']) / $row['our_price'] * 100 );
      }
      $this->itemsYesterday[ $row['model'] ] = floor( $c_spp );
    }
  }

  private function getTopList():void
  {
    $res = $this->dbPanel->select(['model'], 'wb_top_models')->limit(30)->make();
    foreach ($res as $row) {
      $this->topList[] = $row['model'];
    }
  }

  private function createMessage():void
  {
    $hasYesterdayData = !empty($this->itemsYesterday);
    $hasTodayData = !empty($this->itemsToday);

    if (!$hasYesterdayData && !$hasTodayData) {
        $this->message = "⚠<b>WB. Изменение соинвеста для %s товаров</b>\n\nНет данных за последние два дня";
        return;
    }

    $tmp1 = [];
    $tmp2 = [];

    // Заполнение данных в зависимости от наличия информации
    foreach ($this->topList as $value) {
        $tmp2[$value] = $hasYesterdayData ? $this->itemsYesterday[$value] ?? 'Нет данных' : 'Нет данных';
        $tmp1[$value] = $hasTodayData ? $this->itemsToday[$value] ?? 'Нет данных' : 'Нет данных';
    }

    $header = "⚠<b>WB. Изменение соинвеста для %s товаров</b>\n\n";
    $k = 1;
    foreach ( $this->topList as $key => $model ){
      $i = $key++;
      if ( $tmp2[$model] == 100 && $tmp1[$model] == 100 ){
        $body .= "<b>{$model}</b> - <i>Два дня или более нет в наличии</i> \n";
        $k++;
        continue;
      }
      if ( $tmp2[$model] == $tmp1[$model] ){
        continue;
      }
      if ( $tmp2[$model] == 100 ){
        $body .= "<b>{$model}</b> - <i>Нет в наличии</i> --> <b>{$tmp1[$model]}%</b> \n";
        $k++;
        continue;
      }
      if ( $tmp1[$model] == 100 ){
        $body .= "<b>{$model}</b> - <b>{$tmp2[$model]}%</b> --> <i>Нет в наличии</i> \n";
        $k++;
        continue;
      }
      $body .= "<b>{$model}</b> - <b>{$tmp2[$model]}%</b> --> <b>{$tmp1[$model]}%</b> \n";
      $k++;
    }

    $header = sprintf( $header, $k - 1 );
    $this->message = $header.$body;
  }

  private function sendMessage():void
  {
    // print_r( "\n" . $this->message );
    $res = $this->bot->sendMessage($this->message);
    $this->writeLog( $res );
  }

  private function writeLog( string|bool $message ):void
  {
    if ( $message === false ) return;
    file_put_contents( $this->log_path, date('Y-m-d G:i:s') . ' --- ' . $message . PHP_EOL, FILE_APPEND);
  }

}

( new CheckSppChange )->run();
 ?>
