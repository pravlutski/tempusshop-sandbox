<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);
ob_implicit_flush( true );

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

require( "AdvertConfigProvider.php" );
require( "AdvertApiManager.php" );
require( "AdvertFileManager.php" );
require( "AdvertDataProvider.php" );
require( "AdvertDataProcessor.php" );
require( "AdvertProcessManager.php" );

class AdvertManager
{
  private AdvertApiManager $api;
  private AdvertFileManager $file;
  private AdvertProcessManager $process;
  private AdvertDataProvider $data;

  public function __construct()
  {
    $this->api = new AdvertApiManager;
    $this->file = new AdvertFileManager;
    $this->advProcessor = new AdvertProcessManager(
      api: $this->api
    );
    $this->dataProcessor = new AdvertDataProcessor;
    $this->data = new AdvertDataProvider(
      main: \Bitrix\Main\Application::getConnection(),
      panel: new DBPanel,
      api: $this->api
    );

  }

  public function run():void
  {
    AdvertConfigProvider::init(
      params: $this->data->getSettings()
    );

    // Генерируем токен Performance Api со сроком жизни 30 минут
    $this->api->getAuthoriztionToken();

    $r = $this->api->getAdvertReport(
      data: ["timebounds" => ['to' => "2026-06-26T11:22:00+03:00", 'from' => "2026-06-26T11:22:00+03:00"]],
      method: 'GET'
    );

    var_dump($r);
    die;
    // Сравниваем файлы с нашим ассотиментом и ассортиментом конкруента
    $compData = $this->file->getCompetetitorFile();

    $ownData = $this->dataProcessor->sortItemsByProfit(
      own: $this->data->getItems(),
      comp: $compData,
      profitData: $this->data->getProfitData()
    );

    $distributed = $this->advProcessor->distributeAllItems(
      comp: $compData, // Файл ассортимента конкурента
      own: $ownData, // Наш ассортимент
      dict: $this->data->getSkuDictionary(), // Словарь offer_id => SKU
      priceData: $this->data->getPriceData(),
      quarantine: $this->data->getQuarantineData(),
      advertDict: $this->data->getAdvertDictionary() // Товары всех РК кроме тех, что выбраны в настройках
    );

    // Убираем или добавляем товары в рекламные кампании
    $advertItems = $this->advProcessor->processAdverts(
      distributed: $distributed,
      advertDict: $this->data->getSelectedAdvertDictionary()
    );

    file_put_contents(
      '/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/ozon/classes/adverts/atems.txt',
      print_r( $advertItems, true )
    );
    $this->saveActionLog( $advertItems );
  }

  private function saveActionLog( array $data ):void
  {
    file_put_contents(
      AdvertConfigProvider::getActionLogPath(),
      json_encode( $data, true )
    );
  }
}

(new AdvertManager)->run()
?>
