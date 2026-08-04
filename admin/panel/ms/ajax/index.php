<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");?>
<?if(!CModule::IncludeModule('panel.manager'))return;?>
<?$curdate = date('Y-m-d');?>
<?$APPLICATION->SetTitle('Главная - OZON модуль');?>
<?$APPLICATION->SetPageProperty("page_h1", "Статистика по выгрузке за сегодня ($curdate)");?>
<?
opcache_reset();
global $DB;
global $USER;
$arGroups = $USER->GetUserGroupArray();

CModule::IncludeModule("iblock");

$strSql = "SELECT * FROM wdhs_ozon_upload_status";
$results = $DB->Query($strSql, false, $err_mess.__LINE__);
while ($row = $results->Fetch()){
  $arLast[$row['agent']] = $row;
}

$tmp = file_get_contents('/var/www/bitrix/data/www/tempusshop.ru/admin/modules/OzonImport/logs/prices/shows/'.$curdate.'.txt');
$arTmp = explode('#SPLIT#',$tmp);
foreach ($arTmp as $key => $value) {
  $arSoloLog = json_decode($value,true);
  $arResult['TIME_PRICES'][] = $arSoloLog['TIME_START'];
  if($arSoloLog['UPDATE']['GOOD']){
    $arPrice[] = count($arSoloLog['UPDATE']['GOOD']);
  }
}
$tmp = file_get_contents('/var/www/bitrix/data/www/tempusshop.ru/admin/modules/OzonImport/logs/stocks/shows/'.$curdate.'.txt');
$arTmp = explode('#SPLIT#',$tmp);
foreach ($arTmp as $key => $value) {
  $arSoloLog = json_decode($value,true);
  $arResult['TIME_STOCK'][] = $arSoloLog['TIME_START'];
  if($arSoloLog['UPDATE']['GOOD']){
    $arStock[] = count($arSoloLog['UPDATE']['GOOD'])/5;
  }
}
?>

<div class="card">
  <div class="card-body">
    <h5 class="card-title">Теущий статус выгрузок</h5>
  </div>
  <ul class="list-group list-group-flush">
    <?if ($arLast['price']['status'] == 'COMPLETE'){?>
      <li class="list-group-item resize" style="justify-content: space-between;"><span><b style="margin-right:10px;">Цены: </b>Выгрузка завершена в <?=$arLast['price']['time']?></span><a href="/admin/modules/ozon/logs/price.php" class="card-link">Лог выгрузки цен</a></li>
    <?} else if ($arLast['price']['status'] == 'INCOMPLETE') {?>
      <li class="list-group-item resize"><b style="margin-right:10px;">Цены: </b>
        <div class="progress custom-bar">
          <div class="progress-bar bg-warning text-dark w-<?=$arLast['price']['percent']?>" style="width: <?=$arLast['price']['percent']?>%" role="progressbar" aria-valuenow="<?=$arLast['price']['percent']?>" aria-valuemin="0" aria-valuemax="100">Выгружаются <?=$arLast['price']['percent']?>%</div>
        </div>
      </li>
    <?} else {?>
      <li class="list-group-item resize"><b style="margin-right:10px;">Цены: </b><span style="color:red"> Статус не установлен.</span></li>
    <?}?>
    <?if ($arLast['stock']['status'] == 'COMPLETE'){?>
      <li class="list-group-item resize" style="justify-content: space-between;"><span><b style="margin-right:10px;">Остатки: </b>Выгрузка завершена в <?=$arLast['stock']['time']?></span><a href="/admin/modules/ozon/logs/stock.php" class="card-link">Лог выгрузки остатков</a></li>
    <?} else if ($arLast['stock']['status'] == 'INCOMPLETE') {?>
      <li class="list-group-item resize"><b style="margin-right:10px;">Остатки: </b>
        <div class="progress custom-bar">
          <div class="progress-bar bg-warning text-dark w-<?=$arLast['stock']['percent']?>" style="width: <?=$arLast['stock']['percent']?>%" role="progressbar" aria-valuenow="<?=$arLast['stock']['percent']?>" aria-valuemin="0" aria-valuemax="100">Выгружаются <?=$arLast['stock']['percent']?>%</div>
        </div>
      </li>
    <?} else {?>
      <li class="list-group-item resize"><b style="margin-right:10px;">Остатки: </b><span style="color:red"> Статус не установлен.</span></li>
    <?}?>
    <?if ($arLast['products']['status'] == 'COMPLETE'){?>
      <li class="list-group-item resize" style="justify-content: space-between;"><span><b style="margin-right:10px;">Товары: </b>Выгрузка завершена в <?=$arLast['products']['time']?></span><a href="/admin/modules/ozon/logs/products.php" class="card-link">Лог выгрузки товаров</a></li>
    <?} else if ($arLast['products']['status'] == 'INCOMPLETE') {?>
      <li class="list-group-item resize"><b style="margin-right:10px;">Товары: </b>
        <div class="progress custom-bar">
          <div class="progress-bar bg-warning text-dark w-<?=$arLast['products']['percent']?>" style="width: <?=$arLast['products']['percent']?>%" role="progressbar" aria-valuenow="<?=$arLast['products']['percent']?>" aria-valuemin="0" aria-valuemax="100">Выгружаются <?=$arLast['products']['percent']?>%</div>
        </div>
      </li>
    <?} else {?>
      <li class="list-group-item resize"><b style="margin-right:10px;">Товары: </b><span style="color:red">Статус не установлен.</span></li>
    <?}?>
  </ul>
