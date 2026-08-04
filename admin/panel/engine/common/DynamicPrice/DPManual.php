<?php
require_once("lib/bootstrap.php");

class DPManual extends OrchestraCore
{
  private string $model;

  public function __construct( string $marketplace, string $cabinet, string $model, int $userId )
  {
    parent::__construct( $marketplace, $cabinet );
    $this->model = $model;
    $this->userId = $userId;
  }

  public function run():void
  {
    CommunicationService::log("-----------------------------");
    CommunicationService::log("MANUAL START BY {$this->userId}");
    $items = $this->dataProvider->getItems( model: $this->model );

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
}
 ?>
