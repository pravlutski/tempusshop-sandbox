<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"].'/bitrix/modules/main/include/prolog_before.php');
require('lib/bootstrap.php');

class PP149 extends PurchaseProcessorBase
{
  public function run():void
  {
    $supplier = 149;
    $cabinet = 's2';

    $purchaseList = $this->data->getPurchaseList(
      supp: $supplier,
      site_id: ['s1', 's2'],
      active: 'Y'
    );

    $productIds = array_map( function($item){
      return $item['product_id'];
    }, $purchaseList );

    $productsDict = $this->data->getProductsDict( $productIds );

    $profile = [
      'organization' => ConfigProvider::getOrganization( $cabinet, 'Вотч-трейд' ),
      'sourceStore' => ConfigProvider::getStore( $cabinet, 'Немига' ),
      'targetStore' => ConfigProvider::getStore( $cabinet, 'Минск' ),
      'description' => 'Немига - склад Минск',
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
}

(new PP149)->run();
 ?>
