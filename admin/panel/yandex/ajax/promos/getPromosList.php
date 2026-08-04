<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
require("{$_SERVER['DOCUMENT_ROOT']}/admin/panel/engine/yandex/lib/bootstrap.php");

if ( empty($_POST['cabinet']) ) throw new Exception("POST cannot be empty");

UIProcessor::init();
$config = Config::instance();

$promosList = UIProcessor::data()->getPromosList( $_POST['cabinet'] );

if ( empty($promosList) ) die("<span class='alert alert-danger' style='display:flex; margin-top: 20px; width: fit-content'>Не загружено ни одной акции</span>");

 ?>
 <form id="promos-settings-<?=$_POST['cabinet']?>" action="" method="post">
   <table style="width: 100%">
     <thead style="">
       <th>#</th>
       <th>Название</th>
       <th>Начало</th>
       <th>Конец</th>
       <th>Скидка</th>
       <th>Режим</th>
       <th></th>
     </thead>
     <tbody>
       <? foreach ( $promosList as $promo ):
         $selectedMode = $promo['mode'];
         $status = empty( $promo['active'] ) ? 'danger': 'success';
         $rowId = $promo['id'];
        ?>
        <tr class="alert alert-<?=$status?>" id="<?=$promo['id']?>">
          <td style="padding-left: 5px"><input type="text" class="form form-control" style="width:40px" name="<?=$rowId?>_sort" value="<?=$promo['sort']?>"></td>
          <td><span><?=$promo['name']?></span></td>
          <td><span><?=$promo['date_from']?></span></td>
          <td><span><?=$promo['date_to']?></span></td>
          <td><input class="form form-control" type="text" style="width:70px" name="<?=$rowId?>_discount" value="<?=$promo['discount']?>"></td>
          <td>
            <select class="form form-select" name="<?=$rowId?>_mode">
              <? foreach ( $config->getPromosModes() as $mode ): ?>
                 <option value="<?=$mode?>" <?echo ($mode == $selectedMode) ? 'selected' : '';?>><?=$mode?></option>
              <? endforeach; ?>
            </select>
            </td>
          <td style="padding-right: 5px"><button style="display:flex; margin-left:auto" class="btn btn-danger delete-promo-btn" value="<?=$promo['id']?>">Удалить</button></td>
        </tr>
       <? endforeach; ?>
     </tbody>
   </table>
   <input type="hidden" name="cabinet" value="<?=$_POST['cabinet']?>">
 </form>
