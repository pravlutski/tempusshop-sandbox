<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

class InfographDebugger
{

  public function __construct( $mode )
  {
    if ( !in_array($mode,['ozon','wb']) ) return false;
    CModule::IncludeModule('panel.manager');
    $db = new DBPanel;
    $this->opt = $mode;
  }

  public function getItems( int $offset, int $limit, int $brand = 0, string $sort, string $isOzon ):array
  {
    $items = $this->getItemsMS();
    $total = count( $items );
    if ( $brand == 0 ){
      $items = array_slice($items, $offset, $limit);
    }
    // die;

    $arFilter = [
      'IBLOCK_ID' => 16,
      'PROPERTY_CML2_ARTICLE' => array_values($items)
    ];
    if ( $brand != 0 ){
      unset( $arFilter['PROPERTY_CML2_ARTICLE']);
      $arFilter['PROPERTY_BRAND'] = $brand;
    }
    if ( $isOzon == 'Y' ){
      $arFilter['PROPERTY_OZON_ACTIVE_VALUE'] = 'Да';
    }elseif ( $isOzon == 'N' ){
      $arFilter['PROPERTY_OZON_ACTIVE_VALUE'] = 'Нет';
    }
    if ( $sort == 'date-add' ){
      $arOrder = ['DATE_CREATE' => 'DESC'];
    }else{
      $arOrder = [];
    }

    $arSelect = ["ID", "IBLOCK_ID" ,"PROPERTY_CML2_ARTICLE", "PROPERTY_INFO_WB_IMAGE", "PROPERTY_INFOOZON_IMAGE", "PROPERTY_INFO_WB_PRIORITY"];
    $res = CIBlockElement::GetList( $arOrder, $arFilter, false, false, $arSelect );
    $arExport = [];

    while ( $row = $res->GetNext() ){
      $image = '';

      if ( $this->opt == 'wb' ){
        $image = !empty($row['PROPERTY_INFO_WB_PRIORITY_VALUE']) ? CFile::GetPath( $row['PROPERTY_INFO_WB_PRIORITY_VALUE'] ) : CFile::GetPath( $row['PROPERTY_INFO_WB_IMAGE_VALUE'] );
      }else{
        $image = !empty($row['PROPERTY_INFO_WB_PRIORITY_VALUE']) ? CFile::GetPath( $row['PROPERTY_INFO_WB_PRIORITY_VALUE'] ) : CFile::GetPath( $row['PROPERTY_INFOOZON_IMAGE_VALUE'] );
      }

      $arExport[ $row['PROPERTY_CML2_ARTICLE_VALUE'] ] = [
        'id' => $row['ID'],
        'model' => $row['PROPERTY_CML2_ARTICLE_VALUE'],
        'image' => $image,
      ];
    }
    $result = [];
    if ( $sort == 'popular'){
      foreach ( $items as $model ){
        if ( !empty($arExport[$model]) ){
          $result[] = $arExport[$model];
          unset( $arExport[ $model ] );
        }
      }
      $result = array_merge( array_values($result), array_values($arExport) );
    }else{
      $result = $arExport;
    }

    // var_dump($result);
    // die;
    file_put_contents(
      "/var/www/bitrix/data/www/tempusshop.ru/admin/utilities/infograph/cache/limit.json",
      json_encode(['limit' => $limit, 'brand' => $brand, 'sort' => $sort])
    );

    return [ 'result' => $result, 'total' => count($result) ];
  }

  public function getItemsMS( bool $cache = false ):array
  {
    $cachePath = "/var/www/bitrix/data/www/tempusshop.ru/admin/utilities/infograph/cache/cache.json";
    if ( !file_exists($cachePath) ){
      echo "Не удалось получить список";
    }
    $json = file_get_contents( $cachePath );
    $items = json_decode( $json, true );
    $res = [];
    foreach ( $items as $i ){
      if ( empty($i['ARTICLE']) ) continue;
      $res[] = $i['ARTICLE'];
    }
    return $res;
  }
}

$obj = new InfographDebugger( $_POST['mode'] );
$items = $obj->getItems( $_POST['offset'], $_POST['limit'], $_POST['brand'], $_POST['sort'], $_POST['isOzon'] );

if ( $_POST['brand'] != 0 ){
  $items['result'] = array_slice( $items['result'], $_POST['offset'], $_POST['limit'] );
}

 ?>
<div id="page-info" style="display: none">
  <?=json_encode(['limit' => $_POST['limit'], 'offset' => $_POST['offset'], 'total' => $items['total'], 'this' => count($items['result'])])?>
</div>
<div class="list-block">
  <? foreach( $items['result'] as $item ): ?>
    <a class="card" href="https://tempusshop.ru/bitrix/admin/iblock_element_edit.php?IBLOCK_ID=16&type=aspro_max_catalog&lang=ru&ID=<?=$item['id']?>&find_section_section=932&WF=Y" target="_blank">
      <span class="card-name"><?=$item['model']?></span>
      <? if ( empty($item['image']) ):?>
        <img class="card-img" src="/admin/utilities/infograph/no-image.png" alt="">
      <?else:?>
      <img class="card-img" src="<?=$item['image']?>" alt="">

    <? endif;?>
    </a>
  <? endforeach; ?>
</div>
