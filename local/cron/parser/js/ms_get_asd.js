var casper = require('casper').create({
    pageSettings: {
        userAgent: "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/127.0.0.0 Safari/537.36"
    },
    viewportSize : { width: 1280, height: 1024 },
    verbose: false,
	logLevel: "debug",
	javascriptEnabled: true,
	loadImages: true,
	loadPlugins: true,
//	timeout: 60000,
//	stepTimeout: 20000
});
casper.windowName = 'MOYSKLADFRAME';
casper.cookiesEnabled = true;

var B2B_USER = 'api@chronos';
var B2B_PASS = 'VvrmVqzKtF7B';

var data, wsurl = 'https://online.moysklad.ru/';
var fs = require('fs');
var utils = require('utils');
var counter = 0; 

var start = true;
casper.label = function label(labelname) {
    var step = new Function('"empty function for label: ' + labelname + ' "'); // make empty step
    step.label = labelname; // Adds new property 'label' to the step for label naming
    this.then(step); // Adds new step by then()
    return this;
};

/**
 * Goto labeled navigation step
 *
 * @param String labelname Label name for jumping navigation step
 */
casper.goto = function goto(labelname) {
    for (var i = 0; i < this.steps.length; i++) { // Search for label in steps array
//        if (this.steps[i].label == labelname) { // found?
		if (this.steps[i].label === labelname || this.steps[i].name === labelname) {
            this.step = i; // new step pointer is set
        }
    }
    return this;
};

casper.bypass = function bypass(nbOrLabel) {
    "use strict";
    var step = this.step,
        steps = this.steps,
        last = steps.length;
    this.checkStarted();
    if (utils.isNumber(nbOrLabel)){
        this.step = Math.min(step + nbOrLabel, last);
    } else {
        for (var i = step, l = this.steps.length; i < l && this.step !== step; i++) { // Search for label in next steps array
            if (typeof this.steps[i].label !== "undefined" && this.steps[i].label === nbOrLabel) { // found?
                this.step = i; // new step pointer is set
            }
        }
    }
    this.emit('step.bypassed', this.step, step);
    return this;
};

var delay = (function(){
	var timer = 0;
	return function(callback, ms){
		clearTimeout (timer);
		timer = setTimeout(callback, ms);
	};
})();
/* auth */


/*casper.start('https://online.moysklad.ru/', function () {
	
	var loginInput = '#lable-login';
	var passInput = '#lable-password';
	console.log('Submitted' + this.getCurrentUrl());
	this.mouseEvent('click', loginInput, '15%', '48%');
	this.sendKeys(loginInput, B2B_USER);

	this.mouseEvent('click', passInput, '12%', '67%');
	this.sendKeys(passInput, B2B_PASS);
	
	//this.click('#submitButton');
});
//.waitForUrl(/app$/, function() {
//    this.echo('redirected to login.html');
//});
casper.thenClick('#submitButton', function (response) {
	console.log('Submitted' + this.getCurrentUrl() + ' - ' + casper.windowName);
});
casper.waitForUrl('https://online.moysklad.ru/app/', function() {
    this.echo('redirected to login.html');
});
  */
/*
casper.thenClick('#submitButton', function (response) {
	console.log('Submitted' + this.getCurrentUrl() + ' - ' + casper.windowName);
});
casper.then(function () {
	
	this.wait(5000, function () {
		this.captureSelector('/var/www/bitrix/data/www/tempusshop.ru/local/cron/parser/js/ms.png', 'body');
		fs.write("/var/www/bitrix/data/www/tempusshop.ru/local/cron/parser/js/ms.txt", this.getPageContent(), 'w');
	});
	
});


casper.then(function() {
	this.captureSelector('/var/www/bitrix/data/www/tempusshop.ru/local/cron/parser/js/ms0.png', 'body');
}); */

casper.start().then(function() {
    this.open('https://online.moysklad.ru/doLogon', {
        method: 'post',
        headers: {
            'content-type': 'application/x-www-form-urlencoded',
			'accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
			'Accept-Language': 'ru,en-US;q=0.9,en;q=0.8,pl;q=0.7',
			'Cookie': 'BX_USER_ID=97799a4b6aeaf8ced02fe00cb0e5ad46',
			':authority': 'online.moysklad.ru',
        },
		data: {
			'returnPath': '',
			'LognexShowCaptcha':  false,
			'j_username':  B2B_USER,
			'j_password':  B2B_PASS,
			'submitButton':  '',
		}
    });
});


