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

class SearchController extends SearchControllerCore
{

    public function initContent()
    {
        $parentContent = true;
        if (Module::isEnabled('doofinder')){
            $m = Module::getInstanceByName('doofinder');
            if ($m->testDoofinderApi(Context::getContext()->language->iso_code)) {
                $olderThan17 = (version_compare(_PS_VERSION_, '1.7', '<') === true);
                $overwrite_embedded = Configuration::get('DF_OWSEARCHEB', null);
                if ($overwrite_embedded) {
                    //HERE you are using embedded layer
                    if  ($olderThan17) {
                        $parentContent = false;
                        $this->setTemplate($m->getEmbeddedTemplateLocation());
                        FrontController::initContent();
                    } else {
                        $parentContent = false;
                        ProductListingFrontController::initContent();
                        $html = $this->context->smarty->fetch($m->getEmbeddedTemplateLocation());
                        $this->context->smarty->assign(array(
                            'HOOK_HOME' => trim($html),
                        ));
                        $this->setTemplate('index');
                    }
                } elseif ($olderThan17) {
                    //HERE you are using API!
                    //If your PS is 1.6 or older. Newer are using productSearchProvider hook
                    $query = Tools::getValue('search_query', Tools::getValue('ref', Tools::getValue('s')));
                    $overwrite_search = Configuration::get('DF_OWSEARCH', null);           
                    $this->p = abs((int) (Tools::getValue('p', 1)));
                    $this->n = abs((int) (Tools::getValue('n', Configuration::get('PS_PRODUCTS_PER_PAGE'))));
                    
                    if ($overwrite_search && ($search = $m->generateSearch(true)) && $query && !is_array($query)) {
                        $parentContent = false;
                        $original_query = $query;
                        $query = Tools::replaceAccentedChars(urldecode($query));

                        foreach ($search['result'] as &$product) {
                            $product['link'] .= (strpos($product['link'], '?') === false ? '?' : '&') . 'search_query=' . urlencode($query) . '&results=' . (int) $search['total'];
                        }
                        Hook::exec('actionSearch', array('expr' => $query, 'total' => $search['total']));
                        $nbProducts = $search['total'];
                        $this->pagination($nbProducts);

                        if (method_exists($this, 'addColorsToProductList')) { 
                            //RETROCOMPATIBILITY
                            $this->addColorsToProductList($search['result']);
                        }

                        if (method_exists('ImageType', 'getFormatedName')) {
                            $imageSize = Image::getSize(ImageType::getFormatedName('home'));
                        } else {
                            $imageSize = Image::getSize($m->getFormatedName('home'));
                        }

                        $this->context->smarty->assign(
                            array(
                                'products' => $search['result'], // DEPRECATED (since to 1.4), not use this: conflict with block_cart module
                                'search_products' => $search['result'],
                                'nbProducts' => $search['total'],
                                'search_query' => $original_query,
                                'homeSize' => $imageSize
                            )
                        );

                        $this->context->smarty->assign(
                            array(
                                'add_prod_display' => Configuration::get('PS_ATTRIBUTE_CATEGORY_DISPLAY'), 
                                'comparator_max_item' => Configuration::get('PS_COMPARATOR_MAX_ITEM')
                            )
                        );

                        $this->setTemplate(_PS_THEME_DIR_ . 'search.tpl');
                        FrontController::initContent();
                    }
                }
            }
        }

        if ($parentContent) {
            parent::initContent();
        }
    }
}
