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

namespace PrestaShop\Module\Doofinder\Src\Entity;

if (!defined('_PS_VERSION_')) {
    exit;
}

class DfCmsBuild
{
    private $idShop;
    private $idLang;
    private $cmsPages;
    private $link;

    public function __construct($idShop, $idLang)
    {
        $this->idShop = $idShop;
        $this->idLang = $idLang;
    }

    /**
     * Set the CMS pages to be included in the payload
     *
     * @param array $arrayCmsPages cms pages ids
     */
    public function setCmsPages($arrayCmsPages)
    {
        $this->cmsPages = $arrayCmsPages;
    }

    public function build($json = true)
    {
        $this->assign();

        $payload = [];

        foreach ($this->cmsPages as $cms) {
            $payload[] = $this->buildCms($cms);
        }

        return $json ? json_encode($payload) : $payload;
    }

    private function assign()
    {
        $this->link = \Context::getContext()->link;
    }

    private function buildCms($idCms)
    {
        $cms = new \CMS($idCms, $this->idLang, $this->idShop);

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
