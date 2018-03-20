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
 */

require_once __DIR__.DIRECTORY_SEPARATOR.'DoofinderFiltersConverter.php';
require_once __DIR__.DIRECTORY_SEPARATOR.'DoofinderFacetsURLSerializer.php';
require_once __DIR__.DIRECTORY_SEPARATOR.'DoofinderRangeAggregator.php';

use PrestaShop\PrestaShop\Core\Product\Search\URLFragmentSerializer;
use PrestaShop\PrestaShop\Core\Product\Search\ProductSearchProviderInterface;
use PrestaShop\PrestaShop\Core\Product\Search\ProductSearchContext;
use PrestaShop\PrestaShop\Core\Product\Search\ProductSearchQuery;
use PrestaShop\PrestaShop\Core\Product\Search\ProductSearchResult;
use PrestaShop\PrestaShop\Core\Product\Search\Facet;
use PrestaShop\PrestaShop\Core\Product\Search\FacetCollection;
use PrestaShop\PrestaShop\Core\Product\Search\Filter;
use PrestaShop\PrestaShop\Core\Product\Search\SortOrder;

class DoofinderProductSearchProvider implements ProductSearchProviderInterface
{
    private $module;
    private $filtersConverter;
    private $facetsSerializer;
    private $features_slug = array();
    private $group_attributes_slug = array();

    private $doofinderSearchQuery = null;
    
    public function __construct(Doofinder $module)
    {
        $this->module = $module;
        $this->filtersConverter = new DoofinderFiltersConverter();
        $this->facetsSerializer = new DoofinderFacetsURLSerializer();
    }

    public function getFacetCollectionFromEncodedFacets(
        ProductSearchQuery $query
    ) {
        // do not compute range filters, all info we need is encoded in $encodedFacets
        $compute_range_filters = false;
        
        $result = $this->doofinderSearchQuery;
        
        $presta17Filters = $this->getFormatedFilters($result['facets'], $result['filters'],$query->getSearchString());

        $queryTemplate = $this->filtersConverter->getFacetsFromFacetedSearchFilters(
            $presta17Filters
        );

        $facets = $this->facetsSerializer->setFiltersFromEncodedFacets(
            $queryTemplate,
            $query->getEncodedFacets()
        );

        return (new FacetCollection())->setFacets($facets);
    }
    
    public function getIdByFacetType($type,$key){
        $object = array();
        if($type=='category'){
            $category = Category::searchByName(null, $key);
            if($category){
                return $category->id_category;
            }
        }elseif ($type == 'feature'){
            
        }
    }

    private function copyFiltersActiveState(
        array $sourceFacets,
        array $targetFacets
    ) {
        $copyByLabel = function (Facet $source, Facet $target) {
            foreach ($target->getFilters() as $targetFilter) {
                foreach ($source->getFilters() as $sourceFilter) {
                    if ($sourceFilter->getLabel() === $targetFilter->getLabel()) {
                        $targetFilter->setActive($sourceFilter->isActive());
                        break;
                    }
                }
            }
        };

        $copyByRangeValue = function (Facet $source, Facet $target) {
            foreach ($source->getFilters() as $sourceFilter) {
                if ($sourceFilter->isActive()) {
                    $foundRange = false;
                    foreach ($target->getFilters() as $targetFilter) {
                        $tFrom = $targetFilter->getValue()['from'];
                        $tTo = $targetFilter->getValue()['to'];
                        $sFrom = $sourceFilter->getValue()['from'];
                        $sTo = $sourceFilter->getValue()['to'];
                        if ($tFrom <= $sFrom && $sTo <= $tTo) {
                            $foundRange = true;
                            $targetFilter->setActive(true);
                            break;
                        }
                    }
                    if (!$foundRange) {
                        $filter = clone $sourceFilter;
                        $filter->setDisplayed(false);
                        $target->addFilter($filter);
                    }
                    break;
                }
            }
        };

        $copy = function (
            Facet $source,
            Facet $target
        ) use (
            $copyByLabel,
            $copyByRangeValue
        ) {
            if ($target->getProperty('range')) {
                $strategy = $copyByRangeValue;
            } else {
                $strategy = $copyByLabel;
            }

            $strategy($source, $target);
        };

        foreach ($targetFacets as $targetFacet) {
            foreach ($sourceFacets as $sourceFacet) {
                if ($sourceFacet->getLabel() === $targetFacet->getLabel()) {
                    $copy($sourceFacet, $targetFacet);
                    break;
                }
            }
        }
    }