</div>
<div class="row">
  <div class="col-md-6 col-sm-12">
    <canvas class="my-4 w-100" id="stats_price" width="900" height="300"></canvas>
  </div>
  <div class="col-md-6 col-sm-12">
    <canvas class="my-4 w-100" id="stats_stock" width="900" height="300"></canvas>
  </div>
</div>
<hr>
<div class="row">
  <div class="col-md-6 col-sm-12">

    <div class="detail-block py-2 pl-4">
      <h3 class="mb-4 font-semibold text-xl">Динамика остатков FBO</h3>
      <hr>
      <h5 class="mb-4 font-semibold text-xl">Средние значения за период</h5>
      <form id="stat-period-form" action="">
        <label for="input-date-log" class="font-bold mr-4"><b>От</b></label>
        <input type="date" name="min_date_stat" class="btn btn-light" style="margin-left:10px" id="input-date-min" value="">
        <label for="input-date-log" class="font-bold mr-4"><b>до</b></label>
        <input type="date" name="max_date_stat" class="btn btn-light" style="margin-left:10px" id="input-date-max" value="">
        <button id="show-stat-period" class="btn btn-light" style="margin-left:10px">Показать</button>
      </form>
      <div class="avg-stats pt-4" style="display:flex; flex-direction: column">
        <span style="display:flex; flex-direction:row" id="avg-stock"></span>
        <span style="display:flex; flex-direction:row" id="avg-price"></span>
      </div>
    </div>
    <hr>
    <div class="detail-block py-2 pl-4">
      <h5 class="mb-4 font-semibold text-xl">Детализация</h5>
      <form id="settings-log-form" action="">
        <label for="input-date-log" class="font-bold mr-4"><b>Дата лога</b></label>
        <input type="date" name="date" class="btn btn-light" style="margin-left:10px" id="input-date-log" value="">
        <button id="show-log" class="btn btn-light" style="margin-left:10px">Показать</button>
      </form>
    </div>
    <div class="list-ill mt-2">

    </div>
  </div>
  <div class="col-md-6 col-sm-12">
    <i style="text-align: right;display:block"><?print_r( 'Последнее обновление было ' . date('Y.m.d G:i:s', filectime('/var/www/bitrix/data/www/tempusshop.ru/admin/modules/OzonImport/logs/stat/stock.txt')) );?></i>
    <div id="stats-graph">
      <canvas class="my-4 w-100" id="stats_cost_fbo" width="900" height="300"></canvas>
      <canvas class="my-4 w-100" id="stats_stock_fbo" width="900" height="300"></canvas>
    </div>
  </div>
