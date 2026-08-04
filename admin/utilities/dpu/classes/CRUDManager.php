<?php
require_once("{$_SERVER['DOCUMENT_ROOT']}/admin/panel/engine/common/DynamicPrice/lib/bootstrap.php");

class CRUDManager
{
  private ?DBPanel $dbPanel;
  private object $dbMain;

  private string $cabinet;
  // Конфиги

  public function __construct( string $mp, string $cab )
  {
    $checkPlatform = in_array( $mp, ConfigProvider::getAllowedPlatforms() );
    if ( !$checkPlatform ) throw new InvalidArgumentException("Unknown platform {$mp}");

    $checkCabinet = in_array( $cab, ConfigProvider::getAllowedCabinets($mp) );
    if ( !$checkCabinet ) throw new InvalidArgumentException("Unknown cabinet {$cab} for platform {$mp}");

    ConfigProvider::init( $mp, $cab );
    $this->init();
  }

  private function init():void
  {
    // Инициализируем базы
    $dbPanel = new DBPanel;
    $dbMain = \Bitrix\Main\Application::getConnection();

    $this->dataProvider = new DataProvider(
      items: new ItemsRepository( $dbMain, $dbPanel ),
      prices: new PricesRepository( $dbMain, $dbPanel ),
      settings: new SettingsRepository( $dbMain, $dbPanel )
    );

    $this->orderProvider = Loader::getOrderProvider(
      panel: $dbPanel,
      main: $dbMain
    );

    $this->dbPanel = $dbPanel;
    $this->dbMain = $dbMain;
  }

  public function getItems( bool $showYesterdayCharts = false ):array
  {
    $coefficients = $this->dataProvider->getCoeffientsSettings();
    $settingsList = $this->getSettingsList();
    $defaults = $this->getDefaultSettings();

    $items = $this->dataProvider->getItems();
    // $itemsTmp = $items;
    // $this->orderProvider->getOrdersCount( $itemsTmp, false );
    $this->orderProvider->getOrdersCount( $items, true );

    $this->enrichInstalledData( $items );
    $this->setAvailabiltyFlag( $items );
    $this->setStatusHistory( $items, $showYesterdayCharts );
    $this->setOrdersHistory( $items, $showYesterdayCharts );
    $this->setGoalForPeriod( $items, $coefficients );
    $this->setItemSettings( $items, $settingsList );
    $this->setProfitCapFlag( $items, $defaults );
    // $this->mergeItems( $items, $itemsTmp );

    return $items;
  }

  private function enrichInstalledData( array &$items ):void
  {
    $rows = $this->dbPanel->select(['*'], ConfigProvider::getFinalPriceTable())->where('cabinet', ConfigProvider::getCabinet())->make();

    foreach ( $rows as $row ){
      $items[ $row['model'] ]['installed']['profit'] = $row['profit_rub'];
      $items[ $row['model'] ]['installed']['margin'] = $row['profit_perc'];
    }
  }

  private function mergeItems( array &$items, array $itemsTmp ):void
  {
    foreach ( $items as $model => &$data ){
      $data['ordersCountAll'] = $itemsTmp[$model]['ordersCount'];
    }
  }

  private function setAvailabiltyFlag( array &$items ):void
  {
    if ( empty($items) ) return;

    $models = array_map( function($item){
      return "'".$item."'";
    }, array_keys($items) );

    $filterModel = implode( ',', $models );
    $filterPrice = ConfigProvider::getPriceFilterName();

    $strSql = "SELECT * FROM ci_price WHERE {$filterPrice} = 'Y' AND model IN ({$filterModel})";
    $result = $this->dbMain->Query( $strSql );

    foreach ( $items as $model => &$data ){
      $data['isAvailable'] = false;
    }

    while ( $row = $result->Fetch() ){
      if ( empty($items[ $row['model'] ]['cost']) ) continue;
      $items[ $row['model'] ]['isAvailable'] = true;
    }
    if ( !ConfigProvider::getCheckFboFlag() ) return;

    $rows = $this->dbPanel->select(['*'], ConfigProvider::getFboCostTable())->make();

    $modelKey = ConfigProvider::getFboSelectField('cost', 'model');

    foreach ( $rows as $row ){
      $model = $row[$modelKey];
      if ( !isset($items[$model]) ) continue;
      $items[$model]['isAvailable'] = true;
    }

  }

