/**
 * Copyright (c) Doofinder
 *
 * @license MIT
 * @see https://opensource.org/licenses/MIT
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
