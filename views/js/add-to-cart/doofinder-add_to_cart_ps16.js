//implementation of "add to cart" functionality for prestashop 1.6.x

let doofinderManageCart = (cartOptions) => {
    ajaxCart.add(cartOptions.productID, cartOptions.customizationID);
    closeDoofinderLayer();
}