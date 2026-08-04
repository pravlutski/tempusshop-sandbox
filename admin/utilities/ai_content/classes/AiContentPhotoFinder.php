<?php

/**
 * Finds downloadable product photos when AI direct URLs are hotlink-protected.
 * Strategy: DuckDuckGo → product pages (via HTTP proxy) → extract CDN image URLs → download.
 */
class AiContentPhotoFinder
{
	private AiContentImageFetcher $fetcher;

	public function __construct(?AiContentImageFetcher $fetcher = null)
	{
		$this->fetcher = $fetcher ?: new AiContentImageFetcher();
	}

	public function find(string $brand, string $article, int $limit = 10): array
	{
		$brand = trim($brand);
		// ci_section names look like "Наручные часы / Seiko"
		if (str_contains($brand, '/')) {
			$parts = array_map('trim', explode('/', $brand));
			$brand = (string)end($parts);
		}
		$article = trim($article);
		if ($article === '') {
			return [];
		}

		$pages = $this->searchPages($brand, $article);
		// Prefer known-good shops first
		usort($pages, static function ($a, $b) {
			return self::pageScore($b) <=> self::pageScore($a);
		});

		$candidates = [];
		foreach (array_slice($pages, 0, 8) as $pageUrl) {
			foreach ($this->extractImagesFromPage($pageUrl, $article) as $img) {
				$candidates[$img['url']] = $img;
			}
			if (count($candidates) >= 30) {
				break;
			}
		}

		if (!$candidates) {
			return [];
		}

		// Rank: shopify/jomashop CDNs first
		$list = array_values($candidates);
		usort($list, static function ($a, $b) {
			return self::imageScore($b['url']) <=> self::imageScore($a['url']);
		});

		return $this->fetcher->filterValidPhotos($list, $limit);
	}

	private function searchPages(string $brand, string $article): array
	{
		$q = $brand . ' ' . $article . ' watch';
		$url = 'https://html.duckduckgo.com/html/?q=' . rawurlencode($q);
		$html = $this->httpGet($url);
		$pages = [];
		if ($html !== '') {
			if (preg_match_all('#uddg=([^&\"\']+)#', $html, $m)) {
				foreach ($m[1] as $enc) {
					$u = urldecode($enc);
					if (filter_var($u, FILTER_VALIDATE_URL)) {
						$pages[] = $u;
					}
				}
			}
			if (preg_match_all('#href=\"(https?://[^\"]+)\"#', $html, $m2)) {
				foreach ($m2[1] as $u) {
					if (str_contains($u, 'duckduckgo.com')) {
						continue;
					}
					if (filter_var($u, FILTER_VALIDATE_URL)) {
						$pages[] = $u;
					}
				}
			}
		}

		// Seed known patterns that often expose JSON/CDN
		$art = strtolower($article);
		$pages[] = 'https://shop.seikoboutique.com.ph/products/classic-' . $art . '.json';
		$pages[] = 'https://shop.seikoboutique.com.ph/products/' . $art . '.json';
		$pages[] = 'https://www.jomashop.com/seiko-essentials-quartz-crystal-white-dial-ladies-watch-' . $art . '.html';
		// DDG site-scoped hints
		foreach ([
			'site:jomashop.com ' . $brand . ' ' . $article,
			'site:cdn.shopify.com ' . $article,
		] as $extraQ) {
			$html2 = $this->httpGet('https://html.duckduckgo.com/html/?q=' . rawurlencode($extraQ));
			if ($html2 && preg_match_all('#uddg=([^&\"\']+)#', $html2, $mm)) {
				foreach ($mm[1] as $enc) {
					$u = urldecode($enc);
					if (filter_var($u, FILTER_VALIDATE_URL)) {
						$pages[] = $u;
					}
				}
			}
		}

		return array_values(array_unique(array_filter($pages)));
	}

	private function extractImagesFromPage(string $pageUrl, string $article): array
	{
		$out = [];
		$articleLower = strtolower($article);

		// Shopify product JSON is gold
		if (preg_match('#/products/([^/?#]+)#', $pageUrl, $m) && !str_ends_with($pageUrl, '.json')) {
			$jsonUrl = preg_replace('#\?.*$#', '', $pageUrl) . '.json';
			$out = array_merge($out, $this->extractFromShopifyJson($jsonUrl, $articleLower));
		}
		if (str_ends_with(parse_url($pageUrl, PHP_URL_PATH) ?? '', '.json')) {
			$out = array_merge($out, $this->extractFromShopifyJson($pageUrl, $articleLower));
		}

		$html = $this->httpGet($pageUrl);
		if ($html === '') {
			return $out;
		}

		$patterns = [
			'#property=[\"\']og:image[\"\'][^>]+content=[\"\']([^\"\']+)#i',
			'#content=[\"\']([^\"\']+)[\"\'][^>]+property=[\"\']og:image[\"\']#i',
			'#\"image\"\s*:\s*\[?\s*\"(https?://[^\"]+)\"#i',
			'#\"src\"\s*:\s*\"(https?://[^\"]+\.(?:jpg|jpeg|png|webp)[^\"]*)\"#i',
			'#https?://cdn\.shopify\.com/[^\"\',\s]+\.(?:jpg|jpeg|png|webp)[^\"\',\s]*#i',
			'#https?://cdn\d*\.jomashop\.com/[^\"\',\s]+\.(?:jpg|jpeg|png|webp)[^\"\',\s]*#i',
			'#https?://[^\"\',\s]+\.(?:jpg|jpeg|png|webp)(?:\?[^\"\',\s]*)?#i',
		];
		$found = [];
		foreach ($patterns as $pat) {
			if (preg_match_all($pat, $html, $m)) {
				foreach ($m[1] ?? $m[0] as $u) {
					$u = html_entity_decode(trim($u), ENT_QUOTES);
					$u = str_replace('\\/', '/', $u);
					if (!filter_var($u, FILTER_VALIDATE_URL)) {
						continue;
					}
					if (!$this->looksLikeProductImage($u, $articleLower)) {
						continue;
					}
					$found[$u] = true;
				}
			}
		}

		foreach (array_keys($found) as $u) {
			$out[] = [
				'url' => $u,
				'source' => 'retailer',
				'rank' => 40,
				'from_page' => $pageUrl,
			];
		}
		return $out;
	}

