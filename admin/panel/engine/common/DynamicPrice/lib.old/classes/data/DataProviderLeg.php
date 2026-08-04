<?php
class DataProvider
{
  private ?DBPanel $dbPanel;
  private Bitrix\Main\DB\MysqliConnection $dbMain;

  public function __construct( DBPanel $dbPanel, Bitrix\Main\DB\MysqliConnection $dbMain )
  {
    $this->dbPanel = $dbPanel;
    $this->dbMain = $dbMain;
  }

  public function getCoeffientsSettings():array
  {
    $coefficientTable = ConfigProvider::getCoefficientsTable();
    $rows = $this->dbPanel->select(['*'], $coefficientTable)->make();
    $result = [];

    foreach ( $rows as $row ){
      $hour = $row['hour'] == 24 ? 0 : $row['hour'];
      $result[ $hour ] = $row['coefficient'];
    }

    return $result;
  }

  public function getItems():array
  {
    $settingsTable = ConfigProvider::getSettingsTable();
    $cabinet = ConfigProvider::getCabinet();

    $rows = $this->dbPanel->select(['*'], $settingsTable )->where('cabinet', $cabinet)->make();
    $items = [];

    foreach ( $rows as $row ){
      $items[ $row['model'] ] = $row;
    }

    return $this->enrichItemsArray( $items );
  }

  private function enrichItemsArray( array $items ):array
  {
    if ( empty($items) ) return [];

    $models = array_keys( $items );

    $this->getItemsStartPrice(
      items: $items,
      fboPrices: $this->getFboPrices()
    );

    $this->getItemsCost(
      items: $items,
      models: $models,
      fboCosts: $this->getFboCosts(),
      reserved: $this->getReservedItems()
    );

    $this->getItemsCurrentStatuses(
      items: $items,
      models: $models,
      dpData: $this->getItemsDataDP()
    );

    $this->setDefaultSettings( $items );

    $this->calculateItemsIntervals( $items );

    return $items;
  }

  private function getItemsStartPrice( array &$items, array $fboPrices ):void
  {
    if ( empty($items) ){
      throw new Exception('Cannot get start price: items array is empty');
    }

    $priceProperty = ConfigProvider::getPricePropertyName();

    $arFilter = [
      'IBLOCK_ID' => 16,
      'PROPERTY_CML2_ARTICLE' => array_keys( $items ),
    ];
    $arSelect = [ "IBLOCK_ID", "ID", "PROPERTY_CML2_ARTICLE", "{$priceProperty}" ];

    $res = CIBlockElement::GetList( [], $arFilter, false, false, $arSelect );

    while ( $row = $res->GetNext() ){

      $model = $row["PROPERTY_CML2_ARTICLE_VALUE"];
      $price = $row["{$priceProperty}_VALUE"];

      $items[ $model ]['startPrice'] = floatval( $fboPrices[$model] ?? $price );
    }
  }

  private function getItemsCost( array &$items, array $models, array $fboCosts, array $reserved ):void
  {
    if ( empty($items) ){
      throw new Exception('Cannot get orders: items array is empty');
    }

    $models = $this->prepareModelsFilter( models: array_keys( $items ) );
    $filter = ConfigProvider::getPriceFilterName();

    $strSql = "SELECT model, price, count FROM ci_price WHERE {$filter} = 'Y' AND model IN ({$models})";
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
        fbo: $fboCosts,
        reserved: $reserved
      );
    }
  }

  private function getFboCosts():array
  {
    $result = [];
    $rows = $this->dbPanel->select( ['*'], ConfigProvider::getFboCostTable() )->make();

    $modelKey = ConfigProvider::getFboSelectField('cost', 'model');
    $costKey = ConfigProvider::getFboSelectField('cost', 'cost');

    foreach ( $rows as $row ){
      $result[ $row[$modelKey] ] = $row[ $costKey ];
    }

    return $result;
  }

  private function getFboPrices():array
  {
    $result = [];
    $rows = $this->dbPanel->select( ['*'], ConfigProvider::getFboPriceTable() )->make();

    $modelKey = ConfigProvider::getFboSelectField('price', 'model');
    $priceKey = ConfigProvider::getFboSelectField('price', 'price');

    foreach ( $rows as $row ){
      $result[ $row[$modelKey] ] = $row[ $priceKey ];
    }

    return $result;
  }

  private function getItemsDataDP():array
  {
    $result = [];
    $rows = $this->dbPanel->select(['*'], ConfigProvider::getFinalPriceTable() )->where('cabinet', ConfigProvider::getCabinet())->make();

    foreach ( $rows as $row ){
      $result[ $row['model'] ] = $row;
    }

    return $result;
  }

  private function getItemsCurrentStatuses( array &$items, array $models, array $dpData ):void
  {
    foreach ( $models as $model ){
      $settedData = $dpData[$model] ?? false;
      if ( $settedData ){
        $perc = $dpData[$model]['perc'];

        $items[$model]['intervals']['lastRunDate'] = $dpData[$model]['date'];
        $items[$model]['installed']['step'] = ($dpData[$model]['action'] == 'down') ? ($perc * -1) : $perc;
        $items[$model]['installed']['price'] = $dpData[$model]['price'];
        continue;
      }
      $items[$model]['intervals']['lastRunDate'] = false;
      // $items[$model]['installed']['step'] = 0;
      // $items[$model]['installed']['price'] = false;
    }
  }

  private function setDefaultSettings( array &$items ):void
  {
    $defaultSettings = $this->getDefaultSettings();
    foreach ( $items as $model => $data ){
      $items[$model]['min_profit_rub'] = $items[$model]['min_profit_rub'] ?? $defaultSettings['min_profit_rub'];
      $items[$model]['min_profit_perc'] = $items[$model]['min_profit_perc'] ?? $defaultSettings['min_profit_perc'];
      $items[$model]['step'] = $items[$model]['step'] ?? $defaultSettings['step'];
    }
  }

  private function calculateItemsIntervals( array &$items ):void
  {
    foreach ( $items as $model => $item ){
      $lastRunDate = $item['intervals']['lastRunDate'] ?? false;
      $gap = CalculationService::calculateTimeGap( $item['goal'] );

      if ( !$lastRunDate ){
        $lastRunDate = date( 'Y-m-d H:00:00', strtotime("- {$gap} hour") );
        $nextRunDate = date( 'Y-m-d H:00:00' );
      }else{
        $nextRunDate = date( "Y-m-d H:00:00", strtotime("{$lastRunDate} + {$gap} hour") );
      }

      $items[$model]['intervals'] = [
        'lastRunDate' => $lastRunDate,
        'nextRunDate' => $nextRunDate,
      ];
    }
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

  public function getDefaultSettings():array
  {
    $defaultSettingsTable = ConfigProvider::getDefaultSettingsTable();
    $marketplace = ConfigProvider::getMarketplace();
    $cabinet = ConfigProvider::getCabinet();

    $rows = $this->dbPanel->select( ['*'], $defaultSettingsTable )->where('cabinet', $cabinet)->make();
    $result = $rows[0];
    $result['com'] = $this->getCommission();

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

  private function getCommission():int
  {
    return $this->dbPanel->select(
        ['value'],
        ConfigProvider::getPlatformSettingsTable()
      )->where('name', 'com')->make()[0]['value'];
  }
}
?>
