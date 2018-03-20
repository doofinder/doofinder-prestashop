# doofinder-prestashop

Plugin that allows to configure the [Doofinder](http://www.doofinder.com) search service in a Prestashop 1.5 store with less effort than configuring it from scratch.

## How to install

The easiest way of installing the plugin is downloading it from our [support page](http://www.doofinder.com/support). If you want to download it from this page, you can download the latest release from the tags section, but you will have to prepare the module `.zip` file prior to installing it.

If it is the case, there is an included `package.sh` script file (UNIX systems) that will create the package for you. If you are using Windows refer to that script to get hints on how to create the package.

Once you have a `doofinder.zip` package file, please refer to the [Prestashop User Guide](http://doc.prestashop.com/display/PS15/Managing+Modules+and+Themes#ManagingModulesandThemes-Installingmodules) to get instructions on how to install the module.

## Configure Doofinder

The plugin has two configuration sections:

- **The Data Feed:** to configure the information displayed in the Doofinder data file.
- **The Doofinder Scripts:** to paste the init scripts for the Doofinder search layer.

### The Data Feed

Doofinder needs your product information to be read from a data file located in a public web URL. You will find the actual URLs published by this plugin under each of the script text boxes. They will look like:

    http://www.example.com/modules/doofinder/feed.php?lang=es

![Data Feed Settings](http://f.cl.ly/items/0G2I2T1J3G3r2I3X0T0o/the-data-feed.png)

In the Data Feed section you can configure these parameters:

- **Product Image Size:** The image size to be displayed in the layer from those defined in your store.
- **Product Description Length:** Index the short description or the long one. The latter is recommended.
- **Currency for each active language:** The price of the products will be converted to the selected currency using the internal conversion rates.

You can also force a different currency conversion by passing a `currency` parameter to the feed URL:

	http://www.example.com/modules/doofinder/feed.php?lang=es&currency=USD

The value must be the ISO alpha code for the currency and the currency must be active in your system. If not, then the default active currency will be used instead.

### The Doofinder Scripts

This section contains so many text boxes as languages you have activated in your online store.

In Doofinder you can have multiple search engines for one website but each search engine can index its that in only one language so, if your store has two languages configured and you want to use Doofinder in both languages you will need to create two search engines in the Doofinder site admin pane.

Once you have the init scripts for each of your store languages, you have to paste them in the corresponding text boxes.

![Doofinder Script Configuration](http://f.cl.ly/items/2D0N1w2V1e2q2l2j2b0I/the-script.png)

It is possible that you have to adjust the scripts to match your design preferences. Don't worry, it's a matter of changing some text values.

You can leave blank any of the text boxes. The layer will not be shown for that language.

#### Script sample

The Doofinder script looks like this:

    <script type="text/javascript">
        var doofinder_script ='//d3chj0zb5zcn0g.cloudfront.net/media/js/doofinder-3.latest.min.js';
        (function(d,t){
            var f=d.createElement(t),s=d.getElementsByTagName(t)[0];f.async=1;
                f.src=('https:'==location.protocol?'https:':'http:')+doofinder_script;
                s.parentNode.insertBefore(f,s)}(document,'script')
        );
        if(!doofinder){var doofinder={};}
        doofinder.options = {
            lang: 'en',
            hashid: 'fffff22da41abxxxxxxxxxx35daaaaaa',
            queryInput: '#search_query_top',
            width: 535,
            dleft: -112,
            dtop: 84,
            marginBottom: 0
        }
    </script>

At the end of the script you will see a `doofinder.options` section. Here is where you will have to make adjustments.

The Doofinder layer is attached to a search box. To identify that input control we use a *CSS selector*. In this case the selector is `#search_query_top` that identifies the HTML element with an id attribute with a value of `search_query_top`. It is the default search box in Prestashop.

There are three other parameters you probably will want to customize:

- `width`: The width of the layer. Use a number without quotes around it.
- `dleft`: Is the horizontal displacement of the layer from the point where it is placed automatically. You can use a positive or negative number without quotes around it.
- `dtop`: Is the vertical displacement of the layer from the point where it is placed automatically. You can use a positive or negative number without quotes around it.

If you decide to put the search box included with this plugin for the top of the page you probably will have to adjust these parameters. Remember to do it for each script.
