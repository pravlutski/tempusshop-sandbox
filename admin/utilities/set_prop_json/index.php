<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");?>
<?if(!CModule::IncludeModule('panel.manager'))return;?>
<h1 class="page-header">Установить значения свойств для товаров</h1>

<?
set_time_limit(3600);

global $USER;
$arGroups = $USER->GetUserGroupArray();

if (!$USER->IsAdmin() && !in_array(7, $arGroups) && !in_array(6, $arGroups))
{
    $APPLICATION->AuthForm(GetMessage("PERMISION_DENIED"));
    return ;
}

function removeSquareBracketsRecursive($array) {
    $result = [];
    
    foreach ($array as $key => $value) {
        $cleanKey = is_string($key) ? str_replace(['[', ']', "'", '"'], ['', '', '|rbracket1|', '|rbracket2|'], $key) : $key;
        
        if (is_array($value)) {
            $result[$cleanKey] = removeSquareBracketsRecursive($value);
        } elseif (is_string($value)) {
            $result[$cleanKey] = str_replace(['[', ']', "'", '"'], ['', '', '|rbracket1|', '|rbracket2|'], $value);
        } else {
            $result[$cleanKey] = $value;
        }
    }
    
    return $result;
}

$dir_profile = $_SERVER["DOCUMENT_ROOT"] . "/admin/utilities/set_prop_json/profiles/";

$objContent = new CPanelContent;

$tmp = $objContent->getProps();
$tmp = sort_nested_arrays($tmp, $args = array('name' => 'asc', 'sort' => 'asc'));
foreach($tmp as $arItem){
	//if(is_array($arItem["values"]))
		$arResult["PROPS"][$arItem["id"]] = $arItem;
}
prent($_REQUEST);
if($_REQUEST["submit_profile"]){
	if($_REQUEST["new_profile_name"]){
		$profile_name = trim($_REQUEST["new_profile_name"]);

	}else if($_REQUEST["set_profile"]){
		$profile_name = trim($_REQUEST["set_profile"]);

	}else if($_REQUEST["profile_name"]){
		$profile_name = trim($_REQUEST["profile_name"]);
	}

	if($profile_name){
		$p = $_REQUEST["PROPS"];
		$profile_path = $dir_profile . "{$profile_name}.txt";
		file_put_contents($profile_path, json_encode($p));


		$arLog = [
			"date" => date("Y-m-d H:i:s"),
			"user" => $USER->getID(),
			"profile_name" => $profile_name,
			"props" => $p,
		];
		file_put_contents("/home/bitrix/logs/set_prop_json/log_save_profile.txt", json_encode($arLog) . "r\n", 8);
	}else{
		echo "<p style='color: red;'>Имя профиля не определено</p>";
	}
}

// смотрим файл настроеек профиля
if($_REQUEST["profile_name"]){
	$profile_name = trim($_REQUEST["profile_name"]);
	$profile_path = $dir_profile . "{$profile_name}.txt";
	$tmp = file_get_contents($profile_path);
	$arProfile = json_decode($tmp, true);
	$arProfile = removeSquareBracketsRecursive($arProfile);
	//prent($arProfile);
}

$arItems = [];
if($_REQUEST["submit-file"] && $_FILES["file_parse"]){
	$f = $_FILES["file_parse"]["tmp_name"];
	$file = fopen($f, 'r');

	if ($file) {
		while (($line = fgets($file)) !== false) {
			$arItems[] = json_decode($line, true);
		}
		fclose($file);
	}
	///admin/utilities/set_prop_json
	// сохраняем по сути его же во временную папку
	$file_hash = time();

	$filename_parse = $_SERVER["DOCUMENT_ROOT"] . "/admin/utilities/set_prop_json/tmp/{$file_hash}.txt";
	file_put_contents($filename_parse, json_encode($arItems));

	unset($arItems);
}elseif($_REQUEST["filename_parse"]){
	$filename_parse = trim($_REQUEST["filename_parse"]);
	$tmp = file_get_contents($filename_parse);
	$arItems = json_decode($tmp, true);
}else{
	//return false;
}

