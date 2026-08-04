<?php

class AiContentResearcher
{
	private AiContentRepository $repo;
	private OpenAiClient $client;
	private CPanelContent $content;

	public function __construct(?AiContentRepository $repo = null, ?OpenAiClient $client = null)
	{
		$this->repo = $repo ?: new AiContentRepository();
		$this->client = $client ?: new OpenAiClient();
		$this->content = new CPanelContent();
	}

	public function processTask(int $taskId): array
	{
		$task = $this->repo->getTask($taskId);
		if (!$task) {
			throw new RuntimeException('Task not found');
		}

		$this->repo->updateTask($taskId, ['status' => 'researching', 'error_text' => null]);
		$this->repo->log($taskId, 'Research started');

		try {
			$catalogProps = $this->content->getProps();
			$propSchema = $this->buildPropSchema($catalogProps);
			$collections = $this->content->getCollections((int)$task['brand_id']);
			$collectionNames = array_values(array_map(static fn($c) => $c['name'], $collections));

			$system = $this->systemPrompt();
			$user = $this->userPrompt($task, $propSchema, $collectionNames);
			$result = $this->client->researchJson($system, $user);

			if (!$result['json']) {
				throw new RuntimeException('Model did not return JSON. Raw: ' . mb_substr($result['text'], 0, 500));
			}

			$normalized = $this->normalizeResult($result['json'], $catalogProps, $collections, $task);
			$normalized['draft'] = $this->validateAndEnrichPhotos($taskId, $task, $normalized['draft']);

			$this->repo->upsertDraft($taskId, $normalized['draft']);

			$photoCount = count($normalized['draft']['photos'] ?? []);
			$note = (string)$normalized['note'];
			if ($photoCount < 1) {
				$note = trim($note . ' | Нет рабочих фото (URL битые/редирект на HTML). Нажми «Доискать фото».');
			}

			$this->repo->updateTask($taskId, [
				'status' => $normalized['status'],
				'match_status' => $normalized['match_status'],
				'collection_id' => $normalized['collection_id'],
				'error_text' => $note,
			]);

			$this->repo->log($taskId, 'Research finished', [
				'status' => $normalized['status'],
				'match_status' => $normalized['match_status'],
				'valid_photos' => $photoCount,
				'sources' => $normalized['draft']['sources'] ?? [],
			]);

			return [
				'ok' => true,
				'task_id' => $taskId,
				'status' => $normalized['status'],
				'match_status' => $normalized['match_status'],
				'valid_photos' => $photoCount,
			];
		} catch (Throwable $e) {
			$this->repo->updateTask($taskId, [
				'status' => 'error',
				'error_text' => $e->getMessage(),
			]);
			$this->repo->log($taskId, 'Research failed', ['error' => $e->getMessage()], 'error');
			throw $e;
		}
	}

