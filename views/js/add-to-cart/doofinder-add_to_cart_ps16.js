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

//implementation of "add to cart" functionality for prestashop 1.6.x

let doofinderManageCart = (cartOptions) => {
    ajaxCart.add(cartOptions.productID, cartOptions.customizationID);
}