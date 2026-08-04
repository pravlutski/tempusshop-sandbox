<?php
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
require( $_SERVER['DOCUMENT_ROOT'].'/admin/panel/ozon/price_settings/classes/class.php' );

$settings = new SettingsManager('IP');
// var_dump("NOT");
// die;
$ys_flag = ($_POST['yesterday'] == 'true') ? true : false;

$list = $settings->getListSettings( showYesterdayCharts: boolval($ys_flag) );
$defaults = $settings->getDefaultSettings()[0];
$nextRun = $settings->getCalculatedNextRunDate();

$items = array_values($list['items']);
$orders = $list['orders'];
$goalsLong = $list['goals_long'];
$goalsShort = $list['goals_short'];
$ordersByPeriod = $list['ordersByPeriod'];
$goalsForPeriod = $list['goalsForPeriod'];
$ordersByHour = $list['ordersByHour'];
$statusHistory = $list['statusHistory'];
$unAvailableModels = $list['unAvailableModels'];
$final = $list['currentStatusData'];

foreach ( $items as $key => $value ):
  $classProfit = '';
  $goalL = $goalsLong[ $value['model'] ];
  if ( intval($goalL) <= $orders[ $value['model'] ]['24_hour'] ){
    $classL = 'goal-complete';
  }else{
    $classL = 'goal-incomplete';
  }

  if ( substr($final[$value['model']]['status'], 0, 1) == '+' || substr($final[$value['model']]['status'], 1, 2) == '0' ){
    $classStatus = 'goal-complete';
  }elseif( substr($final[$value['model']]['status'], 0, 1) == '-' ){
    $classStatus = 'goal-incomplete';
  }

  $orderBP = $ordersByPeriod[ $value['model'] ];
  $goalFP = $goalsForPeriod[ $value['model'] ];

  if ( $goalFP === false ){
    $displayPeriodData = 'Нет данных';
  }else{
    $displayPeriodData = "{$orderBP} / {$goalFP}";
  }

  if ( $goalFP !== false && ($orderBP >= $goalFP) ){
    $classS = 'goal-complete';
  }elseif ( $goalFP !== false && ($orderBP < $goalFP) ){
    $classS = 'goal-incomplete';
  }else{
    $classS = '';
  }
  $uaFlag = '';
  if ( $final[$value['model']]['profit_cap_rub'] == 'Y' || $final[$value['model']]['profit_cap_perc'] == 'Y' ){
    $uaFlag = 'profit-cap-reached';
  }

  if ( $unAvailableModels[$value['model']] ){
    $uaFlag = 'unavailable';
  }

 ?>
 <tr class="dp-card <?echo$uaFlag ?? '';?>">
   <td class="model" title="Модель: <?=$value['model']?>|Предыдущий: <?=$final[$value['model']]['date']?>|Следующий: <?=$nextRun[$value['model']]?>">
     <span><?=$value['model']?></span>
   </td>
   <td class="goal">
     <input name="<?=$value['id']?>|goal" value="<?=$value['goal']?>">
   </td>
   <td class="min_profit_perc">
     <input name="<?=$value['id']?>|min_profit_perc" value="<?=$value['min_profit_perc']?>" placeholder="<?=$defaults['min_profit_perc']?>">
   </td>
   <td class="min_profit_rub">
     <input name="<?=$value['id']?>|min_profit_rub" value="<?=$value['min_profit_rub']?>" placeholder="<?=$defaults['min_profit_rub']?>">
   </td>
   <td class="step">
     <input name="<?=$value['id']?>|step" value="<?=$value['step']?>" placeholder="<?=$defaults['step']?>">
   </td>

   <td class="ordersByHour" style="display:none; max-width: 200px !important">
     <div style="width: 110px !important; height: 40px !important; display:flex; margin-left:auto; margin-right:auto">
       <canvas id="ordersByHour_<?=$key?>" width="100" height="25"></canvas>
     </div>
   </td>

   <td class="statusHistory" style="display:none; max-width: 200px !important">
     <div style="width: 110px !important; height: 40px !important;display:flex; margin-left:auto; margin-right:auto">
       <canvas id="statusHistory_<?=$key?>" width="100" height="25"></canvas>
     </div>
   </td>

   <td class="startPrice">
     <span><?echo $final[$value['model']]['startPrice'] ?? '-';?></span>
   </td>
   <td class="status <?=$classStatus?>">
     <span><?echo $final[$value['model']]['status'] ?? 0;?>%</span>
   </td>
   <td class="finalPrice">
     <span><?echo $final[$value['model']]['finalPrice'] ?? '-';?></span>
   </td>
   <td class="cost">
     <span><?echo $final[$value['model']]['cost'] ?? '-';?></span>
   </td>
   <td class="profitRub <?=$classProfit?> <? echo ($final[$value['model']]['profit_cap_rub'] == 'Y') ? 'suspicious' : '';?>">
     <span><?echo $final[$value['model']]['profit_rub'] ?? '-';?></span>
   </td>
   <td class="profitPerc <?=$classProfit?> <? echo ($final[$value['model']]['profit_cap_perc'] == 'Y') ? 'suspicious' : '';?>">
     <span><?echo empty($final[$value['model']]['profit_perc']) ? '-' : $final[$value['model']]['profit_perc'] . '%';?></span>
   </td>
   <td class="last_hour <?=$classS?>" >
     <span><?=$displayPeriodData?></span>
   </td>
   <td class="last_24 <?=$classL?>">
     <span><?echo $orders[$value['model']]['24_hour'] ?? 0;?> / <?=$goalL?></span>
   </td>
   <td>
     <button class="del-btn btn btn-danger" data-id="<?=$value['id']?>">Удалить</button>
   </td>
 </tr>