    private function getAvailableSortOrders()
    {
        return [
            (new SortOrder('product', 'position', 'asc'))->setLabel(
                $this->module->getTranslator()->trans('Relevance', array(), 'Modules.Facetedsearch.Shop')
            ),
            (new SortOrder('product', 'name', 'asc'))->setLabel(
                $this->module->getTranslator()->trans('Name, A to Z', array(), 'Shop.Theme.Catalog')
            ),
            (new SortOrder('product', 'name', 'desc'))->setLabel(
                $this->module->getTranslator()->trans('Name, Z to A', array(), 'Shop.Theme.Catalog')
            ),
            (new SortOrder('product', 'price', 'asc'))->setLabel(
                $this->module->getTranslator()->trans('Price, low to high', array(), 'Shop.Theme.Catalog')
            ),
            (new SortOrder('product', 'price', 'desc'))->setLabel(
                $this->module->getTranslator()->trans('Price, high to low', array(), 'Shop.Theme.Catalog')
            ),
        ];
    }

    public function runQuery(
        ProductSearchContext $context,
        ProductSearchQuery $query
    ) {

        $groupFilters = explode('/',$query->getEncodedFacets());
        $doofinderFilters = array();
        $options = $this->module->getDoofinderTermsOptions();
        foreach($groupFilters as $filters){
            $filter = explode('-',$filters);
            foreach($filter as $key => $value){
                if($keyFilter = array_search($filter[0], $options)){
                    if($key>0){
                        $doofinderFilters[$keyFilter][] = $value;
                    }
                }
            }
            if(!empty($keyFilter) && count($doofinderFilters[$keyFilter])==3){
                if(is_numeric($doofinderFilters[$keyFilter][1]) && is_float((float)$doofinderFilters[$keyFilter][1]) &&
                    is_numeric($doofinderFilters[$keyFilter][2]) && is_float((float)$doofinderFilters[$keyFilter][2])){
                    
                    $doofinderFilters[$keyFilter] = array(
                        'gte' => $doofinderFilters[$keyFilter][1],
                        'lte' => $doofinderFilters[$keyFilter][2]
                    );
                }
            }
        }

        $this->doofinderSearchQuery = $this->module->searchOnApi($query->getSearchString(),$query->getPage(),$query->getResultsPerPage(),8000,$doofinderFilters,true);
        
        $result = new ProductSearchResult();
        $menu = $this->getFacetCollectionFromEncodedFacets($query);
        
        $order_by = $query->getSortOrder()->toLegacyOrderBy(true);
        $order_way = $query->getSortOrder()->toLegacyOrderWay();

        $facetedSearchFilters = $this->filtersConverter->getFacetedSearchFiltersFromFacets(
            $menu->getFacets()
        );
        
        $productsAndCount = array(
            'products' => $this->doofinderSearchQuery['result'],
            'count' => $this->doofinderSearchQuery['total'],
        );

        if((int)$productsAndCount['count'] == 0) {
            $productsAndCount = array(
                'products' => array(),
                'count' => 0,
            );
        }
        $result
            ->setProducts($productsAndCount['products'])
            ->setTotalProductsCount($productsAndCount['count'])
            //->setAvailableSortOrders($this->getAvailableSortOrders())
        ;

        $presta17Filters = $this->getFormatedFilters($this->doofinderSearchQuery['facets'], $this->doofinderSearchQuery['filters'],$query->getSearchString());
        
        $facets = $this->filtersConverter->getFacetsFromFacetedSearchFilters(
            $presta17Filters
        );
        

        $this->copyFiltersActiveState(
            $menu->getFacets(),
            $facets
        );

        $this->labelRangeFilters($facets);

        $this->addEncodedFacetsToFilters($facets);

        //$this->hideZeroValues($facets);
        //$this->hideUselessFacets($facets);

        $nextMenu = (new FacetCollection())->setFacets($facets);
        $result->setFacetCollection($nextMenu);
        $result->setEncodedFacets($this->facetsSerializer->serialize($facets));
        return $result;
    }
    
