<?php
class SalesCommunicationService
{
  private ?DBPanel $db;
  private string $cabinet;

  public function __construct( DBPanel $db, string $cabinet )
  {
    $this->db = $db;
    $this->cabinet = $cabinet;
  }

  public function log( array $data ):void
  {
    $chunks = array_chunk( $data, 1000 );
    foreach ( $chunks as $chunk ){
      $this->db->insert( 'ozon_sales_detail_log_IP', $chunk );
    }
  }

  public static function logTech( mixed $message ):void
  {
    $date = date( 'Y-m-d G:i:s' );
    $text = print_r( $message, true );

    $backtrace = debug_backtrace()[1];
    $callerClass = $backtrace['class'] ?? 'Global';
    $callerMethod = $backtrace['function'];
    $backtraceBlock = "[{$callerClass}::{$callerMethod}]";
    print_r( "{$backtraceBlock} {$date} --- {$text} " . PHP_EOL );
    // file_put_contents(
    //   SalesConfigProvider::getLogFilePath( $this->cabinet ),
    //   "{$backtraceBlock} {$date} --- {$text} " . PHP_EOL,
    //   FILE_APPEND|LOCK_EX
    // );
  }

  public function updateStatus( $text, $perc, $status = false, $start = false, $end = false ):void
  {
    $tmp = [
      'status' => $status,
      'status_text' => $text,
      'percent' => $perc,
      'time_start' => $start,
      'time_end' => $end,
    ];

    $where[] = [
      'column' => 'code',
      'operator' => '=',
      'value' => SalesConfigProvider::getModuleName( $this->cabinet ),
    ];

    $add = array_filter($tmp, function($value) {
      return $value !== false;
    });

    $this->db->update('ozon_agents', $add, $where );
  }
}

 ?>