/* список профилей */
$files = scandir($dir_profile);
$profiles = [];
foreach($files as $file){
	if($file[0] == ".") continue;
	$ar = pathinfo($file);
	$profiles[] = $ar["filename"];
}


//$filename = $_SERVER["DOCUMENT_ROOT"] . "/admin/utilities/set_prop_json/casio_prop.txt";
//$file = fopen($filename, 'r');
function removeSquareBrackets($key) {
    return str_replace(['[', ']'], '', $key);
}


$arProps = [];
$arPropsCnt = [];
$arSkip = [
	"date", "desc", "images",
	"name", "name2", "model",
	"Compatible band size",
];
// $implodeExclude = ["Sensor feature"];
$implodeExclude = [];
foreach($arItems as &$arItem){
	$arItem = removeSquareBracketsRecursive($arItem);
	$arItem = array_map( fn($item) => (is_array($item) ? $item : [$item]), $arItem );
	foreach($arItem as $code => $value){
		$code = removeSquareBrackets($code);
		$value = ( count($value) > 1 && !in_array($code, $implodeExclude) ) ? [implode('. ', $value)] : $value;
		if(in_array($code, $arSkip)) continue;
		if(!$arProps[$code]) $arProps[$code] = [];
		if(is_array($value)){
			foreach($value as &$v){
				$v = removeSquareBrackets($v);
				if(!in_array($v, $arProps[$code])) $arProps[$code][] = $v;
				$arPropsCnt[md5($code.$v)] += 1;
			}
			unset($v);
		} else {
			$value = removeSquareBrackets($value);
		}
	}
}
unset($arItem);

?>
<div class="progress " style="margin:6px 0 0 0;display:none;">
	<div class="progress-bar progress-bar-striped active" role="progressbar" style=""></div>
</div>
<hr>
<div id="text-status"></div>
<?if(!$filename_parse):?>
<form action="/admin/utilities/set_prop_json/" method="post" enctype="multipart/form-data">
	<div id="upload-wrapper">
		<div align="center">
			<input name="file_parse" type="file" />
			<input type="submit"  name="submit-file" id="submit-btn" value="Загрузить" />
		</div>
	</div>
</form>
<?else:?>
	<p>Файл загружен. <a href="/admin/utilities/set_prop_json/">Загрузить новый</a></p>
