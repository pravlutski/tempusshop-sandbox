<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

class MegaExport
{
  private ?\Bitrix\Main\DB\MysqliConnection $main = null;
  private ?DBPanel $panel = null;
  private string $path = "/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/common/shitsExport.csv";

  public function __construct()
  {
    $this->main = \Bitrix\Main\Application::getConnection();
    $this->panel = new DBPanel;
  }

  public function run():void
  {
    $items = $this->getItems(
      nmids: $this->getNmids(),
      skus: $this->getSkus(),
      brands: $this->getBrands(),
    );

    $this->export( $items );
  }

  private function getItems( array $nmids, array $skus, array $brands ):array
  {
    $rows = CIBlockElement::getList(
      [],
      ['IBLOCK_ID' => 16],
      false, false,
      ["IBLOCK_ID", "ID", "NAME", "PROPERTY_CML2_ARTICLE", "PROPERTY_BRAND", "PROPERTY_WBARTICLE", "PROPERTY_WBARTICLE2", "PROPERTY_WBARTICLE3", "PROPERTY_GLASS", "PROPERTY_MECHANISM", "PROPERTY_FINALCOUNTRY", "PROPERTY_FACE"]
    );

    $result = [];

    while( $row = $rows->getNext() ){
      $result[] = [
        "id" => $row['ID'],
        "name" => $row['NAME'],
        "brand" => $brands[ $row['PROPERTY_BRAND_VALUE'] ],
        "vendorCode" => $row['PROPERTY_CML2_ARTICLE_VALUE'],
        "articleWBWR" => $row['PROPERTY_WBARTICLE2_VALUE'],
        "articleWBIP" => $row['PROPERTY_WBARTICLE3_VALUE'],
        "articleOZON" => $row['PROPERTY_WBARTICLE_VALUE'],
        "nmidWBWR" => $nmids[ $row['PROPERTY_CML2_ARTICLE_VALUE'] ]["WR"],
        "nmidWBIP" => $nmids[ $row['PROPERTY_CML2_ARTICLE_VALUE'] ]["TL"],
        "sku" => $skus[ $row['PROPERTY_CML2_ARTICLE_VALUE'] ],
        "mechanism" => implode( ', ' , $row['PROPERTY_MECHANISM_VALUE'] ),
        "face" => $row['PROPERTY_FACE_VALUE'],
        "finalCountry" => $row['PROPERTY_FINALCOUNTRY_VALUE'],
      ];
    }

    return $result;
  }

  private function getNmids():array
  {
    $strSql = "SELECT article, nmid, cabinet FROM wdhs_wb_props";
    $rows = $this->main->query( $strSql );
    $result = [];

    while ( $row = $rows->fetch() ){
      $result[ $row['article'] ][ $row['cabinet'] ] = $row['nmid'];
    }

    return $result;
  }

  private function getSkus():array
  {
    $rows = $this->panel->select(['*'], 'ozon_sku_dict_IP')->make();

    return array_column( $rows, 'sku', 'dict' );
  }

  private function getBrands():array
  {
    $rows = CIBlockElement::getList(
      [],
      ["IBLOCK_ID" => 11],
      false, false,
      ["ID", "IBLOCK_ID", "NAME"]
    );

    $result = [];

    while( $row = $rows->GetNext() ){
      $result[ $row['ID'] ] = $row['NAME'];
    }

    return $result;
  }

  private function export( array $items ):void
  {
    $headers = array_keys( reset($items) );
    $arFile[] = implode(';', $headers);

    foreach ( $items as $item ){
      $arFile[] = implode( ';', $item );
    }

    $file = implode(PHP_EOL, $arFile);

    file_put_contents( $this->path, $file );
  }
}

(new MegaExport)->run();
 ?>
