<?php

use PrestaShop\PrestaShop\Adapter\Image\ImageRetriever;
use PrestaShop\PrestaShop\Adapter\Product\PriceFormatter;
use PrestaShop\PrestaShop\Adapter\Product\ProductColorsRetriever;
use PrestaShop\PrestaShop\Core\Product\ProductListingPresenter;

require_once _PS_MODULE_DIR_ . 'doofinder/lib/doofinder_api_landing.php';

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

        $this->landing_data = $this->getLandingData($landing_name, $this->context->shop->id, $this->context->language->id, $this->context->currency->id);

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

        if (version_compare(_PS_VERSION_, '1.7', '>=')) {
            $this->renderProductList();
        } else {
            $this->renderProductList16();
        }
    }

    private function renderProductList()
    {
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
    }

    private function renderProductList16()
    {
        $this->context->smarty->assign(
            [
                'search_products' => $this->products,
                'title' => $this->landing_data['title'],
                'description' => $this->landing_data['description'],
                'meta_title' => $this->landing_data['meta_title'],
                'meta_description' => $this->landing_data['meta_description'],
                'nobots' => $this->landing_data['index'] ? false : true,
            ]
        );

        $this->addCSS([
            _THEME_CSS_DIR_ . 'category.css' => 'all',
            _THEME_CSS_DIR_ . 'product_list.css' => 'all',
        ]);

        $this->setTemplate('landing16.tpl');
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

    private function getLandingData($name, $id_shop, $id_lang, $id_currency)
    {
        $hashid = $this->module->getHashId($id_lang, $id_currency);
        $cache = $this->getLandingCache($name, $hashid);

        if ($cache && !$this->refreshCache($cache)) {
            return json_decode($cache['data'], true);
        } else {
            $response = $this->getApiCall($name, $hashid);

            if (!$response) {
                $this->setLandingCache($name, $hashid, null);

                return false;
            }

            $data = [
                'title' => $response['title'],
                'description' => $response['description'],
                'meta_title' => $response['meta_title'],
                'meta_description' => $response['meta_description'],
                'index' => $response['index'],
                'query' => $response['query'],
            ];

            $this->setLandingCache($name, $hashid, json_encode($data));

            return $response;
        }
    }

    private function getApiCall($name, $hashid)
    {
        $apikey = explode('-', Configuration::get('DF_API_KEY'))[1];
        $region = Configuration::get('DF_REGION');

        $api = new DoofinderApiLanding($hashid, $apikey, $region);

        return $api->getLanding($name);
    }

    private function setLandingCache($name, $hashid, $data)
    {
        return Db::getInstance()->insert(
            'doofinder_landing',
            [
                'name' => pSQL($name),
                'hashid' => pSQL($hashid),
                'data' => $data,
                'date_upd' => date('Y-m-d H:i:s'),
            ],
            false,
            true,
            Db::REPLACE
        );
    }

    private function getLandingCache($name, $hashid)
    {
        return Db::getInstance()->getRow(
            '
            SELECT * FROM ' . _DB_PREFIX_ . "doofinder_landing 
            WHERE name = '" . pSQL($name) . "' AND hashid = '" . pSQL($hashid) . "'"
        );
    }

    private function refreshCache($row)
    {
        $last_exec_ts = strtotime($row['date_upd']);

        $diff_min = (time() - $last_exec_ts) / 60;

        return $diff_min > self::TTL_CACHE;
    }
}