<?endif?>
<?
$checked = [];
?>
<?if($filename_parse):?>
<form action="/admin/utilities/set_prop_json/" method="post" id="form_set_prop" >
	<div class="profile-block">
		<p>Выберите профиль</p>
		<select class="form-control select_w" name="set_profile" id="set_profile" style="float:left;">
			<option value="">--- Создать новый ---</option>

			<?foreach($profiles as $profile):?>
				<option value="<?=$profile?>" <?if($profile_name && $profile_name == $profile):?>selected<?endif?>><?=$profile?></option>
			<?endforeach?>
		</select>
		<?if($profile_name):?>
		<input type="text" class="form-control" style="width: auto;margin: 0 0 0 59px;display: inline-block;" name="new_profile_name" id="new_profile_name" placeholder="Имя для копирования профиля" value="<?=$profile_name?>">
		<?endif?>
		<input type="text" class="form-control" style="<?if(!$profile_name):?>display: inline-block;<?else:?>display: none;<?endif?>width: auto;margin: 0 0 0 40px;" name="profile_name" id="profile_name" value="<?=$profile_name?>">
	</div>
	<input type="hidden" name="profile_name222" id="profile_name222" value="<?=$profile_name?>">
	<input type="hidden" name="filename_parse" id="filename_parse" value="<?=$filename_parse?>">
	<?foreach($arProps as $code => $arValues):?>
		<div class="page_header_selects clearfix">
			<h2><?=$code?></h2>
			<?
			if($arProfile[$code])
				$s_prop = $arProfile[$code];
			else
				$s_prop = false;


			?>
			<?if($code == "Case size (L× W× H)"):?>
			<table class="table <?if($s_prop):?>open<?endif?>" style="">
				<thead>
					<tr>
						<th style="width: 300px"></th>
						<th style="width: 300px">Свойство</th>
						<th style="width: max-content;">Значение</th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td>Case size (<b>L</b>× W× H)</td>
						<td class="td-prop">
							<select class="form-control select_w" name="PROPS[<?=$code?>][0]">
								<option value="">--- Выберите сойство ---</option>
								<?foreach($arResult["PROPS"] as $key => $arItem):?>
								<option value="<?=$arItem["id"]?>" <?if($arItem["id"] == $s_prop[0]):?>selected<?endif?>><?=$arItem["name"]?></option>
								<?endforeach?>
							</select>
						</td>
						<td>
						</td>
					</tr>
					<tr>
						<td>Case size (L× <b>W</b>× H)</td>
						<td class="td-prop">
							<select class="form-control select_w" name="PROPS[<?=$code?>][1]">
								<option value="">--- Выберите сойство ---</option>
								<?foreach($arResult["PROPS"] as $key => $arItem):?>
								<option value="<?=$arItem["id"]?>" <?if($arItem["id"] == $s_prop[1]):?>selected<?endif?>><?=$arItem["name"]?></option>
								<?endforeach?>
							</select>
						</td>
						<td>
						</td>
					</tr>
					<tr>
						<td>Case size (L× W× <b>H</b>)</td>
						<td class="td-prop">
							<select class="form-control select_w" name="PROPS[<?=$code?>][2]">
								<option value="">--- Выберите сойство ---</option>
								<?foreach($arResult["PROPS"] as $key => $arItem):?>
								<option value="<?=$arItem["id"]?>" <?if($arItem["id"] == $s_prop[2]):?>selected<?endif?>><?=$arItem["name"]?></option>
								<?endforeach?>
							</select>
						</td>
						<td>
						</td>
					</tr>
				</tbody>
			</table>
			<?elseif($code == "Weight"):?>
			<table class="table <?if($s_prop):?>open<?endif?>" style="">
				<thead>
					<tr>
						<th style="width: 300px"></th>
						<th style="width: 300px">Свойство</th>
						<th style="width: max-content;">Значение</th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td>Weight</td>
						<td class="td-prop">
							<select class="form-control select_w" name="PROPS[<?=$code?>]">
								<option value="">--- Выберите сойство ---</option>
								<?foreach($arResult["PROPS"] as $key => $arItem):?>
								<option value="<?=$arItem["id"]?>" <?if($arItem["id"] == $s_prop):?>selected<?endif?>><?=$arItem["name"]?></option>
								<?
								if($arItem["id"] == $s_prop) {
								//	$checked[$code][$arItem["id"]] = true;
								}
								?>
								<?endforeach?>
							</select>
						</td>
						<td>
						</td>
					</tr>
				</tbody>
			</table>
			<?else:?>
			<table class="table <?if($s_prop):?>open<?endif?>" style="">
				<thead>
					<tr>
						<th style="width: 300px"></th>
						<th style="width: 300px">Свойство</th>
						<th style="width: max-content;">Значение</th>
					</tr>
				</thead>
				<tbody>
				<?foreach($arValues as $value):?>
					<?
					if($s_prop[$value]){
						$sel_prop = $s_prop[$value];
						//prent($sel_prop);
					}else
						$sel_prop = ["qwe" => ["dsa"]];
					$i = 0;
					?>
					<?foreach($sel_prop as $s_prop_code => $values):?>
						<?$bxProp = $arResult["PROPS"][$s_prop_code];?>
						<?//foreach($values["VALUES"] as $k => $v):

						?>
						<tr>
							<td>
								<?if($i == 0):?>
									<span class="add-variant">+</span><?=$value?> (<?=$arPropsCnt[md5($code.$value)]?>)
								<?else:?>
									<span class="remove-variant">-</span>
								<?endif?>
							</td>
							<td class="td-prop">
								<select class="form-control select_w" name="prop" data-prop="<?=addslashes($code)?>" data-value="<?=addslashes($value)?>" onchange="getVariants(this);">
									<option value="">--- Выберите сойство ---</option>
									<?foreach($arResult["PROPS"] as $key => $arItem):?>
									<option value="<?=$arItem["id"]?>" <?if($s_prop_code == $arItem["id"]):?>selected<?endif?>><?=$arItem["name"]?></option>
									<?endforeach?>
								</select>
							</td>
							<td>
								<?if(is_array($bxProp)):?>
									<?
									//prent([$bxProp]);
									?>
									<?if($bxProp["property_type"] == "L"):?>
										<?if($bxProp["is_multiple"] == "Y"):?>
											<div class="props-block">
												<p style="margin: 0 0 0 0;padding: 3px 3px 3px 10px;border: 1px solid black;">
												<input type="checkbox" name="PROPS[<?=$code?>][<?=$value?>][<?=$s_prop_code?>][INSERT_TO]" value="Y" <?if($values["INSERT_TO"] == "Y"):?>checked<?endif?>><span>Добавить к списку без перезаписи</span></p>
												<?foreach($bxProp["values"] as $_k => $_v):?>
													<p style="margin: 0 0 0 0;"><input type="checkbox" name="PROPS[<?=$code?>][<?=$value?>][<?=$s_prop_code?>][VALUES][]" value="<?=$_k?>" <?if(is_array($values["VALUES"]) && in_array($_k, $values["VALUES"])):?>checked<?endif?>><span><?=$_v?></span></p>
													<?
													if(is_array($values["VALUES"]) && in_array($_k, $values["VALUES"])) {
														$checked[$code][$value][$s_prop_code][$_k] = true;
													}
													?>
												<?endforeach?>
											</div>
										<?else:?>
											<select name="PROPS[<?=$code?>][<?=$value?>][<?=$s_prop_code?>][VALUES]" class="form-control">
												<?foreach($bxProp["values"] as $_k => $_v):?>
													<option value="<?=$_k?>" <?if($values["VALUES"] == $_k):?>selected<?endif?>><?=$_v?></option>
													<?
													if($values["VALUES"] == $_k) {
														$checked[$code][$value][$s_prop_code][$_k] = true;
													}
													?>
												<?endforeach?>
											</select>
										<?endif?>
									<?elseif($bxProp["property_type"] == "S"):?>
										<?/*if($bxProp["id"] == 'ENG_DESCRIPTION'):?>
										<input type="checkbox" name="PROPS[<?=$code?>][<?=$value?>][<?=$s_prop_code?>][INSERT_TO]" value="Y" <?if($values["INSERT_TO"] == "Y"):?>checked<?endif?>><span>Добавить в конец</span></p>
										<?endif*/?>
										<?
										if($values["VALUES"]) {
											$checked[$code][$value][$s_prop_code][$values["VALUES"]] = true;
										}
										?>
										<input name="PROPS[<?=$code?>][<?=$value?>][<?=$s_prop_code?>][VALUES]" class="form-control" value="<?=$values["VALUES"]?>">
									<?endif?>
								<?endif?>
							</td>
						</tr>
						<?//endforeach?>
						<?$i++;?>
					<?endforeach?>
				<?endforeach?>
				</tbody>
			</table>
			<?endif?>
		</div>
		<hr>
	<?endforeach?>
	<input type="submit" class="btn btn-primary btn_big_width" name="submit_profile" value="Сохранить профиль и настройки">
	<?//if($_REQUEST["submit_profile"]):?>
	<div class="form-group" style="margin-top: 20px;">
		<a href="/admin/utilities/set_prop_json/preview.php?filename_parse=<?= urlencode($filename_parse) ?>&set_profile=<?= urlencode($profile_name) ?>"
		   class="btn btn-info btn-lg">
			Показать превью изменений
		</a>
	</div>
	<?//else:?>
	<?//endif?>
