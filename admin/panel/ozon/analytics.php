<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");?>
<?if(!CModule::IncludeModule('panel.manager'))return;?>
<?$APPLICATION->SetTitle('Аналитика - OZON модуль');?>
<?$APPLICATION->SetPageProperty("page_h1", "Аналитика");?>
<link href="<?=SITE_TEMPLATE_PATH?>/css/products.css" rel="stylesheet">
<script src="<?=SITE_TEMPLATE_PATH?>/js/products.js"></script>
<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js" integrity="sha256-lSjKY0/srUM9BE3dPm+c4fBo1dky2v27Gdjm2uoZaL0=" crossorigin="anonymous"></script>
<link href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css" rel="stylesheet">
<?
global $USER;
$fields = [
  "our" => 'Цена продавца',
  // "g_price" => "Зелёная цена",
  "sell" => 'Цена для покупателя',
  "spp" => 'Соинвест',
  // "g_spp" => 'Соинвест зеленый',
  "is_fbo" => 'ФБО',
  "sale_name" => 'В акции',
  // "sale_price" => 'Цена для входа Х4',
  'orders_count' => 'Количество продаж',
  // 'competitor' => 'Цена конкурента',
  // 'boost' => 'Бустинг в поиске, %',
  // 'promo' => 'В акции',
  // 'fbs' => 'ФБС',
  // 'a_ordersum' => 'Заказано на сумму',
  // 'a_ordersum_d' => 'Динамика заказов',
  // 'a_shows' => 'Показы',
  // 'a_shows_d' => 'Динамика показов',
  // 'a_conv' => 'Конверсия из показа в заказ',
  // 'a_conv_d' => 'Динамика конверсии',
  // 'a_orders' => 'Заказано, шт.',
  // 'a_orders_d' => 'Динамика заказов',
];
$selected = [];
$filterFileName = '/var/www/bitrix/data/www/tempusshop.ru/admin/panel/ozon/ajax/analytics/filter/filter_'.$USER->GetID().'.json';
$filter = [];
if ( file_exists($filterFileName) ){
  $raw = file_get_contents($filterFileName);
  $filter = json_decode( $raw, true );
}
?>
<div id="container">
  <div class="date-picker-block">
    <form id="date-form" action="" method="post">
      <input id="dateFrom" type="date" name="dateFrom" class="form form-control input-f" value="">
      <span class="label"> -- </span>
      <input id="dateTo" type="date" name="dateTo" class="form form-control input-f" value="">
      <button id="show-table" class="btn btn-warning">Показать</button>
    </form>
    <button id="show-modal-filter" class="btn btn-light">Фильтр</button>
    <button id="show-modal-reports" class="btn btn-light">Отчеты</button>
    <button id="show-modal-control" class="btn btn-light">Данные парсера</button>
    <button id="update-today-data" class="btn btn-primary">Обновить данные за сегодня</button>
  </div>

  <div class="modal-background" style="display:none">
    <div class="modal-window filter-modal">
      <div class="head">
        <h3 class="modal-name">Фильтр по параметрам</h3>
      </div>
      <div class="body">
        <form id="filter-form" action="" method="post">
          <? foreach ( $fields as $key => $name ): ?>
            <? if ( in_array($key, $filter) ):?>
              <div id="<?=$key?>-div" class="row sell-row selected">
                <div class="name-block"><span><?=$name?></span></div>
                <div class="input-block"><input type="checkbox" id="<?=$key?>-input" class="chkbx" name="<?=$key?>" checked></div>
              </div>
            <? else: ?>
            <div id="<?=$key?>-div" class="row sell-row selected">
              <div class="name-block"><span><?=$name?></span></div>
              <div class="input-block"><input type="checkbox" id="<?=$key?>-input" class="chkbx" name="<?=$key?>"></div>
            </div>
          <? endif; ?>
          <? endforeach; ?>
        </form>
        <button class="btn btn-warning" style="margin-left:auto; margin-bottom: 15px; margin-top: 25px; display:flex" id="save-filter">Сохранить</button>
      </div>
    </div>

    <div class="modal-window control-modal" style="display:none;">
      <div class="head">
        <h3 class="modal-name">Контроль данных парсера</h3>
      </div>
      <div class="body">
        <form id="control-form" action="" method="post">
          <input class="form-control" style="margin-top: 2px;" type="date" name="control-date" id="control-date" value="">
          <!-- <button class="btn btn-warning control-btn">Проверить</button> -->
          <button class="btn btn-warning" style="margin-left: auto !important; margin-top: 10px" id="import-parsed-data-btn">Импорт</button>
        </form>
        <hr>
        <div class="check-info-block">

        </div>
        <div class="response-block" style="">

        </div>
      </div>
    </div>

    <div class="modal-window report-modal" style="display:none;">
      <div class="head">
        <h3 class="modal-name">Отчеты</h3>
      </div>
      <div class="body">

        <hr>
        <div class="btns-report-block">
          <div style="margin-bottom: 15px;">
            <a class="btn btn-primary" href="https://tempusshop.ru/admin/panel/engine/ozon/reportTopSales.php?cabinet=TI">Отчёт по ТОП моделям</a>
          </div>
          <div class="">
            <a href="https://tempusshop.ru/admin/panel/ozon/ajax/analytics/getAllPricesOzon.php" class="btn btn-primary">Цены всего ассортимента</a>
          </div>
        </div>
      </div>
    </div>

  </div>

  <div class="modal-spp" style="display:none">
    <div class="modal-window window-spp">
      <div class="head">
        <h3 class="modal-name name-spp">Детализация соинвеста</h3>
      </div>
      <div id="body-spp" class="body">

      </div>
    </div>
  </div>

  <hr>
  <div id="table-block">

  </div>
