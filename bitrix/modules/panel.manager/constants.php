<?php
/*define('BQ_EXCHANGE' , [
    1 => [
		"COLUMN" => ["date","productName","productXmlID","productArticle","sellQuantity","sellPrice","sellCost","sellSum","sellCostSum","returnQuantity","returnPrice","returnCost","returnSum","returnCostSum","profit","margin"],
    ],
    2 => [
    	"COLUMN" => ["date","channelName","channelType","salesCount","salesAvgCheck","sellSum","sellCostSum","returnAvgCheck","returnSum","returnCostSum","profit","margin"],
    ]
]);*/
define('BQ_DIRECTORY' , [
	// мой склад
		"CLASS" => "MoyskladAPI",
		"METHOD" => [
			"getCustomers" => [
				"NAME" => "Клиенты",
				"COLUMN" => ["cutromer_id","cutromer_name","group","adress","code","external_code","type","TIN"],
			],
			"getProducts" => [
				"NAME" => "Товары",
				"COLUMN" => ["product_id","product_name","product_collection","product_brand","country","supplier","item_number","code","external_code","vat","EAN8","EAN13","Code128","GTIN","UPC"],
			],
			"getWarehouses" => [
				"NAME" => "Склады",
				"COLUMN" => ["warehouse_id","warehouse_name"],
			],
		],
		"LOGIN_LIST" => ["s1" => "bitrix@tempusint","s2" => "bitrix@tempusby","msk" => "api@chronos","s1_opt" => "api@tempusws"]
]);

define('BQ_EXCHANGE' , [
	// мой склад
    1 => [
		"CLASS" => "MoyskladAPI",
		"METHOD" => [
			"getListProfitChannel" => [
				"NAME" => "По каналам",
				"COLUMN" => ["date","channelName","channelType","salesCount","salesAvgCheck","sellSum","sellCostSum","returnAvgCheck","returnSum","returnCostSum","profit","margin"],
			],
			"getListProfitDay" => [
				"NAME" => "По товарам",
				"COLUMN" => ["date","productName","productXmlID","productArticle","sellQuantity","sellPrice","sellCost","sellSum","sellCostSum","returnQuantity","returnPrice","returnCost","returnSum","returnCostSum","profit","margin","product_id"],
			],
      "getListDemandPur" => [
				"NAME" => "По отгрузкам",
				"COLUMN" => ["demand_id","date","customer_id","warehouse_id"],
        "COLUMN_POS" => ["demand_id","product_id","quantity","price","discount","vat"],
			],
			"getStocksCount" => [
				"NAME" => "По остатками",
				"COLUMN" => ["date","product_id","quantity","COGS","warehouse_id"],
			],
			"getSupply" => [
				"NAME" => "По приемке",
				"COLUMN" => ["supply_id","date","customer_id","quantity","sum","warehouse_id"],
			],
		],
		"LOGIN_LIST" => ["s1" => "bitrix@tempusint","s2" => "bitrix@tempusby","s3" => "admin@tempuspl","s1_order" => "admin@tempusint","s1_opt" => "api@tempusws","msk" => "api@chronos"]
	]
]);


define('TYPE_SKLAD_CONST' , [
		"Express 7D" => "1020005000505202",
		"7D" => "1020005000172885",
		"5D-13.00" => "1020005000172886",
		"BY" => "1020005000172888",
		// "7D-12.00" => "1020005000172889",
		"5D-09.00" => "1020005000172889",
]);

define('TYPE_SKLAD_CONST_TI' , [
		"Express 7D" => "1020001970733000",
		"ХГ-экспресс" => "1020001289937000",
		"6D" => "1020001970677000",
		"7D" => "1020001970669000",
		"5D" => "1020001970685000",
		"BY" => "1020001970703000",
		"7D-12.00" => "1020002027360000"
]);

define("TYPE_SKLAD_CONST_WT", [
	"7D" => "1020005003830350",
	"5D-13.00" => "1020005003830490",
	"7D-12.00" => "1020005003830510",
	"BY" => "1020005003830590"
]);

define('OZON_API_CONN' , [
		"api_url" => "https://api-seller.ozon.ru",
		"client_id" => "211514",
		"token" => "8a187ff2-6715-42bc-a8f7-ae627dff7dd1",
]);
