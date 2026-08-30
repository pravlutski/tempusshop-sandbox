<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
$workers = new WorkersChecker("admin_panel_engine_sites_getProfitMS_php");
if (!$workers->checkStatus()) {
	exit;
}
$workers->updateStatus("Y");

class ProfitMS
{
  private $period;
  private $site_id;


  private $db;
  private $ms;
  private $items;

  private $pagesLimit;

  public function __construct( $period = 6, $site_id = 's1' )
  {
    CModule::includeModule('panel.manager');

    var_dump( 'START: ' . $site_id . ' PERIOD: ' . $period );

    $this->period = $period;
    $this->site_id = $site_id;

    $cabUpper = ($site_id == 's1') ? 'RU' : 'BY';
    $cabLower = ($site_id == 's1') ? 'ru' : 'by';
    $this->pagesLimit = ($site_id == 's1') ? 12 : 2;
    $this->optionUpdate = "MOYSKLAD_PROFIT_{$cabUpper}_{$period}";
    $this->table = "ms_profit_{$cabLower}_{$period}";

    // var_dump($this->table);
    // var_dump($this->optionUpdate);
    // var_dump($this->site_id);
    // var_dump($this->period);
    //
    // die;

    if ( !in_array( $period, [6, 12] ) ) {
      $this->writeStatus( 'ERROR|Задан недопустимый период' );
      return;
    }
    if ( !in_array( $site_id, ['s1', 's2'] ) ) {
      $this->writeStatus( 'ERROR|Задан неверный кабинет' );
      return;
    }

    $this->ms = new MoySkladAPI( $this->site_id );
    $this->db = new DBPanel;
    var_dump( 'PARAMS SETTED' );
  }

  public function run():void
  {
    $this->getProfit();
    $this->writeData();
  }

  private function getProfit():void
  {
    $ms = new MoySkladAPI( $this->site_id );

    $arFilter = [
      'momentFrom' => date('Y-m-d' , strtotime("- {$this->period} months")),
      'momentTo' => date('Y-m-d')
    ];

    $ms->getListProfitByAgentCustom( $i, false, $arFilter );

    foreach ( $ms->MSPosition as $item ){
      if ( empty($item['ARTICLE']) ) continue;
      // if ( isset( $this->items[$item['ARTILCE']] ) ) continue;
      $this->items[] = [
        'model' => $item['ARTICLE'],
        'sellQuantity' => $item['SELLSUM'],
        'quantity' => $item['COUNT'],
      ];
    }

    var_dump('GOT ITEMS: ' . count($this->items) );
    // file_put_contents(
    //   '/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/sites/profitMS.log',
    //   print_r($this->items, true)
    // );
    sleep(5);
    // die;
  }

  private function writeData():void
  {
    if ( empty( $this->items ) ) {
      $this->writeStatus('ERROR|Нет данных из МС');
      return;
    }

    $strSql = "DELETE FROM {$this->table} WHERE 1=1";
    $this->db->query( $strSql );

    usort($this->items, function($a, $b) {
      return $b['sellQuantity'] <=> $a['sellQuantity'];
    });

    $this->db->insert( $this->table, $this->items );
    var_dump('ADDED ITEMS: ' . count($this->items) );
    $this->writeStatus('SUCCESS|Получено ' . count($this->items) . ' товаров');
  }

  private function writeStatus( string $message ):bool
  {
    CProSet::setOption($this->optionUpdate, $message);
    return true;
  }
}

( new ProfitMS(12, 's1') )->run();
sleep(10);
( new ProfitMS(6, 's1') )->run();
sleep(10);
( new ProfitMS(12, 's2') )->run();
sleep(10);
( new ProfitMS(6, 's2') )->run();


$workers->updateStatus("N");
 ?>
