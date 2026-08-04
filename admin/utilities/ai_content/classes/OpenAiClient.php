<?php

class OpenAiClient
{
	private string $apiKey;
	private string $model;
	private string $proxy;
	private string $proxyType;

	public function __construct(?array $config = null)
	{
		$config = $config ?: AiContentConfig::get();
		$this->apiKey = $config['api_key'];
		$this->model = $config['model'];
		$this->proxy = (string)($config['proxy'] ?? '');
		$this->proxyType = AiContentConfig::normalizeProxyType((string)($config['proxy_type'] ?? 'http'));
	}

	/**
	 * Call Responses API with web_search. Returns decoded payload + raw text.
	 */
	public function researchJson(string $system, string $user): array
	{
		$body = [
			'model' => $this->model,
			'tools' => [
				['type' => 'web_search'],
			],
			'tool_choice' => 'auto',
			'input' => [
				[
					'role' => 'system',
					'content' => [
						['type' => 'input_text', 'text' => $system],
					],
				],
				[
					'role' => 'user',
					'content' => [
						['type' => 'input_text', 'text' => $user],
					],
				],
			],
		];

		$raw = $this->request('https://api.openai.com/v1/responses', $body);
		$text = $this->extractOutputText($raw);
		$json = $this->extractJson($text);

		return [
			'raw' => $raw,
			'text' => $text,
			'json' => $json,
		];
	}

	/** Lightweight connectivity check + exit IP via proxy */
	public function ping(): array
	{
		$parsed = AiContentConfig::parseProxy($this->proxy, $this->proxyType);
		if (!$parsed) {
			throw new RuntimeException('Прокси не разобран. Формат: user:pass@host:port или socks5h://user:pass@host:port');
		}

		$exitIp = $this->fetchViaProxy('https://api.ipify.org?format=json', false);
		$exit = is_array($exitIp) ? (string)($exitIp['ip'] ?? '') : trim((string)$exitIp);

		$ch = curl_init('https://api.openai.com/v1/models');
		$opts = [
			CURLOPT_HTTPGET => true,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HTTPHEADER => [
				'Authorization: Bearer ' . $this->apiKey,
			],
			CURLOPT_TIMEOUT => 60,
		];
		$this->applyProxy($opts);
		curl_setopt_array($ch, $opts);
		$response = curl_exec($ch);
		$errno = curl_errno($ch);
		$error = curl_error($ch);
		$code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$primaryIp = (string)curl_getinfo($ch, CURLINFO_PRIMARY_IP);
		curl_close($ch);

		if ($errno) {
			throw new RuntimeException('Proxy/curl error: ' . $error . ' | parsed=' . $parsed['hostport'] . ' type=' . $parsed['type_name']);
		}
		$decoded = json_decode((string)$response, true);
		if ($code >= 400) {
			$msg = is_array($decoded) ? ($decoded['error']['message'] ?? ('HTTP ' . $code)) : ('HTTP ' . $code);
			throw new RuntimeException(
				'OpenAI API error: ' . $msg
				. ' | exit_ip=' . ($exit ?: '?')
				. ' | proxy=' . $parsed['hostport']
				. ' | type=' . $parsed['type_name']
			);
		}
		return [
			'ok' => true,
			'http_code' => $code,
			'proxy' => true,
			'proxy_host' => $parsed['hostport'],
			'proxy_type' => $parsed['type_name'],
			'exit_ip' => $exit,
			'primary_ip' => $primaryIp,
		];
	}

