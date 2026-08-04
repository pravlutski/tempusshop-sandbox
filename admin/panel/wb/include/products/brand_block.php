 <div id="brand-block" class="tab-block" style="display:none">
   <div class="d-btns-block" style="display:flex; flex-direction: row; padding: 15px 15px 0 15px">

     <select id="brand_bx" class="form-select prop-selector" name="brand_bx" style="width: 240px;">
       <option value="">Не выбрано</option>
       <? foreach ( $brandsBX as $id => $bbx ):?>
          <option value="<?=$id?>"><?=$bbx?></option>
       <? endforeach;?>
     </select>
     <input type="text" class="form-control" style="margin-left: 20px; width: 240px" id="brand_name" value="" placeholder="Название бренда на WB">

     <!-- <button id="create-brand-dep" class="btn btn-warning" style="margin-left: 20px;">Создать связку</button> -->
     <button id="save-brand-dep" class="btn btn-warning" style="margin-left: auto">Создать зависимость</button>
   </div>
   <hr>
   <div class="brand-dep-list">



   </div>
 </div>

 <style media="screen">

   .alias-card{
     display: flex;
     flex-direction: row;
     padding: 10px;
     width: 70%;
     border-bottom: 1px solid rgba(0,0,0,0.15);
   }
   .card-col{
     width: 33%;
     padding: 5px;
     display: flex;
     flex-direction: row;
   }
   .card-name{
     align-items: center;
   }

   .card-btns{
     gap: 10px;
     justify-content: flex-end;
   }
 </style>

 <script type="text/javascript">
    function getAliasesList()
    {
      $.ajax({
        url: ajax_folder_path + "brands/get_aliases_list.php",
        method: "POST",
        success: function( response ){
          $('.brand-dep-list').html(response);
        },
        error: function( response ){
          alert('Системная ошибка');
        }
      })
    }

    function createAlias()
    {
      $.ajax({
        url: ajax_folder_path + '/brands/create_alias.php',
        method: "POST",
        data: { brand_id: $('#brand_bx').val(), brand_name: $('#brand_name').val() },
        success: function( response ) {
          getAliasesList();
        },
        error: function( response ){
          alert('Ошибка записи. Возможно этот бренд уже указан');
        }
      })
    }

    function deleteAlias( id )
    {
      $.ajax({
        url: ajax_folder_path + '/brands/delete_alias.php',
        method: "POST",
        data: { id: id },
        success: function( response ) {
          getAliasesList();
        }
      })
    }


    $(document).on('click', '#brand-set', function(e){
      e.preventDefault();
      getAliasesList();
    })

    $(document).on('click', '#save-brand-dep', function(e){
      e.preventDefault();
      createAlias()
    })

    $(document).on('click', '.delete-alias', function(e){
      e.preventDefault();
      var row_id = $(this).val();
      deleteAlias( row_id );
    })
 </script>
