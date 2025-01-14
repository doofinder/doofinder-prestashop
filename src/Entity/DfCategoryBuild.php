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

class DfCategoryBuild
{
    private $idShop;
    private $idLang;
    private $categories;
    private $link;

    public function __construct($idShop, $idLang)
    {
        $this->idShop = $idShop;
        $this->idLang = $idLang;
    }

    /**
     * Set the categories to be included in the payload
     *
     * @param array $arrayCategories categories ids
     */
    public function setCategories($arrayCategories)
    {
        $this->categories = $arrayCategories;
    }

    public function build($json = true)
    {
        $this->assign();

        $payload = [];

        foreach ($this->categories as $category) {
            if (\Category::categoryExists($category)) {
                $payload[] = $this->buildCategory($category);
            }
        }

        return $json ? json_encode($payload) : $payload;
    }

    private function assign()
    {
        $this->link = \Context::getContext()->link;
    }

    private function buildCategory($idCategory)
    {
        $category = new \Category($idCategory, $this->idLang, $this->idShop);

        $c = [];

        $tableName = 'category';
        $tableName = (method_exists(get_class(new \ImageType()), 'getFormattedName')) ? \ImageType::getFormattedName($tableName) : $tableName . '_default';

        $c['id'] = (string) $category->id;
        $c['title'] = DfTools::cleanString($category->name);
        $c['description'] = DfTools::cleanString($category->description);
        $c['meta_title'] = DfTools::cleanString($category->meta_title);
        $c['meta_description'] = DfTools::cleanString($category->meta_description);
        $c['tags'] = DfTools::cleanString($category->meta_keywords);
        $c['link'] = $this->link->getCategoryLink($category);
        $c['image_link'] = $category->id_image ? $this->link->getCatImageLink($category->link_rewrite, $category->id_image, $tableName) : '';

        return $c;
    }
}
