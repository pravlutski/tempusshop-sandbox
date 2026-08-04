<?php
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');


class SettingsManager
{
  private ?DBPanel $dbPanel;
  private object $dbMain;

  private string $cabinet;
  // Конфиги
  private string $settingsTable = 'ozon_dp_settings';
  private string $defaultSettingsTable = 'ozon_dp_defaults';
  private string $coefficientsTable = 'ozon_dp_coefficients';
  private string $historyTable = 'ozon_dp_history';
  private string $pricesTable = 'ozon_dp_prices';

  private string $ordersTable = "wdhs_ozon_orders";
  private string $ci_price_filter = 'active_os';
  private string $fboTable = 'ozon_fbo_sebes_IP';
  private string $settingsModuleTable = "ozon_main_settings_IP";

  private array $allowedCabinets = ['TI', 'IP'];

  public function __construct( $cabinet )
  {
    if ( !in_array($cabinet, $this->allowedCabinets) ){
      $msgCabinets = implode(', ', $this->allowedCabinets);
      throw new \InvalidArgumentException("{$cabinet} is not supported. Allowed cabinets: {$msgCabinets}");
    }

    $this->cabinet = $cabinet;
    $this->init();
  }

  private function init():void
  {
    CModule::IncludeModule('panel.manager');
    // Инициализируем базы
    $this->dbPanel = new DBPanel;
    $this->dbMain = \Bitrix\Main\Application::getConnection();
  }

  public function getListSettings( bool $showYesterdayCharts = false ):array
  {
    $rows = $this->dbPanel->select( ['*'], $this->settingsTable )->where('cabinet', $this->cabinet)->make() ?? [];
    $items = [];
    $models = [];

    foreach ( $rows as $row ){
      $items[ $row['model'] ] = $row;
      $models[] = $row['model'];
    }

    return [
      'items' => $items,
      'orders' => $this->getOrdersCount($models),
      'goals_long' => $this->calculateGoalsLong( $items ),
      'goals_short' => $this->calculateGoalsShort( $items ),
      'ordersByPeriod' => $this->getOrdersCountByPeriod( $items ),
      'goalsForPeriod' => $this->calculateGoalsPeriod( $items ),
      'ordersByHour' => $this->getOrdersCountByHour( $models, $showYesterdayCharts ),
      'statusHistory' => $this->getStatusHistory( $models, $showYesterdayCharts ),
      'unAvailableModels' => $this->checkIfModelsUnavailable( $models ),
      'currentStatusData' => $this->getCurrentStatusData( $items ),
    ];
  }

  private function getCurrentStatusData( array $items = [] ):array
  {
    $rows = $this->dbPanel->select(['*'], $this->pricesTable)->where('cabinet', $this->cabinet)->make();

    $result = [];

    foreach ( $rows as $row ){
      $result[$row['model']] = [
        'finalPrice' => $row['price'],
        'startPrice' => $row['startPrice'],
        'profit_rub' => $row['profit_rub'],
        'profit_perc' => $row['profit_perc'],
        // 'profit_cap' => $row['profit_cap'],
        'cost' => $row['cost'],
        'status' => ($row['action'] == 'up') ? '+' . $row['perc'] : '-' . $row['perc'],
        'date' => $row['date']
      ];
    }

    if ( empty($items) ){
      return $result;
    }

    return $this->checkIfGotProfitCap($result, $items);
  }

  private function checkIfModelsUnavailable( array $models ):array
  {
    if ( empty($models) ) return [];
    $modelsFormatted = array_map(function($item){
      return "'".$item."'";
    }, $models);

    $modelsStr = implode( ',', $modelsFormatted );

    $strSql = "SELECT * FROM ci_price WHERE {$this->ci_price_filter} = 'Y' AND model IN ({$modelsStr})";
    $result = $this->dbMain->Query( $strSql );

    $items = [];
    foreach ( $models as $model ){
      $items[$model] = 1;
    }

    while ( $row = $result->Fetch() ){
      unset( $items[$row['model']] );
    }

    $rows = $this->dbPanel->select(['*'], $this->fboTable)->make();
    foreach ( $rows as $row ){
      unset( $items[ $row['model'] ] );
    }

    return $items; // Возвращаем модели которых нет в наличии
  }

