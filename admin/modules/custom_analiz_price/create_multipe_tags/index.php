<?php

/**
 * @global $APPLICATION
 */

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/header.php");

if(!CModule::IncludeModule('panel.manager'))
	return;

opcache_reset();

CModule::IncludeModule("iblock");

$APPLICATION->SetTitle('Массовое создание профилей | Ручной анализ цен');

?>
	<link href="/admin/modules/custom_analiz_price/style.css" rel="stylesheet">
	<h1 class="page-header">
		Массовое создание профилей
	</h1>
	<div class="col-sm-12 panel panel-default create-block-tags">
		<div class="col-sm-12">
			<h3 class="page-header">
				Основные параметры
			</h3>
		</div>
		<form action="process_create_multiple.php" method="post" enctype="multipart/form-data" id="uploadForm">
			<div class="col-sm-12 mb-15">
				<label for="profiles_list">Список профилей (каждый новый элемент с новой строки):</label>
				<textarea name="profiles_list" id="profiles_list" class="form-control" rows="5" placeholder="артикул;ставка;тип цены"></textarea>
			</div>
			<div class="col-sm-12 mb-15">
				<label for="csv_file">
					Загрузка CSV файла
				</label>
				<input class="form-control" type="file" name="csv_file" id="csv_file" accept=".csv" id="csv_file">
			</div>
			<hr>
			<div class="col-sm-12">
				<input type="submit" value="Создать все профили" class="btn btn-primary set_period">
				<a href="/admin/modules/custom_analiz_price/" class="btn btn-primary">
					Отменить
				</a>
			</div>
		</form>
	</div>
	<script>
		$(document).ready(function() {
			$('#csv_file').on('change', function() {
				let file = this.files[0];
				let reader = new FileReader();
				reader.onload = function(e) {
					let contents = e.target.result;
					let lines = contents.split('\n');

					lines.shift();
					lines.pop();

					let trimmedProfiles = lines.join('\n');

					$('#profiles_list').val(trimmedProfiles);
				};
				reader.readAsText(file);
			});
		});
	</script>
<?php

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/footer.php");