</div>
<hr>
<div class="row">
  <div class="col-md-6 col-sm-12">

    <div class="detail-block py-2 pl-4">
      <h3 class="mb-4 font-semibold text-xl">Доля заказов по моделям продаж</h3>
      <div class="per-day-block" style="margin-right:auto; height: 400px">
        <canvas class="my-4" id="postings_per_day" width="600" height="400"></canvas>
      </div>
      <form id="settings-period-form" action="">
        <label for="input-date-log" class="font-bold mr-4"><b>Период</b></label>
        <input type="date" name="min-date-postings" class="btn btn-light" style="margin-left:10px" id="min-date-postings" value="">
        <span>--</span>
        <input type="date" name="max-date-postings" class="btn btn-light" style="margin-left:10px" id="max-date-postings" value="">
        <button id="show-period" class="btn btn-light" style="margin-left:10px">Показать</button>
      </form>
    </div>

  </div>
  <div class="col-md-6 col-sm-12">
    <div style="margin-right:auto; margin-left:auto; width: 400px; padding-top: 40px">
      <span style="font-size: 14px; opacity: 0.5"><i class="last-update-share"></i></span>
      <canvas class="my-4" id="postings_per_hour" width="100" height="100"></canvas>
    </div>
  </div>
</div>
<?
/*
labels: [
  '00:00','01:00','02:00','03:00','04:00','05:00','06:00','07:00','08:00','09:00','10:00','11:00',
  '10:00','11:00','12:00','13:00','14:00','15:00','16:00','17:00','18:00','19:00','20:00','21:00','22:00','23:00',
],
*/
$strSql = "SELECT * FROM ozon_stock_fbo_stat";
$resultDB = $DB->Query($strSql, false, $err_mess.__LINE__);
$statsFboData = [];
$statsStockData = [];
while ( $row = $resultDB->Fetch() ){
  if ( isset( $statsFboData[$row['date']] ) ){
    $statsFboData[$row['date']] += $row['stock'] * $row['price'];
    $statsStockData[$row['date']] += $row['stock'];
  }else{
    $statsFboData[$row['date']] = $row['stock'] * $row['price'];
    $statsStockData[$row['date']] = $row['stock'];
  }
}
$perHour = json_decode( file_get_contents('/var/www/bitrix/data/www/tempusshop.ru/admin/modules/ozon/stat/perHour.json'), 1 );
$timePostings = $perHour['date'];
unset($perHour['date']);
$dow = date('w') == '0' ? 7 : date('w');
// $dayOfWeekMin = $dow - ($dow - 1);
$minDate = date('Y-m-d', strtotime('- ' . $dow - 1 . ' day') );
$maxDate = date('Y-m-d');
$strSql = "SELECT date, type, count(model) as count
FROM `ozon_postings_shares`
WHERE date BETWEEN '{$minDate}' AND '{$maxDate}'
GROUP BY date,type";
$strSql = "SELECT date,type, count(model) AS count FROM ozon_postings_shares GROUP BY date,type";
// var_dump($strSql);
$resultDB = $DB->Query($strSql, false, $err_mess.__LINE__);
$postingsRaw = [];
while ( $row = $resultDB->Fetch() ){
  if ( isset($allPeriodPostings[$row['date']]) ){
    $allPeriodPostings[$row['date']] += $row['count'];
  }else{
    $allPeriodPostings[$row['date']] = $row['count'];
  }
  // $allPeriodPostings += $row['count'];
  $postingsRaw[$row['date']][$row['type']] = $row['count'];
}
foreach ( $postingsRaw as $date => $types){
  foreach ($types as $type => $value) {
    if ( $allPeriodPostings[$date] != 0 ){
      $postings[$date][$type] = $value / $allPeriodPostings[$date] * 100;
    }else{
      $postings[$date]['Нет_данных'] = 100;
    }
  }
}
?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
$(document).on('click', '#show-stat-period', function(e){
  e.preventDefault();
  showStatByPeriod(true);
})

