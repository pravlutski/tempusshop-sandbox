<?php
class ImportStockService
{
  public function __construct(
    private ?ApiManager $api = null,
    private ?DataProvider $data = null,
    private ?ConfigProviderInterface $config = null
  ){}

  public function getStoreDictionary( string $cabinet ):array
  {
    $rows = $this->data->settings()->getCampaignsMatchList( $cabinet );
    $result = [];

    foreach ( $rows as $row ){
      $result[ $row['campaignId'] ] = $row;
    }

    return $result;
  }

  public function actualizeOffers( array $offers, array $items, int $actualStock, float $minPrice ):array
  {
    $result = [];
    $defaultStock = $this->config->getDefaultStockValue();

    foreach ( $offers as $sku => $stock ){
      $itemPrice = $items[$sku]['price'];

      $isItemValid = isset( $items[$sku] );
      $isPriceAllowed = empty( $itemPrice ) ? false : $itemPrice > $minPrice;

      $expectedStock = $isItemValid && $isPriceAllowed ? $actualStock : $defaultStock;

      if ( $stock == $expectedStock ) continue;

      $result[] = [
        'sku' => (string) $sku,
        'items' => [ ['count' => (int) $expectedStock] ],
      ];
    }

    return $result;
  }

  public function sendBatch( array $offers, int $campaignId ):array
  {
    $response = $this->api->updateStocks( $offers, $campaignId );
    return $response->getData()->decode();
  }

  public function getItemsPrices( array $ids ):array
  {
    if ( empty($ids) ){
      CommunicationService::updateStatus( text: "КРИТИЧЕСКАЯ ОШИБКА ВЫБОРКИ ТОВАРОВ", percent: 100, status: "ABORTED" );
      throw new InvalidArgumentException("ids array cannot be an empty array");
    }

    $priceProp = $this->config->getPriceProperty();
    $pricePropVal = $this->config->getPricePropertyVal();

    $rows = $this->data->items()->getItems(
      filter: [ 'ID' => $ids ],
      select: [ $priceProp ],
    );

    $result = [];

    foreach ( $rows as $row ){
      $result[ $row['ID'] ] = $row[ $pricePropVal ];
    }

    return $result;
  }
}
 ?>
