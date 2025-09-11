<?php
/**
 * NOTICE OF LICENSE
 *
 * This file is licenced under the Software License Agreement.
 * With the purchase or the installation of the software in your application
 * you accept the licence agreement.
 *
 * You must not modify, adapt or create derivative works of this source code
 *
 * @author    Doofinder
 * @copyright Doofinder
 * @license   GPLv3
 *
 * Based on original from Author:: JoeZ99 (<jzarate@gmail.com>). all credit to
 * Gilles Devaux (<gilles.devaux@gmail.com>) (https://github.com/flaptor/indextank-php)
 */

namespace PrestaShop\Module\Doofinder\Entity;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * This class handles communication with the Doofinder Search API for a specific account.
 * Supports search queries, filters, pagination, and API connection testing.
 *
 * Usage:
 *   $api = new DoofinderApi($hashid, $apiKey);
 *   $results = $api->query('search term');
 */
class DoofinderApi
{
    /*
     * Basic client for an account.
     * It needs an API url to be constructed.
     * Its only method is to query the doofinder search server
     * Returns a DoofinderResults object
     */

    const DEFAULT_TIMEOUT = 10000;
    const DEFAULT_RPP = 10;
    const DEFAULT_PARAMS_PREFIX = 'dfParam_';
    const DEFAULT_API_VERSION = '6';
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
     * @var array Associative array storing search options and parameters
     */
    private $searchOptions = [];

    /**
     * @var string Prefix to prepend to serialized parameters
     */
    private $paramsPrefix = self::DEFAULT_PARAMS_PREFIX;

    /**
     * @var array Request array used when unserializing from GET or POST
     */
    private $serializationArray;

    /**
     * @var string Parameter name used for the main query string
     */
    private $queryParameter = 'query';

    /**
     * @var array List of allowed parameters when serializing/deserializing
     */
    private $allowedParameters = ['page', 'rpp', 'timeout', 'types', 'filter', 'query_name', 'transformer'];

    /**
     * @var string Region/zone key, used for regional API endpoints
     */
    private $zone;

    // request parameters that Doofinder handle

    /**
     * Constructor. Search Engine's hashid and api version set here
     *
     * @param string $hashid the Search Engine's hashid
     * @param bool $fromParams if set, the object is unserialized from GET or POST params
     * @param array $init_options. associative array with some options:
     *                             -'prefix' (default: 'dfParam_')=> the prefix to use when serializing.
     *                             -'queryParameter' (default: 'query') => the parameter used for querying
     *                             -'apiVersion' (default: '4')=> the api of the search server to query
     *                             -'restrictedRequest'(default: $_REQUEST):  =>restrict request object
     *                             to look for params when unserializing. either 'get' or 'post'
     */
    public function __construct($hashid, $apiKey, $fromParams = false, $init_options = [])
    {
        $zone_key_array = explode('-', $apiKey);
        $this->apiKey = end($zone_key_array);
        $this->zone = \Configuration::get('DF_REGION');
        $this->url = UrlManager::getRegionalUrl(DoofinderConstants::DOOPHOENIX_REGION_URL, $this->zone);

        if (array_key_exists('prefix', $init_options)) {
            $this->paramsPrefix = $init_options['prefix'];
        }

        $this->allowedParameters = array_map([$this, 'addprefix'], $this->allowedParameters);

        if (array_key_exists('queryParameter', $init_options)) {
            $this->queryParameter = $init_options['queryParameter'];
        } else {
            $this->queryParameter = $this->paramsPrefix . $this->queryParameter;
        }

        $this->apiVersion = array_key_exists('apiVersion', $init_options) ?
            $init_options['apiVersion'] : self::DEFAULT_API_VERSION;
        $this->serializationArray = $_REQUEST;
        if (array_key_exists('restrictedRequest', $init_options)) {
            switch (strtolower($init_options['restrictedRequest'])) {
                case 'get':
                    $this->serializationArray = $_GET;
                    break;
                case 'post':
                    $this->serializationArray = $_POST;
                    break;
            }
        }
        $patt = '/^[0-9a-f]{32}$/i';

        if ($hashid != false && !preg_match($patt, $hashid)) {
            throw new DoofinderException('Wrong hashid');
        } else {
            $this->hashid = $hashid;
        }

        if (!in_array($this->apiVersion, ['5', '4', '3.0', '1.0'])) {
            throw new DoofinderException('Wrong API');
        }

        if ($fromParams) {
            $this->fromQuerystring();
        }
    }

    public function getOptions()
    {
        return $this->apiCall('options/' . $this->hashid);
    }

