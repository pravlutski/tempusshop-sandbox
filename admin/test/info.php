<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");?>
<?if(!CModule::IncludeModule('panel.manager'))return;?>

<?
$APPLICATION->SetPageProperty("page_h1", "Товары созданные через контент-редактор - модуль сайтов");
$APPLICATION->SetTitle('Товары созданные через контент-редактор - модуль сайтов');
?>

<?
opcache_reset();
global $DB;
global $USER;
$arGroups = $USER->GetUserGroupArray();

CModule::IncludeModule("iblock");

$logFile = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/logs/wb/log.txt';
$log = file_get_contents($logFile);

?>
<div class="system-log" >
  <div id ="textarea" class="textarea">
    <?
    if (!empty($log)) {
      print_r($log,true);
  } else {
      echo 'Лог пуст';
  }
    ?>
  </div>
</div>
<style>
.textarea {
    max-width: 800px;
    width: 100%;  height: 70vh;
    border: 1px solid #ccc; /* Цвет и ширина границы */
    padding: 5px; /* Отступ внутри области */
    overflow-y: scroll; /* Вертикальная прокрутка */
    resize: none; /* Отключение изменения размеров */
    font-family: inherit; /* Наследуемый шрифт, чтобы соответствовать остальной части страницы */
    font-size: 14px; /* Размер шрифта */
    background-color: #fff; /* Цвет фона */
}
</style>
<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>
