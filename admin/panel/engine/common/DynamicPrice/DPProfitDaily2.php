<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
require("lib/bootstrap.php");

class DPProfitDaily
{
  private DBPanel $panel;
  private Bitrix\Main\DB\MysqliConnection $main;

  private DataProvider $data;
  private OrderProviderInterface $orders;

  public function __construct( string $marketplace, string $cabinet )
  {
    ValidationService::validateConstruct( $marketplace, $cabinet );
    ConfigProvider::init( $marketplace, $cabinet );
    $this->init();
  }

  private function init():void
  {
    $dbMain = \Bitrix\Main\Application::getConnection();
    $dbPanel = new DBPanel;

    $this->data = new DataProvider(
      items: new ItemsRepository( $dbMain, $dbPanel ),
      prices: new PricesRepository( $dbMain, $dbPanel ),
      settings: new SettingsRepository( $dbMain, $dbPanel )
    );
    $this->orders = Loader::getOrderProvider(
      panel: $dbPanel,
      main: $dbMain
    );

    $this->main = $dbMain;
    $this->panel = $dbPanel;
  }

  public function run():void
  {
    $items = $this->data->getItems();
    $this->orders->getOrdersData(
      items: $items,
      minDate: date('Y-m-d', strtotime('- 1 day')),
      maxDate: date('Y-m-d'),
    );

    $this->setProfit( $items );

    foreach ( $items as $model => $item ){
      var_dump( "{$model} - {$item['profit']}" );
    }
    var_dump( $items['MTP-V001D-1B'] );
  }

  private function setProfit( array &$items ):void
  {
    foreach ( $items as $model => $item ){
      $items[$model]['profit'] = $this->calculateProfit( $item['orders'] );
    }
  }

  private function calculateProfit( array $orders ):float
  {
    if ( empty($orders) ) return 0;
    $profit = 0;

    foreach ( $orders as $order ){
      $profit += $order['quantity'] * ($order['price'] * 0.43) - $order['quantity'] * $order['cost'];
    }

    return round($profit, 2);
  }
}

$obj = new DPProfitDaily( $argv[1], $argv[2] );
$obj->run();
?>
