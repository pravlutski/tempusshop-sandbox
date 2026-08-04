<?php
class StocksPageFetcher extends PageFetcherBase implements PageFetcherInterface
{
  public function fetch( ?string $id = null, ?int $limit = null, ?callable $request = null, ?callable $response = null ):array
  {
    if ( $request === null && $id === null ) {
      throw new InvalidArgumentException('Either $request or $id must be provided');
    }
    return $this->paginate(
      limit: $limit ?? $this->config->getRequestStockLimit(),
      requestFn: $request ?? $this->getRequestCallable( $id ),
      responseFn: $response ?? $this->getResponseCallable(),
    );
  }

  private function getRequestCallable( string $id ):Closure
  {
    return function($query) use ($id) {
      return $this->api->getStocks( [], $query, $id );
    };
  }

  private function getResponseCallable():Closure
  {
    return function($data) {
      return $this->processResponse( $data );
    };
  }

  private function processResponse( array $data ):array
  {
    $stocks = reset($data['warehouses'])['offers'] ?? [];
    $result = [];
    foreach ( $stocks as $row ){
      $result[ $row['offerId'] ] = $this->findAvailableStockCount( $row['stocks'] );
    }

    return $result;
  }

  private function findAvailableStockCount( array $stocks ):int
  {
    foreach ( $stocks as $elem ){
      if ( $elem['type'] == "AVAILABLE" ){
        return $elem['count'];
      }
    }

    return 0;
  }
}
 ?>