  private function setStatusHistory( array &$items, bool $ys_flag ):void
  {
    $date = $ys_flag ? date('Y-m-d', strtotime('- 1 day')) : date('Y-m-d');
    $rows = $this->dbPanel->select(['*'], ConfigProvider::getHistoryDataTable())->where('date', "%{$date}%", 'LIKE')->make();

    foreach ( $rows as $row ){
      if ( empty($items[ $row['model'] ]) ) continue;
      $time = strtotime( $row['date'] );
      $hour = date('G', $time);
      $items[ $row['model'] ]['history']['status'][$hour] = $row['perc'];

      if ( empty($row['goal']) ) continue;

      $items[ $row['model'] ]['history']['goal'][] = [
        'goal' => $row['goal'],
        'goalInterval' => $row['goalInterval'],
        'orders' => $row['orders'],
        'date' => date('G:i', strtotime($row['date']))
      ];
    }
  }

  private function setOrdersHistory( array &$items, bool $ys_flag ):void
  {
    if ( empty($items) ){
      throw new InvalidArgumentException('Items array cannot be empty');
    }
    $filter = $this->prepareModelsFilter(
      models: array_keys( $items )
    );

    $ordersTable = ConfigProvider::getOrdersTable();
    $orderProductsTable = ConfigProvider::getOrderProductsTable();

    if ( $ys_flag ){
      $minDate = date('Y-m-d 00:00:00', strtotime('- 1 day'));
      $maxDate = date('Y-m-d 23:59:59', strtotime('- 1 day'));
    }else{
      $minDate = date('Y-m-d 00:00:00');
      $maxDate = date('Y-m-d H:i:s');
    }
    $dateField = 'in_process_at';
    $joinField = 'posting_number';
    $additionalCond = '';
    if ( ConfigProvider::getCabinet() == 'WR' ){
      $dateField = 'created_at';
      $additionalCond = "  AND cabinet = 'WR'";
      $joinField = 'order_id';
    }

    $strSql = "SELECT op.vendor_code as model, UNIX_TIMESTAMP(o.{$dateField}) as date
      FROM `{$orderProductsTable}` AS op
      JOIN `{$ordersTable}` AS o
      ON o.{$joinField} = op.{$joinField}
      WHERE o.{$dateField} > '{$minDate}' AND op.vendor_code IN ({$filter}){$additionalCond}";

    $res = $this->dbMain->Query( $strSql );

    foreach ( $items as $model => &$data ){
      $hours = range( 0, 23 );
      foreach( $hours as $h ){
        $items[ $model ]['history']['orders'][$h] = 0;
      }
      $items[ $model ]['ordersCountAll'] = 0;
    }
    while ( $row = $res->Fetch() ){
      if ( empty($items[ $row['model'] ]) ) continue;
      $hour = date( 'G', intval($row['date']) );
      $items[ $row['model'] ]['history']['orders'][$hour] += 1;
      $items[ $row['model'] ]['ordersCountAll'] += 1;
    }
  }

  private function setGoalForPeriod( array &$items, array $coefficients ):void
  {
    foreach ( $items as $model => &$data ){
      $data['goalPeriod'] = $this->calculateGoal( $data, $coefficients );
      $data['goalFull'] = CalculationService::calculateGoal( $data, $coefficients, false );
    }
  }

