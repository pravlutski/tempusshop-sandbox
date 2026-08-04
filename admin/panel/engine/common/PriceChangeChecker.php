<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

class PriceChangeChecker
{
  private array $priceData;
  private array $defaultSettings;
  private array $update;

  private string $priceTable = "ozon_dp_prices";
  private string $defaultSettingsTable = "ozon_dp_defaults";
  private string $settingsTable = "ozon_dp_settings";
  private string $historyTable = "ozon_dp_history";

  private string $fboPriceTable = "ozon_fbo_price_IP";
  private string $fboCostTable = "ozon_fbo_sebes_IP";

  private string $settingsModuleTable = "ozon_main_settings_IP";

  private string $priceProperty = "OZSB_PRICE";
  private string $priceTableFilter = "active_os";
  private string $logPath;

  private float $threshold = 0.03;

  public function __construct( string $marketplace, string $cabinet, string $debug )
  {
	  $cabinet = str_replace(("'", '"'), "", $cabinet);
    $this->validateConstruct(
      mp: $marketplace,
      cab: $cabinet
    );

    $this->cabinet = $cabinet;

    $this->init();
  }

  private function init():void
  {
    CModule::IncludeModule('panel.manager');
    $this->dbPanel = new DBPanel;
    $this->dbMain = \Bitrix\Main\Application::getConnection();

    $this->logPath = "/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/common/logs/price_settings/{$this->marketplace}/{$this->cabinet}_PC_" . date('Y_m_d') . ".log";

    $this->loadUtilitySettings();
  }

  public function run():void
  {
    $this->writeLog("START");
    $this->getPriceTableData();
    $this->getActualItemPrices();
    $this->comparePrices();
    $this->updateTable();
    $this->saveHistory();
    $this->writeLog("END");
  }

  private function getPriceTableData():void
  {
    $rows = $this->dbPanel->select(['*'], $this->priceTable)->where('cabinet', $this->cabinet)->make();
    $result = [];

    foreach ( $rows as $row ){
      $result[$row['model']] = $row;
    }

    $this->getItemsCost( $result );
    $this->getItemsSettings( $result );
    $this->getCommission( $result );

    $this->priceData = $result;
  }

  private function getFboPrices():array
  {
    $rows = $this->dbPanel->select(['*'], $this->fboPriceTable)->make();
    $result = [];

    foreach ( $rows as $row ){
      $result[ $row['article'] ] = $row['price'];
    }

    return $result;
  }

  private function getActualItemPrices():void
  {
    if ( empty($this->priceData) ) throw new Exception("Price table is empty");
    $fboPrices = $this->getFboPrices();

    $arFilter = [
      "IBLOCK_ID" => 16,
      "PROPERTY_CML2_ARTICLE" => array_keys( $this->priceData )
    ];

    $arSelect = ["IBLOCK_ID", "ID", "PROPERTY_CML2_ARTICLE", "PROPERTY_{$this->priceProperty}"];
    $result = CIBlockElement::GetList( [], $arFilter, false, false, $arSelect );

    while ( $row = $result->GetNext() ){
      $model = $row["PROPERTY_CML2_ARTICLE_VALUE"];
      $price = $row["PROPERTY_{$this->priceProperty}_VALUE"];
      $this->priceData[$model]['actualPrice'] = $fboPrices[$model] ?? $price;
    }
  }