function showStatByPeriod(flag = false){
  if ( flag == true ){
    var data = $('#stat-period-form').serialize();
  }else{
    var dateNow = new Date();
    var data = {max_date_stat: dateNow.toISOString().split('T')[0]}
  }
  $.ajax({
    url: '/admin/modules/ozon/ajax/get_stock_stat_by_period.php',
    method: 'post',
    data: data,
    success: function(response){
      var result = $.parseJSON(response);
      if ( result.error == '' ){
        $('#avg-stock').html('<b>Средний остаток, шт.:&nbsp</b>' + result.stock);
        $('#avg-price').html('<b>Средняя себестоимость, ₽:&nbsp</b>' + result.price);
        $('#stats-graph').html('<canvas class="my-4 w-100" id="stats_cost_fbo" width="900" height="300"></canvas><canvas class="my-4 w-100" id="stats_stock_fbo" width="900" height="300"></canvas>');

        var ctx3 = document.getElementById('stats_cost_fbo');
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
           }
         },
        })

        var ctx4 = document.getElementById('stats_stock_fbo');
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
           }
         },
        })

      }else{
        alert(result.error);
      }
    }
  })
}

$(document).on('click','#show-period',function(e){
  e.preventDefault();
  $.ajax({
    url: '/admin/modules/ozon/ajax/changePeriodPostings.php',
    method: 'post',
    data: $('#settings-period-form').serialize(),
    success: function(response){
      var result = $.parseJSON(response);
      var alabels = Object.keys(result);
      var afbs = Object.values(result).map(item => item.fbs);
      var arfbs = Object.values(result).map(item => item.rfbs);
      var afbo = Object.values(result).map(item => item.fbo);
      console.log(afbs);
      $('#postings_per_day').remove();
      $('.per-day-block').html('<canvas class="my-4" id="postings_per_day" width="600" height="300"></canvas>');
      var ctx6 = document.getElementById('postings_per_day');
      var myChart = new Chart(ctx6, {
        type: 'bar',
        data: {
          labels: alabels,
          datasets: [{
            label: 'Доля FBS, %',
            data: afbs,
            lineTension: 0,
            backgroundColor: [
              "rgba(123,0,255,0.5)",
              // "rgba(0,123,255,0.5)",
              // "rgba(123,255,0,0.5)",
            ],
            pointBackgroundColor: 'blue'
          },
          {
            label: 'Доля rFBS, %',
            data: arfbs,
            lineTension: 0,
            backgroundColor: [
              // "rgba(123,0,255,0.5)",
              // "rgba(0,123,255,0.5)",
              "rgba(255,255,0,0.5)",
            ],
            pointBackgroundColor: 'blue'
          },
          {
            label: 'Доля FBO, %',
            data: afbo,
            backgroundColor: [
              // "rgba(123,0,255,0.5)",
              "rgba(0,123,255,0.5)",
              // "rgba(123,255,0,0.5)",
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
              display: true,
              text: 'Данные за выбранный период'
            }
          }
        },
      })
    }
  })
})
var array = <?php echo json_encode($postings);?>;
console.log(<?php echo json_encode($postingsRaw)?>);
console.log(array);
const labels = Object.keys(array);
const fbs = Object.values(array).map(item => item.fbs);
const rfbs = Object.values(array).map(item => item.rfbs);
const fbo = Object.values(array).map(item => item.fbo);
var ctx6 = document.getElementById('postings_per_day');
var myChart = new Chart(ctx6, {
  type: 'bar',
  data: {
    labels: labels,
    datasets: [{
      label: 'Доля FBS, %',
      data: fbs,
      lineTension: 0,
      backgroundColor: [
        "rgba(123,0,255,0.5)",
        // "rgba(0,123,255,0.5)",
        // "rgba(123,255,0,0.5)",
      ],
      pointBackgroundColor: 'blue'
    },
    {
      label: 'Доля rFBS, %',
      data: rfbs,
      lineTension: 0,
      backgroundColor: [
        // "rgba(123,0,255,0.5)",
        // "rgba(0,123,255,0.5)",
        "rgba(255,255,0,0.5)",
      ],
      pointBackgroundColor: 'blue'
    },
    {
      label: 'Доля FBO, %',
      data: fbo,
      backgroundColor: [
        // "rgba(123,0,255,0.5)",
        "rgba(0,123,255,0.5)",
        // "rgba(123,255,0,0.5)",
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
        display: true,
        text: 'Данные за эту неделю'
      }
    }
  },
})
var ctx5 = document.getElementById('postings_per_hour');
var myChart = new Chart(ctx5, {
  type: 'pie',
  data: {
    labels: <?=json_encode(array_keys($perHour))?>,
    datasets: [{
      label: 'Доля, %',
      data: <?=json_encode(array_values($perHour))?>,
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
      title: {
        display: true,
        text: 'Данные за последний час (<?echo $timePostings;?>)'
      }
    }
  },

})
var ctx = document.getElementById('stats_price');
var myChart = new Chart(ctx, {
  type: 'line',
  data: {
    labels: <?=json_encode($arResult['TIME_PRICES'])?>,
    datasets: [{
      label: 'Цены',
      data: <?=json_encode($arPrice)?>,
      lineTension: 0,
      backgroundColor: 'transparent',
      borderColor: '#007bff',
      borderWidth: 1,
      pointBackgroundColor: '#007bff'
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
    legend: {
      display: false
    }
  }
})

var ctx2 = document.getElementById('stats_stock');
var myChart2 = new Chart(ctx2, {
  type: 'line',
  data: {
    labels: <?=json_encode($arResult['TIME_STOCK'])?>,
    datasets: [
    {
      label: 'Остатки',
      data: <?=json_encode($arStock)?>,
      lineTension: 0,
      backgroundColor: 'transparent',
      borderColor: '#dede2b',
      borderWidth: 1,
      pointBackgroundColor: '#dede2b'
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
    legend: {
      display: false
    }
  }
})

var ctx3 = document.getElementById('stats_cost_fbo');
var myChart = new Chart(ctx3, {
  type: 'bar',
 data: {
  labels: <?echo json_encode( array_keys($statsFboData) );?>,
  datasets: [
    {
      label: 'FBO',
      data: <?echo json_encode( array_values($statsFboData) );?>,
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
   }
 },
})

var ctx4 = document.getElementById('stats_stock_fbo');
var myChart = new Chart(ctx4, {
  type: 'bar',
 data: {
  labels: <?echo json_encode( array_keys($statsStockData) );?>,
  datasets: [
    {
      label: 'FBO',
      data: <?echo json_encode( array_values($statsStockData) );?>,
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
   }
 },
})
$(document).on('click', '#show-log', function(e){
  e.preventDefault();
  $.ajax({
    url: '/admin/modules/ozon/ajax/get_stock_by_date.php',
    method: 'post',
    data: $('#settings-log-form').serialize(),
    success: function(response){
      $('.list-ill').html(response);
      $('.list-ill').slideDown();
    },
    error: function(response){
      $('.list-ill').html(response);
      $('.list-ill').slideDown();
    }
  })
})
$.ajax({
  url: '/admin/modules/ozon/ajax/get_stock_by_date.php',
  method: 'post',
  data: {date: '<?echo date('Y-m-d');?>'},
  success: function(response){
    $('.list-ill').html(response);
    $('.list-ill').slideDown();
  },
  error: function(response){
    $('.list-ill').html(response);
    $('.list-ill').slideDown();
  }
})
showStatByPeriod();
</script>
<style>
.custom-bar {
  margin: 5px!important;
  height: 100%!important;
  width: 100%!important;
}
.resize {
  display: flex!important;
  align-items: center!important;
}
</style>
<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>
