<script src="lib/chart.js" type="text/javascript"></script>
<?php
CModule::IncludeModule('panel.manager');
$dbPanel = new DBPanel;
////////////////////////////////////////////////////////////////////// IP
$perHour_ip = json_decode( file_get_contents('/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/ozon/logs/IP/stat/perHour.json'), 1 );
$timePostings_ip = $perHour_ip['date'];
unset($perHour_ip['date']);

$strSql = "SELECT * FROM ozon_postings_shares_IP";
$resultDB = $dbPanel->Query($strSql, false, $err_mess.__LINE__);
$rows = $dbPanel->fetchAll( $resultDB );
$postingsRaw = [];
foreach( $rows as $row ){
  if ( isset($allPeriodPostings[$row['date']]) ){
    $allPeriodPostings[$row['date']] += $row['quantity'] * $row['price'];
  }else{
    $allPeriodPostings[$row['date']] = $row['quantity'] * $row['price'];
  }
  if ( isset($postingsRaw[$row['date']][$row['type']]) ){
    $postingsRaw[$row['date']][$row['type']] += $row['quantity'] * $row['price'];
  }else{
    $postingsRaw[$row['date']][$row['type']] = $row['quantity'] * $row['price'];
  }
}
$postings_ip = [];
foreach ( $postingsRaw as $date => $types){
  foreach ($types as $type => $value) {
    if ( $allPeriodPostings[$date] != 0 ){
      $postings_ip[$date][$type] = $value / $allPeriodPostings[$date] * 100;
    }else{
      $postings_ip[$date]['Нет_данных'] = 100;
    }
  }
}
// echo "<pre>";
// var_dump($allPeriodPostings);
// echo "</pre>";
unset($postingsRaw);
unset($allPeriodPostings);

////////////////////////////////////////////////////////////////////// TI
$perHour_ti = json_decode( file_get_contents('/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/ozon/logs/TI/stat/perHour.json'), 1 );
$timePostings_ti = $perHour_ti['date'];
unset($perHour_ti['date']);

$strSql = "SELECT * FROM ozon_postings_shares_TI";
$resultDB = $dbPanel->Query($strSql, false, $err_mess.__LINE__);
$rows = $dbPanel->fetchAll( $resultDB );
$postingsRaw = [];
foreach( $rows as $row ){
  if ( isset($allPeriodPostings[$row['date']]) ){
    $allPeriodPostings[$row['date']] += $row['quantity'] * $row['price'];
  }else{
    $allPeriodPostings[$row['date']] = $row['quantity'] * $row['price'];
  }
  if ( isset($postingsRaw[$row['date']][$row['type']]) ){
    $postingsRaw[$row['date']][$row['type']] += $row['quantity'] * $row['price'];
  }else{
    $postingsRaw[$row['date']][$row['type']] = $row['quantity'] * $row['price'];
  }
}
$postings_ti = [];
foreach ( $postingsRaw as $date => $types){
  foreach ($types as $type => $value) {
    if ( $allPeriodPostings[$date] != 0 ){
      $postings_ti[$date][$type] = $value / $allPeriodPostings[$date] * 100;
    }else{
      $postings_ti[$date]['Нет_данных'] = 100;
    }
  }
}
unset($postingsRaw);
unset($allPeriodPostings);
 ?>

