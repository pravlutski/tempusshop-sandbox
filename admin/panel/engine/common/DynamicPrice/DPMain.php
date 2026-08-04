<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
require_once("lib/bootstrap.php");

class DPMain extends OrchestraCore
{
  public function run():void
  {
    CommunicationService::log("-----------------------------");
    CommunicationService::log("Preventive corrections");
    exec("php /var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/common/DynamicPrice/DPCorrector.php WB WR");
    CommunicationService::log("START");
    $items = $this->dataProvider->getItems();

    list( $excluded, $items ) = $this->processManager->checkTimestamps( $items );

    $this->processExcluded( $excluded, $items );

    if ( empty($items) ){
      CommunicationService::log("No items to process. Died");
      die;
    }

    $this->orderProvider->getOrdersCount( $items, $this->defaults['isPeriod']);


    $data = $this->processManager->checkItems( $items );

    if ( empty($data) ){
      CommunicationService::log("No items to save. Died");
      die;
    }

    $this->communicationService->saveData( $data );
    $this->communicationService->saveHistory( $data );
    CommunicationService::log("END");
  }

  private function processExcluded( array $excluded, array $items ):void
  {
    if ( empty($excluded) ){
      CommunicationService::log("All items will be processed");
      return;
    }
    foreach ( $excluded as $value ){
      CommunicationService::log("{$value['model']} was exluded due to timestamp check: next check will be at {$value['nextRunDate']}");
    }

    $countExcluded = count( $excluded );
    $countItems = count( $items );

    CommunicationService::log("Summary was excluded: {$countExcluded} models");
    CommunicationService::log("Summary will be processed: {$countItems} models");
  }

}

$obj = new DPMain($argv[1], $argv[2]);
$obj->run();

 ?>
