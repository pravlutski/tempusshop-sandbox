<?php

class FboStockValidator
{
  private array $dictionary = [];
  private ?FboStockApi $api = null;

  private array $items = [];
  private string $vaultPath = "/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/wb/configs/corrections.json";

  public function __construct(
    private ?DBPanel $panel = null,
    private ?\Bitrix\Main\DB\MysqliConnection $main = null,
    private ?string $cabinet = null
  )
  {
    $this->getDictionary();
    $fbs = $this->getFbsStock();

    $this->api = new FboStockApi( $fbs );
  }

  public function getInfo( array $items ):void
  {
    $dictionary = $this->dictionary;
    $data = array_map( function($item) use ($dictionary){
      return $dictionary[$item] ?? null;
    }, $items );

    $data = array_filter( $data );

    try{
      $result = $this->api->getCardInfo( $data );
      $this->save( $result, $dictionary );

    } catch ( UnauthorizedRequestException $e ){

      if ( $this->loadPreviousIteration() ){
        return;
      }
      throw $e;

    } catch( Throwable $e ){

      throw $e;
    }

  }

  public function checkIfVisible( string $model ):?bool
  {
    return $this->items[$model];
  }

  private function save( array $result, array $dict ):void
  {
    $dict = array_flip( $dict );
    $items = [];

    foreach ( $result as $row ){
      $model = $dict[$row['id']];
      $items[$model] = $row['isVisible'];
    }

    $this->items = $items;

    $correctionsVault = [
      'items' => $items,
      'date' => date('Y-m-d G:i:s')
    ];

    file_put_contents(
      $this->vaultPath,
      json_encode($correctionsVault)
    );
  }

  private function loadPreviousIteration():bool
  {
    if ( !file_exists($this->vaultPath) ) return false;

    $json = file_get_contents( $this->vaultPath );
    $data = json_decode( $json, true );

    $diff = ( time() - strtotime($data['date']) ) / 60;

    if ( $diff > 90 ) return false;

    $this->items = $data['items'] ?? [];

    return true;
  }

  private function getDictionary():void
  {
    $strSql = "SELECT * FROM wdhs_wb_props WHERE cabinet = '{$this->cabinet}'";
    $rows = $this->main->query( $strSql );
    $result = [];

    while ( $row = $rows->fetch() ){
      $result[ $row['article'] ] = $row['nmid'];
    }

    $this->dictionary = $result;
  }

  private function getFbsStock():array
  {
    $path = "{$_SERVER["DOCUMENT_ROOT"]}/admin/panel/engine/wb/logs/stock.json";
    $json = file_get_contents( $path );
    $data = json_decode( $json, true );

    if ( $data === false ) return [];

    foreach ( $data as $model => $value ){
      $nmid = $this->dictionary[ $model ];
      $result[ $nmid ] = $value;
    }

    return $result;
  }
}

 ?>
