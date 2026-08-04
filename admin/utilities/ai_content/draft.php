<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");?>
<?
if (!CModule::IncludeModule('panel.manager')) {
	return;
}
$APPLICATION->SetTitle('AI черновик');
AccessValidator::checkIfAllowed();

require_once __DIR__ . '/classes/AiContentBootstrap.php';
AiContentBootstrap::init();
(new AiContentRepository())->ensureSchema();

$taskId = (int)($_GET['id'] ?? 0);
if ($taskId <= 0) {
	LocalRedirect('/admin/utilities/ai_content/');
}
?>

<link rel="stylesheet" href="/admin/utilities/ai_content/css/ai_content.css">

<div class="ai-wrap">
	<p><a href="/admin/utilities/ai_content/">← К очереди</a></p>
	<div id="draft-root">Загрузка черновика #<?= $taskId ?>…</div>
</div>

<script>
const ajax_path = '/admin/utilities/ai_content/ajax/';
const taskId = <?= $taskId ?>;

const PRIORITY_PROPS = [
	'TYPE','MECHANISM','FACE','CASE','MATERIAL','GLASS','WR','COLOR','DIAL_COLOR','dial_color',
	'DIAMETER','HEIGHT','THICKNESS','CALENDAR','BACKLIGHT','FEATURES','WARRANTY','VENDORCODES'
];

