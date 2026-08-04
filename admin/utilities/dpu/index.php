<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");?>
<?if(!CModule::IncludeModule('panel.manager'))return;?>
<?$APPLICATION->SetTitle('Динамическое ценообразование');?>
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

 ?>
<div id="container">
  <h2>Динамическое ценообразование</h2>
  <hr>
  <div class="dp-header">

    <button id="add-items-btn" class="btn btn-primary dp-btn btn-modal" data-target="add-modal">Добавить модели</button>
    <button id="default-settings-btn" class="btn btn-light dp-btn btn-modal" data-target="settings-modal">Настройки утилиты</button>
    <button class="hide-btn btn btn-warning">Показать/Скрыть графики</button>

    <select id="mp-selector" class="form-control dp-btn" style="width: 90px; margin-left: 10px;">
      <option value="OZON" selected>OZON</option>
      <option value="WB">WB</option>
    </select>

    <input type="hidden" id="marketplace" value="OZON">
    <input type="hidden" id="OZON-cabinet" value="IP">
    <input type="hidden" id="WB-cabinet" value="WR">

    <div class="checkboxes">
      <div class="ds-chunk" style="">
        <input type="checkbox" id="yesterday-charts">
        <label for="yesterday-charts" class="s-label">Данные за вчера</label>
      </div>
      <div class="ds-chunk" style="">
        <input type="checkbox" id="out-of-stock" checked>
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
    <a id="export-link" href="https://tempusshop.ru/admin/utilities/dpu/ajax/exportTable.php" class="btn btn-primary">Экспорт</a>
    <button id="save-settings-btn" class="btn btn-warning dp-btn">Сохранить настройки</button>
  </div>
  <hr>
  <div class="dp-body">
    <form id="settings-list-form">
      <table>
        <thead>
          <th class="th-data" style="width: 115px !important">Артикул</th>
          <th class="th-data th-data-goal">План</th>
          <th class="th-data th-data-mpp switch-settings" style="display:none">Мин. маржин., %</th>
          <th class="th-data th-data-mpr switch-settings" style="display:none">Мин. маржа, P</th>
          <th class="th-data th-data-step switch-settings" style="display:none">Шаг, %</th>
          <th class="th-data th-data-chart1 switch-visual" >Заказы по часам</th>
          <th class="th-data th-data-chart2 switch-visual" >История изменений</th>
          <th class="th-data">Начальная цена, Р</th>
          <th class="th-data">Статус, %</th>
          <th class="th-data">Конеченая цена, Р</th>
          <th class="th-data">Себес., P</th>
          <th class="th-data">ФБО, шт.</th>
          <th class="th-data">Маржа, P</th>
          <th class="th-data">Маржин., %</th>
          <th class="th-data">Факт / План (Интервал)</th>
          <th class="th-data">Факт (Сутки)</th>
          <th class="th-data th-last"></th>
        </thead>
        <tbody class="list-settings">

        </tbody>
      </table>
    </form>
  </div>
  <div class="dp-body-empty">

  </div>
</div>

<div class="">

</div>

