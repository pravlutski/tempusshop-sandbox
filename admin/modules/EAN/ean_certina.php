<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
CModule::IncludeModule('panel.manager');

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class RadoEAN
{

  private $items;
  private $sections;
  private $logPath;

  public function __construct(){

  }

  public function run()
  {
    // $this->getEbayUpc();
    $this->getItems();
    $this->getCodes1();
    $this->getCodes2();
    $this->buildExcel();
  }

  private function getItems():void
  {
    $brands = $this->getBrands();
    $sections = $this->getSections();
    $arFilter = [
      'IBLOCK_ID' => 16,
      'PROPERTY_BRAND' => 88200,
      '>=DATE_CREATE' => '01.01.2023 00:00:00'
    ];
    $arSelect = ['ID', 'IBLOCK_ID', 'PROPERTY_CML2_ARTICLE', 'PROPERTY_BRAND', 'IBLOCK_SECTION_ID'];
    $result = CIBlockElement::GetList(array(), $arFilter, false, false, $arSelect);
    while ( $row = $result->GetNextElement() ){
      $item = $row->GetFields();
      $this->items[$item['PROPERTY_CML2_ARTICLE_VALUE']] = [
        'model' => $item['PROPERTY_CML2_ARTICLE_VALUE'],
        'brand' => $brands[ $item['PROPERTY_BRAND_VALUE'] ],
        'section' => $sections[ $item['IBLOCK_SECTION_ID'] ],
        'code' => ''
      ];
    }
  }

  private function filterGoods():array
  {
    $emptyCodes = [];
    foreach ( $this->items as $key => $arModel){
      if( empty($arModel['code']) ){
        $emptyCodes[$arModel['model']] = $arModel;
      }
    }
    return $emptyCodes;
  }

  private function getCodes1():void
  {
    if ( empty($this->items) ) return;
    $i = 0;
    foreach ( $this->items as $key => $codes ){
      $url = "https://www.upcitemdb.com/query?s={$codes['model']}&type=2";
      // var_dump($url);
      // die;
      $ch = curl_init();
      curl_setopt($ch, CURLOPT_URL, $url);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
      curl_setopt($ch, CURLOPT_TIMEOUT, 10);
      $html = curl_exec($ch);
      preg_match('/EAN\s[0-9]+\s/', $html, $ean_match);
      foreach ( $ean_match as $ean ){
        $this->items[ $key ]['code'] = trim( explode('EAN', $ean)[1] );
      }
      unset( $ean_match );
      unset( $ean );

      preg_match('/UPC\s[0-9]+\s/', $html, $upc_match);
      foreach ( $ean_match as $upc ){
        if ( !empty($this->items[ $key ]['code']) ) break;
        $this->items[ $key ]['code'] = trim( explode('UPC', $ean)[1] );
      }
      unset( $upc_match );
      unset( $upc );

      // $dom = new DOMDocument();
      // libxml_use_internal_errors(true);
      // $dom->loadHTML( $html );
      // libxml_clear_errors();
      //
      // $xpath = new DOMXPath($dom);
    }
    sleep( rand(4, 7) );
    // file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/admin/modules/EAN/fileteredR.json', json_encode($this->items));
  }

  private function getCodes2():void
  {
    $i = 0;
    $models = $this->filterGoods();
    if ( empty($models) ) return;
    foreach ( $models as $key => $arModel ){

      // if ( $i == 7 ) return;

      $brand = mb_strtolower($arModel['brand']);
      $model = str_replace('.','-', mb_strtolower($arModel['model']) );
      $path = "{$brand}-watch-{$model}";


      $url = "https://www.watchmaxx.com/{$path}";
      // var_dump($url);

      $ch = curl_init();
      curl_setopt($ch, CURLOPT_URL, $url);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
      curl_setopt($ch, CURLOPT_TIMEOUT, 10);
      $html = curl_exec($ch);

      $dom = new DOMDocument();
      libxml_use_internal_errors(true);
      $dom->loadHTML( $html );
      libxml_clear_errors();

      $xpath = new DOMXPath($dom);
      for ( $k = 6; $k >= 0; $k--){
        $field = $xpath->query('//p[@class="value___1Bpjp"]')->item($k)->textContent;
        if ( preg_match('/[0-9]+/', $field) ){
          $nodes = $field;
          break;
        }else{
          $nodes = '';
        }
      }
      // var_dump( $nodes);

      $this->items[$key]['code'] = $nodes;

      // if ( $i % 3 == 0 ){
        sleep( rand(3, 7) );
      // }
      $i++;
    }
  }

  private function buildExcel():void
  {
    if ( empty($this->items) ) return;

    require $_SERVER['DOCUMENT_ROOT'] . '/local/vendor/autoload.php';

    if (!class_exists('SpreadsheetReader')){
      require($_SERVER["DOCUMENT_ROOT"] . '/bitrix/php_interface/include/classes/excel/excel_reader.php');
      require($_SERVER["DOCUMENT_ROOT"] . '/bitrix/php_interface/include/classes/excel/SpreadsheetReader.php');
      require($_SERVER["DOCUMENT_ROOT"] . '/bitrix/php_interface/include/classes/phpexcel/PHPExcel/IOFactory.php');
    }

    $xls = new PHPExcel();
    $xls->setActiveSheetIndex(0);
    $sheet = $xls->getActiveSheet();
    $sheet->setTitle('listOne');

    $alphabet = range('A', 'Z');
    $i = 0;

    foreach ( $this->items as $key => $arModel ){
      $sheet->setCellValueExplicit("A{$i}", $arModel['model'], PHPExcel_Cell_DataType::TYPE_STRING);
      if ( is_array($arModel['code']) ){
        foreach ($arModel['code'] as $key => $c) {
            $sheet->setCellValueExplicit("{$alphabet[$key + 1]}{$i}", $c, PHPExcel_Cell_DataType::TYPE_STRING);
        }
      }else{
        $sheet->setCellValueExplicit("B{$i}", $arModel['code'], PHPExcel_Cell_DataType::TYPE_STRING);
      }
      $i++;
    }
    $objWriter = new PHPExcel_Writer_Excel2007($xls);
    $dirPath = $_SERVER["DOCUMENT_ROOT"] . '/admin/modules/EAN/';
    $filename = 'EAN_CERTINA_FILTERED.xlsx';
    $objWriter->save( $dirPath . $filename );
  }

  private function getSections():array
  {
    $res = CIBlockSection::GetList(
      Array("SORT"=>"ASC"),
      Array(),
      false,
      Array('ID','NAME'),
      false
    );
    $sections = [];
    while ( $row = $res->GetNextElement() ){
      $item = $row->GetFields();
      $sections[ $item['ID'] ] = $item['NAME'];
    }
    return $sections;
  }

  private function getBrands():array
  {
    $arFilter = Array(
      "IBLOCK_ID" => CProSet::IB_BRANDS,
    );
    $result = CIBlockElement::GetList(Array(), $arFilter, false, false, array("ID", "NAME"));
    $brands = [];
    while ( $arFields = $result->GetNext() ){
      $brands[ $arFields["ID"] ] = $arFields["NAME"];
    }
    return $brands;
  }
}

( new RadoEAN )->run();


 ?>
