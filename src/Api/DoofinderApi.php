<?php
/**
 * NOTICE OF LICENSE
 *
 * This file is licensed under the Software License Agreement.
 * With the purchase or the installation of the software in your application
 * you accept the license agreement.
 *
 * You must not modify, adapt or create derivative works of this source code
 *
 * @author    Doofinder
 * @copyright Doofinder
 * @license   MIT
 *
 * Based on original from Author:: JoeZ99 (<jzarate@gmail.com>). all credit to
 * Gilles Devaux (<gilles.devaux@gmail.com>) (https://github.com/flaptor/indextank-php)
 */

namespace PrestaShop\Module\Doofinder\Api;

if (!defined('_PS_VERSION_')) {
    exit;
}

use PrestaShop\Module\Doofinder\Core\DoofinderConstants;
use PrestaShop\Module\Doofinder\Exception\DoofinderException;
use PrestaShop\Module\Doofinder\Manager\UrlManager;
use PrestaShop\Module\Doofinder\View\DoofinderAdminPanelView;

/**
 * This class handles communication with the Doofinder Search API.
 * Supports API connection testing and options retrieval.
 *
 * Usage:
 *   $api = new DoofinderApi($hashid, $apiKey);
 *   $messages = $api->checkConnection($module);
 */
class DoofinderApi
{
    const API_VERSION = '5';
    const VERSION = '5.2.3';

    /**
     * @var string User's API key for Doofinder authentication
     */
    private $apiKey;

    /**
     * @var string Hashid of the Search Engine
     */
    private $hashid;

    /**
     * @var string API version to use for requests
     */
    private $apiVersion;

    /**
     * @var string Base URL of the Doofinder API, depends on region
     */
    private $url;

    /**
     * @var string Region/zone key, used for regional API endpoints
     */
    private $zone;

    /**
     * Constructor. Search Engine's hashid and api version set here
     *
     * @param string $hashid the Search Engine's hashid
     * @param string $apiKey the API key for authentication
     */
    public function __construct($hashid, $apiKey)
    {
        $zone_key_array = explode('-', $apiKey);
        $this->apiKey = end($zone_key_array);
        $this->zone = \Configuration::get('DF_REGION');
        $this->url = UrlManager::getRegionalUrl(DoofinderConstants::DOOPHOENIX_REGION_URL, $this->zone);
        $this->apiVersion = self::API_VERSION;

        $patt = '/^[0-9a-f]{32}$/i';

        if ($hashid != false && !preg_match($patt, $hashid)) {
            throw new DoofinderException('Wrong hashid');
        } else {
            $this->hashid = $hashid;
        }
    }

    /**
     * Get options for the search engine
     *
     * @return string JSON response with search engine options
     */
    public function getOptions()
    {
        return $this->apiCall('options/' . $this->hashid);
    }

    /**
     * Builds the request headers for the API call.
     *
     * @return array array of headers for cURL request
     */
    private function reqHeaders()
    {
        $headers = [];
        $headers[] = 'Expect:'; // Fixes the HTTP/1.1 417 Expectation Failed
        $authHeaderName = $this->apiVersion == '4' ? 'API Token: ' : 'authorization: ';
        $headers[] = $authHeaderName . $this->apiKey; // API Authorization

        return $headers;
    }

