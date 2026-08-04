<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

$panel = new DBPanel;

function countActiveItems( DBPanel $panel ):array
{
  $rows = $panel->select(['DISTINCT date'], 'ozon_sales_detail_log_IP')->make();

  $dates = [];
  foreach ( $rows as $row ){
    $dates[] = $row['date'];
  }
  $lastTime = strtotime( end($dates) );
  $lastDate = date('Y-m-d G:i', $lastTime);

  $rows = $panel->select(['*'], 'ozon_sales_detail_log_IP')->where('date', "%{$lastDate}%", 'LIKE')->where('status', 'Y')->make();

  $sales = [];

  foreach( $rows as $row ){
    $sales[ $row['saleId'] ][] = $row;
  }
  $result = [];

  foreach ( $sales as $id => $rows ){
    $result[ $id ] = count($rows);
  }

  return $result;
}

function pickRowColor( array $row ):string
{
  if ( $row['active'] == 0 ) return 'danger';

  $dateEnd = str_replace( '.', '-', $row['date_end'] );
  $tsEnd = strtotime($dateEnd);

  if ( $tsEnd < time() ) return 'danger';

  $timeDiff = abs( $tsEnd - time() );
  $t = $timeDiff / 3600;

  if ( $t <= 72 ) return 'warning';

  return 'success';
}

$rows = $panel->select(['*'], 'ozon_sales_IP')->make();

// $activeItems = countActiveItems( $panel );

foreach ( $rows as $row ):
  $rowColor = pickRowColor( $row );
 ?>

 <tr class="alert alert-<?=$rowColor?> sale-item" id="<?=$row['sale_id']?>">
   <td style="padding-left: 5px"><input type="text" class="form form-control" style="width:40px" name="data[<?=$row['sale_id']?>][sort]" value="<?=$row['sort']?>"></td>
   <td><span><?=$row['name']?></span></td>
   <td><span><?=$row['date_start']?></span></td>
   <td><span><?=$row['date_end']?></span></td>
   <td><input class="form form-control" type="text" style="width:70px" name="data[<?=$row['sale_id']?>][boost]" value="<?=$row['boost']?>"></td>
   <td><input class="form form-control" type="text" style="width:70px" name="data[<?=$row['sale_id']?>][perc]" value="<?=$row['perc']?>"></td>
   <td>
     <select class="form form-select" name="data[<?=$row['sale_id']?>][perc_entry]">
       <option value="N" <? echo ($row['perc_entry'] == 'N' ? 'selected' : '');?>>MAP</option>
       <option value="Y" <? echo ($row['perc_entry'] == 'Y' ? 'selected' : '');?>>FIX</option>
     </select>
   </td>
   <td style="text-align:center"><span class="<?=$row['sale_id']?>-counter items-counter" data-loader="Считаю..."><? echo $activeItems[$row['sale_id']] ?? '-';?></span></td>
   <td style="padding-right: 5px"><button style="display:flex; margin-left:auto" class="btn btn-danger delete-btn" data-id="<?=$row['sale_id']?>">Удалить</button></td>
 </tr>

 <? endforeach; ?>
