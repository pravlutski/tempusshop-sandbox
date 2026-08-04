<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");?>
<?if(!CModule::IncludeModule('panel.manager'))return;?>
<?$APPLICATION->SetTitle('Аналитика - WB модуль');?>
<?$APPLICATION->SetPageProperty("page_h1", "Аналитика");?>
<link href="<?=SITE_TEMPLATE_PATH?>/css/products.css" rel="stylesheet">
<script src="<?=SITE_TEMPLATE_PATH?>/js/products.js"></script>
<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js" integrity="sha256-lSjKY0/srUM9BE3dPm+c4fBo1dky2v27Gdjm2uoZaL0=" crossorigin="anonymous"></script>
<link href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css" rel="stylesheet">
<?
$fields = [
  "our_price" => 'Цена продавца',
  "black_price" => "Цена (чёрная)",
  "sell_price" => 'Цена (Клуб)',
  "spp" => 'Соинвест (стд)',
  "c_spp" => 'Соинвест (клуб)',
  "is_fbo" => 'ФБО',
  // "sale_name" => 'В акции',
  // "sale_price" => 'Цена акционная',
  // "orders_count" => 'Количество продаж',
];
$selected = [];
$filterFileName = '/var/www/bitrix/data/www/tempusshop.ru/admin/panel/wb/ajax/analytics/filter.json';
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
    <button id="show-modal" class="btn btn-light">Фильтр</button>
    <button id="update-today-data" class="btn btn-primary">Обновить данные за сегодня</button>
  </div>

  <div class="modal-background modal-filter" style="display:none">
    <div class="modal-window">
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
        <button class="btn btn-warning" style="margin-left:auto; margin-top: 25px; display:flex" id="save-filter">Сохранить</button>
      </div>
    </div>
  </div>

  <div class="modal-background modal-spp" style="display:none">
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
    background-color: #BC83F7 !important;
    color:#000000;
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
    color: #000000 !important;
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
  .has-data{
    background-color: rgba(0,200,0,0.45) !important;
  }
  .no-comps{
    color: grey;
  }
  .warning-cell{
    background-color: rgba(255, 159, 1, 0.5) !important;
    font-weight: bolder;
  }
  .warning-cell:hover{
    background-color: rgba(255, 159, 1, 0.2) !important;
  }
  .sticked-left{
    position: sticky;
    left: 0;
    background-color: #BC83F7 !important;
    color:#f5f5f5;
    /* font-weight: bolder; */
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
  .spp-btn{
    cursor: pointer;
  }
  .spp-btn:hover{
    background-color: #eee6f7;
  }
  .spp-btn:active{
    background-color: #d4b5f5;
  }
  #fitler-form{
    padding-top: 5px;
    border-top: 1px solid rgba(0,0,0,0.25)
  }
  .active-select{
    border-top: 2px solid #BC83F7 !important;
    border-bottom: 2px solid #BC83F7 !important;
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
  @keyframes spin{
    0% {transform: rotate(0deg);}
    100% {transform: rotate(360deg);}
  }
</style>
<script type="text/javascript">
  const url = '/admin/panel/wb/ajax/analytics/';

  $(document).on('click', '#show-modal', function(e){
    // e.preventDefault();
    $('.modal-filter').show();
  })
  $(document).on('click', '.modal-filter', function(e){
    // e.preventDefault();
    if( !$('.modal-window').is(e.target) && $('.modal-window').has(e.target).length == 0 ){
      $('.modal-filter').hide();
      getTableData( true )
    };
  })

  $(document).on('click', '.spp-btn', function(e){
    $('.modal-spp').show();
    var nmid = $(this).attr('data-nmid');
    var date = $(this).attr('data-date');
    var model = $(this).attr('data-model');
    $('.name-spp').html("Детализация для " + model + " (" + date + ")");
    getSppDetail( nmid, date );
  })
  $(document).on('click', '.modal-spp', function(e){
    if( !$('.modal-window').is(e.target) && $('.modal-window').has(e.target).length == 0 ){
      $('#body-spp').html();
      $('.modal-spp').hide();
    };
  })

  function getSppDetail( nmid, date ){
    $('#body-spp').html('\
      <div class="spin-wrapper">\
        <div class="spinner">\
        </div>\
      </div>\
      ');
    $.ajax({
      url: url + 'getSppDetail.php',
      method: "POST",
      data: {nmid:nmid, date:date},
      success: function(response){
        $('#body-spp').html(response);
      }
    })
  }

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

  $(document).on('click', '#update-today-data', function(e){
    e.preventDefault();
    $('#table-block').html('\
      <div class="spin-wrapper">\
        <div class="spinner">\
        </div>\
      </div>\
      ');
    $.ajax({
      url: '/admin/panel/engine/wb/analytics/topAnalytics.php',
      method: 'POST',
      success: function(response){
        getTableData( false )
      },
      error: function(response){
        alert('Ошибка обновления');
      }
    });
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

  getTableData(false);
</script>
<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>
