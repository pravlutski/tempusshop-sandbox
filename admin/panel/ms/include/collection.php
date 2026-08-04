 <div class="collection-settings-container" style="display:none;">
   <div class="btns-block">
     <!-- <button id="add-new" class="btn btn-primary">Добавить</button> -->
     <label class="input-label btn btn-primary" for="input-xlsx" style="margin-left: 10px;">
       <span>Выбрать файл</span>
       <input type="file" id="input-xlsx" style="display:none">
     </label>
     <a id="export-table" class="btn btn-primary" href="https://tempusshop.ru/admin/panel/ozon/ajax/products/exportTable.php">Экспорт</a>
     <button id="delete-all" class="btn btn-danger">Удалить всё</button>
     <button id="save-form-collection" class="btn btn-warning">Сохранить настройки</button>
   </div>

   <div class="form-block">
     <form action="" id="form-options" method="post">

     </form>
     <div class="msg-block">

     </div>
   </div>
 </div>

<style media="screen">
  .form-block{
    display: flex;
    flex-direction: row;
    border-left: 1px solid rgba(0,0,0,0.15);
    border-right: 1px solid rgba(0,0,0,0.15);
    border-bottom: 1px solid rgba(0,0,0,0.15);
    border-radius: 4px;
    padding-bottom: 60px;
  }
  #form-options{
    display: flex;
    flex-direction: column;
    width: 65%;
  }
  .btns-block{
    display: flex;
    padding: 15px;
    border: 1px solid rgba(0,0,0,0.15);
    border-radius: 6px;
  }
  #delete-all{
    margin-left: auto;
  }
  #save-form-collection, #export-table{
    margin-left: 10px;
  }

  .model-row{
    display: flex;
    flex-direction: row;
    padding: 10px;
    border-bottom: 1px solid rgba(0,0,0,0.15);
    gap: 35px;
    width: 100%;
  }
  .row-piece{
    width: 30%
  }
  .model-block{
    background-color: #6c757d;
    border-radius: 4px;
    color: white;
    text-align: center;
    padding: 6px;
  }
  .msg-block{
    display: flex;
    margin-left: auto;
    margin: 10px 10px 10px 0;
    align-self: self-start;
    width: 35%;
  }
  .message-complete{
    padding: 15px;
    border-radius: 8px;
    margin-left: auto;
    background-color: rgba(0,205,0,0.7);
    color: white;
    font-weight: bolder;
  }
  .message-error{
    padding: 15px;
    border-radius: 8px;
    margin-left: auto;
    background-color: rgba(255,0,0,0.7);
    color: white;
    font-weight: bolder;
  }
</style>

 <script type="text/javascript">
  const base_url = "/admin/panel/ozon/ajax/products/";
  var fileInput;
  getOptions();

  $(document).on('change', '#input-xlsx', function(e){
    fileInput = this.files[0];
    if ( fileInput != undefined ){
      createOptions( fileInput );
    }
  })
  $(document).on('click', '.del-btn', function(e){
    var model = $(this).val();
    deleteOption( model, 'single' );
  })
  $(document).on('click', '#delete-all', function(e){
    var model = $(this).val();
    deleteOption( model, 'all' );
  })
  $(document).on('click', '#save-form-collection', function(e){
    updateOptions();
  })

  function deleteOption( model, mode ){
    $.ajax({
      url: base_url + "deleteOption.php",
      method: "POST",
      data: {model: model, mode: mode},
      success: function( response ){
        $('.msg-block').html(response);
        getOptions();
      }
    });

  }

  function createOptions( file ){
    var form_data = new FormData();
    form_data.append( 'file', file )
    $.ajax({
      url: base_url + "createOptions.php",
      method: "POST",
      data: form_data,
      // dataType: 'json',
      cache: false,
      processData: false,
      contentType: false,
      success: function( response ){
        getOptions();
      }
    })
  }

  function updateOptions(){
    $.ajax({
      url: base_url + "updateOptions.php",
      method: "POST",
      data: $('#form-options').serialize(),
      success: function(response){
        $('.msg-block').html(response);
        getOptions()
      }
    })
  }

  function getOptions(){
   $.ajax({
     url: base_url + "getOptions.php",
     method: "POST",
     success: function(response){
       $("#form-options").html(response);
     }
   })
  }
 </script>
