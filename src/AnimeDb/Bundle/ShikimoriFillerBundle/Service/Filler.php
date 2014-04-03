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

use AnimeDb\Bundle\CatalogBundle\Plugin\Fill\Filler\Filler as FillerPlugin;
use AnimeDb\Bundle\ShikimoriFillerBundle\Service\Browser;
use Doctrine\Bundle\DoctrineBundle\Registry;
use Symfony\Component\Validator\Validator;
use Symfony\Component\Filesystem\Filesystem;
use AnimeDb\Bundle\CatalogBundle\Entity\Item;
use AnimeDb\Bundle\CatalogBundle\Entity\Name;
use AnimeDb\Bundle\CatalogBundle\Entity\Source;
use AnimeDb\Bundle\CatalogBundle\Entity\Genre;
use AnimeDb\Bundle\CatalogBundle\Entity\Studio;
use AnimeDb\Bundle\AppBundle\Entity\Field\Image as ImageField;

/**
 * Search from site shikimori.org
 * 
 * @link http://shikimori.org/
 * @package AnimeDb\Bundle\ShikimoriFillerBundle\Service
 * @author  Peter Gribanov <info@peter-gribanov.ru>
 */
class Filler extends FillerPlugin
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
     * Path to item
     *
     * @var string
     */
    const FILL_URL = '/animes/#ID#';

    /**
     * RegExp for get item id
     *
     * @var string
     */
    const REG_ITEM_ID = '#/animes/(?<id>\d+)\-#';

    /**
     * API host
     *
     * @var string
     */
    private $host;

    /**
     * Browser
     *
     * @var \AnimeDb\Bundle\ShikimoriFillerBundle\Service\Browser
     */
    private $browser;

    /**
     * Doctrine
     *
     * @var \Doctrine\Bundle\DoctrineBundle\Registry
     */
    private $doctrine;

    /**
     * Validator
     *
     * @var \Symfony\Component\Validator\Validator
     */
    private $validator;

    /**
     * Filesystem
     *
     * @var \Symfony\Component\Filesystem\Filesystem
     */
    private $fs;

    /**
     * Construct
     *
     * @param string $host
     * @param \AnimeDb\Bundle\ShikimoriFillerBundle\Service\Browser $browser
     * @param \Doctrine\Bundle\DoctrineBundle\Registry $doctrine
     * @param \Symfony\Component\Validator\Validator $validator
     * @param \Symfony\Component\Filesystem\Filesystem $fs
     */
    public function __construct(
        $host,
        Browser $browser,
        Registry $doctrine,
        Validator $validator,
        Filesystem $fs
    ) {
        $this->host = $host;
        $this->browser = $browser;
        $this->doctrine = $doctrine;
        $this->validator = $validator;
        $this->fs = $fs;
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
     * Fill item from source
     *
     * @param array $date
     *
     * @return \AnimeDb\Bundle\CatalogBundle\Entity\Item|null
     */
    public function fill(array $data)
    {
        if (empty($data['url']) || !is_string($data['url']) ||
            strpos($data['url'], $this->host) !== 0 ||
            !preg_match(self::REG_ITEM_ID, $data['url'], $match)
        ) {
            return null;
        }
        $path = str_replace('#ID#', $match['id'], self::FILL_URL);
        $body = $this->browser->get($path);

        $item = new Item();
        $item->setDuration($body['duration']);
        $item->setSummary(!empty($body['description_html']) ? $body['description_html'] : $body['description']);
        $item->setDatePremiere(new \DateTime($body['aired_on']));
        if ($body['released_on']) {
            $item->setDateEnd(new \DateTime($body['released_on']));
        }
        $ep_num = $body['episodes_aired'] ? $body['episodes_aired'] : $body['episodes'];
        $item->setEpisodesNumber($ep_num.($body['ongoing'] ? '+' : ''));

        // add source
        $source = new Source();
        $source->setUrl($data['url']);
        $item->addSource($source);

        // set complex data
        $this->setCover($item, $body);
        $this->setType($item, $body);
        $this->setNames($item, $body);
        $this->setGenres($item, $body);
        $this->setStudio($item, $body);
        return $item;
    }

    /**
     * Set item names
     *
     * @param \AnimeDb\Bundle\CatalogBundle\Entity\Item $item
     * @param array $body
     *
     * @return \AnimeDb\Bundle\CatalogBundle\Entity\Item
     */
    protected function setNames(Item $item, $body)
    {
        $names = array_merge([$body['russian']], $body['english'], $body['japanese'], $body['synonyms']);
        foreach ($names as $value) {
            if ($value != $body['name']) {
                $item->addName((new Name())->setName($value));
            }
        }
        return $item->setName($body['name']);
    }

    /**
     * Set item cover
     *
     * @param \AnimeDb\Bundle\CatalogBundle\Entity\Item $item
     * @param array $body
     *
     * @return \AnimeDb\Bundle\CatalogBundle\Entity\Item
     */
    protected function setCover(Item $item, $body)
    {
        if (!empty($body['image']) && !empty($body['image']['original'])) {
            try {
                $ext = pathinfo(parse_url($body['image']['original'], PHP_URL_PATH), PATHINFO_EXTENSION);
                $item->setCover($this->uploadImage($this->host.$body['image']['original'], $body['id'].'/1.'.$ext));
            } catch (\Exception $e) {}
        }
        return $item;
    }

    /**
     * Set item type
     *
     * @param \AnimeDb\Bundle\CatalogBundle\Entity\Item $item
     * @param array $body
     *
     * @return \AnimeDb\Bundle\CatalogBundle\Entity\Item
     */
    protected function setType(Item $item, $body)
    {
        // TODO set type
        return $item;
    }

    /**
     * Set item genres
     *
     * @param \AnimeDb\Bundle\CatalogBundle\Entity\Item $item
     * @param array $body
     *
     * @return \AnimeDb\Bundle\CatalogBundle\Entity\Item
     */
    protected function setGenres(Item $item, $body)
    {
        foreach ($body['genres'] as $genre) {
            $genre = $this->doctrine
                ->getRepository('AnimeDbCatalogBundle:Genre')
                ->findOneByName($genre);
            if ($genre instanceof Genre) {
                $item->addGenre($genre);
            }
        }
        return $item;
    }

    /**
     * Set item studio
     *
     * @param \AnimeDb\Bundle\CatalogBundle\Entity\Item $item
     * @param array $body
     *
     * @return \AnimeDb\Bundle\CatalogBundle\Entity\Item
     */
    protected function setStudio(Item $item, $body)
    {
        foreach ($body['studios'] as $studio) {
            $studio = $this->doctrine
                ->getRepository('AnimeDbCatalogBundle:Studio')
                ->findOneByName($studio['name']);
            if ($studio instanceof Studio) {
                $item->setStudio($studio);
                break;
            }
        }
        return $item;
    }

    /**
     * Upload image from url
     *
     * @param string $url
     * @param string|null $target
     *
     * @return string
     */
    protected function uploadImage($url, $target = null) {
        $image = new ImageField();
        $image->setRemote($url);
        $image->upload($this->validator, $target);
        return $image->getPath();
    }
}