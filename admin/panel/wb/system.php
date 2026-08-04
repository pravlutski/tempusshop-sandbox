<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");?>
<?if(!CModule::IncludeModule('panel.manager'))return;?>
<?$APPLICATION->SetTitle('Контроль логов - WB модуль');?>
<?$APPLICATION->SetPageProperty("page_h1", "Контроль логов");?>
<link href="<?=SITE_TEMPLATE_PATH?>/css/products.css" rel="stylesheet">
<script src="<?=SITE_TEMPLATE_PATH?>/js/products.js"></script>
<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js" integrity="sha256-lSjKY0/srUM9BE3dPm+c4fBo1dky2v27Gdjm2uoZaL0=" crossorigin="anonymous"></script>
<link href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css" rel="stylesheet">

<div id="container">
  <div class="btns-block">
    <button class="del-logs btn btn-warning" value="price">Удалить логи цен</button>
    <button class="del-logs btn btn-warning" value="stock">Удалить логи остатков</button>
    <button class="del-logs btn btn-warning" value="orders">Удалить логи заказов</button>
    <button class="del-logs btn btn-warning" value="products">Удалить логи продуктов</button>
  </div>
  <div class="status-block">
    <div class="block-WR">

    </div>
    <div class="block-TL">

    </div>
  </div>
</div>
<div class="response-block-WR" style="width: 70%">

</div>
<div class="response-block-TL" style="width: 70%; padding-top: 30px;">

</div>
<? require('include/mobile.php');?>
<style media="screen">
  #container{
    display: flex;
    flex-direction: row;
    width: 100%;
  }
  .btns-block{
    display: flex;
    flex-direction: column;
    width: 30%;
    gap: 10px;
    padding: 30px;
  }
  .status-block{
    display: flex;
    flex-direction: column;
    width: 70%;
    gap: 10px;
  }
  .del-logs{
    width: 220px;
  }
  .type-row{
    display: flex;
    flex-direction: row;
    width: 100%;
    gap: 5px;
    padding: 6px;
    border-bottom: 1px solid rgba(0,0,0,0.25);
  }
  .type-name{
    font-weight: bolder;
  }
  .type-name, .type-value{
    width: 50%;
  }
  @media (max-width: 867px){
    #container{
      flex-direction: column;
    }
    .btns-block, .status-block{
      width: 100%
    }
  }

</style>

<script type="text/javascript">
  const base_url = "/admin/panel/wb/ajax/cleaning/";
  checkSpace('WR');
  checkSpace('TL');
  setInterval(function(){
    checkSpace('WR');
    checkSpace('TL');
  },10000);

  $(document).on('click', '.del-logs', function(e){
    var mode = $(this).val();
    clearSmth( 'WR', mode );
    clearSmth( 'TL', mode );
  })

  function checkSpace(cabinet){
    $.ajax({
      url: base_url + 'checkSpace.php',
      method: 'POST',
      data: { cabinet:cabinet },
      success: function(response){
        $('.block-' + cabinet).html(response);
      }
    })
  }
  function clearSmth(cabinet, mode){
    $.ajax({
      url: base_url + 'clearLogs.php',
      method: 'POST',
      data: { cabinet: cabinet, mode: mode },
      success: function(response){
        $('.response-block-' + cabinet).html( response );
        checkSpace(cabinet);
      }
    })
  }
</script>
<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>
