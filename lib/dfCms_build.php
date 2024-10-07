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
 */

use PrestaShop\Module\Doofinder\Lib\DfTools;

if (!defined('_PS_VERSION_')) {
    exit;
}

class DfCmsBuild
{
    private $id_shop;
    private $id_lang;
    private $cms_pages;
    private $link;

    public function __construct($id_shop, $id_lang)
    {
        $this->id_shop = $id_shop;
        $this->id_lang = $id_lang;
    }

    /**
     * Set the CMS pages to be included in the payload
     *
     * @param array cms pages ids
     */
    public function setCmsPages($array_cms_pages)
    {
        $this->cms_pages = $array_cms_pages;
    }

    public function build($json = true)
    {
        $this->assign();

        foreach ($this->cms_pages as $cms) {
            $payload[] = $this->buildCms($cms);
        }

        return $json ? json_encode($payload) : $payload;
    }

    private function assign()
    {
        $this->link = Context::getContext()->link;
    }

    private function buildCms($id_cms)
    {
        $cms = new CMS($id_cms, $this->id_lang, $this->id_shop);

        $c = [];
        $c['id'] = (string) $cms->id;
        $c['title'] = DfTools::cleanString($cms->meta_title);
        $c['description'] = DfTools::cleanString($cms->meta_description);
        $c['meta_title'] = DfTools::cleanString($cms->meta_title);
        $c['meta_description'] = DfTools::cleanString($cms->meta_description);
        $c['tags'] = DfTools::cleanString($cms->meta_keywords);
        $c['content'] = DfTools::cleanString($cms->content);
        $c['link'] = $this->link->getCMSLink($cms);

        return $c;
    }
}
