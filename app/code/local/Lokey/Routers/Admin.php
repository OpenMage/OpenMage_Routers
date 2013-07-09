<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt
 *
 * @category   Mage
 * @package    Lokey_Routers
 * @copyright  Copyright (c) 2012 Lokey Coding, LLC <ip@lokeycoding.com>
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * @author     Lee Saferite <lee.saferite@lokeycoding.com>
 */

class Lokey_Routers_Admin extends Mage_Core_Controller_Varien_Router_Admin
{
    /**
     * @var bool Flag indicating if the extended matching code should be active
     */
    protected $_extendedSearchActive = false;

    /**
     * @var string|null Cached copy of the admin frontName
     */
    protected $_adminFrontName;

    /**
     * Match the request
     *
     * This is actually a wrapper for the real match method
     * The wrapper adds the ability og having '/' characters
     * in the frontName
     *
     * @param Zend_Controller_Request_Http $request
     *
     * @return boolean
     */
    public function match(Zend_Controller_Request_Http $request)
    {
        /**
         * Fail Fast
         *
         * There is no need to run code if there are:
         *  - no extended frontNames
         *  - a manually selected module name
         */
        if (!$this->_extendedSearchActive || $request->getModuleName()) {
            return parent::match($request);
        }

        //checking before even try to find out that current module
        //should use this router
        // NB: this check will be run twice due to the way we are extending the core router
        if (!$this->_beforeModuleMatch()) {
            return false;
        }

        $originalPath = trim($request->getPathInfo(), '/');

        /**
         * Fail Fast
         *
         * There is no need to run this if it's an empty path
         */
        if (!$originalPath) {
            return parent::match($request);
        }

        // Copy the originalPath to workingPath so we can revert our changes later
        $workingPath = $originalPath;

        // Pull out the longest valid frontName, modifying workingPath in the process
        $frontName = $this->_extractFrontName($workingPath);

        if (strpos($frontName, '/') === false) {
            // Bypass request modification when there is not extended frontName match
            return parent::match($request);
        } else {
            // Change request PATH_INFO to replace real frontName with fake one
            $request->setPathInfo('[PARSED]/' . $workingPath);

            // Force usage of the frontName we detected
            $request->setModuleName($frontName);

            // Run the normal match() method
            $result = parent::match($request);

            // Reset request PATH_INFO (just in case)
            $request->setPathInfo($originalPath);

            return $result;
        }
    }

    /**
     * Parse the PATH_INFO string passed looking for the best frontName
     *
     * This method will support frontNames with '/' characters
     * It matches the longest possible valid frontName
     *
     * @param string $path
     *
     * @return mixed|string
     */
    protected function _extractFrontName(&$path)
    {
        $p = explode('/', $path);

        $frontName = array_shift($p);
        while (count($p) > 0) {
            $frontNames = preg_grep('/^' . preg_quote($frontName . '/' . $p[0], '/') . '.*/', $this->_routes);
            if (count($frontNames) > 0) {
                $frontName .= '/' . array_shift($p);
            } else {
                break;
            }
        }

        $path = implode('/', $p);

        return $frontName;
    }

    /**
     * Extend the addModule method to check for an "[admin]" prefix on frontNames
     * and replace it with the real admin frontName.
     *
     * @param string $frontName
     * @param string $moduleName
     * @param string $routeName
     *
     * @return Mage_Core_Controller_Varien_Router_Standard
     */
    public function addModule($frontName, $moduleName, $routeName)
    {
        if (strpos($frontName, '[admin]/') === 0) {
            $frontName = $this->_getAdminFrontName() . substr($frontName, 7);
        }

        if (strpos($frontName, '/') !== false) {
            $this->_extendedSearchActive = true;
        }

        return parent::addModule($frontName, $moduleName, $routeName);
    }

    /**
     * Grab the currently configured admin frontName and cache it locally
     *
     * @return string
     */
    protected function _getAdminFrontName()
    {
        if (!$this->_adminFrontName) {
            $this->_adminFrontName = (string)Mage::getConfig()->getNode(Mage_Adminhtml_Helper_Data::XML_PATH_ADMINHTML_ROUTER_FRONTNAME);
        }

        return $this->_adminFrontName;
    }
}
