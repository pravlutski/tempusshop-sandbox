<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);
ob_implicit_flush(true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
$workers = new WorkersChecker("wb_classes_control_ItemsControl_php_WR");
if (!$workers->checkStatus()) {
	exit;
}
$workers->updateStatus("Y");

require("ControlApiManager.php");
require("ControlDataProvider.php");
require("ControlCommunicationService.php");
require("IncompleteDataException.php");
require("{$_SERVER['DOCUMENT_ROOT']}/admin/panel/engine/wb/classes/StocksDataProvider.php");

class ItemsControl
{
  private ?ControlApiManager $api = null;
  private ?ControlDataProvider $data = null;

  public function __construct( string $cabinet )
  {
    if ( !in_array($cabinet, ['WR', 'IP']) ){
      throw new InvalidArgumentException("Cabinet '{$cabinet}' is not supported");
    }

    $this->api = new ControlApiManager($cabinet);

    $this->data = new ControlDataProvider(
      main: \Bitrix\Main\Application::getConnection(),
      panel: new DBPanel,
      cabinet: $cabinet
    );

    ControlCommunicationService::init(
      cabinet: $cabinet,
      panel: new DBPanel,
      module: "control_$cabinet"
    );

    $this->cabinet = $cabinet;
  }

  public function run():void
  {
    // die;
    ControlCommunicationService::updateStatus(
      text: 'Начало работы',
      perc: '10',
      status: 'PROCESS',
      start: date('Y.m.d G:i:s'),
      end: false,
    );
    $dictionary = $this->data->getDictionary();
    $top = $this->data->getTopModels( $dictionary );

    ControlCommunicationService::updateStatus( text: 'Получение данных с WB', perc: '40' );

    try{
      $inSale = $this->api->getItemsWB( maxPage: 45 );
      var_dump( "InSale count: " . count($inSale ?? []) );
    } catch ( IncompleteDataException $e ){
      ControlCommunicationService::updateStatus(
        text: $e->getMessage(),
        perc: '100',
        status: "COMPLETED"
      );
      die( $e->getMessage() . PHP_EOL );
    }

    $checkArray = [
      'inSale' => $inSale,
      'priceData' => $this->data->getPriceData( $dictionary ),
      'quarantine' => $this->data->getQuarantineData( $dictionary ),
      'costs' => $this->data->getItemsCost( $dictionary ),
      'excluded' => $this->data->getExcludedModels( $dictionary ),
      'reserved' => $this->data->getFullReserved( $dictionary ),
      'fbo' => $this->data->getFboData( $dictionary ),
    ];

    ControlCommunicationService::updateStatus( text: 'Фильтрация данных', perc: '70' );

    $result = $this->filterItems(
      check: $checkArray,
      data: $top,
      rules: $this->data->getCabinetPriceRules()
    );

    $this->displayResult( $result, $top );
    $items = $this->data->enrichWithContext( $result['remaining'] );

    ControlCommunicationService::save( $items );
    ControlCommunicationService::updateStatus(
      text: 'Завершено',
      perc: '100',
      status: 'COMPLETED',
      start: false,
      end: date('Y.m.d G:i:s'),
    );
  }

  private function filterItems( array $check, array $data, array $rules ):array
  {
    $debug = [];
    $remainingData = $data;

    $reasons = [
      'inSale' => "inSale",
      'priceData' => 'outOfStock',
      'quarantine' => 'quarantine',
      'costs' => 'costs',
      'excluded' => 'excluded',
      'reserved' => 'reserved',
      'fbo' => 'fboOnly',
    ];

    $functions = [
      'inSale' => ( fn($val) => isset($val) ),
      'priceData' => ( fn($val) => !isset($val) ),
      'quarantine' => ( fn($val) => isset($val) ),
      'costs' => ( fn($val) => $this->checkCost($val ?? false, $rules) ),
      'excluded' => ( fn($val) => isset($val) ),
      'reserved' => ( fn($val) => isset($val) ),
      'fbo' => ( fn($val) => isset($val) ),
    ];

    foreach ( $check as $type => $array ) {
      $fn = $functions[$type];
      $removed = [];
      $kept = [];

      foreach ($remainingData as $item) {
        if ( $fn($array[$item]) ) {
          $removed[] = $item;
          continue;
        }
        $kept[] = $item;
      }

      $debug[ $reasons[$type] ] = $removed;
      $remainingData = $kept;
    }

    return [
      'removed' => $debug,
      'remaining' => $remainingData
    ];
  }

  private function checkCost( bool|float $cost, array $rules ):bool
  {
    if ( !$cost ) return false;
    return $rules['min'] > $cost || $cost > $rules['max'];
  }

  private function displayResult( array $result, array $top ):void
  {
    $inSaleCount = count( $result['removed']['inSale'] ?? [] );
    $outOfStockCount = count( $result['removed']['outOfStock'] ?? [] );
    $quarantineCount = count( $result['removed']['quarantine'] ?? [] );
    $allItemsCount = count( $top ?? [] );
    $remainingCount = count( $result['remaining'] ?? [] );
    $inAllowedCount = count( $result['removed']['costs'] ?? [] );

    $stat = [
      "In sale: {$inSaleCount}/{$allItemsCount}",
      "Out of Stock: {$outOfStockCount}",
      "Quarantine: {$quarantineCount}",
      "Remaining Items: {$remainingCount}",
      "Inallowed cost: {$inAllowedCount}"
    ];

    print_r( implode(PHP_EOL, $stat) . PHP_EOL );
  }

}

$obj = new ItemsControl( $argv[1] );
$obj->run();
$workers->updateStatus("N");
 ?>
