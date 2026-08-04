<?php
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
require( $_SERVER['DOCUMENT_ROOT'].'/admin/panel/ozon/price_settings/classes/class.php' );

$settings = new SettingsManager('IP');
// var_dump("NOT");
// die;
$list = $settings->getListSettings();
$defaults = $settings->getDefaultSettings()[0];
$final = $settings->getCurrentStatusData();


$items = $list['items'];
$orders = $list['orders'];
$goalsLong = $list['goals_long'];
$goalsShort = $list['goals_short'];
// echo '<pre>';
// var_dump( $orders );
// var_dump( $goals );
// echo '</pre>';
// die;

foreach ( $items as $value ):
  $goalL = $goalsLong[ $value['model'] ];
  if ( intval($goalL) <= $orders[ $value['model'] ]['24_hour'] ){
    $classL = 'goal-complete';
  }else{
    $classL = 'goal-incomplete';
  }

  $goalS = $goalsShort[ $value['model'] ];
  if ( intval($goalS) <= $orders[ $value['model'] ]['1_hour'] ){
    $classS = 'goal-complete';
  }else{
    $classS = 'goal-incomplete';
  }
 ?>
 <tr class="dp-card">
   <td class="model">
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

   <td class="startPrice">
     <span><?echo $final[$value['model']]['startPrice'] ?? 0;?></span>
   </td>
   <td class="status">
     <span><?echo $final[$value['model']]['status'] ?? 0;?></span>
   </td>
   <td class="finalPrice">
     <span><?echo $final[$value['model']]['finalPrice'] ?? 'Не установлена';?></span>
   </td>
   <td class="cost">
     <span><?echo $final[$value['model']]['cost'] ?? 0;?></span>
   </td>
   <td class="profitRub">
     <span><?echo $final[$value['model']]['profit_rub'] ?? 0;?></span>
   </td>
   <td class="profitPerc">
     <span><?echo $final[$value['model']]['profit_perc'] ?? 0;?></span>
   </td>
   <td class="last_hour <?=$classS?>" title="<?=$goalS?>">
     <span><?echo $orders[$value['model']]['1_hour'] ?? 0;?> / <?=$goalS?></span>
   </td>
   <td class="last_24 <?=$classL?>" title="<?=$goalL?>">
     <span><?echo $orders[$value['model']]['24_hour'] ?? 0;?> / <?=$goalL?></span>
   </td>
   <td>
     <button class="del-btn btn btn-danger" data-id="<?=$value['id']?>">Удалить</button>
   </td>
 </tr>
<? endforeach; ?>
