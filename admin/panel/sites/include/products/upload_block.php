<div id="upload-block" class="tab-block" style="display:none;">
  <div class="upl-ctrl-block">
    <select class="upl-el form-select" id="cab-select">
      <option value="WR">WR</option>
      <option value="TL">TL</option>
    </select>
    <button class="upl-el upl-mode-btn btn btn-warning" value="create">Создать карточки</button>
    <button class="upl-el upl-mode-btn btn btn-warning" value="update">Обновить карточки</button>
    <button class="upl-el upl-mode-btn btn btn-warning" value="media">Обновить медиа</button>
    <button class="upl-el upl-mode-btn btn btn-warning" value="nmid">Получить nmid</button>
  </div>
  <div class="upload-result-block">

  </div>
</div>
<style media="screen">
  .upl-ctrl-block{
    display: flex;
    flex-direction: column;
    gap: 8px;
    padding: 20px;
    border-top: 1px solid rgba(0,0,0,0.25);
  }
  .upl-el{
    width: 200px !important;
  }
</style>
<script type="text/javascript">
  $(document).on('click', '.upl-mode-btn', function(e){
    var upload_mode = $(this).val();
    var cab = $('#cab-select').val();
    $.ajax({
      url: '/admin/panel/wb/ajax/products/upload_master.php',
      method: 'POST',
      success: function(e){

      }
    })
  })
</script>
