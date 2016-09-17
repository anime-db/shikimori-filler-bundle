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

use AnimeDb\Bundle\CatalogBundle\Plugin\Fill\Refiller\RefillerInterface;
use AnimeDb\Bundle\ShikimoriBrowserBundle\Service\Browser;
use AnimeDb\Bundle\CatalogBundle\Plugin\Fill\Search\Item as ItemSearch;
use AnimeDb\Bundle\CatalogBundle\Plugin\Fill\Refiller\Item as ItemRefiller;
use AnimeDb\Bundle\CatalogBundle\Entity\Item;
use AnimeDb\Bundle\CatalogBundle\Entity\Source;
use AnimeDb\Bundle\CatalogBundle\Entity\Name;

class Refiller implements RefillerInterface
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
     * @var Browser
     */
    private $browser;

    /**
     * @var Filler
     */
    protected $filler;

    /**
     * @var Search
     */
    protected $search;

    /**
     * @param Browser $browser
     * @param Filler $filler
     * @param Search $search
     */
    public function __construct(Browser $browser, Filler $filler, Search $search)
    {
        $this->browser = $browser;
        $this->filler = $filler;
        $this->search = $search;
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
    public function getTitle() {
        return self::TITLE;
    }

    /**
     * Is can refill item from source
     *
     * @param Item $item
     * @param string $field
     *
     * @return boolean
     */
    public function isCanRefill(Item $item, $field)
    {
        return in_array($field, $this->supported_fields) && $this->getSourceForFill($item);
    }

    /**
     * Refill item field from source
     *
     * @param Item $item
     * @param string $field
     *
     * @return Item
     */
    public function refill(Item $item, $field)
    {
        if (!($url = $this->getSourceForFill($item))) {
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
                foreach ($new_item->getGenres() as $new_genre) {
                    $item->addGenre($new_genre);
                }
                break;
            case self::FIELD_IMAGES:
                $this->filler->setImages($item, $body);
                break;
            case self::FIELD_NAMES:
                $new_item = $this->filler->setNames(new Item(), $body);
                // set main name in top of names list
                $names = $new_item->getNames()->toArray();
                array_unshift($names, (new Name())->setName($new_item->getName()));
                foreach ($names as $new_name) {
                    $item->addName($new_name);
                }
                break;
            case self::FIELD_SOURCES:
                $new_item = $this->filler->setSources(new Item(), $body);
                foreach ($new_item->getSources() as $new_source) {
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
     * @param Item $item
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

        /* @var $name Name */
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
     * @param Item $item
     * @param string $field
     *
     * @return ItemRefiller[]
     */
    public function search(Item $item, $field)
    {
        // can refill from source. not need search
        if ($url = $this->getSourceForFill($item)) {
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
            /* @var $item ItemSearch */
            foreach ($result as $key => $item) {
                if ($query = parse_url($item->getLink(), PHP_URL_QUERY)) {
                    parse_str($query, $query);
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
        }

        return $result;
    }

    /**
     * Refill item field from search result
     *
     * @param Item $item
     * @param string $field
     * @param array $data
     *
     * @return Item
     */
    public function refillFromSearchResult(Item $item, $field, array $data)
    {
        if (!empty($data['url'])) {
            $item->addSource((new Source())->setUrl($data['url']));
            $item = $this->refill($item, $field);
        }
        return $item;
    }

    /**
     * @param Item $item
     *
     * @return string
     */
    public function getSourceForFill(Item $item)
    {
        /* @var $source Source */
        foreach ($item->getSources() as $source) {
            if (strpos($source->getUrl(), $this->browser->getHost()) === 0) {
                return $source->getUrl();
            }
        }

        return '';
    }
}
