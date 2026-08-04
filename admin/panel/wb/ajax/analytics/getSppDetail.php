<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
CModule::includeModule('panel.manager');
$dbPanel = new DBPanel;

if ( empty($_POST) ) die("<h4>НЕТ ВХОДНЫХ ПАРАМЕТРОВ</h4>");

$nmid = $_POST['nmid'];
$date = $_POST['date'];
$mode = 'spp';

$data = $dbPanel->select(['*'], 'wb_spp_analytics_by_hour')->where('nmid', $nmid)->where('date', $date)->make();

if ( empty($data) ) die("<br><h4>Нет данных на выбранную дату</h4>");

$header = [];
for ($i = 0; $i <= 23; $i++){
  $header[$i.':00'] = 0;
}

$chartData_2 = $chartData_1 = [];

$noDataMessage = [];

foreach ( $data as $row ){
  if ( empty($row['our_price']) || empty($row['sell_price']) || empty($row['black_price']) ){
    // $formatted[ $row['hour'] ] = [];
    $noDataMessage[] = "{$row['date']} {$row['hour']}:00 -- нет данных";
    continue;
  }
  $spp = round( ($row['our_price'] - $row['black_price']) / $row['our_price'] * 100 );
  $c_spp = round( ($row['our_price'] - $row['sell_price']) / $row['our_price'] * 100 );

  $chartData_1[ $row['hour'] . ':00' ] = $spp;
  $chartData_2[ $row['hour'] . ':00' ] = $c_spp;
  $chartData_3[ $row['hour'] . ':00' ] = $row['stock_fbo'];

  $chartData_4[ $row['hour'] . ':00' ] = $row['our_price'];
  $chartData_5[ $row['hour'] . ':00' ] = $row['black_price'];
  $chartData_6[ $row['hour'] . ':00' ] = $row['sell_price'];
}

$result = [
  'chart_black' => $chartData_1,
  'chart_green' => $chartData_2,
  'warnings' => $noDataMessage,
];
if ( empty($chartData_1) ){
  die("<br><h4>Нет данных (Модели нет в наличии)</h4>");
}
$chartOptions1 = [
  'canvas' => 'spp_chart_black',
  'type' => 'line',
  'dates' => array_keys($chartData_1),
  'percents' => array_values($chartData_1),
  'background' => 'rgba(188, 131, 247, 0.5)',
  'title' => 'Соинвест (Чёрный), %',
  'label' => '%',
  'min' => min( array_values($chartData_1) ) - 2,
];
$chartOptions2 = [
  'canvas' => 'spp_chart_green',
  'type' => 'line',
  'dates' => array_keys($chartData_2),
  'percents' => array_values($chartData_2),
  'background' => 'rgba(255, 105, 180, 0.5)',
  'title' => 'Соинвест (Кошелек), %',
  'label' => '%',
  'min' => min( array_values($chartData_1) ) - 2,
];
$chartOptions3 = [
  'canvas' => 'stock_chart',
  'type' => 'line',
  'dates' => array_keys($chartData_3),
  'percents' => array_values($chartData_3),
  'background' => 'rgba(255, 159, 1, 0.5)',
  'title' => 'Остатки ФБО, шт.',
  'label' => 'шт.',
  'min' => 0,
];

$chartOptions4 = [
  'canvas' => 'our_price_chart',
  'type' => 'line',
  'dates' => array_keys($chartData_4),
  'percents' => array_values($chartData_4),
  'background' => 'rgba(188, 131, 247, 0.5)',
  'title' => 'Цена (Продавец), ₽',
  'label' => '₽',
  'min' => 1000,
];
$chartOptions5 = [
  'canvas' => 'black_price_chart',
  'type' => 'line',
  'dates' => array_keys($chartData_5),
  'percents' => array_values($chartData_5),
  'background' => 'rgba(255, 105, 180, 0.5)',
  'title' => 'Цена (Чёрная), ₽',
  'label' => '₽',
  'min' => 1000,
];
$chartOptions6 = [
  'canvas' => 'sell_price_chart',
  'type' => 'line',
  'dates' => array_keys($chartData_6),
  'percents' => array_values($chartData_6),
  'background' => 'rgba(255, 159, 1, 0.5)',
  'title' => 'Цена (Кошелёк), ₽',
  'label' => '₽',
  'min' => 1000,
];