<style media="screen">
  .sidebar{
    display: none;
    background-color: rgba(245,245,245,0.75);
    backdrop-filter: blur(6px);
    box-shadow: 2px 0 7px 3px rgba(0,0,0,0.3);
    height: fit-content;
    border-bottom-right-radius: 6px;
  }
  .main{
    margin-left: 0 !important;
    width: 100%;
  }
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
    margin-right: 20px;
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
    background-color: rgba(100,100,100,0.15) !important;
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
    margin-left: 7px;
    font-weight: normal;
  }
  .ds-chunk{
    display: flex;
    flex-direction: row;
    margin-left: 15px;
    align-items: center;
  }
  .ds-chunk input{
    margin-top: -7px;
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
  const ajax_folder = '/admin/utilities/dpu/ajax/';
  let showOutOfStock;
  let showYesterdayCharts;

  function getList( showYesterdayCharts = false, changeDisplaySettings = false, showOutOfStock = true ) {
    $('.loader-container').show();
    var mp = $('#mp-selector').val();
    $.ajax({
      url: ajax_folder + 'getList.php',
      method: "POST",
      data: {
        yesterday: showYesterdayCharts,
        showOutOfStock: showOutOfStock,
        marketplace: $('#mp-selector').val(),
        cabinet: $('#'+mp+'-cabinet').val(),
      },
      success: function(response){
        if ( response.includes('<h4>') ){
          $('.dp-body-empty').html(response);

          $('.dp-body').hide();
          $('.loader-container').hide();
          $('#save-settings-btn').hide();

          $('.dp-body-empty').show();
          return;
        }
        $('.dp-body').show();
        $('#save-settings-btn').show();

        $('.dp-body-empty').hide();

        $('.list-settings').html(response);

        $('.switch-settings').hide();
        $('.switch-visual').show();
        displayVisual = true;

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
    var mp = $('#mp-selector').val();
    $.ajax({
      url: ajax_folder + 'deleteItem.php',
      method: "POST",
      data: {
        id: id,
        marketplace: $('#mp-selector').val(),
        cabinet: $('#'+mp+'-cabinet').val(),
      },
      success: function(response){
        getList()
      },
      error: function(response){
        alert('Не удалось удалить элемент')
      }
    })
  }

  function updateItem( model ){
    var mp = $('#mp-selector').val();
    $.ajax({
      url: ajax_folder + 'updateSingleItem.php',
      method: "POST",
      data: {
        model: model,
        marketplace: $('#mp-selector').val(),
        cabinet: $('#'+mp+'-cabinet').val(),
      },
      success: function(response){
        getList()
      },
      error: function(response){
        alert('Не удалось обновить товар')
      }
    })
  }

  function navigateThroughWindowHash(){
    let defaultPage = 'ozon';
    let page;

    if ( window.location.hash == '' ){
      window.location.hash = defaultPage;
      page = defaultPage;
    }else{
      page = window.location.hash.split('#')[1];
    }

    $('#mp-selector').val( page.toUpperCase() ).change();
  }

  $(document).on('click', '.del-btn', function(e){
    e.preventDefault();
    var id = $(this).attr('data-id');
    deleteItem( id );
  })

  $(document).on('click', '.update-btn', function(e){
    e.preventDefault();
    var id = $(this).attr('data-model');
    updateItem( id );
  })

  $(document).on('click', '#save-settings-btn', function(){
    saveListSettings();
  })

  $(document).on('change', '#yesterday-charts', function(e){
    getList( $('#yesterday-charts').is(':checked'), true, $('#out-of-stock').is(':checked') );
  })

  $(document).on('click', '#out-of-stock', function(e){
    getList( $('#yesterday-charts').is(':checked'), true, $('#out-of-stock').is(':checked') );
  })

  $(document).on('click', '#export-link', function(e){
    e.preventDefault();
    var mp = $('#mp-selector').val();
    var cab = $('#'+mp+'-cabinet').val();
    var href = $(this).attr('href') + "?marketplace=" + mp + "&cabinet=" + cab;
    window.location.href = href;
  })

  $(document).tooltip({
    items: '[title]',
    show: { effect: "blind", duration: 0 },
    show: { effect: "blind", duration: 0 },
    content: function(){
      var text = JSON.parse( $(this).attr('title') );
      var tooltip = '';

      for ( const row of Object.values(text) ){
        tooltip += "<span>"+row+"</span><br>";
      }
      return tooltip;
    }
  });

  var displayVisual = true;
  $(document).on('click', '.hide-btn', function(e){
    if ( displayVisual ){
      $('.switch-settings').show();
      $('.switch-visual').hide();
      displayVisual = false;
      return;
    }
    $('.switch-settings').hide();
    $('.switch-visual').show();
    displayVisual = true;
  })

  $(document).on('mouseenter', '.navbar-brand', function(e){
    e.preventDefault();
    $('.sidebar').show('slide',{direction: 'up'}, 200);
  })

  $(document).on('mouseleave', '.sidebar', function(e){
    e.preventDefault();
    $('.sidebar').hide('slide',{direction: 'up'}, 200);
  })

  $(document).on('change', '#mp-selector', function(e){
    window.location.hash = $(this).val().toLowerCase();
    getList( $('#yesterday-charts').is(':checked'), true, $('#out-of-stock').is(':checked') );
  })
  navigateThroughWindowHash();
  // getList( $('#yesterday-charts').is(':checked'), true, $('#out-of-stock').is(':checked') );

</script>

<?include('include/modal.php');?>
<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>
