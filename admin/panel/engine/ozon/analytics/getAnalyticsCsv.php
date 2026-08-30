<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
$workers = new WorkersChecker("panel_engine_ozon_analytics_getAnalyticsCsv_php");
if (!$workers->checkStatus()) {
	exit;
}
$workers->updateStatus("Y");

class AnalyticsCsv
{
  private $dbMain;
  private $dbPanel;
  private $hl;

  private int $maxInterval;
  private string $path = '/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/ozon/export/';
  private string $filename = 'cost_OZ.csv';

  public function __construct()
  {
    $this->loadModules();
  }

  private function loadModules():void
  {
    CModule::IncludeModule('panel.manager');
    $this->dbPanel = new DBPanel;
    $this->dbMain = \Bitrix\Main\Application::getConnection();
    $this->hl = new HighloadApi( 8 );
  }

  public function run():void
  {
    $items = $this->prepareData();
    $this->arrayToCsv( $items, $this->path . $this->filename );
  }

  private function prepareData():array
  {
    $items = $this->getCostDataBitrix();
    $reserved = $this->getReserved();
    $brands = $this->getBrands();
    $productsData = $this->getBitrixProductsData();
    $costFBO = $this->getCostFBO();

    $result = [];

    foreach ( $items as $model => $arItem ){
      $r = $reserved[$model] ?? 0;
      foreach ( $arItem as $itemPrice ){
        $restRes = $itemPrice['count'] - $r;
        if ( $restRes <= 0 ) continue;

        $result[] = [
          'Наименование' => $productsData[$model]['name'] ?? '',
          'Артикул' => $model,
          'Артикул WB' => $productsData[$model]['offer_id'] ?? '',
          'Себестоимость' => strval($costFBO[$model] ?? $itemPrice['price']),
        ];
        break;
      }
    }

    return $result;
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


  function getBitrixProductsData()
  {
    $arFilter = [
      'IBLOCK_ID' => 16,
      '!PROPERTY_WBARTICLE' => false,
    ];
    $arSelect = ['ID', 'IBLOCK_ID', 'NAME', 'PROPERTY_CML2_ARTICLE', 'PROPERTY_WBARTICLE'];
    $result = CIBlockElement::GetList([], $arFilter, false, false, $arSelect);
    $items = [];

    while ( $row = $result->GetNext() ){
      $items[ $row['PROPERTY_CML2_ARTICLE_VALUE'] ] = [
        'offer_id' => $row['PROPERTY_WBARTICLE_VALUE'],
        'name' => $row['NAME']
      ];
    }

    return $items;
  }

  private function getCostFBO():array
  {
    $rows = $this->dbPanel->select(['*'], 'ozon_fbo_sebes_IP')->make();
    $items = [];

    foreach ( $rows as $row ){
      $items[ $row['article'] ] = $row['cost'];
    }

    return $items;
  }

  private function getCostDataBitrix():array
  {
    $strSql = "SELECT model, price, count, brand_id FROM ci_price WHERE active_os = 'Y' AND model NOT IN ('ФУТЛЯР', 'КОРОБКА', 'КОРО') ORDER BY price ASC";

    $result = $this->dbMain->Query( $strSql );
    $items = [];

    while ( $row = $result->Fetch() ) {
      $items[ $row['model'] ][] = [
          'brand_id' => $row['brand_id'],
          'count' => $row['count'],
          'price' => $row['price'],
      ];
    }

    return $items ?? [];
  }

  private function getReserved():array
  {
    $strSql = "SELECT ARTICLE, RESERVED FROM ci_reserved";
    $result = $this->dbMain->Query( $strSql );
    $items = [];

    while ( $row = $result->Fetch() ){
      $items[ $row['ARTICLE'] ] = $row['RESERVED'];
    }

    return $items ?? [];
  }

  private function getBrands():array
  {
    $strSql = "SELECT id, name FROM ci_brands";
    $result = $this->dbMain->Query( $strSql );
    $items = [];

    while ( $row = $result->Fetch() ){
      $items[ $row['id'] ] = $row['name'];
    }

    return $items ?? [];
  }
}

( new AnalyticsCsv )->run();
$workers->updateStatus("N");
 ?>