<?/*	<a href="#" id="runScript"  class="btn" data-action="run">Старт</a>


	<input type="submit" class="btn btn-primary btn_big_width" name="set_props" value="Установить">
	<a href="#" id="runScript"  class="btn" data-action="run">Старт</a>
	<a href="#" id="refreshScript" class="btn" style="display: none;">Заново</a>*/?>
</form>
<?endif?>
<?
//prent($checked);
//prent($arProfile);
?>
<?php
function compareArraysDetailed($arProfile, $checked, $path = '') {
    $result = [];
    
    foreach ($arProfile as $key => $profileValue) {
        $currentPath = $path ? $path . '[' . $key . ']' : $key;
        
        // Если в profile есть VALUES, а в checked это ключ с цифрами
        if (isset($profileValue['VALUES'])) {
            $profileValues = is_array($profileValue['VALUES']) ? $profileValue['VALUES'] : [$profileValue['VALUES']];
            
            // Пытаемся найти соответствующие значения в $checked
            $checkedFound = false;
            $checkedValues = [];
            
            // Ищем по пути
            $temp = $checked;
            $pathKeys = explode('[', str_replace(']', '', $currentPath));
            $pathKeys = array_filter($pathKeys);
            
            $tempChecked = $checked;
            foreach ($pathKeys as $pk) {
                if (isset($tempChecked[$pk])) {
                    $tempChecked = $tempChecked[$pk];
                } else {
                    $tempChecked = null;
                    break;
                }
            }
            
            if ($tempChecked !== null && is_array($tempChecked)) {
                // Проверяем все значения
                foreach ($profileValues as $profileVal) {
                    $found = false;
                    foreach ($tempChecked as $checkKey => $checkVal) {
                        if ($checkKey == $profileVal || $checkVal == $profileVal) {
                            $found = true;
                            break;
                        }
                    }
                    if (!$found) {
                        $result[] = [
                            'path' => $currentPath,
                            'profile_value' => $profileVal,
                            'status' => '❌ Отсутствует в выбранных'
                        ];
                    }
                }
            } else {
                // Весь блок отсутствует в $checked
                foreach ($profileValues as $profileVal) {
                    $result[] = [
                        'path' => $currentPath,
                        'profile_value' => $profileVal,
                        'status' => '❌ Блок отсутствует в выбранных'
                    ];
                }
            }
        } 
        // Рекурсивный обход
        else if (is_array($profileValue)) {
            $subResult = compareArraysDetailed($profileValue, $checked, $currentPath);
            $result = array_merge($result, $subResult);
        }
    }
    
    return $result;
}

