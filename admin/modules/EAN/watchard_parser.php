<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
CModule::IncludeModule('panel.manager');

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class WatchardParser
{
  private $items; // Спаршенные товары
  private $catalogLink;
  private $links; // Спаршенные ссылки на товары
  private $brand; // Бренд, который будет парсится
  private $page; // Номер страницы, с которой будут ссылки тянутся, или количество страниц, начиная с первой
  private $singlePage; // Флаг, который определяет трактовку переменной $page

  public function __construct( string $brand, int $page, bool $singlePage = true )
  {
    $this->brand = mb_strtolower( $brand );
    $this->catalogLink = "https://watchard.com/{$this->brand}-watches";
    $this->page = $page;
    $this->singlePage = $singlePage;
  }

  public function run():void
  {
    $this->getLinks();
    $this->parseItems();
    $this->buildExcel();
  }

  private function getLinks():void
  {
    $this->links = [];
    if ( !$this->singlePage ){
      for ( $i = 1; $i <= $this->page; $i++ ){
        $html = $this->request( $this->catalogLink, $i );
        preg_match_all('/\"https:\/\/watchard\.com\/'.$this->brand.'-.+-watch\"/', $html, $matches);
        $this->links = array_merge( $this->links, $matches[0] );
        sleep( rand(3, 5) );
      }
      $this->links = array_map( function($item){
        return str_replace( '"', '', $item);
      }, $this->links );
      $this->links = array_unique( $this->links );
      return;
    }

    $html = $this->request( $this->catalogLink, $this->page );
    preg_match_all('/\"https:\/\/watchard\.com\/'.$this->brand.'-.+-watch\"/', $html, $matches);
    $this->links = $matches[0];
    $this->links = array_map( function($item){
      return str_replace( '"', '', $item);
    }, $this->links );

    $this->links = array_unique( $this->links );
    sleep( 6 );
  }

  private function parseItems():void
  {
    if ( empty($this->links) ){
      trigger_error( "There are no links to parse", E_USER_ERROR );
    }

    foreach ( $this->links as $link ){
      $html = $this->request( $link, 1 );

      if ( empty($html) ){
        sleep( 4 );
        continue;
      }
      $dom = new DOMDocument();
      libxml_use_internal_errors(true);
      $dom->loadHTML( $html );
      libxml_clear_errors();

      $xpath = new DOMXPath($dom);

      $vendorCode = $xpath->query('//td[@data-th="Model"]')->item(0)->textContent;
      $vendorCode = explode( ' ', trim($vendorCode) )[0];

      $ean = $xpath->query('//td[@data-th="EAN Code"]')->item(0)->textContent;
      $ean = trim( $ean );
      $this->items[] = [
        'brand' => $this->brand,
        'model' => $vendorCode,
        'ean' => empty( $ean ) ? '' : $ean
      ];
      sleep( rand(5, 7) );
    }

  }

  private function buildExcel():void
  {
    if ( empty($this->items) ) trigger_error( "There are no items for writing in a file", E_USER_ERROR );

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
      $sheet->setCellValueExplicit("B{$i}", $arModel['ean'], PHPExcel_Cell_DataType::TYPE_STRING);
      $i++;
    }

    $objWriter = new PHPExcel_Writer_Excel2007( $xls );
    $dirPath = $_SERVER["DOCUMENT_ROOT"] . '/admin/modules/EAN/';
    $flag = $this->singlePage ? 'true' : false;
    $filename = "WATCHARD_{$this->brand}_page_{$this->page}_singlePage_{$flag}.xlsx";
    $objWriter->save( $dirPath . $filename );
  }

  private function request( string $link , int $page ):string
  {
    if ( $page <= 0 ) trigger_error( "property \$page must be greater than zero", E_USER_ERROR );

    $url = $link;
    if ( $page != 1 ) $url .= "?p={$page}";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $html = curl_exec($ch);

    return $html;
  }

}

( new WatchardParser( 'tissot', 6, false ) )->run();

 ?>
