<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

$CurDB = new DBPanel;
$result = $CurDB->query("SELECT * FROM wb_top_models");
$rows = $CurDB->fetchAll($result);
$tops = [];
foreach ($rows as $row) {
  $tops[] = $row['model'];
}
$tops = implode("\n", $tops);
unset($result);
unset($rows);

 ?>

 <div id="topModelsModal_WB" class="modal" tabindex="-1">
   <div class="modal-dialog">
     <div class="modal-content">
       <div class="modal-header">
         <h5 class="modal-title">Топ ВБ</h5>

         <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
       </div>

       <div class="modal-body">
         <textarea id="topModelsText_<?=$cabinet?>" class="form-control" rows="20" placeholder="Введите данные..."><?=$tops?></textarea>
         <input type="hidden" id="topModelsID_<?=$cabinet?>" value="<?=$cabinet?>"/>
       </div>
       <div class="modal-footer">
         <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отменить</button>
       </div>
     </div>
   </div>
 </div>

 <script type="text/javascript">
 $('#topModels_WB').click(function(e) {
   e.preventDefault();
   $('#topModelsModal_WB').modal('show');
 });
 </script>
