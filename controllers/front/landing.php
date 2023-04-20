<?php

use PrestaShop\PrestaShop\Adapter\Image\ImageRetriever;
use PrestaShop\PrestaShop\Adapter\Product\PriceFormatter;
use PrestaShop\PrestaShop\Adapter\Product\ProductColorsRetriever;
use PrestaShop\PrestaShop\Core\Product\ProductListingPresenter;

class DoofinderLandingModuleFrontController extends ModuleFrontController
{
    public $products = [];
    public $landing_data = [];

    const RESULTS = 48;
    const TTL_CACHE = 30;

    /**
     * Initialize landing controller.
     *
     * @see FrontController::init()
     */
    public function init()
    {
        parent::init();

        $landing_name = Tools::getValue('landing_name');

        $this->landing_data = $this->getLandingData($landing_name, $this->context->shop->id, $this->context->language->id);

        if (!$this->landing_data) {
            Tools::redirect('index.php?controller=404');
        }

        if ($products_result = $this->module->searchOnApi($this->landing_data['query'], 1, self::RESULTS)) {
            $this->products = $products_result['result'];
        }
    }

    /**
     * Assign template vars related to page content.
     *
     * @see FrontController::initContent()
     */
    public function initContent()
    {
        $this->display_column_right = false;
        $this->display_column_left = false;

        parent::initContent();

        if(!version_compare(_PS_VERSION_, '1.7', '<') === true) {
            $assembler = new ProductAssembler($this->context);
            $presenterFactory = new ProductPresenterFactory($this->context);
            $presentationSettings = $presenterFactory->getPresentationSettings();
            $presenter = new ProductListingPresenter(
                new ImageRetriever(
                    $this->context->link
                ),
                $this->context->link,
                new PriceFormatter(),
                new ProductColorsRetriever(),
                $this->context->getTranslator()
            );

            $products = [];
            foreach ($this->products as $productDetail) {
                $products[] = $presenter->present(
                    $presentationSettings,
                    $assembler->assembleProduct($productDetail),
                    $this->context->language
                );
            }

            $this->context->smarty->assign(
                [
                    'products' => $products,
                    'title' => $this->landing_data['title'],
                    'description' => $this->landing_data['description'],
                ]
            );

            $this->setTemplate('module:doofinder/views/templates/front/landing.tpl');
        } else {
            $this->context->smarty->assign(
                [
                    'search_products' => $this->products,
                    'title' => $this->landing_data['title'],
                    'description' => $this->landing_data['description'],
                    'meta_title' => $this->landing_data['meta_title'],
                    'meta_description' => $this->landing_data['meta_description'],
                    'nobots' => $this->landing_data['index'] ? false : true
                ]
            );

            $this->addCSS(array(
                _THEME_CSS_DIR_.'category.css'     => 'all',
                _THEME_CSS_DIR_.'product_list.css' => 'all',
            ));
        
           $this->setTemplate('landing16.tpl');
        }
    }

    /**
     * Assign meta tags
     *
     * @return array
     */
    public function getTemplateVarPage()
    {
        $page = parent::getTemplateVarPage();

        $page['meta']['title'] = $this->landing_data['meta_title'];
        $page['meta']['description'] = $this->landing_data['meta_description'];
        $page['meta']['robots'] = $this->landing_data['index'] ? 'index' : 'noindex';

        return $page;
    }

    private function getLandingData($name, $id_shop, $id_lang)
    {
        $cache = $this->getLandingCache($name, $id_shop, $id_lang);

        if ($cache && !$this->refreshCache($cache)) {
            return json_decode($cache['data'], true);
        } else {
            // $response -> petición a la API Doofinder()

            $response = [
                'title' => 'title test 2',
                'description' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Maecenas quis vestibulum elit. Proin eleifend mattis mattis. Morbi iaculis varius leo, ullamcorper vestibulum enim tempor et. Aliquam tincidunt orci eu dolor auctor, eget semper lectus rutrum. Suspendisse augue dolor, facilisis vitae feugiat sed, euismod in turpis. Fusce ullamcorper condimentum pellentesque. Fusce sodales eget justo convallis fermentum. Fusce laoreet a odio iaculis suscipit. Nunc ut sollicitudin velit. Fusce feugiat est scelerisque scelerisque porttitor. Pellentesque in lacinia velit. Etiam finibus pretium orci non auctor. Curabitur lacinia sapien vel convallis condimentum.',
                'meta_title' => 'meta title test',
                'meta_description' => 'meta_description_test',
                'index' => false,
                'query' => $name,
            ];

            $this->setLandingCache($name, $id_shop, $id_lang, json_encode($response));

            return $response;
        }
    }

    private function setLandingCache($name, $id_shop, $id_lang, $data)
    {
        return Db::getInstance()->insert(
            'doofinder_landing',
            [
                'name' => pSQL($name),
                'id_shop' => $id_shop,
                'id_lang' => $id_lang,
                'data' => $data,
                'date_upd' => date('Y-m-d H:i:s'),
            ],
            false,
            true,
            Db::REPLACE
        );
    }

    private function getLandingCache($name, $id_shop, $id_lang)
    {
        return Db::getInstance()->getRow(
            '
            SELECT * FROM ' . _DB_PREFIX_ . "doofinder_landing 
            WHERE name = '" . pSQL($name) . "' AND id_shop = " . (int) $id_shop . ' AND id_lang = ' . (int) $id_lang
        );
    }

    private function refreshCache($row)
    {
        $last_exec_ts = strtotime($row['date_upd']);

        $diff_min = (time() - $last_exec_ts) / 60;

        if ($diff_min > self::TTL_CACHE) {
            return true;
        }

        return false;
    }
}
