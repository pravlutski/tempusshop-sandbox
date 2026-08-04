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
			$this->repo->upsertDraft($taskId, $normalized['draft']);

			$this->repo->updateTask($taskId, [
				'status' => $normalized['status'],
				'match_status' => $normalized['match_status'],
				'collection_id' => $normalized['collection_id'],
				'error_text' => $normalized['note'],
			]);

			$this->repo->log($taskId, 'Research finished', [
				'status' => $normalized['status'],
				'match_status' => $normalized['match_status'],
				'sources' => $normalized['draft']['sources'] ?? [],
			]);

			return [
				'ok' => true,
				'task_id' => $taskId,
				'status' => $normalized['status'],
				'match_status' => $normalized['match_status'],
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
