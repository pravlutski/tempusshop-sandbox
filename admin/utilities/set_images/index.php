<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");?>
<?if(!CModule::IncludeModule('panel.manager'))return;?>
<?AccessValidator::checkIfAllowed(); // Менеджер прав?>
<h1 class="page-header">Загрузить картинки к товарам</h1>

<?
global $USER;
$arGroups = $USER->GetUserGroupArray();

if (!$USER->IsAdmin() && !in_array(7, $arGroups))
{
    $APPLICATION->AuthForm(GetMessage("PERMISION_DENIED"));
    return ;
}

//$arFields['PREVIEW_PICTURE'] = CFile::ResizeImageGet("/var/www/bitrix/data/www/tempusshop.ru/upload/newci/687775f9c3ab6b264b2000f78d852f1c.jpeg", array('width'=>200, 'height'=>200), BX_RESIZE_IMAGE_PROPORTIONAL);


//sdfsdf@https://www.casio-europe.com/resource/images/watch/zoom/HS-80TW-1EF.jpg;https://opt-99999999.ssl.1c-bitrix-cdn.ru/main/706/7063f519fc1a6190dc9de2a9aedaddee/bitrix-property-tables-file-cell.png
?>
<div class="progress" style="display: none;">
	<div class="progress-bar progress-bar-striped active" role="progressbar" aria-valuenow="50" aria-valuemin="0" aria-valuemax="100" style="width: 0%;"></div>
</div>
<input id="offset" name="offset" type="hidden">
<form action="#" method="post" id="form-set-images">
	<div class="page_header_selects clearfix">
		<div class="col-sm-12 row" style=" margin: 0;">
			<label style="">Список (разделитель ; или табуляция)</label>
			<textarea class="form-control select_w" name="list_articles" style="width: 100%;height: 200px;font-size: 9px;"><?if($_POST["list_articles"]):?><?=addslashes($_POST["list_articles"])?><?endif?></textarea>
		</div>
		<div class="col-sm-12 row panel panel-default" style=" ">
			<div class="" style="padding: 10px 0 10px 4px;    margin: 10px 0 10px 0;">
				<input type="checkbox" class="btn-checkbox" id="replace1" name="replace1" value="Y" <?if($_POST["replace1"] == "Y"):?>checked<?endif?>>
				<label for="replace1" style="line-height: 18px;float: left;">Перезаписать детальную и первую дополнительную</label>
			</div>
			<div class="" style="padding: 10px 0 10px 4px;    margin: 20px 0 25px 0;">
				<input type="checkbox" class="btn-checkbox" id="replace2" name="replace2" value="Y" <?if($_POST["replace2"] == "Y"):?>checked<?endif?>>
				<label for="replace2" style="line-height: 18px;float: left;">Перезаписать со второй доп.картинки</label>
			</div>
      <div class="" style="padding: 10px 0 10px 4px;    margin: 20px 0 25px 0;">
        <input type="checkbox" class="btn-checkbox" id="replace3" name="replace3" value="Y" <?if($_POST["replace3"] == "Y"):?>checked<?endif?>>
        <label for="replace3" style="line-height: 18px;float: left;">Перезаписать базу для инфографики</label>
      </div>
			<span class="badge" style="margin: 0 0 10px 5px;">Если не выбран ни один селектор картинки будут добавлены в конец списка к дополнительным</span>
		</div>

	</div>
	<a href="#" id="runScript"  class="btn btn-primary btn_big_width" data-action="run">Установить</a>
</form>
<script>
function setCookie (offset){

    	var ws=new Date();
		if (!offset) {
			ws.setMinutes(10-ws.getMinutes());
		} else {
			ws.setMinutes(10+ws.getMinutes());
		}

		document.cookie="scriptOffsetOffset="+offset+";expires="+ws.toGMTString();

}

function getCookie(name) {
		var cookie = " " + document.cookie;
		var search = " " + name + "=";
		var setStr = null;
		var offset = 0;
		var end = 0;
		if (cookie.length > 0) {
			offset = cookie.indexOf(search);
			if (offset != -1) {
				offset += search.length;
				end = cookie.indexOf(";", offset)
				if (end == -1) {
					end = cookie.length;
				}
				setStr = unescape(cookie.substring(offset, end));
			}
		}
		return(setStr);
	}

function showProcess (sucsess, offset, action) {


	$('.progress-bar').text(parseFloat(sucsess * 100).toFixed(2) + '%');
	$('.progress-bar').css('width', sucsess * 100 + '%');
	//setCookie(offset);

	//$('#runScript').click(function(){
	//	document.location.href=document.location.href
	//});

	scriptOffset(offset, action);

}

function scriptOffset (offset, action) {

	//if(action == "stop") return;
	//var action = $('#runScript').data('action');

	var dataScript = $("#form-set-images").serialize() + "&action=" + action + "&offset=" + offset;
	console.log(dataScript);
	$.ajax({
		url: "/admin/utilities/set_images/ajax.php",
		type: "POST",
		data: dataScript,
		dataType: "json",
		success: function(data){
			console.log(data);
			var textStatus = "";

			if(data.error !== null && data.error.length !== null){
				$.each(data.error, function(key, value){
					textStatus += value;
				});
			}
				//
			if(data.info !== null && data.info.length !== null){
				$.each(data.info, function(key, value){
					textStatus += value;
				});
			}

			$("#text-status").append(textStatus);

			if(data.sucsess < 1) {
				showProcess(data.sucsess, data.offset, action);
			} else {
				//setCookie();
				$('.progress-bar').css('width','100%');
				$('.progress-bar').text('OK');
				$('#runScript').text('Установить');
				$('#runScript').attr("data-action", "run");
				$('#runScript').removeAttr("disabled");
			}

		},
		error: function(data){
			console.log(data);
		}
	});
}

$(document).ready(function() {

	$('#runScript').click(function() {
		$('#runScript').attr("disabled", "disabled");

		$('.progress').show();
		$('.progress-bar').css('width','0%');

		var action = $('#runScript').data('action');

		if($(this).attr("data-action") == "stop"){

			//$(this).text('Установить');
			$(this).attr("data-action", "run");
			scriptOffset(0, "stop");
		}else{
			$('#runScript').attr("disabled", "disabled");
			$("#text-status").html("");
			//$(this).text('Стоп!');
			$(this).attr("data-action", "stop");
			scriptOffset(0, "run");
		}
		return false;

	});

});
</script>
<hr>
<div id="text-status"></div>
<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>
