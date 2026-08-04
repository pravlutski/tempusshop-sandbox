<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"].'/bitrix/modules/main/include/prolog_before.php');
require('lib/bootstrap.php');

class PP128 extends PurchaseProcessorBase
{
  public function run():void
  {
    $supplier = 128;
    $cabinet = 's1';

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
      'organization' => ConfigProvider::getOrganization( $cabinet, 'Вотчес-ритейл' ),
      'sourceStore' => ConfigProvider::getStore( $cabinet, 'Новокузнецкая' ),
      'targetStore' => ConfigProvider::getStore( $cabinet, 'Основной' ),
      'description' => 'Магазин - склад',
      'group' => '8417e3cf-0854-11f1-0a80-0c93000194ca',
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

(new PP128)->run();
 ?>