// echo json_encode( $result );
 ?>
 <div class="details-main">
   <div class="details-tabs">
     <button value="detail-spp-analytics" class="tab <?echo $mode == 'spp' ? 'active-d': '';?>">Соинвест</button>
     <button value="details-price-analytics" class="tab <?echo $mode != 'spp' ? 'active-d': '';?>">Цены</button>
   </div>
   <div class="detail-spp-analytics details-analytics" style="<?echo $mode == 'spp' ? '': 'display:none';?>">
     <div class="spp-block" style="display:flex; flex-direction:column; width: 50%">
       <!-- <h3>Черный соинвест</h3> -->
       <canvas class="my-4 w-100" id="spp_chart_black" width="800" height="300" style="min-width: 300px;"></canvas>
       <!-- <h3>Не черный соинвест</h3> -->
       <canvas class="my-4 w-100" id="spp_chart_green" width="800" height="300" style="min-width: 300px;"></canvas>

     </div>

     <div class="stock-block" style="margin-left: 10px; width: 50%; padding-left: 10px;">
       <!-- <h3>Сток ФБО</h3> -->
       <canvas class="my-4 w-100" id="stock_chart" width="800" height="300" style="min-width: 300px;"></canvas>
       <!-- <h3>Блок ошибок</h3> -->
       <details style="border:1px solid rgba(0,0,0,0.25); border-radius: 4px; padding: 10px; margin-top: 1px; max-height: 300px; overflow-y:auto">
         <summary>Предупреждения (<?echo count($noDataMessage);?>)</summary>
         <?php foreach ( $noDataMessage as $msg): ?>
           <span style="display:flex; padding: 10px; border-radius: 6px; background-color: rgba(255,0,0,0.5); margin-top:2px;"><?=$msg?></span>
         <?php endforeach; ?>
       </details>
     </div>
   </div>

   <div class="details-price-analytics details-analytics" style="<?echo $mode == 'spp' ? 'display:none': '';?>">
     <div class="price-1-block" style="width: 50%">
       <canvas class="my-4 w-100" id="our_price_chart" width="800" height="300" style="min-width: 300px;"></canvas>
       <canvas class="my-4 w-100" id="black_price_chart" width="800" height="300" style="min-width: 300px;"></canvas>
     </div>
     <div class="price-2-block" style="width: 50%; padding-left: 10px;">
       <canvas class="my-4 w-100" id="sell_price_chart" width="800" height="300" style="min-width: 300px;"></canvas>
       <details style="border:1px solid rgba(0,0,0,0.25); border-radius: 4px; padding: 10px; margin-top: 5px; max-height: 300px; overflow-y:auto">
         <summary>Предупреждения (<?echo count($noDataMessage);?>)</summary>
         <?php foreach ( $noDataMessage as $msg): ?>
           <span style="display:flex; padding: 10px; border-radius: 6px; background-color: rgba(255,0,0,0.5); margin-top:2px;"><?=$msg?></span>
         <?php endforeach; ?>
       </details>
     </div>
   </div>

 </div>

 <script type="text/javascript">
   var chartOptions1 = <?echo json_encode($chartOptions1);?>;
   var chartOptions2 = <?echo json_encode($chartOptions2);?>;
   var chartOptions3 = <?echo json_encode($chartOptions3);?>;

   var chartOptions4 = <?echo json_encode($chartOptions4);?>;
   var chartOptions5 = <?echo json_encode($chartOptions5);?>;
   var chartOptions6 = <?echo json_encode($chartOptions6);?>;

   if ( '<?=$mode?>' == 'spp'){
     drawChart(chartOptions1);
     drawChart(chartOptions2);
     drawChart(chartOptions3);
   }else{
     drawChart(chartOptions4);
     drawChart(chartOptions5);
     drawChart(chartOptions6);
   }


  function drawChart( options ){
    var chart = document.getElementById( options.canvas );
    var costs = new Chart(chart, {
      type: options.type,
     data: {
      labels: options.dates,
      datasets: [
        {
          label: options.label,
          data: options.percents,
          backgroundColor: options.background,
          borderWidth: 2,
          pointBackgroundColor: '#007bff',
          fill: true,
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
           text: options.title
         }
       },
       responsive: true,
       scales: { // Для большей читаемости накидываем или отнимаем пару пунктов
         y: {
           min: options.min,
         },
       },
     },
    })
  }

  $(document).on('click', '.tab', function(e){
    var block = $(this).val();
    $('.tab').removeClass('active-d');
    $(this).addClass('active-d');
    $('.details-analytics').hide();
    $('.'+block).show();
    if ( block == 'details-price-analytics' ){
      drawChart(chartOptions4);
      drawChart(chartOptions5);
      drawChart(chartOptions6);
    }
    if ( block == 'detail-spp-analytics' ){
      drawChart(chartOptions1);
      drawChart(chartOptions2);
      drawChart(chartOptions3);
    }
  })

 </script>

 <style media="screen">
 .details-analytics{
   display: flex;
   flex-direction: row;
   width: 100%;
 }
 .tab{
   /* display: flex; */
   border: none;
   padding: 10px;
   width: 50%;
   background-color: #f2f2f2;
   text-align: center;
 }
 .active-d{
   background-color: #bc83f7 !important;
   color: #f2f2f2;
   font-weight: bolder;
 }
 .details-tabs{
   display: flex;
   flex-direction: row;
   width: 100%;
 }
 </style>
