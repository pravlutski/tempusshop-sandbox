<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");?>
<?
if (!CModule::IncludeModule('panel.manager') || !CModule::IncludeModule('iblock')) {
	return;
}

$APPLICATION->SetTitle('Отправка изменений в Tempus');
AccessValidator::checkIfAllowed();

global $USER;
$arGroups = $USER->GetUserGroupArray();
if (!$USER->IsAdmin() && !in_array(6, $arGroups) && !in_array(7, $arGroups)) {
	$APPLICATION->AuthForm(GetMessage('PERMISION_DENIED'));
	return;
}

require_once(__DIR__ . '/include.php');

$iblockProps = sendTempusGetIblockProps(SEND_TEMPUS_IBLOCK_ID);
$mainFields = sendTempusMainFields();
?>

<h1 class="page-header">Отправка изменений в Tempus</h1>
<p class="text-muted">Отправка выбранных свойств и полей товаров в систему Tempus. Если заполнены и ID, и артикулы — используются ID товаров. Галка «Выгрузить все активные товары» игнорирует оба списка.</p>

<form id="send-tempus-form" class="send-tempus-form">
	<?=bitrix_sessid_post()?>
	<div class="send-tempus-all-active">
		<label for="all_active" style="font-weight: 600;">
			<input type="checkbox" id="all_active" name="all_active" value="Y">
			Выгрузить все активные товары
		</label>
	</div>
	<div class="row">
		<div class="col-sm-6">
			<label for="product_ids">ID товаров</label>
			<textarea id="product_ids" name="product_ids" class="form-control" rows="10" placeholder="По одному в строке, через запятую или пробел"></textarea>
		</div>
		<div class="col-sm-6">
			<label for="product_articles">Артикулы товаров</label>
			<textarea id="product_articles" name="product_articles" class="form-control" rows="10" placeholder="Ищем по свойству CML2_ARTICLE, если ID не указаны"></textarea>
		</div>
	</div>

	<div class="row" style="margin-top: 20px;">
		<div class="col-sm-6">
			<label for="props">Передаваемые свойства</label>
			<input type="text" id="props-filter" class="form-control" placeholder="Поиск свойства..." style="margin-bottom: 8px;">
			<div class="send-tempus-select-toolbar">
				<a href="#" class="js-select-all" data-target="props">Выбрать все</a>
				<span>·</span>
				<a href="#" class="js-select-none" data-target="props">Снять</a>
			</div>
			<select id="props" name="props[]" class="form-control" multiple size="16">
				<?foreach ($iblockProps as $code => $prop):?>
					<option value="<?=htmlspecialcharsbx($code)?>"><?=htmlspecialcharsbx($prop['NAME'])?> [<?=htmlspecialcharsbx($code)?>]</option>
				<?endforeach;?>
			</select>
			<small class="text-muted">Свойства инфоблока <?=SEND_TEMPUS_IBLOCK_ID?></small>
		</div>
		<div class="col-sm-6">
			<label for="fields">Передаваемые поля</label>
			<div class="send-tempus-select-toolbar" style="margin-top: 36px;">
				<a href="#" class="js-select-all" data-target="fields">Выбрать все</a>
				<span>·</span>
				<a href="#" class="js-select-none" data-target="fields">Снять</a>
			</div>
			<select id="fields" name="fields[]" class="form-control" multiple size="16">
				<?foreach ($mainFields as $code => $title):?>
					<option value="<?=htmlspecialcharsbx($code)?>"><?=htmlspecialcharsbx($title)?></option>
				<?endforeach;?>
			</select>
			<small class="text-muted">Дефолтные поля элемента инфоблока (SORT, NAME, DETAIL_PICTURE и др.)</small>
		</div>
	</div>

	<div style="margin-top: 20px;">
		<button type="submit" id="send-tempus-submit" class="btn btn-primary btn_big_width">Отправить</button>
	</div>
</form>

<div class="progress send-tempus-progress" style="margin-top: 16px; display: none;">
	<div class="progress-bar progress-bar-striped active" role="progressbar" style="width: 0%;">0%</div>
</div>
<div id="send-tempus-log" class="send-tempus-log"></div>