  private function checkIfGotProfitCap( array $items, array $settings ):array
  {
    $commission = $this->getCommission();
    $defaultSettings = $this->getDefaultSettings()[0];

    foreach ( $items as $model => &$item ){
      $nextStep = intval($settings[$model]['step'] ?? $defaultSettings['step']);
      $status = (intval( $item['status'] ) - $nextStep) / 100;
      $price = $item['startPrice'];
      $cost = $item['cost'];

      $finalPrice = $item['startPrice'] * (1 + $status);

      $profit_perc = ( $finalPrice * (1 - $commission) - $item['cost'] ) / $item['cost'];
      $profit_rub = $finalPrice * (1 - $commission) - $item['cost'];

      // echo '<pre>';
      //
      // var_dump($model);
      // var_dump( $settings[$model]['min_profit_rub'] ?? $defaultSettings['min_profit_rub'] );
      // var_dump($profit_rub);
      // var_dump( $settings[$model]['min_profit_perc'] ?? $defaultSettings['min_profit_perc'] );
      // var_dump($profit_perc);
      // var_dump('####');
      // var_dump($item['startPrice']);
      // var_dump($finalPrice);
      // var_dump($item['cost']);
      // var_dump($commission);
      // var_dump('---------------------------------');
      //
      // echo '</pre>';

      if ( ($settings[$model]['min_profit_perc'] ?? $defaultSettings['min_profit_perc']) > $profit_perc * 100 ){
        $item['profit_cap_perc'] = 'Y';
        continue;
      }
      if ( ($settings[$model]['min_profit_rub'] ?? $defaultSettings['min_profit_rub']) > $profit_rub ){
        $item['profit_cap_rub'] = 'Y';
        continue;
      }
    }
    // die;
    return $items;
  }

  private function getCommission():float
  {
    $rows = $this->dbPanel->select(['*'], $this->settingsModuleTable)->make();

    foreach ( $rows as $row ){
      $settings[ $row['name'] ] = $row['value'];
    }

    return floatval( ($settings['com'] ?? 0) / 100 );
  }

  public function getCalculatedNextRunDate():array
  {
    $items = $this->getListSettings()['items'];
    $final = $this->getCurrentStatusData();

    $result = [];
    foreach( $items as $item ){
      $model = $item['model'];
      $lastRun = $final[$model]['date'] ?? false;

      if  ( $item['goal'] >= 24 ){
        $timeGap = 1;
      }else{
        $timeGap = round( 1 / ($item['goal'] / 24) );
      }

      if ( $lastRun === false ){
        $result[ $model ] = date(
          'Y-m-d G:00:00',
          strtotime('+ 1 hour')
        );
        continue;
      }

      $nextRunDate = date(
        'Y-m-d G:00:00',
        strtotime( $lastRun . " + $timeGap hour" )
      );
      if ( strtotime($nextRunDate) < strtotime(date('Y-m-d G:00:00')) ){
          $result[$model] = date(
            'Y-m-d G:00:00',
            strtotime( " + 1 hour" )
          );
          continue;
      }
      $result[$model] = $nextRunDate;
    }

    return $result;
  }

  private function calculateGoalsLong( array $items ):array
  {
    if ( empty($items) ) return [];

    $result = [];
    $coefs = [];

    $hour = date('G');

    $data = $this->getCoefficientConfig();

    foreach ( $data as $value ){
      $coefs[ $value['hour'] ] = $value['coefficient'];
    }

    $m = [];

    for ( $i = 1; $i <= 24; $i++ ){
      if ( $i - 1 == 0 ){
        $m[$i] = $coefs[$i];
        continue;
      }
      $m[$i] = round($m[ $i - 1 ] + $coefs[$i], 2);
    }

    foreach ( $items as $item ){
      $result[ $item['model'] ] = round( $item['goal'] * $m[$hour] );
    }

    return $result;
  }

  private function calculateGoalsShort( array $items ):array
  {
    if ( empty($items) ) return [];

    $result = [];
    $coefs = [];

    $hour = date('G');

    $data = $this->getCoefficientConfig();

    foreach ( $data as $value ){
      $coefs[ $value['hour'] ] = $value['coefficient'];
    }

    foreach ( $items as $item ){
      $result[ $item['model'] ] = round( $item['goal'] * $coefs[$hour] );
    }

    return $result;
  }

