<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"].'/bitrix/modules/main/include/prolog_before.php');
require('lib/bootstrap.php');

class NKZ extends PurchaseProcessorBase
{
  public function run():void
  {
    $cabinet = 's1';
    $suppliers = $this->data->getSuppliersList( location: 'moscow' );

    $purchaseList = $this->data->getPurchaseList(
      supp: $suppliers,
      site_id: ['s1', 's1_nkz'],
      active: 'Y'
    );

    $this->preparePurchaseList($purchaseList);

    $orders = array_map( function($item){
      return $item['order_id'];
    }, $purchaseList );


    $productIds = array_map( function($item){
      return $item['product_id'];
    }, $purchaseList );

    $purchaseList = $this->filterItems(
      items: $purchaseList,
      tp: $this->data->getTradingPlatformMatch( array_filter($orders) )
    );

    $productsDict = $this->data->getProductsDict( $productIds );

    $profile = [
      'organization' => ConfigProvider::getOrganization( $cabinet, 'Вотчес-ритейл' ),
      'sourceStore' => ConfigProvider::getStore( $cabinet, 'Основной' ),
      'targetStore' => ConfigProvider::getStore( $cabinet, 'Новокузнецкая' ),
      'description' => 'Склад - магазин',
      'applicable' => false,
    ];

    $this->getDocument( $cabinet )->create(
      products: $purchaseList,
      productsDict: $productsDict,
      metaDict: ConfigProvider::getMetaDicitonary(),
      profile: $profile,
      document: 'move'
    );
  }

  private function filterItems( array $items, array $tp ):array
  {
    $conds = [ 'tpid1' => 23, 'tpid2' => 13, 'status' => 'TA' ];
    $result = [];

    foreach ( $items as $item ) {
      $isOrderIdEmpty = empty( $item['order_id'] );

      if ( !$isOrderIdEmpty ){
        $tpid = $tp[ $item['order_id'] ];
        $status = $this->data->getOrderStatus( $item['order_id'] );

        $isTpIdCheck = ( $tpid == $conds['tpid1'] || $tpid == $conds['tpid2'] );
        $isStatusCheck = ( $status == $conds['status'] );

        if ( !$isTpIdCheck || !$isStatusCheck ) continue;
      }

      $result[] = $item;
    }

    return $result;
  }

  private function preparePurchaseList( array &$items ):void
  {
    foreach ( $items as &$item ){
      if ( $item['site_id'] == 's1_nkz' ){
        $item['site_id'] = 's1';
      }
    }
  }
}

( new NKZ )->run();
 ?>
