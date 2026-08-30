<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
$workers = new WorkersChecker("panel_engine_yandex_analytics_getTopMS_php");
if (!$workers->checkStatus()) {
	exit;
}
$workers->updateStatus("Y");


class TopProfitYandex
{
  public function __construct()
  {}

  public function run():void
  {
    CModule::IncludeModule('panel.manager');

    $ms = new MoyskladAPI('s1');
    $panel = new DBPanel;

    $items = $this->getItems( $ms );
    $this->save( $panel, $items );
  }

  private function getItems( MoyskladAPI $api ):array
  {
    $api->getListProfitByAgent(0, false, [
      'agent'=> 'a22b6df1-e19c-11eb-0a80-04a6000ec37b',
      'momentFrom' => date('Y-m-d', strtotime('- 1 year'))
    ]);
    $positions = $api->MSPosition;

    usort($positions, function($a, $b) {
      return $b['COUNT'] <=> $a['COUNT'];
    });

    $positions = array_map(fn($el) => ['model' => $el['ARTICLE'], 'quantity' => $el['COUNT'], 'date' => date('Y-m-d G:i:s')], $positions);

    return array_slice( $positions, 0, 150 );
  }

  private function save( DBPanel $panel, array $items ):void
  {
    if ( empty($items) ) throw new InvalidArgumentException("items cannot be empty");

    $panel->query( "DELETE FROM yandex_top_models WHERE 1=1" );
    $panel->insert( 'yandex_top_models', $items );
  }
}

(new TopProfitYandex)->run();
?>
