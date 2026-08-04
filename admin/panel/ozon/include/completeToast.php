<div class="position-fixed bottom-0 end-0 p-3" style="z-index: 11">
  <div id="complete-toast" class="toast hide align-items-center bg-wb-value" data-bs-delay="4000" role="alert" aria-live="assertive" aria-atomic="true" style="margin-left: auto">
    <div class="toast-header">
      <img style="width:20px;height:20px;" src="<?=SITE_TEMPLATE_PATH?>/img/logo.png" class="rounded me-2" alt="...">
      <strong class="me-auto">Tempus Yandex Module</strong>
      <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Закрыть"></button>
    </div>
    <div class="toast-body">
      Операция выполнена успешно
    </div>
  </div>
  <div id="error-toast" class="toast hide align-items-center bg-wb-value" data-bs-delay="4000" role="alert" aria-live="assertive" aria-atomic="true" style="margin-left: auto">
    <div class="toast-header">
      <img style="width:20px;height:20px;" src="<?=SITE_TEMPLATE_PATH?>/img/logo.png" class="rounded me-2" alt="...">
      <strong class="me-auto">Tempus Yandex Module</strong>
      <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Закрыть"></button>
    </div>
    <div class="toast-body">
      Ошибка! Операция не выполнена
    </div>
  </div>
</div>
<audio id="successSound">
  <source src="<?=SITE_TEMPLATE_PATH?>/source/success.mp3" type="audio/mpeg">
</audio>
