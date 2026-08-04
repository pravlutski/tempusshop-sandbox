<?php
namespace Yandex\Market\Trading\Service\Marketplace\Command;

use Yandex\Market\Result;
use Yandex\Market\Utils;
use Yandex\Market\Trading\Service\Marketplace\Api;
use Yandex\Market\Trading\Service\Marketplace\Provider;
use Yandex\Market\Api\Reference\HasOauthConfiguration;

class PushApiMode
{
	protected $httpConfig;
	protected $provider;

	public function __construct(Provider $provider, HasOauthConfiguration $httpConfig)
	{
		$this->provider = $provider;
		$this->httpConfig = $httpConfig;
	}

	public function run($mode)
	{
		Utils\ServerStamp\Facade::check();

		$request = new Api\ApiMode\Request();
		$request->setLogger($this->provider->getLogger());
		$request->setOauthClientId($this->httpConfig->getOauthClientId());
		$request->setOauthToken($this->httpConfig->getOauthToken()->getAccessToken());
		$request->setCampaignId($this->httpConfig->getCampaignId());
		$request->setApiMode(mb_strtoupper($mode));

		$submit = $request->send();

		Result\Facade::handleException($submit);
	}
}