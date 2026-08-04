<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");?>
<?if(!CModule::IncludeModule('panel.manager'))return;?>
<?$APPLICATION->SetTitle('Детальный лог акций');?>
<?$APPLICATION->SetPageProperty("page_h1", "Детальный лог акций");?>
<link href="<?=SITE_TEMPLATE_PATH?>/css/sales.css" rel="stylesheet">
<script src="<?=SITE_TEMPLATE_PATH?>/js/sales.js"></script>
<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js" integrity="sha256-lSjKY0/srUM9BE3dPm+c4fBo1dky2v27Gdjm2uoZaL0=" crossorigin="anonymous"></script>
<link href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css" rel="stylesheet">
<style>
.ui-front {
  z-index: 1000000000!important;
}
</style>
<?


global $DB;
global $USER;
$arGroups = $USER->GetUserGroupArray();

$strSql = "SELECT * FROM wdhs_ozon_sales_new";
$results = $DB->Query($strSql, false, $err_mess.__LINE__);

while ($row = $results->Fetch()){
  $salesActive[$row['sale_id']] = $row['name'];
}
?>
<div class="content-ajax">
<div class="row">
  <form method="POST" action="/admin/modules/ozon2/sales/detail-log-ajax.php" class="s-form" style="">
    <div class="input-group imput-s" style="max-width:500px;">
      <span class="input-group-text">Введите модель</span>
      <input type="text" name="model" class="form-control" aria-label="" value="">
    </div>
    <div class="input-group" style="max-width: 300px;">
      <select class="form-select form-select-sm" name="sale_id" aria-label=".form-select-sm example">
        <option value="0">---Фильтр по акциям---</option>
        <?php foreach ($salesActive as $key => $value): ?>
          <option value="<?=$key?>" <?if ($key == $_GET['sale_id']) { echo "selected";}?>><?=$value?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="input-group">
      <button type="submit" class="btn btn-warning">Поиск</button>
    </div>
  </form>
</div>
</div>
<script>
$(document).ready(function() {
    $('#ajax-form').on('submit', function(e) {
        e.preventDefault(); // Предотвратить стандартную отправку формы

        $.ajax({
            url: $(this).attr('action'),
            type: 'POST',
            data: $(this).serialize(), // Собираем данные формы
            success: function(response) {
                // Заменяем содержимое элемента с классом content-ajax на ответ сервера
                $('.content-ajax').html(response);
            },
            error: function(xhr, status, error) {
                // Обработка ошибок
                console.error('Ошибка:', error);
            }
        });
    });
});
</script>
<style>
table {
width: 50%;
border-collapse: collapse;
margin: 20px 0;
font-size: 16px;
font-family: Arial, sans-serif;
background-color: #d9d9d9;
  border-radius: 10px;
}
th, td {
border: 1px solid #ddd;
padding: 10px;
}
th {
text-align: left;
}
</style>
<style>

.arrow-4 {
    position: relative;
    cursor: pointer;
    margin:20px;
    width: 66px;
    height: 30px;
}
.arrow-4-left {
    position: absolute;
    background-color: transparent;
    top: 10px;
    left: 0;
    width: 20px;
    height: 5px;
    display: block;
    transform: rotate(35deg);
    float: right;
    border-radius: 2px;
}
.arrow-4-left:after {
    content: "";
    background-color: #337AB7;
    width: 20px;
    height: 5px;
    display: block;
    float: right;
    border-radius: 6px 10px 10px 6px;
    transition: all 0.5s cubic-bezier(0.25, 1.7, 0.35, 0.8);
    z-index: -1;
}

.arrow-4-right {
    position: absolute;
    background-color: transparent;
    top: 10px;
    left: 14px;
    width: 20px;
    height: 5px;
    display: block;
    transform: rotate(-35deg);
    float: right;
    border-radius: 2px;
}
.arrow-4-right:after {
    content: "";
    background-color: #337AB7;
    width: 20px;
    height: 5px;
    display: block;
    float: right;
    border-radius: 10px 6px 6px 10px;
    transition: all 0.5s cubic-bezier(0.25, 1.7, 0.35, 0.8);
    z-index: -1;
}
.open .arrow-4-left:after {
    transform-origin: center center;
    transform: rotate(-70deg);
}
.open .arrow-4-right:after {
    transform-origin: center center;
    transform: rotate(70deg);
}

.tabled {
    display:none;
}
.header_table {
    display: flex;
    flex-direction: row;
    align-items: center;
    padding-left: 20px;;
    font-size: 18px;;
}
.header_table span {
    font-weight: bold;
    font-size:24px;
}
</style>
<style>
.s-form {display: flex;gap: 1rem;}
.s-form .imput-s {max-width:500px;}
.price-analiz{
  display: flex;
  gap: 2rem;
  font-size: 14px;
}
.accordion-button:focus {
  z-index: 3;
  border-color: unset!important;
  outline: inherit!important;
  box-shadow: none!important;
}
.accordion-button:not(.collapsed)::after {
  background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%23212529'%3e%3cpath fill-rule='evenodd' d='M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z'/%3e%3c/svg%3e")!important;
}
.accordion-button:not(.collapsed) {
  color: inherit!important;
  background-color: inherit!important;
  box-shadow: none!important;
}
@media screen and (max-width: 767px) {
  .s-form {display: flex;gap: 1rem;flex-direction: column;}
  .s-form .imput-s {width: 100%}
}
</style>
<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>
