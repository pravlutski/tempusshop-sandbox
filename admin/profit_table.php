<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");?>
<?if(!CModule::IncludeModule('panel.manager'))return;?>
<?
$db = new DBPanel;

if ( empty( $_GET['table'] ) ) die('Не указана таблица');
$allowedTables = [
  'ms_profit_ru_6',
  'ms_profit_ru_12',
  'ms_profit_by_6',
  'ms_profit_by_12',
];

if( !in_array($_GET['table'], $allowedTables) ) die('Неккоректная таблица');

$data = $db->select(['*'], $_GET['table'])->make();
?>
<h2>Прибыльность <?echo mb_strtoupper(explode('_', $_GET['table'])[2]);?> за <?echo explode('_', $_GET['table'])[3];?> месяцев</h2>
<table class="table table-stripped">
  <thead>
    <tr>
      <th style="width:45px">#</th>
      <th>Артикул</th>
      <th>Кол-во продаж, шт.</th>
    </tr>
  </thead>
  <tbody>
    <?
    foreach ($data as $key => $value) {
      $index = $key + 1;
      echo "<tr>";
      echo "<td>{$index}</td>";
      echo "<td>{$value['model']}</td>";
      echo "<td>{$value['sellQuantity']}</td>";
      echo "</tr>";
    }
    ?>
  </tbody>
</table>

<style media="screen">

</style>

<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>
