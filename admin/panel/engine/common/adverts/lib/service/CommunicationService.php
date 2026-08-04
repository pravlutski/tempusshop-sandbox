<?php
class CommunicationService
{
  private static string $callerClass = '';
  private static string $dividerClass = "-------------------------------------\n";
  private static string $dividerIteration = "#####################################\n";
  private static bool $silent = false;

  public static function log( mixed $message, bool $onlyWrite = false ):void
  {
    $time = date( 'G:i:s' );
    $text = print_r( $message, true );

    $backtrace = debug_backtrace()[1];
    $callerClass = $backtrace['class'] ?? 'Global';
    $callerMethod = $backtrace['function'];
    $backtraceBlock = "[{$callerClass}::{$callerMethod}]";
    $printMessage = "{$backtraceBlock} {$time} --- {$text} " . PHP_EOL;

    if ( $message == 'START' ){
      $printMessage = self::$dividerIteration . $printMessage;
    }

    if ( self::$callerClass != $callerClass ){
      $printMessage = self::$dividerClass . $printMessage;
      self::$callerClass = $callerClass;
    }

    if ( !$onlyWrite ) print_r( $printMessage );
    if ( self::$silent ) return;

    file_put_contents(
      self::getPath( $callerClass ),
      $printMessage,
      FILE_APPEND|LOCK_EX
    );
  }

  public static function silence():void
  {
    self::$silent = true;
  }

  private static function getPath( string $callerClass ):string
  {
    $paths = Config::instance()->getLogPaths();

    return sprintf(
      $paths[ $callerClass ] ?? $paths['default'],
      Config::instance()->getPlatform(),
      date('Y-m-d'),
    );
  }
}
 ?>