	private function extractFromShopifyJson(string $jsonUrl, string $articleLower): array
	{
		$raw = $this->httpGet($jsonUrl);
		if ($raw === '') {
			return [];
		}
		$data = json_decode($raw, true);
		if (!is_array($data)) {
			return [];
		}
		$product = $data['product'] ?? $data;
		$images = $product['images'] ?? [];
		$out = [];
		foreach ($images as $img) {
			$src = is_array($img) ? (string)($img['src'] ?? '') : (string)$img;
			$src = str_replace('\\/', '/', $src);
			if (!filter_var($src, FILTER_VALIDATE_URL)) {
				continue;
			}
			$out[] = [
				'url' => $src,
				'source' => 'official',
				'rank' => 10,
				'from_page' => $jsonUrl,
			];
		}
		return $out;
	}

	private function looksLikeProductImage(string $url, string $articleLower): bool
	{
		$u = strtolower($url);
		// skip junk
		foreach (['logo', 'sprite', 'icon', 'favicon', 'banner', 'pixel', '1x1', 'placeholder'] as $bad) {
			if (str_contains($u, $bad)) {
				return false;
			}
		}
		$host = strtolower((string)(parse_url($url, PHP_URL_HOST) ?? ''));
		$goodHosts = [
			'cdn.shopify.com',
			'jomashop.com',
			'cdn2.jomashop.com',
			'cdn1.jomashop.com',
			'media-amazon.com',
			'm.media-amazon.com',
			'chrono24.com',
			'watchbase.com',
		];
		foreach ($goodHosts as $h) {
			if (str_contains($host, $h)) {
				return true;
			}
		}
		// article mentioned in path
		if ($articleLower !== '' && str_contains($u, $articleLower)) {
			return true;
		}
		// generic product path hints
		if (preg_match('#/(files|products|catalog|media|images?)/#', $u)) {
			return true;
		}
		return false;
	}

	private static function pageScore(string $url): int
	{
		$u = strtolower($url);
		$score = 0;
		if (str_contains($u, 'jomashop.com')) $score += 50;
		if (str_contains($u, 'shopify')) $score += 40;
		if (str_contains($u, 'seikoboutique.com.ph')) $score += 45;
		if (str_contains($u, 'chrono24')) $score += 30;
		if (str_contains($u, 'amazon.')) $score += 25;
		if (str_contains($u, 'seikoboutique.co.uk')) $score -= 20;
		if (str_contains($u, 'seikowatches.com')) $score -= 10;
		return $score;
	}

	private static function imageScore(string $url): int
	{
		$u = strtolower($url);
		$score = 0;
		if (str_contains($u, 'cdn.shopify.com')) $score += 100;
		if (str_contains($u, 'jomashop.com')) $score += 90;
		if (str_contains($u, 'media-amazon.com')) $score += 70;
		if (str_contains($u, 'superzoom')) $score += 20;
		if (str_contains($u, 'seikoboutique.co.uk')) $score -= 50;
		return $score;
	}

	private function httpGet(string $url): string
	{
		$proxyOpts = [];
		try {
			$cfg = AiContentConfig::get();
			$parsed = AiContentConfig::parseProxy((string)($cfg['proxy'] ?? ''), 'http');
			if ($parsed && empty($parsed['is_socks'])) {
				$userpwd = (string)($parsed['userpwd'] ?? '');
				$hostport = (string)$parsed['hostport'];
				$proxyOpts[CURLOPT_PROXY] = $userpwd !== '' ? ('http://' . $userpwd . '@' . $hostport) : ('http://' . $hostport);
				$proxyOpts[CURLOPT_HTTPPROXYTUNNEL] = true;
				if ($userpwd !== '') {
					$proxyOpts[CURLOPT_PROXYUSERPWD] = $userpwd;
				}
			}
		} catch (Throwable $e) {
		}

		$ch = curl_init($url);
		$opts = [
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_MAXREDIRS => 5,
			CURLOPT_TIMEOUT => 20,
			CURLOPT_CONNECTTIMEOUT => 8,
			CURLOPT_SSL_VERIFYPEER => false,
			CURLOPT_SSL_VERIFYHOST => 0,
			CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
			CURLOPT_HTTPHEADER => [
				'Accept: text/html,application/json,application/xhtml+xml,*/*;q=0.8',
				'Accept-Language: en-US,en;q=0.9',
			],
			CURLOPT_ENCODING => '',
			CURLOPT_NOSIGNAL => true,
		] + $proxyOpts;
		if (defined('CURL_IPRESOLVE_V4')) {
			$opts[CURLOPT_IPRESOLVE] = CURL_IPRESOLVE_V4;
		}
		curl_setopt_array($ch, $opts);
		$body = curl_exec($ch);
		$code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);
		if ($code >= 400 || !is_string($body)) {
			return '';
		}
		return $body;
	}
}
