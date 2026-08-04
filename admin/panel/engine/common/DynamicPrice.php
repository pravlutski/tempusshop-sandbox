<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

class DynamicPrice{

  private ?DBPanel $dbPanel;
  private object $dbMain;

  private string $settingsTable = 'ozon_dp_settings';
  private string $defaultSettingsTable = 'ozon_dp_defaults';
  private string $coefficientsTable = 'ozon_dp_coefficients';
  private string $finalPriceTable = 'ozon_dp_prices';
  private string $historyTable = 'ozon_dp_history';

  private string $ordersTable = 'wdhs_ozon_orders';
  private string $ordersProductsTable = 'wdhs_ozon_order_products';

  private string $fboCostTable = "ozon_fbo_sebes_IP";
  private string $fboPriceTable = "ozon_fbo_price_IP";
  private string $settingsModuleTable = "ozon_main_settings_IP";

  private string $priceTableFilter = 'active_os';
  private string $priceProperty = "PROPERTY_OZSB_PRICE";

  private array $defaultSettings;
  private array $coefficientSettings;

  private array $items;
  private array $priceData;
  private string $date;
  private string $checkHour;

  private string $logPath;
  private string $debug;

  public function __construct( string $marketplace, string $cabinet, bool $debug )
  {
    // var_dump($marketplace);
    // var_dump($cabinet);
    $this->validateConstruct(
      mp: $marketplace,
      cab: $cabinet
    );

    $this->cabinet = $cabinet;
    $this->marketplace = $marketplace;

    $date = date('Y_m_d');
    $filename = "{$this->cabinet}_$date";

    $this->logPath = "/var/www/bitrix_logs/dynamicPriceSettings/{$this->marketplace}/{$filename}.log";

    $this->debug = ($debug == 'true') ? true : false;

    $this->setTime();
    $this->init();

  }

  private function init():void
  {
    CModule::IncludeModule('panel.manager');
    $this->dbPanel = new DBPanel;
    $this->dbMain = \Bitrix\Main\Application::getConnection();
    $this->loadUtilitySettings();
  }

  private function setTime():void
  {
    if ( date('G') == 0 ){
      $this->date = date( 'Y-m-d 00:00:00', strtotime('- 1 day') );
    } else {
      $this->date = date( 'Y-m-d 00:00:00' );
      $this->checkHour = date('G');
    }
  }

  public function run():void
  {
    $this->writeLog('START');
    $this->getfinalPriceData();
    $this->getItems();
    $this->calculatePrice();
    $this->writeLog('END');
    $this->writeLog('####################################');
    $this->writeLog('####################################');
  }

  private function getItems():void
  {
    $rows = $this->dbPanel->select(['*'], $this->settingsTable )->where('cabinet', $this->cabinet)->make();
    $items = [];

    foreach ( $rows as $row ){
      $items[ $row['model'] ] = $row;
    }
    $this->writeLog("Got " . count($items ?? []) . " items");

    $this->calculateItemsIntervals( $items );
    $this->checkTimestamps( $items );
    if ( count($items ?? []) == 0 ){
      $this->writeLog("All items is up to date");
      exit( "All items is up to date\n" );
    }

    $this->getItemsStartPrice( $items );
    $this->writeLog("Got start prices");
    $this->getItemsCost( $items );
    $this->writeLog("Got cost prices");
    // $this->getOrdersCount( $items );
    $this->getOrdersCountByPeriod( $items );
    $this->writeLog("Got orders count");
    $this->getInstalledStep( $items );
    $this->writeLog("Got installed steps");
    $this->getCommission( $items );
    $this->writeLog("Got commissions");

    $this->items = $items;

  }

  private function getfinalPriceData():void
  {
    $rows = $this->dbPanel->select(['*'], $this->finalPriceTable)->where('cabinet', $this->cabinet)->make();
    $result = [];

    foreach ( $rows as $row ){
      $result[ $row['model'] ] = $row;
    }

    $this->priceData = $result;
  }

  private function getItemsStartPrice( array &$items ):void
  {
    if ( empty($items) ){
      throw new Exception('Cannot get start price: items array is empty');
    }

    $models = array_keys($items);

    $arFilter = [
      'IBLOCK_ID' => 16,
      'PROPERTY_CML2_ARTICLE' => $models,
    ];
    $arSelect = [ "IBLOCK_ID", "ID", "PROPERTY_CML2_ARTICLE", "{$this->priceProperty}" ];

    $res = CIBlockElement::GetList( [], $arFilter, false, false, $arSelect );

    $fboPrice = $this->getFboPrice();

    while ( $row = $res->GetNext() ){

      $model = $row["PROPERTY_CML2_ARTICLE_VALUE"];
      $price = $row["{$this->priceProperty}_VALUE"];

      $items[ $model ]['startPrice'] = floatval( $fboPrice[$model] ?? $price );
    }
  }

