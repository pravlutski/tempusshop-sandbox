<?php
class StocksDataProvider2
{
  private array $stockSuppliers = [47, 103, 129, 141, 144];
  private array $cabinets = ['WR' => 'WB', 'TL' => 'WBTL', 'WT' => 'WB'];

  public function __construct(
    private \Bitrix\Main\DB\MysqliConnection $main,
    private DBPanel $panel
  ){}

  public function getActiveItems( string $cabinet ):array
  {
    $priceData = $this->getCostData( $cabinet );
    $result = [];

    foreach ( $priceData as $model => $offers ){
      $offer = reset( $offers );
      $suppId = $offer['supplier_id'];

      $result[ $offer['bitrix_id'] ] = [
        'cost' => $offer['price'],
        'supplier' => $offer['supplier_id'],
        'model' => $offer['model'],
        'chrtId' => $dict[ $offer['bitrix_id'] ] ?? false,
      ];
    }

    return $result;
  }

  public function getStockSuppliers():array
  {
    return $this->stockSuppliers;
  }

  public function getFboStock(  string $cabinet ):array
  {
    if ( $cabinet != 'WR' ) return [];

    $rows = $this->panel->select(['*'], 'wb_fbo_stock_WR')->make();
    $result = [];

    foreach ( $rows as $row ){
      $result[ $row['article'] ] = true;
    }

    return $result;
  }

  private function getCiPriceSetData( string $cabinet ):array
  {
    $strSql = "SELECT ARTICLE FROM ci_price_set WHERE PRICE_TYPE = '{$this->cabinets[$cabinet]}'";
    $rows = $this->main->query( $strSql );
    $result = [];

    while ( $row = $rows->fetch() ){
      $result[] = $row['ARTICLE'];
    }

    return $result;
  }

  private function getCostData( string $cabinet ):array
  {
    $service = PanelManager::getPriceManager();
    $servicePrice = $service->updatePriceService($this->cabinets[$cabinet], 'debug');

    $models = $this->getCiPriceSetData( $cabinet );
    if ( empty($models) ) throw new Exception("No active models for selected price type");

    $filter = [
      'article' => $models
    ];

    $servicePrice->market->setPriceFilter( $filter );
    $servicePrice->market->setConfig('tbl_sebes_fbo', false);

    $result = $servicePrice->getMinPurchasePrice();

    return $result;
  }
}

 ?>
