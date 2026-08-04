<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

class CleanerWB
{
  private $cabinet; // Кабинет
  private $base_path; // Базовый путь к папке с логами
  private $threshold; // Сколько дней логов сохранять
  private $dbPanel; // Экземпляр класса базы данных (Панель)
  private $dbLeg; // Экземпляр класса базы данных (Основная)


  public function __construct( $cabinet, $isManual = false )
  {
    if ( !in_array($cabinet,['WR','TL']) ){
      die( 'Не выбран / Невалидный кабинет' );
    }
    $this->isManual = $isManual;
    $this->cabinet = $cabinet;
    $this->base_path = $_SERVER['DOCUMENT_ROOT'] . '/admin/panel/engine/wb/logs';
    $this->threshold = 5;

    global $DB;
    $this->dbLeg = $DB;
    CModule::IncludeModule('panel.manager');
    $this->dbPanel = new DBPanel;
  }

  public function clearProductsLogs():void
  {
    echo "<h3>Кабинет {$this->cabinet}</h3>";
    $logPath = $_SERVER["DOCUMENT_ROOT"] . "/admin/panel/engine/wb/logs/products/cron/" . $this->cabinet;
    $threshold = date( 'Y-m-d', strtotime('- 7 day') );
    exec("ls -l {$logPath}", $output);
    $first_file = end( explode( ' ', $output[1] ) );
    $file_date = explode( '_', $first_file )[1];
    $file_date = explode( '.txt', $file_date )[0];
    $diff = self::getDayDiff( $threshold, $file_date );
    if ( $diff <= 0 && $this->isManual ){
      print_r('<span>Нет файлов, соответствующих требованиям (старше 7-ми дней)<span><br>');
      return;
    }
    $files = [];
    for ( $i = 1; $i <= $diff; $i++ ){
      if ( $i < 10 ) {
        $k = "0" . "{$i}";
      }else{
        $k = $i;
      }
      $datePart = date( 'Y-m-d', strtotime($threshold . "-{$i} day") );
      $filename = 'products_' . $datePart . '.txt';
      if ( file_exists($logPath . '/' . $filename) ){
        print_r($logPath . '/' . $filename . " -- <span style='color:rgba(255,0,0,0.7)'>Удалено</span><br>" );
        unlink( $logPath . '/' . $filename );
      }
    }
    // foreach ( $files as $file ){
    // }
  }

  public function clearOrdersLogs():void
  {
    echo "<h3>Кабинет {$this->cabinet}</h3>";
    $logPath = $_SERVER["DOCUMENT_ROOT"] . "/admin/panel/engine/wb/logs/orders/" . $this->cabinet;
    $threshold = date( 'Y-m-d', strtotime('- 30 day') );
    exec("ls -l {$logPath}", $output);
    $first_file = end( explode( ' ', $output[1] ) );
    $file_date = explode( '_', $first_file )[0];
    $diff = self::getDayDiff( $threshold, $file_date );
    if ( $diff <= 0 && $this->isManual ){
      print_r('<span>Нет файлов, соответствующих требованиям (старше 30-ти дней)<span><br>');
      return;
    }
    $files = [];
    for ( $i = 1; $i <= $diff; $i++ ){
      if ( $i < 10 ) {
        $k = "0" . "{$i}";
      }else{
        $k = $i;
      }
      $datePart = date( 'Y-m-d', strtotime($threshold . "-{$i} day") );
      $filenameOrd =  $datePart . '_orders.txt';
      $filenameUpd =  $datePart . '_orders_update.txt';
      if ( file_exists($logPath . '/' . $filenameOrd) ){
        print_r($logPath . '/' . $filenameOrd . " -- <span style='color:rgba(255,0,0,0.7)'>Удалено</span><br>" );
        unlink( $logPath . '/' . $filenameOrd );
      }
      if ( file_exists($logPath . '/' . $filenameUpd) ){
        print_r($logPath . '/' . $filenameUpd . " -- <span style='color:rgba(255,0,0,0.7)'>Удалено</span><br>" );
        unlink( $logPath . '/' . $filenameUpd );
      }
      // $files = array_merge( $files, glob($logPath .'/'. $filename) );

    }
    // foreach ( $files as $file ){
    // }
  }

