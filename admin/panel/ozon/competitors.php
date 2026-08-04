<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");?>
<?if(!CModule::IncludeModule('panel.manager'))return;?>
<?$APPLICATION->SetTitle('Конкуренты - OZON модуль');?>
<?$APPLICATION->SetPageProperty("page_h1", "Конкуренты");?>
<link href="<?=SITE_TEMPLATE_PATH?>/css/products.css" rel="stylesheet">
<script src="<?=SITE_TEMPLATE_PATH?>/js/products.js"></script>
<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js" integrity="sha256-lSjKY0/srUM9BE3dPm+c4fBo1dky2v27Gdjm2uoZaL0=" crossorigin="anonymous"></script>
<link href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css" rel="stylesheet">
<?CModule::includeModule('panel.manager');?>
<?
$dbPanel = new DBPanel;
$strSql = "SELECT DISTINCT seller FROM ozon_competitors";
$res = $dbPanel->query($strSql);
$rows = $dbPanel->fetchAll($res);
$sellers = [];
foreach ( $rows as $row ){
  $sellers[] = $row['seller'];
}
$filterFileName = '/var/www/bitrix/data/www/tempusshop.ru/admin/panel/ozon/ajax/analytics/filter.json';
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
      <input type="hidden" name="sellers-filter" value="">
      <button id="show-table" class="btn btn-warning">Показать</button>
    </form>
    <button id="show-modal" class="btn btn-light">Фильтр</button>
  </div>
  <hr>

  <div class="modal-background" style="display:none">
    <div class="modal-window">
      <div class="head">
        <h3 class="modal-name">Фильтр по продавцам</h3>
        <!-- <span>Закрыть</span> -->
      </div>
      <!-- <hr> -->
      <div class="body">
        <button class="btn btn-warning" style="margin-left:auto; margin-bottom: 15px; display:flex" id="save-filter">Сохранить</button>
        <form id="comps-form" action="" method="post">
          <? $i = 1; ?>
          <? foreach( $sellers as $name ):?>
          <?if ( in_array($name, $filter) ):?>
          <div id="s-<?=$i;?>" class="row sell-row selected">
            <div class="input-block"><span><?=$name?></span></div>
            <div class="input-block"><input class="chkbx" type="checkbox" id="i-<?=$i?>" name="<?=$name?>" value="1" checked></div>
          </div>
          <?else:?>
          <div id="s-<?=$i;?>" class="row sell-row insel">
            <div class="input-block"><span><?=$name?></span></div>
            <div class="input-block"><input class="chkbx" type="checkbox" id="i-<?=$i?>" name="<?=$name?>" value=""></div>
          </div>
          <? endif; ?>
          <? $i++; ?>
        <? endforeach; ?>
        </form>
      </div>
    </div>
  </div>

  <div id="table-block">

  </div>
</div>
<style media="screen">
  #container{
    overflow-x: auto;
    /* height: 70%; */
  }
  th{
    font-size: 14px;
    text-align: center !important;
    background-color: #ffc107 !important;
  }
  td{
    text-align: center;
  }
  tr{
    border-bottom: rgba(0,0,0,0.25);
  }
  .bordered{
    border-right: 1px solid rgba(0,0,0,0.3);
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
    background-color: rgba(200,0,0,0.3) !important;
  }
  .red-1{
    background-color: rgba(200,0,0,0.1) !important;
  }
  .no-data{
    color: grey;
  }
  .sticked-left{
    position: sticky;
    left: 0;
    background-color: #ffc107 !important;
  }
  .sticked-price{
    position: sticky;
    left: 210px;
    background-color: #ffc107 !important;
  }
  .row-header{
    font-size: 13px;
    font-weight: bolder;
  }
  table a {
    text-decoration: none !important;
    color: black !important;
  }
  a:hover{
    color: white !important;
  }
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
    height: 820px;
    width: 36%;
    background-color: white;
    margin: 5% auto auto auto;
    z-index: 999;
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
  .name-block, .input-block{
    width: 50% !important;
  }
  .chkbx{
    margin: auto 0 auto auto;
    display: flex;
  }
  .active-select{
    border-top: 2px solid #ffc107 !important;
    border-bottom: 2px solid #ffc107 !important;
  }
  .active-select-black{
    border-top: 2px solid black !important;
    border-bottom: 2px solid black !important;
  }

  .date-picker-block{
    display: flex;
    flex-direction: row;
    gap: 20px;
  }
  #date-form{
    width: fit-content;
  }
  #comps-form{
    padding: 10px;
    border-top: 1px solid rgba(0,0,0,0.25)
  }
  .notif{
    display: flex;
    padding: 5px;
    font-size: 16px;
  }

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
  const url = '/admin/panel/ozon/ajax/analytics/';

  $(document).on('click','.table-data tbody tr', function(e){
    if ( $(this).hasClass('active-select') ){
      $(this).removeClass('active-select');
    }else{
      $('.table-data tbody tr').removeClass('active-select');
      $(this).addClass('active-select');
    }
  })

  $(document).on('click', '#show-modal', function(e){
    // e.preventDefault();
    $('.modal-background').show();
  })
  $(document).on('click', '.modal-background', function(e){
    // e.preventDefault();
    if( !$('.modal-window').is(e.target) && $('.modal-window').has(e.target).length == 0 ){
      $('.modal-background').hide();
      getTableData( true );
    };
  })

  // $(document).on('click', '.chkbx', function(e){
  //   e.preventDefault();
  // })

  // $(document).on('click','.insel', function(e){
  //   var inputId = $(this).attr('id').split('-')[1];
  //   $('#i-' + inputId).val(1).change();
  //   $('#i-' + inputId).prop('checked', true);
  //   $(this).removeClass('insel');
  //   $(this).addClass('selected');
  // })
  //
  // $(document).on('click','.selected', function(e){
  //   var inputId = $(this).attr('id').split('-')[1];
  //   $('#i-' + inputId).val(0).change();
  //   $('#i-' + inputId).prop('checked', false);
  //   $(this).addClass('insel');
  //   $(this).removeClass('selected');
  // })

  $(document).on('click', '#save-filter', function(e){
    e.preventDefault();
    var dataC = $('#comps-form').serialize();
    $.ajax({
      url: url + 'saveFilter.php',
      method: "POST",
      data: {data: dataC},
      success: function(response){
        console.log('Фильтр сохранен');
        alert('Фильтр сохранен');
        $('.modal-background').hide();
        getTableData( true );
      }
    })
  })

  function getTableData(flag){
    var dateFrom = $('#dateFrom').val();
    var dateTo = $('#dateTo').val();
    var dataC = $('#comps-form').serialize();
    $('#table-block').html('\
      <div class="spin-wrapper">\
        <div class="spinner">\
        </div>\
      </div>\
      ');
    $.ajax({
      url: url + 'getCompetitorsData.php',
      method: "POST",
      data: {dateFrom: dateFrom, dateTo: dateTo, data: dataC},
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
