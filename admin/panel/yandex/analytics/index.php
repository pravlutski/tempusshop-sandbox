<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");?>
<?if(!CModule::IncludeModule('panel.manager'))return;?>
<?$APPLICATION->SetTitle('Аналитика - Yandex модуль');?>
<?$APPLICATION->SetPageProperty("page_h1", "Аналитика");?>
<link href="<?=SITE_TEMPLATE_PATH?>/css/settings.css" rel="stylesheet">

<div id="container">
  <div class="date-picker-block">
    <form id="date-form" action="" method="post">
      <input id="dateFrom" type="date" name="dateFrom" class="form form-control input-f" value="" onchange="getdata()">
      <span class="label"> -- </span>
      <input id="dateTo" type="date" name="dateTo" class="form form-control input-f" value="" onchange="getdata()">
    </form>
    <button id="show-modal" class="btn btn-light">Фильтр</button>
    <!-- <button id="update-today-data" class="btn btn-primary">Обновить данные за сегодня</button> -->
  </div>
  <hr>
  <div id="table-block">

  </div>
</div>

<style media="screen">
.date-picker-block{
  position: sticky;
  left: 0px;
  display: flex;
  flex-direction: row;
}
#date-form{
  display: flex;
  flex-direction: row;
  gap: 10px;
  margin-right: 10px;
}
.input-f{
  max-width: 160px;
}
.btn{
  height: fit-content;
  margin-right: 10px;
}
/* table */
#container{
  overflow-x: auto;
}
table{
  width: 100%;
}
td, th{
  min-width: 155px !important;
  text-align: center !important;
}
tr{
  border-top: 1px solid rgba(0,0,0,0.15) !important;
  border-bottom: 1px solid rgba(0,0,0,0.15) !important;
}
td{
  padding: 10px;
}
th{
  font-size: 15px;
}
.active-select{
  border-bottom: 2px solid rgba(252, 214, 98, 1) !important;
}
.group-divider{
  min-width: 155px !important;
  border-left: 1px solid rgba(0,0,0,0.15);
  border-right: 1px solid rgba(0,0,0,0.15);
}
.header-cell{
  background-color: rgba(252, 214, 98, 1);
}
.no-data-cell{
  color: rgba(0,0,0,0.5);
}
.model-sticky{
  position: sticky;
  left: 0;
  background-color: rgba(252, 214, 98, 1);
}
.green-lighter{
  background-color: rgba(0,200,0,0.1) !important;
}
.green-light{
  background-color: rgba(0,200,0,0.3) !important;
}
.green-medium{
  background-color: rgba(0,200,0,0.45) !important;
}
.green-hard{
  background-color: rgba(0,230,0,0.5) !important;
}
.red-lighter{
  background-color: rgba(200,0,0,0.1) !important;
}
.red-light{
  background-color: rgba(200,0,0,0.4) !important;
}
.red-medium{
  background-color: rgba(200,0,0,0.45) !important;
}
.red-hard{
  background-color: rgba(230,0,0,0.5) !important;
}


</style>

<script type="text/javascript">
  function getdata(){
    $.ajax({
      url: '/admin/panel/yandex/ajax/analytics/getDataForPeriod.php',
      method: "POST",
      data:{
        dateFrom: $('#dateFrom').val(),
        dateTo: $('#dateTo').val(),
      },
      success: function(response){
        $('#table-block').html(response);
      }
    });
  }

  $(document).on('click','table tbody tr', function(e){
    if ( $(this).hasClass('active-select') ){
      $(this).removeClass('active-select');
      return
    }
    $('table tbody tr').removeClass('active-select');
    $(this).addClass('active-select');
  })

  getdata();
</script>

<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>
