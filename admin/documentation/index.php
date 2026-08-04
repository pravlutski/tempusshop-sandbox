<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");?>
<?if(!CModule::IncludeModule('panel.manager'))return;?>
<?$APPLICATION->SetTitle('Документация');?>
<h1>Документация</h1>
<div id="doc-main" class="col-sm-12 row" style="margin-top: 20px;">
  <a href="../help.php" class="btn btn-primary">Описание настроек</a>
  <a href="../crontab.php" class="btn btn-primary">Описание кронтаба</a>
</div>
<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>
