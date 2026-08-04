<?php
class LogPromoService
{
  private ?array $rows = null;

  public function __construct(
    private ?Updater $updater,
    private ?ConfigProviderInterface $config,
    private string $cabinet,
  ){}

  public function prepare( array $items, array $promoList ):LogPromoService
  {
    $result = [];

    foreach ( $items as $promoId => $data ){
      $result[] = array_merge(
        $this->prepareRows( $data['good'] ?? [], true, $promoId, $promoList ),
        $this->prepareRows( $data['bad'] ?? [], false, $promoId, $promoList )
      );
    }

    $this->rows = array_merge( ...$result );

    return $this;
  }

  public function save():bool
  {
    if ( $this->rows === null ) throw new Exception("Using 'save' before 'prepare' is forbidden");
    $chunks = array_chunk( $this->rows, $this->config->getLogChunkSize() );

    foreach ( $chunks as $chunk ){
      $this->updater->insertSome(
        into: $this->config->getTableName('promos_detail_log'),
        values: $chunk
      );
    }

    return true;
  }

  public function count():int
  {
    if ( $this->rows === null ) throw new Exception("Using 'count' before 'prepare' is forbidden");
    return count($this->rows);
  }

  public function rows():array
  {
    if ( $this->rows === null ) throw new Exception("Using 'get' before 'prepare' is forbidden");
    return $this->rows;
  }

  private function prepareRows( array $items, bool $status, string $promoId, array $promoList = [] ):array
  {
    $result = [];

    foreach ( $items as $item ){
      $result[] = [
        'promoId' => $promoId,
        'promoName' => $promoList[$promoId]['name'] ?? '',
        'model' => $item['model'],
        'status' => $status ? 'Y' : 'N',
        'cost' => $item['cost'],
        'startPrice' => $item['startPrice'],
        'promoPrice' => empty($item['promoPrice']) ? 0 : $item['promoPrice'],
        'maxPromoPrice' => empty($item['offer']['maxPromoPrice']) ? 0 : $item['offer']['maxPromoPrice'],
        'reason' => $item['reason'],
        'cabinet' => $this->cabinet,
        'date' => date('Y-m-d G:i:s'),
      ];
    }

    return $result;
  }
}
 ?>