  private function comparePrices():void
  {
    if ( empty($this->priceData) ) throw new Exception("Price table is empty");

    $update = [];

    foreach ( $this->priceData as $model => $data ){
      $this->writeLog("ITEM: {$model}");
      if ( empty($data['cost']) ){
        $this->writeLog("{$model} has no cost value. Excluded");
        $this->writeLog("##########################");
        continue;
      }
      if ( $data['startPrice'] == $data['actualPrice'] ) {
        $this->writeLog( "Start price ({$data['startPrice']}) is equal to actual price ({$data['actualPrice']}). No need to update" );
        $this->writeLog("##########################");
        continue;
      }
      $diff = $data['startPrice'] / $data['actualPrice'];
      $diffPerc = round( ($diff > 1) ? ($diff - 1) :  1 - $diff, 2);

      if ( $diffPerc > $this->threshold ){
        $this->writeLog( "Old price: {$data['startPrice']}" );
        $this->writeLog( "Actual price: {$data['actualPrice']}" );
        $this->writeLog( "Price difference ({$diffPerc}) is greater than threshold ({$this->threshold}). Changes will be applied" );
        $this->writeLog("##########################");
        $profit = $this->checkProfit(
          item: $data,
          finalPrice: $data['actualPrice']
        );
        $update[] = [
          "model" => $model,
          "startPrice" => $data['actualPrice'],
          "price" => $data['actualPrice'],
          "cost" => $data["cost"],
          "action" => "none",
          "profit_rub" => $profit['rub'],
          "profit_perc" => $profit['perc'],
          "profit_cap" => $profit['cap'],
          "perc" => 0,
          "cabinet" => $this->cabinet,
        ];
        continue;
      }
      $this->writeLog( "Old price: {$data['startPrice']}" );
      $this->writeLog( "Actual price: {$data['actualPrice']}" );
      $this->writeLog( "price difference ({$diffPerc}) is lower than threshold ({$this->threshold}). No need to update" );
      $this->writeLog("##########################");
    }


    $this->update = $update;
  }

  private function updateTable():void
  {
    if ( empty($this->update) ){
      $this->writeLog("Nothing to update");
      die;
    }
    foreach ( $this->update as $e ){
      $strSql = "UPDATE {$this->priceTable} SET
        startPrice = '{$e['startPrice']}',
        price = '{$e['price']}',
        cost = '{$e['cost']}',
        action = '{$e['action']}',
        profit_rub = '{$e['profit_rub']}',
        profit_perc = '{$e['profit_perc']}',
        profit_cap = '{$e['profit_cap']}',
        perc = '{$e['perc']}'v
      WHERE model = '{$e['model']}' AND cabinet = '{$e['cabinet']}'";

      $this->dbPanel->query( $strSql );
      $this->writeLog("{$e['model']} updated");
    }
  }

  private function saveHistory():void
  {
    $insert = [];

    foreach ( $this->update as $e ){
      $insert[] = [
        'model' => $e['model'],
        'perc' => $perc,
        'cabinet' => $this->cabinet,
        'date' => date('Y-m-d G:i:s')
      ];
    }

    $this->dbPanel->insert( $this->historyTable, $insert );
  }

  private function loadUtilitySettings():void
  {
    $rows = $this->dbPanel->select(['*'], $this->defaultSettingsTable)->where('cabinet', $this->cabinet)->make()[0];
    foreach ( $rows as $name => $row ){
      $this->defaultSettings[$name] = $row;
    }
  }

  private function getCommission( array &$items ):void
  {
    if ( empty($items) ){
      throw new Exception('Cannot get installed step: items array is empty');
    }

    $rows = $this->dbPanel->select(['*'], $this->settingsModuleTable)->make();

    foreach ( $rows as $row ){
      $settings[ $row['name'] ] = $row['value'];
    }

    foreach ( $items as $model => &$item ){
      $item['commission'] = floatval($settings['com']);
    }
  }

  private function getItemsCost( array &$items ):void
  {
    if ( empty($items) ){
      throw new Exception('Cannot get orders: items array is empty');
    }

    $models = array_keys( $items );
    $modelsStr = $this->prepareModelsFilter( $models );
    $reserved = $this->getReservedItems();
    $fboCost = $this->getFboCost();

    $strSql = "SELECT model, price, count FROM ci_price WHERE {$this->priceTableFilter} = 'Y' AND model IN ({$modelsStr})";
    $rows = $this->dbMain->Query( $strSql );

    $data = [];

    while ( $row = $rows->Fetch() ){
      $data[ $row['model'] ][] = [
        'price' => $row['price'],
        'count' => $row['count'],
      ];
    }

    foreach ( $data as $model => $priceData ){
      $items[$model]['cost'] = $this->getMinCost(
        model: $model,
        data: $priceData,
        fbo: $fboCost,
        reserved: $reserved
      );
    }
  }

