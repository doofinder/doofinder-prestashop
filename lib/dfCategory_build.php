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
if (!class_exists('dfTools')) {
    require_once dirname(__FILE__) . '/dfTools.class.php';
}

class DfCategoryBuild
{
    public function __construct($id_shop, $id_lang)
    {
        $this->id_shop = $id_shop;
        $this->id_lang = $id_lang;
    }

    /**
     * Set the categories to be included in the payload
     *
     * @param array Categories ids
     */
    public function setCategories($array_categories)
    {
        $this->categories = $array_categories;
    }

    public function build($json = true)
    {
        $this->assign();

        foreach ($this->categories as $category) {
            $payload[] = $this->buildCategory($category);
        }

        return $json ? json_encode($payload) : $payload;
    }

    private function assign()
    {
        $this->link = Context::getContext()->link;
    }

    private function buildCategory($id_category)
    {
        $category = new Category($id_category, $this->id_lang, $this->id_shop);

        $c = [];

        $c['id'] = $category->id;
        $c['title'] = dfTools::cleanString($category->name);
        $c['description'] = dfTools::cleanString($category->description);
        $c['meta_title'] = dfTools::cleanString($category->meta_title);
        $c['meta_description'] = dfTools::cleanString($category->meta_description);
        $c['tags'] = dfTools::cleanString($category->meta_keywords);
        $c['link'] = $this->link->getCategoryLink($category);
        $c['image_link'] = $category->id_image ? $this->link->getCatImageLink($category->link_rewrite, $category->id_image, 'category_default') : '';

        return $c;
    }
}
