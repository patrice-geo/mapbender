require 'selenium-webdriver'
require 'rspec-expectations'

def setup
  Selenium::WebDriver::Chrome::Service.executable_path = File.join(Dir.pwd, './chromedriver')
  @driver = Selenium::WebDriver.for :chrome
end

def teardown
  @driver.quit
end

def run
  setup
  yield
  teardown
end

run do
  @driver.get 'http://the-internet.herokuapp.com/'
  @driver.title.should == 'The Internet'
end
