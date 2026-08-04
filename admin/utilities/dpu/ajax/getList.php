<?php
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
require( $_SERVER['DOCUMENT_ROOT'].'/admin/utilities/dpu/classes/CRUDManager.php' );

if ( empty($_POST['marketplace']) || empty($_POST['cabinet']) ){
  throw new Exception("Empty marketplace or cabinet");
}

$settings = new CRUDManager(
  mp: $_POST['marketplace'],
  cab: $_POST['cabinet']
);
$showOutOfStock = ($_POST['showOutOfStock'] == 'true') ? true : false;
$ys_flag = ($_POST['yesterday'] == 'true') ? true : false;

try{
  $items = $settings->getItems( showYesterdayCharts: boolval($ys_flag) );
} catch( EmptyItemsListException $e ){
  echo '<h4 style="text-align:center; margin-top: 5%">Список товаров пуст ¯\_(ツ)_/¯. Чтобы добавить новые товары, нажмите "Добавить модели"<h4>';
}
$stockFbo = $settings->getFboStats();

$defaults = $settings->getDefaultSettings();

function getGoalCounterClassName( int $orders, int $goal ):string
{
  return ( $orders >= $goal ) ? 'goal-complete' : 'goal-incomplete';
}
function getStepClassName( int $status ):string
{
  if ( $status > 0 ) return 'goal-complete';
  if ( $status < 0 ) return 'goal-incomplete';

  return '';
}

foreach ( $items as $key => $value ):
  if ( !$showOutOfStock && !$value['isAvailable'] ) continue;

  $displayPeriodData = "{$value['ordersCount']} / {$value['goalPeriod']}";
  $displayFullData = "{$value['ordersCountAll']}";
  if ( strtotime($value['intervals']['nextRunDate']) < time() ){
    $value['intervals']['nextRunDate'] = date('Y-m-d H:00:00', strtotime('+1 hour'));
  }
  $intervalTooltip = [
    "Модель: {$value['model']}",
    "Предыдущий: {$value['intervals']['lastRunDate']}",
    "Следующий: {$value['intervals']['nextRunDate']}"
  ];
  $goalTooltip = [];
  foreach ( $value['history']['goal'] as $key => $el ){
    $goalTooltip[] = "{$el['date']} - {$el['orders']}/{$el['goalInterval']} ({$el['goal']})";
  }
  $intervalTooltip = json_encode( $intervalTooltip );
  $goalTooltip = json_encode( $goalTooltip );
 ?>
 <tr class="dp-card <?echo $value['isAvailable'] ? '' : 'unavailable';?> <? echo ( !$value['cap']['margin'] || !$value['cap']['profit']) ? '' : '';?>">
   <td class="model" title='<?=$intervalTooltip?>'>
     <span><?=$value['model']?></span>
   </td>
   <td class="goal">
     <input name="<?=$value['id']?>|goal" value="<?=$value['goal']?>" title='<?=$goalTooltip?>'>
   </td>
   <td class="min_profit_perc switch-settings" style="display:none">
     <input name="<?=$value['id']?>|min_profit_perc" value="<?=$value['min_profit_perc']?>" placeholder="<?=$defaults['min_profit_perc']?>">
   </td>
   <td class="min_profit_rub switch-settings" style="display:none">
     <input name="<?=$value['id']?>|min_profit_rub" value="<?=$value['min_profit_rub']?>" placeholder="<?=$defaults['min_profit_rub']?>">
   </td>
   <td class="step switch-settings" style="display:none">
     <input name="<?=$value['id']?>|step" value="<?=$value['step']?>" placeholder="<?=$defaults['step']?>">
   </td>

   <td class="ordersByHour switch-visual" style=" max-width: 200px !important">
     <div style="width: 110px !important; height: 40px !important; display:flex; margin-left:auto; margin-right:auto">
       <canvas id="ordersByHour_<?=$value['id']?>" width="100" height="25"></canvas>
     </div>
   </td>

   <td class="statusHistory switch-visual" style=" max-width: 200px !important">
     <div style="width: 110px !important; height: 40px !important;display:flex; margin-left:auto; margin-right:auto">
       <canvas id="statusHistory_<?=$value['id']?>" width="100" height="25"></canvas>
     </div>
   </td>

   <td class="startPrice">
     <span><?echo $value['startPrice'] ?? '-';?></span>
   </td>
   <td class="status <?echo getStepClassName($value['installed']['step'] ?? 0);?>">
     <span><?echo $value['installed']['step'] ?? 0;?>%</span>
   </td>
   <td class="finalPrice">
     <span><?echo $value['installed']['price'] ?? '-';?></span>
   </td>
   <td class="cost">
     <span><?echo $value['cost'] ?? '-';?></span>
   </td>
   <td class="stockFbo">
     <span><? echo $stockFbo[$value['model']] ?? 0; ?></span>
   </td>
   <td class="profitRub <? echo !$value['cap']['profit'] ? 'suspicious' : ''; ?>">
     <span><?echo $value['installed']['profit'] ?? '-';?></span>
   </td>
   <td class="profitPerc <? echo !$value['cap']['margin'] ? 'suspicious' : ''; ?>">
     <span><?echo ($value['installed']['margin']) . '%';?></span>
   </td>
   <td class="last_hour <? echo getGoalCounterClassName($value['ordersCount'], $value['goalPeriod']);?>" >
     <span><?=$displayPeriodData?></span>
   </td>
   <td class="last_24" style="">
     <span><?=$displayFullData?></span>
   </td>
   <td>
     <!-- <button class="del-btn btn btn-danger" data-id="<?=$value['id']?>">Удалить</button> -->
     <div class="btn-group" style="margin: 0 0 10px 0;">
       <button type="button" class="btn btn-warning dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">Действие</button>
       <div class="dropdown-menu">
         <a class="dropdown-item update-btn" style="<? echo $value['isAvailable'] ? '' : 'display:none';?>" href="#" data-model="<?=$value['model']?>">Обновить</a>
         <a class="dropdown-item del-btn" href="#" data-id="<?=$value['id']?>">Удалить</a>
       </div>
     </div>
   </td>
 </tr>