	private function fetchViaProxy(string $url, bool $json = true)
	{
		$ch = curl_init($url);
		$opts = [
			CURLOPT_HTTPGET => true,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_TIMEOUT => 30,
		];
		$this->applyProxy($opts);
		curl_setopt_array($ch, $opts);
		$response = curl_exec($ch);
		$errno = curl_errno($ch);
		$error = curl_error($ch);
		$code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);
		if ($errno || $code >= 400) {
			return $json ? [] : '';
		}
		if (!$json) {
			$decoded = json_decode((string)$response, true);
			return is_array($decoded) ? $decoded : (string)$response;
		}
		$decoded = json_decode((string)$response, true);
		return is_array($decoded) ? $decoded : [];
	}

	private function request(string $url, array $body): array
	{
		$ch = curl_init($url);
		$opts = [
			CURLOPT_POST => true,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HTTPHEADER => [
				'Content-Type: application/json',
				'Authorization: Bearer ' . $this->apiKey,
			],
			CURLOPT_POSTFIELDS => json_encode($body, JSON_UNESCAPED_UNICODE),
			CURLOPT_TIMEOUT => 180,
		];
		$this->applyProxy($opts);
		curl_setopt_array($ch, $opts);
		$response = curl_exec($ch);
		$errno = curl_errno($ch);
		$error = curl_error($ch);
		$code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		if ($errno) {
			throw new RuntimeException('OpenAI curl error: ' . $error);
		}
		$decoded = json_decode((string)$response, true);
		if (!is_array($decoded)) {
			throw new RuntimeException('OpenAI invalid JSON response, HTTP ' . $code);
		}
		if ($code >= 400) {
			$msg = $decoded['error']['message'] ?? ('HTTP ' . $code);
			throw new RuntimeException('OpenAI API error: ' . $msg);
		}
		return $decoded;
	}

	private function applyProxy(array &$opts): void
	{
		$parsed = AiContentConfig::parseProxy($this->proxy, $this->proxyType);
		if (!$parsed) {
			return;
		}

		$isSocks = !empty($parsed['is_socks']) || str_starts_with((string)$parsed['type_name'], 'socks');

		// Prefer URL form for SOCKS auth — more reliable across libcurl builds
		if ($isSocks && !empty($parsed['userpwd'])) {
			$opts[CURLOPT_PROXY] = 'socks5h://' . $parsed['userpwd'] . '@' . $parsed['hostport'];
		} else {
			$opts[CURLOPT_PROXY] = $parsed['hostport'];
			$opts[CURLOPT_PROXYTYPE] = $parsed['type'];
			if (!empty($parsed['userpwd'])) {
				$opts[CURLOPT_PROXYUSERPWD] = $parsed['userpwd'];
			}
		}

		// HTTP CONNECT tunnel only for HTTP(S) proxies — breaks SOCKS if set
		if (!$isSocks) {
			$opts[CURLOPT_HTTPPROXYTUNNEL] = true;
			$opts[CURLOPT_PROXYAUTH] = CURLAUTH_ANY;
		} else {
			// Ensure SOCKS5 hostname resolution through proxy
			if (defined('CURLPROXY_SOCKS5_HOSTNAME')) {
				$opts[CURLOPT_PROXYTYPE] = CURLPROXY_SOCKS5_HOSTNAME;
			}
		}

		$opts[CURLOPT_SSL_VERIFYPEER] = true;
		$opts[CURLOPT_SSL_VERIFYHOST] = 2;

		// Some corporate HTTPS proxies need relaxed proxy TLS checks
		if (($parsed['type_name'] ?? '') === 'https') {
			if (defined('CURLOPT_PROXY_SSL_VERIFYPEER')) {
				$opts[CURLOPT_PROXY_SSL_VERIFYPEER] = false;
			}
			if (defined('CURLOPT_PROXY_SSL_VERIFYHOST')) {
				$opts[CURLOPT_PROXY_SSL_VERIFYHOST] = 0;
			}
		}
	}

	private function extractOutputText(array $raw): string
	{
		if (!empty($raw['output_text']) && is_string($raw['output_text'])) {
			return $raw['output_text'];
		}
		$parts = [];
		foreach (($raw['output'] ?? []) as $item) {
			if (($item['type'] ?? '') !== 'message') {
				continue;
			}
			foreach (($item['content'] ?? []) as $content) {
				if (($content['type'] ?? '') === 'output_text' && !empty($content['text'])) {
					$parts[] = $content['text'];
				}
			}
		}
		return trim(implode("\n", $parts));
	}

	private function extractJson(string $text): ?array
	{
		$text = trim($text);
		if ($text === '') {
			return null;
		}
		$decoded = json_decode($text, true);
		if (is_array($decoded)) {
			return $decoded;
		}
		if (preg_match('/\{.*\}/s', $text, $m)) {
			$decoded = json_decode($m[0], true);
			if (is_array($decoded)) {
				return $decoded;
			}
		}
		return null;
	}
}
