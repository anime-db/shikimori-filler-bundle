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
use Symfony\Component\HttpFoundation\Request;

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
     * Request
     *
     * @var \Symfony\Component\HttpFoundation\Request
     */
    protected $request;

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
     * Set request
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     */
    public function setRequest(Request $request = null)
    {
        $this->request = $request;
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

        $locale = $this->request ? substr($this->request->getLocale(), 0, 2) : 'en';

        // build list
        foreach ($body as $key => $item) {
            // set a name based on the locale
            if ($locale == 'ru' && $item['russian']) {
                $name = $item['russian'];
                $description = $item['name'];
            } else {
                $name = $item['name'];
                $description = $item['russian'];
            }

            $body[$key] = new ItemSearch(
                $name,
                $this->getLinkForFill($this->browser->getHost().$item['url']),
                $this->browser->getHost().$item['image']['original'],
                $description
            );
        }
        return $body;
    }
}