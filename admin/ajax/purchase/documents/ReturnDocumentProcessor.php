<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"].'/bitrix/modules/main/include/prolog_before.php');
require('lib/bootstrap.php');

class ReturnDocumentProcessor extends PurchaseProcessorBase
{
  private array $defaultOrg = [
    's1' => 'Вотчес-ритейл',
    's2' => 'Вотч-трейд',
  ];

  private array $defaultStore = [
    's1' => 'Основной',
    's2' => 'Минск',
  ];

  public function run( string $hash ):void
  {
    $file = new FileProcessor( $_SERVER['DOCUMENT_ROOT'] );
    $suppliersDict = $this->data->getSuppliersDict();
    $supplier = $suppliersDict[$hash]['id'];
    $cabinet = $suppliersDict[$hash]['cabinet'];

    $items = $file->getFileContents( $hash );

    $models = array_map( fn($val) => $val['model'], $items );
    $ids = $this->data->getItemsIds( $models );

    foreach ( $items as $key => $item ){
      $items[$key]['product_id'] = $ids[$item['model']] ?? 0;
    }

    $dictionary = $this->data->getProductsDict( $ids );

    $document = $this->getDocument( $cabinet );

    $profile = [
      'organization' => ConfigProvider::getOrganization( $cabinet, $this->defaultOrg[$cabinet] ),
      'store' => ConfigProvider::getStore( $cabinet, $this->defaultStore[$cabinet] ),
      'agent' => $supplier,
      'applicable' => false,
    ];

    $this->getDocument( $cabinet )->create(
      products: $items,
      productsDict: $dictionary,
      metaDict: ConfigProvider::getMetaDicitonary(),
      profile: $profile,
      document: 'purchasereturn'
    );
  }
}

if ( empty($_POST['supplier']) ) throw new Exception("Cannot get supplier");

$obj = new ReturnDocumentProcessor;
$obj->run( $_POST['supplier'] );

 ?>
