<?php
/**
 * @author    Doofinder
 * @copyright Doofinder
 * @license   MIT
 * @see       https://opensource.org/licenses/MIT
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class DoofinderPricesModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        parent::initContent();
        $this->ajax = true;

        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');

        if ('POST' !== $_SERVER['REQUEST_METHOD']) {
            http_response_code(405);
            echo json_encode(['error' => 'Method Not Allowed']);
            exit;
        }

        $body = file_get_contents('php://input');
        $data = json_decode($body, true);

        if (!isset($data['ids']) || !is_array($data['ids'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Bad Request']);
            exit;
        }

        $customer = Context::getContext()->customer;
        $includeTaxes = $this->resolveIncludeTaxes((int) $customer->id_default_group);

        $products = [];
        foreach ($data['ids'] as $rawId) {
            $id = (string) $rawId;
            $products[$id] = $this->resolvePrice($id, $includeTaxes);
        }

        echo json_encode(['products' => $products]);
        exit;
    }

    /**
     * Determines tax inclusion from the customer group's price_display_method.
     * 0 = with tax, 1 = without tax.
     */
    private function resolveIncludeTaxes($idGroup)
    {
        if (!$idGroup) {
            return true;
        }
        $priceDisplayMethod = (int) Db::getInstance()->getValue(
            'SELECT `price_display_method` FROM `' . _DB_PREFIX_ . 'group` WHERE `id_group` = ' . (int) $idGroup
        );

        return 0 === $priceDisplayMethod;
    }

    private function resolvePrice($id, $includeTaxes)
    {
        if (0 === strpos($id, 'VAR-')) {
            return $this->getVariantPrice((int) substr($id, 4), $includeTaxes);
        }

        return $this->getProductPrice((int) $id, $includeTaxes);
    }

    /**
     * Resolves VAR-{n}: looks up id_product from id_product_attribute, then fetches the contextual price.
     */
    private function getVariantPrice($idProductAttribute, $includeTaxes)
    {
        $idProduct = (int) Db::getInstance()->getValue(
            'SELECT `id_product` FROM `' . _DB_PREFIX_ . 'product_attribute`'
            . ' WHERE `id_product_attribute` = ' . (int) $idProductAttribute
        );
        if (!$idProduct) {
            return null;
        }

        return $this->fetchPrice($idProduct, $idProductAttribute, $includeTaxes);
    }

    /**
     * Resolves a parent product: returns the cheapest contextual price across all its variants,
     * falling back to the product's own price if it has no attributes.
     */
    private function getProductPrice($idProduct, $includeTaxes)
    {
        $rows = Db::getInstance()->executeS(
            'SELECT `id_product_attribute` FROM `' . _DB_PREFIX_ . 'product_attribute`'
            . ' WHERE `id_product` = ' . (int) $idProduct
        );
        if (empty($rows)) {
            return $this->fetchPrice($idProduct, null, $includeTaxes);
        }
        $minPrice = null;
        foreach ($rows as $row) {
            $price = $this->fetchPrice($idProduct, (int) $row['id_product_attribute'], $includeTaxes);
            if (null !== $price && (null === $minPrice || $price < $minPrice)) {
                $minPrice = $price;
            }
        }

        return $minPrice;
    }

    private function fetchPrice($idProduct, $idProductAttribute, $includeTaxes)
    {
        $price = Product::getPriceStatic(
            $idProduct,
            $includeTaxes,
            $idProductAttribute,
            6
        );
        if (null === $price || false === $price) {
            return null;
        }

        return round((float) $price, 2);
    }
}
