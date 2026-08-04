<?php
class UpdateManager
{
  public function __construct(
    private DBPanel $panel,
    private string $cabinet
  ){}

  public function updatePriceTable( array $update ):bool
  {
    if ( empty($update) ) return true;

    foreach ( $update as $model => $row ){
      $arWhere = [
        'column' => 'model',
        'operator' => '=',
        'value' => $model
      ];

      try{
        $this->panel->update( ConfigProvider::getFinalPriceTable(), $row, [ $arWhere ] );
      } catch( Throwable $e){
        CommunicationService::log("Error occurred during updating price table");
        CommunicationService::log( $e->getMessage() );
        return false;
      }
    }

    $this->updateHistory( $update );

    return true;
  }

  public function deleteBadItems( array $delete ):bool
  {
    if ( empty($delete) ) return true;

    $table = ConfigProvider::getFinalPriceTable();
    try{
      $models = array_keys( $delete );
      $prepared = array_map( fn($item) => "'".$item."'", $models );
      $filter = "(".implode(',', $prepared).")";

      $strSql = "DELETE FROM {$table} WHERE model IN {$filter}";
      $this->panel->query( $strSql );
    } catch( Throwable $e ){
      CommunicationService::log( "Error occurred during deleting from price table" );
      CommunicationService::log( $e->getMessage() );
      return false;
    }

    return true;
  }

  public function updateHistory( array $update ):void
  {

  }

  public function clearUpdateList():void
  {
    $table = ConfigProvider::getUpdateListTable();
    $strSql =  "DELETE FROM {$table} WHERE 1=1";
    $this->panel->query( $strSql );
  }
}
 ?>
