<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");?>
<?AccessValidator::checkIfAllowed(); // Менеджер прав?>
<?php
$limitCache = "/var/www/bitrix/data/www/tempusshop.ru/admin/utilities/infograph/cache/limit.json";
if ( file_exists($limitCache) ){
  $limC = file_get_contents($limitCache);
  $limC = json_decode($limC, true);
}
$limitOpt = [50,100,200,500,1000];

$brands = array();
$arFilter = Array(
	"IBLOCK_ID" => CProSet::IB_BRANDS,
);
$result = CIBlockElement::GetList(Array("NAME" => "ASC"), $arFilter, false, false, array("ID", "NAME"));
while($arFields = $result->GetNext()){
	$brands[] = ['id' => $arFields["ID"], 'name' => $arFields["NAME"]];
}
 ?>
<link href="<?=SITE_TEMPLATE_PATH?>/css/products.css" rel="stylesheet">
<title>Проверка инфографики</title>
<div id="container">
  <h1>Проверка инфографики</h1>
  <div id="util-header">
    <div class="settings" >

      <div style="width: 420px">
        <div class="opt-row">
          <label for="mp-selector" style="margin-right:auto;display:flex; width:fit-content">Маркетплейс</label>
          <select id="mp-selector" class="selector">
            <option value="wb">WB</option>
            <option value="ozon">OZON</option>
          </select>
        </div>
      <div class="opt-row">
        <label for="limit-selector" style="margin-right:auto;display:flex; width:fit-content">Эл-тов на странице</label>
        <select id="limit-selector" class="selector">
          <?
          foreach( $limitOpt as $opt ){
            if ( $opt == $limC['limit'] ){
              echo '<option value="'.$opt.'" selected>'.$opt.'</option>';
            }else{
              echo '<option value="'.$opt.'">'.$opt.'</option>';
            }
          }
          ?>
        </select>
      </div>
      <div class="opt-row">
        <label for="sort-selector" style="margin-right:auto;display:flex; width:fit-content">Сортировка</label>
        <select id="sort-selector" class="selector">
          <option value="popular">По популярности</option>
          <option value="date-add">По дате добавления</option>
        </select>
      </div>
      <div class="opt-row">
        <label for="isOzon-selector" style="margin-right:auto;display:flex; width:fit-content">Активность ОЗОН</label>
        <select id="isOzon-selector" class="selector">
          <option value="Y" selected>Да</option>
          <option value="N">Нет</option>
          <option value="insel">Неважно</option>
        </select>
      </div>
      <div class="opt-row">
        <label for="brand-selector" style="margin-right:auto;display:flex; width:fit-content">Бренд</label>
        <select id="brand-selector" class="selector" name="">
          <option value="0">Все</option>
          <?foreach ($brands as $elem){
            if ( $elem['id'] == $limC['brand'] ){
              echo '<option value="'.$elem['id'].'" selected>'.$elem['name'].'</option>';
            }else{
              echo '<option value="'.$elem['id'].'">'.$elem['name'].'</option>';
            }

          }?>
        </select>
      </div>
      <div class="opt-row">
        <label for="page-selector" style="margin-right:auto;display:flex; width:fit-content">Страница</label>
        <select id="page-selector" class="selector">
        </select>
      </div>
    </div>

    <div style="margin-bottom:10px; display:flex; margin-left:auto; height:fit-content">
      <button id="update-cache" class="btn btn-danger">Обновить кэш списка</button>
    </div>

    </div>
    <hr>
    <div class="nav-btns">
      <button class="prev-btn util-btn btn btn-danger">Назад</button>
      <button class="next-btn util-btn btn btn-primary">Далее</button>
    </div>
  </div>

  <div id="util-body">

  </div>
  <div id="util-footer">
    <div class="nav-btns">
      <button class="prev-btn util-btn btn btn-danger">Назад</button>
      <button class="next-btn util-btn btn btn-primary">Далее</button>
    </div>
  </div>
</div>

