<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();
require_once($_SERVER['DOCUMENT_ROOT'] . '/local/classes/ElasticSearchService.php');

class SearchStatsComponent extends CBitrixComponent
{
    private $elasticService;

    public function __construct($component = null)
    {
        parent::__construct($component);
        $this->elasticService = new ElasticSearchService();
    }

    public function executeComponent()
    {
        try {
            if (!$this->checkAccess()) {
                $this->arResult['ERROR'] = 'Доступ запрещен';
                $this->includeComponentTemplate();
                return;
            }

            $this->processFilters();
            $this->arResult = $this->getSearchStatistics();
            
            $this->includeComponentTemplate();
        } catch (Exception $e) {
            $this->arResult['ERROR'] = $e->getMessage();
            $this->includeComponentTemplate();
        }
    }

    private function checkAccess()
    {
        global $USER;
        return $USER->IsAdmin();
    }

    private function processFilters()
    {
        $request = \Bitrix\Main\Context::getCurrent()->getRequest();
        
        $this->arParams['DATE_FROM'] = $request->get('date_from') ?: date('Y-m-d', strtotime('-7 days'));
        $this->arParams['DATE_TO'] = $request->get('date_to') ?: date('Y-m-d');
        $this->arParams['LIMIT'] = (int)$request->get('limit') ?: 50;
    }

    private function getSearchStatistics()
    {
        $stats = $this->elasticService->getSearchStats(
            $this->arParams['DATE_FROM'],
            $this->arParams['DATE_TO'],
            $this->arParams['LIMIT']
        );

        return [
            'stats' => $stats,
            'filters' => [
                'date_from' => $this->arParams['DATE_FROM'],
                'date_to' => $this->arParams['DATE_TO'],
                'limit' => $this->arParams['LIMIT']
            ],
            'total_queries' => count($stats)
        ];
    }
}