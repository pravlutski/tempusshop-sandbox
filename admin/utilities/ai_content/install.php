<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");?>
<?
if (!CModule::IncludeModule('panel.manager')) {
	return;
}
$APPLICATION->SetTitle('AI content — установка');
if (!$USER->IsAdmin()) {
	ShowError('Только администратор');
	require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");
	return;
}

require_once __DIR__ . '/classes/AiContentBootstrap.php';
AiContentBootstrap::init();

$repo = new AiContentRepository();
$repo->ensureSchema();
$reg = $repo->registerUtility();

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_bitrix_sessid()) {
	$key = trim((string)($_POST['api_key'] ?? ''));
	$model = trim((string)($_POST['model'] ?? 'gpt-4.1'));
	if ($key !== '') {
		AiContentConfig::save($key, $model ?: 'gpt-4.1');
		$msg = 'OpenAI ключ сохранён в настройках Bitrix (переживает git deploy).';
		$repo->log(null, 'OpenAI key saved via install');
	}
}

$hasKey = AiContentConfig::hasKey();
$currentModel = 'gpt-4.1';
if (class_exists('COption')) {
	$currentModel = COption::GetOptionString('panel.manager', 'ai_content_openai_model', 'gpt-4.1') ?: 'gpt-4.1';
}
?>
<div style="max-width:720px;margin:20px 0;">
	<h2>Установка AI наполнения контента</h2>
	<? if ($msg): ?><p style="color:green"><?= htmlspecialchars($msg) ?></p><? endif; ?>

	<p>Таблицы <code>ai_content_task</code>, <code>ai_content_draft</code>, <code>ai_content_log</code> созданы/проверены.</p>
	<p>
		Утилита в списке:
		<? if ($reg['created']): ?>
			<strong>добавлена</strong> (id <?= (int)$reg['id'] ?>)
		<? else: ?>
			<strong>уже была</strong> (id <?= (int)$reg['id'] ?>)
		<? endif; ?>
	</p>
	<p>
		OpenAI ключ:
		<? if ($hasKey): ?>
			<span style="color:green">задан</span>
		<? else: ?>
			<span style="color:red">не задан</span>
		<? endif; ?>
	</p>

	<form method="post" style="margin:18px 0;padding:14px;border:1px solid #ddd;border-radius:8px;">
		<?= bitrix_sessid_post() ?>
		<div style="margin-bottom:10px;">
			<label><strong>OpenAI API key</strong></label>
			<input type="password" name="api_key" class="form-control" style="width:100%" placeholder="sk-proj-..." autocomplete="off">
		</div>
		<div style="margin-bottom:10px;">
			<label><strong>Model</strong></label>
			<input type="text" name="model" class="form-control" style="width:100%" value="<?= htmlspecialchars($currentModel) ?>">
		</div>
		<button type="submit" class="btn btn-primary">Сохранить ключ</button>
	</form>

	<p><a class="btn btn-success" href="/admin/utilities/ai_content/">Открыть утилиту</a></p>
</div>
<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>
