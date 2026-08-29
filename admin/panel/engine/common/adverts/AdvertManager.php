<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
require_once $_SERVER['DOCUMENT_ROOT'] . '/local/classes/CronWorkerGuard.php';
if (!CronWorkerGuard::startFromArgv()) {
	exit;
}
require("lib/bootstrap.php");

class AdvertManager
{
  private DataProvider $data;
  private AdvertServiceInterface $service;

  public function __construct( string $platform )
  {
    $config = Loader::loadConfig( $platform );
    Config::init( $config );
  }

  private function init():void
  {
    if( !CModule::includeModule('panel.manager') ){
      CommunicationService::log("Cannot load required component: panel.manger");
      throw new Exception("Cannot load required component: panel.manger");
    }

    $panel = new DBPanel;
    $main = \Bitrix\Main\Application::getConnection();

    $this->data = new DataProvider(
      panel: $panel,
      main: $main,
      ms: new MoyskladAPI('s1'),
      platform: Config::instance()->getPlatform()
    );

    $this->service = Loader::loadService( $this->data );
  }

  public function run():void
  {
    CommunicationService::log("START");

    try{
      $this->init();
    } catch( Throwable $e ){
      CommunicationService::log("Failed to load required providers/services");
      throw $e;
    }

    $items = $this->data->getItems();
    $profiles = $this->data->getProfiles();
    $distributed = DistributeService::distribute( $profiles, $items );

    $this->service->setProducts( $distributed );
    $this->service->getCampaignProducts();
    $this->service->manageProducts();
    $this->service->manageAdverts();

    CommunicationService::log("END");
  }
}

$obj = new AdvertManager( $argv[1] );
$obj->run();

?>