  private function setItemSettings( array &$items, array $settings ):void
  {
    foreach ( $items as $model => &$data ){
      $data['min_profit_rub'] = $settings[$model]['min_profit_rub'];
      $data['min_profit_perc'] = $settings[$model]['min_profit_perc'];
      $data['step'] = $settings[$model]['step'];
    }
  }

  private function getSettingsList():array
  {
    $rows = $this->dbPanel->select(['*'], ConfigProvider::getSettingsTable() )->where('cabinet', ConfigProvider::getCabinet() )->make();
    $result = [];

    foreach ( $rows as $row ){
      $result[ $row['model'] ] = $row;
    }

    return $result;
  }

  private function setProfitCapFlag( array &$items, array $defaults ):void
  {

    foreach ( $items as $model => &$data ){
      $data['cap']['profit'] = true;
      $data['cap']['margin'] = true;
      if ( empty($data['cost']) ) continue;
      $step = ($data['installed']['step'] ?? 0) - ($data['step'] ?? $defaults['step']);
      $price = $data['startPrice'] * ( 1 + $step / 100 );

      $profit = CalculationService::calculateProfit( $price, $data['cost'], $defaults['commission'] );
      $margin = CalculationService::calculateMargin( $price, $data['cost'], $defaults['commission'] );


      if ( $profit < $defaults['min_profit_rub'] ){
        $data['cap']['profit'] = false;
      }
      if ( $margin * 100 < $defaults['min_profit_perc'] ){
        $data['cap']['margin'] = false;
      }
    }
  }

  private function calculateGoal( array $data, array $coefficients ):int
  {
    $minInterval = strtotime($data['intervals']['lastRunDate']);
    $maxInterval = strtotime($data['intervals']['nextRunDate'] . ' + 3 min');

    $secondsDiff = $maxInterval - $minInterval;
    $hoursDiff = round( $secondsDiff / 3600 ) - 1;
    $result = 0;

    for ( $i = 0; $i <= $hoursDiff; $i++ ){

      $hour = date( 'G', $minInterval + $i * 3600 );
      $result += $data['goal'] * $coefficients[ $hour ];
    }

    return round($result);
  }

  private function prepareModelsFilter( array $models ):string
  {
    $modelsFormatted = array_map(function($item){
      return "'".$item."'";
    }, $models);

    $string = implode( ',', $modelsFormatted );

    return $string ?? '';
  }

  public function getDefaultSettings():array
  {
    return $this->dbPanel->select( ['*'], ConfigProvider::getDefaultSettingsTable() )->where( 'cabinet', ConfigProvider::getCabinet() )->make()[0];
  }

  public function getCoefficientConfig():array
  {
    return $this->dbPanel->select( ['*'], ConfigProvider::getCoefficientsTable() )->where( 'cabinet', ConfigProvider::getCabinet() )->make() ?? [];
  }

  public function getFboStats():array
  {
    if ( ConfigProvider::getMarketplace() == 'WB' ){
      $data = $this->getFboStatWB();
    }else{
      $data = $this->getFboStatOZON();
    }

    return $data ?? [];
  }

  private function getFboStatWB():array
  {
    $result = [];

    $rows = $this->dbPanel->select( ['*'], ConfigProvider::getFboStatTable() )->where('stock_date', date('Y-m-d'))->make();

    foreach ( $rows as $row ){
      $result[ $row['model'] ] = $row['stock'];
    }

    return $result;
  }

  private function getFboStatOZON():array
  {
    $result = [];
    $date = date('Y-m-d');
    $table = ConfigProvider::getFboStatTable();

    $strSql = "SELECT * FROM {$table} WHERE date = '{$date}'";
    $rows = $this->dbMain->query( $strSql );

    while( $row = $rows->fetch() ){
      $result[ $row['model'] ] = $row['stock'];
    }

    return $result;
  }