  public function clearPricesLogs():void
  {
    $arPath = [
      'stock' => $_SERVER["DOCUMENT_ROOT"] . "/admin/panel/engine/wb/logs/prices/" . $this->cabinet,
      'reqests' => $_SERVER["DOCUMENT_ROOT"] . "/admin/panel/engine/wb/logs/reqests/prices/" . $this->cabinet
    ];
    echo "<h3>Кабинет {$this->cabinet}</h3>";
    foreach ( $arPath as $key => $logPath ){
      // $logPath = $_SERVER["DOCUMENT_ROOT"] . "/admin/panel/engine/wb/logs/prices/" . $this->cabinet;
      $threshold = date( 'Y-m-d', strtotime('- 7 day') );
      exec("ls -l {$logPath}", $output);
      $first_file = end( explode( ' ', $output[1] ) );
      $file_date = explode( '.', $first_file )[0];
      $diff = self::getDayDiff( $threshold, $file_date );

      if ( $diff <= 0 && $this->isManual ){
        print_r('<span>Нет файлов ('.$key.'), соответствующих требованиям (старше 7-ми дней)<span><br>');
        continue;
      }
      $files = [];
      for ( $i = 1; $i <= $diff; $i++ ){
        if ( $i < 10 ) {
          $k = "0" . "{$i}";
        }else{
          $k = $i;
        }
        $datePart = date( 'Y-m-d', strtotime($threshold . "-{$i} day") );
        $filename =  $datePart . '.txt';
        if ( file_exists($logPath . '/' . $filename) ){
          print_r($logPath . '/' . $filename . " -- <span style='color:rgba(255,0,0,0.7)'>Удалено</span><br>" );
          unlink( $logPath . '/' . $filename );
        }
      }
    }
  }

  public function clearStocksLogs():void
  {
    $arPath = [
      'stock' => $_SERVER["DOCUMENT_ROOT"] . "/admin/panel/engine/wb/logs/stock/" . $this->cabinet,
      'reqests' => $_SERVER["DOCUMENT_ROOT"] . "/admin/panel/engine/wb/logs/reqests/stock/" . $this->cabinet
    ];
    echo "<h3>Кабинет {$this->cabinet}</h3>";
    foreach ( $arPath as $key => $logPath ){
      // $logPath = $_SERVER["DOCUMENT_ROOT"] . "/admin/panel/engine/wb/logs/prices/" . $this->cabinet;
      $threshold = date( 'Y-m-d', strtotime('- 7 day') );
      exec("ls -l {$logPath}", $output);
      $first_file = end( explode( ' ', $output[1] ) );
      $file_date = explode( '.', $first_file )[0];
      $diff = self::getDayDiff( $threshold, $file_date );

      if ( $diff <= 0 && $this->isManual ){
        print_r('<span>Нет файлов ('.$key.'), соответствующих требованиям (старше 7-ми дней)<span><br>');
        continue;
      }
      $files = [];
      for ( $i = 1; $i <= $diff; $i++ ){
        if ( $i < 10 ) {
          $k = "0" . "{$i}";
        }else{
          $k = $i;
        }
        $datePart = date( 'Y-m-d', strtotime($threshold . "-{$i} day") );
        $filename =  $datePart . '.txt';
        // print_r($logPath . '/' . $filename . "<br>");

        if ( file_exists($logPath . '/' . $filename) ){
          print_r($logPath . '/' . $filename . " -- <span style='color:rgba(255,0,0,0.7)'>Удалено</span><br>" );
          unlink( $logPath . '/' . $filename );
        }
      }
      // foreach ( $files as $file ){
      // }
    }
  }

  public function checkLogsSize():array
  {
    $arTree = [
      'orders' => [
        'WR' => '',
        'TL' => '',
      ],
      'products' => [
        'cron' => [
          'WR' => '',
          'TL' => '',
        ]
      ],
      'prices' => [
        'WR' => '',
        'TL' => '',
      ],
      'reqests' => [
        'prices' => [
          'WR' => '',
          'TL' => '',
        ],
        'stock' => [
          'WR' => '',
          'TL' => '',
        ],
      ],
      'stock' => [
        'WR' => '',
        'TL' => '',
      ]
    ];

    $arTreeRes = [];

    foreach ( $arTree as $type => $arFolder ){
      foreach ( $arFolder as $nestedFolder => $folder ){
        if ( is_array($folder) ){
          foreach ( $folder as $cab => $value ){
            exec("du -sh {$this->base_path}/{$type}/{$nestedFolder}/{$cab}", $output);
            $arTreeRes[$type][$nestedFolder][$cab] = trim( explode('/', $output[0])[0] );
            unset($output);
          }
        }else{
          exec("du -sh {$this->base_path}/{$type}/{$nestedFolder}", $output);
          $arTreeRes[$type][$nestedFolder] = trim( explode('/', $output[0])[0] );
          unset($output);
        }
      }
    }

    return $arTreeRes;
  }

  public function checkTablesSize():int
  {
    global $DB;
    $dbLeg = $DB;
    $strSql = "SELECT * FROM wdhs_wb_orders";
    $resultDB = $dbLeg->Query( $strSql, false, $err_mess.__LINE__);
    return $resultDB->SelectedRowsCount();
  }

  static function getDayDiff(string $date2, string $date1): int
  {
    $time1 = strtotime($date1 . '-01');
    $time2 = strtotime($date2 . '-01');

    $days1 = date('Y', $time1) * 12 + date('d', $time1);
    $days2 = date('Y', $time2) * 12 + date('d', $time2);

    $difference = $days2 - $days1;

    return $difference;
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

// (new CleanerWB('TL'))->checkLogsSize();


 ?>
