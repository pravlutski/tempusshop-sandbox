<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");?>
<?if(!CModule::IncludeModule('panel.manager'))return;?>
<?$APPLICATION->SetTitle('Детальный лог акций');?>
<?$APPLICATION->SetPageProperty("page_h1", "Детальный лог акций");?>
<link href="<?=SITE_TEMPLATE_PATH?>/css/sales.css" rel="stylesheet">
<script src="<?=SITE_TEMPLATE_PATH?>/js/sales.js"></script>
<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js" integrity="sha256-lSjKY0/srUM9BE3dPm+c4fBo1dky2v27Gdjm2uoZaL0=" crossorigin="anonymous"></script>
<link href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css" rel="stylesheet">
<style>
.ui-front {
  z-index: 1000000000!important;
}
</style>

<?php
CModule::IncludeModule('panel.manager');
$dbPanel = new DBPanel;
$rows = $dbPanel->select(['name', 'sale_id'], "ozon_sales_IP")->make();
$salesList = [];
foreach ( $rows as $row ){
  $salesList[ $row['sale_id'] ] = $row['name'];
}
 ?>

<div class="log-container">
  <div class="header-control">
    <input type="form-input" id="search" value="" placeholder="Введите модель...">
    <select class="form-select" id="sale-selector">
      <option value="0">Все акции</option>
      <? foreach ( $salesList as $sale_id => $name):?>
      <option value="<?=$sale_id?>"><?=$name?></option>
      <? endforeach; ?>
    </select>
    <input type="hidden" id="cabinet" value="IP">
    <button class="btn btn-light" id="find-btn">Найти</button>
  </div>
  <div class="log-body">

    <div class="card active">
      <div class="card-header">
        <span class="card-name"><b>2026-01-16 14:10:23</b> - Эластичный бустинг. Без ограничения срока действия</span>
        <span class="card-status"><b>Статус:</b> В акции</span>
      </div>
      <div class="card-body">
        <div class="card-row">
          <span class="row-name">Комментарий: </span>
          <span class="row-value">Товар прошел по всем условиям: маржинальность - 58.46%, маржа - 787.44руб., скидка - 7.93</span>
        </div>
        <div class="card-row">
          <span class="row-name">Себестоимость: </span>
          <span class="row-value">1000</span>
        </div>
        <div class="card-row">
          <span class="row-name">Цена товара: </span>
          <span class="row-value">3000</span>
        </div>
        <div class="card-row">
          <span class="row-name">Цена с фикс. скидкой: </span>
          <span class="row-value">2500</span>
        </div>
        <div class="card-row">
          <span class="row-name">Модуль ДЦ: </span>
          <span class="row-value">Да</span>
        </div>
        <div class="card-row">
          <span class="row-name">Макс. цена вхождения: </span>
          <span class="row-value">2200</span>
        </div>
      </div>
    </div>

    <div class="card inactive">
      <div class="card-header">
        <span class="card-name"><b>2026-01-16 14:10:23</b> - Эластичный бустинг. Без ограничения срока действия</span>
        <span class="card-status"><b>Статус:</b> В акции</span>
      </div>
      <div class="card-body">
        <div class="card-row">
          <span class="row-name">Комментарий: </span>
          <span class="row-value">Товар прошел по всем условиям: маржинальность - 58.46%, маржа - 787.44руб., скидка - 7.93</span>
        </div>
        <div class="card-row">
          <span class="row-name">Себестоимость: </span>
          <span class="row-value">1000</span>
        </div>
        <div class="card-row">
          <span class="row-name">Цена товара: </span>
          <span class="row-value">3000</span>
        </div>
        <div class="card-row">
          <span class="row-name">Цена с фикс. скидкой: </span>
          <span class="row-value">2500</span>
        </div>
        <div class="card-row">
          <span class="row-name">Модуль ДЦ: </span>
          <span class="row-value">Да</span>
        </div>
        <div class="card-row">
          <span class="row-name">Макс. цена вхождения: </span>
          <span class="row-value">2200</span>
        </div>
      </div>
    </div>

  </div>
</div>

<style media="screen">
  .header-control{
    display: flex;
    flex-direction: row;
    gap: 10px;
  }
  #search{
    padding: 5px;
    width: 300px;
    border-radius: 4px;
    border: 1px solid rgba(0,0,0,0.15);
  }
  #sale-selector{
    width: 450px;
  }

  .card{
    display: flex;
    flex-direction: column;
    margin-top: 10px;
    border-radius: 6px;
  }
  .card-header{
    display: flex;
    flex-direction: column;
  }
  .card-body{
    display: flex;
    flex-direction: column;
    margin-top: 5px
  }

  .card-name{
    font-size: 18px;
  }
  .card-status{
    font-size: 17px;
  }
  .card-row{
    display: flex;
    flex-direction: row;
    margin-bottom: 5px;
    gap: 10px;
    border-bottom: 1px solid rgba(0,0,0,0.05);
    padding-bottom: 5px;
  }
  .row-name{
    width: 15%;
    font-weight: bolder;
  }
  .row-value{
    width: 85%;
    font-size: bolder;
  }

  .closed{
    display: none;
  }

  .active{
    background-color: rgba(160, 221, 240, 0.7) !important;
  }
  .inactive{
    background-color: rgba(244, 185, 185, 0.7) !important;
  }
</style>

<script type="text/javascript">
  $(document).on('click', '.closed', function(e){
    $(this).removeClass('closed');
    $(this).addClass('open');
  })
  $(document).on('click', '.open', function(e){
    $(this).removeClass('open');
    $(this).addClass('closed');
  })
  function showLog()
  {
    $.ajax({
      url: '/admin/panel/ozon/sales/ajax/showLog.php',
      method: 'POST',
      data:{
        search: $('#search').val(),
        sale: $('#sale-selector').val(),
        cabinet: $('#cabinet').val(),
      },
      success: function(response){
        $('.log-body').html(response);
      },
      error: function(response){
        alert('Системная ошибка')
      }
    })
  }

  $(document).on('click', '#find-btn', function(e){
    showLog();
  })
</script>


<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>
