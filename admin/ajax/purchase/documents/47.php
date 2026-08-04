<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"].'/bitrix/modules/main/include/prolog_before.php');
require('lib/bootstrap.php');

class PP47 extends PurchaseProcessorBase
{
  public function run():void
  {
    $supplier = 47;
    $suppliers = $this->data->getSuppliersList( location: 'moscow' );

    $purchaseList = $this->data->getPurchaseList(
      supp: $suppliers,
      site_id: ['s2'],
      active: 'Y'
    );

    $productIds = array_map( function($item){
      return $item['product_id'];
    }, $purchaseList );

    $productsDict = $this->data->getProductsDict( $productIds );

    $this->createDocumentsRU( $purchaseList, $productsDict );
    $this->createDocumentsBY( $purchaseList, $productsDict );

  }

  private function createDocumentsRU( array $purchaseList, array $productsDict ):void
  {
    $profile = [
      'organization' => ConfigProvider::getOrganization('s1', 'Вотчес-ритейл'),
      'store' => ConfigProvider::getStore('s1', 'Основной'),
      'agent' => ConfigProvider::getCounterparty('s1', 'Вотч-трейд'),
      'description' => 'ВР-ВТ',
      'applicable' => false,
    ];

    $document = $this->getDocument('s1');

    $types = ['demand', 'purchasereturn'];

    foreach ( $types as $type ){
      $document->create(
        products: $purchaseList,
        productsDict: $productsDict,
        metaDict: ConfigProvider::getMetaDicitonary(),
        profile: $profile,
        document: $type
      );
    }
  }

  private function createDocumentsBY( array $purchaseList, array $productsDict ):void
  {
    $orders = array_map(function($item){
      return $item['order_id'];
    }, $purchaseList);

    $distributed = $this->distributeItems(
      items: $purchaseList,
      tp: $this->data->getTradingPlatformMatch( $orders )
    );

    $profile = [
      'organization' => ConfigProvider::getOrganization( 's2', 'Вотч-трейд' ),
      'store' => '',
      'agent' => ConfigProvider::getCounterparty( 's2', 'Вотчес-ритейл' ),
      'description' => '',
      'applicable' => false,
    ];

    $document = $this->getDocument('s2');
    $descBase = 'ВР-ВТ ';

    $profile['store'] = ConfigProvider::getStore( 's2', 'Минск' );
    $profile['description'] = $descBase . "Минск";

    // if ( empty($items) ) return;

    $document->create(
      products: $purchaseList,
      productsDict: $productsDict,
      metaDict: ConfigProvider::getMetaDicitonary(),
      profile: $profile,
      document: 'supply'
    );

    // foreach ( $distributed as $store => $items ){
    //
    //   $profile['store'] = ConfigProvider::getStore( 's2', $store );
    //   $profile['description'] = $descBase . $store;
    //
    //   if ( empty($items) ) continue;
    //
    //   $document->create(
    //     products: $items,
    //     productsDict: $productsDict,
    //     metaDict: ConfigProvider::getMetaDicitonary(),
    //     profile: $profile,
    //     document: 'supply'
    //   );
    // }
  }

  private function distributeItems( array $items, array $tp ):array
  {
    $result = [
      'Минск' => [],
      'Немига' => [],
    ];
    $conds1 = [ 'tpid1' => 13, 'tpid2' => 24, 'status' => 'TA' ];
    $conds2 = [ 'tpid' => 15, 'status' => 'TA' ];

    foreach ( $items as $item ){
      $isOrderIdEmpty = empty( $item['order_id'] );

      if ( !$isOrderIdEmpty ){
        $tpid = $tp[ $item['order_id'] ];
        $status = $this->data->getOrderStatus( $item['order_id'] );
      }

      $isTpIdCheck = (($tpid == $conds1['tpid1'] || $tpid == $conds1['tpid2']) || $tpid == $conds2['tpid']);
      $isStatusCheck = ( $status == $conds1['status'] );

      $key = ( ($isTpIdCheck && $isStatusCheck) || $isOrderIdEmpty ) ? 'Немига' : 'Минск';

      $result[ $key ][] = $item;
    }

    return $result;
  }
}

( new PP47 )->run();

 ?>
