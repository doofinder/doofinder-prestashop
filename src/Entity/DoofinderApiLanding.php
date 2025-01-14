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

namespace PrestaShop\Module\Doofinder\Src\Entity;

if (!defined('_PS_VERSION_')) {
    exit;
}

class DoofinderApiLanding
{
    private $hashid;
    private $apiKey;
    private $apiUrl;

    public function __construct($hashid, $apiKey, $region)
    {
        $this->hashid = $hashid;
        $this->apiKey = $apiKey;
        $this->apiUrl = UrlManager::getRegionalUrl(DoofinderConstants::DOOPLUGINS_REGION_URL, $region);
    }

    /**
     * Make a request to the API to get landing data
     *
     * @param string Name from Landing
     */
    public function getLanding($slug)
    {
        $endpoint = "/landing/{$this->hashid}/$slug";

        $url = $this->apiUrl . $endpoint;

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
            ['Authorization: Token ' . $this->apiKey]
        );

        return json_decode($response->response, true);
    }

    /**
     * Search Doofinder using the API
     *
     * @param string $searchString
     * @param int $page
     * @param int $pageSize
     * @param int $timeout
     * @param array $filters
     * @param bool $returnFacets
     *
     * @return array
     */
    public function searchOnApi($searchString, $module, $page = 1, $pageSize = 12, $timeout = 8000, $filters = null, $returnFacets = false)
    {
        $pageSize = (int) $pageSize;
        if (!$pageSize) {
            $pageSize = \Configuration::get('PS_PRODUCTS_PER_PAGE');
        }
        $page = (int) $page;
        if (!$page) {
            $page = 1;
        }
        $queryName = \Tools::getValue('df_query_name', false);
        DoofinderConfig::debug('Search On API Start');
        $hashid = SearchEngine::getHashId(\Context::getContext()->language->id, \Context::getContext()->currency->id);
        $apiKey = \Configuration::get('DF_API_KEY');
        $showVariations = \Configuration::get('DF_SHOW_PRODUCT_VARIATIONS');
        if ((int) $showVariations !== 1) {
            $showVariations = false;
        }

        if ($hashid && $apiKey) {
            $fail = false;
            $df = new DoofinderApi($hashid, $apiKey, false, ['apiVersion' => '5']);
            try {
                $queryParams = [
                    'rpp' => $pageSize, // results per page
                    'timeout' => $timeout,
                    'types' => [
                        'product',
                    ],
                    'transformer' => 'basic',
                ];
                if ($queryName) {
                    $queryParams['query_name'] = $queryName;
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
            $productPoolAttributes = [];
            $productPoolIds = [];
            foreach ($dfResultsArray as $entry) {
                // For unknown reasons, it can sometimes be defined as 'products' in plural
                if (in_array($entry['type'], ['product', 'products'], true)) {
                    if (false === strpos($entry['id'], 'VAR-')) {
                        $productPoolIds[] = (int) pSQL($entry['id']);
                    } else {
                        $idProductAttribute = str_replace('VAR-', '', $entry['id']);
                        if (!in_array($idProductAttribute, $productPoolAttributes)) {
                            $productPoolAttributes[] = (int) pSQL($idProductAttribute);
                        }
                        $id_product = \Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue(
                            'SELECT id_product FROM ' . _DB_PREFIX_ . 'product_attribute'
                            . ' WHERE id_product_attribute = ' . (int) pSQL($idProductAttribute)
                        );
                        $productPoolIds[] = ((!empty($id_product)) ? (int) pSQL($id_product) : 0);
                    }
                }
            }
            $productPool = implode(', ', $productPoolIds);

            // To avoid SQL errors.
            if ($productPool == '') {
                $productPool = '0';
            }

            DoofinderConfig::debug("Product Pool: $productPool");

            $productPoolAttributes = implode(',', $productPoolAttributes);

            $context = \Context::getContext();
            // Avoids SQL Error
            if ($productPoolAttributes == '') {
                $productPoolAttributes = '0';
            }

            DoofinderConfig::debug("Product Pool Attributes: $productPoolAttributes");
            $db = \Db::getInstance(_PS_USE_SQL_SLAVE_);
            $idLang = $context->language->id;
            $sql = 'SELECT p.*, product_shop.*, stock.out_of_stock,
                IFNULL(stock.quantity, 0) as quantity,
                pl.`description_short`, pl.`available_now`,
                pl.`available_later`, pl.`link_rewrite`, pl.`name`,
                ' . (\Combination::isFeatureActive() && $showVariations ?
                ' IF(ipa.`id_image` IS NULL OR ipa.`id_image` = 0, MAX(image_shop.`id_image`),ipa.`id_image`)'
                . ' id_image, ' : 'i.id_image, ') . '
                il.`legend`, m.`name` manufacturer_name '
                . (\Combination::isFeatureActive() ? (($showVariations) ?
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
                    AND pl.`id_lang` = ' . (int) pSQL($idLang) . \Shop::addSqlRestrictionOnLang('pl') . ') '
                . (\Combination::isFeatureActive() ? ' LEFT JOIN `' . _DB_PREFIX_ . 'product_attribute` pa
                    ON (p.`id_product` = pa.`id_product`)
                    ' . \Shop::addSqlAssociation('product_attribute', 'pa', false, ($showVariations) ? '' :
                            ' product_attribute_shop.default_on = 1') . '
                    ' . \Product::sqlStock('p', 'product_attribute_shop', false, $context->shop) :
                    \Product::sqlStock('p', 'product', false, \Context::getContext()->shop)) . '
                LEFT JOIN `' . _DB_PREFIX_ . 'manufacturer` m ON m.`id_manufacturer` = p.`id_manufacturer`
                LEFT JOIN `' . _DB_PREFIX_ . 'image` i ON (i.`id_product` = p.`id_product` '
                . ((\Combination::isFeatureActive() && $showVariations) ? '' : 'AND i.cover=1') . ') '
                . ((\Combination::isFeatureActive() && $showVariations) ?
                    ' LEFT JOIN `' . _DB_PREFIX_ . 'product_attribute_image` pai'
                    . ' ON (pai.`id_product_attribute` = product_attribute_shop.`id_product_attribute`) ' : ' ')
                . \Shop::addSqlAssociation('image', 'i', false, 'i.cover=1') . '
                LEFT JOIN `' . _DB_PREFIX_ . 'image_lang` il'
                . ' ON (i.`id_image` = il.`id_image` AND il.`id_lang` = ' . (int) pSQL($idLang) . ') '
                . (\Combination::isFeatureActive() && $showVariations ?
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
                . ' WHERE p.`id_product` IN (' . pSQL($productPool) . ') ' .
                (($showVariations) ? ' AND (product_attribute_shop.`id_product_attribute` IS NULL'
                    . ' OR product_attribute_shop.`id_product_attribute`'
                    . ' IN (' . pSQL($productPoolAttributes) . ')) ' : '') .
                ' GROUP BY product_shop.id_product '
                . (($showVariations) ? ' ,  product_attribute_shop.`id_product_attribute` ' : '') .
                ' ORDER BY FIELD (p.`id_product`,' . pSQL($productPool) . ') '
                . (($showVariations) ? ' , FIELD (product_attribute_shop.`id_product_attribute`,'
                    . pSQL($productPoolAttributes) . ')' : '');

            DoofinderConfig::debug("SQL: $sql");

            $result = $db->executeS($sql);

            if (!$result) {
                return false;
            } else {
                if (version_compare(_PS_VERSION_, '1.7', '<') === true) {
                    $resultProperties = \Product::getProductsProperties((int) $idLang, $result);
                    // To print the id and links in the javascript so I can register the clicks
                    $module->setProductLinks([]);

                    foreach ($resultProperties as $rp) {
                        $module->setProductLinkByIndexName($rp['link'], $rp['id_product']);
                    }
                } else {
                    $resultProperties = $result;
                }
            }
            $module->searchBanner = $dfResults->getBanner();

            if ($returnFacets) {
                return [
                    'doofinder_results' => $dfResultsArray,
                    'total' => $dfResults->getProperty('total'),
                    'result' => $resultProperties,
                    'facets' => $dfResults->getFacets(),
                    'filters' => $df->getFilters(),
                    'df_query_name' => $dfResults->getProperty('query_name'),
                ];
            }

            return [
                'doofinder_results' => $dfResultsArray,
                'total' => $dfResults->getProperty('total'),
                'result' => $resultProperties,
                'df_query_name' => $dfResults->getProperty('query_name'),
            ];
        } else {
            return false;
        }
    }
}
