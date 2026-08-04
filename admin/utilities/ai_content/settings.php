<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");?>
<?
if (!CModule::IncludeModule('panel.manager')) {
	return;
}
$APPLICATION->SetTitle('AI content — настройки');

require_once __DIR__ . '/classes/AiContentBootstrap.php';
AiContentBootstrap::init();
(new AiContentRepository())->ensureSchema();

if (!$USER->IsAdmin()) {
	ShowError('Только администратор');
	require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");
	return;
}

$hasKey = AiContentConfig::hasKey();
$currentModel = 'gpt-4.1';
$currentProxy = AiContentConfig::getProxyRaw();
$currentProxyType = AiContentConfig::getProxyTypeRaw();
if (class_exists('COption')) {
	$currentModel = COption::GetOptionString('panel.manager', 'ai_content_openai_model', 'gpt-4.1') ?: 'gpt-4.1';
}
?>
<link rel="stylesheet" href="/admin/utilities/ai_content/css/ai_content.css">

<div class="ai-wrap">
	<p><a href="/admin/utilities/ai_content/">← К очереди</a></p>

	<div class="ai-card">
		<h2 style="margin-top:0">Настройки OpenAI</h2>
		<p class="ai-muted">
			Ключ: <?php echo $hasKey ? '<span style="color:green">задан</span>' : '<span style="color:red">не задан</span>'; ?>
			|
			Прокси: <?php echo $currentProxy !== '' ? '<span style="color:green">задан ('.htmlspecialchars($currentProxyType).')</span>' : '<span style="color:red">не задан — без него Research не заработает</span>'; ?>
		</p>

		<div class="ai-row">
			<label>OpenAI API key</label>
			<input type="password" id="api_key" class="form-control" placeholder="<?php echo $hasKey ? 'оставьте пустым, чтобы не менять' : 'sk-proj-...'; ?>" autocomplete="off">
		</div>
		<div class="ai-row">
			<label>Model</label>
			<input type="text" id="model" class="form-control" value="<?php echo htmlspecialchars($currentModel); ?>">
		</div>
		<div class="ai-row">
			<label>Proxy (обязательно для sandbox в BY/RU)</label>
			<input type="text" id="proxy" class="form-control" value="<?php echo htmlspecialchars($currentProxy); ?>" placeholder="user:pass@host:port">
			<small class="ai-muted">
				Формат: <code>user:pass@host:port</code>.
				Если провайдер пишет SOCKS5, а проверка висит — часто это на самом деле <strong>HTTP</strong>.
			</small>
		</div>
		<div class="ai-row">
			<label>Proxy type</label>
			<select id="proxy_type" class="form-control">
				<?php
				$types = array('http','socks5','socks5h','https');
				$selectedType = $currentProxyType !== '' ? $currentProxyType : 'http';
				foreach ($types as $t):
				?>
					<option value="<?php echo $t; ?>" <?php echo $selectedType === $t ? 'selected' : ''; ?>><?php echo $t; ?></option>
				<?php endforeach; ?>
			</select>
		</div>

		<button type="button" id="btn-save-settings" class="btn btn-primary">Сохранить</button>
		<button type="button" id="btn-test-proxy" class="btn btn-warning">Проверить прокси → OpenAI</button>
		<span id="settings-result" class="ai-muted" style="margin-left:10px;"></span>
	</div>
</div>

<script>
const ajax_path = '/admin/utilities/ai_content/ajax/';

function showResult(ok, text){
	$('#settings-result').css('color', ok ? 'green' : 'red').text(text);
}

$('#btn-save-settings').on('click', function(){
	showResult(true, 'сохранение…');
	$.post(ajax_path + 'saveSettings.php', {
		api_key: $('#api_key').val(),
		model: $('#model').val(),
		proxy: $('#proxy').val(),
		proxy_type: $('#proxy_type').val()
	}).done(function(res){
		if (typeof res === 'string') try { res = JSON.parse(res); } catch(e) {}
		if (!res.ok) { showResult(false, res.error || 'ошибка'); return; }
		showResult(true, 'Сохранено' + (res.has_proxy ? ' (прокси задан)' : ' (прокси пустой!)'));
	}).fail(function(xhr){
		let msg = 'ошибка сохранения';
		try { const j = JSON.parse(xhr.responseText); if (j.error) msg = j.error; } catch(e) {}
		showResult(false, msg);
	});
});

$('#btn-test-proxy').on('click', function(){
	showResult(true, 'проверка… (до ~40 сек, пробует http/socks)');
	$.ajax({
		url: ajax_path + 'testProxy.php',
		method: 'POST',
		timeout: 45000,
		data: {
			api_key: $('#api_key').val(),
			model: $('#model').val(),
			proxy: $('#proxy').val(),
			proxy_type: $('#proxy_type').val()
		}
	}).done(function(res){
		if (typeof res === 'string') try { res = JSON.parse(res); } catch(e) {}
		if (!res.ok) { showResult(false, res.error || 'fail'); return; }
		let msg = 'OK HTTP ' + res.http_code + ' | exit_ip=' + (res.exit_ip||'?') + ' | proxy=' + (res.proxy_host||'') + ' (' + (res.proxy_type||res.tried_type||'') + ')';
		if (res.note) msg += ' | ' + res.note;
		showResult(true, msg);
	}).fail(function(xhr, status){
		if (status === 'timeout') {
			showResult(false, 'Таймаут 45с. Поставь type=http и сохрани — этот прокси скорее HTTP, не SOCKS5.');
			return;
		}
		let msg = 'fail';
		try { const j = JSON.parse(xhr.responseText); if (j.error) msg = j.error; } catch(e) {}
		showResult(false, msg);
	});
});
</script>
<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>
