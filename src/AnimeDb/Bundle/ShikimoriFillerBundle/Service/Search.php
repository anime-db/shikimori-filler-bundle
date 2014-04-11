<?php
/**
 * AnimeDb package
 *
 * @package   AnimeDb
 * @author    Peter Gribanov <info@peter-gribanov.ru>
 * @copyright Copyright (c) 2011, Peter Gribanov
 * @license   http://opensource.org/licenses/GPL-3.0 GPL v3
 */

namespace AnimeDb\Bundle\ShikimoriFillerBundle\Service;

use AnimeDb\Bundle\CatalogBundle\Plugin\Fill\Search\Search as SearchPlugin;
use AnimeDb\Bundle\CatalogBundle\Plugin\Fill\Search\Item as ItemSearch;
use AnimeDb\Bundle\ShikimoriBrowserBundle\Service\Browser;

/**
 * Search from site shikimori.org
 * 
 * @link http://shikimori.org/
 * @package AnimeDb\Bundle\ShikimoriFillerBundle\Service
 * @author  Peter Gribanov <info@peter-gribanov.ru>
 */
class Search extends SearchPlugin
{
    /**
     * Name
     *
     * @var string
     */
    const NAME = 'shikimori';

    /**
     * Title
     *
     * @var string
     */
    const TITLE = 'Shikimori.org';

    /**
     * Path for search
     *
     * @var string
     */
    const SEARH_URL = '/animes?limit=#LIMIT#&search=#NAME#';

    /**
     * Limit the search results list
     *
     * @var integet
     */
    const DEFAULT_LIMIT = 30;

    /**
     * Browser
     *
     * @var \AnimeDb\Bundle\ShikimoriBrowserBundle\Service\Browser
     */
    private $browser;

    /**
     * Construct
     *
     * @param \AnimeDb\Bundle\ShikimoriBrowserBundle\Service\Browser $browser
     */
    public function __construct(Browser $browser) {
        $this->browser = $browser;
    }

    /**
     * Get name
     *
     * @return string
     */
    public function getName() {
        return self::NAME;
    }

    /**
     * Get title
     *
     * @return string
     */
    public function getTitle() {
        return self::TITLE;
    }

    /**
     * Search source by name
     *
     * Return structure
     * <code>
     * [
     *     \AnimeDb\Bundle\CatalogBundle\Plugin\Fill\Search\Item
     * ]
     * </code>
     *
     * @param array $data
     *
     * @return array
     */
    public function search(array $data)
    {
        $path = str_replace('#NAME#', urlencode($data['name']), self::SEARH_URL);
        $path = str_replace('#LIMIT#', self::DEFAULT_LIMIT, $path);
        $body = $this->browser->get($path);

        // build list
        foreach ($body as $key => $item) {
            $body[$key] = new ItemSearch(
                $item['name'],
                $this->getLinkForFill($this->browser->getHost().$item['url']),
                $this->browser->getHost().$item['image']['original'],
                $item['russian']
            );
        }
        return $body;
    }
}