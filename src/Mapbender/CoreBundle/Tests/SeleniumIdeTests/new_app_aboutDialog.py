# -*- coding: utf-8 -*-

from lib.user import login
from lib.logout import logout
from lib.utils import create_webdriver  # Changed

from selenium.webdriver.common.by import By
from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC

import time

success = True
wd = create_webdriver()


def is_alert_present(wd):
    try:
        wd.switch_to_alert().text
        return True
    except:
        return False

try:
    login(wd)
    time.sleep(2)
    wd.find_element_by_link_text("Mapbender Demo Map").click()
    wd.find_element_by_css_selector("span.mb-aboutButton").click()
    element = WebDriverWait(wd, 10).until(
        EC.visibility_of_element_located((By.CSS_SELECTOR, "span.popupTitle"))
    )
    popup = wd.find_element_by_css_selector("div.popup")
    popup_title = wd.find_element_by_css_selector("span.popupTitle").text
    sub_text = wd.find_element_by_css_selector("p.subTitle").text
    descr_text = wd.find_element_by_css_selector(".popup p.description").text
    if len(sub_text) > 0 and len(descr_text) > 0 and popup_title == 'About':
        wd.execute_script("console.log('success');")
        time.sleep(2)
    else:
        success = False
    

    #logout(wd)

except Exception as e:  # Changed ff
    raise e
    wd.quit()
    #raise e
finally:
    wd.quit()
    if not success:
        raise Exception("Test failed.")