  private function calculateItemsIntervals( array &$items ):void
  {
    $result = [];
    $now = date('Y-m-d H:00:00');

    foreach ( $items as $model => $item ){
      $lastRunDate = $this->priceData[$model]['date'] ?? false;
      $gap = $this->calculateTimeGap( $item['goal'] );

      if ( !$lastRunDate ){
        $lastRunDate = date( 'Y-m-d H:00:00', strtotime("{$now} - {$gap} hour") );
        $nextRunDate = $now;
      }else{
        $nextRunDate = date( "Y-m-d H:00:00", strtotime("{$lastRunDate} + {$gap} hour") );
      }

      $result = [
        'lastRunDate' => $lastRunDate,
        'nextRunDate' => $nextRunDate,
      ];
      $items[$model]['intervals'] = $result;
    }
  }

  private function calculateTimeGap( int $goal ):int
  {
    if  ( $goal >= 24 ){
      return 1;
    }
    return intval( round( 1 / ($goal / 24) ) );
  }

  private function getOrdersCount( array &$items ):void
  {
    if ( empty($items) ){
      throw new Exception('Cannot get orders: items array is empty');
    }

    $models = array_keys( $items );
    $modelsStr = $this->prepareModelsFilter( $models );

    $strSql = "SELECT op.vendor_code as model, o.in_process_at as date
      FROM `{$this->ordersProductsTable}` AS op
      JOIN `{$this->ordersTable}` AS o
      ON o.posting_number = op.posting_number
      WHERE o.in_process_at > '{$this->date}' AND op.vendor_code IN ({$modelsStr})";

    $res = $this->dbMain->Query( $strSql );

    foreach ( $models as $model ){
      $items[$model]['ordersCount'] = 0;
    }

    while ( $row = $res->Fetch() ){
      $items[ $row['model'] ]['ordersCount'] += 1;
    }
  }

  private function getOrdersCountByPeriod( array &$items ):array
  {
    $arRunDates = [];
    foreach ( $items as $model => $item ){
      $arRunDates[] = strtotime( $item['intervals']["lastRunDate"] );
      $arRunDatesInfo[ $model ] = $item['intervals']["lastRunDate"];
    }

    $modelsFormatted = array_map(
      function($item){
        return "'".$item."'";
      },
      array_keys( $items )
    );

    $modelsStr = implode( ',', $modelsFormatted );
    $minDate = date( 'Y-m-d G:00:00', min($arRunDates) );

    $strSql = "SELECT op.vendor_code as model, o.in_process_at as date
      FROM `wdhs_ozon_order_products` AS op
      JOIN `wdhs_ozon_orders` AS o
      ON o.posting_number = op.posting_number
      WHERE o.in_process_at > '{$minDate}' AND op.vendor_code IN ({$modelsStr})";


    $result = $this->dbMain->Query( $strSql );
    $ordersData = [];

    foreach ( $items as $item ){
      $ordersData[ $item['model'] ] = 0;
    }

    $now = time();
    while ( $row = $result->Fetch() ){
      $inProcessAt = strtotime( $row['date'] );
      $lastRunDate = strtotime($arRunDatesInfo[ $row['model'] ]);

      if ( $lastRunDate > $inProcessAt ) continue;

      $ordersData[ $row['model'] ] += 1;
    }

    foreach ( $items as $model => $item ){
      $items[$model]['ordersCount'] = $ordersData[$model] ?? 0;
    }

    return $ordersData;
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

    $data = $items;

    while ( $row = $rows->Fetch() ){
      $data[ $row['model'] ]['priceData'][] = [
        'price' => $row['price'],
        'count' => $row['count'],
      ];
    }

    foreach ( $data as $model => $arModel ){
      $items[$model]['cost'] = $this->getMinCost(
        model: $model,
        data: $arModel['priceData'] ?? [],
        fbo: $fboCost,
        reserved: $reserved
      );
    }
  }