</div>
<? require('include/mobile.php'); ?>
<script src="lib/chart.js" type="text/javascript"></script>
<style media="screen">
  #container{
    overflow-x: auto;
    /* height: 70%; */
  }
  th{
    font-size: 14px;
    text-align: center !important;
    width: 200px;
    background-color: #ffc107 !important;
  }
  td{
    text-align: center;
  }
  tr{
    border-bottom: 1px solid rgba(0,0,0,0.25)
  }
  .bordered{
    border-right: 1px solid rgba(0,0,0,0.3);
  }
  .date-picker-block{
    position: sticky;
    left:0px;
  }
  #date-form{
    display: flex;
    flex-direction: row;
    gap: 10px;
  }
  .input-f{
    max-width: 160px;
  }
  .label{
    display: flex;
    font-weight: bolder;
    margin: auto 0 auto 0;
  }
  #table-block{
    display: flex;
    flex-direction: column-reverse;
    min-height: 220px;
  }
  .table-main{
    /* display: flex; */
  }
  .table-total{
    /* display: flex; */
  }
  table a {
    text-decoration: none !important;
    color: black !important;
  }
  table a:hover{
    color: white !important;
  }
  .green-15{
    background-color: rgba(0,230,0,0.5) !important;
  }
  .green-10{
    background-color: rgba(0,200,0,0.45) !important;
  }
  .green-5{
    background-color: rgba(0,200,0,0.3) !important;
  }
  .green-1{
    background-color: rgba(0,200,0,0.1) !important;
  }
  .red-15{
    background-color: rgba(230,0,0,0.5) !important;
  }
  .red-10{
    background-color: rgba(200,0,0,0.45) !important;
  }
  .red-5{
    background-color: rgba(200,0,0,0.4) !important;
  }
  .red-1{
    background-color: rgba(200,0,0,0.1) !important;
  }
  .no-data{
    background-color: rgba(200,0,0,0.45) !important;
    /* color: grey; */
  }
  .warning-cell{
    background-color: rgba(255, 159, 1, 0.5) !important;
    font-weight: bolder;
  }
  .warning-cell:hover{
    background-color: rgba(255, 159, 1, 0.2) !important;
  }
  .has-data{
    background-color: rgba(0,200,0,0.45) !important;
  }
  .no-comps{
    color: grey;
  }
  .sticked-left{
    position: sticky;
    left: 0;
    background-color: #ffc107 !important;
  }
  .row-header{
    font-size: 13px;
    font-weight: bolder;
  }
  .date-picker-block{
    display: flex;
    flex-direction: row;
    gap: 20px;
  }
  /* Modal window */
  .modal-background{
    width: 100%;
    height: 100%;
    top: 0;
    left: 0;
    position: fixed;
    background-color: rgba(0, 0, 0, 0.3);
    backdrop-filter: blur(5px);
    z-index: 998;
    overflow-y: auto;
  }
  .modal-spp{
    width: 100%;
    height: 100%;
    top: 0;
    left: 0;
    position: fixed;
    background-color: rgba(0, 0, 0, 0.3);
    backdrop-filter: blur(5px);
    z-index: 998;
    overflow-y: auto;
  }
  .modal-window{
    display: flex;
    padding: 40px;
    border-radius: 6px;
    flex-direction: column;
    /* height: 580px; */
    width: 36%;
    background-color: white;
    margin: 10% auto auto auto;
    z-index: 999;
  }
  .window-spp{
    width: 84%;
    margin: 5% auto auto auto;
  }
  .detail-analytics{
    display: flex;
    flex-direction: row;
    /* gap: 20px; */
  }
  .spp-btn{
    cursor: pointer;
  }
  .spp-btn:hover{
    background-color: rgba(0,0,0,0.15)
  }
  .modal-window .head{
    display: flex;
    flex-direction: row;
    margin-bottom: 5px;
    padding-bottom: 5px;
    /* border-bottom: 1px solid rgba(0,0,0,0.25); */
  }
  .modal-window .head .modal-name{
    margin-right: auto;
    font-weight: bolder;
  }
  .sell-row{
    display: flex;
    flex-direction: row;
    padding-bottom: 10px;
    padding-top: 10px;
    border-bottom: 1px solid rgba(0,0,0,0.25);
    cursor: pointer;
  }
  /* .sell-row:active{
    background-color: #ffc107;
  } */
  /* .sell-row input{
    width: 20%;
  } */
  /* .sell-row span{
    width: 80%;
  } */
  .name-block, .input-block{
    width: 50% !important;
  }
  .chkbx{
    margin: auto 0 auto auto;
    display: flex;
  }
  #fitler-form{
    padding-top: 5px;
    border-top: 1px solid rgba(0,0,0,0.25)
  }
  .active-select{
    border-top: 2px solid #ffc107 !important;
    border-bottom: 2px solid #ffc107 !important;
  }
  /* Контроль импорта */
  .status-body{
    display: flex;
    flex-direction: row;
  }
  .status-block{
    padding-bottom: 15px;
    padding-top: 15px;
    border-bottom: 1px solid rgba(0,0,0,0.25);
    display: flex;
    flex-direction: column;
  }
  .s-row{
    display: flex;
    flex-direction: row;
    gap: 10px;
    width: fit-content;
  }
  .si-block, .c-block{
    display: flex;
    flex-direction: column;
  }
  .si-block{
    width: 70%;
  }
  .c-block{
    width: 30%;
  }
  .update-data{
    margin: auto 0 auto auto;
    width: fit-content;
  }
  #control-form{
    display: flex;
    flex-direction: row;
  }
  #control-form input{
    width: 50%;
  }
  #control-form button{
    margin-left: auto;
  }
  /* Спиннер */
  .spin-wrapper{
    position: relative;
    width: 100%;
    height: 100%;
    /* background: #080705; */
  }
    .spinner{
      position: absolute;
      height: 60px;
      width: 60px;
      border: 3px solid transparent;
      border-top-color: #A04668;
      top: 50%;
      left: 50%;
      margin: -114px;
      border-radius: 50%;
      animation: spin 2s linear infinite;
  }
  .spinner-c{
    position: absolute;
    height: 60px;
    width: 60px;
    border: 3px solid transparent;
    border-top-color: #A04668;
    top: 50%;
    left: 44%;
    margin: 20px;
    border-radius: 50%;
    animation: spin 2s linear infinite;
}
  @keyframes spin{
    0% {transform: rotate(0deg);}
    100% {transform: rotate(360deg);}
  }
  @media (max-width: 867px){
    .modal-window{
      width: 100% !important;
      margin-top: 25%;
    }
    .control-modal{
      padding: 20px;
      /* margin-top: 25%; */
    }
    .status-block{
      flex-direction: column;
    }
    .si-block{
      width: 100%;
    }
  }
