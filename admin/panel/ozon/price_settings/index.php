<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");?>
<?if(!CModule::IncludeModule('panel.manager'))return;?>
<?$APPLICATION->SetTitle('Ценообразование - OZON модуль');?>
<?$APPLICATION->SetPageProperty("page_h1", "Динамическое ценообразование");?>
<link href="<?=SITE_TEMPLATE_PATH?>/css/products.css" rel="stylesheet">
<link href="css/style.css" rel="stylesheet">
<script src="<?=SITE_TEMPLATE_PATH?>/js/products.js"></script>
<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js" integrity="sha256-lSjKY0/srUM9BE3dPm+c4fBo1dky2v27Gdjm2uoZaL0=" crossorigin="anonymous"></script>
<link href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css" rel="stylesheet">
<?php
$path = '/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/ozon/orders/logs/IP/' . date('Y-m-d') . '_fbo_orders.txt';
$lastLogOrderDate = date(
  'Y.m.d G:i:s',
  filectime($path)
);
if ( empty( filectime($path) ) ){
  $lastLogOrderDate = '';
  $classLabel = 'red';
}

$path = '/var/www/bitrix_logs/dynamicPriceSettings/OZON/IP_'. date('Y_m_d') . '.log';
$lastLogPriceDate = date(
  'Y.m.d G:i:s',
  filectime($path)
);
if ( empty( filectime($path) ) ){
  $lastLogPriceDate = '';
}

global $USER;
$userId = $USER->GetId();

$path = "/var/www/bitrix/data/www/tempusshop.ru/admin/panel/ozon/price_settings/settings/{$userId}.json";
if ( file_exists($path) ){
  $json = file_get_contents( $path );
  $settings = json_decode( $json, true );
  $displaySettings = $settings['displaySettings'];
  $butttonText = ($displaySettings == "hide") ? "Скрыть настройки" : "Показать настройки";
}else{
  $displaySettings = "show";
  $butttonText = ($displaySettings == "hide") ? "Скрыть настройки" : "Показать настройки";
}
 ?>
<div id="container">
  <div class="dp-header">

    <select id="cab-selector" class="form-select dp-btn" style="display:none">
      <option value="IP" selected>IP</option>
      <option value="TI">TI</option>
    </select>

    <button id="add-items-btn" class="btn btn-primary dp-btn btn-modal" data-target="add-modal">Добавить модели</button>
    <button id="default-settings-btn" class="btn btn-light dp-btn btn-modal" data-target="settings-modal">Настройки утилиты</button>
    <button class="hide-btn btn btn-warning" data-stat="<?=$displaySettings?>"><?=$butttonText?></button>

    <div class="checkboxes">
      <div class="ds-chunk" style="display:flex; flex-direction: row">
        <input type="checkbox" id="yesterday-charts">
        <label for="yesterday-charts" class="s-label">Данные графиков за вчера</label>
      </div>
      <div class="ds-chunk" style="display:flex; flex-direction: row">
        <input type="checkbox" id="out-of-stock" checked disabled>
        <label for="out-of-stock" class="s-label">Показывать не в наличии</label>
      </div>
    </div>
    <div class="divider">

    </div>
    <div class="loader-container">
      <span class="loader"></span>
    </div>
    <div class="update-label <?=$classLabel?>">
      <span>Заказы обновлены: <?=$lastLogOrderDate;?></span>
      <span>Цены рассчитаны:&nbsp&nbsp <?=$lastLogPriceDate;?></span>
    </div>
    <button id="save-settings-btn" class="btn btn-warning dp-btn">Сохранить настройки</button>
  </div>
  <hr>
  <div class="dp-body">
    <form id="settings-list-form">
      <table>
        <thead>
          <th class="th-data" style="width: 115px !important">Артикул</th>
          <th class="th-data th-data-goal">План</th>
          <th class="th-data th-data-mpp">Мин. маржин., %</th>
          <th class="th-data th-data-mpr">Мин. маржа, P</th>
          <th class="th-data th-data-step">Шаг, %</th>
          <th class="th-data th-data-chart1" style="display:none">Заказы по часам</th>
          <th class="th-data th-data-chart2" style="display:none">История изменений</th>
          <th class="th-data">Начальная цена, Р</th>
          <th class="th-data">Статус, %</th>
          <th class="th-data">Конеченая цена, Р</th>
          <th class="th-data">Себес., P</th>
          <th class="th-data">Маржа, P</th>
          <th class="th-data">Маржин., %</th>
          <th class="th-data">Заказы за период / План</th>
          <th class="th-data">Заказы к этому часу / План</th>
          <th class="th-data th-last"></th>
        </thead>
        <tbody class="list-settings">

        </tbody>
      </table>
    </form>
  </div>
