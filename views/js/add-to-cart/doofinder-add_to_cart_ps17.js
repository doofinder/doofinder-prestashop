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

class DoofinderAddToCartError extends Error {
    constructor(reason, status = "") {
      const message = "Error adding an item to the cart. Reason: " + reason + ". Status code: " + status;
      super(message);
      this.name = "DoofinderAddToCartError";
    }
}

//implementation of "add to cart" functionality for prestashop 1.7.x

let dfAddToCart = (cartOptions) => {

    let IdProductCart;
    let IdCustomization;
    if (cartOptions.productID.includes('VAR-')) {
        IdProductCart = cartOptions.group_id;
        IdCustomization = cartOptions.productID.replace('VAR-', ''); 
    } else {
        IdProductCart = cartOptions.productID;
        IdCustomization = cartOptions.customizationID;
    }

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

    let quantityInput = document.createElement("input");
    Object.assign(quantityInput, {
        type    : "number",
        name    : "qty",
        value   : cartOptions.quantity,
        min     : 1
    });

    let productAttributeInput = document.createElement("input");
    Object.assign(productAttributeInput, {
        type    : "hidden",
        name    : "id_product_attribute",
        value   : IdCustomization
    });

    let productInput = document.createElement("input");
    Object.assign(productInput, {
        type    : "hidden",
        name    : "id_product",
        value   : IdProductCart
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

    form.appendChild(quantityInput);
    form.appendChild(productAttributeInput);
    form.appendChild(productInput);
    form.appendChild(tokenInput);
    form.appendChild(submit);

    /**
     * 2º Add to DOM the form.
     */
    document.body.appendChild(form);

    if (typeof prestashop !== 'undefined' && typeof cartOptions.statusPromise !== 'undefined') {

        // It's triggered everytime the cart is updated.
        prestashop.on('updateCart', function (event) {
            // This should be trigger only if the cart has been updated from the layer. Otherwise, the statusPromise will be undefined.
            if (typeof cartOptions.statusPromise === 'undefined' || 
            !event || 
            !event.reason || 
            !event.resp) {
                return;
            }
            
            if (event.resp.success) {
                cartOptions.statusPromise.resolve("The item has been successfully added to the cart.");
            } else {
                // The event.resp.errors can be "" or an array
                errorList = Array.isArray(event.resp.errors) ? event.resp.errors.join(", ") : event.resp.errors;
                console.warn("Error adding to the cart: " + errorList);
                cartOptions.statusPromise.reject(new DoofinderAddToCartError(errorList));
                /* Special case: the product cannot be added because is customizable.
                I don't know if it's the best way to detect it, but at least it's better than
                detecting it from the error message. In this case, the user should be redirected
                to the product detail page */
                if (0 === event.resp.quantity && 'undefined' === typeof event.reason.cart) {
                    window.location.href = cartOptions.itemLink;
                }
            }
        });

        prestashop.on('handleError', function (event) {
             // This should be trigger only if the cart has been updated from the layer. Otherwise, the statusPromise will be undefined.
            if (typeof cartOptions.statusPromise === 'undefined') {
                return;
            }

            /* Possible errors variantions coming from the add to cart */
            if (event && event.eventType && ('updateProductInCart' === event.eventType || 
            'updateShoppingCart' === event.eventType || 
            'updateCart' === event.eventType || 
            'updateProductInCart' === event.eventType || 
            'updateProductQuantityInCart' === event.eventType || 
            'addProductToCart' === event.eventType)) {
                let message = "Unknown error";
                /* Empirically I've have reproduced this error after loading another page in a specific moment  */
                if (0 === event.resp.readyState) {
                    message = "The connection was interrupted while adding to the cart";
                }
                console.error(message);
                cartOptions.statusPromise.reject(new DoofinderAddToCartError(message));
            }
        });
    }
      
    /**
     * 3º clicks on the submit (does not work if you invoke the submit event) 
     * and removes the form from the DOM
     */
    submit.click();
    form.remove();
}

let doofinderManageCart = (cartOptions) => {
    dfAddToCart(cartOptions);
}