<div class="charts-container" style="display:block">
   <div class="tabs-charts-control" style="margin-bottom: 20px">
     <button id="c-tab-ip" class="c-tab t-selected btn btn-warning">Кабинет ИП</button>
     <button id="c-tab-ti" class="c-tab t-selected btn btn-light">Кабинет ТИ</button>
   </div>
  <div class="ip-charts" style="">
     <div class="row">
       <div class="col-md-6 col-sm-12">

         <div class="detail-block py-2 pl-4">
           <h3 class="mb-4 font-semibold text-xl">Динамика остатков FBO</h3>
           <hr>
           <h5 class="mb-4 font-semibold text-xl">Средние значения за период</h5>
           <form id="stat-period-form_ip" action="">
             <div class="d-1 d-d">
               <label for="input-date-log" class="font-bold mr-4"><b>От</b></label>
               <input type="date" name="min_date_stat" class="btn btn-light" style="margin-left:10px" id="input-date-min" value="">
             </div>
             <div class="d-2 d-d">
               <label for="input-date-log" class="font-bold mr-4"><b>до</b></label>
               <input type="date" name="max_date_stat" class="btn btn-light" style="margin-left:10px" id="input-date-max" value="">
             </div>
             <button id="show-stat-period_ip" class="btn btn-light" style="margin-left:10px">Показать</button>
           </form>
           <div class="avg-stats pt-4" style="display:flex; flex-direction: column">
             <span style="display:flex; flex-direction:row" id="avg-stock_ip"></span>
             <span style="display:flex; flex-direction:row" id="avg-price_ip"></span>
           </div>
         </div>
         <hr>
         <div class="detail-block py-2 pl-4">
           <h5 class="mb-4 font-semibold text-xl">Детализация</h5>
           <form id="settings-log-form" action="">
             <label for="input-date-log" class="font-bold mr-4"><b>Дата лога</b></label>
             <input type="date" name="date" class="btn btn-light" style="margin-left:10px" id="input-date-log" value="">
             <button id="show-log-ip" class="btn btn-light" style="margin-left:10px">Показать</button>
           </form>
         </div>
         <div class="list-ill list-ill_ip mt-2">

         </div>
       </div>
       <div class="col-md-6 col-sm-12">
         <i style="text-align: right;display:block; font-size: 14px; opacity: 0.5;"><?print_r( 'Последнее обновление было ' . date('Y.m.d G:i:s', filectime('/var/www/bitrix/data/www/tempusshop.ru/admin/modules/OzonImport/logs/stat/stock.txt')) );?></i>
         <div id="stats-graph_ip">
           <canvas class="my-4 w-100" id="stats_cost_fbo_ip" width="900" height="300"></canvas>
           <canvas class="my-4 w-100" id="stats_stock_fbo_ip" width="900" height="300"></canvas>
         </div>
       </div>
     </div>
     <hr>
     <div class="row">
       <div class="col-md-6 col-sm-12">

         <div class="detail-block py-2 pl-4">
           <h3 class="mb-4 font-semibold text-xl">Доля заказов по моделям продаж</h3>
           <div class="per-day-block" style="margin-right:auto; height: 400px">
             <canvas class="my-4" id="postings_per_day_ip" height="400"></canvas>
           </div>
           <form id="settings-period-form_ip" action="" style="display:none;">
             <label for="input-date-log" class="font-bold mr-4"><b>Период</b></label>
             <input type="date" name="min-date-postings" class="btn btn-light" style="margin-left:10px" id="min-date-postings-ip" value="">
             <span>--</span>
             <input type="date" name="max-date-postings" class="btn btn-light" style="margin-left:10px" id="max-date-postings-ip" value="">
             <button id="show-period-ip" class="btn btn-light" style="margin-left:10px">Показать</button>
           </form>
         </div>

       </div>
       <div class="col-md-6 col-sm-12">
         <div style="margin-right:auto; margin-left:auto; width: 400px; padding-top: 40px" class="per-hour-block_ip">
           <span style="font-size: 14px; opacity: 0.5"><i class="last-update-share-ip">Последнее обновление: <?=$timePostings_ip?></i></span>
           <canvas class="my-4" id="postings_per_hour_ip" width="100" height="100"></canvas>
         </div>
         <div class="row" style="margin-bottom: 20px; padding-right: 20px;">
           <button id="update-shares-ip" class="btn btn-warning" style="width: fit-content; margin-left:auto">Обновить</button>
         </div>
       </div>
     </div>
   </div>

  <div class="ti-charts" style="display:none">
     <div class="row" style="">
       <div class="col-md-6 col-sm-12">

         <div class="detail-block py-2 pl-4">
           <h3 class="mb-4 font-semibold text-xl">Динамика остатков FBO</h3>
           <hr>
           <h5 class="mb-4 font-semibold text-xl">Средние значения за период</h5>
           <form id="stat-period-form_ti" action="">
             <div class="d-1 d-d">
               <label for="input-date-log" class="font-bold mr-4"><b>От</b></label>
               <input type="date" name="min_date_stat" class="btn btn-light" style="margin-left:10px" id="input-date-min-ti" value="">
             </div>
             <div class="d-2 d-d">
               <label for="input-date-log" class="font-bold mr-4"><b>до</b></label>
               <input type="date" name="max_date_stat" class="btn btn-light" style="margin-left:10px" id="input-date-max-ti" value="">
             </div>
             <button id="show-stat-period_ti" class="btn btn-light" style="margin-left:10px">Показать</button>
           </form>
           <div class="avg-stats pt-4" style="display:flex; flex-direction: column">
             <span style="display:flex; flex-direction:row" id="avg-stock_ti"></span>
             <span style="display:flex; flex-direction:row" id="avg-price_ti"></span>
           </div>
         </div>
         <hr>
         <div class="detail-block py-2 pl-4">
           <h5 class="mb-4 font-semibold text-xl">Детализация</h5>
           <form id="settings-log-form_ti" action="">
             <label for="input-date-log" class="font-bold mr-4"><b>Дата лога</b></label>
             <input type="date" name="date" class="btn btn-light" style="margin-left:10px" id="input-date-log-ti" value="">
             <button id="show-log-ti" class="btn btn-light" style="margin-left:10px">Показать</button>
           </form>
         </div>
         <div class="list-ill list-ill_ti mt-2">

         </div>
       </div>
       <div class="col-md-6 col-sm-12">
         <i style="text-align: right;display:block; font-size: 14px; opacity: 0.5;"><?print_r( 'Последнее обновление было ' . date('Y.m.d G:i:s', filectime('/var/www/bitrix/data/www/tempusshop.ru/admin/modules/OzonImport2/logs/stat/stock.txt')) );?></i>
         <div id="stats-graph_ti">
           <canvas class="my-4 w-100" id="stats_cost_fbo_ti" width="900" height="300"></canvas>
           <canvas class="my-4 w-100" id="stats_stock_fbo_ti" width="900" height="300"></canvas>
         </div>
       </div>
     </div>
     <hr>
     <div class="row" >
       <div class="col-md-6 col-sm-12">

         <div class="detail-block py-2 pl-4">
           <h3 class="mb-4 font-semibold text-xl">Доля заказов по моделям продаж</h3>
           <div class="per-day-block" style="margin-right:auto; height: 400px">
             <canvas class="my-4" id="postings_per_day_ti" height="400"></canvas>
           </div>
           <form id="settings-period-form" action="" style="margin-top:20px; display:none;">
             <label for="input-date-log" class="font-bold mr-4"><b>Период</b></label>
             <input type="date" name="min-date-postings" class="btn btn-light" style="margin-left:10px" id="min-date-postings" value="">
             <span>--</span>
             <input type="date" name="max-date-postings" class="btn btn-light" style="margin-left:10px" id="max-date-postings" value="">
             <button id="show-period" class="btn btn-light" style="margin-left:10px">Показать</button>
           </form>
         </div>

       </div>
       <div class="col-md-6 col-sm-12">
         <div style="margin-right:auto; margin-left:auto; width: 400px; padding-top: 40px" class="per-hour-block_ti">
           <span style="font-size: 14px; opacity: 0.5; text-align:center; display: block"><i class="last-update-share">Последнее обновление: <?=$timePostings_ti?></i></span>
           <canvas class="my-4" id="postings_per_hour_ti"></canvas>
         </div>
         <div class="row" style="margin-bottom: 20px; padding-right: 20px;">
           <button id="update-shares-ti" class="btn btn-warning" style="width: fit-content; margin-left:auto">Обновить</button>
         </div>
       </div>
     </div>
   </div>