</div>

<div class="">

</div>

<style media="screen">
  .dp-btn{
    display: flex;
    margin-right: 10px;
  }
  .dp-header{
    display: flex;
    flex-direction: row;
    height: 42px;
  }
  #cab-selector{
    width: 80px;
  }
  #save-settings-btn{
    margin-left: 10px;
  }
  table{
    width: 100%;
  }
  .th-data{
    text-align: center;
    border-bottom: 1px solid rgba(0, 0, 0, 0.12);
    border-right: 2px solid rgba(0, 0, 0, 0.12);
  }
  .th-last{
    border-right: none;
  }
  .dp-card{
    border-bottom: 1px solid rgba(0, 0, 0, 0.12);
    height: 81px !important;
  }
  .dp-card td{
    width: 75px;
    text-align: center;
    padding-top: 15px;
    padding-bottom: 15px;
  }
  .dp-card input{
    text-align: center;
    display: flex;
    /* border: none; */
    margin-left: auto;
    margin-right: auto;
    border-radius: 4px;
    border: 1px solid rgba(0,0,0,0.12);
    padding-left: 4px;
    padding-right: 4px;
    width: 60px;
  }
  .step, .profitPerc, .statusHistory, .th-data-chart2{
    border-right: 2px solid rgba(0,0,0,0.12);
  }
  .goal-complete{
    background-color: rgba(0,255,0, 0.2);
  }
  .goal-incomplete{
    background-color: rgba(255,0,0, 0.2);
  }
  .update-label{
    display: flex;
    flex-direction: column;
    /* margin-left: auto; */
    color: rgba(0,0,0,0.5);
    font-size: 14px;
  }
  .classLabel{
    color: red;
  }
  .tooltip-run{
    display: flex;
    flex-direction: column;
  }
  .model{
    cursor: pointer;
  }
  .unavailable{
    background-color: rgba(100,100,100,0.15);
    color: grey;
    font-style: italic;
  }
  .profit-cap-reached{
    background-color: rgba(100,0,0,0.15)
  }
  .suspicious{
    color: red;
  }
  .checkboxes{
    display: flex;
    flex-direction: column;
    font-size: 14px;
  }
  .s-label{
    margin-left: 3px;
  }
  .ds-chunk{
    margin-left: 10px;
  }
  .divider{
    margin-left: auto;
  }
  .loader-container{
    display: none;
    margin-left: left;
    margin-right: 10px;
    width: 42px;
    height: 42px;
    display: flex;
    justify-content: center;
    align-items: center;
    /* overflow: hidden; /* скроет часть лоадера, выходящую за границы */ */
  }
  .loader {
    width: 42px;
    height: 42px;
    border-radius: 50%;
    position: relative;
    animation: rotate 1s linear infinite
  }
  .loader::before , .loader::after {
    content: "";
    box-sizing: border-box;
    position: absolute;
    inset: 0px;
    border-radius: 50%;
    border: 5px solid #FFF;
    animation: prixClipFix 2s linear infinite ;
  }
  .loader::after{
    inset: 8px;
    transform: rotate3d(90, 90, 0, 180deg );
    border-color: #FF3D00;
  }

  @keyframes rotate {
    0%   {transform: rotate(0deg)}
    100%   {transform: rotate(360deg)}
  }

  @keyframes prixClipFix {
    0%   {clip-path:polygon(50% 50%,0 0,0 0,0 0,0 0,0 0)}
    50%  {clip-path:polygon(50% 50%,0 0,100% 0,100% 0,100% 0,100% 0)}
    75%, 100%  {clip-path:polygon(50% 50%,0 0,100% 0,100% 100%,100% 100%,100% 100%)}
  }
</style>

