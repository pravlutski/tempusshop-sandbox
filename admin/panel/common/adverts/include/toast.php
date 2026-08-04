<?php
$moduleName = "Управление рекламными кампаниями";
 ?>
<div class="" style="z-index: 11; position: fixed; right: 0; bottom: 0; padding-right: 15px;">
  <div id="complete-toast" style="display:none" class="toast alert alert-success align-items-center bg-wb-value" data-bs-delay="4000" role="alert" aria-live="assertive" aria-atomic="true">
    <div class="toast-header">
      <!-- <img style="width:20px;height:20px;" src="<?=SITE_TEMPLATE_PATH?>/img/logo.png" class="rounded me-2" alt="..."> -->
      <strong class="me-auto"><?=$moduleName?></strong>
      <!-- <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Закрыть"></button> -->
    </div>
    <div class="toast-body">
      Операция выполнена успешно
    </div>
  </div>
  <div id="error-toast" style="display:none" class="toast alert alert-danger align-items-center bg-wb-value" data-bs-delay="4000" role="alert" aria-live="assertive" aria-atomic="true">
    <div class="toast-header">
      <!-- <img style="width:20px;height:20px;" src="<?=SITE_TEMPLATE_PATH?>/img/logo.png" class="rounded me-2" alt="..."> -->
      <strong class="me-auto"><?=$moduleName?></strong>
      <!-- <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Закрыть"></button> -->
    </div>
    <div class="toast-body">
      Ошибка! Операция не выполнена
    </div>
  </div>
  <div id="pending-toast" style="display:none" class="toast alert alert-warning align-items-center bg-wb-value" data-bs-delay="4000" role="alert" aria-live="assertive" aria-atomic="true">
    <div class="toast-header">
      <!-- <img style="width:20px;height:20px;" src="<?=SITE_TEMPLATE_PATH?>/img/logo.png" class="rounded me-2" alt="..."> -->
      <strong class="me-auto"><?=$moduleName?></strong>
      <!-- <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Закрыть"></button> -->
    </div>
    <div class="toast-body">
      Идёт обновление списка доступных товаров
    </div>
  </div>
</div>
