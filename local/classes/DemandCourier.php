<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

class DemandCourier
{
  private $order;
  private $db;
  private $ms;
  private $service;

  private $orderId;
  private $products;

  public $log;

  public function __construct( $cabinet = 's1' )
  {
    $this->loadModules();
    global $DB;
    $this->db = $DB;
    $this->ms = new MoyskladAPI( $cabinet );
    $this->service = new OrderService;
    $this->log = [];
  }

  private function loadModules():void
  {
    CModule::IncludeModule('panel.manager');
  }

  private function getOrderMS( $orderNumber ):string|bool
  {
    $strSql = "SELECT * FROM ci_ms_order WHERE ORDER_NUMBER = '%s'";
    $rows = $this->db->Query( sprintf($strSql, $orderNumber) );

    if ( $rows->SelectedRowsCount() <= 0 ){
      $this->log['ERROR'][] = "Заказ {$orderNumber} не найден в таблице соответствий МС";
      return false;
    }

    return $rows->Fetch()['MS_ID'];
  }

  public function createDemand( $orderNumber ):bool
  {
    if ( empty($orderNumber) ){
      $this->log['ERROR'][] = "Номер заказа не может быть пустым";
      return false;
    }

    $ms_id = $this->getOrderMS( $orderNumber );
    if ( !$ms_id ) return false;

    $data = array(
      "href" => "https://api.moysklad.ru/api/remap/1.2/entity/customerorder/{$ms_id}",
      "metadataHref" => "https://api.moysklad.ru/api/remap/1.2/entity/customerorder/metadata",
      "type" => "customerorder",
      "mediaType" => "application/json",
    );

    $arTemplate = $this->ms->getDemandTemplate( $data );

    if ( empty($arTemplate['customerOrder']) ){
      $this->log['ERROR'][] = "Заказ {$orderNumber}: Не удалось получить шаблон или заказ не найден в МС";
      return false;
    }

    $resMS = $this->ms->setDemand( array($arTemplate) );

    if ( !empty($resMS[0]['errors']) ){
      $this->log['ERROR'][] = "Заказ {$orderNumber}: Отгрузка не создана ({$resMS[0]['errors'][0]['error']})";
      return false;
    }

    return true;
  }

  public function deleteDemand( $orderNumber ):bool
  {
    $ms_id = $this->getOrderMS( $orderNumber );
    if ( !$ms_id ) return false;

    $resOrder = $this->ms->customRequest("https://api.moysklad.ru/api/remap/1.2/entity/customerorder/{$ms_id}");

    if ( empty($resOrder['demands']) ){
      $this->log['ERROR'][] = "Заказ {$orderNumber}: Не найдена отгрузка";
      return false;
    }

    $demandHref = $resOrder['demands'][0]['meta']['href'];
    $demandId = end( explode('/', $demandHref) );
    // var_dump($demandId);

    $resOrder = $this->ms->deleteDemand($demandId);
    if ( !empty($resOrder) ){
      $this->log['ERROR'][] = "Заказ {$orderNumber}: Нестандартный ответ от МС при удалении отгрузки";
      return false;
    }
    return true;
  }

  public function getLog():array
  {
    return $this->log;
  }

}
 ?>
