<?php

class AiContentPublisher
{
	private AiContentRepository $repo;

	public function __construct(?AiContentRepository $repo = null)
	{
		$this->repo = $repo ?: new AiContentRepository();
	}

	/**
	 * Save CM edits into draft (no publish).
	 */
	public function saveDraft(int $taskId, array $input): void
	{
		$task = $this->repo->getTask($taskId);
		if (!$task) {
			throw new RuntimeException('Task not found');
		}
		$draft = $this->repo->getDraft($taskId);
		$existingPhotos = $draft ? json_decode((string)$draft['photos_json'], true) : [];
		if (!is_array($existingPhotos)) {
			$existingPhotos = [];
		}

		$props = is_array($input['props'] ?? null) ? $input['props'] : [];
		$selected = is_array($input['selected_photos'] ?? null) ? array_values(array_filter($input['selected_photos'])) : [];
		$collectionId = (int)($input['collection_id'] ?? 0) ?: null;
		$detail = (string)($input['detail_text'] ?? '');
		$manual = (string)($input['manual_url'] ?? '');
		$video = (string)($input['video_url'] ?? '');

		$fields = [
			'brand' => (int)$task['brand_id'],
			'collection' => $collectionId,
			'artnumber' => $task['article'],
			'manual' => $manual,
			'video' => $video,
		];

		$this->repo->upsertDraft($taskId, [
			'props' => $props,
			'fields' => $fields,
			'detail_text' => $detail,
			'detail_text_type' => 'html',
			'photos' => $existingPhotos,
			'selected_photos' => $selected,
			'sources' => $draft ? (json_decode((string)$draft['sources_json'], true) ?: []) : [],
			'manual_url' => $manual,
			'video_url' => $video,
			'raw_ai' => $draft ? json_decode((string)$draft['raw_ai_json'], true) : null,
		]);

		$this->repo->updateTask($taskId, [
			'status' => 'needs_review',
			'collection_id' => $collectionId,
		]);
		$this->repo->log($taskId, 'Draft saved by content manager', [
			'selected_photos' => count($selected),
			'props' => array_keys($props),
		]);
	}

	/**
	 * Create catalog product via existing content-editor pipeline (ci_application + CPanelProduct).
	 */
	public function publish(int $taskId): array
	{
		global $USER;

		$task = $this->repo->getTask($taskId);
		if (!$task) {
			throw new RuntimeException('Task not found');
		}
		$draft = $this->repo->getDraft($taskId);
		if (!$draft) {
			throw new RuntimeException('Draft not found');
		}

		$props = json_decode((string)$draft['props_json'], true) ?: [];
		$fields = json_decode((string)$draft['fields_json'], true) ?: [];
		$selected = json_decode((string)$draft['selected_photos_json'], true) ?: [];
		$selected = array_values(array_filter($selected));

		$collectionId = (int)($fields['collection'] ?? 0);
		if ($collectionId <= 0 && !empty($task['collection_id'])) {
			$collectionId = (int)$task['collection_id'];
		}
		if ($collectionId <= 0) {
			throw new RuntimeException('Выберите коллекцию перед публикацией');
		}
		if (count($selected) < 1) {
			throw new RuntimeException('Выберите минимум 1 фото');
		}
		if (CPanelProduct::findArticle($task['article'])) {
			throw new RuntimeException('Артикул уже есть в каталоге');
		}

		$fields = [
			'brand' => (int)$task['brand_id'],
			'collection' => $collectionId,
			'artnumber' => $task['article'],
			'img_watch' => $selected,
			'manual' => (string)$draft['manual_url'],
			'video' => (string)$draft['video_url'],
		];

		$cleanProps = [];
		foreach ($props as $code => $value) {
			if ($value === null || $value === '') {
				continue;
			}
			$cleanProps[$code] = $value;
		}

		$content = new CPanelContent();
		$price = method_exists($content, 'getBrandPrice')
			? (float)$content->getBrandPrice((int)$task['brand_id'])
			: 0;

		global $DB;
		$userId = ($USER && method_exists($USER, 'GetID')) ? (int)$USER->GetID() : 0;
		$appId = (int)$DB->Insert('ci_application', [
			'user_id' => "'" . $userId . "'",
			'fields' => "'" . $DB->ForSql(json_encode($fields, JSON_UNESCAPED_UNICODE)) . "'",
			'props' => "'" . $DB->ForSql(json_encode($cleanProps, JSON_UNESCAPED_UNICODE)) . "'",
			'status' => "'W'",
			'price' => "'" . $price . "'",
			'detail_text' => "'" . $DB->ForSql((string)$draft['detail_text']) . "'",
			'detail_text_type' => "'" . $DB->ForSql((string)($draft['detail_text_type'] ?: 'html')) . "'",
		]);
		if ($appId <= 0) {
			throw new RuntimeException('Не удалось сохранить заявку ci_application');
		}

		$this->repo->log($taskId, 'ci_application created', ['app_id' => $appId]);

		$product = new CPanelProduct();
		$productId = $product->addProduct($appId);
		if (!$productId) {
			throw new RuntimeException($product->LAST_ERROR ?: 'Ошибка создания товара');
		}

		$this->repo->updateTask($taskId, [
			'status' => 'published',
			'product_id' => (int)$productId,
			'collection_id' => $collectionId,
			'user_id' => $USER && method_exists($USER, 'GetID') ? (int)$USER->GetID() : null,
		]);
		$this->repo->log($taskId, 'Product published', ['product_id' => $productId, 'app_id' => $appId]);

		return [
			'ok' => true,
			'product_id' => (int)$productId,
			'app_id' => (int)$appId,
		];
	}
}
