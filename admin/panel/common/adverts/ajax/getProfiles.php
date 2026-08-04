<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

$panel = new DBPanel;

if ( empty($_POST['platform']) ){
  header('HTTP/1.1 400 Bad Request');
  die;
}

function buildPlatformLink( int $id, string $platform ):string
{
  $templates = [
    'ozon' => 'https://seller.ozon.ru/app/advertisement/product/cpc/%s',
    'wb' => 'https://cmp.wildberries.ru/campaigns/edit/%s',
  ];

  return sprintf( $templates[$platform] ?? '#', $id );
}

function getProfileAdverts( DBPanel $panel, string $platform ):array
{
  $rows = $panel->select(['*'], 'am_campaign_products')->where('platform', $platform)->make();
  $grouped = [];
  $items = [];

  foreach ( $rows as $row ){
    $grouped[ $row['brand'] ][ $row['advertId'] ][] = $row['platform_product_id'];
    $items[ $row['brand'] ][] = $row['platform_product_id'];
  }

  return [
    'grouped' => $grouped,
    'items' => $items,
  ];
}

$profiles = $panel->select(['*'], 'am_brand_profiles')->where('platform', $_POST['platform'])->make();
$adverts = getProfileAdverts( $panel, $_POST['platform'] );

$rows = CIBlockElement::getList(["NAME" => "ASC"], ["IBLOCK_ID" => 11], false, false, ["ID", "NAME"] );
$brands = [];
while ( $row = $rows->getNext() ){
  $brands[ $row['ID'] ] = $row['NAME'];
}
?>
<form class="profile-list-form-<?=$_POST['platform']?>" action="index.html" method="post">

<?foreach ( $profiles as $row ):?>

<div id="<?=$row['id']?>" class="card profile" style="display:flex; flex-direction: row">
  <div class="profile-settings">
    <div class="profile-header" style="display: flex; flex-direction: row; margin-bottom: 15px">
      <div class="profile-name" style="display:flex; width: 50%; flex-direction: row">
        <span style="display:flex; font-size: 23px;"><?=$brands[$row['brand_id']]?></span>
        <span style="margin: 9px 0px 0px 8px; font-size: 12px">
          <? echo "Кампаний создано: <b>" . count( $adverts['grouped'][$row['brand_id']] ?? [] ) . "</b>";?>
        </span>
        <br>
      </div>
      <div class="profile-control" style="display:flex; width: 50%; margin: auto 0 auto auto">
        <button class="btn btn-danger btn-delete-profile btn-control" style="margin-left:auto" value="<?=$row['id']?>" onclick="deleteProfile($(this), event)">Удалить</button>
      </div>
    </div>
    <div class="profile-body body-<?=$row['id']?>" style="display:none; flex-direction: row">
      <div style="display:flex; flex-direction:row">
        <div class="options-container" style="display:flex; width: 60%; flex-direction: column">
          <div class="option" style="display:flex; flex-direction:row">
            <span class="option-field">Себестоимость, ₽</span>
            <div class="option-field cost-input-block" style="flex-direction: row; gap: 5px; display:flex">
              <input id="<?=$row['id']?>_cmin" data-id="<?=$row['id']?>" data-param="minCost" class="cost-input cost-min profile-param" type="text" name="profiles[<?=$row['id']?>][minCost]" value="<?=$row['minCost']?>" placeholder="0">
              <span>-</span>
              <input id="<?=$row['id']?>_cmax" data-id="<?=$row['id']?>" data-param="maxCost" class="cost-input cost-max profile-param" type="text" name="profiles[<?=$row['id']?>][maxCost]" value="<?=$row['maxCost']?>" placeholder="99999">
            </div>
          </div>
          <div class="option" style="display:flex; flex-direction:row">
            <span class="option-field">Дней на складе, дн.</span>
            <input id="<?=$row['id']?>_sd" data-id="<?=$row['id']?>" data-param="stockDays" class="option-field profile-param" type="text" name="profiles[<?=$row['id']?>][stockDays]" value="<?=$row['stockDays']?>" placeholder="0">
          </div>
          <div class="option" style="display:flex; flex-direction:row">
            <span class="option-field">Ставка, ₽</span>
            <input id="<?=$row['id']?>_bid" data-id="<?=$row['id']?>" data-param="bid" class="option-field profile-param" type="text" name="profiles[<?=$row['id']?>][bid]" value="<?=$row['bid']?>" placeholder="Мин. ставка">
          </div>
          <input id="<?=$row['id']?>_brand" type="hidden" value="<?=$row['brand_id']?>">
          <input id="<?=$row['id']?>_platform" type="hidden" value="<?=$row['platform']?>">
          <div class="option">
            <span style="margin-top: 6px; margin-left: 24%; font-size: 13px; color: rgba(0,0,0,0.5)" id="counter_<?=$row['id']?>">Количество доступных товаров: ??</span>
          </div>
        </div>
        <div class="adverts-container" style="display:flex; width: 40%; flex-direction: column">
          <? foreach ( $adverts['grouped'][$row['brand_id']] ?? [] as $adv => $items): ?>
            <div class="advert-row" style="display:flex; flex-direction: row; gap: 10px;">
              <a target="_blank" href="<?=buildPlatformLink($adv, $_POST['platform'])?>"><?=$adv?> - Товаров: <?echo count( $items ?? [] );?></a>
              <a target="_blank" href="ajax/exportCampaigns.php?advertId=<?=$adv?>" download>Экспорт</a>
            </div>
          <? endforeach; ?>
        </div>
      </div>
    </div>
  </div>

</div>

<?endforeach;?>
</form>
