<?php
require __DIR__ . '/_init.php';

$repo = new AiContentRepository();
$tasks = $repo->listTasks(150);

AiContentBootstrap::jsonResponse(['ok' => true, 'tasks' => $tasks]);
