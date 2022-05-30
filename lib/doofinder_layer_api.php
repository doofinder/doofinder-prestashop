<?php
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
 *
 */

require_once _PS_MODULE_DIR_ . 'doofinder/lib/EasyREST.php';

class DoofinderLayerApi
{
    /**
     * Function that returns a hashid given an installationID, currency and language. 
     * Since this API returns the default hashid if both currency and language do not match, 
     * a check is made to see if the desired hashid is returned.
     */
    public static function getHashidByInstallationID($installationID, $currency, $language) {
        $client = new EasyREST();
        $api_endpoint = "https://eu1-layer.doofinder.com/api/1/installation/".$installationID.'?currency='.$currency.'&language='.$language;

        $response = $client->get($api_endpoint);

        if($response->headers['code'] === 200){
            $options = json_decode($response->response)->options;
            if($currency === $options->currency && $language === $options->language)
            {
                return $options->hashid;
            }
        }
        
        return null;
    }
}