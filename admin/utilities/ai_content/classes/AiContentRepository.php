<?php

class AiContentRepository
{
	public function ensureSchema(): void
	{
		global $DB;

		$DB->Query("
			CREATE TABLE IF NOT EXISTS ai_content_task (
				id INT UNSIGNED NOT NULL AUTO_INCREMENT,
				brand_id INT UNSIGNED NOT NULL,
				brand_name VARCHAR(255) NOT NULL DEFAULT '',
				article VARCHAR(128) NOT NULL,
				status VARCHAR(32) NOT NULL DEFAULT 'new',
				match_status VARCHAR(32) NOT NULL DEFAULT 'unknown',
				collection_id INT UNSIGNED NULL,
				product_id INT UNSIGNED NULL,
				user_id INT UNSIGNED NULL,
				error_text TEXT NULL,
				created_at DATETIME NOT NULL,
				updated_at DATETIME NOT NULL,
				PRIMARY KEY (id),
				KEY status (status),
				KEY article (article),
				KEY brand_id (brand_id)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
		", true);

		$DB->Query("
			CREATE TABLE IF NOT EXISTS ai_content_draft (
				id INT UNSIGNED NOT NULL AUTO_INCREMENT,
				task_id INT UNSIGNED NOT NULL,
				props_json LONGTEXT NULL,
				fields_json LONGTEXT NULL,
				detail_text LONGTEXT NULL,
				detail_text_type VARCHAR(16) NOT NULL DEFAULT 'html',
				photos_json LONGTEXT NULL,
				selected_photos_json LONGTEXT NULL,
				sources_json LONGTEXT NULL,
				manual_url VARCHAR(1024) NULL,
				video_url VARCHAR(1024) NULL,
				raw_ai_json LONGTEXT NULL,
				updated_at DATETIME NOT NULL,
				PRIMARY KEY (id),
				UNIQUE KEY task_id (task_id)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
		", true);

		$DB->Query("
			CREATE TABLE IF NOT EXISTS ai_content_log (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				task_id INT UNSIGNED NULL,
				level VARCHAR(16) NOT NULL DEFAULT 'info',
				message VARCHAR(512) NOT NULL,
				context_json LONGTEXT NULL,
				created_at DATETIME NOT NULL,
				PRIMARY KEY (id),
				KEY task_id (task_id)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
		", true);
	}

	public function registerUtility(): array
	{
		global $DB;
		$link = '/admin/utilities/ai_content/';
		$name = 'AI наполнение контента';

		$rs = $DB->Query("SELECT id FROM admin_utilities_list WHERE link = '" . $DB->ForSql($link) . "' LIMIT 1");
		if ($row = $rs->Fetch()) {
			return ['created' => false, 'id' => (int)$row['id']];
		}

		$groupId = 0;
		$gr = $DB->Query("SELECT id FROM admin_utilities_groups ORDER BY id ASC LIMIT 1");
		if ($g = $gr->Fetch()) {
			$groupId = (int)$g['id'];
		}

		$id = (int)$DB->Insert('admin_utilities_list', [
			'name' => "'" . $DB->ForSql($name) . "'",
			'link' => "'" . $DB->ForSql($link) . "'",
			'group_id' => "'" . $groupId . "'",
		]);

		// Admins always pass AccessValidator; empty ACL also allows.
		return ['created' => true, 'id' => $id];
	}

	public function log(?int $taskId, string $message, array $context = [], string $level = 'info'): void
	{
		global $DB;
		$DB->Insert('ai_content_log', [
			'task_id' => $taskId ? "'" . (int)$taskId . "'" : 'NULL',
			'level' => "'" . $DB->ForSql($level) . "'",
			'message' => "'" . $DB->ForSql(mb_substr($message, 0, 500)) . "'",
			'context_json' => "'" . $DB->ForSql(json_encode($context, JSON_UNESCAPED_UNICODE)) . "'",
			'created_at' => "'" . date('Y-m-d H:i:s') . "'",
		]);
	}

	public function addTasks(int $brandId, string $brandName, array $articles, ?int $userId): array
	{
		global $DB;
		$now = date('Y-m-d H:i:s');
		$added = 0;
		$skipped = 0;
		$ids = [];

		foreach ($articles as $article) {
			$article = trim((string)$article);
			if ($article === '') {
				continue;
			}
			if (CPanelProduct::findArticle($article)) {
				$skipped++;
				$this->log(null, 'Skip: already in catalog', ['article' => $article]);
				continue;
			}
			$exists = $DB->Query("
				SELECT id FROM ai_content_task
				WHERE article = '" . $DB->ForSql($article) . "'
				  AND brand_id = " . (int)$brandId . "
				  AND status NOT IN ('published')
				LIMIT 1
			");
			if ($exists->Fetch()) {
				$skipped++;
				continue;
			}

			$id = (int)$DB->Insert('ai_content_task', [
				'brand_id' => "'" . (int)$brandId . "'",
				'brand_name' => "'" . $DB->ForSql($brandName) . "'",
				'article' => "'" . $DB->ForSql($article) . "'",
				'status' => "'new'",
				'match_status' => "'unknown'",
				'user_id' => $userId ? "'" . (int)$userId . "'" : 'NULL',
				'created_at' => "'" . $now . "'",
				'updated_at' => "'" . $now . "'",
			]);
			if ($id > 0) {
				$added++;
				$ids[] = $id;
				$this->log($id, 'Task created', ['brand_id' => $brandId, 'article' => $article]);
			}
		}

		return compact('added', 'skipped', 'ids');
	}

	public function listTasks(int $limit = 100): array
	{
		global $DB;
		$limit = max(1, min(500, $limit));
		$rs = $DB->Query("SELECT * FROM ai_content_task ORDER BY id DESC LIMIT {$limit}");
		$out = [];
		while ($row = $rs->Fetch()) {
			$out[] = $row;
		}
		return $out;
	}

	public function getTask(int $id): ?array
	{
		global $DB;
		$rs = $DB->Query("SELECT * FROM ai_content_task WHERE id = " . (int)$id . " LIMIT 1");
		$row = $rs->Fetch();
		return $row ?: null;
	}

	public function updateTask(int $id, array $fields): void
	{
		global $DB;
		$set = [];
		foreach ($fields as $k => $v) {
			if ($v === null) {
				$set[$k] = 'NULL';
			} else {
				$set[$k] = "'" . $DB->ForSql((string)$v) . "'";
			}
		}
		$set['updated_at'] = "'" . date('Y-m-d H:i:s') . "'";
		$DB->Update('ai_content_task', $set, 'WHERE id=' . (int)$id);
	}

	public function getDraft(int $taskId): ?array
	{
		global $DB;
		$rs = $DB->Query("SELECT * FROM ai_content_draft WHERE task_id = " . (int)$taskId . " LIMIT 1");
		$row = $rs->Fetch();
		return $row ?: null;
	}

	public function upsertDraft(int $taskId, array $data): void
	{
		global $DB;
		$now = date('Y-m-d H:i:s');
		$existing = $this->getDraft($taskId);
		$payload = [
			'props_json' => "'" . $DB->ForSql(json_encode($data['props'] ?? [], JSON_UNESCAPED_UNICODE)) . "'",
			'fields_json' => "'" . $DB->ForSql(json_encode($data['fields'] ?? [], JSON_UNESCAPED_UNICODE)) . "'",
			'detail_text' => "'" . $DB->ForSql((string)($data['detail_text'] ?? '')) . "'",
			'detail_text_type' => "'" . $DB->ForSql((string)($data['detail_text_type'] ?? 'html')) . "'",
			'photos_json' => "'" . $DB->ForSql(json_encode($data['photos'] ?? [], JSON_UNESCAPED_UNICODE)) . "'",
			'selected_photos_json' => "'" . $DB->ForSql(json_encode($data['selected_photos'] ?? [], JSON_UNESCAPED_UNICODE)) . "'",
			'sources_json' => "'" . $DB->ForSql(json_encode($data['sources'] ?? [], JSON_UNESCAPED_UNICODE)) . "'",
			'manual_url' => "'" . $DB->ForSql((string)($data['manual_url'] ?? '')) . "'",
			'video_url' => "'" . $DB->ForSql((string)($data['video_url'] ?? '')) . "'",
			'raw_ai_json' => "'" . $DB->ForSql(json_encode($data['raw_ai'] ?? null, JSON_UNESCAPED_UNICODE)) . "'",
			'updated_at' => "'" . $now . "'",
		];

		if ($existing) {
			$DB->Update('ai_content_draft', $payload, 'WHERE task_id=' . (int)$taskId);
		} else {
			$payload['task_id'] = "'" . (int)$taskId . "'";
			$DB->Insert('ai_content_draft', $payload);
		}
	}

	public function getLogs(int $taskId, int $limit = 100): array
	{
		global $DB;
		$limit = max(1, min(500, $limit));
		$rs = $DB->Query("
			SELECT * FROM ai_content_log
			WHERE task_id = " . (int)$taskId . "
			ORDER BY id DESC
			LIMIT {$limit}
		");
		$out = [];
		while ($row = $rs->Fetch()) {
			$out[] = $row;
		}
		return $out;
	}

	public function nextNewTaskId(): ?int
	{
		global $DB;
		$rs = $DB->Query("SELECT id FROM ai_content_task WHERE status = 'new' ORDER BY id ASC LIMIT 1");
		$row = $rs->Fetch();
		return $row ? (int)$row['id'] : null;
	}
}