    /**
     * Executes a GET request to the Doofinder API.
     *
     * @param string $entryPoint API entry point (e.g., 'search', 'options').
     * @param array $params associative array of query parameters
     *
     * @return string API response as raw JSON string
     *
     * @throws DoofinderException if the request fails or returns a non-2xx HTTP status
     */
    private function apiCall($entryPoint = 'search', $params = [])
    {
        $params['hashid'] = $this->hashid;
        $args = http_build_query($this->sanitize($params));

        $url = $this->url . '/' . $this->apiVersion . '/' . $entryPoint . '?' . $args;

        $session = curl_init($url);
        curl_setopt($session, CURLOPT_CUSTOMREQUEST, 'GET');
        curl_setopt($session, CURLOPT_HEADER, false);
        curl_setopt($session, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($session, CURLOPT_HTTPHEADER, $this->reqHeaders());
        $response = curl_exec($session);
        $httpCode = curl_getinfo($session, CURLINFO_HTTP_CODE);
        $debugCurlError = \Configuration::get('DF_DEBUG_CURL');
        if ($debugCurlError) {
            echo curl_errno($session);
        }
        curl_close($session);

        if (floor($httpCode / 100) == 2) {
            return $response;
        }

        if (0 === $httpCode) {
            $response = 'Connection could not be established';
        }

        throw new DoofinderException('Error code: ' . $httpCode . ' - ' . $response, $httpCode);
    }

    /**
     * Sanitizes an array by removing keys with empty values.
     *
     * Recursively removes empty strings, null values, and whitespace-only strings.
     *
     * @param array $params array to sanitize
     *
     * @return array sanitized array
     */
    private function sanitize($params)
    {
        $result = [];
        foreach ($params as $name => $value) {
            if (is_array($value)) {
                $result[$name] = $this->sanitize($value);
            } elseif (trim($value)) {
                $result[$name] = $value;
            }
        }

        return $result;
    }

    /**
     * Perform an API connection test
     *
     * @param \Doofinder $module Module main class to be able to use l() function here
     * @param bool $onlyOneLang
     *
     * @return bool|string
     */
    public function checkConnection($module, $onlyOneLang = false)
    {
        $result = false;
        $messages = '';
        $currency = \Tools::strtoupper(\Context::getContext()->currency->iso_code);
        $context = \Context::getContext();
        $apiKeyMsgAlreadyShown = false;
        $messagesArray = [
            'errorQueryLimit' => [
                'message' => $module->l('Error: Credentials OK but limit query reached for Search Engine - ', 'doofinderapi'),
                'displayFunction' => 'displayErrorCtm',
                'languages' => [],
            ],
            'errorNoConnection' => [
                'message' => $module->l('Error: no connection for Search Engine - ', 'doofinderapi'),
                'displayFunction' => 'displayErrorCtm',
                'languages' => [],
            ],
            'emptySearchEngine' => [
                'message' => $module->l('Empty Search Engine', 'doofinderapi') . ' - ',
                'displayFunction' => 'displayWarningCtm',
                'languages' => [],
            ],
            'success' => [
                'message' => $module->l('Connection successful for Search Engine - ', 'doofinderapi'),
                'displayFunction' => 'displayConfirmationCtm',
                'languages' => [],
            ],
        ];
        foreach (\Language::getLanguages(true, $context->shop->id) as $lang) {
            if (!$onlyOneLang || !empty($lang['iso_code'])) {
                $langIso = \Tools::strtoupper($lang['iso_code']);
                $langFullIso = (isset($lang['language_code'])) ? \Tools::strtoupper($lang['language_code']) : $langIso;
                $hashid = \Configuration::get('DF_HASHID_' . $currency . '_' . $langFullIso);
                $this->hashid = $hashid;
                $apiKey = \Configuration::get('DF_API_KEY');
                $isAdvParamPresent = (bool) \Tools::getValue('adv', 0);
                if ($hashid && $apiKey) {
                    try {
                        $dfOptions = $this->getOptions();
                        if ($dfOptions) {
                            $opt = json_decode($dfOptions, true);
                            if (isset($opt['query_limit_reached']) && $opt['query_limit_reached']) {
                                $messagesArray['errorQueryLimit']['languages'][] = sprintf('(%s)', $langFullIso);
                            } else {
                                $result = true;
                                if ($isAdvParamPresent) {
                                    $messagesArray['success']['languages'][] = sprintf('(%s)', $langFullIso);
                                }
                            }
                        } else {
                            if ($isAdvParamPresent) {
                                $messagesArray['errorNoConnection']['languages'][] = sprintf('(%s)', $langFullIso);
                            }
                        }
                    } catch (DoofinderException $e) {
                        $messages .= DoofinderAdminPanelView::displayErrorCtm($e->getMessage() . ' Search Engine ' . $langFullIso);
                    } catch (\Exception $e) {
                        $msg = $e->getMessage() . ' - Search Engine ';
                        $messages .= DoofinderAdminPanelView::displayErrorCtm($msg . $langFullIso);
                    }
                } else {
                    if (!$apiKeyMsgAlreadyShown && !$apiKey) {
                        $msg = $module->l('Empty Api Key', 'doofinderapi');
                        $messages .= DoofinderAdminPanelView::displayWarningCtm($msg);
                        $apiKeyMsgAlreadyShown = true;
                    }
                    if ($isAdvParamPresent && !$hashid) {
                        $messagesArray['emptySearchEngine']['languages'][] = sprintf('(%s)', $langFullIso);
                    }
                }
            }
        }

        foreach ($messagesArray as $messageArray) {
            if (empty($messageArray['languages'])) {
                continue;
            }
            $msg = $messageArray['message'] . implode(', ', $messageArray['languages']);
            $messages .= DoofinderAdminPanelView::{$messageArray['displayFunction']}($msg);
        }

        if ($onlyOneLang) {
            return $result;
        } else {
            return $messages;
        }
    }

    /**
     * Check the connection to the API using the saved API KEY
     *
     * @param bool $text If the response is received as a string
     *
     * @return bool|string
     */
    public static function checkApiKey($text = false)
    {
        $result = \Db::getInstance()->getValue('SELECT id_configuration FROM ' . _DB_PREFIX_
            . 'configuration WHERE name = "DF_API_KEY" AND (value IS NOT NULL OR value <> "")');
        $statusText = (($result) ? 'OK' : 'KO');

        return ($text) ? $statusText : $result;
    }
}