function esc(s){
	return String(s ?? '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
}

function propOptions(meta, current){
	const values = meta && meta.values;
	if (!values || typeof values !== 'object') {
		return '<input class="form-control prop-input" data-code="'+esc(meta.code)+'" value="'+esc(current||'')+'">';
	}
	let opts = '<option value="">—</option>';
	const list = Array.isArray(values) ? values.map((v,i)=>({id:i,label:v})) : Object.keys(values).map(k=>{
		const v = values[k];
		if (v && typeof v === 'object') return {id:k, label: v.VALUE || v.value || v.name || JSON.stringify(v)};
		return {id:k, label: String(v)};
	});
	list.forEach(function(item){
		const selected = String(current||'') === String(item.label) || String(current||'') === String(item.id) ? ' selected' : '';
		opts += '<option value="'+esc(item.label)+'"'+selected+'>'+esc(item.label)+'</option>';
	});
	return '<select class="form-control prop-input" data-code="'+esc(meta.code)+'">'+opts+'</select>';
}

function collectPayload(){
	const props = {};
	$('.prop-input').each(function(){
		const code = $(this).data('code');
		const val = $(this).val();
		if (val) props[code] = val;
	});
	const selected = [];
	$('.photo-check:checked').each(function(){ selected.push($(this).val()); });
	return {
		task_id: taskId,
		props: props,
		selected_photos: selected,
		collection_id: $('#collection_id').val(),
		detail_text: $('#detail_text').val(),
		manual_url: $('#manual_url').val(),
		video_url: $('#video_url').val()
	};
}

function render(res){
	const t = res.task;
	const d = res.draft || {props:{}, photos:[], selected_photos:[], sources:{}, detail_text:'', manual_url:'', video_url:''};
	const fields = d.fields || {};
	const props = d.props || {};
	const selectedSet = {};
	(d.selected_photos || []).forEach(u => selectedSet[u] = true);

	let html = '';
	html += '<div class="ai-card"><div class="ai-toolbar">';
	html += '<div><h2 style="margin:0">'+esc(t.brand_name)+' '+esc(t.article)+'</h2>';
	html += '<div class="ai-muted">status: '+esc(t.status)+' / match: '+esc(t.match_status)+'</div>';
	if (t.error_text) html += '<div class="ai-note">'+esc(t.error_text)+'</div>';
	html += '</div><div>';
	html += '<button id="btn-save" class="btn btn-default">Сохранить</button> ';
	html += '<button id="btn-publish" class="btn btn-success"'+(t.status==='published'?' disabled':'')+'>Создать на сайте</button>';
	html += '</div></div></div>';

	html += '<div class="ai-grid">';

	html += '<div class="ai-card">';
	html += '<h3>Коллекция / медиа</h3>';
	html += '<div class="ai-row"><label>Коллекция</label><select id="collection_id" class="form-control">';
	html += '<option value="">— выберите —</option>';
	(res.collections||[]).forEach(function(c){
		const sel = String(fields.collection||t.collection_id||'') === String(c.id) ? ' selected' : '';
		html += '<option value="'+esc(c.id)+'"'+sel+'>'+esc(c.name)+'</option>';
	});
	html += '</select></div>';
	html += '<div class="ai-row"><label>Инструкция (PDF URL)</label><input id="manual_url" class="form-control" value="'+esc(d.manual_url||'')+'"></div>';
	html += '<div class="ai-row"><label>YouTube</label><input id="video_url" class="form-control" value="'+esc(d.video_url||'')+'"></div>';
	html += '<div class="ai-row"><label>Описание</label><textarea id="detail_text" class="form-control" rows="10">'+esc(d.detail_text||'')+'</textarea></div>';
	html += '</div>';

	html += '<div class="ai-card">';
	html += '<h3>Свойства</h3><div class="ai-props">';
	const meta = res.props_meta || {};
	const codes = PRIORITY_PROPS.filter(c => meta[c]).concat(Object.keys(props).filter(c => PRIORITY_PROPS.indexOf(c)<0));
	const uniq = [];
	codes.forEach(c => { if (uniq.indexOf(c)<0 && meta[c]) uniq.push(c); });
	uniq.forEach(function(code){
		const m = Object.assign({}, meta[code], {code: code});
		html += '<div class="ai-prop"><label>'+esc(m.name||code)+' <span class="ai-muted">'+esc(code)+'</span></label>';
		html += propOptions(m, props[code] || '');
		const src = (d.sources && d.sources.props && d.sources.props[code]) ? d.sources.props[code] : '';
		if (src) html += '<div class="ai-src"><a href="'+esc(src)+'" target="_blank" rel="noopener">источник</a></div>';
		html += '</div>';
	});
	html += '</div></div>';

	html += '</div>'; // grid

	html += '<div class="ai-card"><h3>Фото (выберите минимум 1, лучше 5–6)</h3>';
	html += '<div class="ai-photos">';
	(d.photos||[]).forEach(function(ph, idx){
		const url = ph.url || ph;
		const source = ph.source || '';
		const checked = selectedSet[url] ? ' checked' : '';
		html += '<label class="ai-photo">';
		html += '<input type="checkbox" class="photo-check" value="'+esc(url)+'"'+checked+'>';
		html += '<img src="'+esc(url)+'" alt="" loading="lazy" onerror="this.style.opacity=.2">';
		html += '<span>'+esc(source||('#'+(idx+1)))+'</span>';
		html += '</label>';
	});
	if (!(d.photos||[]).length) html += '<p class="ai-muted">Нет кандидатов фото</p>';
	html += '</div></div>';

	html += '<div class="ai-card"><h3>Лог</h3><div class="ai-log">';
	(res.logs||[]).forEach(function(l){
		html += '<div><span class="ai-muted">'+esc(l.created_at)+'</span> ['+esc(l.level)+'] '+esc(l.message)+'</div>';
	});
	html += '</div></div>';

	$('#draft-root').html(html);
}

function load(){
	$.getJSON(ajax_path + 'getDraft.php', {task_id: taskId})
		.done(function(res){
			if (!res.ok) {
				$('#draft-root').text(res.error || 'Ошибка');
				return;
			}
			render(res);
		})
		.fail(function(){
			$('#draft-root').text('Не удалось загрузить черновик');
		});
}

$(document).on('click', '#btn-save', function(){
	const payload = collectPayload();
	$.ajax({
		url: ajax_path + 'saveDraft.php',
		method: 'POST',
		contentType: 'application/json; charset=utf-8',
		data: JSON.stringify(payload)
	}).done(function(res){
		if (typeof res === 'string') try { res = JSON.parse(res); } catch(e) {}
		alert(res.ok ? 'Сохранено' : (res.error || 'Ошибка'));
		load();
	}).fail(function(xhr){
		alert('Ошибка сохранения');
	});
});

$(document).on('click', '#btn-publish', function(){
	if (!confirm('Создать товар на сайте? Это действие необратимо для артикула.')) return;
	const payload = collectPayload();
	if (!payload.collection_id) { alert('Выберите коллекцию'); return; }
	if (!payload.selected_photos.length) { alert('Выберите хотя бы одно фото'); return; }
	$('#btn-publish').prop('disabled', true).text('Публикация…');
	$.ajax({
		url: ajax_path + 'publish.php',
		method: 'POST',
		contentType: 'application/json; charset=utf-8',
		data: JSON.stringify(payload),
		timeout: 180000
	}).done(function(res){
		if (typeof res === 'string') try { res = JSON.parse(res); } catch(e) {}
		if (!res.ok) {
			alert(res.error || 'Ошибка публикации');
			$('#btn-publish').prop('disabled', false).text('Создать на сайте');
			return;
		}
		alert('Товар создан, ID ' + res.product_id);
		location.href = '/admin/utilities/ai_content/';
	}).fail(function(xhr){
		let msg = 'Ошибка публикации';
		try { const j = JSON.parse(xhr.responseText); if (j.error) msg = j.error; } catch(e) {}
		alert(msg);
		$('#btn-publish').prop('disabled', false).text('Создать на сайте');
	});
});

load();
</script>

<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>
