<?php
namespace Helper;

// here you can define custom actions
// all public methods declared in helper class will be available in $I
use Codeception\Exception\ModuleException;

class Acceptance extends \Codeception\Module
{
    private $webDriver;
    /** @var \Codeception\Module\WebDriver */
    private $webDriverModule;

    /** @var  \Codeception\Module\Db */
    private $databaseModule;

    /**
     * Event hook before a test starts.
     *
     * @param \Codeception\TestInterface $test
     *
     * @throws \Exception
     */
    public function _before(\Codeception\TestInterface $test)
    {
        $this->webDriverModule = $this->getModule('WebDriver');
        $this->webDriver = $this->webDriverModule->webDriver;

        $this->databaseModule = $this->getModule('Db');
    }

    /**
     * @param $link
     * @param $timeout
     */
    public function amOnPage($link, $timeout = 30)
    {
        $this->webDriverModule->amOnPage($link);
        $this->waitPageLoad($timeout);
    }

    /**
     * @param $timeout
     */
    public function waitAjaxLoad($timeout = 30)
    {
        $this->webDriverModule->waitForJS('return !!window.jQuery && window.jQuery.active == 0;', $timeout);
        $this->webDriverModule->wait(1);
        $this->dontSeeJsError();
    }

    /**
     * @param $timeout
     */
    public function waitPageLoad($timeout = 30)
    {
        $this->webDriverModule->waitForJs('return document.readyState == "complete"', $timeout);
        $this->waitAjaxLoad($timeout);
        $this->dontSeeJsError();
    }

    /**
     * @throws ModuleException
     */
    public function dontSeeJsError()
    {
        $messagesToIgnore = [
            "Error: [object Object]",   ////https://github.com/magento/magento2/issues/6410
            "Uncaught TypeError: Cannot read property 'customer' of undefined",
        ];

        $logs = $this->webDriver->manage()->getLog('browser');
        foreach ($logs as $log) {
            if ($log['source'] === 'javascript' && $log['level'] === 'SEVERE') {
                foreach ($messagesToIgnore as $messageToIgnore) {
                    if (strpos($log['message'], $messageToIgnore) !== false) {
                        continue 2; //This message contains one of our ignored strings, move to next message.
                    }
                }
                throw new ModuleException($this, 'Some error in JavaScript: ' . json_encode($log));
            }
        }
    }

    /**
     * @param $table
     * @param $criteria
     */
    public function deleteFromDatabase($table, $criteria = [])
    {
        $this->databaseModule->_getDriver()->deleteQueryByCriteria($table, $criteria);
    }
}