	private function systemPrompt(): string
	{
		return <<<PROMPT
You are a product content researcher for a watch e-commerce catalog (Tempus Shop).
Use web_search. Prefer official brand sites, then authorized retailers, then marketplaces.
Never invent specs. If unsure, leave null and explain in notes.
Return ONLY a single JSON object (no markdown) with this shape:
{
  "match_status": "ok" | "not_found" | "ambiguous",
  "candidates": [{"title":"","url":"","why":""}],
  "collection_name": string|null,
  "props": { "PROP_CODE": "value or null", "...": "..." },
  "props_sources": { "PROP_CODE": "source_url" },
  "detail_text_official": "html or empty",
  "detail_text_generated": "html or empty",
  "photos": [
    {"url":"https...","source":"official|retailer|marketplace|other","has_watermark_suspect":false,"rank":1}
  ],
  "manual_url": "https... or null",
  "video_url": "youtube url or null",
  "notes": "short Russian note for content manager"
}
Rules for photos:
- Prefer official product images without watermarks.
- Marketplace photos allowed only if no watermark.
- Aim for 8-15 candidates, rank best first.
- Ordinary watch photos only (no infographics).
- CRITICAL: each photos[].url MUST be a direct image file URL (ends with .jpg/.jpeg/.png/.webp OR known CDN image path) that returns HTTP 200 image/* — NOT a product HTML page. Do not use URLs that 301/302 to brand homepages.
- Prefer retailer CDNs that host real files (e.g. seikoboutique.eu /.../*.jpg, brand media CDNs). Avoid dead boutique.uk paths that redirect to seikowatches.com homepage.
For enum props: values MUST be chosen from the provided allowed values list when possible.
PROMPT;
	}

	private function userPrompt(array $task, array $propSchema, array $collectionNames): string
	{
		$payload = [
			'brand' => $task['brand_name'],
			'article' => $task['article'],
			'allowed_collections' => $collectionNames,
			'properties_to_fill' => $propSchema,
			'locale' => 'ru',
			'instructions' => [
				'Search official brand site first for this exact model/article.',
				'Fill as many properties as possible using allowed enum values.',
				'If official description exists, put it into detail_text_official.',
				'Otherwise write a concise Russian HTML description into detail_text_generated (2-4 short paragraphs, no hype).',
				'Also find manual PDF and YouTube review/official video if available.',
			],
		];
		return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
	}

	private function buildPropSchema(array $catalogProps): array
	{
		$codes = AiContentConfig::researchPropCodes();
		$out = [];
		foreach ($codes as $code) {
			if (!isset($catalogProps[$code])) {
				// try dial_color alias
				if ($code === 'DIAL_COLOR' && isset($catalogProps['dial_color'])) {
					$code = 'dial_color';
				} else {
					continue;
				}
			}
			$p = $catalogProps[$code];
			$item = [
				'code' => $code,
				'name' => $p['name'],
				'type' => $p['property_type'],
				'multiple' => $p['is_multiple'],
			];
			if (!empty($p['values']) && is_array($p['values'])) {
				$item['allowed_values'] = array_values($p['values']);
			}
			$out[] = $item;
		}
		return $out;
	}

	private function normalizeResult(array $json, array $catalogProps, array $collections, array $task): array
	{
		$match = (string)($json['match_status'] ?? 'not_found');
		if (!in_array($match, ['ok', 'not_found', 'ambiguous'], true)) {
			$match = 'not_found';
		}

		$status = 'needs_review';
		if ($match === 'not_found') {
			$status = 'not_found';
		} elseif ($match === 'ambiguous') {
			$status = 'ambiguous';
		} else {
			$status = 'draft';
		}

		$propsIn = is_array($json['props'] ?? null) ? $json['props'] : [];
		$propsOut = [];
		$sources = [
			'props' => is_array($json['props_sources'] ?? null) ? $json['props_sources'] : [],
			'candidates' => is_array($json['candidates'] ?? null) ? $json['candidates'] : [],
			'notes' => (string)($json['notes'] ?? ''),
		];

		foreach ($propsIn as $code => $value) {
			$code = (string)$code;
			if ($value === null || $value === '') {
				continue;
			}
			$resolvedCode = $this->resolvePropCode($code, $catalogProps);
			if (!$resolvedCode) {
				continue;
			}
			$mapped = $this->mapPropValue($resolvedCode, $value, $catalogProps[$resolvedCode]);
			if ($mapped !== null && $mapped !== '') {
				$propsOut[$resolvedCode] = $mapped;
			}
		}

		$collectionId = null;
		$collectionName = trim((string)($json['collection_name'] ?? ''));
		if ($collectionName !== '') {
			foreach ($collections as $c) {
				if (mb_strtolower($c['name']) === mb_strtolower($collectionName)) {
					$collectionId = (int)$c['id'];
					break;
				}
			}
			// fuzzy contains
			if (!$collectionId) {
				foreach ($collections as $c) {
					if (mb_stripos($c['name'], $collectionName) !== false || mb_stripos($collectionName, $c['name']) !== false) {
						$collectionId = (int)$c['id'];
						break;
					}
				}
			}
		}
		if (!$collectionId && count($collections) === 1) {
			$collectionId = (int)$collections[0]['id'];
		}

		$official = trim((string)($json['detail_text_official'] ?? ''));
		$generated = trim((string)($json['detail_text_generated'] ?? ''));
		$detail = $official !== '' ? $official : $generated;

		$photos = [];
		foreach ((array)($json['photos'] ?? []) as $ph) {
			$url = '';
			$source = 'other';
			$rank = 99;
			$watermark = false;
			if (is_string($ph)) {
				$url = trim($ph);
			} elseif (is_array($ph)) {
				$url = trim((string)($ph['url'] ?? $ph['src'] ?? $ph['image'] ?? $ph['link'] ?? ''));
				$source = (string)($ph['source'] ?? 'other');
				$rank = (int)($ph['rank'] ?? 99);
				$watermark = !empty($ph['has_watermark_suspect']);
			}
			// strip markdown / wrappers: ![](url) or <url>
			if (preg_match('#https?://[^\s\)\"\']+#i', $url, $m)) {
				$url = $m[0];
			}
			$url = rtrim($url, '.,;)');
			if (!filter_var($url, FILTER_VALIDATE_URL)) {
				continue;
			}
			if ($watermark) {
				continue;
			}
			$photos[] = [
				'url' => $url,
				'source' => $source,
				'rank' => $rank,
			];
		}
		usort($photos, static fn($a, $b) => $a['rank'] <=> $b['rank']);
		// prefer official first among same rank
		usort($photos, static function ($a, $b) {
			$prio = ['official' => 0, 'retailer' => 1, 'other' => 2, 'marketplace' => 3];
			$pa = $prio[$a['source']] ?? 5;
			$pb = $prio[$b['source']] ?? 5;
			if ($pa === $pb) {
				return $a['rank'] <=> $b['rank'];
			}
			return $pa <=> $pb;
		});

		$selected = array_slice(array_column($photos, 'url'), 0, 6);

		$draft = [
			'props' => $propsOut,
			'fields' => [
				'brand' => (int)$task['brand_id'],
				'collection' => $collectionId,
				'artnumber' => $task['article'],
				'manual' => (string)($json['manual_url'] ?? ''),
				'video' => (string)($json['video_url'] ?? ''),
			],
			'detail_text' => $detail,
			'detail_text_type' => 'html',
			'photos' => $photos,
			'selected_photos' => $selected,
			'sources' => $sources,
			'manual_url' => (string)($json['manual_url'] ?? ''),
			'video_url' => (string)($json['video_url'] ?? ''),
			'raw_ai' => $json,
		];

		return [
			'status' => $status,
			'match_status' => $match,
			'collection_id' => $collectionId,
			'note' => (string)($json['notes'] ?? ''),
			'draft' => $draft,
		];
	}

	/**
	 * Download-check photo URLs; keep only real images; cache locally for draft preview.
	 * If almost none valid — second AI pass for working direct image URLs.
	 */
	private function validateAndEnrichPhotos(int $taskId, array $task, array $draft): array
	{
		$fetcher = new AiContentImageFetcher();
		$photos = is_array($draft['photos'] ?? null) ? $draft['photos'] : [];
		$before = count($photos);
		$valid = $fetcher->filterValidPhotos($photos, 12);
		$this->repo->log($taskId, 'Photo validation', [
			'before' => $before,
			'after' => count($valid),
		]);

		if (count($valid) < 2) {
			$this->repo->log($taskId, 'Photo re-search started');
			try {
				$failedUrls = [];
				foreach ($photos as $ph) {
					if (is_array($ph) && !empty($ph['url'])) {
						$failedUrls[] = (string)$ph['url'];
					}
				}
				$extra = $this->researchPhotosOnly($task, $failedUrls);
				if ($extra) {
					$merged = array_merge($valid, $fetcher->filterValidPhotos($extra, 12));
					$by = [];
					foreach ($merged as $ph) {
						$by[$ph['url']] = $ph;
					}
					$valid = array_values($by);
					$this->repo->log($taskId, 'Photo re-search done', ['valid' => count($valid)]);
				}
			} catch (Throwable $e) {
				$this->repo->log($taskId, 'Photo re-search skipped', ['error' => $e->getMessage()], 'error');
			}
		}

		$draft['photos'] = $valid;
		$draft['selected_photos'] = array_slice(array_map(static function ($ph) {
			return !empty($ph['local_url']) ? $ph['local_url'] : $ph['url'];
		}, $valid), 0, 6);

		// Prefer local cached files for publish too when available
		return $draft;
	}

	public function refreshPhotos(int $taskId): array
	{
		$task = $this->repo->getTask($taskId);
		if (!$task) {
			throw new RuntimeException('Task not found');
		}
		$draftRow = $this->repo->getDraft($taskId);
		if (!$draftRow) {
			throw new RuntimeException('Draft not found — сначала Research');
		}
		$draft = [
			'props' => json_decode((string)$draftRow['props_json'], true) ?: [],
			'fields' => json_decode((string)$draftRow['fields_json'], true) ?: [],
			'detail_text' => (string)$draftRow['detail_text'],
			'detail_text_type' => (string)($draftRow['detail_text_type'] ?: 'html'),
			'photos' => json_decode((string)$draftRow['photos_json'], true) ?: [],
			'selected_photos' => json_decode((string)$draftRow['selected_photos_json'], true) ?: [],
			'sources' => json_decode((string)$draftRow['sources_json'], true) ?: [],
			'manual_url' => (string)$draftRow['manual_url'],
			'video_url' => (string)$draftRow['video_url'],
			'raw_ai' => json_decode((string)$draftRow['raw_ai_json'], true),
		];

		$failed = [];
		foreach ($draft['photos'] as $ph) {
			if (is_array($ph) && !empty($ph['url'])) {
				$failed[] = (string)$ph['url'];
			} elseif (is_string($ph)) {
				$failed[] = $ph;
			}
		}

		$aiError = null;
		try {
			$extra = $this->researchPhotosOnly($task, $failed);
		} catch (Throwable $e) {
			$aiError = $e->getMessage();
			$extra = [];
			$this->repo->log($taskId, 'Photo re-search AI error', ['error' => $aiError], 'error');
		}

		if ($extra) {
			$draft['photos'] = array_merge($draft['photos'], $extra);
		}

		// Validate once (do not trigger nested AI search again)
		$fetcher = new AiContentImageFetcher();
		$before = count($draft['photos']);
		$valid = $fetcher->filterValidPhotos($draft['photos'], 12);
		$this->repo->log($taskId, 'Photo refresh validation', [
			'before' => $before,
			'ai_extra' => count($extra),
			'after' => count($valid),
			'ai_error' => $aiError,
		]);

		$draft['photos'] = $valid;
		$draft['selected_photos'] = array_slice(array_map(static function ($ph) {
			return !empty($ph['local_url']) ? $ph['local_url'] : $ph['url'];
		}, $valid), 0, 6);

		$this->repo->upsertDraft($taskId, $draft);
		$this->repo->updateTask($taskId, [
			'status' => 'needs_review',
			'error_text' => count($valid)
				? ('Фото обновлены: ' . count($valid) . ' шт.')
				: ('Рабочих фото нет' . ($aiError ? ('. AI: ' . $aiError) : '. Попробуй другие источники / вставь URL вручную')),
		]);

		if (!$valid && $aiError) {
			throw new RuntimeException('AI поиск фото: ' . $aiError);
		}
		if (!$valid) {
			throw new RuntimeException('AI вернул ссылки, но ни одна не скачалась как картинка. Вставь URL вручную или повтори позже.');
		}

		return [
			'ok' => true,
			'photos' => count($valid),
			'ai_extra' => count($extra),
		];
	}

	private function researchPhotosOnly(array $task, array $failedUrls = []): array
	{
		$system = <<<PROMPT
You find WORKING direct product image URLs for watches.
Use web_search. Return ONLY JSON:
{"photos":[{"url":"https://...jpg","source":"official|retailer|marketplace","rank":1}]}
Rules:
- URLs must be direct image files that return image/* (jpg/png/webp).
- Do NOT return HTML product pages or URLs that redirect to homepages.
- Prefer large EU/US retailers and brand media CDNs with stable hotlinkable images.
- Avoid seikoboutique.co.uk/_images paths that redirect to seikowatches.com.
- Avoid URLs that require login.
- 6-12 photos of the exact model.
PROMPT;
		$user = json_encode([
			'brand' => $task['brand_name'] ?? '',
			'article' => $task['article'] ?? '',
			'failed_urls_do_not_repeat' => array_values(array_filter($failedUrls)),
			'hint' => 'Open product pages and extract real image CDN links (og:image, gallery src).',
		], JSON_UNESCAPED_UNICODE);

		$result = $this->client->researchJson($system, $user);
		$json = $result['json'] ?? null;
		if (!is_array($json)) {
			throw new RuntimeException('Модель не вернула JSON с photos. Raw: ' . mb_substr((string)($result['text'] ?? ''), 0, 300));
		}
		$photos = [];
		foreach ((array)($json['photos'] ?? []) as $ph) {
			$url = is_array($ph) ? trim((string)($ph['url'] ?? '')) : trim((string)$ph);
			if (preg_match('#https?://[^\s\)\"\']+#i', $url, $m)) {
				$url = rtrim($m[0], '.,;)');
			}
			if (!filter_var($url, FILTER_VALIDATE_URL)) {
				continue;
			}
			$photos[] = [
				'url' => $url,
				'source' => is_array($ph) ? (string)($ph['source'] ?? 'retailer') : 'retailer',
				'rank' => is_array($ph) ? (int)($ph['rank'] ?? 50) : 50,
			];
		}
		if (!$photos) {
			throw new RuntimeException('AI не нашёл ни одного photo URL');
		}
		return $photos;
	}

	private function resolvePropCode(string $code, array $catalogProps): ?string
	{
		if (isset($catalogProps[$code])) {
			return $code;
		}
		$aliases = [
			'dial_color' => 'DIAL_COLOR',
			'DIAL_COLOR' => 'dial_color',
			'THIKNESS' => 'THICKNESS',
		];
		if (isset($aliases[$code]) && isset($catalogProps[$aliases[$code]])) {
			return $aliases[$code];
		}
		foreach ($catalogProps as $k => $_) {
			if (strcasecmp($k, $code) === 0) {
				return $k;
			}
		}
		return null;
	}

	private function mapPropValue(string $code, $value, array $propMeta)
	{
		if (is_array($value)) {
			$value = implode(', ', array_map('strval', $value));
		}
		$value = trim((string)$value);
		if ($value === '') {
			return null;
		}

		$allowed = $propMeta['values'] ?? null;
		if (!is_array($allowed) || !$allowed) {
			return $value;
		}

		// values may be id=>label or list of labels
		$labels = [];
		foreach ($allowed as $k => $v) {
			if (is_array($v)) {
				$label = (string)($v['VALUE'] ?? $v['value'] ?? $v['name'] ?? reset($v));
				$id = $v['ID'] ?? $v['id'] ?? $k;
			} else {
				$label = (string)$v;
				$id = $k;
			}
			$labels[] = ['id' => $id, 'label' => $label];
			if (mb_strtolower($label) === mb_strtolower($value) || (string)$id === $value) {
				// content editor stores human labels for many enums; keep label if string keys
				return is_numeric($id) && !is_string($k) ? $label : $label;
			}
		}

		foreach ($labels as $item) {
			if (mb_stripos($item['label'], $value) !== false || mb_stripos($value, $item['label']) !== false) {
				return $item['label'];
			}
		}

		// FEATURES etc may be free-ish multiple — keep raw if multiple
		if (($propMeta['is_multiple'] ?? 'N') === 'Y') {
			return $value;
		}

		return $value;
	}
}