  private function calcualteGoalForPeriodPerModel( int $goal, int|bool $hour ):int|bool
  {
    if ( $hour === false ) return $hour;
    $data = $this->getCoefficientConfig();

    foreach ( $data as $value ){
      $coefs[ $value['hour'] ] = $value['coefficient'];
    }

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

  private function getOrdersCount( array $models ):array
  {
    if ( empty($models) ) return [];
    $modelsFormatted = array_map(function($item){
      return "'".$item."'";
    }, $models);

    $modelsStr = implode( ',', $modelsFormatted );


    $date = date( 'Y-m-d 00:00:00' );

    $timestamps = [
      '1_hour' => strtotime('- 1 hour'),
      '6_hours' => strtotime('- 6 hour'),
      '24_hours' => strtotime( $date ),
    ];

    $strSql = "SELECT op.vendor_code as model, o.in_process_at as date
      FROM `wdhs_ozon_order_products` AS op
      JOIN `wdhs_ozon_orders` AS o
      ON o.posting_number = op.posting_number
      WHERE o.in_process_at > '{$date}' AND op.vendor_code IN ({$modelsStr})";

    $result = $this->dbMain->Query( $strSql );
    $items = [];

    foreach ( $models as $model ){
      foreach ( $timestamps as $name => $ts ){
        $items[$model][$name] = 0;
      }
    }

    while ( $row = $result->Fetch() ){
      $orderTime = strtotime( $row['date'] );
      // $orderTime = $row['date'];
      if ( $orderTime >= $timestamps['1_hour'] ){
        $items[ $row['model'] ]['1_hour'] += 1;
      }
      if ( $orderTime >= $timestamps['6_hours'] ){
        $items[ $row['model'] ]['6_hour'] += 1;
      }
      if ( $orderTime >= $timestamps['24_hours'] ){
        $items[ $row['model'] ]['24_hour'] += 1;
      }
    }

    return $items;
  }

  private function getOrdersCountByPeriod( array $items ):array
  {
    $priceData = $this->getCurrentStatusData();

    $arRunDates = [];
    if ( empty($items) ) return [];
    foreach ( $items as $item ){
      $lastRunDate = $priceData[ $item['model'] ]['date'] ?? false;
      if ( !$lastRunDate ) continue;

      $arRunDates[ $item['model'] ] = strtotime( $lastRunDate );
    }
    unset( $lastRunDate );

    if ( empty($arRunDates) ) return [];

    $modelsFormatted = array_map(
      function($item){
        return "'".$item."'";
      },
      array_keys( $arRunDates )
    );

    $modelsStr = implode( ',', $modelsFormatted );
    $date = date( 'Y-m-d 00:00:00' );

    $strSql = "SELECT op.vendor_code as model, o.in_process_at as date
      FROM `wdhs_ozon_order_products` AS op
      JOIN `wdhs_ozon_orders` AS o
      ON o.posting_number = op.posting_number
      WHERE o.in_process_at > '{$date}' AND op.vendor_code IN ({$modelsStr})";

    $result = $this->dbMain->Query( $strSql );

    $ordersData = [];

    foreach ( $items as $item ){
      $ordersData[ $item['model'] ] = 0;
    }

    while ( $row = $result->Fetch() ){
      $inProcessAt = strtotime( $row['date'] );
      $lastRunDate = $arRunDates[ $row['model'] ];
      if ( $lastRunDate > $inProcessAt ) continue;

      $ordersData[ $row['model'] ] += 1;
    }

    return $ordersData;
  }

  private function getOrdersCountByHour( array $models, bool $ys_flag ):array
  {
    if ( empty($models) ) return [];
    $modelsFormatted = array_map(function($item){
      return "'".$item."'";
    }, $models);
    $modelsStr = implode( ',', $modelsFormatted );

    if ( $ys_flag ){
      $dateYesterday = date( 'Y-m-d 00:00:00', strtotime('- 1 day') );
      $dateToday = date( 'Y-m-d 00:00:00' );

      $strSql = "SELECT op.vendor_code as model, o.in_process_at as date
        FROM `wdhs_ozon_order_products` AS op
        JOIN `wdhs_ozon_orders` AS o
        ON o.posting_number = op.posting_number
        WHERE o.in_process_at > '{$dateYesterday}' AND o.in_process_at < '{$dateToday}' AND op.vendor_code IN ({$modelsStr})";
    }else{
      $date = date( 'Y-m-d 00:00:00' );

      $strSql = "SELECT op.vendor_code as model, o.in_process_at as date
      FROM `wdhs_ozon_order_products` AS op
      JOIN `wdhs_ozon_orders` AS o
      ON o.posting_number = op.posting_number
      WHERE o.in_process_at > '{$date}' AND op.vendor_code IN ({$modelsStr})";
    }

    $result = $this->dbMain->Query( $strSql );
    $hours = range(0, 23);
    $items = [];

    foreach ( $models as $model ){
      foreach ( $hours as $hour ){
        $items[$model][$hour] = 0;
      }
    }

    while ( $row = $result->Fetch() ){
      $orderHour = date( 'G' ,strtotime( $row['date'] ) );
      $items[ $row['model'] ][$orderHour] += 1;
    }

    return $items;
  }

  private function getStatusHistory( array $models, bool $ys_flag ):array
  {
    $date = $ys_flag ? date('Y-m-d', strtotime('- 1 day')) : date('Y-m-d');
    $rows = $this->dbPanel->select(['*'], $this->historyTable)->where('date', "%{$date}%", 'LIKE')->make();
    $result = [];

    foreach ( $rows as $row ){
      $time = strtotime( $row['date'] );
      $hour = date('G', $time);
      $result[ $row['model'] ][$hour] = $row['perc'];
    }

    return $result;
  }

  private function calculateGoalsPeriod( array $items ):array
  {
    $priceData = $this->getCurrentStatusData();
    $coefsRaw = $this->getCoefficientConfig();

    $coefficients = [];
    foreach ($coefsRaw as $row) {
      $hour = ($row['hour'] == 24) ? 0 : $row['hour'];
      $coefficients[ $row['hour'] ] = $row['coefficient'];
    }

    $currentHour = date('G');

    $result = [];

    foreach ( $items as $item ){
      $lastRunDate = $priceData[ $item['model'] ]['date'] ?? false;
      $lastRunHour = $lastRunDate ? date('G', strtotime($lastRunDate) ) : $lastRunDate;
      $result[ $item['model'] ] = $this->calcualteGoalForPeriodPerModel( $item['goal'], $lastRunHour );
    }

    return $result;
  }

  public function updateListSettings( array $settings ):void
  {
    if ( empty($settings) ) return;

    foreach ( $settings as $id => $row ){
      $goal = $row['goal'];
      $mpr = empty( $row['min_profit_rub'] ) ? 'NULL' : $row['min_profit_rub'];
      $mpp = empty( $row['min_profit_perc'] ) ? 'NULL' : $row['min_profit_perc'];
      $step = empty( $row['step'] ) ? 'NULL' : $row['step'];

      $strSql = "UPDATE {$this->settingsTable} SET goal = {$goal}, min_profit_rub = {$mpr}, min_profit_perc = {$mpp}, step = $step WHERE cabinet = '{$this->cabinet}' AND id = '{$id}'";

      $this->dbPanel->query( $strSql );
    }
  }

  public function getDefaultSettings():array
  {
    return $this->dbPanel->select( ['*'], $this->defaultSettingsTable )->where( 'cabinet', $this->cabinet )->make() ?? [];
  }

  public function getCoefficientConfig():array
  {
    return $this->dbPanel->select( ['*'], $this->coefficientsTable )->where( 'cabinet', $this->cabinet )->make() ?? [];
  }

  public function updateDefaultSettings( array $defaults ):void
  {
    foreach ( $defaults as $field => $value ){
      $strSql = "UPDATE {$this->defaultSettingsTable} SET {$field} = '{$value}' WHERE cabinet = '{$this->cabinet}'";

      $this->dbPanel->query( $strSql );
    }
  }

  public function updateCoefficientsSettings( array $data ):void
  {
    if ( empty($data) ) return;

    foreach ( $data as $row ){
      $strSql = "UPDATE {$this->coefficientsTable} SET coefficient = '{$row['coefficient']}' WHERE id = '{$row['id']}'";
      $this->dbPanel->query( $strSql );
    }
  }

  public function addItems( array $items ):void
  {
    $rows = $this->dbPanel->select( ['*'], $this->settingsTable )->where( 'cabinet', $this->cabinet )->make();
    $data = [];

    foreach( $rows as $row ){
      $data[ $row['model'] ] = $row['goal'];
    }

    $import = [];
    $update = [];

    foreach ( $items as $item ){
      if ( isset($data[$item['model']]) && $data[$item['model']] == $item['goal'] ) continue;

      if ( isset($data[$item['model']]) ){
        $update[] = $item;
        continue;
      }

      $import[] = $item;
    }
    $this->updateSettings( $update );
    $this->importSettings( $import );
  }

  private function importSettings( array $import ):void
  {
    if ( empty($import) ) return;
    $this->dbPanel->insert( $this->settingsTable, $import );
  }

  private function updateSettings( array $update ):void
  {
    if ( empty($update) ) return;

    foreach ( $update as $row ){

      $strSql = "UPDATE {$this->settingsTable} SET goal = '{$row['goal']}' WHERE model = '{$row['model']}' AND cabinet = '{$this->cabinet}'";
      $this->dbPanel->query( $strSql );
    }
  }

  public function deleteItem( int $id ):void
  {
    if ( empty($id) ) return;
    $strSql = "DELETE FROM {$this->settingsTable} WHERE id = {$id}";
    $this->dbPanel->query( $strSql );
  }
}
?>