<script src="https://code.jquery.com/ui/1.13.0/jquery-ui.min.js"></script>
<link rel="stylesheet" href="https://code.jquery.com/ui/1.13.0/themes/base/jquery-ui.css">

<script type="text/javascript">
  const ajax_folder = '/admin/panel/ozon/ajax/price_settings/';
  let showOutOfStock;
  let changeDisplaySettings;
  let showYesterdayCharts;

  function getList( showYesterdayCharts = false, changeDisplaySettings = false, showOutOfStock = true ) {
    $('.loader-container').show();
    $.ajax({
      url: ajax_folder + 'getList.php',
      method: "POST",
      data: {
        yesterday: showYesterdayCharts,
        showOutOfStock: showOutOfStock
      },
      success: function(response){
        $('.list-settings').html(response);
        console.log( changeDisplaySettings );
        hideShowColumns( $('.hide-btn') );
        if ( changeDisplaySettings ){
          console.log('ive changed it again')
          hideShowColumns( $('.hide-btn') );
        }
        $('.loader-container').hide();
      }
    });
  }

  function saveListSettings(){
    $.ajax({
      url: ajax_folder + 'saveListSettings.php',
      method: "POST",
      data: $('#settings-list-form').serialize(),
      success: function(response){
        getList();
      },
      error: function(response){
        alert("Не удалось сохранить настройки");
      }
    })
  }

  function deleteItem( id ){
    $.ajax({
      url: ajax_folder + 'deleteItem.php',
      method: "POST",
      data: {id:id},
      success: function(response){
        getList()
      },
      error: function(response){
        alert('Не удалось удалить элемент')
      }
    })
  }

  function saveDisplaySettings( data )
  {
    $.ajax({
      url: ajax_folder + 'saveDisplaySettings.php',
      method: "POST",
      data: {display: data},
      success: function(response){
        console.log('Display settings successfully saved: ' + data);
      }
    })
  }

  $(document).on('click', '.del-btn', function(e){
    e.preventDefault();
    var id = $(this).attr('data-id');
    deleteItem( id );
  })

  $(document).on('click', '#save-settings-btn', function(){
    saveListSettings();
  })

  $(document).on('change', '#yesterday-charts', function(e){
    if ( $(this).is(':checked') ){
      getList( true, true );
    }else{
      getList( false, true );
    }
  })

  $(document).tooltip({
    items: '[title]',
    show: { effect: "blind", duration: 0 },
    show: { effect: "blind", duration: 0 },
    content: function(){
      var text = $(this).attr('title').split("|");
      return "<div class='tooltip-run'>\
        <span>"+text[0]+"</span>\
        <span>"+text[1]+"</span>\
        <span>"+text[2]+"</span>\
        </div>";
    }
  });

  $(document).on('click', '.hide-btn', function(e){
    saveDisplaySettings( $('.hide-btn').attr("data-stat") )
    hideShowColumns( $(this) );
  })

  function hideShowColumns( item ){
    var stat = item.attr('data-stat');
    if ( stat == 'show' ){
      // $('.goal').hide();
      $('.min_profit_rub').hide();
      $('.min_profit_perc').hide();
      $('.step').hide();

      // $('.th-data-goal').hide();
      $('.th-data-mpp').hide();
      $('.th-data-mpr').hide();
      $('.th-data-step').hide();

      $('.th-data-chart1').show();
      $('.ordersByHour').show();
      $('.th-data-chart2').show();
      $('.statusHistory').show();

      item.attr('data-stat', 'hide');
      item.html('Показать настройки');
      return;
    }
    // $('.goal').show();
    $('.min_profit_rub').show();
    $('.min_profit_perc').show();
    $('.step').show();

    // $('.th-data-goal').show();
    $('.th-data-mpp').show();
    $('.th-data-mpr').show();
    $('.th-data-step').show();

    $('.th-data-chart1').hide();
    $('.ordersByHour').hide();
    $('.th-data-chart2').hide();
    $('.statusHistory').hide();
    item.attr('data-stat', 'show');
    item.html('Скрыть настройки');
  }

  var item = $('.hide-btn');
  // hideShowColumns( item );

  getList( false );
</script>

<?include('include/modal.php');?>
<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>
