<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
require($_SERVER['DOCUMENT_ROOT'].'/admin/panel/engine/ozon/classes/adverts/AdvertConfigProvider.php');
require($_SERVER['DOCUMENT_ROOT'].'/admin/panel/engine/ozon/classes/adverts/AdvertDataProvider.php');

$data = new AdvertDataProvider(
  main: \Bitrix\Main\Application::getConnection(),
  panel: new DBPanel,
  api: null
);

AdvertConfigProvider::init(
  params: $data->getSettings()
);

$json = file_get_contents( AdvertConfigProvider::getActionLogPath() );
$logData = json_decode( $json, true );

$logData = array_merge( $logData['good'], $logData['bad'] );

$query = mb_strtoupper( trim($_POST['query']) );

$found = [];
foreach ( $logData as $key => $row ){
  if ( !str_contains($row['model'], $query) ) continue;
  $found[$key] = $row;
}

function returnAdvertLink( string $advId = '', string $text = '' ):string
{
  $template = '<a class="advert-link" href="https://seller.ozon.ru/app/advertisement/product/cpc/%s" target="_blank">%s</a>';
  return sprintf( $template, $advId, $text );
}

if ( empty($found) ){
  die("<span style='display:flex; margin: 5% auto 0 auto; font-weight: bolder; text-align:center'>Не найдены совпадения по запросу '{$query}'</span>");
}
?>

 <table class="table-log">
   <thead>
     <tr>
       <th></th>
       <th>Артикул</th>
       <th>Наша цена</th>
       <th>Цена конкурента</th>
       <th>Статус</th>
     </tr>
   </thead>
   <tbody>
   <? foreach ( $found as $key => $data): ?>
   <? if ( $k >= AdvertConfigProvider::getMaxTopItemsCount() ) break;?>
   <?
   try{
     $k = $key+1;
   }catch( Throwable $e ){
     continue;
   }
   ?>
     <tr class="<? echo ($data['status'] == 'good') ? 'approved' : 'denied';?>">
       <td><?=$k?></td>
       <td><?=$data['model']?></td>
       <td><?=$data['own_price']?></td>
       <td><? echo $data['comp_price'] ?? 'Нет данных'?></td>
       <td><?echo ($data['status'] == 'good') ? returnAdvertLink($data['advertId'], "Добавлен в {$data['advertId']}") : $data['reason'] . ' '. returnAdvertLink($data['adv']??'', $data['adv']??'');?></td>
     </tr>
   <? endforeach; ?>
   </tbody>
 </table>
<style media="screen">
  .advert-link{
    text-decoration: none;
    color: black;
  }
</style>
