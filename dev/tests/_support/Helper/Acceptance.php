<?php
namespace Helper;

// here you can define custom actions
// all public methods declared in helper class will be available in $I
use Codeception\Exception\ModuleException;
use Symfony\Component\BrowserKit\Cookie;

class Acceptance extends \Codeception\Module
{
    /** @var  \Codeception\Module\Db */
    private $databaseModule;

    /** @var  \Codeception\Module\REST */
    private $restModule;

    /**
     * Event hook before a test starts.
     *
     * @param \Codeception\TestInterface $test
     *
     * @throws \Exception
     */
    public function _before(\Codeception\TestInterface $test)
    {
        $this->databaseModule = $this->getModule('Db');
        $this->restModule = $this->getModule('REST');
    }

    /**
     * @param $url
     * @param array $params
     * @param array $files
     */
    public function sendPOSTAndVerifyResponseCodeIs200($url, $params = [], $files = [])
    {
        $this->restModule->sendPOST($url, $params, $files);
        $this->restModule->seeResponseCodeIs(200);
    }

    /**
     * Set XDEBUG cookie for debugging rest connections
     */
    public function haveRESTXdebugCookie()
    {
        $this->restModule->client->getCookieJar()->set(new Cookie('XDEBUG_SESSION', 'phpstorm'));
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
