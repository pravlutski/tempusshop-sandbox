<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");?>
<?$APPLICATION->SetTitle('Формирование заказов');?>
<?AccessValidator::checkIfAllowed(); // Менеджер прав?>
<head>
  <script type="text/javascript" src="https://ajax.googleapis.com/ajax/libs/jquery/2.2.4/jquery.min.js"></script>
  <!-- <script type="text/javascript" src="js/script.js"></script> -->
  <!-- <link rel="stylesheet" href="css/style.css"> -->
</head>

<body>
  <h2 class="util-name">Формирование заказа</h2>
  <div id="ord-container">
    <div class="control-block btn-block">
      <select id="supp" class="form-control" style="width:240px;">
        <option value="124">Олег</option>
        <option value="41">Дарья</option>
        <option value="135">Никита</option>
      </select>
      <button type="button" class="btn btn-light send-data-btn o-btn" disabled>Сгенерировать</button>
    </div>
    <br>
    <div class="upload-buttons-block">
      <div class="supp-block btn-block">
        <label id="label-supp" class="btn btn-primary o-btn" for="supplier-input">Загрузить файл поставщика</label>
        <span id="supp-filename" class="filename"></span>
      </div>
      <div class="chronos-block btn-block">
        <label id="label-chronos" class="btn btn-primary o-btn" for="chronos-input">Загрузить наш файл</label>
        <span id="chronos-filename" class="filename"></span>
      </div>
      <input type="file" id="supplier-input" value="" style="display:none;">
      <input type="file" id="chronos-input" value="" style="display:none;">

    </div>
    <div id="output-field" style="display:none;">
      <a id="download-link" href="">Скачать</a>
    </div>

    <div class="response-block">

    </div>

  </div>
</body>

<style media="screen">
#ord-container{
  display: flex;
  flex-direction: column;
  gap: 10px;
  border-top: 1px solid rgba(0,0,0,0.15);
  padding: 10px;
}
.o-btn{
  width: 240px;
  margin-bottom: 10px;
}
.btn-block{
  display: flex;
  flex-direction: row;
  gap: 30px;
}
.filename{
  padding-top: 8px;
}
.util-name{
  margin-bottom: 35px;
}
.upload-buttons-block{
  display: flex;
  flex-direction: column;
  height: 120px;
}
#output-field{
  margin-top: 60px;
  font-size: 16px;
}
.response-block{
  font-size: 16px;
  font-weight: bolder;
  color: red;
}
/* .upload-buttons-block label, .send-data-btn, #download-link{
  background-color: #083344;
  color: white;
  height: 40px;
  margin-left: 20px;
  margin-top: 6px;
  border-radius: 6px;
  padding: 10px;
  width: 250px;
  text-align: center;
  font-weight: bolder;
  cursor: pointer;
}
.upload-buttons-block label:hover{
  background-color: #f0ad4e;
  color: #083344;
} */
.send-data-btn:hover{
  background-color: #f0ad4e;
  color: #083344;
}
#download-link:hover{
  background-color: #f0ad4e;
  color: #083344;
}

</style>

<script type="text/javascript">

var fileSup;
var fileChronos;
$(document).on('change','#supplier-input',function(){
  fileSup = this.files[0];
  $('#supp-filename').html(fileSup.name);
  if ( fileChronos != undefined && fileSup != undefined){
    $('.send-data-btn').attr('disabled', false);
  }
})
$(document).on('change','#chronos-input',function(){
  fileChronos = this.files[0];
  $('#chronos-filename').html(fileChronos.name);
  if ( fileChronos != undefined && fileSup != undefined){
    $('.send-data-btn').attr('disabled', false);
  }
})

$(document).on('change', '#supp', function(e){
  e.preventDefault();
  $('.filename').html('');
  fileSup = undefined;
  fileChronos = undefined;
  $('.send-data-btn').attr('disabled', true);
})

$(document).on('click','.send-data-btn',function(){

  var form_data = new FormData();
  form_data.append('supplier', fileSup);
  form_data.append('chronos', fileChronos);
  var supp = $('#supp').val();

  $.ajax({
    url: '/admin/utilities/adapter/ajax/'+supp+'/revModifier.php',
    method: 'POST',
    data: form_data,
    dataType: 'json',
    cache: false,
    processData: false,
    contentType: false,
    success: function(response){
      $('.response-block').html(response);
      $('#download-link').prop('download');
      $('#download-link').attr('href', response.savelink);
      document.getElementById('download-link').click();
    }
  })
})

</script>

<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>