<?
  $goalFP = false;
 endforeach;
 ?>
 <input type="hidden" name="marketplace" value="<?=$_POST['marketplace']?>">
 <input type="hidden" name="cabinet" value="<?=$_POST['cabinet']?>">

 <script src="/admin/panel/ozon/lib/chart.js" type="text/javascript"></script>
 <script type="text/javascript">
   <?php foreach ( $items as $key => $value):?>
   <?php if ( !$showOutOfStock && !$value['isAvailable'] ) continue; ?>
   var ctx1_<?=$value['id']?> = document.getElementById('ordersByHour_<?=$value['id']?>' );

   var chart1_<?=$value['id']?> = new Chart(ctx1_<?=$value['id']?>, {
    type: 'bar',
    data: {
     labels: Object.keys(<? echo json_encode($value['history']['orders']);?>),
     datasets: [
       {
         label: false,
         data: Object.values(<? echo json_encode($value['history']['orders']);?>),
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

   <?php if ( empty($value['history']['orders']) ) continue; ?>
   var ctx2_<?=$value['id']?> = document.getElementById('statusHistory_<?=$value['id']?>' );
   var dataValues = Object.values(<? echo json_encode($value['history']['status'] ?? []);?>);
   var barColors = dataValues.map(function(value){
     return value >= 0 ? 'rgba(152,255,152,0.9)' : 'rgba(241, 64, 101, 0.5)';
   });
   var chart2_<?=$value['id']?> = new Chart(ctx2_<?=$value['id']?>, {
    type: 'bar',
    data: {
     labels: Object.keys(<? echo json_encode($value['history']['status'] ?? []);?>),
     datasets: [
       {
         label: false,
         data: dataValues,
         // backgroundColor: 'rgba(223,0,155,0.5)',
         backgroundColor: barColors,
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
           display: true,
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
