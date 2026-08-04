<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");?>
<?if(!CModule::IncludeModule('panel.manager'))return;?>
<?$APPLICATION->SetTitle('Лог акций');?>

<link href="<?=SITE_TEMPLATE_PATH?>/css/sales.css" rel="stylesheet">
<script src="<?=SITE_TEMPLATE_PATH?>/js/sales.js"></script>
<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js" integrity="sha256-lSjKY0/srUM9BE3dPm+c4fBo1dky2v27Gdjm2uoZaL0=" crossorigin="anonymous"></script>
<link href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css" rel="stylesheet">
<style>
.ui-front {
  z-index: 1000000000!important;
}
</style>
<?
opcache_reset();

global $DB;
global $USER;
$arGroups = $USER->GetUserGroupArray();

$strSql = "SELECT * FROM wdhs_ozon_sales";
$results = $DB->Query($strSql, false, $err_mess.__LINE__);
while ($row = $results->Fetch()){
  $salesActive[$row['sale_id']] = $row;
}

$action_id = $_GET['ID'];
$tmp = file_get_contents('/var/www/bitrix/data/www/tempusshop.ru/admin/modules/OzonImport/logs/sales/log.txt');
$arLog = json_decode($tmp,true);

$arLog = $arLog['1177169'];

if (empty($action_id)) {
  $APPLICATION->SetPageProperty("page_h1", "Логи акции");
} else {
  $APPLICATION->SetPageProperty("page_h1", "Лог акции - ".$salesActive[$action_id]['name']."" );
}
?>

<?php foreach ($arLog as $key => $value): ?>
  <?
  switch ($key) {
    case 'STAY':
        $name = "Остальсь в акции без изменний";
        $bg = "background-color: rgba(212, 242, 64, 0.44);";
        break;
    case 'REPLACE':
        $name = "Пернесенны из другой акции";
        $bg = "background-color: rgba(40, 96, 144, 0.33);";
        break;
    case 'NOT_ADD':
        $name = "Не добавлены";
        $bg = "background-color: rgba(244, 39, 78, 0.29);";
        break;
    case 'DELETE':
        $name = "Удалены из акции";
        $bg = "background-color: rgba(244, 39, 78, 0.29);";
        break;
    case 'ADD':
        $name = "Добавлены";
        $bg = "background-color: rgba(77, 196, 60, 0.42);";
        break;
}
  ?>
  <div class="col-sm-12" style="<?=$bg?>border-radius: 20px;margin-top: 15px;">
      <div class="header_table">
          <div class="title_header_table">
            <span><?=$name?> <i style="font-size: 18px;
  font-weight: normal;">(<?=count($value)?>)</i></span>
          </div>
          <div class="arrow-4">
              <span class="arrow-4-left"></span>
              <span class="arrow-4-right"></span>
          </div>
      </div>
    <div class="tabled">
      <?php foreach ($value as $k => $v): ?>
        <?=$v['model']?><br>
      <?php endforeach; ?>
    </div>
  </div>

<?php endforeach; ?>
<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>
