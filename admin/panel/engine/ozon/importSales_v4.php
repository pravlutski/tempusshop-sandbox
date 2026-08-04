<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);
ob_implicit_flush( true );

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

require_once('classes/sales/SalesConfigProvider.php');
require_once('classes/sales/SalesCommunicationService.php');
require_once('classes/sales/SalesCalculator.php');

require_once('classes/sales/SalesApiManager.php');
require_once('classes/sales/SalesDataProvider.php');
require_once('classes/sales/SalesProcessManager.php');

set_time_limit(0);

class SalesManager
{
  private ?SalesApiManager $sam;
  private ?SalesDataProvider $sdp;
  private ?SalesProcessManager $spm;
  private ?SalesCommunicationService $scs;

  private ?TGNotifier $bot;

  private ?DBPanel $dbPanel;
  private object $dbMain;

  private string $cabinet;

  public function __construct( $cabinet )
  {
    if ( !in_array($cabinet, SalesConfigProvider::getAllowedCabinets()) ){
      throw new \InvalidArgumentException("{$cabinet} is not supported");
    }

    $this->cabinet = $cabinet;

    CModule::IncludeModule('panel.manager');
    $this->initConnection();
    $this->initModules();
  }

  private function initConnection():void
  {
    $this->dbPanel = new DBPanel;
    $this->dbMain = \Bitrix\Main\Application::getConnection();
    $this->bot = new TGNotifier;
  }

  private function initModules():void
  {
    $this->sdp = new SalesDataProvider(
      cabinet: $this->cabinet,
      dbPanel: $this->dbPanel,
      dbMain: $this->dbMain
    );
    $settingsApi = $this->sdp->getMainSettings();
    $settingsSales = $this->sdp->getSalesSettings();

    $this->scs = new SalesCommunicationService(
      db: $this->dbPanel,
      cabinet: $this->cabinet
    );
    $this->sam = new SalesApiManager( $settingsApi );

    $this->spm = new SalesProcessManager(
      settings: $settingsSales,
      communicationService: $this->scs
    );
  }

  public function run():void
  {
    SalesCommunicationService::logTech( "Получение товаров" );
    $this->scs->updateStatus(
      text: "Запуск скрипта",
      perc: 0,
      status: "PROCESS",
      start: date('Y.m.d G:i:s')
    );
    $items = $this->sdp->getItems();


    SalesCommunicationService::logTech( "Получение списка акций" );
    $this->scs->updateStatus(
      text: "Получение списка акций",
      perc: 10,
    );
    $salesList = $this->sdp->getSalesList();

    $time_start = time();
    SalesCommunicationService::logTech( "Получение товаров в акциях" );
    $this->scs->updateStatus(
      text: "Получение товаров в акциях",
      perc: 25,
    );
    $salesProductsActive = $this->sam->getSalesProducts(
      salesList: $salesList,
      method: '/v1/actions/products',
    );

    $time_diff = $time_start - time();
    SalesCommunicationService::logTech( "Активные товары получены за " . $time_diff . " сек" );

    $time_start = time();
    SalesCommunicationService::logTech( "Получение товаров доступных для участия" );
    $this->scs->updateStatus(
      text: "Получение товаров доступных для участия",
      perc: 50,
    );
    $salesProductsCandidates = $this->sam->getSalesProducts(
      salesList: $salesList,
      method: '/v1/actions/candidates',
    );
    file_put_contents(
      "/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/ozon/salesProductsCandidates.txt",
      print_r( $salesProductsCandidates, true )
    );
    $time_diff = $time_start - time();
    SalesCommunicationService::logTech( "Доступные для участия товары получены за " . $time_diff . " сек" );

    $salesProducts = $this->spm->mergeSourceData(
      itemsActive: $salesProductsActive,
      itemsCandidates: $salesProductsCandidates
    );

    $time_start = time();
    SalesCommunicationService::logTech( "Проверка товаров" );
    $this->scs->updateStatus(
      text: "Проверка товаров",
      perc: 70,
    );
    $resultData = $this->spm->processSales(
      salesList: $salesList,
      salesProducts: $salesProducts,
      items: $items
    );

    $time_diff = $time_start - time();
    SalesCommunicationService::logTech( "Товары проверены за " . $time_diff . " сек" );

    $data = $this->spm->filterFailedCandidates(
      checkedItems: $resultData,
      activeItems: $salesProductsActive
    );

    $time_start = time();
    SalesCommunicationService::logTech( "Отправляем запрос" );
    $this->scs->updateStatus(
      text: "Отправление запросов в OZON",
      perc: 90,
    );
    $this->sam->sendSalesData( $data );

    $time_diff = $time_start - time();
    SalesCommunicationService::logTech( "Запросы отправлены за " . $time_diff . " сек" );

    $this->scs->updateStatus(
      text: "Завершено",
      perc: 100,
      status: "COMPLETE",
      end: date('Y.m.d G:i:s')
    );
  }

}

// $obj = new SalesManager( $argv[1] );
// $obj->run();
 ?>
