<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
require("lib/bootstrap.php");

class DPProfitDaily
{
  private ?DataProvider $dataProvider;
  private ?DBPanel $panel;
  private Bitrix\Main\DB\MysqliConnection $main;

  private string $productFilter = "product=https://api.moysklad.ru/api/remap/1.2/entity/product/%s";
  private string $access;

  public function __construct( string $marketplace, string $cabinet )
  {
    ValidationService::validateConstruct( $marketplace, $cabinet );
    ConfigProvider::init( $marketplace, $cabinet );
    $this->init();
  }

  public function run():void
  {
    $items = $this->dataProvider->getItems();
    $ids = $this->getItemsIds( array_keys( $items ) );
    $assort = $this->getMSAssortmentIds( array_values($ids) );

    $productFilter = array_map( function($item){
      return sprintf( $this->productFilter, $item );
    }, $assort);

    $dateFilter = [
      "momentFrom=".date('Y-m-d%2000:00:00'),
      "momentTo=".date('Y-m-d%2023:59:59')
    ];

    $profitItems = $this->getItemsProfit( $productFilter, $dateFilter );
    $resultData = $this->processProfitItems( $profitItems['rows'] );
    var_dump( $dateFilter );
    var_dump( $resultData );
  }

  private function init():void
  {
    CModule::IncludeModule('panel.manager');
    $ms = new MoyskladAPI('s1');

    $dbMain = \Bitrix\Main\Application::getConnection();
    $dbPanel = new DBPanel;

    $this->dataProvider = new DataProvider(
      items: new ItemsRepository( $dbMain, $dbPanel ),
      prices: new PricesRepository( $dbMain, $dbPanel ),
      settings: new SettingsRepository( $dbMain, $dbPanel )
    );

    $this->access = $ms->getAccessParams()['access_token'];
    $this->main = $dbMain;
    $this->panel = $dbPanel;
  }

  private function getItemsIds( array $models ):array
  {
    if ( empty($models) ) return [];

    $rows = CIBlockElement::GetList(
      [],
      ["IBLOCK_ID" => 16, "PROPERTY_CML2_ARTICLE" => $models],
      false,
      false,
      ["ID", "IBLOCK_ID", "PROPERTY_CML2_ARTICLE"]
    );

    $result = [];

    while ( $row = $rows->GetNext() ){
      $result[ $row['PROPERTY_CML2_ARTICLE_VALUE'] ] = $row['ID'];
    }

    return $result;
  }

  private function getMSAssortmentIds( array $ids ):array
  {
    if ( empty($ids) ) return [];

    $filter = implode( ',', $ids );
    $strSql = "SELECT BX_ID, MS_ID FROM ci_ms_assortment WHERE BX_ID IN ({$filter}) AND SITE_ID = 's1'";
    $rows = $this->main->Query( $strSql );
    $result = [];

    while ( $row = $rows->Fetch() ){
      $result[ $row['BX_ID'] ] = $row["MS_ID"];
    }

    return $result;
  }

  private function getItemsProfit( array $products = [], array $dates = [] ):array
  {
    $query = [];
    if ( !empty($products) ) $query[] = "filter=" . implode( ';', $products );
    if ( !empty($dates) ) $query[] = implode( '&', $dates );
    $query[] = "limit=1000";
    $query[] = "offset=0";

    $method = "https://api.moysklad.ru/api/remap/1.2/report/profit/byproduct?" . implode('&', $query);
    $headers = [
      "Accept: application/json;charset=utf-8",
      // "Accept-Encoding: gzip",
      "Authorization: Bearer {$this->access}"
    ];

    return $this->request( $method, $headers );
  }

  private function processProfitItems( array $items ):array
  {
    $result = [];
    foreach ( $items as $item ){
      $result[ $item['assortment']['article'] ] = $item['sellQuantity'];
    }
    return $result;
  }

  private function request( string $url, array $headers ):array
  {
    $ch = curl_init( $url );
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
    curl_setopt( $ch, CURLOPT_HEADER, false );
    curl_setopt($ch, CURLOPT_ENCODING, 'gzip,deflate');

    $res = curl_exec( $ch );

    return json_decode( $res, true );
  }
}

$obj = new DPProfitDaily(
  marketplace: $argv[1],
  cabinet: $argv[2]
);

$obj->run();
 ?>
