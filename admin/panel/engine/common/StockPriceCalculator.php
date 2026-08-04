<?php
class DynamicPriceCalculator
{
  private object $dbMain;
  private ?DBPanel $dbPanel;

  private string $marketplace;
  private array $stockDays;
  private array $log;

  private array $settingsKeys = [
    'dp_threshold', 'step', 'discount', 'max_discount'
  ];

  private array $filePathsWH = [
    'OZON' => '/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/ozon/configs/warehouses_fbo.json',
    'WB' => '/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/wb/configs/warehouses_fbo.json',
  ];

  private array $filePathsExport = [
    'OZON' => '/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/ozon/export/stockDaysOzon.csv',
    'WB' => '/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/wb/export/stockDaysWB.csv',
  ];

  private array $allowedWarehouses;
  private array $settings;

  public function __construct( string $marketplace )
  {
    if ( !in_array($marketplace, ['OZON', 'WB']) ){
      throw new InvalidArgumentException("'{$marketplace}' - Inacceptable value");
    }

    $this->marketplace = $marketplace;
    $this->loadModules();
    $this->getSettings();
    $this->getStatTable();
  }

  private function loadModules()
  {
    CModule::IncludeModule('panel.manager');
    $this->dbPanel = new DBPanel;
    $this->dbMain = \Bitrix\Main\Application::getConnection();
  }

  private function getSettings():void
  {
    $rows = [];
    $items = [];

    switch ( $this->marketplace ){
      case 'OZON':
        $rows = $this->dbPanel->select(['*'], 'ozon_main_settings_IP')->make();
        foreach ( $rows as $row ){
          $items[ $row['name'] ] = $row['value'];
        }
        break;
      case 'WB':
        $strSql = "SELECT settings FROM wdhs_wb_main_settings WHERE cabinet = 'WR'";
        $result = $this->dbMain->Query( $strSql )->Fetch()['settings'];
        $items = json_decode($result, true);
        break;
    }
    foreach ( $this->settingsKeys as $value ){
      $this->settings[$value] = $items[$value] ?? false;
    }

    $this->getAllowedWarehouses( $this->marketplace );
  }

  private function getAllowedWarehouses( string $mp ):void
  {
    $file = $this->filePathsWH[ $mp ];

    if ( !file_exists($file) ){
      $this->allowedWarehouses = [];
      return;
    }
    $json = file_get_contents( $file );

    if ( $json === false ){
      throw new Exception("Error occured with alowed warehouse list file: {$file}. Invalid file or access denied\n");
    }
    $data = json_decode($json, true);
    if ( $data === false ){
      throw new Exception("Invalid warehouse list json structure\n");
    }
    $this->allowedWarehouses = $data;
  }

  private function validateSettings():bool
  {
    foreach ( $this->settings as $key => $value ){
      if ( $value === false ) return false;
    }
    return true;
  }

  private function getTopModels():array
  {
    $items = [];
    $rows = [];

    switch ( $this->marketplace ){
      case 'OZON':
        $table = 'ozon_top_models';
        break;
      case 'WB':
        $table = 'wb_top_models';
        break;
    }

    $rows = $this->dbPanel->select(['model'], $table)->make();

    foreach ( $rows as $row ){
      $items[] = $row['model'];
    }

    return $items;
  }

  private function getStatTable():void
  {
    $items = [];
    $rows = [];

    switch ( $this->marketplace ){
      case 'OZON':
        $rows = $this->getStatTableOzon();
        break;
      case 'WB':
        $rows = $this->getStatTableWB();
        break;
    }

    if ( empty($rows) ) return;
    $topModels = $this->getTopModels();

    foreach ( $rows as $row ){
      $items[ $row['model'] ][] = strtotime( $row['date'] );
    }

    $res = [];
    foreach ( $items as $model => $arDate ){
      if ( in_array($model, $topModels) ) continue;
      $res[ $model ] = $this->getStockDays( $arDate );
    }

    $this->stockDays[ $this->marketplace ] = $res;
  }

  private function getStatTableOzon():array
  {
    // Делаем выборку, отталкиваясь от того, что есть на последнюю дату
    $strSql = "SELECT * FROM `ozon_stock_fbo_stat` WHERE date = (SELECT max(date) FROM `ozon_stock_fbo_stat`)";
    $res = $this->dbMain->Query( $strSql );
    $modelsToday = [];
    while ( $row = $res->Fetch() ){
      $modelsToday[] = $row['model'];
    }

    unset( $row );
    $modelsToday = array_map(function($item){
      return "'".$item."'";
    }, $modelsToday);
    $whereIn = implode( ',', $modelsToday );

    $strSql = "SELECT * FROM `ozon_stock_fbo_stat` WHERE model IN ({$whereIn})";

    $res = $this->dbMain->Query( $strSql );
    $result = [];
    while ( $row = $res->Fetch() ){
      $result[] = [
        'model' => $row['model'],
        'date' => $row['date'],
      ];
    }

    return $result;
  }

