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

/**
 * Builds structured payload data for CMS pages in a PrestaShop store.
 *
 * This class retrieves CMS page information, sanitizes the data using DfTools,
 * and generates an array or JSON payload for CSV export with relevant page details such as
 * title, meta information, content and links.
 */
class DfCmsBuild
{
    /**
     * @var int shop ID
     */
    private $idShop;

    /**
     * @var int language ID
     */
    private $idLang;

    /**
     * @var array list of CMS page IDs to include in the payload
     */
    private $cmsPages;

    /**
     * @var \Link prestashop link instance for building URLs
     */
    private $link;

    public function __construct($idShop, $idLang)
    {
        $this->idShop = $idShop;
        $this->idLang = $idLang;
    }

    /**
     * Sets the CMS page IDs to be included in the payload.
     *
     * @param array $arrayCmsPages list of CMS page IDs
     *
     * @return void
     */
    public function setCmsPages($arrayCmsPages)
    {
        $this->cmsPages = $arrayCmsPages;
    }

    /**
     * Builds the CMS pages payload.
     *
     * - Iterates over each provided CMS page ID.
     * - Retrieves and sanitizes CMS data.
     * - Generates an array or JSON structure containing page information.
     *
     * @param bool $json whether to return the payload as JSON (true) or array (false)
     *
     * @return string|array JSON string or array containing CMS page data
     */
    public function build($json = true)
    {
        $this->assign();

        $payload = [];

        foreach ($this->cmsPages as $cms) {
            $payload[] = $this->buildCms($cms);
        }

        return $json ? json_encode($payload) : $payload;
    }

    /**
     * Assigns required PrestaShop context properties (e.g., link builder).
     *
     * @return void
     */
    private function assign()
    {
        $this->link = \Context::getContext()->link;
    }

    /**
     * Builds a single CMS page's data array.
     *
     * - Cleans text fields using DfTools.
     * - Retrieves CMS link and content.
     *
     * @param int $idCms CMS page ID
     *
     * @return array associative array with CMS page details
     */
    private function buildCms($idCms)
    {
        $cms = new \CMS($idCms, $this->idLang, $this->idShop);

        $c = [];
        $c['id'] = (string) $cms->id;
        $c['title'] = DfTools::cleanString($cms->meta_title);
        $c['description'] = DfTools::cleanString($cms->meta_description);
        $c['meta_title'] = DfTools::cleanString($cms->meta_title);
        $c['meta_description'] = DfTools::cleanString($cms->meta_description);
        $c['content'] = DfTools::cleanString($cms->content);
        $c['link'] = $this->link->getCMSLink($cms);

        return $c;
    }
}
