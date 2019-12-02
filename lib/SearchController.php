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
        $query = Tools::getValue('search_query', Tools::getValue('ref'));
        $overwrite_search = Configuration::get('DF_OWSEARCH', null);
        $overwrite_embedded = Configuration::get('DF_OWSEARCHEB', null);
        $m = Module::getInstanceByName('doofinder');
        $this->p = abs((int) (Tools::getValue('p', 1)));
        $this->n = abs((int) (Tools::getValue('n', Configuration::get('PS_PRODUCTS_PER_PAGE'))));
        if (Module::isEnabled('doofinder')){
            if ($overwrite_embedded) {
                $this->setTemplate(_PS_MODULE_DIR_ . 'doofinder/views/templates/front/doofinder-embedded.tpl');
                FrontController::initContent();
            } else if (($search = $m->generateSearch(true)) && $overwrite_search && $query && !is_array($query)) {
                $original_query = $query;
                $query = Tools::replaceAccentedChars(urldecode($query));

                foreach ($search['result'] as &$product)
                    $product['link'] .= (strpos($product['link'], '?') === false ? '?' : '&') . 'search_query=' . urlencode($query) . '&results=' . (int) $search['total'];
                    
                Hook::exec('actionSearch', array('expr' => $query, 'total' => $search['total']));
                $nbProducts = $search['total'];
                $this->pagination($nbProducts);

                if (method_exists($this, 'addColorsToProductList')) //RETROCOMPATIBILITY
                    $this->addColorsToProductList($search['result']);

                if (method_exists('ImageType', 'getFormatedName')) {
                    $imageSize = Image::getSize(ImageType::getFormatedName('home'));
                } else {
                    $imageSize = Image::getSize($m->getFormatedName('home'));
                }

                $this->context->smarty->assign(array(
                    'products' => $search['result'], // DEPRECATED (since to 1.4), not use this: conflict with block_cart module
                    'search_products' => $search['result'],
                    'nbProducts' => $search['total'],
                    'search_query' => $original_query,
                    'homeSize' => $imageSize));

                $this->context->smarty->assign(array('add_prod_display' => Configuration::get('PS_ATTRIBUTE_CATEGORY_DISPLAY'), 'comparator_max_item' => Configuration::get('PS_COMPARATOR_MAX_ITEM')));

                $this->setTemplate(_PS_THEME_DIR_ . 'search.tpl');
                FrontController::initContent();
            } else {
                parent::initContent();
            }
        } else {
            parent::initContent();
        }
    }
}
