<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");?>
<head>
  <script type="text/javascript" src="https://ajax.googleapis.com/ajax/libs/jquery/2.2.4/jquery.min.js"></script>
  <!-- <script type="text/javascript" src="js/script.js"></script> -->
  <!-- <link rel="stylesheet" href="css/style.css"> -->
</head>

<body>
  <div class="container">
    <div class="oleg">
      <h3 class="util-name">Формирование заказа (для Дарьи)</h2>
      <div class="upload-buttons-block">
        <label id="label-supp" for="supplier-input">Загрзить файл поставщика</label>
        <label id="label-chronos" for="chronos-input">Загрзить НАШ файл</label>
        <input type="file" id="supplier-input" value="" style="display:none;">
        <input type="file" id="chronos-input" value="" style="display:none;">
        <button type="button" class="send-data-btn">Сгенерировать</button>
      </div>
      <div id="output-field" style="display:none;">
        <a id="download-link" href="">Скачать</a>
      </div>
    </div>
  </div>

  <div class="response-block">

  </div>
</body>

<style media="screen">
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
.upload-buttons-block label, .send-data-btn, #download-link{
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
}
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
  $('#label-supp').html(fileSup.name);
  $('#label-supp').css('background-color', '#f0ad4e');
  $('#label-supp').css('color', '#083344');
})
$(document).on('change','#chronos-input',function(){
  fileChronos = this.files[0];
  $('#label-chronos').html(fileChronos.name);
  $('#label-chronos').css('background-color', '#f0ad4e');
  $('#label-chronos').css('color', '#083344');
})


$(document).on('click','.send-data-btn',function(){
  var form_data = new FormData();
  form_data.append('supplier', fileSup);
  form_data.append('chronos', fileChronos);
  $.ajax({
    url: '/admin/utilities/adapter2/ajax/revModifier.php',
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
      $('#output-field').slideDown();
    }
  })
})

</script>
