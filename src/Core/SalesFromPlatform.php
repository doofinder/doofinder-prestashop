<?php
/**
 * @author    Doofinder
 * @copyright Doofinder
 * @license   MIT
 *
 * @see       https://opensource.org/licenses/MIT
 */

namespace PrestaShop\Module\Doofinder\Core;

use PrestaShop\Module\Doofinder\Api\EasyREST;
use PrestaShop\Module\Doofinder\Configuration\DoofinderConfig;
use PrestaShop\Module\Doofinder\Manager\UrlManager;
use PrestaShop\Module\Doofinder\Utils\DfTools;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Sends the orders to Doofinder when they become valid, so they are registered as sales from the
 * server instead of from the order confirmation page.
 *
 * The Doofinder session lives in the browser's localStorage, so the storefront copies it to a
 * cookie, which is saved with the cart every time the shopper changes it: payment modules often
 * create the order from the notification of their own server, which doesn't bring the cookie.
 *
 * The sale is sent at once, when the first order of the cart gets a valid or paid status (a paid
 * order with products out of stock gets "On backorder (paid)", which isn't valid), with the lines
 * of every order of the cart, as a cart split by carriers is a single sale. Orders waiting for a
 * bank wire, a check or the validation of a cash on delivery count when the merchant accepts them.
 * Doofinder registers it only in the stores it gets the sales from the server for.
 */
class SalesFromPlatform
{
    public const COOKIE = 'doofinder_session';
    public const TABLE = 'doofinder_cart_session';

    /**
     * Days a cart keeps its session. An order validated later counts without it.
     */
    public const SESSION_DAYS = 30;

    /**
     * Seconds the order waits for Doofinder to take its sale, as it is sent while the order is
     * placed or its status changes.
     */
    public const TIMEOUT_IN_SECONDS = 3;

    /**
     * Whether the storefront exposes the cart the Doofinder script reads the carts from
     * (`prestashop.cart`, from PrestaShop 1.7.3 on), so the sales can be taken from the module.
     *
     * @return bool
     */
    public static function isSupported()
    {
        return DfTools::versionGte('1.7.3.0');
    }

    /**
     * Creates the table with the Doofinder session of each cart.
     *
     * @return bool
     */
    public static function installDb()
    {
        return \Db::getInstance()->execute(
            'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . self::TABLE . '` (
                `id_cart` INT(10) UNSIGNED NOT NULL,
                `session_id` VARCHAR(64) NOT NULL,
                `user_id` VARCHAR(64) NOT NULL,
                `hashid` VARCHAR(64) NULL,
                `user_agent` VARCHAR(255) NULL,
                `date_upd` DATETIME NOT NULL,
                PRIMARY KEY (`id_cart`),
                KEY `date_upd` (`date_upd`)
            ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;'
        );
    }

    /**
     * @return bool
     */
    public static function uninstallDb()
    {
        return \Db::getInstance()->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . self::TABLE . '`');
    }

    /**
     * Saves with the cart the Doofinder session of the cookie, when it changes in the storefront.
     * In the back office the cookie would be the employee's. Older versions than isSupported()
     * never send the sales, so they don't save it.
     *
     * @param \Cart $cart
     *
     * @return void
     */
    public static function saveCartSession($cart)
    {
        if (!self::isSupported()
            || !(\Context::getContext()->controller instanceof \FrontController)
            || empty($_COOKIE[self::COOKIE])
            || !\Validate::isLoadedObject($cart)) {
            return;
        }

        $cookie = json_decode($_COOKIE[self::COOKIE], true);
        $session = [];
        foreach (['session_id', 'user_id', 'hashid'] as $key) {
            if (isset($cookie[$key]) && is_string($cookie[$key]) && preg_match('/^[A-Za-z0-9-]{1,64}$/', $cookie[$key])) {
                $session[$key] = $cookie[$key];
            }
        }

        if (!isset($session['session_id'], $session['user_id'])) {
            return;
        }

        $userAgent = isset($_SERVER['HTTP_USER_AGENT']) ? \Tools::substr($_SERVER['HTTP_USER_AGENT'], 0, 255) : '';

        \Db::getInstance()->insert(
            self::TABLE,
            [
                'id_cart' => (int) $cart->id,
                'session_id' => pSQL($session['session_id']),
                'user_id' => pSQL($session['user_id']),
                'hashid' => isset($session['hashid']) ? pSQL($session['hashid']) : null,
                'user_agent' => pSQL($userAgent),
                'date_upd' => date('Y-m-d H:i:s'),
            ],
            true,
            false,
            \Db::REPLACE
        );
    }

    /**
     * Sends the sale of the cart of an order that gets a valid or paid status, unless an order of
     * the cart had one before (the sale was sent then) or the order was created in the back office.
     * Older versions than isSupported() never get their sales from the module, so they send nothing
     * instead of making the order wait for a sale Doofinder would skip.
     *
     * Doofinder gets only what the sale needs: no customer data.
     *
     * @param int $idOrder
     * @param \OrderState $newStatus
     *
     * @return void
     */
    public static function sendSale($idOrder, $newStatus)
    {
        if (!self::isSupported()
            || !\Validate::isLoadedObject($newStatus)
            || !($newStatus->logable || $newStatus->paid)) {
            return;
        }

        $order = new \Order((int) $idOrder);
        $orderIds = array_map('intval', array_column(\Db::getInstance()->executeS(
            'SELECT id_order FROM `' . _DB_PREFIX_ . 'orders` WHERE id_cart = ' . (int) $order->id_cart
        ), 'id_order'));

        if (self::createdInBackOffice($order) || self::wasValid($orderIds)) {
            return;
        }

        $hashid = SearchEngine::getHashId($order->id_lang, $order->id_currency, $order->id_shop_group, $order->id_shop);
        $apiKey = DfTools::getFormattedApiKey();
        if (empty($hashid) || empty($apiKey)) {
            return;
        }

        $session = self::cartSession($order->id_cart);
        $sale = [
            'cart_id' => (int) $order->id_cart,
            'currency' => (new \Currency($order->id_currency))->iso_code,
            'user_agent' => $session ? $session['user_agent'] : null,
            'session' => $session ? [
                'session_id' => $session['session_id'],
                'user_id' => $session['user_id'],
                'hashid' => $session['hashid'],
            ] : null,
            'line_items' => self::lineItems($orderIds, $order->id_shop_group, $order->id_shop),
        ];

        $response = EasyREST::createClient(UrlManager::getSalesUrl(\Configuration::get('DF_REGION'), $hashid))
            ->setParameters(json_encode($sale))
            ->setMethod('POST')
            ->setContentType('application/json')
            ->setHttpHeaders(['Authorization: Token ' . $apiKey])
            ->setTimeout(self::TIMEOUT_IN_SECONDS)
            ->execute()
            ->close();

        if (200 !== $response->getResponseCode()) {
            DoofinderConfig::debug('Error sending the sale of cart ' . $order->id_cart . ': ' . $response->getResponseCode() . ' ' . $response->response);
        }
    }

    /**
     * Asks Doofinder to take the sales of every shop from the module, when the storefront lets the
     * Doofinder script read the carts. Shops without an installation are skipped.
     *
     * @return void
     */
    public static function activate()
    {
        $apiKey = DfTools::getFormattedApiKey();
        if (!self::isSupported() || empty($apiKey)) {
            return;
        }

        foreach (\Shop::getShops() as $shop) {
            $installationId = \Configuration::get('DF_INSTALLATION_ID', null, $shop['id_shop_group'], $shop['id_shop']);
            if (empty($installationId)) {
                continue;
            }

            $response = EasyREST::post(
                UrlManager::getSalesActivateUrl(\Configuration::get('DF_REGION')),
                json_encode(['installation_id' => $installationId]),
                null,
                null,
                'application/json',
                ['Authorization: Token ' . $apiKey]
            );

            DoofinderConfig::debug('Sales from platform activation of ' . $installationId . ': ' . $response->getResponseCode());
        }
    }

    /**
     * PrestaShop saves the employee who creates an order in the back office in its first status.
     * While the order is being created that status isn't saved yet, but the employee is in the
     * context.
     *
     * @param \Order $order
     *
     * @return bool
     */
    private static function createdInBackOffice($order)
    {
        $idEmployee = \Db::getInstance()->getValue(
            'SELECT id_employee FROM `' . _DB_PREFIX_ . 'order_history`
            WHERE id_order = ' . (int) $order->id . ' ORDER BY id_order_history ASC'
        );

        if (false === $idEmployee) {
            $employee = \Context::getContext()->employee;

            return \Validate::isLoadedObject($employee);
        }

        return (int) $idEmployee > 0;
    }

    /**
     * Whether an order had a valid or paid status before. The status being set isn't in the history
     * yet.
     *
     * @param int[] $orderIds
     *
     * @return bool
     */
    private static function wasValid($orderIds)
    {
        return (bool) \Db::getInstance()->getValue(
            'SELECT 1 FROM `' . _DB_PREFIX_ . 'order_history` oh
            INNER JOIN `' . _DB_PREFIX_ . 'order_state` os ON os.id_order_state = oh.id_order_state
            WHERE oh.id_order IN (' . implode(',', $orderIds) . ') AND (os.logable = 1 OR os.paid = 1)'
        );
    }

    /**
     * The Doofinder session of a cart, unless older than SESSION_DAYS. The older ones are deleted.
     *
     * @param int $idCart
     *
     * @return array|false
     */
    private static function cartSession($idCart)
    {
        $db = \Db::getInstance();
        $db->delete(self::TABLE, 'date_upd < DATE_SUB(NOW(), INTERVAL ' . (int) self::SESSION_DAYS . ' DAY)');

        return $db->getRow(
            'SELECT session_id, user_id, hashid, user_agent FROM `' . _DB_PREFIX_ . self::TABLE . '`
            WHERE id_cart = ' . (int) $idCart
        );
    }

    /**
     * The lines of the orders with the id they are indexed with, as the feed builds it, and their
     * unit price before the cart rules, with or without taxes as the feed takes them.
     *
     * @param int[] $orderIds
     * @param int $idShopGroup
     * @param int $idShop
     *
     * @return array
     */
    private static function lineItems($orderIds, $idShopGroup, $idShop)
    {
        $withTaxes = (bool) DfTools::cfg($idShop, 'DF_GS_PRICES_USE_TAX', DoofinderConstants::YES);
        $variations = (bool) \Configuration::get('DF_SHOW_PRODUCT_VARIATIONS', null, $idShopGroup, $idShop);
        $lines = \Db::getInstance()->executeS(
            'SELECT product_id, product_attribute_id, product_quantity, product_name,
                unit_price_tax_incl, unit_price_tax_excl
            FROM `' . _DB_PREFIX_ . 'order_detail` WHERE id_order IN (' . implode(',', $orderIds) . ')'
        );

        $items = [];
        foreach ($lines as $line) {
            $items[] = [
                'id' => $variations && (int) $line['product_attribute_id'] > 0
                    ? 'VAR-' . (int) $line['product_attribute_id']
                    : (string) (int) $line['product_id'],
                'quantity' => (int) $line['product_quantity'],
                'price' => round((float) ($withTaxes ? $line['unit_price_tax_incl'] : $line['unit_price_tax_excl']), 2),
                'title' => $line['product_name'],
            ];
        }

        return $items;
    }
}
