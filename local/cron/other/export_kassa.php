<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

class ExportProfileKassa
{
  private $header;
  private $items;
  private $brands;

  private $exportPath;
  private $filename;

  public function __construct()
  {
    $this->header = [
      "Код товара",
      "Штрихкод",
      "Бренд",
      "Артикул",
      "Наименование",
      "Тип механизма",
      "Цена",
    ];
    $this->exportPath = $_SERVER["DOCUMENT_ROOT"] . "/admin/export/kassa/";
    $this->filename = "export_kassa.csv";
  }

  public function run():void
  {
    $this->getBrands();
    $this->getItems();
    $this->exportCsv();
  }

  public function getBrands():void
  {
    $arFilter = Array(
      "IBLOCK_ID" => CProSet::IB_BRANDS,
    );
    $result = CIBlockElement::GetList(Array(), $arFilter, false, false, array("ID", "NAME"));
    while ( $arFields = $result->GetNext() ){
      $this->brands[ $arFields["ID"] ] = $arFields["NAME"];
    }

  }

  public function getItems():void
  {
    $arFilter = [
      "IBLOCK_ID" => 16,
      "PROPERTY_OZON_ACTIVE_VALUE" => "Да"
    ];
    $arSelect = ["ID", "IBLOCK_ID", "XML_ID", "PROPERTY_2823", "PROPERTY_TYPE", "PROPERTY_MECHANISM", "PROPERTY_BRAND", "NAME", "PROPERTY_CML2_ARTICLE", "CATALOG_PRICE_2", "PROPERTY_OZON_ACTIVE"];

    $res = CIBlockElement::GetList(array(), $arFilter, false, false, $arSelect);
    while ( $row = $res->GetNext() ){
      if ( empty($row["PROPERTY_2823_VALUE"]) ) continue;
      if ( stripos( $row["PROPERTY_2823_VALUE"], ',' ) ){
        $barcodes = explode( ',', $row["PROPERTY_2823_VALUE"] );
        $barcodes = array_map('trim', $barcodes);
        $clear_bc = [];
        foreach ( $barcodes as $key => $bc ){
          if ( preg_match("/^[0-9]+$/", $bc) && strlen($bc) <= 14 ){
            $clear_bc[] = $bc;
          }
        }

        foreach ( $clear_bc as $key => $bc ){
          // if ( count($clear_bc) == 1 ){
          //   $xml_id = $row["XML_ID"];
          // }else{
          //   $xml_id = $row["XML_ID"] . "/" . $key + 1;
          // }
          $this->items[] = [
            'xml_id' => $row['XML_ID']. "/" . $key + 1,
            "barcode" => $bc,
            "brand" => $this->brands[ $row["PROPERTY_BRAND_VALUE"] ],
            "vendorCode" => $row["PROPERTY_CML2_ARTICLE_VALUE"],
            "name" => $row["NAME"],
            'mechanism' => $row['PROPERTY_MECHANISM_VALUE'],
            "price" => $row["CATALOG_PRICE_2"]
          ];
        }
        continue;
      }
      if ( !preg_match("/^[0-9]+$/", $bc) ) continue;
      $this->items[] = [
        'xml_id' => $row['XML_ID'],
        "barcode" => $row['PROPERTY_2823_VALUE'],
        "brand" => $this->brands[ $row["PROPERTY_BRAND_VALUE"] ],
        "vendorCode" => $row["PROPERTY_CML2_ARTICLE_VALUE"],
        "name" => $row["NAME"],
        'mechanism' => $row['PROPERTY_MECHANISM_VALUE'],
        "price" => $row["CATALOG_PRICE_2"]
      ];
    }
  }

  public function exportCsv():void
  {
    if ( empty($this->items) ) die("ОШИБКА ВЫБОРКИ. МАССИВ ПУСТОЙ\n");
    $file = fopen($this->exportPath . $this->filename, "w");
    fputcsv($file, $this->header, ';');
    foreach ( $this->items as $item ){
      fputcsv($file, $item, ';');
    }
    fclose( $file );
  }


}

( new ExportProfileKassa )->run();


 ?>
