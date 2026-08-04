 <div id="link-block" class="tab-block" style="display:none">

   <div class="link-body">

     <div class="lb-btns-block">
       <label for="lb-input-selector">Тип импорта</label>
       <select id="lb-input-selector" class="form-select lb-ctrl">
         <option value="lb-list">Список</option>
         <option value="lb-file">Файл (xlsx)</option>
       </select>

       <label style="display:none" for="lb-model-selector">Формат импорта</label>
       <select style="display:none" id="lb-model-selector" class="form-select lb-ctrl">
         <option value="none">--- Не выбрано ---</option>
         <option value="nmid"selected>Nmid</option>
         <!-- <option value="vendor_code">Артикулы</option> -->
       </select>
       <input id="lb-file-input" type="file" style="display:none">

       <button id="lb-upload-btn" class="btn btn-warning lb-ctrl">Начать загрузку</button>
       <button id="lb-clear-btn" class="btn btn-danger lb-ctrl">Очистить</button>
       <div class="lb-result-block">
         <div class="lbrb-head">
            <span>Результат:</span>
         </div>
         <div class="lbrb-body">
           <!-- <div class="spin-wrapper">
             <div class="spinner">
             </div>
           </div> -->
         </div>
       </div>
     </div>

     <div class="lb-input-block">

       <div class="lb-text-block lb-list">
         <textarea id="lb-txt" placeholder="Вставьте nmid каждый с новой строки..."></textarea>
       </div>

       <div class="lb-file-block lb-file" style="display:none">
         <div id="lb-dragndrop">
           <span class="lb-dnd-msg">Брось сюда файл</span>
         </div>
       </div>

     </div>

   </div>


 </div>

 <style media="screen">
   .link-body{
     display: flex;
     flex-direction: row;
   }
   .lb-btns-block, .lb-input-block{
     display: flex;
     flex-direction: column;
     width: 50%;
     padding: 25px;
   }
   .lb-ctrl{
     width: 240px !important;
     margin-bottom: 10px;
   }
   #lb-txt{
     resize: none;
     border-radius: 4px;
     border: 1px solid rgba(0,0,0,0.25);
     padding: 10px;
     width: 100%;
     height: 600px;
   }
   #lb-dragndrop{
     width: 100%;
     height: 600px;
     border-radius: 4px;
     border: 1px solid rgba(0,0,0,0.25);
     padding: 10px;
     cursor: pointer;
   }
   .lb-dnd-msg{
     display: flex;
     padding: 20px;
     border: 1px solid rgba(0,0,0,0.25);
     border-radius: 4px;
     width: 186px;
     /* height: 180px; */
     margin: auto;
     margin-top: auto;
     margin-top: 26%;
     color: rgba(0,0,0,0.35);
     font-weight: bolder;
     text-align: center;
   }
   #lb-dragndrop.drag-over {
     border: 2px dashed #007bff; /* Или другой стиль, чтобы выделить область */
     background-color: #f8f9fa;
   }

   .lb-result-block{
     display: flex;
     flex-direction: column;
     border: 1px solid rgba(0,0,0,0.25);
     border-radius: 4px;
     margin-top: 15px;
     min-height: 345px;
   }
   .lbrb-head{
     font-size: 20px;
     font-weight: bolder;
     border-bottom: 1px solid rgba(0,0,0,0.25);
     padding: 10px;
   }
   .lbrb-body{
     overflow-y: auto;
     padding: 12px;
   }
   .error, .success{
     display: flex;
     padding: 10px;
     border-radius: 4px;
     width: fit-content;
   }
   .error{
     background-color: rgba(255,0,0,0.35);
   }
   .success{
     background-color: rgba(0,255,0,0.35);
   }
   /* Спиннер */
   .spin-wrapper{
     position: relative;
     width: 87%;
     height: 242px;
     /* background: #080705; */
   }
     .spinner{
       position: absolute;
       height: 60px;
       width: 60px;
       border: 3px solid transparent;
       border-top-color: #A04668;
       top: 50%;
       left: 50%;
       margin: -12px;
       border-radius: 50%;
       animation: spin 2s linear infinite;
   }
   @keyframes spin{
     0% {transform: rotate(0deg);}
     100% {transform: rotate(360deg);}
   }
 </style>

 <script type="text/javascript">
    // const ajax_folder_path = "/admin/panel/wb/ajax/products";
    var lb_txt_ph = "Вставьте %TYPE% каждый с новой строки...";
    var files;

    $(document).on('click', '#lb-clear-btn', function(e){
      e.preventDefault();
      console.log(files);
      console.log( $('#lb-txt').val() )
      files = undefined;
      $('.lb-dnd-msg').html('Брось сюда файл');
      $('#lb-txt').val('').change();
      console.log(files);
      console.log( $('#lb-txt').val() );
    })

   $(document).on('change', '#lb-model-selector', function(e){
     e.preventDefault();
     var selectedOption = $(this).find(':selected').text().toLowerCase();
     if ( $(this).val() == 'none' ){
       $('#lb-txt').attr( 'placeholder', 'Укажите формат импорта...' );
       $('#lb-txt').attr( 'readonly', true);
     }else{
       $('#lb-txt').attr( 'placeholder', lb_txt_ph.replace( '%TYPE%', selectedOption ) );
       $('#lb-txt').attr( 'readonly', false);
     }
   })

   $(document).on('change', '#lb-input-selector', function(e){
     e.preventDefault();
     $('.lb-list, .lb-file').hide();
     $('.' + $(this).val()).show();
   })

   $(document).on('click', '#lb-upload-btn', function(e){
     var type = $('#lb-input-selector').val();
     var mode = $('#lb-model-selector').val();

     if ( type == 'lb-file' ){
       if ( files == undefined || mode == 'none'){
         $('.lbrb-body').html('<span class="error">Ошибка импорта: файл не выбран или не указан формат импорта</span><br>');
         return;
       }
       uploadData( type, mode );
     }else{
       if ( mode == 'none' ){
         $('.lbrb-body').html('<span class="error">Ошибка импорта: не указан формат импорта</span><br>');
         return;
       }
       if ( $('#db-txt').val() == '' ){
         $('.lbrb-body').html('<span class="error">Ошибка импорта: не указано ни одного товара</span><br>');
         return;
       }
       uploadData( type, mode );
     }
   })



   function uploadData( type, mode ){
     var form_data = new FormData;
     form_data.append( 'type', type );
     form_data.append( 'mode', mode );
     if ( type == 'lb-file' ){
       form_data.append( 'data', files[0] );
     }else{
       form_data.append( 'data', $('#lb-txt').val() );
     }
     $('.lbrb-body').html('\
       <div class="spin-wrapper">\
         <div class="spinner">\
         </div>\
       </div>\
       ');
     $.ajax({
       url: ajax_folder_path + 'send_cards_group.php',
       method: "POST",
       data: form_data,
       cache: false,
       processData: false,
       contentType: false,
       success: function(response){
         $('.lbrb-body').html('<span class="success">Импорт прошел успешно</span><br>');
         $('.lbrb-body').append('<pre>'+response+'</pre>');
       },
       error: function(response){
         $('.lbrb-body').html('<span class="error">Ошибка импорта</span><br>');
         $('.lbrb-body').append('<pre>'+response+'</pre>');
       }
     })
   }

   $(document).ready(function() {
      const dragDropArea = $('#lb-dragndrop');
      const fileInput = $('#lb-file-input');
      const dragDropMessage = $('.lb-dnd-msg');

      // Предотвращаем действия браузера по умолчанию при перетаскивании
      dragDropArea.on('dragenter dragover dragleave drop', function(e) {
        e.preventDefault();
        e.stopPropagation();
      });

      // Подсветка области при перетаскивании
      dragDropArea.on('dragover', function() {
        dragDropArea.addClass('drag-over');
      });

      dragDropArea.on('dragleave', function() {
        dragDropArea.removeClass('drag-over');
      });

      // Обработка события drop
      dragDropArea.on('drop', function(e) {
        dragDropArea.removeClass('drag-over');

        files = e.originalEvent.dataTransfer.files;

        if (files.length) {
          handleFiles(files);
        }
      });

      // Обработка клика по области drag'n'drop для открытия диалога выбора файла
      dragDropArea.on('click', function() {
        fileInput.click();
      });

      // Обработка изменения input[type="file"] (альтернативный способ выбора файла)
      fileInput.on('change', function() {
        files = fileInput[0].files;
        if (files.length) {
          handleFiles(files);
        }
      });

      // Функция обработки выбранных файлов
      function handleFiles(files) {
        // Здесь ваша логика обработки файла(ов).
        // Например, чтение содержимого, отправка на сервер и т.д.

        // Пример: Отображение имени первого файла в сообщении drag'n'drop
        dragDropMessage.text('Выбран файл: ' + files[0].name);
        //
        // // Пример: Чтение содержимого файла
        // const reader = new FileReader();
        // reader.onload = function(e) {
        //   // e.target.result содержит содержимое файла
        //   console.log('Содержимое файла:', e.target.result);
        // };
        // reader.readAsText(files[0]);
        //
        // // Пример: Отправка файла на сервер
        // // uploadFile(files[0]);  // Функция uploadFile должна быть определена
      }

    });
 </script>
