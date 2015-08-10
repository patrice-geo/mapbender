# -*- coding: utf-8 -*-

from lib.user import login
from lib.logout import logout
from lib.utils import create_webdriver  # Changed
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
    wd.find_element_by_link_text("Mapbender Demo Map").click()
    wd.find_element_by_css_selector("label.iconLayertree").click()
    #wd.find_element_by_css_selector("div.mb-element-layertree:eq(1)").click()
    wd.execute_script("$('.mb-element-map').data('mapbender-mbMap').map.olMap.layers[1].setVisibility(false);")
    wd.execute_script("$('.mb-element-map').data('mapbender-mbMap').map.olMap.layers[2].setVisibility(false);")
    time.sleep(5)
    '''
    
    
    wd.find_element_by_css_selector("li.item-4").click()
    wd.find_element_by_id("application_title").send_keys("testing responsive template")
    wd.find_element_by_id("application_slug").send_keys("testing_responsive_template")
    wd.find_element_by_id("application_description").send_keys("run a test to create a new application based on the responsive alternative template")
    wd.find_element_by_css_selector("input.button").click()
    if not ("testing responsive template" in wd.find_element_by_tag_name("html").text):
        raise Exception("find_element_by_tag_name failed: testing responsive template")
    '''

    #logout(wd)

except Exception as e:  # Changed ff
    wd.quit()
    raise e
finally:
    wd.quit()
    if not success:
        raise Exception("Test failed.")

