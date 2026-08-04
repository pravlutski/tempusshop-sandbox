<?php

namespace Imedia\Main\Helper\Main;

use Bitrix\Main\Context;
use Bitrix\Main\HttpRequest;
use Bitrix\Main\Page\Asset;
use CJSCore;

class Application
{
    private HttpRequest $request;
    private bool $isFront;
    private bool $is404;
    private bool $isBlog;
    private bool $isContacts;
    private bool $isBot;

    private static self $instance;

    private const DIR_BLOG = 'blog';
    private const DIR_CONTACTS = 'contacts';

    private function __construct()
    {
        $context = Context::getCurrent();

        $this->request = $context->getRequest();

        $this->isFront = $this->request->getRequestedPage() === SITE_DIR . 'index.php';
        $this->is404 = (defined('ERROR_404') && ERROR_404 === 'Y');
        $this->isBlog = str_starts_with($this->request->getRequestedPageDirectory(), SITE_DIR . static::DIR_BLOG);
        $this->isContacts = str_starts_with($this->request->getRequestedPageDirectory(), SITE_DIR . static::DIR_CONTACTS);
        $this->isBot = Bot::isBot();
    }

    public static function getInstance(): self
    {
        if (!isset(self::$instance)) {
            self::$instance = new static();
        }

        return self::$instance;
    }

    public function getRequest(): HttpRequest
    {
        return $this->request;
    }

    public function isFront(): bool
    {
        return $this->isFront;
    }

    public function is404(): bool
    {
        return $this->is404;
    }

    public function isBlog(): bool
    {
        return $this->isBlog;
    }

    public function isContacts(): bool
    {
        return $this->isContacts;
    }

    public function isBot(): bool
    {
        return $this->isBot;
    }

    public function jsCoreInit(array $array = []): void
    {
        if ($this->isBot()) {
            return;
        }

        CJSCore::Init($array);
    }

    public function setAssets(): void
    {
        $asset = Asset::getInstance();

        $asset->addCss(SITE_TEMPLATE_PATH . '/assets/libs/css/libs.min.css');
        $asset->addCss(SITE_TEMPLATE_PATH . '/assets/styles/app.min.css');
        $asset->addCss(SITE_TEMPLATE_PATH . '/assets/add/css/app.css');

        $asset->addJs(SITE_TEMPLATE_PATH . '/assets/libs/js/svg4everybody.min.js');
        $asset->addJs(SITE_TEMPLATE_PATH . '/assets/libs/js/imask.js');
        $asset->addJs(SITE_TEMPLATE_PATH . '/assets/libs/js/libs.min.js');
        $asset->addJs(SITE_TEMPLATE_PATH . '/assets/scripts/app.min.js');
        $asset->addJs(SITE_TEMPLATE_PATH . '/assets/add/js/app.js');
        $asset->addJs(SITE_TEMPLATE_PATH . '/assets/add/js/app.js');
        $asset->addJs(SITE_TEMPLATE_PATH . '/assets/scripts/jquery.lazy.min.js');

        if ($this->isContacts()) {
            $asset->addString('<script src="//api-maps.yandex.ru/2.1/?lang=ru_RU&amp;apikey=cfdaeda4-4fc9-44e6-af40-6d489e90117a"></script>');
        }
    }

    public function showHead(): void
    {
        global $APPLICATION;

        echo '<meta http-equiv="Content-Type" content="text/html; charset=' . LANG_CHARSET . '">';
        echo $this->getDefaultHead();
        $APPLICATION->ShowMeta('robots');
        $APPLICATION->ShowMeta('description');
        $APPLICATION->ShowCSS();
        $APPLICATION->ShowHeadStrings();
        $APPLICATION->ShowHeadScripts();
    }

    protected function getDefaultHead(): string
    {
        return '
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
        <meta name="msthemecompatible" content="no">
        <meta name="HandheldFriendly" content="True">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
        <meta name="format-detection" content="telephone=no">
        <meta name="format-detection" content="address=no">
        <link rel="apple-touch-icon" sizes="180x180" href="' . SITE_TEMPLATE_PATH . '/favicons/apple-touch-icon.png">
        <link rel="icon" type="image/png" sizes="32x32" href="' . SITE_TEMPLATE_PATH . '/favicons/favicon-32x32.png">
        <link rel="icon" type="image/png" sizes="16x16" href="' . SITE_TEMPLATE_PATH . '/favicons/favicon-16x16.png">
        <link rel="manifest" href="' . SITE_TEMPLATE_PATH . '/favicons/site.webmanifest">
        <link rel="mask-icon" href="' . SITE_TEMPLATE_PATH . '/favicons/safari-pinned-tab.svg" color="#5bbad5">
        <link rel="shortcut icon" href="' . SITE_TEMPLATE_PATH . '/favicons/favicon.ico">
        <meta name="msapplication-TileColor" content="#da532c">
        <meta name="msapplication-config" content="' . SITE_TEMPLATE_PATH . '/favicons/browserconfig.xml">
        <meta name="theme-color" content="#ffffff">
        ';
    }

public function setTitle(): void
{
    global $APPLICATION;

    $classList = $APPLICATION->GetProperty('classes--title') ?: 'h4';
    $rawTitle = $APPLICATION->GetTitle();

    $processedTitle = preg_replace('/(Наручные часы)\s*/u', '$1<br>', $rawTitle, 1);
    $title = '<h1 class="' . $classList . '" style="white-space: pre-line;">' . $APPLICATION->GetProperty('product-title') . '</h1>';
    $APPLICATION->AddViewContent('title', $title);
    $subtext = $APPLICATION->GetProperty('subtext');
    if ($subtext) {
        $APPLICATION->AddViewContent('subtext', '<div class="page-top__subtext">' . $subtext . '</div>');
    }
}


    public function setPage404(): void
    {
        $page404 = '/404.php';

        global $APPLICATION;

        if (
            defined('ERROR_404')
            && (ERROR_404 === 'Y')
            && (!str_contains($APPLICATION->GetCurPage(), $page404))
        ) {

            $APPLICATION->RestartBuffer();
            \CHTTP::SetStatus('404 Not Found');

            $server = Context::getCurrent()->getServer();

            include($server->getDocumentRoot() . SITE_TEMPLATE_PATH . '/header.php');
            include($server->getDocumentRoot() . $page404);
            include($server->getDocumentRoot() . SITE_TEMPLATE_PATH . '/footer.php');

        }
    }

    public function includeContent(string $name = null, bool $fromRoot = false): void
    {
        if (!$name) {
            $name = 'index_blocks';
        }

        $server = Context::getCurrent()->getServer();

        $file = $server->getDocumentRoot();
        if (!$fromRoot) {
            $file .= $this->request->getRequestedPageDirectory();
        }

        $file .= '/' . $name . '.php';

        if (file_exists($file)) {
            @include $file;
        }
    }
}
