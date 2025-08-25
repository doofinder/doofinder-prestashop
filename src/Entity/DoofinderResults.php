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

namespace PrestaShop\Module\Doofinder\Src\Entity;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * DoofinderResults - Wrapper class for Doofinder search API results
 *
 * This class provides a clean interface to access search results, facets, filters,
 * and other properties returned by the Doofinder search server. It handles both
 * terms and range facets, manages filter states, and provides status information.
 */
class DoofinderResults
{
    // doofinder status
    /** @var string Success status - everything ok */
    const SUCCESS = 'success';      // everything ok

    /** @var string Not found status - no account with the provided hashid found */
    const NOTFOUND = 'notfound';    // no account with the provided hashid found

    /** @var string Exhausted status - the account has reached its query limit */
    const EXHAUSTED = 'exhausted';  // the account has reached its query limit

    /** @var array<string, mixed> Search result properties (rpp, page, query, etc.) */
    private $properties;

    /** @var array<int, array<string, mixed>> Search results array */
    private $results;

    /** @var array<string, array<string, mixed>> Facets data with type and terms/ranges */
    private $facets;

    /** @var array<string, mixed> Applied filters for the current search */
    private $filter;

    /** @var array<string, mixed>|null Banner data if present */
    private $banner;

    /** @var string Current status of the Doofinder request */
    public $status;

    /**
     * Constructor - Parse JSON response from Doofinder search server
     *
     * @param string $jsonString Stringified JSON returned by Doofinder search server
     *
     * @throws \JsonException When JSON parsing fails
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
     * Get a single property from the search results
     *
     * Retrieves properties like 'results_per_page', 'query', 'max_score', 'page', 'total', 'hashid'
     *
     * @param string $propertyName Property name to retrieve
     *
     * @return mixed The value of the property, or null if not found
     */
    public function getProperty($propertyName)
    {
        return array_key_exists($propertyName, $this->properties) ?
            $this->properties[$propertyName] : null;
    }

    /**
     * Get the search results array
     *
     * Returns the 'cooked' version of search results. Each result contains:
     * - header: Result header/title
     * - body: Result description/content
     * - price: Product price
     * - href: Result URL
     * - image: Result image URL
     * - type: Result type
     * - id: Result identifier
     *
     * @return array<int, array<string, mixed>> Search results array
     */
    public function getResults()
    {
        return $this->results;
    }

    /**
     * Get the names of all available facets
     *
     * @return array<int, string> Array of facet names
     */
    public function getFacetsNames()
    {
        return array_keys($this->facets);
    }

    /**
     * Get a specific facet by name
     *
     * Returns facet data with the following structure:
     *
     * For terms facets:
     * array(
     *   '_type'=> 'terms',  // type of facet 'terms' or 'range'
     *   'missing'=> 3, // # of elements with no value for this facet
     *   'others'=> 2, // # of terms not present in the search response
     *   'total'=> 6, // # number of possible terms for this facet
     *   'terms'=> array(
     *     array('count'=>6, 'term'=>'Blue', 'selected'=>false),
     *     // in the response, there are 6 'blue' terms
     *     array('count'=>3, 'term': 'Red', 'selected'=>true),
     *     // if 'selected'=>true, that term has been selected as filter
     *     ...
     *   )
     * )
     *
     * For range facets:
     * array(
     *   '_type'=> 'range',
     *   'ranges'=> array(
     *     array(
     *       'count'=>6, // in the response, 6 elements within that range.
     *       'from':0,
     *       'min': 30
     *       'max': 90,
     *       'mean'=>33.2,
     *       'total'=>432,
     *       'total_count'=>6,
     *       'selected_from'=> 34.3
     *       // if present. this value has been used as filter. false otherwise
     *       'selected_to'=> 99.3
     *       // if present. this value has been used as filter. false otherwise
     *     ),
     *     ...
     *   )
     * )
     *
     * @param string $facetName The facet name whose results are wanted
     *
     * @return array<string, mixed>|null Facet search data or null if facet doesn't exist
     */
    public function getFacet($facetName)
    {
        return isset($this->facets[$facetName]) ? $this->facets[$facetName] : null;
    }

    /**
     * Get all facets data
     *
     * Returns the complete facets associative array where each key is a facet name
     * and each value is the facet data as described in getFacet() documentation
     *
     * @return array<string, array<string, mixed>> Complete facets associative array
     */
    public function getFacets()
    {
        return $this->facets;
    }

    /**
     * Get the currently applied filters for the search query
     *
     * Returns an array representing the active filters:
     * - Simple arrays for terms facets (e.g., categories, colors)
     * - Associative arrays for range facets (e.g., price with 'from', 'to' keys)
     *
     * Example structure:
     * [
     *   'categories' => ['Sillas de paseo', 'Sacos sillas de paseo'],
     *   'color' => ['red', 'blue'],
     *   'price' => ['from' => 35.19, 'to' => 9999, 'include_upper' => true]
     * ]
     *
     * @return array<string, mixed> Currently applied filters
     */
    public function getAppliedFilters()
    {
        return $this->filter;
    }

    /**
     * Check if the search request was successful
     *
     * @return bool True if status is 'success', false otherwise
     */
    public function isOk()
    {
        return $this->status == self::SUCCESS;
    }

    /**
     * Get banner data if present in the search results
     *
     * @return array<string, mixed>|null Banner data array or null if no banner
     */
    public function getBanner()
    {
        return $this->banner;
    }
}