    /**
     * query. makes the query to the doofinder search server.
     * also set several search parameters through it's $options argument
     *
     * @param string $query the search query
     * @param int $page the page number or the results to show
     * @param array $options query options:
     *                       - 'rpp'=> number of results per page. default 10
     *                       - 'timeout' => timeout after which the search server drops the conn.
     *                       defaults to 10 seconds
     *                       - 'types' => types of index to search at. default: all.
     *                       - 'filter' => filter to apply. ['color'=>['red','blue'], 'price'=>['from'=>33]]
     *                       - any other param will be sent as a request parameter
     *
     * @return DoofinderResults results
     */
    public function query($query = null, $page = null, $options = [])
    {
        if ($query) {
            $this->searchOptions['query'] = $query;
        }
        if ($page) {
            $this->searchOptions['page'] = (int) $page;
        }
        foreach ($options as $optionName => $optionValue) {
            $this->searchOptions[$optionName] = $options[$optionName];
        }

        $params = $this->searchOptions;

        // translate filters
        if (!empty($params['filter'])) {
            foreach ($params['filter'] as $filterName => $filterValue) {
                $params['filter'][$filterName] = $this->translateFilter($filterValue);
            }
        }

        // no query? then match all documents
        if (!$this->optionExists('query') || !trim($this->searchOptions['query'])) {
            $params['query_name'] = 'match_all';
        }

        // if filters without query_name, pre-query first to obtain it.
        if (empty($params['query_name']) && !empty($params['filter'])) {
            $filter = $params['filter'];
            unset($params['filter']);
            $dfResults = new DoofinderResults($this->apiCall('search', $params));
            $params['query_name'] = $dfResults->getProperty('query_name');
            $params['filter'] = $filter;
        }
        $dfResults = new DoofinderResults($this->apiCall('search', $params));
        $this->searchOptions['query'] = $dfResults->getProperty('query');

        return $dfResults;
    }

    /**
     * getFilters
     *
     * gets all filters and their configs. Used in Conversion pages.
     *
     * @return array|false assoc array filterName => filterConditions, or false if no filters are set
     */
    public function getFilters()
    {
        if (isset($this->searchOptions['filter'])) {
            return $this->searchOptions['filter'];
        } else {
            return false;
        }
    }

    /**
     * Populates the object's search options from query string parameters.
     *
     * This method reads the serialized state from $this->serializationArray
     * (typically $_GET, $_POST, or $_REQUEST depending on construction)
     * and extracts only the parameters that belong to Doofinder (checked via belongsToDoofinder).
     * The extracted values are stored in $this->searchOptions.
     *
     * Example:
     *   If a query string contains dfParam_rpp=20 and dfParam_page=2,
     *   after calling this method, $this->searchOptions['rpp'] = 20
     *   and $this->searchOptions['page'] = 2.
     *
     * @return void
     */
    public function fromQuerystring()
    {
        $doofinderReqParams = array_filter(array_keys($this->serializationArray), [$this, 'belongsToDoofinder']);

        foreach ($doofinderReqParams as $dfReqParam) {
            $key = 'query';
            if ($dfReqParam !== $this->queryParameter) {
                $key = substr($dfReqParam, strlen($this->paramsPrefix));
            }
            $this->searchOptions[$key] = $this->serializationArray[$dfReqParam];
        }
    }

    /**
     * Adds the parameter prefix to a given string.
     *
     * @param string $value the parameter name to prefix
     *
     * @return string the prefixed parameter name
     */
    private function addprefix($value)
    {
        return $this->paramsPrefix . $value;
    }

    /**
     * Translates a range filter from legacy format to Elasticsearch format.
     *
     * Converts:
     *   ['from' => 9, 'to' => 20]
     * To:
     *   ['gte' => 9, 'lte' => 20]
     *
     * @param array $filter the filter array to translate
     *
     * @return array the translated filter array
     */
    private function translateFilter($filter)
    {
        $new_filter = [];
        foreach ($filter as $key => $value) {
            if ($key === 'from') {
                $new_filter['gte'] = $value;
            } elseif ($key === 'to') {
                $new_filter['lte'] = $value;
            } else {
                $new_filter[$key] = $value;
            }
        }

        return $new_filter;
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
        $args = http_build_query($this->sanitize($params)); // remove any null value from the array

        $url = $this->url . '/' . $this->apiVersion . '/' . $entryPoint . '?' . $args;

        $session = curl_init($url);
        curl_setopt($session, CURLOPT_CUSTOMREQUEST, 'GET');
        curl_setopt($session, CURLOPT_HEADER, false); // Tell curl not to return headers
        curl_setopt($session, CURLOPT_RETURNTRANSFER, true); // Tell curl to return the response
        curl_setopt($session, CURLOPT_HTTPHEADER, $this->reqHeaders()); // Adding request headers
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
     * Determines if a parameter belongs to the Doofinder serialization parameters.
     *
     * @param string $paramName parameter name to check
     *
     * @return bool true if the parameter belongs to Doofinder, false otherwise
     */
    private function belongsToDoofinder($paramName)
    {
        if ($pos = strpos($paramName, '[')) {
            $paramName = substr($paramName, 0, $pos);
        }

        return in_array($paramName, $this->allowedParameters) || $paramName == $this->queryParameter;
    }

    /**
     * Checks whether a search option is defined in $this->searchOptions.
     *
     * @param string $optionName the option name to check
     *
     * @return bool true if the option exists, false otherwise
     */
    private function optionExists($optionName)
    {
        return array_key_exists($optionName, $this->searchOptions);
    }

    /**
     * Get results per page
     *
     * @return int
     */
    public function getRpp()
    {
        $rpp = $this->optionExists('rpp') ? $this->searchOptions['rpp'] : null;
        $rpp = $rpp ? $rpp : self::DEFAULT_RPP;

        return $rpp;
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
