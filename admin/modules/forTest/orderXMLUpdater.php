<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);
ob_implicit_flush(true);
ob_end_flush();

use Bitrix\Main\Application,
    Bitrix\Sale\Order,
	  Bitrix\Main\Loader,
    Bitrix\Iblock\ElementTable;

class OrderXMLUpdater
{
  private $numbers;
  private $orderIDs;
  private $filename = "/var/www/bitrix/data/www/tempusshop.ru/admin/modules/forTest/export/bad_orders.txt";
  private $savePath = "/var/www/bitrix/data/www/tempusshop.ru/admin/modules/forTest/export/bad_orders_ids.json";

  public function __construct()
  {
    if ( !CModule::IncludeModule('panel.manager') ) die('BORODA\n');

    $this->orderService = new OrderService;
  }

  public function run():void
  {
    $this->getOrderNumbers();
    $this->getOrderIDs();
    $this->setProperties();
  }

  private function getOrderNumbers():void
  {

    $string = file_get_contents( $this->filename );
    $numbers = explode( PHP_EOL, $string );
    $numbers = array_map( "trim", $numbers );

    $this->numbers = $numbers;
  }

  private function getOrderIDs():void
  {
    if ( empty($this->numbers) ) die("got no order numbers");
    $arFilter = [
      'ACCOUNT_NUMBER' => $this->numbers
    ];
    $orders = $this->orderService->getOrder(array(), $arFilter);
    foreach ( $orders as $item ){
      $this->orderIDs[] = $item['ID'];
    }
    // var_dump( $this->numbers );
    print_r( "NUMBERS COUNT: " . count($this->numbers) . "\n" );
    print_r( "IDS COUNT: " . count($this->orderIDs) . "\n" );
  }

  private function setProperties():void
  {
    global $DB;

    if ( empty($this->orderIDs) ) die("got no order IDs");

    foreach ( $this->orderIDs as $id ){
      $this->updateOrderProductsXml( $id, $DB );
      sleep(1);
    }
    file_put_contents( $this->savePath, json_encode($this->orderIDs) );
  }

  private function updateOrderProductsXml($orderId, $DB)
  {


      if (!$orderId || !is_numeric($orderId)) {
          return "Некорректный ID заказа.";
      }

      $connection = Application::getConnection();
      $sqlHelper = $connection->getSqlHelper();

      // Запрос в таблицу b_sale_basket с фильтрацией по ORDER_ID
      $basketItems = $connection->query("
          SELECT ID, PRODUCT_ID, CATALOG_XML_ID, PRODUCT_XML_ID
          FROM b_sale_basket
          WHERE ORDER_ID = " . $sqlHelper->forSql($orderId)
      );

      $catalogXmlId = 'aspro_mshop_catalog_s1';
      $updatedItems = 0;

      while ($item = $basketItems->fetch()) {
          $updateFields = [];

          // Проверяем поле CATALOG_XML_ID
          if (empty($item['CATALOG_XML_ID'])) {
              $updateFields['CATALOG_XML_ID'] = $catalogXmlId;
          }

          // Проверяем поле PRODUCT_XML_ID
          if (empty($item['PRODUCT_XML_ID'])) {
              // Получаем XML_ID товара по его PRODUCT_ID из инфоблока 16
              $product = ElementTable::getList([
                  'select' => ['XML_ID'],
                  'filter' => ['ID' => $item['PRODUCT_ID'], 'IBLOCK_ID' => 16]
              ])->fetch();

              if ($product && !empty($product['XML_ID'])) {
                  $updateFields['PRODUCT_XML_ID'] = $product['XML_ID'];
              }
          }

          // Если есть поля для обновления, выполняем UPDATE запроса
          if (!empty($updateFields)) {
              $updateQuery = "UPDATE b_sale_basket SET ";

              $updateParts = [];
              if (!empty($updateFields['CATALOG_XML_ID'])) {
                  $updateParts[] = "CATALOG_XML_ID = '" . $sqlHelper->forSql($updateFields['CATALOG_XML_ID']) . "'";
              }
              if (!empty($updateFields['PRODUCT_XML_ID'])) {
                  $updateParts[] = "PRODUCT_XML_ID = '" . $sqlHelper->forSql($updateFields['PRODUCT_XML_ID']) . "'";
              }

              $updateQuery .= implode(', ', $updateParts);
              $updateQuery .= " WHERE ID = " . intval($item['ID']);

              $connection->queryExecute($updateQuery);
              $updatedItems++;
          }
      }

       print_r("Обновлено записей для {$orderId}: " . $updatedItems . "\n");
  }
}

( new OrderXMLUpdater )->run();


 ?>