  public function updateDefaultSettings( array $defaults ):void
  {
    $defaultSettingsTable = ConfigProvider::getDefaultSettingsTable();
    $cabinet = ConfigProvider::getCabinet();
    $installed = $this->getDefaultSettings();
    $list = $this->dbPanel->select(['model'], ConfigProvider::getSettingsTable())->make();

    foreach ( $defaults as $field => $value ){
      $strSql = "UPDATE {$defaultSettingsTable} SET {$field} = '{$value}' WHERE cabinet = '{$cabinet}'";

      $this->dbPanel->query( $strSql );
    }

    $this->insertAllItemsInUpdateList( $defaults, $installed, $list );
  }

  private function insertAllItemsInUpdateList( array $settings, array $installed, array $list ):void
  {
    $allowedFields = ['min_profit_rub','min_profit_perc', 'commission'];
    $needUpdate = false;

    foreach ( $settings as $field => $value ){
      if ( !in_array($field, $allowedFields) ) continue;
      if ( $value == $installed[$field] ) continue;

      $needUpdate = true;
      break;
    }

    if ( !$needUpdate ) return;
    if ( empty($list) ) return;
    $table = ConfigProvider::getUpdateListTable();
    $strSql = "DELETE FROM {$table} WHERE 1=1";

    $this->dbPanel->query( $strSql );
    $this->dbPanel->insert( $table, $list );
  }

  public function updateCoefficientsSettings( array $data ):void
  {
    if ( empty($data) ) return;
    $coefficientsTable = ConfigProvider::getCoefficientsTable();
    foreach ( $data as $row ){
      $strSql = "UPDATE {$coefficientsTable} SET coefficient = '{$row['coefficient']}' WHERE id = '{$row['id']}'";
      $this->dbPanel->query( $strSql );
    }
  }

  public function addItems( array $items ):void
  {
    $rows = $this->dbPanel->select( ['*'], ConfigProvider::getSettingsTable() )->where( 'cabinet', ConfigProvider::getCabinet() )->make();
    $data = [];

    foreach( $rows as $row ){
      $data[ $row['model'] ] = $row;
    }

    $import = [];
    $update = [];

    foreach ( $items as $item ){


      if ( isset($data[$item['model']]) ){
        // var_dump($items);
        $update[ $item['model'] ] = $this->findFieldsForUpdate( $item, $data[$item['model']] );
        continue;
      }

      $import[] = $item;
    }
    $this->updateSettings( $update );
    $this->importSettings( $import );
  }

  private function findFieldsForUpdate( array $item, array $data ): ?array
  {
    $result = [];

    foreach ($item as $name => $value) {
      if ($value === null) continue;
      if (!isset($data[$name]) || $data[$name] != $value) {
          $result[$name] = $value;
      }
    }

    return empty($result) ? null : $result;
  }

  private function importSettings( array $import ):void
  {
    if ( empty($import) ) return;

    foreach ( $import as $row ){
      $row = array_filter( $row, fn($val) => $val !== null);
      $this->dbPanel->insert( ConfigProvider::getSettingsTable(), [$row] );
    }
  }

  private function updateSettings( array $update ):void
  {
    if ( empty($update) ) return;

    $settingsTable = ConfigProvider::getSettingsTable();
    $cabinet = ConfigProvider::getCabinet();

    $installed = $this->dbPanel->select(['model', 'min_profit_perc', 'min_profit_rub'], ConfigProvider::getSettingsTable())->make();

    foreach ( $update as $model => $row ){
      if ( $row == null ) continue;
      $fieldsRaw = array_filter( $row, fn($val) => $val !== null );
      $fields = [];

      foreach ( $fieldsRaw as $field => $value ){
        if ( $field == 'model' || $field == 'cabinet' ) continue;
        $fields[] = "{$field} = {$value}";
      }

      $fields = implode( ',', $fields );

      $strSql = "UPDATE {$settingsTable} SET $fields WHERE model = '{$model}' AND cabinet = '{$cabinet}'";
      $this->dbPanel->query( $strSql );
    }

    $this->insertUpdatedItemsInUpdateList( $update );
  }

