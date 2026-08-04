<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
require($_SERVER["DOCUMENT_ROOT"]."/admin/modules/KaspiFeedModule/lib/Array2Xml.php");

use Lalit\Array2Xml;

class FeedBuilder
{
  private $items;
  private $db;
  private $xmlObj;
  private $filename;
  private $logPath;

  function __construct()
  {
    global $DB;
    $this->db = $DB;
    $this->filename = '/var/www/bitrix/data/www/tempusshop.ru/bitrix/catalog_export/export_kaspi.xml';
    $this->logPath = '/var/www/bitrix/data/www/tempusshop.ru/admin/modules/KaspiFeedModule/logs/log.txt';
    $this->brandsHG = [75585,182462,7973,37278,180505,7972,37235,7975,7974,43588,88200,125923];
  }

  public function run()
  {
    $this->writeLog('START');
    $this->getItems();
    $this->buildFeed();
    $this->writeLog('END');
    $this->writeLog(' ');
  }

  private function getBrands()
  {
    $arFilter = Array(
      "IBLOCK_ID" => CProSet::IB_BRANDS,
    );
    $result = CIBlockElement::GetList(Array(), $arFilter, false, false, array("ID", "NAME")); //Получаем названия всех брендов
    while($arFields = $result->GetNext()){
      $brands[$arFields["ID"]] = $arFields["NAME"];
    }
    return $brands;
  }

  private function getItems()
  {
    $brands = $this->getBrands();
    if ( empty($brands) ){
      $this->writeLog('Не удалось получить бренды');
      return false;
    }

    $goodsKZ = $this->getActiveGoodsKZ();
    if ( empty($goodsKZ) ){
      $this->writeLog('Не удалось полчить активные на KZ товары');
      return false;
    }

    $arFilter = Array(
    	"IBLOCK_ID"	=> 16,
      "ID" => $goodsKZ
    );
    $arSelect = array("ID", "IBLOCK_ID", "XML_ID", "PROPERTY_CML2_ARTICLE", "PROPERTY_NAME_MARKETPLACE", "PROPERTY_BRAND", "PROPERTY_AVAILABILITY_KZ");
    $rs = CIBlockElement::GetList( array(), $arFilter, false, false, $arSelect );
    while($arItem = $rs->GetNext()){
      $rsPrice = CPrice::GetList( array(),["PRODUCT_ID" => $arItem["ID"]], false, false, array() );
      while($arPrice = $rsPrice->GetNext()){
        if ($arPrice['CURRENCY'] == 'KZT'){
          $priceKZ = intval($arPrice['PRICE']);
        }
      }
      if ( in_array($arItem['PROPERTY_BRAND_VALUE'], $this->brandsHG) ){
        $storeId = 'PP2';
      }else{
        $storeId = 'PP1';
      }
      if ( empty($priceKZ) ){
        $this->writeLog($arItem['PROPERTY_CML2_ARTICLE_VALUE'] . ' --- нет цены. Исключаем из фида');
        continue;
      }
      $this->items[] = [
        '@attributes' => [
          'sku' => $arItem['XML_ID']
        ],
        'model' => $arItem['PROPERTY_NAME_MARKETPLACE_VALUE'],
        'brand' => in_array($arItem['PROPERTY_BRAND_VALUE'], [7971, 179977]) ? mb_strtoupper($brands[ $arItem['PROPERTY_BRAND_VALUE'] ]) : $brands[ $arItem['PROPERTY_BRAND_VALUE'] ],
        'availabilities' => [
          'availability' => [
            '@attributes' => [
                'available' => 'yes',
                'storeId' => $storeId,
            ]
          ]
        ],
        'price' => $priceKZ
      ];
    }
    $this->writeLog('В фид будет добавлено ' . count($this->items) . ' товаров');
  }

  private function getActiveGoodsKZ()
  {

    $strSql = "SELECT bitrix_id FROM ci_price WHERE active_kz = 'Y'";
    $resultDB = $this->db->Query($strSql, false, $err_mess.__LINE__);
    $activityKZ = [];
    while( $row = $resultDB->Fetch() ){
      $activityKZ[] = $row['bitrix_id'];
    }
    $this->writeLog('Товаров для KZ активно ' . count($activityKZ) );
    return $activityKZ;
  }

  private function buildFeed()
  {
    if( empty($this->items) ){
      $this->writeLog('Нет товаров для формирования фида');
      return false;
    }
    $this->writeLog('Начинаем сборку фида');
    $feedArray = [
      '@attributes' => [
        'date' => date('Y-m-d') . 'T' . date('G:i:s') . '+05:00',
        'xmlns' => 'kaspiShopping',
        'xmlns:xsi' => 'http://www.w3.org/2001/XMLSchema-instance',
        'xsi:schemaLocation' => 'kaspiShopping http://kaspi.kz/kaspishopping.xsd'
      ],
      'company' => 'ТОО "Вотчес-Маркет"',
      'merchantid' => 30294326,
      'offers' => [
        'offer' => $this->items,
      ]
    ];
    $xml = Array2Xml::createXML('kaspi_catalog', $feedArray);
    $xml->save($this->filename);
    $this->writeLog('Фид собран успешно');
  }

  private function writeLog($message)
  {
    file_put_contents($this->logPath, date('d-m-Y G:i:s'). ' --- ' . $message . PHP_EOL, FILE_APPEND);
  }

}

(new FeedBuilder())->run();

 ?>
