<?php
class DataProvider
{
  public function __construct(
    private ?SettingsRepository $settings = null,
    private ?PricesRepository $prices = null,
    private ?ItemsRepository $items = null,
  )
  {}

  public function settings():SettingsRepository
  {
    if ( $this->settings === null ) throw new DisabledRepositoryException("Opertaion failed: Required repository is disabled");
    return $this->settings;
  }

  public function prices():PricesRepository
  {
    if ( $this->prices === null ) throw new DisabledRepositoryException("Opertaion failed: Required repository is disabled");
    return $this->prices;
  }

  public function items():ItemsRepository
  {
    if ( $this->items === null ) throw new DisabledRepositoryException("Opertaion failed: Required repository is disabled");
    return $this->items;
  }

  public function getWarehousesList():array
  {
    return array_keys(TYPE_SKLAD_CONST); // /bitrix/modules/panel.manager/constants.php
  }

  public function getPromosList( string $cabinet, bool $isSerious = false ):array
  {
    if ( $this->settings === null ) throw new DisabledRepositoryException("Opertaion failed: Required repository is disabled");

    $list = $this->settings->getPromosList( $cabinet );

    if ( $isSerious ){
      $filter = fn($el) => (!empty($el['sort']) && $el['active'] == 1);
      $list = array_filter( $list, $filter );
    }

    $result = [];
    usort($list, fn($a, $b) => $a['sort'] <=> $b['sort']);

    foreach ( $list as $row ){
      $result[ $row['promo_id'] ] = $row;
    }

    return $result;
  }

  public function getActiveItems():array
  {
    if ( $this->settings === null || $this->prices === null ) {
      throw new DisabledRepositoryException("Opertaion failed: Required repository is disabled");
    }

    $priceData = $this->prices->getCostDataWithPriority();
    $suppliers = $this->settings->getSuppliersStockSettings();

    $result = [];

    foreach ( $priceData as $model => $data ){
      $offer = reset($data);
      $suppId = $offer['supplier_id'];

      if ( empty($suppliers[ $suppId ]) ) continue;

      $warehouses = $this->getStockWarehouses( 0, $data, $suppliers );
      $warehouses = array_unique( array_merge($suppliers[$suppId], $warehouses) );

      $result[ $offer['bitrix_id'] ] = [
        'cost' => $offer['price'],
        'supplier' => $offer['supplier_id'],
        'model' => $offer['model'],
        'warehouses' => $warehouses,
      ];
    }
    return $result;
  }

  private function getStockWarehouses( int $key, array $data, array $suppliers ):array
  {
    $stockSuppliers = Config::instance()->getStockSuppliersList();
    $stockSuppliers = array_flip( $stockSuppliers );
    $result = [];

    while( isset($data[$key]) ) { // starts from best proposition
      $supplierId = $data[$key]['supplier_id'];

      if ( isset($stockSuppliers[$supplierId]) ){
        $result += $this->placeExpressEnd( $suppliers[$supplierId] );
      }

      $key++;
    }

    return $result;
  }

  private function placeExpressEnd( array $warehouses ):array
  {
    $expressName = Config::instance()->getExpressWarehouseName();

    if ( reset($warehouses) == $expressName ) {
      return array_reverse( $warehouses );
    }

    return $warehouses;
  }
}
 ?>
