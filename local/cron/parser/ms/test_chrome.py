from selenium import webdriver
from selenium.webdriver.common.keys import Keys
from selenium.webdriver.common.by import By
from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC
from sys import argv
from selenium.webdriver.firefox.firefox_profile import FirefoxProfile
from selenium.common.exceptions import TimeoutException
from selenium.webdriver.common.action_chains import ActionChains
from pprint import pprint as pp

import json
import os
import traceback
import time


match_page = {
    'stockReport': 'https://online.moysklad.ru/app/#stockReport?reportType=GOODS&upToDateFilter=29.08.2024%2023:59:00|'
}
# python3 /var/www/bitrix/data/www/tempusshop.ru/local/cron/parser/ms/clicker.py cabinet=msk2 username=api@chronos password=VvrmVqzKtF7B pages=stockReport,



"""
from selenium.webdriver.firefox.options import Options
from selenium.webdriver.firefox.firefox_profile import FirefoxProfile
options=Options()
firefox_profile = FirefoxProfile()
firefox_profile.set_preference("javascript.enabled", False)
options.profile = firefox_profile  
"""

"""
from selenium.webdriver import FirefoxOptions

opts = FirefoxOptions()
opts.add_argument("--headless")
opts.add_argument("--width=2560")
opts.add_argument("--height=1440")
opts.set_preference("dom.popup_maximum", 50)
opts.binary_location = '/usr/bin/chromedriver'


opts.add_argument("--disable-extensions");
opts.add_argument("--disable-gpu");
opts.add_argument("--no-sandbox");
opts.add_argument("--disable-dev-shm-usage");
opts.add_argument("--disable-setuid-sandbox");
opts.add_argument("--disable-popup-blocking");
opts.add_argument("--disable-infobars");
opts.add_argument("--disable-notifications");
opts.add_argument("--disable-default-apps");
opts.add_argument("--mute-audio");
opts.add_argument("--blink-settings=imagesEnabled=false");
    
try:
    driver = webdriver.Chrome(executable_path='/usr/bin/chromium-browser')
except:
    print(traceback.format_exc())

driver.get("https://online.moysklad.ru/doLogon")
"""

options = webdriver.ChromeOptions()
options.add_argument("--headless")
options.add_argument("--no-sandbox")
options.add_argument("--ignore-ssl-errors=true")
options.add_argument("--ignore-certificate-errors")    
options.binary_location="/usr/bin/chromium-browser"

driver = webdriver.Chrome('/usr/bin/chromedriver',options=options)
driver.get("https://online.moysklad.ru/doLogon")
    
driver.close()
driver.quit()
