<?php
class CommunicationService
{
  private ?DBPanel $db;

  public function __construct( DBPanel $db )
  {
    $this->db = $db;
  }

  public static function log( mixed $message ):void
  {
    $logPath = sprintf(
      ConfigProvider::getLogPathTemplate(),
      ConfigProvider::getMarketplace(),
      ConfigProvider::getCabinet() . "_" . date('Y_m_d')
    );
    $time = date( 'G:i:s' );
    $text = print_r( $message, true );

    $backtrace = debug_backtrace()[1];
    $callerClass = $backtrace['class'] ?? 'Global';
    $callerMethod = $backtrace['function'];
    $backtraceBlock = "[{$callerClass}::{$callerMethod}]";
    $printMessage = "{$backtraceBlock} {$time} --- {$text} " . PHP_EOL;

    print_r( $printMessage );
    // Не комментируй. Это временно и важно
    file_put_contents( $logPath, $printMessage, FILE_APPEND|LOCK_EX );
  }

  public function saveData( array $data ):void
  {
    $this->deleteData(
      table: ConfigProvider::getFinalPriceTable(),
      cabinet: ConfigProvider::getCabinet(),
      data: $data
     );
    $this->db->insert( ConfigProvider::getFinalPriceTable(), $data );
  }

  private function deleteData( string $table, string $cabinet, array $data ):void
  {
    $models = array_map(function($item){
      return "'" . $item['model'] . "'";
    }, $data);

    $filter = implode( ',', $models );
    $strSql = "DELETE FROM {$table} WHERE model IN ({$filter}) AND cabinet = '{$cabinet}'";
    //
    // $this->db->delete( from: $table )->whereIn('model', $models)->make(); // Этот метод не реализован

    $this->db->query( $strSql );
  }

  public function saveHistory( array $data ):void
  {
    $insert = [];

    foreach ( $data as $elem ){
      $perc = $elem['perc'];
      if ( $elem['action'] == 'down' ) $perc = $perc * -1;
      $insert[] = [
        'model' => $elem['model'],
        'perc' => $perc,
        'cabinet' => $elem['cabinet'],
        'date' => date('Y-m-d G:i:s')
      ];
    }

    $this->db->insert( ConfigProvider::getHistoryDataTable(), $insert );
  }
}
 ?>