  private function getInstalledStep( array &$items ):void
  {
    if ( empty($items) ){
      throw new Exception('Cannot get installed step: items array is empty');
    }

    foreach ( $this->priceData as $model => $row ){
      if ( $row['action'] == 'up' ){
        $data[ $model ] = intval( $row['perc'] );
        continue;
      }
      if ( $row['action'] == 'down' ){
        $data[ $model ] = intval( $row['perc'] / -1 );
        continue;
      }
      $data[ $model ] = 0;
    }

    foreach ( $data as $model => $val ){
      $items[ $model ]["installed_step"] = $val;
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

  private function checkTimestamps( array &$items ):void
  {
    if ( date('G') == 0 ){
      $this->writeLog("Hour is equal to zero. All will be updated");
      return;
    }

    foreach( $items as $model => $item ){
      $nextRunDate = $item['intervals']['nextRunDate'];

      if ( strtotime(date('Y-m-d H:00:00')) < strtotime($nextRunDate) ){
        $this->writeLog("{$model} - excluded. Next run: {$nextRunDate}");
        unset( $items[$model] );
        continue;
      }
    }
  }

  private function calculatePrice():void
  {
    $importData = [];

    $def_mpp = $this->defaultSettings['min_profit_perc'];
    $def_mpr = $this->defaultSettings['min_profit_rub'];
    $def_s = $this->defaultSettings['step'];
    $threshold = $this->defaultSettings['threshold'] / 100;

    $update = [];
    $delete = [];

    $this->writeLog("DEFAULT SETTINGS:");
    $this->writeLog( $this->defaultSettings );

    foreach( $this->items as $model => $item ){

      if ( empty($item['cost']) ){
        $this->writeLog("{$model} - has zero cost value. Excluded");
        continue;
      }

      $this->writeLog("ITEM {$model}: ");
      $this->writeLog( $item );

      $item['min_profit_perc'] = $item['min_profit_perc'] ?? $def_mpp;
      $item['min_profit_rub'] = $item['min_profit_rub'] ?? $def_mpr;

      $startPrice = $item['startPrice'];
      $ordersCount = intval($item['ordersCount']);
      // $goalCount = round( $this->getGoalCount($item['goal']) );
      // Расчет плана на период с последнего запуска к настоящему моменту
      $lastHour = date( 'G', strtotime($item['intervals']['lastRunDate']));
      $goalCount = $this->calcualteGoalForPeriodPerModel( $item['goal'], $lastHour );

      $thresholdFlag = true;
      if ( $goalCount == 0 && $ordersCount > 0 ){
        $diff = 1;
      }else if( $goalCount == 0 && $ordersCount == 0 ){
        $diff = 0;
      }else{
        $diff = $ordersCount / $goalCount;
        if ( $diff >= 1 ){
          $diff = $diff - 1;
        } else{
          $diff = 1 - $diff;
        }
      }


      $this->writeLog("ITEM ORDERS GOAL: {$goalCount}");
      $this->writeLog("ITEM ORDERS DIFFECRENCE: ~" . round($diff * 100, 2) . "%");


      if ( $diff < $threshold ){
        $this->writeLog("Difference had not reached the threshold ({$diff}). Item will not be updated");
        $thresholdFlag = false;
      }

      if ( $ordersCount < $goalCount && $thresholdFlag ) {
        $s = ($item['installed_step'] ?? 0) - ($item['step'] ?? $def_s);
      }

      if ( $ordersCount > $goalCount && $thresholdFlag ) {
        $s = ($item['installed_step'] ?? 0) + ($item['step'] ?? $def_s);
      }

      if ( $s > 0  && $thresholdFlag ){
        $finalPrice = $startPrice * (1 + abs($s) / 100 );
        $action = 'up';
      } else if ( $s < 0 && $thresholdFlag ){
        $finalPrice = $startPrice * (1 - abs($s) / 100 );
        $action = 'down';
      } else if ( abs($s) == 0  && $thresholdFlag ){
        $finalPrice = $startPrice;
        $action = 'none';
      } else{
        $s = $this->priceData[$model]['perc'] ?? 0;
        $finalPrice = $this->priceData[$model]['price'] ?? $startPrice;
        $action = $this->priceData[$model]['action'] ?? 'none';
      }
      try{
        $profit = $this->checkProfit( $item, $finalPrice );
      }catch ( Throwable $e ){
        var_dump( $item['model'] );
        var_dump($startPrice);
        var_dump($finalPrice);
        die;
      }

      if ( $profit['status'] != 'ok' ){
        $this->writeLog( "{$model} - {$profit['status']}. Was not updated. Profit recalculated" );
        $finalPrice = $this->priceData[ $model ]["price"] ?? $startPrice;
        $s = $this->priceData[ $model ]["perc"];
        $cap = $profit['cap'];
        $profit = $this->checkProfit( $item, $finalPrice );
      }

      $update[] = [
        'model' => $model,
        'action' => $action,
        'perc' => abs( $s ),
        'startPrice' => $startPrice,
        'price' => round($finalPrice),
        'cost' => $item['cost'],
        'profit_rub' => $profit['rub'],
        'profit_perc' => $profit['perc'],
        'profit_cap' => $cap ?? $profit['cap'],
        'cabinet' => $this->cabinet,
        'date' => date('Y-m-d G:i:s'),
      ];
      $this->writeLog('####################################');
    }

    $this->writeLog( "UPDATE ARRAY: " );
    $this->writeLog( $update );
    $this->saveData( $update );
    $this->saveHistory( $update );
  }

  private function saveData( array $update ):void
  {
    if ( empty($update) ){
      $this->writeLog("Update array is empty");
      die("Update array is empty. Check logs\n");
    }
    foreach ( $update as $elem ){
      $strSql = "DELETE FROM {$this->finalPriceTable} WHERE model = '{$elem['model']}'";
      $this->dbPanel->query( $strSql );
    }

    $this->dbPanel->insert( $this->finalPriceTable, $update );
  }

  private function saveHistory( array $update ):void
  {
    if ( empty($update) ){
      $this->writeLog("Update array is empty");
      die("Update array is empty. Check logs\n");
    }
    $insert = [];

    foreach ( $update as $elem ){
      $perc = $elem['perc'];
      if ( $elem['action'] == 'down' ) $perc = $perc * -1;
      $insert[] = [
        'model' => $elem['model'],
        'perc' => $perc,
        'cabinet' => $this->cabinet,
        'date' => date('Y-m-d G:i:s')
      ];
    }

    $this->dbPanel->insert( $this->historyTable, $insert );
  }

  private function writeLog( mixed $message ):void
  {
    if ( $this->debug ) {
      print_r( $message );
      print_r( PHP_EOL );
    }
    file_put_contents(
      $this->logPath,
      date('Y-m-d G:i:s'). ' --- ' . print_r($message, true) . PHP_EOL,
      FILE_APPEND
    );
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

  private function getGoalCount( int $goal ):float
  {
    // m - multiplier (суммарный коэффициент к текущему часу)
    // с - coefficient (почасовой коэффициент)
    // h - hour (текущий час)

    $coefs = [];
    foreach ( $this->coefficients as $h => $c ){
      $coefs[$h] = $c;
    }

    $m = [];
    for ( $i = 1; $i <= 24; $i++ ){
      if ( $i - 1 == 0 ){
        $m[$i] = $coefs[$i];
        continue;
      }
      $m[$i] = round($m[ $i - 1 ] + $coefs[$i], 2);
    }

    return $goal * round( $m[date('G')], 3 );
  }

  private function calcualteGoalForPeriodPerModel( int $goal, int|bool $hour ):int|bool
  {
    if ( $hour === false ) return $hour;

    $coefs = $this->coefficients;

    $currentHour = date('G');

    $m = [];

    for ( $i = 1; $i <= 24; $i++ ){
      if ( $i - 1 == 0 ){
        $m[$i] = $coefs[$i];
        continue;
      }
      $m[$i] = round($m[ $i - 1 ] + $coefs[$i], 2);
    }

    $sumT = 0;
    for ( $i = 0; $i <= $currentHour; $i++ ){
      $sumT += $goal * $coefs[$i];
    }

    if ( $hour > $currentHour ){
      $sumY = 0;
      for ( $i = $hour; $i < 24; $i++){
        $sumY += $goal * $coefs[$i];
      }

      $goalPeriod = $sumY + $sumT;
    }else{
      $sumL = 0;
      for ( $i = $hour; $i <= $currentHour; $i++ ){
        $sumL += $goal * $coefs[$i];
      }

      $goalPeriod = $sumL;
    }

    return round( $goalPeriod );
  }

  private function getMinCost( string $model, array $data, array $fbo, array $reserved ):float
  {
    if ( isset($fbo[$model]) ){
      return floatval( $fbo[$model] );
    }
    if ( empty($data) ) return 0;

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

  private function getFboCost():array
  {
    $rows = $this->dbPanel->select(['*'], $this->fboCostTable)->make();
    $result = [];

    foreach ( $rows as $row ){
      $result[ $row['model'] ] = $row['sebes'];
    }

    return $result;
  }

  private function getFboPrice():array
  {
    $rows = $this->dbPanel->select(['*'], $this->fboPriceTable)->make();
    $result = [];

    foreach ( $rows as $row ){
      $result[ $row['article'] ] = $row['price'];
    }

    return $result;
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

  private function loadUtilitySettings():void
  {
    $rows = $this->dbPanel->select(['*'], $this->defaultSettingsTable)->where('cabinet', $this->cabinet)->make()[0];
    foreach ( $rows as $name => $row ){
      $this->defaultSettings[$name] = $row;
    }

    $rows = $this->dbPanel->select(['*'], $this->coefficientsTable)->make();
    foreach ( $rows as $name => $row ){
      if ( $row['hour'] == 24 ) $row['hour'] = 0;
      $this->coefficients[ $row['hour'] ] = $row['coefficient'];
    }
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

$dp = new DynamicPrice(
  marketplace: $argv[1],
  cabinet: $argv[2],
  debug: $argv[3] ?? false,
);
$dp->run();
 ?>
