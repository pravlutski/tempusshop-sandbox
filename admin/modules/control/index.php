<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");?>
<?if(!CModule::IncludeModule('panel.manager'))return;?>
<?$curdate = date('Y-m-d');?>
<?$APPLICATION->SetTitle('Контрольная панель');?>
<?$APPLICATION->SetPageProperty("page_h1", "Контрольная панель");?>
<?
opcache_reset();
global $DB;
global $USER;
$arGroups = $USER->GetUserGroupArray();

CModule::IncludeModule("panel.manager");
$metric = new Metric();
?>
<style>
       table {
           width: 100%;
           margin-top: 20px;
           margin-bottom: 20px;
           border-collapse: collapse;
       }
       table thead th {
           background-color: #dede2b;
           color: black;
       }
       table tbody tr:nth-of-type(even) {
           background-color: #f2f2f2;
       }
       table, th, td {
           border: 1px solid #ddd;
           padding: 8px;
       }
       th {
           text-align: center;
       }
       td {
       }
   </style>
</head>
<body>
<div class="container">
   <div class="mb-4">
       <form action="" method="post" class="col">
           <input type="text" name="q" class="form-control" placeholder="Введите модель">
       </form>
   </div>
   <?if (empty($_POST)) {?>
     <div style="padding: 100px;  font-size: 32px;  text-align: center;">Введите модель в строку поиска</div>
   <?} else {?>

        <?
        $res = $metric->getRecordsByModel($_POST['q']);
        //print_r($res);
        ?>
        <? if ($res) {?>
         <table class="table">
             <thead>
                 <tr>
                     <th>Дата-время</th>
                     <th>Артикул</th>
                     <th>Операция</th>
                     <th>Результат</th>
                 </tr>
             </thead>
             <tbody>
                <?php foreach ($res as $key => $v){ ?>
                 <tr>
                     <td><?=$v['datetime']?></td>
                     <td><?=$v['model']?></td>
                     <td><?=$v['o_name']?></td>
                     <td><?=$v['result']?></td>
                 </tr>
                 <?php } ?>
             </tbody>
         </table>
         <?}else{?>
           <div style="padding: 100px;        font-size: 32px;        text-align: center;">Модель не найдена</div>
         <?}?>
   <?}?>

</div>

<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>