if ($_REQUEST['set_profile']) {
	$differences = compareArraysDetailed($arProfile, $checked);

	echo "<h3>Результаты проверки несоответствий:</h3>";
	if (empty($differences)) {
		echo "<p style='color: green; font-weight: bold;'>✅ Все значения совпадают!</p>";
	} else {
		echo "<table border='1' cellpadding='8' style='border-collapse: collapse; width: 100%;'>";
		echo "<tr style='background-color: #f2f2f2;'>";
		echo "<th>№</th>";
		echo "<th>Путь</th>";
		echo "<th>Значение из профиля</th>";
		echo "<th>Статус</th>";
		echo "</tr>";
		
		$i = 1;
		foreach ($differences as $diff) {
			echo "<tr>";
			echo "<td>" . $i++ . "</td>";
			echo "<td>" . htmlspecialchars($diff['path']) . "</td>";
			echo "<td>" . htmlspecialchars($diff['profile_value']) . "</td>";
			echo "<td style='color: red;'>" . htmlspecialchars($diff['status']) . "</td>";
			echo "</tr>";
		}
		echo "</table>";
	}
}

?>
<script>

var JSONPROPS = '<?=json_encode($arResult["PROPS"], JSON_UNESCAPED_UNICODE)?>';
var PROPS = jQuery.parseJSON( JSONPROPS );

