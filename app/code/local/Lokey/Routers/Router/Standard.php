<?php
class Lokey_Routers_Router_Standard extends Mage_Core_Controller_Varien_Router_Standard
{
    /**
     * @var bool Flag indicating if the extended matching code should be active
     */
    protected $_extendedSearchActive = false;

    /**
     * Match the request
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
     * Extend the addModule method to check for a "/" character in frontNames
     *
     * @param string $frontName
     * @param string $moduleName
     * @param string $routeName
     *
     * @return Mage_Core_Controller_Varien_Router_Standard
     */
    public function addModule($frontName, $moduleName, $routeName)
    {
        if (strpos($frontName, '/') !== false) {
            $this->_extendedSearchActive = true;
        }

        return parent::addModule($frontName, $moduleName, $routeName);
    }
}
