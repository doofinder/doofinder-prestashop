<?php
/**
 * @author    Doofinder
 * @copyright Doofinder
 * @license   MIT
 * @see       https://opensource.org/licenses/MIT
 */

namespace PrestaShop\Module\Doofinder\Feed;

use PrestaShop\Module\Doofinder\Utils\DfTools;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Builds structured payload data for categories in a PrestaShop store.
 *
 * This class retrieves category information, sanitizes the data using DfTools,
 * and generates an array or JSON payload for CSV export with relevant page details such as
 * title, meta information, description and links.
 */
class DfCategoryBuild
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
     * @var array list of category IDs to include in the payload
     */
    private $categories;

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
     * Set the categories to be included in the payload
     *
     * @param array $arrayCategories categories ids
     */
    public function setCategories($arrayCategories)
    {
        $this->categories = $arrayCategories;
    }

    /**
     * Sets the category IDs to be included in the payload.
     *
     * @param bool $json whether to return the payload as JSON (true) or array (false)
     *
     * @return string|array JSON string or array containing category data
     */
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
     * Builds a single category's data array.
     *
     * - Cleans text fields using DfTools.
     * - Retrieves category link and image link.
     *
     * @param int $idCategory category ID
     *
     * @return array Associative array with category details
     */
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
        $c['link'] = $this->link->getCategoryLink($category);
        $c['image_link'] = $category->id_image ? $this->link->getCatImageLink($category->link_rewrite, $category->id_image, $tableName) : '';

        return $c;
    }
}
