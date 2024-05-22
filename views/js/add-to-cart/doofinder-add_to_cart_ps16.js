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

    let IdProductCart;
    let IdCustomization;
    if (cartOptions.productID.includes('VAR-')) {
        IdProductCart = cartOptions.group_id;
        IdCustomization = cartOptions.productID.replace('VAR-', ''); 
    } else {
        IdProductCart = cartOptions.productID;
        IdCustomization = cartOptions.customizationID;
    }

    ajaxCart.add(IdProductCart, IdCustomization, undefined, undefined, cartOptions.quantity);
}