</div>

<style media="screen">
  .c-tab{
    width: 200px;
    padding: 10px;
    font-size: 17px;
  }
  .d-d{
    flex-direction: row;
    width: fit-content;
  }
  #stat-period-form_ti, #stat-period-form_ip{
    display: flex;
    flex-direction: row;
    gap: 10px;
  }
  #stat-period-form_ti , #stat-period-form_ip{
    display: flex;
    flex-direction: row;
    gap: 10px;
  }
  canvas{
    width: 100%;
  }
  @media (max-width: 867px){
    .tabs-charts-control{
      display: flex;
      flex-direction: row;
    }
    #stat-period-form_ti, #stat-period-form_ip{
      flex-direction: column;
    }
    .d-d{
      flex-direction: row;
      gap: 10px;
      margin-bottom: 10px;
    }
    #settings-log-form_WR, #settings-log-form_TL{
      display: flex;
      flex-direction: column;
      gap: 10px;
    }
    #settings-log-form_WR label, #settings-log-form_TL label{
      display: none;
    }
    #settings-log-form_WR input, #settings-log-form_TL input{
      width: 98%;
    }
    .per-day-block{
      width: 100%;
    }
    #postings_per_day_ip, #postings_per_day_ti{
      width: 100%;
      height: 400px !important;
    }
    canvas{
      width: 100%;
    }
  }