  private function insertUpdatedItemsInUpdateList( array $settings ):void
  {
    if ( empty($settings) ) return;

    $rows = $this->dbPanel->select(['model'], ConfigProvider::getUpdateListTable())->make();
    $updateList = [];
    foreach ( $rows as $row ) { $updateList[$row['model']] = true; }

    $insert = array_filter( $settings, fn($val) => $val !== null );
    $insert = array_filter( array_keys($insert), fn($val) => empty($updateList[$val]) );
    $insert = array_map( fn($val) => ['model' => $val], $insert );

    if ( empty($insert) ) return;

    $this->dbPanel->insert( ConfigProvider::getUpdateListTable(), $insert );
  }


  public function updateListSettings( array $settings ):void
  {
    if ( empty($settings) ) return;

    $settingsTable = ConfigProvider::getSettingsTable();
    $cabinet = ConfigProvider::getCabinet();
    $installed = $this->dbPanel->select(['id', 'model', 'min_profit_rub', 'min_profit_perc'], ConfigProvider::getSettingsTable())->make();

    foreach ( $settings as $id => $row ){
      $goal = $row['goal'];
      $mpr = empty( $row['min_profit_rub'] ) ? 'NULL' : $row['min_profit_rub'];
      $mpp = empty( $row['min_profit_perc'] ) ? 'NULL' : $row['min_profit_perc'];
      $step = empty( $row['step'] ) ? 'NULL' : $row['step'];

      $strSql = "UPDATE {$settingsTable} SET goal = {$goal}, min_profit_rub = {$mpr}, min_profit_perc = {$mpp}, step = $step WHERE cabinet = '{$cabinet}' AND id = '{$id}'";

      $this->dbPanel->query( $strSql );
    }

    $this->insertSomeItemsInUpdateList( $settings, $installed );
  }

  private function insertSomeItemsInUpdateList( array $settings, array $installed ):void
  {
    $rows = $this->dbPanel->select(['model'], ConfigProvider::getUpdateListTable())->make();
    $updateList = [];
    foreach ( $rows as $row ) { $updateList[$row['model']] = true; }

    $insert = [];

    foreach ( $installed as $row ) {
      $isMinPercSame = $settings[ $row['id'] ]['min_profit_perc'] == $row['min_profit_perc'];
      $isMinRubSame = $settings[ $row['id'] ]['min_profit_rub'] == $row['min_profit_rub'];
      if ( $isMinPercSame && $isMinRubSame ) continue;
      if ( isset( $updateList[$row['model']] ) ) continue;
      $insert[] = [ 'model' => $row['model'] ];
    }

    if ( empty($insert) ) return;
    $this->dbPanel->insert( ConfigProvider::getUpdateListTable(), $insert );
  }

  public function deleteItem( int $id ):void
  {
    if ( empty($id) ) return;
    $settingsTable = ConfigProvider::getSettingsTable();
    $strSql = "DELETE FROM {$settingsTable} WHERE id = {$id}";
    $this->dbPanel->query( $strSql );
  }

  public function clearPriceTable():void
  {
    $cabinet = ConfigProvider::getCabinet();
    $table = ConfigProvider::getFinalPriceTable();

    $strSql = "DELETE FROM {$table} WHERE cabinet = '{$cabinet}'";
    $this->dbPanel->query( $strSql );
  }

  public function clearItemsList():void
  {
    $cabinet = ConfigProvider::getCabinet();
    $tableItems = ConfigProvider::getSettingsTable();
    $tableHistory = ConfigProvider::getHistoryDataTable();

    $strSql = "DELETE FROM {$tableItems} WHERE cabinet = '{$cabinet}'";
    $this->dbPanel->query( $strSql );

    $strSql = "DELETE FROM {$tableHistory} WHERE cabinet = '{$cabinet}'";
    $this->dbPanel->query( $strSql );
  }
}


?>
