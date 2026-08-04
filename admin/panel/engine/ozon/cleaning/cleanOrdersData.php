<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

class OzonOrderCleaning{

  private $table_name;
  private $logPath;

  public function __construct( $cabinet )
  {
    switch( $cabinet ){
      case 'TI':
        $module_folder = 'OzonImport2';
        $log_folder = 'orders';
        $this->table_name = 'wdhs_ozon_orders_ti';
        break;
      case 'IP':
        $log_folder = 'orders';
        $module_folder = 'OzonImport';
        $this->table_name = 'wdhs_ozon_orders';
        break;
      case 'KZ':
        $log_folder = 'ordersKZ';
        $module_folder = 'OzonImport';
        $this->table_name = 'wdhs_ozon_orders_kz';
        break;
    }
    $this->logPath = $_SERVER['DOCUMENT_ROOT'] . "/admin/modules/{$module_folder}/logs/{$log_folder}";
  }

  public function run():void
  {
    $this->cleanLogDataAlt();
    $this->cleanTableData();
  }

  private function cleanLogData():void
  {
    $threshold = date( 'Y-m-01', strtotime('- 1 month') );
    exec("ls -l {$this->logPath}", $output);
    $first_file = end( explode( ' ', $output[1] ) );
    $file_date = explode( '_', $first_file )[0];
    $diff = self::getMonthDiff( $threshold, $file_date );

    for ( $i = 1; $i <= $diff; $i++ ){
      $datePart = date( 'Y-m', strtotime($threshold . "-{$i} month") ) . '-*';
      $filenameOrd = $datePart . '_orders.txt';
      $filenameUpd = $datePart . '_order_update.txt';
      // exec("rm {$this->logPath}/{$filenameOrd}");
      // exec("rm {$this->logPath}/{$filenameUpd}");
    }
  }

  private function cleanLogDataAlt():void
  {
    $threshold = date( 'Y-m-01', strtotime('- 1 month') );
    exec("ls -l {$this->logPath}", $output);
    $first_file = end( explode( ' ', $output[1] ) );
    $file_date = explode( '_', $first_file )[0];
    $diff = self::getMonthDiff( $threshold, $file_date );
    $files = [];
    for ( $i = 1; $i <= $diff; $i++ ){
      $datePart = date( 'Y-m', strtotime($threshold . "-{$i} month") ) . '-*';
      $filenameOrd = $datePart . '_orders.txt';
      $filenameUpd = $datePart . '_order_update.txt';

      $files = array_merge( $files, glob($this->logPath .'/'. $filenameOrd) );
      $files = array_merge( $files, glob($this->logPath .'/'. $filenameUpd) );
    }
    var_dump($files);
  }

  private function cleanTableData():void
  {
    global $DB;
    $threshold = date( 'Y-m-01', strtotime('- 1 month') );
    $timestamp = strtotime( $threshold );
    $strSql = "DELETE FROM {$this->table_name} WHERE timestamp < {$timestamp}";
    $DB->Query($strSql, false, $err_mess.__LINE__);
  }

  static function getMonthDiff(string $date2, string $date1): int
  {
    $time1 = strtotime($date1 . '-01');
    $time2 = strtotime($date2 . '-01');

    $months1 = date('Y', $time1) * 12 + date('m', $time1);
    $months2 = date('Y', $time2) * 12 + date('m', $time2);

    $difference = $months2 - $months1;

    return $difference;
  }

}

( new OzonOrderCleaning('IP') )->run();


 ?>