    private function getFormatedFilters($facets,$filters,$queryString){
        $filterBlock = $this->module->getFilterBlock($facets,$filters,$queryString);
        
        $presta17Filters = array();
        foreach($filterBlock['facets'] as $facetKey => $facet){
            if($facet['_type'] == 'terms'){
                $values = array();
                foreach($facet['terms'] as $term){
                    $values[] = array(
                        'name' => $term['term'],
                        'nbr' => $term['count']
                    ); 
                }

                $presta17Filters[]  = array(
                    "type_lite" => 'category',
                    "type" => 'category',
                    "id_key" => 0,
                    "name" => $filterBlock['options'][$facetKey],
                    "values" => $values,
                    "filter_show_limit" => "0",
                    "filter_type" => "0",
                );
            }elseif($facet['_type'] == 'range'){
                
                $unit = '';
                $format = '0.00';
                if($facetKey == 'price'){
                    $context = Context::getContext();
                    $currency = $context->currency;
                    $unit = $currency->sign;
                    $format = $currency->format;
                }
                $range_array = array(
                    "type_lite" => 'price',
                    "type" => 'price',
                    "id_key" => 0,
                    "name" => $filterBlock['options'][$facetKey],
                    "slider" => true,
                    "max" => $facet['ranges'][0]['max'],
                    "min" => $facet['ranges'][0]['min'],
                    "unit" => $unit,
                    "format" => $format,
                    "filter_show_limit" => "0",
                    "filter_type" => "0",
                    "list_of_values" => [],
                    "values" => array(
                        $facet['ranges'][0]['min'],
                        $facet['ranges'][0]['max']
                    )
                );
                
                $rangeAggregator = new DoofinderRangeAggregator();
                if (!empty($this->doofinderSearchQuery['doofinder_results']) &&
                        is_array($this->doofinderSearchQuery['doofinder_results'])) {
                    $aggregatedRanges = $rangeAggregator->getRangesFromList(
                        $this->doofinderSearchQuery['doofinder_results'],
                        $facetKey
                    );
                    $range_array['min'] = $aggregatedRanges['min'];
                    $range_array['max'] = $aggregatedRanges['max'];

                    $mergedRanges = $rangeAggregator->mergeRanges(
                        $aggregatedRanges['ranges'],
                        10
                    );

                    $range_array['list_of_values'] = array_map(function (array $range) {
                        return array(
                            0 => $range['min'],
                            1 => $range['max'],
                            'nbr' => $range['count'],
                        );
                    }, $mergedRanges);

                    $range_array['values'] = array($range_array['min'], $range_array['max']);
                    $presta17Filters[] = $range_array;
                }
            }
        }
        
        return $presta17Filters;
    }

    private function labelRangeFilters(array $facets)
    {
        foreach ($facets as $facet) {
            if ($facet->getType() === 'weight') {
                $unit = Configuration::get('PS_WEIGHT_UNIT');
                foreach ($facet->getFilters() as $filter) {
                    $filter->setLabel(
                        sprintf(
                            '%1$s%2$s - %3$s%4$s',
                            Tools::displayNumber($filter->getValue()['from']),
                            $unit,
                            Tools::displayNumber($filter->getValue()['to']),
                            $unit
                        )
                    );
                }
            } elseif ($facet->getType() === 'price') {
                foreach ($facet->getFilters() as $filter) {
                    $filter->setLabel(
                        sprintf(
                            '%1$s - %2$s',
                            Tools::displayPrice($filter->getValue()['from']),
                            Tools::displayPrice($filter->getValue()['to'])
                        )
                    );
                }
            }
        }
    }

    /**
     * This method generates a URL stub for each filter inside the given facets
     * and assigns this stub to the filters.
     * The URL stub is called 'nextEncodedFacets' because it is used
     * to generate the URL of the search once a filter is activated.
     */
    private function addEncodedFacetsToFilters(array $facets)
    {
        // first get the currently active facetFilter in an array
        $activeFacetFilters = $this->facetsSerializer->getActiveFacetFiltersFromFacets($facets);
        $urlSerializer = new URLFragmentSerializer();

        foreach ($facets as $facet) {
            // If only one filter can be selected, we keep track of
            // the current active filter to disable it before generating the url stub
            // and not select two filters in a facet that can have only one active filter.
            if (!$facet->isMultipleSelectionAllowed()) {
                foreach ($facet->getFilters() as $filter) {
                    if ($filter->isActive()) {
                        // we have a currently active filter is the facet, remove it from the facetFilter array
                        $activeFacetFilters = $this->facetsSerializer->removeFilterFromFacetFilters($activeFacetFilters, $filter, $facet);
                        break;
                    }
                }
            }

            foreach ($facet->getFilters() as $filter) {
                $facetFilters = $activeFacetFilters;

                // toggle the current filter
                if ($filter->isActive()) {
                    $facetFilters = $this->facetsSerializer->removeFilterFromFacetFilters($facetFilters, $filter, $facet);
                } else {
                    $facetFilters = $this->facetsSerializer->addFilterToFacetFilters($facetFilters, $filter, $facet);
                }

                // We've toggled the filter, so the call to serialize
                // returns the "URL" for the search when user has toggled
                // the filter.
                $filter->setNextEncodedFacets(
                    $urlSerializer->serialize($facetFilters)
                );
            }
        }
    }

    private function hideZeroValues(array $facets)
    {
        foreach ($facets as $facet) {
            foreach ($facet->getFilters() as $filter) {
                if ($filter->getMagnitude() === 0) {
                    $filter->setDisplayed(false);
                }
            }
        }
    }

    private function hideUselessFacets(array $facets)
    {
        foreach ($facets as $facet) {
            $usefulFiltersCount = 0;
            foreach ($facet->getFilters() as $filter) {
                if ($filter->getMagnitude() > 0) {
                    ++$usefulFiltersCount;
                }
            }
            $facet->setDisplayed(
                $usefulFiltersCount > 1
            );
        }
    }
}