<?
  $goalFP = false;
 endforeach;
 ?>
 <script src="/admin/panel/ozon/lib/chart.js" type="text/javascript"></script>
 <script type="text/javascript">
   <?php foreach ( $items as $key => $value):?>

   var ctx1_<?=$key?> = document.getElementById('ordersByHour_<?=$key?>' );

   var chart1_<?=$key?> = new Chart(ctx1_<?=$key?>, {
    type: 'bar',
    data: {
     labels: Object.keys(<? echo json_encode($ordersByHour[$value['model']]);?>),
     datasets: [
       {
         label: false,
         data: Object.values(<? echo json_encode($ordersByHour[$value['model']]);?>),
         backgroundColor: 'rgba(123,0,255,0.5)',
         pointBackgroundColor: '#007bff',
         pointRadius: 0,
         fill: true
       },
     ]
   },
   options:{
     plugins: {
       legend: { display: false },
     },
     responsive: true,
     maintainAspectRatio: false,
     animation: {
       duration: 0 // мгновенное появление
     },
     scales: {
       x: { display: true, grid: {display: false}, ticks: {display:false} }, // полностью скрыть ось X
       y: { display: false, grid: {display: false}, ticks: {display: false}, min: 0, max: 5 }  // полностью скрыть ось Y
     },

    },
   })

   <?php if ( empty($statusHistory[$value['model']]) ) continue; ?>
   var ctx2_<?=$key?> = document.getElementById('statusHistory_<?=$key?>' );
   var chart2_<?=$key?> = new Chart(ctx2_<?=$key?>, {
    type: 'bar',
    data: {
     labels: Object.keys(<? echo json_encode($statusHistory[$value['model']]);?>),
     datasets: [
       {
         label: false,
         data: Object.values(<? echo json_encode($statusHistory[$value['model']]);?>),
         backgroundColor: 'rgba(223,0,155,0.5)',
         pointBackgroundColor: '#007bff',
         categoryPercentage: 1.0,
         pointRadius: 0,
         fill: true
       },
     ]
   },
   options:{
     plugins: {
       legend: { display: false },
     },
     responsive: true,
     maintainAspectRatio: false,
     animation: {
       duration: 0 // мгновенное появление
     },
     scales: {
       x: {
         display:true,
         grid: {
           display: false,
           drawBorder: false,
         },
         border: {
           display: false,
         },
         ticks: {
           display:true,
           font: {size: '8px'},
         },
         barPercentage: 1.0,
        categoryPercentage: 1.0,
       }, // полностью скрыть ось X
       y: {
         display: true,
         grid: {
           display: true,
           grid: {
            color: function(context) {
              return context.tick.value === 0 ? '#ccc' : 'transparent';
            },
            lineWidth: function(context) {
              return context.tick.value === 0 ? 1 : 0;
            }
          }
         },
         ticks: {display: false},
         border: { display: false },
         beginAtZero: true,
         min: -35,
         max: 50
       }  // полностью скрыть ось Y
     },
    },
   })
   <?php // break; ?>
   <?php endforeach; ?>
 </script>