</style>


<script type="text/javascript">

////////////////////////// COMMON
if ( screen.width > 768 ){
  var responsiveFlag = true;
}else{
  var responsiveFlag = false;
}
document.addEventListener('DOMContentLoaded', function() {
  $(document).on('click', '#show-stat-period_ti', function(e){
    e.preventDefault();
    if ( $('#input-date-min-ti').val() == '' && $('#input-date-max-ti').val() == '' ){
      showStatByPeriod(false, 'ti');
    }else{
      showStatByPeriod(true, 'ti');
    }
  })
  $(document).on('click', '#show-stat-period_ip', function(e){
    e.preventDefault();
    if ( $('#input-date-min-ip').val() == '' && $('#input-date-max-ip').val() == '' ){
      showStatByPeriod(false, 'ip');
    }else{
      showStatByPeriod(true, 'ip');
    }
  })
});

$(document).on('click', '#c-tab-ti', function(e){
  e.preventDefault();
  $('.c-tab').removeClass('btn-warning');
  $('.c-tab').addClass('btn-light');
  $(this).removeClass('btn-light');
  $(this).addClass('btn-warning');
  $('.ip-charts').hide();
  $('.ti-charts').show();
  showStatByPeriod(false, 'ti');
  getStockLog( false, 'ti' );
})
$(document).on('click', '#c-tab-ip', function(e){
  e.preventDefault();
  $('.c-tab').removeClass('btn-warning');
  $('.c-tab').addClass('btn-light');
  $(this).removeClass('btn-light');
  $(this).addClass('btn-warning');
  $('.ti-charts').hide();
  $('.ip-charts').show();
  showStatByPeriod(false, 'ip');
  getStockLog( false, 'ip' );
})
$(document).on('click', '#update-shares-ti', function(e){
  e.preventDefault();
  updateShares('ti');
})
$(document).on('click', '#update-shares-ip', function(e){
  e.preventDefault();
  updateShares('ip');
})
$(document).on('click', '#show-log-ti', function(e){
  e.preventDefault();
  if ( $('#input-date-log-ti').val() == '' ){
    getStockLog(false, 'ti');
  }else{
    getStockLog(true, 'ti');
  }
})
$(document).on('click', '#show-log-ip', function(e){
  e.preventDefault();
  if ( $('#input-date-log-ip').val() == '' ){
    getStockLog(false, 'ip');
  }else{
    getStockLog(true, 'ip');
  }
})

