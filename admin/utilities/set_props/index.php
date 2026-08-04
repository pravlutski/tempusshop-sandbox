<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");?>
<?if(!CModule::IncludeModule('panel.manager'))return;?>
<h1 class="page-header">Установить значения свойств для товаров</h1>

<?
set_time_limit(3600);
$objContent = new CPanelContent;

$tmp = $objContent->getProps(); 
$tmp = sort_nested_arrays($tmp, $args = array('sort2' => 'asc', 'sort' => 'asc'));
foreach($tmp as $arItem) $arResult["PROPS"][$arItem["id"]] = $arItem;

global $USER;
$arGroups = $USER->GetUserGroupArray();

if (!$USER->IsAdmin() && !in_array(7, $arGroups)) 
{
    $APPLICATION->AuthForm(GetMessage("PERMISION_DENIED"));
    return ;
}
//prent($_POST);

?>
<div class="progress " style="margin:6px 0 0 0;display:none;">
	<div class="progress-bar progress-bar-striped active" role="progressbar" style=""></div>
</div>
<form action="/admin/utilities/set_props/set.php" method="post" id="form_set_prop" >
	<div class="page_header_selects clearfix">
		<div class="page_header_select" style=" width: 45%;margin: 0;">
			<label style="display: block;">Список артикулов</label>
			<textarea class="form-control select_w" name="list_articles" style="width: 90%;height: 200px;"><?if($_POST["list_articles"]):?><?=addslashes($_POST["list_articles"])?><?endif?></textarea>
		</div>
		<div class="page_header_select" style="    width: 50%;">
			<label style="display: block;">Свойство</label>
			<select class="form-control select_w" name="prop" id="sel_prop" onchange="getVariants(this);">
				<option>--- Выберите сойство ---</option>
				<?foreach($arResult["PROPS"] as $key => $arItem):?>
				<option value="<?=$arItem["id"]?>"><?=$arItem["name"]?></option>
				<?endforeach?>
			</select>
			<div id="brand_defaults_newrow_values" style="    margin: 15px 0 0 0;"></div>
		</div>
	</div>

	<input type="submit" class="btn btn-primary btn_big_width" name="set_props" value="Установить">
	<a href="#" id="runScript"  class="btn" data-action="run">Старт</a>
	<a href="#" id="refreshScript" class="btn" style="display: none;">Заново</a>
</form>
<div id="res-ajax"></div>
<script>
	
    var JSONPROPS = '<?=json_encode($arResult["PROPS"], JSON_UNESCAPED_UNICODE)?>';
    var PROPS = jQuery.parseJSON( JSONPROPS );
	
    function getVariants( obj ){
	
		var code = $(obj).find(":selected").val();
		console.log(code);
		console.log(PROPS);
		var prop_options = '';
                if( PROPS[code].values !== null )
                {
					if(PROPS[code].is_multiple == "Y"){

						prop_options = '<input type="hidden" name="MULTIPLE" value="Y">';
						prop_options += '<p style="margin: 0 0 0 0;padding: 3px 3px 3px 10px;border: 1px solid black;"><input type="checkbox" name="INSERT_TO" value="Y" checked><span>Добавить к списку без перезаписи</span></p>'
						for( var key in PROPS[code].values )
							prop_options += '<p style="margin: 0 0 0 0;"><input type="checkbox" name="PROPS[' + PROPS[code].id + '][]" value="' + key + '"><span>' + PROPS[code].values[key] + '</span></p>'
						$('#brand_defaults_newrow_values').html(prop_options);
					}else{
						for( var key in PROPS[code].values )
							prop_options += '<option value="'+ key +'">' + PROPS[code].values[key] + '</option>'
						$('#brand_defaults_newrow_values').html(
							'<select name="PROPS[' + PROPS[code].id + ']" class="form-control">' + prop_options + '</select>'
						);
					}

                }
                else
                {
                    $('#brand_defaults_newrow_values').html(
                        '<input type="text" name="PROPS[' + PROPS[code].id + ']" class="form-control">'
                    );
                }
	/*
        var clear_props = jQuery.parseJSON( JSONPROPS );
        var tr = $( button.parentNode );
        var code = tr.find( 'td:nth-child(2) .def_val').attr('name');
        PROPS[code] = clear_props[code];
        tr.remove();*/
    }

	<?if($_POST["PROPS"]):?>
		<?foreach($_POST["PROPS"] as $code => $ar):?>
		$("#sel_prop option[value=<?=$code?>]").prop('selected', true);
		//var ob = $('sel_prop[name=<?=$code?>]');
		//console.log(ob);
		//getVariants(ob);
		$("#sel_prop").change();
		<?endforeach?>
	<?endif?>
	
	
function setCookie (offset){
    	var ws=new Date();
		if (!offset) {
			ws.setMinutes(10-ws.getMinutes());
		} else {
			ws.setMinutes(10+ws.getMinutes());
		}
//		document.cookie="scriptOffsetUrl="+url+";expires="+ws.toGMTString();
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

function showProcess (data, sucsess, offset, action) {
		$('#refreshScript').hide();
		$('.progress').show();
//		$('#runScript').text('Стоп!');
		//$('.progress-bar').text(url);
		$('.progress-bar').css('width', sucsess * 100 + '%');
		setCookie(offset);

		$('#runScript').click(function(){
				document.location.href=document.location.href
			});
		
		scriptOffset(data, offset, action);
}

function scriptOffset (dataForm, offset, action) {
	$.ajax({
		url: "/admin/utilities/set_props/set_props.php",
		type: "POST",
		data: {
			"action":action
			, "data":dataForm
			, "offset":offset
		},
		success: function(data2){
			
			
			data = $.parseJSON(data2);
			$("#res-ajax").append(data.log);
			
			if(data.sucsess < 1) {
				showProcess(dataForm, data.sucsess, data.offset, action);
			} else {
				setCookie();
				$('.progress-bar').css('width','100%');
				$('.progress-bar').text('OK');
				$('#runScript').text('Старт');
			}
		}
	});
}
	
$(document).ready(function() {
	
	//var url = getCookie("scriptOffsetUrl");
	var offset = getCookie("scriptOffsetOffset");
	
	/*if (url && url != 'undefined') {
		$('#refreshScript').show();
		$('#runScript').text('Продолжить');
		$('#url').val(url);
		$('#offset').val(offset);
	}*/
	
	$('#runScript').click(function() {
		$("#res-ajax").html();
			var action = $('#runScript').data('action');
			var offset = $('#offset').val();
			var data = $('#form_set_prop').serialize();
			/*if ($('#url').val() != getCookie("scriptOffsetUrl")) {
					setCookie();
					scriptOffset(data, 0, action);
				} else {
				console.log(data);console.log(offset);console.log(action);
//					scriptOffset(url, offset, action);
				}*/
			scriptOffset(data, 0, action);
			return false;
		});
		
	$('#refreshScript').click(function() {
		
			var action = $('#runScript').data('action');
			var data = $('#form_set_prop').serialize();
		
			setCookie();
			scriptOffset(data, 0, action);
			return false;
		});
		
});
</script>

<?
echo $set_text;
?>
<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>