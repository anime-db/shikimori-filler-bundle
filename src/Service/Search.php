<?php
/**
 * AnimeDb package.
 *
 * @author    Peter Gribanov <info@peter-gribanov.ru>
 * @copyright Copyright (c) 2011, Peter Gribanov
 * @license   http://opensource.org/licenses/GPL-3.0 GPL v3
 */
namespace AnimeDb\Bundle\ShikimoriFillerBundle\Service;

use AnimeDb\Bundle\CatalogBundle\Plugin\Fill\Search\Search as SearchPlugin;
use AnimeDb\Bundle\CatalogBundle\Plugin\Fill\Search\Item as ItemSearch;
use AnimeDb\Bundle\ShikimoriBrowserBundle\Service\Browser;
use AnimeDb\Bundle\ShikimoriFillerBundle\Form\Type\Search as SearchForm;
use Knp\Menu\ItemInterface;

class Search extends SearchPlugin
{
    /**
     * @var string
     */
    const NAME = 'shikimori';

    /**
     * @var string
     */
    const TITLE = 'Shikimori.org';

    /**
     * Path for search.
     *
     * @var string
     */
    const SEARH_URL = '/animes?limit=#LIMIT#&search=#NAME#&genre=#GENRE#&type=#TYPE#&season=#SEASON#';

    /**
     * Limit the search results list.
     *
     * @var int
     */
    const DEFAULT_LIMIT = 30;

    /**
     * @var Browser
     */
    private $browser;

    /**
     * @var string
     */
    protected $locale;

    /**
     * @var SearchForm
     */
    protected $form;

    /**
     * @param Browser $browser
     * @param SearchForm $form
     * @param string $locale
     */
    public function __construct(Browser $browser, SearchForm $form, $locale)
    {
        $this->browser = $browser;
        $this->locale = $locale;
        $this->form = $form;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return self::NAME;
    }

    /**
     * @return string
     */
    public function getTitle()
    {
        return self::TITLE;
    }

    /**
     * @param ItemInterface $item
     *
     * @return ItemInterface
     */
    public function buildMenu(ItemInterface $item)
    {
        return parent::buildMenu($item)
            ->setLinkAttribute('class', 'icon-label icon-label-plugin-shikimori');
    }

    /**
     * @param array $data
     *
     * @return ItemSearch[]
     */
    public function search(array $data)
    {
        $path = str_replace('#NAME#', urlencode($data['name']), self::SEARH_URL);
        $path = str_replace('#LIMIT#', self::DEFAULT_LIMIT, $path);
        $path = str_replace('#GENRE#', (isset($data['genre']) ? $data['genre'] : ''), $path);
        $path = str_replace('#TYPE#', (isset($data['type']) ? $data['type'] : ''), $path);
        $path = str_replace('#SEASON#', (isset($data['season']) ? str_replace('-', '_', $data['season']) : ''), $path);
        $body = (array) $this->browser->get($path);

        // build list
        foreach ($body as $key => $item) {
            // set a name based on the locale
            if ($this->locale == 'ru' && $item['russian']) {
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
                $description,
                $this->browser->getHost().$item['url']
            );
        }

        return $body;
    }

    /**
     * @return SearchForm
     */
    public function getForm()
    {
        return $this->form;
    }
}