  private function getStatTableWB():array
  {
    // Делаем выборку, отталкиваясь от того, что есть на последнюю дату
    $strSql = "SELECT * FROM `wb_fbo_stat_WR` WHERE stock_date = (SELECT max(stock_date) FROM `wb_fbo_stat_WR`)";
    $res = $this->dbPanel->query( $strSql );
    $rows = $this->dbPanel->fetchAll( $res );
    $modelsToday = [];
    foreach ( $rows as $row ){
      $modelsToday[] = $row['model'];
    }

    $modelsToday = array_map(function($item){
      return "'".$item."'";
    }, $modelsToday);
    $whereIn = implode( ',', $modelsToday );

    $strSql = "SELECT * FROM `wb_fbo_stat_WR` WHERE model IN ({$whereIn})";

    $res = $this->dbPanel->query( $strSql );
    $rows = $this->dbPanel->fetchAll( $res );
    $result = [];
    foreach ( $rows as $row ){
      $result[] = [
        'model' => $row['model'],
        'date' => $row['stock_date'],
      ];
    }

    return $result;
  }

  private function getStockDays( array $arDate ):int
  {
    $unsorted = $arDate;
    sort($unsorted);
    $sorted = array_reverse( $unsorted );

    $res = 0;
    $refVal = 0;

    foreach ( $sorted as $k => $value ){
      if ( $k == 0 ){
        $refVal = $value;
        continue;
      }

      $diff = ($refVal - $value) / 60 / 60 / 24;

      if ( $diff == 1 ){
        $res += 1;
        $refVal = $value;
        continue;
      }
      break;
    }

    return $res;
  }

  private function arrayToCsv($data, $filename = "export.csv")
  {
      // Открываем файл для записи
      $file = fopen($filename, 'w');

      // Добавляем BOM для корректного отображения кириллицы в Excel
      // fputs($file, $bom = (chr(0xEF) . chr(0xBB) . chr(0xBF)));

      // Записываем заголовки (если массив ассоциативный)
      if (!empty($data)) {
          $firstRow = $data[0];
          if (is_array($firstRow)) {
              fputcsv($file, array_keys($firstRow));
          }

          // Записываем данные
          foreach ($data as $row) {
              fputcsv($file, $row);
          }
      }

      fclose($file);
  }

  private function hasAllowedWarehouses( array $productWH ):bool
  {
    $result = array_intersect( $productWH, $this->allowedWarehouses );

    return count( $result ?? [] ) > 0;
  }

  public function getStockDaysCsv():void
  {
    $this->getStatTable();
    $data = $this->stockDays[ $this->marketplace ];
    $export = [];

    foreach ( $data as $model => $days ){
      $export[] = [
        'model' => $model,
        'days' => $days,
      ];
    }
    $this->arrayToCsv(
      data: $export,
      filename: $this->filePathsExport[ $this->marketplace ]
    );
  }

  public function getLogByModel( string $model ):array|false
  {
    return $this->log[$model] ?? false;
  }

  public function calculateDiscount( string $model, int|float $price, array $warehouses ):int|float
  {

    if ( empty($model) ){
      throw new InvalidArgumentException('$model cannot be an empty string');
    }
    if ( empty($price) || $price <= 0 ){
      throw new InvalidArgumentException('$price must be greater than zero');
    }
    if ( !$this->validateSettings() ){
      throw new Exception('Invalid settings array');
    }
    if ( !$this->hasAllowedWarehouses($warehouses) ){
      $messageWH = implode( ', ', $warehouses );
      $this->log[ $model ]['message'] = "Нет в списке разрешенных складов. Товар находится на {$messageWH}";
      $this->log[ $model ]['discount'] = 0;
      return $price;
    }

    $stockDays = $this->stockDays[$this->marketplace][$model] ?? false;

    if ( $stockDays === false ){
      $this->log[ $model ]['message'] = "Нет данных в таблице или ТОП позиция. Скидка не установлена";
      $this->log[ $model ]['discount'] = 0;
      return $price;
    }

    $validDays = $stockDays - intval( $this->settings['dp_threshold'] );
    if ( $validDays <= 0 ){
      $this->log[ $model ]['message'] = "Не достигнуто пороговое значение. Скидка не установлена";
      $this->log[ $model ]['discount'] = 0;
      return $price;
    }

    $discountMultiplier = floor( $validDays / intval($this->settings['step']) );
    if ( $discountMultiplier <= 0 ){
      $this->log[ $model ]['message'] = "Не достигнуто пороговое значение. Скидка не установлена";
      $this->log[ $model ]['discount'] = 0;
      return $price;
    }

    $this->log[ $model ]['settings'] = $this->settings;

    $discount = intval($this->settings['discount']) * intval($discountMultiplier);
    if ( $discount > intval($this->settings['max_discount']) ){
      $appliedDiscount = intval($this->settings['max_discount']);
      $this->log[ $model ]['message'] = "Установлена максимальная скидка";
    }else{
      $this->log[ $model ]['message'] = "Установлена скидка";
      $appliedDiscount = $discount;
    }

    $this->log[ $model ]['stockDays'] = $stockDays;
    $this->log[ $model ]['mutiplier'] = $discountMultiplier;
    $this->log[ $model ]['discount'] = $appliedDiscount;

    $resultPrice = $price * ( 1 - $appliedDiscount / 100 );

    return round($resultPrice,  1);
  }

}

 ?>
