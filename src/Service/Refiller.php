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

use AnimeDb\Bundle\CatalogBundle\Plugin\Fill\Refiller\Refiller as RefillerPlugin;
use AnimeDb\Bundle\ShikimoriBrowserBundle\Service\Browser;
use AnimeDb\Bundle\CatalogBundle\Plugin\Fill\Refiller\Item as ItemRefiller;
use AnimeDb\Bundle\CatalogBundle\Entity\Item;
use AnimeDb\Bundle\CatalogBundle\Entity\Source;
use AnimeDb\Bundle\CatalogBundle\Entity\Image;
use AnimeDb\Bundle\CatalogBundle\Entity\Name;

/**
 * Refiller from site shikimori.org
 * 
 * @link http://shikimori.org/
 * @package AnimeDb\Bundle\ShikimoriFillerBundle\Service
 * @author  Peter Gribanov <info@peter-gribanov.ru>
 */
class Refiller extends RefillerPlugin
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
     * List of supported fields
     *
     * @var array
     */
    protected $supported_fields = [
        self::FIELD_DATE_END,
        self::FIELD_DATE_PREMIERE,
        self::FIELD_DURATION,
        self::FIELD_EPISODES_NUMBER,
        self::FIELD_GENRES,
        self::FIELD_IMAGES,
        self::FIELD_NAMES,
        self::FIELD_STUDIO,
        self::FIELD_SOURCES,
        self::FIELD_SUMMARY
    ];

    /**
     * Browser
     *
     * @var \AnimeDb\Bundle\ShikimoriBrowserBundle\Service\Browser
     */
    private $browser;

    /**
     * Filler
     *
     * @var \AnimeDb\Bundle\ShikimoriFillerBundle\Service\Filler
     */
    protected $filler;

    /**
     * Search
     *
     * @var \AnimeDb\Bundle\ShikimoriFillerBundle\Service\Search
     */
    protected $search;

    /**
     * Construct
     *
     * @param \AnimeDb\Bundle\ShikimoriBrowserBundle\Service\Browser $browser
     * @param \AnimeDb\Bundle\ShikimoriFillerBundle\Service\Filler $filler
     * @param \AnimeDb\Bundle\ShikimoriFillerBundle\Service\Search $search
     */
    public function __construct(Browser $browser, Filler $filler, Search $search)
    {
        $this->browser = $browser;
        $this->filler = $filler;
        $this->search = $search;
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
     * Is can refill item from source
     *
     * @param \AnimeDb\Bundle\CatalogBundle\Entity\Item $item
     * @param string $field
     *
     * @return boolean
     */
    public function isCanRefill(Item $item, $field)
    {
        if (!in_array($field, $this->supported_fields)) {
            return false;
        }
        /* @var $source \AnimeDb\Bundle\CatalogBundle\Entity\Source */
        foreach ($item->getSources() as $source) {
            if (strpos($source->getUrl(), $this->browser->getHost()) === 0) {
                return true;
            }
        }
        return false;
    }

    /**
     * Refill item field from source
     *
     * @param \AnimeDb\Bundle\CatalogBundle\Entity\Item $item
     * @param string $field
     *
     * @return \AnimeDb\Bundle\CatalogBundle\Entity\Item
     */
    public function refill(Item $item, $field)
    {
        // get source url
        $url = '';
        foreach ($item->getSources() as $source) {
            if (strpos($source->getUrl(), $this->browser->getHost()) === 0) {
                $url = $source->getUrl();
                break;
            }
        }
        if (!$url) {
            return $item;
        }

        // get data
        preg_match(Filler::REG_ITEM_ID, $url, $match);
        $path = str_replace('#ID#', $match['id'], Filler::FILL_URL);
        $body = $this->browser->get($path);

        switch ($field) {
            case self::FIELD_DATE_END:
                if ($body['released_on']) {
                    $item->setDateEnd(new \DateTime($body['released_on']));
                }
                break;
            case self::FIELD_DATE_PREMIERE:
                $item->setDatePremiere(new \DateTime($body['aired_on']));
                break;
            case self::FIELD_DURATION:
                $item->setDuration($body['duration']);
                break;
            case self::FIELD_EPISODES_NUMBER:
                $ep_num = $body['episodes_aired'] ? $body['episodes_aired'] : $body['episodes'];
                $item->setEpisodesNumber($ep_num.($body['ongoing'] ? '+' : ''));
                break;
            case self::FIELD_GENRES:
                $new_item = $this->filler->setGenres(new Item(), $body);
                /* @var $new_genre \AnimeDb\Bundle\CatalogBundle\Entity\Genre */
                foreach ($new_item->getGenres() as $new_genre) {
                    // check of the existence of the genre
                    /* @var $genre \AnimeDb\Bundle\CatalogBundle\Entity\Genre */
                    foreach ($item->getGenres() as $genre) {
                        if ($new_genre->getId() == $genre->getId()) {
                            continue 2;
                        }
                    }
                    $item->addGenre($new_genre);
                }
                break;
            case self::FIELD_IMAGES:
                $this->filler->setImages($item, $body);
                break;
            case self::FIELD_NAMES:
                $new_item = $this->filler->setNames(new Item(), $body);
                // set main name in top of names list
                $names = array_merge([(new Name)->setName($new_item->getName())], $new_item->getNames()->toArray());
                /* @var $new_name \AnimeDb\Bundle\CatalogBundle\Entity\Name */
                foreach ($names as $new_name) {
                    // check of the existence of the name
                    /* @var $name \AnimeDb\Bundle\CatalogBundle\Entity\Name */
                    foreach ($item->getNames() as $name) {
                        if ($new_name->getName() == $name->getName()) {
                            continue 2;
                        }
                    }
                    $item->addName($new_name);
                }
                break;
            case self::FIELD_SOURCES:
                $new_item = $this->filler->setSources(new Item(), $body);
                /* @var $new_source \AnimeDb\Bundle\CatalogBundle\Entity\Source */
                foreach ($new_item->getSources() as $new_source) {
                    // check of the existence of the source
                    /* @var $source \AnimeDb\Bundle\CatalogBundle\Entity\Source */
                    foreach ($item->getSources() as $source) {
                        if ($new_source->getUrl() == $source->getUrl()) {
                            continue 2;
                        }
                    }
                    $item->addSource($new_source);
                }
                break;
            case self::FIELD_STUDIO:
                $this->filler->setStudio($item, $body);
                break;
            case self::FIELD_SUMMARY:
                $item->setSummary($body['description']);
                break;
        }

        return $item;
    }

    /**
     * Is can search
     *
     * @param \AnimeDb\Bundle\CatalogBundle\Entity\Item $item
     * @param string $field
     *
     * @return boolean
     */
    public function isCanSearch(Item $item, $field)
    {
        if (!in_array($field, $this->supported_fields)) {
            return false;
        }
        if ($this->isCanRefill($item, $field) || $item->getName()) {
            return true;
        }
        /* @var $name \AnimeDb\Bundle\CatalogBundle\Entity\Name */
        foreach ($item->getNames() as $name) {
            if ($name->getName()) {
                return true;
            }
        }
        return false;
    }

    /**
     * Search items for refill
     *
     * @param \AnimeDb\Bundle\CatalogBundle\Entity\Item $item
     * @param string $field
     *
     * @return array [\AnimeDb\Bundle\CatalogBundle\Plugin\Fill\Refiller\Item]
     */
    public function search(Item $item, $field)
    {
        // search source url
        $url = '';
        foreach ($item->getSources() as $source) {
            if (strpos($source->getUrl(), $this->browser->getHost()) === 0) {
                $url = $source->getUrl();
                break;
            }
        }
        // can refill from source. not need search
        if ($url) {
            return [
                new ItemRefiller(
                    $item->getName(),
                    ['url' => $url],
                    $url,
                    $item->getCover(),
                    $item->getSummary()
                )
            ];
        }

        // get name for search
        if (!($name = $item->getName())) {
            foreach ($item->getNames() as $name) {
                if ($name) {
                    break;
                }
            }
        }

        $result = [];
        // do search
        if ($name) {
            $result = $this->search->search(['name' => $name]);
            /* @var $item \AnimeDb\Bundle\CatalogBundle\Plugin\Fill\Search\Item */
            foreach ($result as $key => $item) {
                parse_str(parse_url($item->getLink(), PHP_URL_QUERY), $query);
                $link = array_values($query)[0]['url'];
                $result[$key] = new ItemRefiller(
                    $item->getName(),
                    ['url' => $link],
                    $link,
                    $item->getImage(),
                    $item->getDescription()
                );
            }
        }

        return $result;
    }

    /**
     * Refill item field from search result
     *
     * @param \AnimeDb\Bundle\CatalogBundle\Entity\Item $item
     * @param string $field
     * @param array $data
     *
     * @return \AnimeDb\Bundle\CatalogBundle\Entity\Item
     */
    public function refillFromSearchResult(Item $item, $field, array $data)
    {
        if (!empty($data['url'])) {
            $source = new Source();
            $source->setUrl($data['url']);
            $item->addSource($source);
            $item = $this->refill($item, $field);
        }
        return $item;
    }
}