casper.then(function() {
	console.log('Submitted' + this.getCurrentUrl());
	this.captureSelector('/var/www/bitrix/data/www/tempusshop.ru/local/cron/parser/js/ms0.png', 'body');
}); 
/*
casper.thenOpen('https://online.moysklad.ru/entrance?returnPath=', function() {
	this.wait(5000, function () {});
	console.log('Submitted2' + this.getCurrentUrl());
	this.captureSelector('/var/www/bitrix/data/www/tempusshop.ru/local/cron/parser/js/ms2.png', 'body');
});*/
/*
casper.start().then(function() {
    this.open('https://online.moysklad.ru/doLogon', {
        method: 'get',
        headers: {
            'Accept': 'application/json',
			"Cookie" : "moysklad.reseller=LogneX; moysklad.firstEntryPoint=https%3A%2F%2Fonline.moysklad.ru%2Flogon; domain_sid=ikBUjh8B9nST1Ao2V8BHM%3A1724582616937; MSSESSIONIDPROD=3865a41a-e184-4521-9383-e9aea1c9fee3_834bddc0-0f74-11ee-0a80-0c99000074ab"
        }
    });
});
casper.then(function() {
  this.waitForResource(this.getCurrentUrl(),function() {
		var loginInput = '#lable-login';
		var passInput = '#lable-password';
		
		this.mouseEvent('click', loginInput, '15%', '48%');
		this.sendKeys(loginInput, B2B_USER);

		this.mouseEvent('click', passInput, '12%', '67%');  
		this.sendKeys(passInput, B2B_PASS);
		this.captureSelector('/var/www/bitrix/data/www/tempusshop.ru/local/cron/parser/js/ms0.png', 'body');

		
  },function() {
    //page load failed after 5 seconds
  },5000);
});

    casper.thenClick('#submitButton', function () {
        console.log('Submitted' + this.getCurrentUrl());
    });
   
    casper.wait(3000, function () {
        this.captureSelector('/var/www/bitrix/data/www/tempusshop.ru/local/cron/parser/js/ms1.png', 'body');
    });
*/
/*
casper.then(function () {
	var loginInput = '#lable-login';
	var passInput = '#lable-password';
	
	this.mouseEvent('click', loginInput, '15%', '48%');
	this.sendKeys(loginInput, B2B_USER);

	this.mouseEvent('click', passInput, '12%', '67%');  
	this.sendKeys(passInput, B2B_PASS);
	this.captureSelector('/var/www/bitrix/data/www/tempusshop.ru/local/cron/parser/js/ms0.png', 'body');
		this.click('#submitButton');
	this.wait(5000, function () {
		this.click('.eye');
		
	});
	this.captureSelector('/var/www/bitrix/data/www/tempusshop.ru/local/cron/parser/js/ms1.png', 'body');
});*/
/*
casper.start('https://online.moysklad.ru/doLogon', function () {
	var loginInput = '#lable-login';
	var passInput = '#lable-password';
	
	this.mouseEvent('click', loginInput, '15%', '48%');
	this.sendKeys(loginInput, B2B_USER);

	this.mouseEvent('click', passInput, '12%', '67%');
	this.sendKeys(passInput, B2B_PASS);
	
	this.wait(5000, function () {
		this.click('.eye');
	});
	
	
});
*/
/*
casper.then(function () {
	this.click('#submitButton');
	this.captureSelector('/var/www/bitrix/data/www/tempusshop.ru/local/cron/parser/js/ms1.png', 'body');
});
casper.then(function () {
    //this.evaluate(function () {
    //    $('form#logon').submit();
		
    //});
});

casper.then(function () {
	this.wait(5000, function () {
		this.captureSelector('/var/www/bitrix/data/www/tempusshop.ru/local/cron/parser/js/ms.png', 'body');
	});
	
});

casper.thenOpen('https://online.moysklad.ru/app/', function() {
	this.wait(10000, function () {});
	this.captureSelector('/var/www/bitrix/data/www/tempusshop.ru/local/cron/parser/js/ms2.png', 'body');
});
*/

/*
casper.then(function () {
	//this.captureSelector('/var/www/bitrix/data/www/tempusshop.ru/upload/tmp/onliner/onliner.png', 'body'); 
	screenshort_filename = casper.cli.get('screenshort_filename');
	this.wait(20000, function () {
		this.echo("https://b2b.onliner.by/shop/competitors_prices");
		//this.captureSelector("/var/www/bitrix/data/www/tempusshop.ru/upload/tmp/onliner/onliner_catalog_prices2_"+Math.random()+".png", "body");
		this.captureSelector("/var/www/bitrix/data/www/tempusshop.ru/upload/tmp/onliner/" + screenshort_filename, ".beaf_text");
		this.download("https://b2b.onliner.by/shop/competitors_prices", "/var/www/bitrix/data/www/tempusshop.ru/upload/onliner_competitors_prices.csv.gz");
	});
}); 
*/
casper.run();