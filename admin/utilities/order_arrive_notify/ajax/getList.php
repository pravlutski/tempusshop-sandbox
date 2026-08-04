<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
require($_SERVER["DOCUMENT_ROOT"]."/admin/ajax/purchase/documents/lib/bootstrap.php");
CModule::includeModule('panel.manager');

function getOrdersInfo( OrderService $service, array $orderIds ):array
{
  if ( empty($orderIds) ) return [];

  $rows = $service->getOrder([], ['ID' => $orderIds]);
  $result = [];

  foreach ( $rows as $row ){
    $result[ $row['ID'] ] = $row;
  }

  return $result;
}

$dataProvider = new DataProvider(
  main: \Bitrix\Main\Application::getConnection()
);
$orderService = new OrderService;
$suppliers = $dataProvider->getSuppliersList( location: 'moscow' );
$purchaseList = $dataProvider->getPurchaseList(
  supp: $suppliers,
  site_id: ['s1'],
  active: 'Y'
);

$orderIds = array_map( fn($item) => $item['order_id'], $purchaseList );
$orderTradingData = $dataProvider->getTradingPlatformMatch( $orderIds );

$requirements = [ 'tpId1' => 13, 'tpId2' => 23, 'status' => 'TA' ];

$ordersInfo = getOrdersInfo( $orderService, $orderIds );

$orders = [];
$productsCount = 0;

foreach ( $purchaseList as $row ){
  if ( empty($row['order_id']) ) continue;
  if ( ($orderTradingData[$row['order_id']] != $requirements['tpId1']) && ($orderTradingData[$row['order_id']] != $requirements['tpId2']) ) continue;

  $orderStatus = $ordersInfo[$row['order_id']]['STATUS_ID'];
  if ( $orderStatus != $requirements['status'] ) continue;

  $orders[ $row['order_id'] ][$row['model']] = $row['model'];
  $productsCount++;
}

$partlyPurchasedCount = 0;
foreach ( $orders as $orderId => $modelData ){
  if ( count($modelData) != count($ordersInfo[$orderId]['BASKET']) ){

    $partlyPurchasedCount++;
    unset( $orders[$orderId] );
  }
}

$key = 1;
?>

<div style="width: 100%">
  Количество заказов: <b><?echo count($orders);?></b><br>Количество товаров: <b><?=$productsCount?></b><br>Частичное поступление: <b><?=$partlyPurchasedCount?></b>
</div>
<hr>
<table style="width: 100%" class="table table-striped">
  <thead>
    <tr>
      <th>#</th>
      <th><input type="checkbox" id="select-all"/></th>
      <th>Номер заказа</th>
      <th>Дата оформления</th>
      <th>Модели</th>
      <th>ФИО</th>
      <th>Комментарий</th>
    </tr>
  </thead>
  <tbody class="table-body">
    <?
    foreach ( $orders as $id => $data ):
      $models = array_map( fn($item) => "{$item}<br>", $data );
      $models = implode("<span class='model-str'></span>", $models);
     ?>

    <tr class="order-row" id="<?=$ordersInfo[$id]['ORDER_ID']?>">
      <td style="width: 1%"><?=$key?></td>
      <td style="width: 5%"><input type="checkbox" style="margin-top: -3px;" id="<?=$ordersInfo[$id]['ORDER_ID']?>_checkbox" class="checkbox" data-order-id="<?=$id?>"/></td>
      <td style="width: 12%"><a class="order-link" href="/bitrix/admin/sale_order_view.php?ID=<?=$id?>" target="_blank"><?=$ordersInfo[$id]['ORDER_ID']?></a></td>
      <td style="width: 15%"><?=$ordersInfo[$id]['DATE_INSERT']?></td>
      <td style="width: 12%"><?=$models?></td>
      <td style="width: 20%"><?=$ordersInfo[$id]['FIO']?></td>
      <td style="width: 25%"><?=$ordersInfo[$id]['USER_DESCRIPTION']?></td>
    </tr>
    <?$key++;?>
     <?endforeach;?>
  </tbody>
</table>
