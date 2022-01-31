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

//implementation of "add to cart" functionality for prestashop 1.7.x

let dfAddToCart = (cartOptions) => {
    /**
     * 1º create the necessary form to add to cart with the required inputs.
     * (the "name" attribute of the inputs is necessary to trigger the event that prestashop uses natively
     * for "add to cart").
     */
    let form = document.createElement("form");
    Object.assign(form, {
        method: "post",
        action: cartOptions.cartURL,
        style: "display: none;"
    });

    let cuantityInput = document.createElement("input");
    Object.assign(cuantityInput, {
        type    : "number",
        name    : "qty",
        value   : cartOptions.cuantity,
        min     : 1
    });

    let productAttributeInput = document.createElement("input");
    Object.assign(productAttributeInput, {
        type    : "hidden",
        name    : "id_product_attribute",
        value   : cartOptions.customizationID
    });

    let productInput = document.createElement("input");
    Object.assign(productInput, {
        type    : "hidden",
        name    : "id_product",
        value   : cartOptions.productID
    });

    let tokenInput = document.createElement("input");
    Object.assign(tokenInput, {
        type    : "hidden",
        name    : "token",
        value   : cartOptions.cartToken
    });

    let submit = document.createElement("input");
    submit.setAttribute("type", "submit");
    submit.setAttribute("data-button-action", "add-to-cart");

    form.appendChild(cuantityInput);
    form.appendChild(productAttributeInput);
    form.appendChild(productInput);
    form.appendChild(tokenInput);
    form.appendChild(submit);

    /**
     * 2º Add to DOM the form.
     */
    document.body.appendChild(form);

    /**
     * 3º clicks on the submit (does not work if you invoke the submit event) 
     * and removes the form from the DOM
     */
    submit.click();
    form.remove();
}

let doofinderManageCart = (cartOptions) => {
    dfAddToCart(cartOptions);
    closeDoofinderLayer();
}
