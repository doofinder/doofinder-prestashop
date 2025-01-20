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

namespace PrestaShop\Module\Doofinder\Src\Entity;

if (!defined('_PS_VERSION_')) {
    exit;
}

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

    private $apiKey; // user API_KEY
    private $hashid; // hashid of the doofinder account
    private $apiVersion;
    private $url;
    private $results;
    private $query;
    private $searchOptions = [];  // assoc. array with doofinder options to be sent as request parameters
    private $page = 1; // the page of the search results we're at
    private $queryName; // the name of the last successful query made
    private $lastQuery; // the last successful query made
    private $total; // total number of results obtained
    private $maxScore;
    private $paramsPrefix = self::DEFAULT_PARAMS_PREFIX;
    private $serializationArray;
    private $queryParameter = 'query'; // the parameter used for querying
    private $allowedParameters = ['page', 'rpp', 'timeout', 'types', 'filter', 'query_name', 'transformer'];
    private $zone;
    private $filter;
    // request parameters that doofinder handle

    /**
     * Constructor. account's hashid and api version set here
     *
     * @param string $hashid the account's hashid
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

    private function addprefix($value)
    {
        return $this->paramsPrefix . $value;
    }

    /*
     * translateFilter
     *
     * translates a range filter to the new ES format
     * 'from'=>9, 'to'=>20 to 'gte'=>9, 'lte'=>20
     *
     * @param array $filter
     * @return array the translated filter
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

    private function reqHeaders()
    {
        $headers = [];
        $headers[] = 'Expect:'; // Fixes the HTTP/1.1 417 Expectation Failed
        $authHeaderName = $this->apiVersion == '4' ? 'API Token: ' : 'authorization: ';
        $headers[] = $authHeaderName . $this->apiKey; // API Authorization

        return $headers;
    }

    private function apiCall($entry_point = 'search', $params = [])
    {
        $params['hashid'] = $this->hashid;
        $args = http_build_query($this->sanitize($params)); // remove any null value from the array

        $url = $this->url . '/' . $this->apiVersion . '/' . $entry_point . '?' . $args;

        $session = curl_init($url);
        curl_setopt($session, CURLOPT_CUSTOMREQUEST, 'GET');
        curl_setopt($session, CURLOPT_HEADER, false); // Tell curl not to return headers
        curl_setopt($session, CURLOPT_RETURNTRANSFER, true); // Tell curl to return the response
        curl_setopt($session, CURLOPT_HTTPHEADER, $this->reqHeaders()); // Adding request headers
        // IF YOU MAKE REQUEST FROM LOCALHOST OR HAVE SERVER CERTIFICATE ISSUE
        $disableSSLVerify = \Configuration::get('DF_DSBL_HTTPS_CURL');
        if ($disableSSLVerify) {
            curl_setopt($session, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($session, CURLOPT_SSL_VERIFYPEER, 0);
        }
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
        $this->page = $dfResults->getProperty('page');
        $this->total = $dfResults->getProperty('total');
        $this->searchOptions['query'] = $dfResults->getProperty('query');
        $this->maxScore = $dfResults->getProperty('max_score');
        $this->queryName = $dfResults->getProperty('query_name');
        $this->lastQuery = $dfResults->getProperty('query');

        return $dfResults;
    }

    /**
     * hasNext
     *
     * @return bool true if there is another page of results
     */
    public function hasNext()
    {
        return $this->page * $this->getRpp() < $this->total;
    }

    /**
     * hasPrev
     *
     * @return true if there is a previous page of results
     */
    public function hasPrev()
    {
        return ($this->page - 1) * $this->getRpp() > 0;
    }

    /**
     * getPage
     *
     * obtain the current page number
     *
     * @return int the page number
     */
    public function getPage()
    {
        return $this->page;
    }

    /**
     * setFilter
     *
     * set a filter for the query
     *
     * @param string filterName the name of the filter to set
     * @param array filter if simple array, terms filter assumed
     *                     if 'from', 'to' in keys, range filter assumed
     */
    public function setFilter($filterName, $filter)
    {
        if (!$this->optionExists('filter')) {
            $this->searchOptions['filter'] = [];
        }
        $this->searchOptions['filter'][$filterName] = $filter;
    }

    /**
     * getFilter
     *
     * get conditions for certain filter
     *
     * @param string filterName
     *
     * @return array filter conditions: - simple array if terms filter
     *               - 'from', 'to'  assoc array if range f
     * @return false if no filter definition found
     */
    public function getFilter($filterName)
    {
        if ($this->optionExists('filter') && isset($this->searchOptions['filter'][$filterName])) {
            return $this->filter[$filterName];
        }

        return false;
    }

    /**
     * getFilters
     *
     * get all filters and their configs
     *
     * @return array assoc array filterName => filterConditions
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
     * addTerm
     *
     * add a term to a terms filter
     *
     * @param string filterName the filter to add the term to
     * @param string term the term to add
     */
    public function addTerm($filterName, $term)
    {
        if (!$this->optionExists('filter')) {
            $this->searchOptions['filter'] = [$filterName => []];
        }
        if (!isset($this->searchOptions['filter'][$filterName])) {
            $this->filter[$filterName] = [];
            $this->searchOptions['filter'][$filterName] = [];
        }
        $this->filter[$filterName][] = $term;
        $this->searchOptions['filter'][$filterName][] = $term;
    }

    /**
     * removeTerm
     *
     * remove a term from a terms filter
     *
     * @param string filterName the filter to remove the term from
     * @param string term the term to be removed
     */
    public function removeTerm($filterName, $term)
    {
        if ($this->optionExists('filter') && isset($this->searchOptions['filter'][$filterName])
            && in_array($term, $this->searchOptions['filter'][$filterName])
        ) {
            $this->searchOptions['filter'][$filterName] =
            array_filter($this->searchOptions['filter'][$filterName], function ($value) use ($term) {
                return $value !== $term;
            });
        }
    }

    /**
     * setRange
     *
     * set a range filter
     *
     * @param string filterName the filter to set
     * @param int from the lower bound value. included
     * @param int to the upper bound value. included
     */
    public function setRange($filterName, $from = null, $to = null)
    {
        if (!$this->optionExists('filter')) {
            $this->searchOptions['filter'] = [$filterName => []];
        }
        if (!isset($this->searchOptions['filter'][$filterName])) {
            $this->searchOptions['filter'][$filterName] = [];
        }
        if ($from) {
            $this->searchOptions['filter'][$filterName]['from'] = $from;
        }
        if ($to) {
            $this->searchOptions['filter'][$filterName]['to'] = $from;
        }
    }

    /**
     * toQuerystring
     *
     * 'serialize' the object's state to querystring params
     *
     * @param int $page the pagenumber. defaults to the current page
     */
    public function toQuerystring($page = null)
    {
        $toParams = [];
        foreach ($this->searchOptions as $paramName => $paramValue) {
            if ($paramName == 'query') {
                $toParams[$this->queryParameter] = $paramValue;
            } else {
                $toParams[$this->paramsPrefix . $paramName] = $paramValue;
            }
        }
        if ($page) {
            $toParams[$this->paramsPrefix . 'page'] = $page;
        }

        return http_build_query($toParams);
    }

    /**
     * fromQuerystring
     *
     * obtain object's state from querystring params
     *
     * @param string $params where to obtain params from:
     *                       - 'GET' $_GET params (default)
     *                       - 'POST' $_POST params
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
     * sanitize
     *
     * Clean array of keys with empty values
     *
     * @param array $params array to be cleaned
     *
     * @return array array with no empty keys
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
     * belongsToDoofinder
     *
     * to know if certain parameter name belongs to doofinder serialization parameters
     *
     * @param string $paramName name of the param
     *
     * @return bool true or false
     */
    private function belongsToDoofinder($paramName)
    {
        if ($pos = strpos($paramName, '[')) {
            $paramName = substr($paramName, 0, $pos);
        }

        return in_array($paramName, $this->allowedParameters) || $paramName == $this->queryParameter;
    }

    /**
     * optionExists
     *
     * checks whether a search option is defined in $this->searchOptions
     *
     * @param string $optionName
     *
     * @return bool
     */
    private function optionExists($optionName)
    {
        return array_key_exists($optionName, $this->searchOptions);
    }

    /**
     * nextPage
     *
     * obtain the results for the next page
     *
     * @return DoofinderResults if there are results
     * @return null otherwise
     */
    public function nextPage()
    {
        if ($this->hasNext()) {
            return $this->query($this->lastQuery, $this->page + 1);
        }

        return null;
    }

    /**
     * prevPage
     *
     * obtain results for the previous page
     *
     * @return DoofinderResults
     * @return null otherwise
     */
    public function prevPage()
    {
        if ($this->hasPrev()) {
            return $this->query($this->lastQuery, $this->page - 1);
        }

        return null;
    }

    /**
     * numPages
     *
     * @return int the number of pages
     */
    public function numPages()
    {
        return ceil($this->total / $this->getRpp());
    }

    public function getRpp()
    {
        $rpp = $this->optionExists('rpp') ? $this->searchOptions['rpp'] : null;
        $rpp = $rpp ? $rpp : self::DEFAULT_RPP;

        return $rpp;
    }

    /**
     * setApiVersion
     *
     * sets the api version to use.
     *
     * @param string $apiVersion the api version , '1.0' or '3.0' or '4'
     */
    public function setApiVersion($apiVersion)
    {
        $this->apiVersion = $apiVersion;
    }

    /**
     * setPrefix
     *
     * sets the prefix that will be used for serialization to querystring params
     *
     * @param string $prefix the prefix
     */
    public function setPrefix($prefix)
    {
        $this->paramsPrefix = $prefix;
    }

    /**
     * setQueryName
     *
     * sets query_name
     * CAUTION: node will complain if this is wrong
     */
    public function setQueryName($queryName)
    {
        $this->queryName = $queryName;
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
        foreach (\Language::getLanguages(true, $context->shop->id) as $lang) {
            if (!$onlyOneLang || ($onlyOneLang && $lang['iso_code'])) {
                $langIso = \Tools::strtoupper($lang['iso_code']);
                $langFullIso = (isset($lang['language_code'])) ? \Tools::strtoupper($lang['language_code']) : $langIso;
                $hashid = \Configuration::get('DF_HASHID_' . $currency . '_' . $langFullIso);
                $apiKey = \Configuration::get('DF_API_KEY');
                if ($hashid && $apiKey) {
                    try {
                        $dfOptions = $this->getOptions();
                        if ($dfOptions) {
                            $opt = json_decode($dfOptions, true);
                            if (isset($opt['query_limit_reached']) && $opt['query_limit_reached']) {
                                $msg = $module->l('Error: Credentials OK but limit query reached for Search Engine - ', 'doofinderapi') . $langFullIso;
                                $messages .= DoofinderAdminPanelView::displayErrorCtm($msg);
                            } else {
                                $result = true;
                                $msg = $module->l('Connection successful for Search Engine - ', 'doofinderapi') . $langFullIso;
                                $messages .= DoofinderAdminPanelView::displayConfirmationCtm($msg);
                            }
                        } else {
                            $msg = $module->l('Error: no connection for Search Engine - ', 'doofinderapi') . $langFullIso;
                            $messages .= DoofinderAdminPanelView::displayErrorCtm($msg);
                        }
                    } catch (DoofinderException $e) {
                        $messages .= DoofinderAdminPanelView::displayErrorCtm($e->getMessage() . ' - Search Engine ' . $langFullIso);
                    } catch (\Exception $e) {
                        $msg = $e->getMessage() . ' - Search Engine ';
                        $messages .= DoofinderAdminPanelView::displayErrorCtm($msg . $langFullIso);
                    }
                } else {
                    $msg = $module->l('Empty Api Key or empty Search Engine - ', 'doofinderapi') . $langFullIso;
                    $messages .= DoofinderAdminPanelView::displayWarningCtm($msg);
                }
            }
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