<style>
.send-tempus-form { max-width: 1100px; }
.send-tempus-form label { display: block; font-weight: 600; margin-bottom: 6px; }
.send-tempus-all-active { margin-bottom: 16px; }
.send-tempus-all-active input { margin-right: 6px; vertical-align: middle; }
.send-tempus-form textarea:disabled { background: #eee; }
.send-tempus-select-toolbar { margin-bottom: 6px; font-size: 12px; }
.send-tempus-select-toolbar a { color: #337ab7; }
.send-tempus-log {
	margin-top: 16px;
	max-width: 1100px;
	max-height: 320px;
	overflow: auto;
	background: #f5f5f5;
	border-radius: 6px;
	padding: 12px 16px;
	display: none;
	white-space: pre-wrap;
}
.send-tempus-log .error { color: #a94442; }
.send-tempus-log .ok { color: #3c763d; }
</style>

<script>
(function($) {
	var ajaxUrl = '/admin/content/send-tempus/ajax.php';

	function log(message, cls) {
		var $log = $('#send-tempus-log');
		$log.show();
		$log.append($('<div/>').addClass(cls || '').text(message));
		$log.scrollTop($log[0].scrollHeight);
	}

	function selectedValues(selectId) {
		return $('#' + selectId + ' option:selected').map(function() {
			return this.value;
		}).get();
	}

	function setProgress(current, total) {
		var pct = total > 0 ? Math.round(current / total * 100) : 0;
		$('.send-tempus-progress').show();
		$('.send-tempus-progress .progress-bar').css('width', pct + '%').text(pct + '%');
	}

	$('#props-filter').on('keyup', function() {
		var q = $.trim($(this).val()).toLowerCase();
		$('#props option').each(function() {
			var text = $(this).text().toLowerCase();
			$(this).toggle(q === '' || text.indexOf(q) !== -1);
		});
	});

	$('.js-select-all').on('click', function(e) {
		e.preventDefault();
		var id = $(this).data('target');
		$('#' + id + ' option:visible').prop('selected', true);
	});

	$('.js-select-none').on('click', function(e) {
		e.preventDefault();
		var id = $(this).data('target');
		$('#' + id + ' option').prop('selected', false);
	});

	function toggleLists() {
		var allActive = $('#all_active').prop('checked');
		$('#product_ids, #product_articles').prop('disabled', allActive);
	}
	$('#all_active').on('change', toggleLists);
	toggleLists();

	function sendChunk(chunks, index, props, fields, sessid) {
		if (index >= chunks.length) {
			setProgress(chunks.length, chunks.length);
			$('#send-tempus-submit').prop('disabled', false);
			log('Готово. Отправлено чанков: ' + chunks.length, 'ok');
			return;
		}

		$.ajax({
			url: ajaxUrl,
			type: 'POST',
			dataType: 'json',
			data: {
				action: 'send',
				sessid: sessid,
				ids: chunks[index],
				props: props,
				fields: fields
			}
		}).done(function(resp) {
			if (!resp || !resp.success) {
				$('#send-tempus-submit').prop('disabled', false);
				log((resp && resp.error) ? resp.error : 'Ошибка отправки чанка', 'error');
				return;
			}
			log('Отправлен чанк ' + (index + 1) + ' из ' + chunks.length + ' (' + resp.sent + ' шт.)', 'ok');
			setProgress(index + 1, chunks.length);
			sendChunk(chunks, index + 1, props, fields, sessid);
		}).fail(function() {
			$('#send-tempus-submit').prop('disabled', false);
			log('Ошибка сети при отправке чанка ' + (index + 1), 'error');
		});
	}

	$('#send-tempus-form').on('submit', function(e) {
		e.preventDefault();
		var $form = $(this);
		var sessid = $form.find('input[name="sessid"]').val();
		var allActive = $('#all_active').prop('checked');
		var productIds = $('#product_ids').val();
		var productArticles = $('#product_articles').val();
		var props = selectedValues('props');
		var fields = selectedValues('fields');

		$('#send-tempus-log').empty().hide();
		$('.send-tempus-progress').hide();
		setProgress(0, 1);

		if (!allActive && !$.trim(productIds) && !$.trim(productArticles)) {
			log('Укажите ID товаров или артикулы, либо включите выгрузку всех активных', 'error');
			return;
		}
		if (!props.length && !fields.length) {
			log('Выберите свойства или поля для передачи', 'error');
			return;
		}

		$('#send-tempus-submit').prop('disabled', true);
		log(allActive ? 'Собираем все активные товары...' : 'Ищем товары...');

		$.ajax({
			url: ajaxUrl,
			type: 'POST',
			dataType: 'json',
			data: {
				action: 'resolve',
				sessid: sessid,
				all_active: allActive ? 'Y' : 'N',
				product_ids: allActive ? '' : productIds,
				product_articles: allActive ? '' : productArticles,
				props: props,
				fields: fields
			}
		}).done(function(resp) {
			if (!resp || !resp.success) {
				$('#send-tempus-submit').prop('disabled', false);
				log((resp && resp.error) ? resp.error : 'Не удалось подготовить отправку', 'error');
				if (resp && resp.notFound && resp.notFound.length) {
					log('Не найдены артикулы: ' + resp.notFound.join(', '), 'error');
				}
				return;
			}

			var sourceLabel = 'артикулам';
			if (resp.source === 'ALL') {
				sourceLabel = 'всем активным';
			} else if (resp.source === 'ID') {
				sourceLabel = 'ID';
			}
			log('Найдено товаров: ' + resp.count + ' (по ' + sourceLabel + ')');
			if (resp.notFound && resp.notFound.length) {
				log('Не найдены артикулы: ' + resp.notFound.join(', '), 'error');
			}
			if (resp.props && resp.props.length) {
				log('Свойства: ' + resp.props.join(', '));
			}
			if (resp.fields && resp.fields.length) {
				log('Поля: ' + resp.fields.join(', '));
			}

			sendChunk(resp.chunks, 0, resp.props, resp.fields, sessid);
		}).fail(function() {
			$('#send-tempus-submit').prop('disabled', false);
			log('Ошибка сети при подготовке отправки', 'error');
		});
	});
})(jQuery);
</script>

<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>
