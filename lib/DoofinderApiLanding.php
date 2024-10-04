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

namespace PrestaShop\Module\Doofinder\Lib;

if (!defined('_PS_VERSION_')) {
    exit;
}

class DoofinderApiLanding
{
    private $hashid;
    private $api_key;
    private $api_url;

    public function __construct($hashid, $api_key, $region)
    {
        $this->hashid = $hashid;
        $this->api_key = $api_key;
        $this->api_url = UrlManager::getRegionalUrl(DoofinderConstants::DOOPLUGINS_REGION_URL, $region);
    }

    /**
     * Make a request to the API to get landing data
     *
     * @param string Name from Landing
     */
    public function getLanding($slug)
    {
        $endpoint = "/landing/{$this->hashid}/$slug";

        $url = $this->api_url . $endpoint;

        return $this->get($url);
    }

    private function get($url)
    {
        $client = new EasyREST();

        $response = $client->get(
            $url,
            null,
            false,
            false,
            'application/json',
            ['Authorization: Token ' . $this->api_key]
        );

        return json_decode($response->response, true);
    }

    /**
     * Search Doofinder using the API
     *
     * @param string $searchString
     * @param int $page
     * @param int $page_size
     * @param int $timeout
     * @param array $filters
     * @param bool $returnFacets
     *
     * @return array
     */
    public function searchOnApi($searchString, $module, $page = 1, $page_size = 12, $timeout = 8000, $filters = null, $returnFacets = false)
    {
        $page_size = (int) $page_size;
        if (!$page_size) {
            $page_size = \Configuration::get('PS_PRODUCTS_PER_PAGE');
        }
        $page = (int) $page;
        if (!$page) {
            $page = 1;
        }
        $query_name = \Tools::getValue('df_query_name', false);
        DoofinderConfig::debug('Search On API Start');
        $hash_id = SearchEngine::getHashId(\Context::getContext()->language->id, \Context::getContext()->currency->id);
        $api_key = \Configuration::get('DF_API_KEY');
        $show_variations = \Configuration::get('DF_SHOW_PRODUCT_VARIATIONS');
        if ((int) $show_variations !== 1) {
            $show_variations = false;
        }

        if ($hash_id && $api_key) {
            $fail = false;
            try {
                $df = new DoofinderApi($hash_id, $api_key, false, ['apiVersion' => '5']);
                $queryParams = [
                    'rpp' => $page_size, // results per page
                    'timeout' => $timeout,
                    'types' => [
                        'product',
                    ],
                    'transformer' => 'basic',
                ];
                if ($query_name) {
                    $queryParams['query_name'] = $query_name;
                }
                if (!empty($filters)) {
                    $queryParams['filter'] = $filters;
                }
                $dfResults = $df->query($searchString, $page, $queryParams);
            } catch (\Exception $e) {
                $fail = true;
            }

            if ($fail || !$dfResults->isOk()) {
                return false;
            }

            $dfResultsArray = $dfResults->getResults();
            $product_pool_attributes = [];
            $product_pool_ids = [];
            foreach ($dfResultsArray as $entry) {
                // For unknown reasons, it can sometimes be defined as 'products' in plural
                if (in_array($entry['type'], ['product', 'products'], true)) {
                    if (false === strpos($entry['id'], 'VAR-')) {
                        $product_pool_ids[] = (int) pSQL($entry['id']);
                    } else {
                        $id_product_attribute = str_replace('VAR-', '', $entry['id']);
                        if (!in_array($id_product_attribute, $product_pool_attributes)) {
                            $product_pool_attributes[] = (int) pSQL($id_product_attribute);
                        }
                        $id_product = \Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue(
                            'SELECT id_product FROM ' . _DB_PREFIX_ . 'product_attribute'
                            . ' WHERE id_product_attribute = ' . (int) pSQL($id_product_attribute)
                        );
                        $product_pool_ids[] = ((!empty($id_product)) ? (int) pSQL($id_product) : 0);
                    }
                }
            }
            $product_pool = implode(', ', $product_pool_ids);

            // To avoid SQL errors.
            if ($product_pool == '') {
                $product_pool = '0';
            }

            DoofinderConfig::debug("Product Pool: $product_pool");

            $product_pool_attributes = implode(',', $product_pool_attributes);

            $context = \Context::getContext();
            // Avoids SQL Error
            if ($product_pool_attributes == '') {
                $product_pool_attributes = '0';
            }

            DoofinderConfig::debug("Product Pool Attributes: $product_pool_attributes");
            $db = \Db::getInstance(_PS_USE_SQL_SLAVE_);
            $id_lang = $context->language->id;
            $sql = 'SELECT p.*, product_shop.*, stock.out_of_stock,
                IFNULL(stock.quantity, 0) as quantity,
                pl.`description_short`, pl.`available_now`,
                pl.`available_later`, pl.`link_rewrite`, pl.`name`,
                ' . (\Combination::isFeatureActive() && $show_variations ?
                ' IF(ipa.`id_image` IS NULL OR ipa.`id_image` = 0, MAX(image_shop.`id_image`),ipa.`id_image`)'
                . ' id_image, ' : 'i.id_image, ') . '
                il.`legend`, m.`name` manufacturer_name '
                . (\Combination::isFeatureActive() ? (($show_variations) ?
                    ', MAX(product_attribute_shop.`id_product_attribute`) id_product_attribute' :
                    ', product_attribute_shop.`id_product_attribute` id_product_attribute') : '') . ',
                DATEDIFF(
                    p.`date_add`,
                    DATE_SUB(
                        NOW(),
                        INTERVAL ' . (\Validate::isUnsignedInt(\Configuration::get('PS_NB_DAYS_NEW_PRODUCT'))
                ? \Configuration::get('PS_NB_DAYS_NEW_PRODUCT') : 20) . ' DAY
                    )
                ) > 0 new' . (\Combination::isFeatureActive() ?
                ', MAX(product_attribute_shop.minimal_quantity) AS product_attribute_minimal_quantity' : '') . '
                FROM ' . _DB_PREFIX_ . 'product p
                ' . \Shop::addSqlAssociation('product', 'p') . '
                INNER JOIN `' . _DB_PREFIX_ . 'product_lang` pl ON (
                    p.`id_product` = pl.`id_product`
                    AND pl.`id_lang` = ' . (int) pSQL($id_lang) . \Shop::addSqlRestrictionOnLang('pl') . ') '
                . (\Combination::isFeatureActive() ? ' LEFT JOIN `' . _DB_PREFIX_ . 'product_attribute` pa
                    ON (p.`id_product` = pa.`id_product`)
                    ' . \Shop::addSqlAssociation('product_attribute', 'pa', false, ($show_variations) ? '' :
                            ' product_attribute_shop.default_on = 1') . '
                    ' . \Product::sqlStock('p', 'product_attribute_shop', false, $context->shop) :
                    \Product::sqlStock('p', 'product', false, \Context::getContext()->shop)) . '
                LEFT JOIN `' . _DB_PREFIX_ . 'manufacturer` m ON m.`id_manufacturer` = p.`id_manufacturer`
                LEFT JOIN `' . _DB_PREFIX_ . 'image` i ON (i.`id_product` = p.`id_product` '
                . ((\Combination::isFeatureActive() && $show_variations) ? '' : 'AND i.cover=1') . ') '
                . ((\Combination::isFeatureActive() && $show_variations) ?
                    ' LEFT JOIN `' . _DB_PREFIX_ . 'product_attribute_image` pai'
                    . ' ON (pai.`id_product_attribute` = product_attribute_shop.`id_product_attribute`) ' : ' ')
                . \Shop::addSqlAssociation('image', 'i', false, 'i.cover=1') . '
                LEFT JOIN `' . _DB_PREFIX_ . 'image_lang` il'
                . ' ON (i.`id_image` = il.`id_image` AND il.`id_lang` = ' . (int) pSQL($id_lang) . ') '
                . (\Combination::isFeatureActive() && $show_variations ?
                    'LEFT JOIN (
                        SELECT i.id_image, P.id_product, P.id_product_attribute
                            from
                            (
                            select
                                pa.id_product,
                                pa.id_product_attribute,
                                paic.id_attribute,min(i.position)
                                as min_position
                            from ' . _DB_PREFIX_ . 'product_attribute pa
                             inner join ' . _DB_PREFIX_ . 'product_attribute_image pai
                               on pai.id_product_attribute = pa.id_product_attribute
                             inner join  ' . _DB_PREFIX_ . 'product_attribute_combination paic
                               on pai.id_product_attribute = paic.id_product_attribute
                             inner join ' . _DB_PREFIX_ . 'image i
                               on pai.id_image = i.id_image
                            group by pa.id_product, pa.id_product_attribute,paic.id_attribute
                            ) as P
                            inner join ' . _DB_PREFIX_ . 'image i
                             on i.id_product = P.id_product and i.position =  P.min_position
                    )
                    AS ipa ON p.`id_product` = ipa.`id_product`
                    AND pai.`id_product_attribute` = ipa.`id_product_attribute`' : '')
                . ' WHERE p.`id_product` IN (' . pSQL($product_pool) . ') ' .
                (($show_variations) ? ' AND (product_attribute_shop.`id_product_attribute` IS NULL'
                    . ' OR product_attribute_shop.`id_product_attribute`'
                    . ' IN (' . pSQL($product_pool_attributes) . ')) ' : '') .
                ' GROUP BY product_shop.id_product '
                . (($show_variations) ? ' ,  product_attribute_shop.`id_product_attribute` ' : '') .
                ' ORDER BY FIELD (p.`id_product`,' . pSQL($product_pool) . ') '
                . (($show_variations) ? ' , FIELD (product_attribute_shop.`id_product_attribute`,'
                    . pSQL($product_pool_attributes) . ')' : '');

            DoofinderConfig::debug("SQL: $sql");

            $result = $db->executeS($sql);

            if (!$result) {
                return false;
            } else {
                if (version_compare(_PS_VERSION_, '1.7', '<') === true) {
                    $result_properties = \Product::getProductsProperties((int) $id_lang, $result);
                    // To print the id and links in the javascript so I can register the clicks
                    $module->setProductLinks([]);

                    foreach ($result_properties as $rp) {
                        $module->setProductLinkByIndexName($rp['link'], $rp['id_product']);
                    }
                } else {
                    $result_properties = $result;
                }
            }
            $module->searchBanner = $dfResults->getBanner();

            if ($returnFacets) {
                return [
                    'doofinder_results' => $dfResultsArray,
                    'total' => $dfResults->getProperty('total'),
                    'result' => $result_properties,
                    'facets' => $dfResults->getFacets(),
                    'filters' => $df->getFilters(),
                    'df_query_name' => $dfResults->getProperty('query_name'),
                ];
            }

            return [
                'doofinder_results' => $dfResultsArray,
                'total' => $dfResults->getProperty('total'),
                'result' => $result_properties,
                'df_query_name' => $dfResults->getProperty('query_name'),
            ];
        } else {
            return false;
        }
    }
}