function getVariants( obj ){
	var option = $(obj).find(":selected");
	var code = $(option).val();
	//var id = $(option).attr("data-code");

	var f_prop  = $(obj).attr("data-prop");
	var f_value  = $(obj).attr("data-value");

	var td_variants = $(obj).closest("td").next("td");

	console.log(PROPS);
	var prop_options = '';
	if( PROPS[code].values !== null )
	{
		if(PROPS[code].is_multiple == "Y"){

			prop_options = '<div class="props-block"><input type="hidden" name="PROPS[' + f_prop + '][' + f_value + '][' + PROPS[code].id + '][MULTIPLE]" value="Y">';
			prop_options += '<p style="margin: 0 0 0 0;padding: 3px 3px 3px 10px;border: 1px solid black;"><input type="checkbox" name="PROPS[' + f_prop + '][' + f_value + '][' + PROPS[code].id + '][INSERT_TO]" value="Y"><span>Добавить к списку без перезаписи</span></p>'
			for( var key in PROPS[code].values )
				prop_options += '<p style="margin: 0 0 0 0;"><input type="checkbox" name="PROPS[' + f_prop + '][' + f_value + '][' + PROPS[code].id + '][VALUES][]" value="' + key + '"><span>' + PROPS[code].values[key] + '</span></p>'
			//$('#' + id).html(prop_options);
			$(td_variants).html(prop_options);
			prop_options += '</div>';
		}else{
			for( var key in PROPS[code].values )
				prop_options += '<option value="'+ key +'">' + PROPS[code].values[key] + '</option>'
			/*$('#' + id).html(
				'<select name="PROPS[' + f_prop + '][' + f_value + '][' + PROPS[code].id + ']" class="form-control">' + prop_options + '</select>'
			);*/
			$(td_variants).html(
				'<select name="PROPS[' + f_prop + '][' + f_value + '][' + PROPS[code].id + '][VALUES]" class="form-control">' + prop_options + '</select>'
			);
		}

	}
	else
	{
		if (PROPS[code].id == 'ENG_DESCRIPTION') {
			$(td_variants).html(
				'<input type="text" name="PROPS[' + f_prop + '][' + f_value + '][' + PROPS[code].id + '][VALUES]" class="form-control" value="' + f_prop + ': ' + f_value + '">'
			);
		} else {
			$(td_variants).html(
				'<input type="text" name="PROPS[' + f_prop + '][' + f_value + '][' + PROPS[code].id + '][VALUES]" class="form-control" value="">'
			);
		}

	}
}

function showProcess (success, offset, action) {
	$('.progress-bar').text(parseFloat(success * 100).toFixed(2) + '%');
	$('.progress-bar').css('width', success * 100 + '%');

	scriptOffset(offset, action);
}

function scriptOffset (offset, action) {

	//if(action == "stop") return;
	//var action = $('#runScript').data('action');

	//var dataScript = $("#form_set_prop").serialize();
	var dataScript = "set_profile=" + $("#set_profile").val() + "&filename_parse=" + $("#filename_parse").val() + "&action=" + action + "&offset=" + offset;
	console.log(dataScript);
	$.ajax({
		url: "/admin/utilities/set_prop_json/ajax.php",
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

			if(data.success < 1) {
				showProcess(data.success, data.offset, action);
			} else {
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


	$('#form_set_prop h2').click(function() {
		$(this).next(".table").toggleClass("open");
		return false;
	});
	$('.add-variant').click(function() {
		$(this).next(".table").toggleClass("open");
		var tr = $(this).closest("tr");
		var td_prop = $(tr).find(".td-prop").html();
		var add_tr = "<tr><td><span class='remove-variant'>-</span></td><td>" + td_prop + "</td><td></td></tr>";
		$(add_tr).insertAfter(tr);
		return false;
	});

	$( "#form_set_prop" ).on( "click", '.remove-variant', function() {
		var tr = $(this).closest("tr");
		$(tr).remove();
		return false;
	});

	$('#set_profile').change(function() {
		$("#profile_name").val($(this).val());

		$("#form_set_prop").submit();
	});
});
</script>

<style>
	.table{display: none;}
	.table.open{display: inline-table;}
	#form_set_prop h2{cursor: pointer;
    font-size: 20px;
    margin: 0 0 0 0;
	}
	.props-block{
		max-height: 250px;
		overflow: auto;
	}
span.add-variant, span.remove-variant{
    padding: 4px 8px 4px 8px;
    cursor: pointer;
    border: 1px solid black;
    border-radius: 5px;
    margin: 0 7px 0 0;
}
.remove-variant{
	color:red;
	float: right;
}
.profile-block {
    margin: 0 0 15px 0;
}
</style>
<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>