function showStatByPeriod(flag = false, cabinet){

  if (cabinet == 'ti'){
    var url = '/admin/panel/ozon/ajax/stat/TI/get_stock_stat_by_period.php';
    var postfix = '_ti';
  }else{
    var url = '/admin/panel/ozon/ajax/stat/IP/get_stock_stat_by_period.php';
    var postfix = '_ip';
  }
  if ( flag == true ){
    var data = $('#stat-period-form' + postfix).serialize();
  }else{
    var dateNow = new Date();
    var data = {max_date_stat: dateNow.toISOString().split('T')[0]}
  }
  $.ajax({
    url: url,
    method: 'post',
    data: data,
    success: function(response){
      var result = $.parseJSON(response);
      if ( result.error == '' ){
        $('#avg-stock'+postfix).html('<b>Средний остаток, шт.:&nbsp</b>' + result.stock);
        $('#avg-price'+postfix).html('<b>Средняя себестоимость, ₽:&nbsp</b>' + result.price);
        $('#stats-graph'+postfix).html('<canvas class="my-4 w-100" id="stats_cost_fbo'+postfix+'" height="300"></canvas><canvas class="my-4 w-100" id="stats_stock_fbo'+postfix+'" height="300"></canvas><canvas class="my-4 w-100" id="stats_model_fbo'+postfix+'" height="300"></canvas>');

        var ctx3 = document.getElementById('stats_cost_fbo' + postfix);
        console.log(ctx3)
        var myChart = new Chart(ctx3, {
          type: 'bar',
         data: {
          labels: Object.keys(result.statsPrice),
          datasets: [
            {
              label: 'FBO',
              data: Object.values(result.statsPrice),
              backgroundColor: 'rgba(0,123,255,0.5)',
              pointBackgroundColor: '#007bff'
            },
          ]
        },
         options: {
           responsive: true,
           plugins: {
             legend: {
               position: 'top',
             },
             title: {
               display: true,
               text: 'Себестоимость FBO, ₽'
             }
           },
           responsive: responsiveFlag,
         },
        })

        var ctx4 = document.getElementById('stats_stock_fbo' + postfix);
        var myChart = new Chart(ctx4, {
          type: 'bar',
         data: {
          labels: Object.keys(result.statsStock),
          datasets: [
            {
              label: 'FBO',
              data: Object.values(result.statsStock),
              backgroundColor: 'rgba(123,0,255,0.5)',
              pointBackgroundColor: '#007bff'
            },
          ]
        },
         options: {
           responsive: true,
           plugins: {
             legend: {
               position: 'top',
             },
             title: {
               display: true,
               text: 'Динамика остатков FBO, шт.'
             }
           },
           responsive: responsiveFlag,
         },
        })

        var ctx5 = document.getElementById('stats_model_fbo'+postfix);
        var myChart = new Chart(ctx5, {
          type: 'bar',
         data: {
          labels: Object.keys(result.statsModel),
          datasets: [
            {
              label: 'FBO',
              data: Object.values(result.statsModel),
              backgroundColor: 'rgba(123,0,255,0.5)',
              pointBackgroundColor: '#007bff'
            },
          ]
        },
         options: {
           responsive: true,
           plugins: {
             legend: {
               position: 'top',
             },
             title: {
               display: true,
               text: 'Широта ассортимента FBO, шт.'
             }
           },
           responsive: responsiveFlag,
         },
        })

      }else{
        alert(result.error);
      }
    }
  })
}
showStatByPeriod(false, 'ip');
// showStatByPeriod(true);

function drawPostingsCharts(cabinet){
  if (cabinet == 'ti'){
    var postfix = '_ti';
    var array = <?=json_encode( $postings_ti );?>;
    var ph_labels = <?=json_encode( array_keys($perHour_ti) )?>;
    var ph_values = <?=json_encode( array_values($perHour_ti) )?>;
  }else{
    postfix = '_ip';
    var array = <?php echo json_encode( $postings_ip );?>;
    var ph_labels = <?=json_encode( array_keys($perHour_ip) )?>;
    var ph_values = <?=json_encode( array_values($perHour_ip) )?>;
  }
  const labels = Object.keys(array);
  const fbs = Object.values(array).map(item => item.fbs);
  const rfbs = Object.values(array).map(item => item.rfbs);
  const fbo = Object.values(array).map(item => item.fbo);
  var postings_pd_ip = document.getElementById('postings_per_day'+postfix);
  var myChart = new Chart(postings_pd_ip, {
    type: 'bar',
    data: {
      labels: labels,
      datasets: [{
        label: 'Доля FBS, %',
        data: fbs,
        lineTension: 0,
        backgroundColor: [
          "rgba(123,0,255,0.5)",
        ],
        pointBackgroundColor: 'blue'
      },
      {
        label: 'Доля rFBS, %',
        data: rfbs,
        lineTension: 0,
        backgroundColor: [
          "rgba(255,255,0,0.5)",
        ],
        pointBackgroundColor: 'blue'
      },
      {
        label: 'Доля FBO, %',
        data: fbo,
        backgroundColor: [
          "rgba(0,123,255,0.5)",
        ],
        pointBackgroundColor: 'blue'
      }
    ]
    },
    options: {
      scales: {
        x: {
          stacked: true
        },
        y: {
          stacked: true
        },
      },
      plugins: {
        legend: {
          position: 'top',
        },
        title: {
          display: false,
          text: 'Данные за эту неделю'
        }
      },
      responsive: responsiveFlag,
    },
  })

  var postings_ph_ip = document.getElementById('postings_per_hour'+postfix);
  var myChart = new Chart(postings_ph_ip, {
    type: 'pie',
    data: {
      labels: ph_labels,
      datasets: [{
        label: 'Доля, %',
        data: ph_values,
        lineTension: 0,
        backgroundColor: [
          "rgba(0,123,255,0.5)",
          "rgba(123,0,255,0.5)",
          "rgba(255,255,0,0.5)",
        ],
        borderColor: 'rgba(0,0,0,0.5)',
        borderWidth: 1,
        pointBackgroundColor: 'blue'
      }
    ]
    },
    options: {
      scales: {
        yAxes: [{
          ticks: {
            beginAtZero: false
          }
        }]
      },
      plugins: {
        legend: {
          position: 'top',
        },
      },
      responsive: responsiveFlag,
    },
  })
}
drawPostingsCharts('ti');
drawPostingsCharts('ip');

