<?php
namespace Panel\Manager\Market;

class OzonMarket extends AbstractMarket
{
    //public function getCompetitorPrices(array $articles): array
    //{
	//	return [];
    //}
	
	protected function getSebesFbo (): array
	{
		$filter = $this->getPriceFilter();
		
		$conditions = [];
		
		if (isset($filter['article']) && !empty($filter['article'])) {
			if (is_array($filter['article'])) {
				$articles = array_map(function($item) {
					return "'" . addslashes($item) . "'";
				}, $filter['article']);
				$conditions[] = "model IN (" . implode(',', $articles) . ")";
			} else {
				$conditions[] = "model = '" . addslashes($filter['article']) . "'";
			}
		}
		
		if (isset($filter['brand_id']) && !empty($filter['brand_id'])) {
			if (is_array($filter['brand_id'])) {
				$brandIds = array_map('intval', $filter['brand_id']);
				$conditions[] = "brand_id IN (" . implode(',', $brandIds) . ")";
			} else {
				$conditions[] = "brand_id = '" . addslashes($filter['brand_id']) . "'";
			}
		}
		
		if (!empty($conditions)) {
			$sql = "SELECT * FROM ozon_fbo_sebes_IP WHERE " . implode(' AND ', $conditions);
		} else {
			$sql = "SELECT * FROM ozon_fbo_sebes_IP";
		}
		
		/*try {
			$result = $this->dbPanel->query($sql);
			return $this->dbPanel->fetchAll($result);
        } catch (\Exception $e) {
            $this->logger->log("ERROR", "Ошибка: " . $e->getMessage());
            return [];
        }*/
		// пусть грохается
		$result = $this->dbPanel->query($sql);
		$price = $this->dbPanel->fetchAll($result);
		
		return $this->prepareSebesFbo($price);
	}
	
	protected function prepareSebesFbo ($prices = []): array 
	{
		if (!$prices) return [];
		
		$arPrice = [];
		
		foreach ($prices as $price) {
			$arPrice[$price['model']] = [
				'model' => $price['model'],
				'brand_id' => $price['brand_id'],
				'supplier_id' => 'sebes OZON',
				'supplier_name' => 'sebes OZON',
				'price' => $price['sebes'],
				'count' => 100,
			];
		}
		
		return $arPrice;
	}
	
	protected function getPriceFbo (): array
	{
		$filter = $this->getPriceFilter();
		
		$conditions = [];
		
		if (isset($filter['article']) && !empty($filter['article'])) {
			if (is_array($filter['article'])) {
				$articles = array_map(function($item) {
					return "'" . addslashes($item) . "'";
				}, $filter['article']);
				$conditions[] = "article IN (" . implode(',', $articles) . ")";
			} else {
				$conditions[] = "article = '" . addslashes($filter['article']) . "'";
			}
		}
		
		/*if (isset($filter['brand_id']) && !empty($filter['brand_id'])) {
			if (is_array($filter['brand_id'])) {
				$brandIds = array_map('intval', $filter['brand_id']);
				$conditions[] = "brand_id IN (" . implode(',', $brandIds) . ")";
			} else {
				$conditions[] = "brand_id = '" . addslashes($filter['brand_id']) . "'";
			}
		}*/
		
		if (!empty($conditions)) {
			$sql = "SELECT * FROM {$this->config['tbl_price_fbo']} WHERE " . implode(' AND ', $conditions);
		} else {
			$sql = "SELECT * FROM {$this->config['tbl_price_fbo']}";
		}
		
		/*try {
			$result = $this->dbPanel->query($sql);
			return $this->dbPanel->fetchAll($result);
        } catch (\Exception $e) {
            $this->logger->log("ERROR", "Ошибка: " . $e->getMessage());
            return [];
        }*/
		// пусть грохается
		$result = $this->dbPanel->query($sql);
		$price = $this->dbPanel->fetchAll($result);
		
		return $this->preparePriceFbo($price);
	}
	
	protected function preparePriceFbo ($prices = []): array 
	{
		if (!$prices) return [];
		
		$arPrice = [];
		
		foreach ($prices as $price) {
			$arPrice[$price['article']] = [
				'model' => $price['article'],
				'price' => $price['price'],
			];
		}
		
		return $arPrice;
	}
}