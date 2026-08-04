<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");?>
<?if(!CModule::IncludeModule('panel.manager'))return;?>
<div id="settings-main" class="col-sm-12 row">
<?
global $USER;

use Bitrix\Main\Page\Asset;
Asset::getInstance()->addJs("/bitrix/templates/admin_courier/js/datatables_all.js");
Asset::getInstance()->addCss("/bitrix/templates/admin_courier/css/datatables.min.css");



Asset::getInstance()->addJs("/bitrix/templates/admin_courier/js/jquery-ui.min.js"); 
Asset::getInstance()->addJs("/bitrix/templates/admin_courier/js/bootstrap.js");

Asset::getInstance()->addCss("/bitrix/templates/admin_courier/css/datepicker.css");
Asset::getInstance()->addCss("/bitrix/templates/admin_courier/css/jquery-ui.min.css");
	
Asset::getInstance()->addJs("/bitrix/templates/admin_panel/js/jquery-ui-timepicker-addon.js");

global $DB;

$strSql = "SELECT * FROM ci_retail_loyalty";
$results = $DB->Query($strSql, false, $err_mess.__LINE__);
while ($row = $results->Fetch()){
					
	$arResult['ITEMS'][] = $row;
					
} 
				
?>

<table class="table" id="barcode">
	<thead>
		<tr>
			<th>ID CRM</th>
			<th>ID BITRIX</th>
			<th>Имя</th>
			<th>Телефон</th>
			<th>Бонусы</th>
			<th>SMS</th>
		</tr>
	</thead>
	<tbody>

		<?foreach($arResult['ITEMS'] as $key => $arItem):?>
			<tr>
				<td><a href="https://tempusshop.retailcrm.ru/loyalty/accounts/<?=$arItem["RETAIL_ID"]?>" target="_blank"><?=$arItem["RETAIL_ID"]?></a></td>
				<td><a href="/bitrix/admin/user_edit.php?lang=ru&ID=<?=$arItem["USER_ID"]?>" target="_blank"><?=$arItem["USER_ID"]?></a></td>
				<td><?=$arItem["USER_NAME"]?></td>
				<td><?=$arItem["PHONE"]?></td>
				<td><?=$arItem["AMOUNT"]?></td>
				<td><?=$arItem["SEND_SMS"]?></td>
			</tr>
			<?$i++;?>
		<?endforeach?>
	</tbody>
</table>

<script>

jQuery.extend( jQuery.fn.dataTableExt.oSort, {
	"currency-pre": function ( a ) {
		//a = a.replace(/<\/?[^>]+(>|$)/g, "");
		//a = a.replace(/(<([^>]+)>)/ig, "");
		//a = a.replace(/<span[\s\w"\=-]*class="sticker6"[\s\w"\=-]*>[\s\w]*<\/span>/g, "");
		a = a.replace(/<span.*?class=(?:"|"(?:[^"]*)\s)sticker6(?:"|\s(?:[^"]*)").*?>(.*?)<\/span>/g,'');
		a = a.replace(/<\/?[^>]+(>|$)/g, "");
		
		a = (a==="-" || a==="") ? 0 : a.replace( /[^\d\-\.]/g, "" );
		return parseFloat( a );
	},
	 
	"currency-asc": function ( a, b ) {
		return a - b;
	},
	 
	"currency-desc": function ( a, b ) {
		return b - a;
	}
});
	
$(document).ready(function() {
    $('table#barcode').DataTable({
		searching: false,
        scrollCollapse: false,
		fixedHeader: false,
		fixedColumns: false,
		columnDefs : [
			{ targets: [5], type: 'currency' }
		],
        "paging":   false,
        "ordering": true,
		"order": [[ 4, "desc" ]],
        "info":     false,
		dom: 'Bfrtip',
		buttons: [
			{
  extend : 'excel',
"fnCellRender": function ( sValue, iColumn, nTr, iDataIndex ) {
	console.log("sdfsdf");
                        if ( iColumn === 2 ) {
                            //feel free to modify the value here
                            return sValue +" TableTools";
                        }
                        return sValue;
                    }
},
            'copy', 'csv', 'pdf', 'print'
        ],
        "language": {
		    "decimal":        ",",
			"thousands":      ".",
            "zeroRecords": "Nothing found - sorry",
            "info": "Показана страница _PAGE_ из _PAGES_",
            "sPrevious": "No records available",
            "infoFiltered": "(filtered from _MAX_ total records)"
        }
    });
});
</script>

</div>
<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>