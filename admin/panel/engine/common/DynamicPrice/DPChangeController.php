<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
require_once("lib/bootstrap.php");

class DPChangeController
{
  private ?DataProvider $data = null;
  private ?DBPanel $panel = null;
  private ?\Bitrix\Main\DB\MysqliConnection $main;

  public function __construct( string $marketplace, string $cabinet )
  {
    ValidationService::validateConstruct( $marketplace, $cabinet );
    ConfigProvider::init( $marketplace, $cabinet );
    $this->init();
  }

  private function init():void
  {
    $dbPanel = new DBPanel;
    $dbMain = \Bitrix\Main\Application::getConnection();

    $this->data = new DataProvider(
      items: new ItemsRepository( $dbMain, $dbPanel ),
      prices: new PricesRepository( $dbMain, $dbPanel ),
      settings: new SettingsRepository( $dbMain, $dbPanel )
    );

    $this->main = $dbMain;
    $this->panel = $dbPanel;
  }

  public function run():void
  {
    $items = $this->data->getItems();
    $priceData = $this->getActualItemPrices( $items );
    $deviations = $this->compareItems( $items, $priceData );
    $deviations = array_merge( $deviations, $this->calculateDeviations($items) );
    if ( !empty($deviations) ){
      $this->deleteFromTable( $deviations );
    }
    var_dump($deviations);
  }

  private function getActualItemPrices( array $items ):array
  {
    $arFilter = [
      "IBLOCK_ID" => 16,
      "PROPERTY_CML2_ARTICLE" => array_keys($items)
    ];
    $priceProperty = ConfigProvider::getPricePropertyName();
    $sellerDiscount = 1 - ConfigProvider::getSellerDiscount();

    $fboPrices = $this->data->getFboPrices();

    $arSelect = [ "ID", "IBLOCK_ID", "PROPERTY_CML2_ARTICLE", $priceProperty ];

    $rows = CIBlockElement::getList( [], $arFilter, false, false, $arSelect );
    $result = [];
    while ( $row = $rows->GetNext() ){
      $model = $row['PROPERTY_CML2_ARTICLE_VALUE'];
      $price = $fboPrices[$model] ?? $row["{$priceProperty}_VALUE"] * $sellerDiscount;
      $result[ $model ] = $price;
    }

    return $result;
  }

  private function compareItems( array $items, array $priceData ):array
  {
    $threshold = ConfigProvider::getPriceDifferenceThreshold();
    $result = [];

    foreach ( $items as $model => $data ) {
      $installed = $data['startPrice'];
      $actual = $priceData[ $model ] ?? false;

      if ( !($installed && $actual) ) continue;
      if ( round($installed) == round($actual) ) continue;

      $diff = CalculationService::calculatePriceDiffernce( $installed, $actual );

      if ( $diff < $threshold ) continue;

      $result[] = [
        'model' => $model,
        'actual' => $actual,
        'installed' => $installed,
        'difference' => $diff,
        'method' => __FUNCTION__,
      ];
    }

    return $result;
  }

  private function calculateDeviations( array $items ):array
  {
    $result = [];

    foreach ( $items as $model => $item ){
      $expectedPrice = $item['startPrice'] * (1 + $item['installed']['step'] / 100);
      if ( round($expectedPrice) == $item['installed']['price'] ) continue;
      if ( empty($item['installed']['price']) ) continue;

      $result[] = [
        'model' => $model,
        'actual' => $expectedPrice,
        'installed' => $item['installed']['price'],
        'difference' => $expectedPrice - $item['installed']['price'],
        'method' => __FUNCTION__,
      ];
    }

    return $result;
  }

  private function deleteFromTable( array $items ):void
  {
    $table = ConfigProvider::getFinalPriceTable();
    foreach ( $items as $item ){
      // $strSql = "DELETE FROM {$table} WHERE model = '{$item['model']}'";
      $this->panel->query( "DELETE FROM {$table} WHERE model = '{$item['model']}'" );
    }
  }
}

$dpcc = new DPChangeController(
  marketplace: $argv[1],
  cabinet: $argv[2]
);

$dpcc->run();
 ?>
