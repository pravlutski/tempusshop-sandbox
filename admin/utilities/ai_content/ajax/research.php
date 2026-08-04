<?php
require __DIR__ . '/_init.php';
AiContentBootstrap::requirePost();

@set_time_limit(240);

$taskId = (int)($_POST['task_id'] ?? 0);
$next = !empty($_POST['next']);

$repo = new AiContentRepository();
if ($next && $taskId <= 0) {
	$taskId = (int)$repo->nextNewTaskId();
}
if ($taskId <= 0) {
	AiContentBootstrap::jsonResponse(['ok' => false, 'error' => 'Нет задачи для research'], 400);
}

try {
	$researcher = new AiContentResearcher($repo);
	$result = $researcher->processTask($taskId);
	AiContentBootstrap::jsonResponse($result);
} catch (Throwable $e) {
	AiContentBootstrap::jsonResponse(['ok' => false, 'error' => $e->getMessage(), 'task_id' => $taskId], 500);
}
