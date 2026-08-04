<?php
require __DIR__ . '/_init.php';

$taskId = (int)($_GET['task_id'] ?? $_POST['task_id'] ?? 0);
if ($taskId <= 0) {
	AiContentBootstrap::jsonResponse(['ok' => false, 'error' => 'task_id required'], 400);
}

$repo = new AiContentRepository();
$task = $repo->getTask($taskId);
if (!$task) {
	AiContentBootstrap::jsonResponse(['ok' => false, 'error' => 'Task not found'], 404);
}

$draft = $repo->getDraft($taskId);
$content = new CPanelContent();
$collections = $content->getCollections((int)$task['brand_id']);
$propsMeta = $content->getProps();
$logs = $repo->getLogs($taskId, 50);

$decoded = null;
if ($draft) {
	$decoded = [
		'props' => json_decode((string)$draft['props_json'], true) ?: [],
		'fields' => json_decode((string)$draft['fields_json'], true) ?: [],
		'detail_text' => (string)$draft['detail_text'],
		'photos' => json_decode((string)$draft['photos_json'], true) ?: [],
		'selected_photos' => json_decode((string)$draft['selected_photos_json'], true) ?: [],
		'sources' => json_decode((string)$draft['sources_json'], true) ?: [],
		'manual_url' => (string)$draft['manual_url'],
		'video_url' => (string)$draft['video_url'],
	];
}

AiContentBootstrap::jsonResponse([
	'ok' => true,
	'task' => $task,
	'draft' => $decoded,
	'collections' => $collections,
	'props_meta' => $propsMeta,
	'logs' => $logs,
]);
