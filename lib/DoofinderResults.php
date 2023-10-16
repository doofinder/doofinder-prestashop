<?php
/**
 * @author JoeZ99 <jzarate@gmail.com>
 * @copyright Doofinder
 * @license   GPLv3
 *
 * DoofinderResults
 *
 * Very thin wrapper of the results obtained from the doofinder server
 * it holds to accessor:
 * - getProperty : get single property of the search results (rpp, page, etc....)
 * - getResults: get an array with the results
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class DoofinderResults
{
    // doofinder status
    const SUCCESS = 'success';      // everything ok
    const NOTFOUND = 'notfound';    // no account with the provided hashid found
    const EXHAUSTED = 'exhausted';  // the account has reached its query limit

    private $properties;
    private $results;
    private $facets;
    private $filter;
    private $banner;
    public $status;

    /**
     * Constructor
     *
     * @param string $jsonString stringified json returned by doofinder search serverphp cs
     */
    public function __construct($jsonString)
    {
        $rawResults = json_decode($jsonString, true);
        foreach ($rawResults as $kkey => $vall) {
            if (!is_array($vall)) {
                $this->properties[$kkey] = $vall;
            }
        }
        // doofinder status
        $this->status = isset($this->properties['doofinder_status']) ?
            $this->properties['doofinder_status'] : self::SUCCESS;

        // results
        $this->results = [];

        if (isset($rawResults['results']) && is_array($rawResults['results'])) {
            $this->results = $rawResults['results'];
        }

        if (isset($rawResults['banner']) && is_array($rawResults['banner'])) {
            $this->banner = $rawResults['banner'];
        }

        // build a friendly filter array
        $this->filter = [];
        // reorder filter, before assigning it to $this
        if (isset($rawResults['filter'])) {
            foreach ($rawResults['filter'] as $filterType => $filters) {
                foreach ($filters as $filterName => $filterProperties) {
                    $this->filter[$filterName] = $filterProperties;
                }
            }
        }

        // facets
        $this->facets = [];
        if (isset($rawResults['facets'])) {
            $this->facets = $rawResults['facets'];

            // mark "selected" true or false according to filters presence
            foreach ($this->facets as $facetName => $facetProperties) {
                if (!isset($facetProperties['_type'])) {
                    $facetProperties['_type'] = null;
                    // $facetProperties['_type'] = (array_key_exists('terms',$facetProperties)?'terms':((array_key_exists('range',$facetProperties))?'range':null));
                }
                switch ($facetProperties['_type']) {
                    case 'terms':
                        foreach ($facetProperties['terms'] as $pos => $term) {
                            if (isset($this->filter[$facetName])
                            && in_array($term['term'], $this->filter[$facetName])) {
                                $this->facets[$facetName]['terms'][$pos]['selected'] = true;
                            } else {
                                $this->facets[$facetName]['terms'][$pos]['selected'] = false;
                            }
                        }
                        break;
                    case 'range':
                        foreach ($facetProperties['ranges'] as $pos => $range) {
                            $this->facets[$facetName]['ranges'][$pos]['selected_from'] = false;
                            $this->facets[$facetName]['ranges'][$pos]['selected_to'] = false;
                            if (isset($this->filter[$facetName]) && isset($this->filter[$facetName]['gte'])) {
                                $this->facets[$facetName]['ranges'][$pos]['selected_from'] =
                                $this->filter[$facetName]['gte'];
                            }
                            if (isset($this->filter[$facetName]) && isset($this->filter[$facetName]['lte'])) {
                                $this->facets[$facetName]['ranges'][$pos]['selected_to'] =
                                $this->filter[$facetName]['lte'];
                            }
                        }
                        break;
                }
            }
        }
    }

    /**
     * getProperty
     *
     * get single property from the results
     *
     * @param string @propertyName: 'results_per_page', 'query', 'max_score', 'page', 'total', 'hashid'
     *
     * @return mixed the value of the property
     */
    public function getProperty($propertyName)
    {
        return array_key_exists($propertyName, $this->properties) ?
            $this->properties[$propertyName] : null;
    }

    /**
     * getResults
     *
     * @return array search results. at the moment, only the 'cooked' version.
     *               Each result is of the form:
     *               array('header'=>...,
     *               'body' => ..,
     *               'price' => ..,
     *               'href' => ...,
     *               'image' => ...,
     *               'type' => ...,
     *               'id' => ..)
     */
    public function getResults()
    {
        return $this->results;
    }

    /**
     * getFacetsNames
     *
     * @return array facets names
     */
    public function getFacetsNames()
    {
        return array_keys($this->facets);
    }

    /**
     * getFacet
     *
     * @param string name the facet name whose results are wanted
     *
     * @return array facet search data
     *               - for terms facets
     *               array(
     *               '_type'=> 'terms',  // type of facet 'terms' or 'range'
     *               'missing'=> 3, // # of elements with no value for this facet
     *               'others'=> 2, // # of terms not present in the search response
     *               'total'=> 6, // # number of possible terms for this facet
     *               'terms'=> array(
     *               array('count'=>6, 'term'=>'Blue', 'selected'=>false),
     *               // in the response, there are 6 'blue' terms
     *               array('count'=>3, 'term': 'Red', 'selected'=>true),
     *               // if 'selected'=>true, that term has been selected as filter
     *               ...
     *               )
     *               )
     *               - for range facets
     *               array(
     *               '_type'=> 'range',
     *               'ranges'=> array(
     *               array(
     *               'count'=>6, // in the response, 6 elements within that range.
     *               'from':0,
     *               'min': 30
     *               'max': 90,
     *               'mean'=>33.2,
     *               'total'=>432,
     *               'total_count'=>6,
     *               'selected_from'=> 34.3
     *               // if present. this value has been used as filter. false otherwise
     *               'selected_to'=> 99.3
     *               // if present. this value has been used as filter. false otherwise
     *               ),
     *               ...
     *               )
     *               )
     */
    public function getFacet($facetName)
    {
        return $this->facets[$facetName];
    }

    /**
     * getFacets
     *
     * get the whole facets associative array:
     *                array('color'=>array(...), 'brand'=>array(...))
     * each array is defined as in getFacet() docstring
     *
     * @return array facets assoc. array
     */
    public function getFacets()
    {
        return $this->facets;
    }

    /**
     * getAppliedFilters
     *
     * get the filters the query has defined
     *                    array('categories' => array(  // filter name . same as facet name
     *                             'Sillas de paseo',   // if simple array, it's a terms facet
     *                             'Sacos sillas de paseo'
     *                             ),
     *                           'color' => array(
     *                              'red',
     *                              'blue'
     *                              ),
     *                           'price' => array(
     *                              'include_upper'=>true, // if 'from' , 'to' keys, it's a range facet
     *                              'from'=>35.19,
     *                              'to'=>9999
     *                              )
     *                         )
     *   MEANING OF THE EXAMPLE FILTER:
     *   "FROM the query results, filter only results that have
     *   ('Sillas de paseo' OR 'Sacos sillas de paseo') categories
     *   AND ('red' OR 'blue') color AND price is BETWEEN 34.3 and 99.3"
     */
    public function getAppliedFilters()
    {
        return $this->filter;
    }

    /**
     * isOk
     *
     * checks if all went well
     *
     * @return bool true if the status is 'success'.
     *              false if the status is not.
     */
    public function isOk()
    {
        return $this->status == self::SUCCESS;
    }

    public function getBanner()
    {
        return $this->banner;
    }
}
