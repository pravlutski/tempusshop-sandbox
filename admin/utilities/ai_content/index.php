<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");?>
<?
if (!CModule::IncludeModule('panel.manager')) {
	return;
}
$APPLICATION->SetTitle('AI наполнение контента');
AccessValidator::checkIfAllowed();

require_once __DIR__ . '/classes/AiContentBootstrap.php';
AiContentBootstrap::init();
(new AiContentRepository())->ensureSchema();

$content = new CPanelContent();
$brands = $content->getBrands();
?>

<link rel="stylesheet" href="/admin/utilities/ai_content/css/ai_content.css">

<div class="ai-wrap">
	<div class="ai-head">
		<h2>AI наполнение контента</h2>
		<p class="ai-muted">Бренд + артикул → поиск в интернете → черновик → ручной апрув → товар на сайте</p>
		<p><a href="/admin/utilities/ai_content/install.php">Установка / проверка таблиц</a></p>
	</div>

	<div class="ai-card">
		<h3>Добавить модели</h3>
		<div class="ai-row">
			<label>Бренд</label>
			<select id="brand_id" class="form-control">
				<option value="">— выберите —</option>
				<? foreach ($brands as $b): ?>
					<option value="<?= (int)$b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
				<? endforeach; ?>
			</select>
		</div>
		<div class="ai-row">
			<label>Артикулы (по одному в строке)</label>
			<textarea id="articles" class="form-control" rows="5" placeholder="T137.410.11.041.00"></textarea>
		</div>
		<button id="btn-add" class="btn btn-primary">Добавить в очередь</button>
		<span id="add-result" class="ai-muted"></span>
	</div>

	<div class="ai-card">
		<div class="ai-toolbar">
			<h3 style="margin:0">Очередь</h3>
			<div>
				<button id="btn-refresh" class="btn btn-default">Обновить</button>
				<button id="btn-research-next" class="btn btn-warning">Research следующую new</button>
			</div>
		</div>
		<div id="task-table" class="ai-table-wrap">Загрузка…</div>
	</div>
</div>

<script>
const ajax_path = '/admin/utilities/ai_content/ajax/';

const STATUS_LABEL = {
	new: 'новая',
	researching: 'ищем…',
	draft: 'черновик',
	needs_review: 'на проверке',
	not_found: 'не найдено',
	ambiguous: 'несколько вариантов',
	approved: 'одобрено',
	published: 'опубликовано',
	error: 'ошибка'
};

function esc(s){
	return String(s ?? '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
}

function loadTasks(){
	$('#task-table').text('Загрузка…');
	$.getJSON(ajax_path + 'listTasks.php')
		.done(function(res){
			if (!res.ok) {
				$('#task-table').text(res.error || 'Ошибка');
				return;
			}
			if (!res.tasks.length) {
				$('#task-table').html('<p class="ai-muted">Очередь пуста</p>');
				return;
			}
			let html = '<table class="table table-striped ai-table"><thead><tr>';
			html += '<th>ID</th><th>Бренд</th><th>Артикул</th><th>Статус</th><th>Match</th><th>Товар</th><th></th>';
			html += '</tr></thead><tbody>';
			res.tasks.forEach(function(t){
				const canOpen = ['draft','needs_review','ambiguous','not_found','published','error'].indexOf(t.status) >= 0;
				html += '<tr>';
				html += '<td>' + esc(t.id) + '</td>';
				html += '<td>' + esc(t.brand_name) + '</td>';
				html += '<td><strong>' + esc(t.article) + '</strong></td>';
				html += '<td><span class="ai-badge ai-badge-' + esc(t.status) + '">' + esc(STATUS_LABEL[t.status] || t.status) + '</span></td>';
				html += '<td>' + esc(t.match_status) + '</td>';
				html += '<td>' + (t.product_id ? esc(t.product_id) : '—') + '</td>';
				html += '<td class="ai-actions">';
				html += '<button class="btn btn-xs btn-info btn-research" data-id="' + esc(t.id) + '">Research</button> ';
				if (canOpen) {
					html += '<a class="btn btn-xs btn-primary" href="/admin/utilities/ai_content/draft.php?id=' + esc(t.id) + '">Открыть</a>';
				}
				html += '</td></tr>';
			});
			html += '</tbody></table>';
			$('#task-table').html(html);
		})
		.fail(function(){
			$('#task-table').text('Не удалось загрузить очередь');
		});
}

$('#btn-add').on('click', function(){
	const brand_id = $('#brand_id').val();
	const articles = $('#articles').val();
	$('#add-result').text('…');
	$.post(ajax_path + 'addTasks.php', {brand_id, articles})
		.done(function(res){
			if (typeof res === 'string') {
				try { res = JSON.parse(res); } catch(e) { res = {ok:false, error:res}; }
			}
			if (!res.ok) {
				$('#add-result').text(res.error || 'Ошибка');
				return;
			}
			$('#add-result').text('Добавлено: ' + res.added + ', пропущено: ' + res.skipped);
			$('#articles').val('');
			loadTasks();
		})
		.fail(function(xhr){
			$('#add-result').text('Ошибка запроса');
		});
});

$('#btn-refresh').on('click', loadTasks);

function runResearch(data){
	$('#btn-research-next').prop('disabled', true).text('Research…');
	$.ajax({
		url: ajax_path + 'research.php',
		method: 'POST',
		data: data,
		timeout: 200000
	}).done(function(res){
		if (typeof res === 'string') {
			try { res = JSON.parse(res); } catch(e) { res = {ok:false, error:res}; }
		}
		if (!res.ok) {
			alert(res.error || 'Research failed');
		} else {
			alert('Готово: task #' + res.task_id + ' → ' + res.status + ' / ' + res.match_status);
		}
		loadTasks();
	}).fail(function(xhr){
		let msg = 'Research request failed';
		try {
			const j = JSON.parse(xhr.responseText);
			if (j.error) msg = j.error;
		} catch(e) {}
		alert(msg);
		loadTasks();
	}).always(function(){
		$('#btn-research-next').prop('disabled', false).text('Research следующую new');
	});
}

$('#btn-research-next').on('click', function(){
	runResearch({next: 1});
});

$(document).on('click', '.btn-research', function(){
	runResearch({task_id: $(this).data('id')});
});

loadTasks();
</script>

<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>