<style media="screen">
  #container{
    width: 100%;
  }
  #util-header{
    display: flex;
    flex-direction: column;
    margin-bottom: 25px;
  }
  #util-body{
    border-top: 1px solid rgba(0,0,0,0.25);
    border-bottom: 1px solid rgba(0,0,0,0.25);
  }
  #util-footer{
    margin-top: 25px;
  }
  #update-cache{
    display: flex;
    margin-left: auto;
  }
  .settings{
    display: flex;
    flex-direction: row;
    margin-top: 20px;
  }
  h1{
    font-weight: bold;
  }
  .selector{
    width: 140px;
    background: white;
    border: 1px solid rgba(0,0,0,0.25);
    height: 32px;
    margin-bottom: 10px;
    border-radius: 4px;
  }
  .opt-row{
    display: flex;
    flex-direction: row;
  }
  .nav-btns{
    display: flex;
    flex-direction: row;
  }
  .util-btn{
    display: flex;
  }
  .next-btn{
    margin-left: auto;
  }
  .list-block{
    display: flex;
    flex-wrap: wrap;
  }
  .card{
    display:flex;
    flex-direction: column-reverse;
    margin-bottom: 15px;
    padding: 10px;
    border-right: 1px solid rgba(0,0,0,0.25);
    border-bottom: 1px solid rgba(0,0,0,0.25);
    border-radius: 4px;
  }
  .card:hover{
    border-right: 1px solid rgba(0,255,0,0.5);
    border-bottom: 1px solid rgba(0,255,0,0.5);
  }
  .card-img{
    width: 276px;
  }
  .card-name{
    text-decoration: none;
    font-size: 17px;
    font-weight: bolder;
  }
  /* Спиннер */
  .spin-wrapper{
    position: relative;
    width: 100%;
    height: 100%;
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
      margin: -114px;
      border-radius: 50%;
      animation: spin 2s linear infinite;
  }
  @keyframes spin{
    0% {transform: rotate(0deg);}
    100% {transform: rotate(360deg);}
  }
</style>

<script type="text/javascript">
  const ajax_folder_path = "ajax/";
  const params = new URLSearchParams( window.location.search );
  if ( window.location.hash == '' ){
    new_url = window.location.pathname + window.location.search + '#0';
    history.pushState(null, null, new_url);
  }

  var offset = parseInt(window.location.hash.replace('#',''));
  var limit = $("#limit-selector").val();

  console.log(window.location.pathname);
  console.log(window.location.search);
  console.log(window.location.hash);

  getCards( offset, limit );

  $(document).on('click', '.next-btn', function(){
    offset += parseInt(limit);
    new_url = window.location.pathname + window.location.search + '#' + offset;
    history.pushState(null, null, new_url);
    getCards(offset, limit)
  })

  $(document).on('click', '.prev-btn', function(){
    offset -= limit;
    if ( offset < 0 ){
      offset = 0;
    }
    new_url = window.location.pathname + window.location.search + '#' + offset;
    history.pushState(null, null, new_url);
    getCards(offset, limit)
  })

  $(document).on('change', '.selector', function(){
    limit = $("#limit-selector").val();
    offset = parseInt( $("#page-selector").val() );
    new_url = window.location.pathname + window.location.search + '#' + offset;

    history.pushState(null, null, new_url);
    getCards(offset, limit);
  })
  // $(document).on('change', '#brand-selector', function(){
  //   getCards(offset, limit);
  // })
  // $(document).on('change', '#limit-selector', function(){
  //
  //   getCards(offset, limit);
  // })
  // $(document).on('change', '#page-selector', function(){
  //   offset = parseInt( $("#page-selector").val() );
  //   // console.log(offset);
  //   new_url = window.location.pathname + window.location.search + '#' + offset;
  //   history.pushState(null, null, new_url);
  //   getCards(offset, limit);
  // })

  $(document).on('click', '#update-сache', function(){
    updateCache();
  })

  function getCards( os, lt ){
    var mode = $('#mp-selector').val();
    var brid = $('#brand-selector').val();
    var sort = $('#sort-selector').val();
    var isOzon = $('#isOzon-selector').val();

    $('#util-body').html('');
    $('#util-body').html('\
      <div class="spin-wrapper">\
        <div class="spinner">\
        </div>\
      </div>\
      ');

    $.ajax({
      url: ajax_folder_path + 'getItems.php',
      method: 'POST',
      data: {
        offset: offset,
        limit: limit,
        mode: mode,
        brand: brid,
        sort: sort,
        isOzon: isOzon,
      },
      success: function(response){
        $('#util-body').html( response );
        var page_info = $('#page-info').html();
        page_info = $.parseJSON(page_info);

        if ( page_info.offset == 0 ){
          $('.prev-btn').hide();
        }else{
          $('.prev-btn').show();
        }

        if ( page_info.limit > page_info.this ){
          $('.next-btn').hide();
        }else{
          $('.next-btn').show();
        }
        console.log(page_info)
        // var pages = Math.floor(page_info.total / page_info.limit);
        var k = 1;
        $("#page-selector").html('');
        for ( i = 0; i <= page_info.total; i += parseInt(limit) ){
          if ( parseInt(os) == i ){
            $("#page-selector").append('<option value="'+i+'" selected>'+k+'</option>');
          }else{
            $("#page-selector").append('<option value="' + i + '">' + k + '</option>');
          }
          k++;
        }
      }
    })
  }

  function updateCache( button ) {
    button.html("Обновляю...");
    $.ajax({
      url: ajax_folder_path + "updateCache.php",
      method: 'POST',
      success: function(response){
        alert('Кэш списка успешно обновлен!');
        button.html("Обновить кэш списка");
      },
      error: function(response){
        alert('Обновление кэша завершилось ошибкой!');
        button.html("Обновить кэш списка");
      }
    })
  }
</script>
