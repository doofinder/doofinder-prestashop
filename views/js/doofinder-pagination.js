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
function paginationButton(nbProductsIn, nbProductOut) {
    if (typeof (current_friendly_url) === 'undefined')
        current_friendly_url = '#';

    $('div.pagination a').not(':hidden').each(function() {
        if ($(this).attr('href').search(/(\?|&)p=/) == -1) {
            var page = 1;
        }
        else {
            var page = parseInt($(this).attr('href').replace(/^.*(\?|&)p=(\d+).*$/, '$2'));
            ;
        }
        var location = window.location.href.replace(/#.*$/, '');
        $(this).attr('href', location + current_friendly_url.replace(/\/page-(\d+)/, '') + '/page-' + page);
    });
    $('div.pagination li').not('.current, .disabled').each(function() {
        var nbPage = 0;
        if ($(this).hasClass('pagination_next'))
            nbPage = parseInt($('div.pagination li.current').children().children().html()) + 1;
        else if ($(this).hasClass('pagination_previous'))
            nbPage = parseInt($('div.pagination li.current').children().children().html()) - 1;

        $(this).children().on('click', function(e)
        {
            e.preventDefault();
            if (nbPage == 0)
                p = parseInt($(this).html()) + parseInt(nbPage);
                if(typeof p == 'undefined' || isNaN(p)){
                    p = parseInt($(this).find('span').html()) + parseInt(nbPage);
                    if(typeof p == 'undefined' || isNaN(p)){
                        console.log('Doofinder pagination problem! Please check that you not use a custom theme. Or modify the "find" declaration below to know where is the page number');
                    }
                }
            else
                p = nbPage;
            p = '&p=' + p;
            reloadContent(p);
            nbPage = 0;
        });
    });


    //product count refresh
    if (nbProductsIn != false) {
        if (isNaN(nbProductsIn) == 0) {
            // add variables
            var productCountRow = $('.product-count').html();
            var nbPage = parseInt($('div.pagination li.current').children().children().html());
            var nb_products = nbProductsIn;

            if ($('#nb_item option:selected').length == 0)
                var nbPerPage = nb_products;
            else
                var nbPerPage = parseInt($('#nb_item option:selected').val());

            isNaN(nbPage) ? nbPage = 1 : nbPage = nbPage;
            nbPerPage * nbPage < nb_products ? productShowing = nbPerPage * nbPage : productShowing = (nbPerPage * nbPage - nb_products - nbPerPage * nbPage) * -1;
            nbPage == 1 ? productShowingStart = 1 : productShowingStart = nbPerPage * nbPage - nbPerPage + 1;


            //insert values into a .product-count
            productCountRow = $.trim(productCountRow);
            productCountRow = productCountRow.split(' ');
            productCountRow[1] = productShowingStart;
            productCountRow[3] = (nbProductOut != 'undefined') && (nbProductOut > productShowing) ? nbProductOut : productShowing;
            productCountRow[5] = nb_products;

            if (productCountRow[3] > productCountRow[5])
                productCountRow[3] = productCountRow[5];

            productCountRow = productCountRow.join(' ');
            $('.product-count').html(productCountRow);
            $('.product-count').show();
        }
        else {
            $('.product-count').hide();
        }
    }
}


