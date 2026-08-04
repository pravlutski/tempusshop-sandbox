<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetPageProperty("description", "Сборка");
$APPLICATION->SetPageProperty("title", "Сборка");

?>
<?if(!CModule::IncludeModule('panel.manager'))return;?>
<div class="col-sm-12 row barcode-main blocked">


<link href="/bitrix/templates/admin_panel/assets/bulma-calendar-master/css/bulma-calendar.min.css" rel="stylesheet">
<script src="/bitrix/templates/admin_panel/assets/bulma-calendar-master/js/bulma-calendar.min.js"></script>

<input id="datepickerDemoDefault" class="input is-hidden" type="text" value="10.03.2026 00:00"></div>
<input id="datepickerDemoDefault2" class="input is-hidden" type="text" ></div>
		
		<script type="text/javascript">
		
$(document).ready(function(){
	var calendar1 = new bulmaCalendar('#datepickerDemoDefault', {
		dateFormat: 'dd.MM.yyyy',
		showButtons: false,
		closeOnSelect: true,
		validateLabel: 'Применить',
		cancelLabel: 'Отмена',
		clearLabel: 'Очистить',
		todayLabel: 'Сегодня',
		nowLabel: 'Сегодня',
		timeFormat: 'HH:mm'
	});
	var calendar2 = new bulmaCalendar('#datepickerDemoDefault2', {
		dateFormat: 'dd.MM.yyyy',
		showButtons: false,
		closeOnSelect: true,
		timeFormat: 'HH:mm'
	});
});
		
		</script>
</div>
<style>

</style>
<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>
