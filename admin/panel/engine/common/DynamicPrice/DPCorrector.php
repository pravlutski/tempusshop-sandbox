<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
require_once("lib/bootstrap.php");

class DPCorrector
{
  private \Bitrix\Main\DB\MysqliConnection $main;
  private DBPanel $panel;
  private DeviationDataProvider $deviations;
  private array $defaults;

  public function __construct( string $marketplace, string $cabinet )
  {
    ValidationService::validateConstruct( $marketplace, $cabinet );

    ConfigProvider::init( $marketplace, $cabinet );
    ConfigProvider::setLogPathTemplate( "/var/www/bitrix_logs/dynamicPriceSettings/%s/correct/%s.log" );

    $this->init();
  }

  private function init():void
  {
    $panel = new DBPanel;
    $main = \Bitrix\Main\Application::getConnection();

    $this->data = new DataProvider(
      items: new ItemsRepository( $main, $panel ),
      prices: new PricesRepository( $main, $panel ),
      settings: new SettingsRepository( $main, $panel )
    );

    $this->deviations = new DeviationDataProvider( $panel );
    $this->updater = new UpdateManager( $panel, ConfigProvider::getCabinet() );

    $defaults = $this->data->getDefaultSettings();

    $this->main = $main;
    $this->panel = $panel;
    $this->defaults = $defaults;
  }

  public function run():void
  {
    CommunicationService::log("-----------------------------");
    CommunicationService::log("START");

    $items = $this->data->getItems();

    CommunicationService::log( "Got all items list: " . count($items ?? []) );

    $priceData = $this->getFinalPriceData();
    CommunicationService::log( "Got final price data list: " . count($priceData ?? []) );

    $deviations = $this->deviations->get( $items, $priceData );
    CommunicationService::log( "Got deviations list: " . count($deviations ?? []) );

    $rows = $this->correctFinalPrices( $deviations, $items, $priceData );

    file_put_contents( '/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/common/DynamicPrice/correct.txt', print_r($rows, true) );

    CommunicationService::log( "Got deviations list for update: " . count($rows['update'] ?? []) );
    CommunicationService::log( "Got deviations list for delete: " . count($rows['delete'] ?? []) );

    $resUpdate = $this->updater->updatePriceTable( $rows['update'] ?? [] );
    $resDelete = $this->updater->deleteBadItems( $rows['delete'] ?? [] );

    if ( $resUpdate && $resDelete ){
      CommunicationService::log("Items were successfully updated/deleted");
      $this->updater->clearUpdateList();
      CommunicationService::log("Update list is cleared");
      return;
    }

    CommunicationService::log("Something wend wrong. Update list was not affected");
  }

  private function correctFinalPrices( array $deviations, array $items, array $priceData ):array
  {
    $update = [];
    $delete = [];

    foreach ( $deviations as $model => $val ){

      if ( !isset($priceData[$model]) ) {
        CommunicationService::log("{$model} is no in priceData list. Skipped");
        continue;
      }

      $data = $items[ $model ];

      if ( empty($data['cost']) ) {
        CommunicationService::log("{$model} has zero cost. Skipped");
        continue;
      }

      $prices = [
        'start' => $data['startPrice'],
        'desired' => $data['installed']['price'],
        'cost' => $data['cost']
      ];
      $settings = [
        'profit' => $data['min_profit_rub'],
        'margin' => $data['min_profit_perc'],
        'step' => $data['step'],
        'com' => $this->defaults['commission']
      ];

      CommunicationService::log("{$model} will be corrected. Start price: {$prices['start']}. Desired price: {$prices['desired']}. Cost: {$prices['cost']}");

      $row = $this->findAppliablePrice(
        model: $model,
        prices: $prices,
        settings: $settings,
      );

      if ( $row ){
        $update[$model] = $row;
        continue;
      }
      $delete[$model] = true;
    }

    return ['update' => $update, 'delete' => $delete];
  }

  private function findAppliablePrice( string $model, array $prices, array $settings ):?array
  {
    $baseStep = ($prices['start'] > $prices['desired']) ? $settings['step'] * -1 : $settings['step'];

    $spaceship = ($prices['start'] <=> $prices['desired']);

    $baseFin = $this->isMarginApproved( $prices['start'], $prices['cost'], $settings );
    $desiredFin = $this->isMarginApproved( $prices['desired'], $prices['cost'], $settings );

    if ( !$baseFin['status'] && !$desiredFin['status'] ){
      CommunicationService::log("{$model}: Start price does not satisfy business' reqiurements");
      return null;
    }

    $step = $baseStep;
    $isDesiredValid = ( $spaceship == -1 && $desiredFin['status'] );

    $iteration = 0;
    $maxIteration = 100;

    while ( true ){
      if ( $step == 0 ){
        CommunicationService::log("{$model} cannot be corrected with step equal to zero");
        return null;
      }
      if ( $iteration >= $maxIteration ){
        CommunicationService::log("{$model} will not be corrected cause cycle reached iteration limit. Additional info:");
        CommunicationService::log($fin);
        CommunicationService::log($prices);
        CommunicationService::log($settings);
        CommunicationService::log("isDesiredValid: " . $isDesiredValid);
        CommunicationService::log("spaceshipStart: " . $spaceship);
        CommunicationService::log( "last price: " . $price );
        CommunicationService::log( "lastPrice variable: " . $lastPrice );
        return null;
      }
      $iteration++;

      $price = $prices['start'] * ( 1 + $step / 100 );

      $fin = $this->isMarginApproved( $price, $prices['cost'], $settings );

      if ( $isDesiredValid && !$fin['status'] ){
        $step += $baseStep;
        continue;
      }

      if ( !$fin['status'] ) break;

      $isPriceOverDesired = (round($price) <=> $prices['desired']) == ($spaceship * -1);
      if ( $isPriceOverDesired ) break;

      $lastFin = $fin;
      $lastPrice = round($price);
      $lastStep = $step;

      $step += $baseStep;
    }

    $result = [
      'startPrice' => $prices['start'],
      'price' => !empty($lastFin['status']) ? $lastPrice : $prices['start'],
      'profit_rub' => !empty($lastFin['status']) ? $lastFin['profit'] : $baseFin['profit'],
      'profit_perc' => !empty($lastFin['status']) ? $lastFin['margin'] : $baseFin['margin'],
      'cost' => $prices['cost'],
      'perc' => !empty($lastFin['status']) ? abs($lastStep) : 0,
      'action' => ( $step < 0 ) ? 'down' : 'up',
    ];

    return $result;
  }

  private function isMarginApproved( float $price, float $cost, array $settings ):array
  {
    $profit = CalculationService::calculateProfit( $price, $cost, $settings['com'] );
    $margin = CalculationService::calculateMargin( $price, $cost, $settings['com'] );

    $status = ( ($margin * 100) >= $settings['margin'] ) && ( $profit >= $settings['profit'] );

    return [
      'profit' => round( $profit, 2),
      'margin' => round( $margin  * 100, 2),
      'status' => $status,
    ];
  }

  private function getFinalPriceData():array
  {
    $rows = $this->panel->select( ['*'], ConfigProvider::getFinalPriceTable() )->make();
    $result = [];

    foreach ( $rows as $row ){
      $result[ $row['model'] ] = $row;
    }

    return $result;
  }
}

$obj = new DPCorrector( $argv[1], $argv[2] );
$obj->run();

 ?>