function updateShares(cabinet){
  if ( cabinet == 'ti' ){
    var url_upd = '/admin/panel/engine/ozon/getPostingsStatPH.php';
    var url_draw = '/admin/panel/ozon/ajax/stat/updateOrderShares.php';
    var postfix = '_ti';
  }else{
    var url_upd = '/admin/modules/ozon/cron/getPostingsStatPH.php';
    var url_draw = '/admin/modules/ozon/ajax/updateOrderShares.php';
    var postfix = '_ip';
  }
  $.ajax({
    url: url_upd,
    method: 'POST',
    success: function(response){
      console.log('ok');
      $.ajax({
        url: url_draw,
        method: 'POST',
        success: function(data){
          var prep = $.parseJSON(data)
          console.log( Object.keys(prep) );
          $('#postings_per_hour').remove();
          $('.per-hour-block'+postfix).html('<span style="font-size: 14px; opacity: 0.5; text-align:center; display: block"><i class="last-update-share">Последнее обновление: только что</i></span><canvas class="my-4" id="postings_per_hour'+postfix+'"></canvas>');
          var ctx5 = document.getElementById('postings_per_hour' + postfix);
          var myChart = new Chart(ctx5, {
            type: 'pie',
            data: {
              labels: Object.keys(prep),
              datasets: [{
                label: 'Доля, %',
                data: Object.values(prep),
                lineTension: 0,
                backgroundColor: [
                  "rgba(123,0,255,0.5)",
                  "rgba(0,123,255,0.5)",
                  "rgba(255,255,0,0.5)",
                ],
                borderColor: 'rgba(0,0,0,0.5)',
                borderWidth: 1,
                pointBackgroundColor: 'blue'
              }
            ]
            },
            options: {
              scales: {
                yAxes: [{
                  ticks: {
                    beginAtZero: false
                  }
                }]
              },
              plugins: {
                legend: {
                  position: 'top',
                },
              }
            },
          })
        }
      });
    },
    error: function(response){
      console.log('system error');
    }
  });
}

function getStockLog( flag, cabinet ){
  if ( cabinet == 'ti'){
    var url = '/admin/panel/ozon/ajax/stat/TI/get_stock_by_date.php';
    var postfix = '_ti';
  }else{
    var url = '/admin/panel/ozon/ajax//stat/IP/get_stock_by_date.php';
    var postfix = '_ip';
  }
  if ( flag == true ){
    var data = $('#settings-log-form').serialize();
  }else{
    var data = {date: new Date().toISOString().slice(0, 10)};
  }
  $.ajax({
    url: url,
    method: 'post',
    data: data,
    success: function(response){
      $('.list-ill'+postfix).html(response);
      $('.list-ill'+postfix).slideDown();
    },
    error: function(response){
      $('.list-ill'+postfix).html(response);
      $('.list-ill'+postfix).slideDown();
    }
  })

}
getStockLog(false, 'ip');

function getStockLogCsv(){

  var url = '/admin/panel/ozon/ajax/stat/IP/get_stock_by_date_csv.php';
  var postfix = '_ip';


  var data = {date: new Date().toISOString().slice(0, 10)};

  $.ajax({
    url: url,
    method: 'post',
    data: data,
    success: function(response){

    },
    error: function(response){

    }
  })

}
///////////////////////// TI

// SHARES STATS TI



</script>
