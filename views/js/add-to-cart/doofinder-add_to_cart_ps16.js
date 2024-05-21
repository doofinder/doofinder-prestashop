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

    let id_product_cart;
    let id_customization;
    if (cartOptions.productID.includes('VAR-')) {
        id_product_cart = cartOptions.group_id;
        id_customization = cartOptions.productID.replace('VAR-', ''); 
    } else {
        id_product_cart = cartOptions.productID;
        id_customization = cartOptions.customizationID;
    }

    ajaxCart.add(id_product_cart, id_customization, undefined, undefined, cartOptions.quantity);
}