</style>
<script type="text/javascript">
  const url = '/admin/panel/ozon/ajax/analytics/';
  console.log('<?echo $filterFileName;?>');
  $(document).on('click', '#show-modal-filter', function(e){
    $('.modal-background').fadeIn();
    $('.control-modal').hide();
    $('.report-modal').hide();
    $('.filter-modal').show();
  })
  $(document).on('click', '#show-modal-control', function(e){
    $('.modal-background').fadeIn();
    $('.filter-modal').hide();
    $('.report-modal').hide();
    getControlData();
    $('.control-modal').show();
  })
  $(document).on('click', '#show-modal-reports', function(e){
    $('.modal-background').fadeIn();
    $('.filter-modal').hide();
    $('.control-modal').hide();
    $('.report-modal').show();
  })

  $(document).on('change', '#control-date', function(e){
    e.preventDefault();
    getControlData();
  })

  $(document).on('click', '.modal-background', function(e){
    // e.preventDefault();
    if( !$('.modal-window').is(e.target) && $('.modal-window').has(e.target).length == 0 ){
      $('.modal-background').hide();
      if ( $('.filter-modal').is(':visible') ){
        getTableData( true );
      }
      $('.filter-modal').hide();
      $('.control-modal').hide();
    };
  })

  function getControlData(){
    $('.check-info-block').html('\
      <div class="spin-wrapper">\
        <div class="spinner-с">\
        </div>\
      </div>\
      ');
    $.ajax({
      url: url + 'checkParsedData.php',
      method: 'POST',
      data: $('#control-form').serialize(),
      success: function(response){
        $('.check-info-block').html(response);
        $('.response-block').html('')
      }
    })
  }

  $(document).on('click', '#update-today-data', function(e){
    e.preventDefault();
    $('#table-block').html('\
      <div class="spin-wrapper">\
        <div class="spinner">\
        </div>\
      </div>\
      ');
    $.ajax({
      url: '/admin/panel/engine/ozon/analytics/topAnalytics2.php',
      method: 'POST',
      success: function(response){
        getTableData( false )
      },
      error: function(response){
        alert('Ошибка обновления');
      }
    });
  })

  $(document).on('click', '.spp-btn', function(e){
    $('.modal-spp').show();
    var date = $(this).attr('data-date');
    var model = $(this).attr('data-model');
    $('.name-spp').html("Детализация для " + model + " (" + date + ")");
    getSppDetail( model, date );
  })
  $(document).on('click', '.modal-spp', function(e){
    if( !$('.modal-window').is(e.target) && $('.modal-window').has(e.target).length == 0 ){
      $('#body-spp').html();
      $('.modal-spp').hide();
    };
  })

  function getSppDetail( model, date ){
    $('#body-spp').html('\
      <div class="spin-wrapper">\
        <div class="spinner">\
        </div>\
      </div>\
      ');
    $.ajax({
      url: url + 'getSppDetail.php',
      method: "POST",
      data: {model:model, date:date},
      success: function(response){
        $('#body-spp').html(response);
      }
    })
  }

  $(document).on('click', '.update-data', function(e){
    e.preventDefault();
    var mode = $(this).val();
    $('.response-block').html('\
      <div class="spin-wrapper">\
        <div class="spinner-c">\
        </div>\
      </div>\
      ');
    $.ajax({
      url: url + 'updateMaster.php',
      method: "POST",
      data: {mode: mode},
      success: function( response ){
        $('.response-block').html(response);
      }
    })
  })

  $(document).on('click', '#save-filter', function(e){
    e.preventDefault();
    var dataC = $('#filter-form').serialize();
    $.ajax({
      url: url + 'saveFilterAn.php',
      method: "POST",
      data: {data: dataC},
      success: function(response){
        // console.log('Фильтр сохранен');
        alert('Фильтр сохранен');
        $('.modal-background').hide();
        getTableData( true );
      }
    })
  })

  // $(document).on('click','.insel', function(e){
  //   var inputId = $(this).attr('id').split('-')[0];
  //   // $('#' + inputId + '-input').val(1).change();
  //   $('#' + inputId + '-input').prop('checked', true);
  //   $(this).removeClass('insel');
  //   $(this).addClass('selected');
  // })
  //
  // $(document).on('click','.selected', function(e){
  //   var inputId = $(this).attr('id').split('-')[0];
  //   // $('#' + inputId + '-input').val(0).change();
  //   $('#' + inputId + '-input').prop('checked', false);
  //   $(this).addClass('insel');
  //   $(this).removeClass('selected');
  // })

  $(document).on('click','.table-data tbody tr', function(e){
    if ( $(this).hasClass('active-select') ){
      $(this).removeClass('active-select');
    }else{
      $('.table-data tbody tr').removeClass('active-select');
      $(this).addClass('active-select');
    }
  })

  function getTableData(flag){
    var dateTo = $('#dateTo').val();
    var dateFrom = $('#dateFrom').val();
    var dataF = $('#filter-form').serialize();
    $('#table-block').html('\
      <div class="spin-wrapper">\
        <div class="spinner">\
        </div>\
      </div>\
      ');
    $.ajax({
      url: url + 'getDataByPeriod.php',
      method: "POST",
      data: {dateFrom: dateFrom, dateTo: dateTo, data:dataF},
      success: function(response){
        $('#table-block').html(response);
      }
    })
  }

  $(document).on('click','#show-table', function(e){
    e.preventDefault();
    getTableData( true )
  })

  function importData()
  {
    if ( $('#file-selector').val() == 0 ){
      alert('Не выбран файл');
      return false;
    }
    $.ajax({
      url: '/admin/panel/ozon/ajax/analytics/importData.php',
      method: "POST",
      data: {name: $('#file-selector').val()},
      success: function(response){
        alert('Файл импортирован');
      },
      error: function(response){
        alert('Ошибка импорта');
      }
    })
  }

  $(document).one('click', '#import-parsed-data-btn', function(e){
    e.preventDefault();
    importData();
  })

  getTableData(false);
</script>
<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>