  private function getMinCost( string $model, array $data, array $fbo, array $reserved ):float
  {
    if ( isset($fbo[$model]) ){
      return floatval( $fbo[$model] );
    }

    usort($data, function($a, $b) {
      return $a['price'] <=> $b['price'];
    });

    $result = 0;
    $itemReserved = $reserved[$model] ?? 0;

    foreach ( $data as $priceData ){
      if ( $priceData['count'] - $itemReserved <= 0 ){
        $itemReserved = abs($priceData['count'] - $itemReserved);
        continue;
      }

      $result = floatval( $priceData['price'] );
      break;
    }

    return $result;
  }

  private function getReservedItems():array
  {
    $strSql = "SELECT * FROM ci_reserved";
    $rows = $this->dbMain->Query( $strSql );

    $data = [];
    while ( $row = $rows->Fetch() ){
      $data[ $row['ARTICLE'] ] = $row['RESERVED'];
    }

    return $data;
  }

  private function getItemsSettings( array &$items ):void
  {
    $rows = $this->dbPanel->select(["*"], $this->settingsTable )->where('cabinet', $this->cabinet)->make();
    $settings = [];

    foreach ( $rows as $row ){
      $settings[ $row['model'] ] = $row;
    }

    foreach ( $settings as $model => $row ){
      $items[ $model ]['min_profit_rub'] = $row['min_profit_rub'] ?? $this->defaultSettings["min_profit_rub"];
      $items[ $model ]['min_profit_perc'] = $row['min_profit_perc'] ?? $this->defaultSettings["min_profit_perc"];
    }
  }

  private function getFboCost():array
  {
    $rows = $this->dbPanel->select(['*'], $this->fboCostTable)->make();
    $result = [];

    foreach ( $rows as $row ){
      $result[ $row['model'] ] = $row['sebes'];
    }

    return $result;
  }

  private function checkProfit( array $item, float $finalPrice ):array
  {
    $profit_perc = ( $finalPrice * (1 - $item['commission'] / 100) - $item['cost'] ) / $item['cost'];
    $profit_rub = $finalPrice * (1 - $item['commission'] / 100) - $item['cost'];

    $profit_perc = ( $finalPrice * (1 - $item['commission'] / 100) - $item['cost'] ) / $item['cost'];
    $profit_rub = $finalPrice * (1 - $item['commission'] / 100) - $item['cost'];

    $profit = [
      'perc' => round($profit_perc * 100, 1),
      'rub' => round($profit_rub, 1),
    ];

    if ( $profit_perc * 100 < $item['min_profit_perc'] ){
      $profit['status'] = "Не прошел по мажинальности {$profit['perc']} < {$item['min_profit_perc']}";
      $profit['cap'] = 'Y';
    }
    else if ( $profit_rub < $item['min_profit_rub'] ){
      $profit['status'] = "Не прошел по мажинальности {$profit['rub']} < {$item['min_profit_rub']}";
      $profit['cap'] = 'Y';
    }
    else{
      $profit['status'] = 'ok';
      $profit['cap'] = 'N';
    }

    return $profit;
  }

  private function prepareModelsFilter( array $models ):string
  {
    $models = array_filter($models);
    $modelsFormatted = array_map(function($item){
      return "'".$item."'";
    }, $models);

    $string = implode( ',', $modelsFormatted );

    return $string ?? '';
  }

  private function writeLog( mixed $message ):void
  {
    print_r( $message );
    print_r( PHP_EOL );

    file_put_contents(
     $this->logPath,
     date('Y-m-d G:i:s'). ' --- ' . print_r($message, true) . PHP_EOL,
     FILE_APPEND
    );

  }

  private function validateConstruct( string $mp, string $cab ):void
  {
    if ( empty($mp) || empty($cab) ){
      throw new InvalidArgumentException("One of parameters is missing");
    }
    if ( !in_array($mp, ['OZON', 'WB']) ){
      throw new Exception('Undefined marketplace');
    }
    if ( $mp == 'OZON' && !in_array($cab, ['IP','TI']) ){
      throw new Exception('Undefined cabinet for installed marketplace');
    }
    if ( $mp == 'WB' && !in_array($cab, ['TL','WR', 'WT']) ){
      throw new Exception('Undefined cabinet for installed marketplace');
    }
  }
}

$dp = new PriceChangeChecker(
  marketplace: $argv[1],
  cabinet: $argv[2],
  debug: $argv[3] ?? false,
);
$dp->run();
 ?>
