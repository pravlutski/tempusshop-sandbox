<?php
class CommunicationService
{
  private static DBPanel $panel;
  private static string $module;

  public static function log( mixed $message ):void
  {
    // return; // Когда-нибудь потом
    // $logPath = sprintf(
    //   Config::instance()->getLogPath(),
    //   date('Y_m_d')
    // );

    $time = date( 'G:i:s' );
    $text = print_r( $message, true );

    $backtrace = debug_backtrace()[1];
    $callerClass = $backtrace['class'] ?? 'Global';
    $callerMethod = $backtrace['function'];
    $backtraceBlock = "[{$callerClass}::{$callerMethod}]";
    $printMessage = "{$backtraceBlock} {$time} --- {$text} " . PHP_EOL;
    if ( $message == 'START' ){
      $printMessage = "-------------------------------------\n" . $printMessage;
    }
    print_r( $printMessage );
    // file_put_contents( $logPath, $printMessage, FILE_APPEND|LOCK_EX );
  }

  public static function techLog( string $mode ):void
  {
    $insert = [
      'source' => $mode,
      'script' => self::$module,
      'time' => date('Y.m.d G:i:s'),
      'status' => 'RUN',
    ];

    self::$panel->insert('ozon_tech_log', [$insert]);
  }

  public static function initConnection( DBPanel $panel, string $module ):void
  {
    self::$panel = $panel;
    self::$module = $module;
  }

  public static function updateStatus( $text, $percent, $status = false, $start = false, $end = false ):void
  {
    $tmp = [
      'status' => $status,
      'status_text' => $text,
      'percent' => $percent,
      'time_start' => $start,
      'time_end' => $end,
    ];

    $where[] = [ 'column' => 'code', 'operator' => '=', 'value' => self::$module ];

    $update = array_filter(
      array: $tmp,
      callback: fn($val) => ($val !== false)
    );

    self::$panel->update( 'ozon_agents', $update, $where );
  }
}
 ?>
