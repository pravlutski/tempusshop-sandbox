<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");?>
<?$APPLICATION->SetTitle('Главная - Yandex модуль');?>
<?$APPLICATION->SetPageProperty("page_h1", "Панель управления");?>

<div class="card" id="table-panel">

</div>

<style>
.action-btn{
  padding: 3px 15px !important;
  min-width: 145px;
}
.action-icon {
  width: 16px;
  height: 16px;
  display: inline-block;
  vertical-align: middle;
  margin-right: 2px;
  margin-top: -4px;
}
.custom-bar {
  margin: 5px!important;
  height: 100%!important;
  width: 100%!important;
}
.resize {
  display: flex!important;
  align-items: center!important;
}
.card {
  max-width: 1200px;
}
.card li {
    height: 80px;
}
.name {
  width: 30%;
  font-size: 18px;
  font-weight: 500;
}
.status {
  width:50%;
  display: flex;
  flex-direction: column;
}
.control {
  width: 20%;
  display: flex;
  flex-direction: column;
  justify-content: flex-end;
  gap:5px;
  align-items: flex-end;
}
.control img {
  width:28px;
  opacity: 0.7;
  cursor:pointer;
}
.control img:hover{
  opacity: 1;
}
.progress {
  height: 100%!important;
  font-weight: 500!important;
  font-size: 14px!important;
}
.time-text {
  font-size: 14px;
  color: #aaa8a8;
}
.progress-bar-animated {
  -webkit-animation: 1s linear infinite progress-bar-stripes!important;
  animation: 1s linear infinite progress-bar-stripes!important;
}
.mob-break{
  display: none;
}
.c-menu-btn-op{
  top: 400px;
  display: none;
  border-top-right-radius: 12px;
  border-bottom-right-radius: 12px;
  height: 80px;
  width: 25px;
  border-right: 1px solid black;
  background-color: white;
}
.c-menu-btn-cls{
  top: 400px;
  display: none;
  border-top-left-radius: 12px;
  border-bottom-left-radius: 12px;
  height: 80px;
  width: 25px;
  border-left: 1px solid black;
  background-color: white;
  z-index: 999;
}
@media (max-width: 867px){
  .list-group-item{
    display: flex !important;
    flex-direction:column !important;
  }
  .name, .control, .status{
    width: 100%;
  }
  .progress{
    width: 95%;
  }
  .control{
    margin-top: 15px;
    margin-bottom: 15px;
    flex-direction: row;
  }
  .card li {
      height: 160px;
  }
  .mob-break{
    display: block;
  }
  .comp-text{
    border-bottom: 1px solid rgba(0,0,0,0.15);
    margin-bottom: 5px;
  }
}
</style>
<script defer src="<?=SITE_TEMPLATE_PATH?>/js/home.js"></script>
<?require("include/modalog.php");?>
<?require("include/completeToast.php");?>
<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>
