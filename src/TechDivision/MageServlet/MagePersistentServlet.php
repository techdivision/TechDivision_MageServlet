<?php

/**
 * TechDivision\MageServlet\MagePersistentServlet
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * PHP version 5
 *
 * @category  Library
 * @package   TechDivision_MageServlet
 * @author    Tim Wagner <tw@techdivision.com>
 * @copyright 2014 TechDivision GmbH <info@techdivision.com>
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 * @link      http://www.appserver.io
 */

namespace TechDivision\MageServlet;

use TechDivision\Http\HttpProtocol;
use TechDivision\Servlet\ServletConfig;
use TechDivision\Servlet\Http\HttpServlet;
use TechDivision\Servlet\Http\HttpServletRequest;
use TechDivision\Servlet\Http\HttpServletResponse;
use TechDivision\MageServlet\Service\Locator\PhpResourceLocator;

/**
 * A servlet implementation for Magento.
 *
 * @category  Library
 * @package   TechDivision_MageServlet
 * @author    Tim Wagner <tw@techdivision.com>
 * @copyright 2014 TechDivision GmbH <info@techdivision.com>
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 * @link      http://www.appserver.io
 */
class MagePersistentServlet extends MageServlet
{

    /**
     * The preloaded Magento instance.
     *
     * @return \Mage_Core_Model_App
     */
    protected $app;

    /**
     * The registry keys to clean after every magento app request.
     *
     * @var array
     */
    protected $registryCleanKeys = array(
        'application_params',
        'current_category',
        'current_product',
        '_singleton/core/layout',
        'current_entity_key',
        '_singleton/core/resource',
        '_resource_singleton/core/website',
        '_resource_singleton/core/store_group',
        '_resource_singleton/core/store',
        '_resource_helper/core',
        '_singleton/core/cookie',
        'controller',
        '_singleton/Mage_Cms_Controller_Router',
        '_singleton/core/factory',
        '_resource_singleton/core/url_rewrite',
        '_helper/core/http',
        '_singleton/core/session',
        '_singleton/core/design_package',
        '_singleton/core/design',
        '_resource_singleton/core/design',
        '_singleton/core/translate',
        '_singleton/core/locale',
        '_singleton/core/translate_inline',
        '_singleton/xmlconnect/observer',
        '_helper/core/string',
        '_singleton/log/visitor',
        '_resource_singleton/log/visitor',
        '_singleton/pagecache/observer',
        '_helper/pagecache',
        '_singleton/persistent/observer',
        '_helper/persistent',
        '_helper/persistent/session',
        '_resource_singleton/persistent/session',
        '_singleton/persistent/observer_session',
        '_singleton/customer/session',
        '_helper/cms/page',
        '_singleton/cms/page',
        '_resource_singleton/cms/page',
        '_helper/page/layout',
        '_helper/page',
        '_singleton/customer/observer',
        '_helper/customer',
        '_helper/catalog',
        '_helper/catalog/map',
        '_helper/catalogsearch',
        '_helper/core',
        '_helper/checkout/cart',
        '_singleton/checkout/cart',
        '_singleton/checkout/session',
        '_helper/checkout',
        '_helper/contacts',
        '_singleton/catalog/session',
        '_helper/core/file_storage_database',
        '_helper/core/js',
        '_helper/directory',
        '_helper/googleanalytics',
        '_helper/adminhtml',
        '_helper/widget',
        '_helper/wishlist',
        '_helper/cms',
        '_helper/catalog/product_compare',
        '_singleton/reports/session',
        '_resource_singleton/reports/product_index_viewed',
        '_helper/catalog/product_flat',
        '_resource_singleton/eav/entity_type',
        '_resource_singleton/catalog/product',
        '_singleton/catalog/factory',
        '_singleton/catalog/product_visibility',
        '_resource_singleton/reports/product_index_compared',
        '_resource_singleton/poll/poll',
        '_resource_singleton/poll/poll_answer',
        '_helper/paypal',
        '_helper/core/cookie',
        '_singleton/core/url',
        '_singleton/core/date'
    );

    /**
     * Loads the necessary files needed.
     *
     * @return void
     */
    public function load()
    {

        if ($this->app == null) {

            error_log("Now reinitialize Magento instance");

            require_once $this->getServletConfig()->getWebappPath() . '/app/Mage.php';

            $this->app = \Mage::app();

            error_log("Initialized app");
        }
    }

    /**
     * Runs the WebApplication
     *
     * @param \TechDivision\Servlet\Http\HttpServletRequest $servletRequest The request instance
     *
     * @return string The web applications content
     */
    public function run(HttpServletRequest $servletRequest)
    {

        try {

            // register the Magento autoloader as FIRST autoloader
            spl_autoload_register(array(new \Varien_Autoload(), 'autoload'), true, true);

            // Varien_Profiler::enable();
            if (isset($_SERVER['MAGE_IS_DEVELOPER_MODE'])) {
                \Mage::setIsDeveloperMode(true);
            }

            ini_set('display_errors', 1);
            umask(0);

            // store or website code
            $mageRunCode = isset($_SERVER['MAGE_RUN_CODE']) ? $_SERVER['MAGE_RUN_CODE'] : '';

            // run store or run website
            $mageRunType = isset($_SERVER['MAGE_RUN_TYPE']) ? $_SERVER['MAGE_RUN_TYPE'] : 'store';

            // set headers sent to false and start output caching
            appserver_set_headers_sent(false);
            ob_start();

            // cleanup mage registry
            foreach ($this->registryCleanKeys as $registryCleanKey) {
                \Mage::unregister($registryCleanKey);
            }

            error_log("Successfully reset Magento");

            // reset and run Magento
            $appRequest = new \Mage_Core_Controller_Request_Http();
            $appResponse = new \Mage_Core_Controller_Response_Http();

            $appRequest->setRequestUri();

            error_log("Set request URI: " . $servletRequest->getUri());

            $this->app->setRequest($appRequest);
            $this->app->setResponse($appResponse);

            $this->app->run(
                array(
                    'scope_code' => $mageRunCode,
                    'scope_type' => $mageRunType,
                    'options'    => array(),
                )
            );

            // write the session back after the request
            session_write_close();

            // We need to init the session anew, so PHP session handling will work like it would in a clean environment
            appserver_session_init();

            // grab the contents generated by Magento
            $content = ob_get_clean();

        } catch (\Exception $e) {
            error_log($content = $e->__toString());
        }

        // return the content
        return $content;
    }
}
