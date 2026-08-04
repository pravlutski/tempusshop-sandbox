<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
require("{$_SERVER['DOCUMENT_ROOT']}/admin/panel/engine/yandex/lib/bootstrap.php");

UIProcessor::init();
$data = UIProcessor::data();
$config = Config::instance();

$warehouseList = $data->getWarehousesList();
$rows = $data->settings()->getCampaignsMatchList( 'WR' );
$matches = [];
foreach( $rows as $row ){
  $matches[$row['cabinet']][$row['campaignId']] = $row;
}
?>

<? foreach ( $config->getAllCabinets() as $cab => $name): ?>
<? $campaignsList = $data->settings()->getCampaignsList( $cab ); ?>

  <form id="stock-match-form-<?=$cab?>" action="" method="post">
    <? foreach ( $campaignsList as $c ): ?>
      <div class="input-group mb-3 card" style="display: flex; flex-direction: column">

        <div class="" style="display: flex; flex-direction: row">
          <span class="input-group-text" id="basic-addon3" style="width: 25%;"><?=$c['domain']?></span>
          <select class="form form-select" name="<?=$c['campaignId']?>|warehouse">
            <?foreach ( $warehouseList as $w ):?>
            <option value="<?=$w?>" <?echo ($matches[$cab][$c['campaignId']]['warehouse'] == $w)? 'selected' : '' ?>><?=$w?></option>
            <?endforeach;?>
          </select>
        </div>

        <div class="" style="display: flex; flex-direction: row">
          <span class="input-group-text" id="basic-addon3" style="width: 25%;">Остаток</span>
          <input class="form form-control" placeholder="0" name="<?=$c['campaignId']?>|stock" value="<?=$matches[$cab][$c['campaignId']]['stock']?>">
        </div>

        <div class="" style="display: flex; flex-direction: row">
          <span class="input-group-text" id="basic-addon3" style="width: 25%;">Наценка</span>
          <input class="form form-control" placeholder="Нет дополнительной наценки" name="<?=$c['campaignId']?>|markup" value="<?=$matches[$cab][$c['campaignId']]['markup']?>">
        </div>

        <div class="" style="display: flex; flex-direction: row">
          <span class="input-group-text" id="basic-addon3" style="width: 25%;">Мин. цена</span>
          <input class="form form-control" placeholder="Нет ограничений" name="<?=$c['campaignId']?>|minPrice" value="<?=$matches[$cab][$c['campaignId']]['minPrice']?>">
        </div>

      </div>
    <? endforeach; ?>
    <input type="hidden" name="cabinet" value="<?=$cab?>">
  </form>
  <hr>
  <button class="btn btn-warning save-match-list" value="<?=$cab?>">Сохранить настройки</button>
<? endforeach; ?>
