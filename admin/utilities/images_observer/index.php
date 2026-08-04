<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$date = date('YmdG');
$string = "ReviewMod{$date}_token";
 ?>
<?AccessValidator::checkIfAllowed(); // Менеджер прав?>
<title>Модерация отзывов</title>
<div class="container-util">
  <h1>Модерация отзывов</h1>
  <hr>
  <input type="hidden" id="pseudoToken" value="<?=md5($string)?>">
  <div class="content"></div>

</div>

<style media="screen">
  .item-container{
    display: flex;
    flex-direction: column;
  }
  .item-content{
    display: flex;
    flex-direction: row;
  }
  .item-name{
    font-size: 18px;
    font-weight: bolder;
    margin-bottom: 30px;
  }
  .quote{
    font-style: italic;
    color: grey
  }
  .item-text{
    font-size: 16px;
  }
  .item-info{
    padding-left: 60px;
    width: 50%;
  }
  .item-image{
    /* min-width: 700px; */
    width: 50%;
  }
  .item-info-main{
    border-radius: 6px;
    background-color: rgba(245, 245, 245, 0.6);
    margin-bottom: 10px;
    display: flex;
    flex-direction: column;
    gap: 4px;
    padding: 25px;
    width: 100%;
  }
  .item-control{
    margin-top: 20%;
    width: 100%;
    display: flex;
    justify-content: end;
  }
  .nav-btn{
    width: 130px;
    height: 40px;
    font-size: 16px;
    border: none;
    border-radius: 6px;
  }
  .like-btn, .nav-stay{
    margin-left: 5px;
  }
  .nav-next{
    margin-left: auto;
  }
  .review-photo{
    width: fit-content;
    max-width: 62%;
  }
  .mod-good{
    color: rgba(0,185,0, 1);
    font-weight: bolder;
    font-size: 16px;
    margin-top: 20px;
  }
</style>

<script
			  src="https://code.jquery.com/jquery-2.2.4.js"
			  integrity="sha256-iT6Q9iMJYuQiMWNd9lDyBUStIq/8PuOW33aOqmvFpqI="
			  crossorigin="anonymous"></script>

<script type="text/javascript">
  var approved = [];

  function removeFromApproved(value) {
    const index = approved.indexOf(value);
    if (index > -1) {
        approved.splice(index, 1);
    }
  }

  function getItemsWithReviews( last = undefined, action = undefined )
  {
    if ( action == 'save' && last != undefined ){
      approved.push(last);
      console.log('approved after push');
      console.log(approved);
    }
    if ( action == 'cancel' && last != undefined ){
      removeFromApproved( last );
      console.log('approved after delete');
      console.log(approved);
    }
    var url = "https://tempus.ru/local/custom/images_observer/ajax/";
    var token = $('#pseudoToken').val();
    console.log(url);
    $.ajax({
      url: url,
      method: "POST",
      data:{
          last: last,
          action: action,
          approved: approved,
          token: token,
      },
      success: function(response){
        $('.content').html(response);
        $('.item-container').fadeIn(100);
      },
      error: function( response ){
        alert('Произошла ошибка');
      }
    });
  }

  $(document).on('click', '.nav-btn', function(e){
    var lastId = $(this).attr('data-id');
    var action = $(this).attr('data-action');
    getItemsWithReviews(lastId, action);
  });

  $(document).on('keydown', function (event) {
    console.log(event.key);
    var lastId = $('.item-container').attr('data-id');
    switch( event.key ){
      case "ArrowRight":
        getItemsWithReviews(lastId, 'save');
        break;
      case "ArrowLeft":
        getItemsWithReviews(lastId, 'next');
        break;
      case "ArrowDown":
        getItemsWithReviews(lastId, 'cancel');
        break;
    }
  });


  getItemsWithReviews();
</script>
