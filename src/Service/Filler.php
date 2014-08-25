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
use AnimeDb\Bundle\ShikimoriBrowserBundle\Service\Browser;
use Doctrine\Bundle\DoctrineBundle\Registry;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use AnimeDb\Bundle\CatalogBundle\Entity\Item;
use AnimeDb\Bundle\CatalogBundle\Entity\Name;
use AnimeDb\Bundle\CatalogBundle\Entity\Source;
use AnimeDb\Bundle\CatalogBundle\Entity\Type;
use AnimeDb\Bundle\CatalogBundle\Entity\Genre;
use AnimeDb\Bundle\CatalogBundle\Entity\Studio;
use AnimeDb\Bundle\CatalogBundle\Entity\Image;
use AnimeDb\Bundle\AppBundle\Entity\Field\Image as ImageField;
use AnimeDb\Bundle\ShikimoriFillerBundle\Form\Filler as FillerForm;
use Knp\Menu\ItemInterface;

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
     * Path to item screenshots
     *
     * @var string
     */
    const FILL_IMAGES_URL = '/animes/#ID#/screenshots';

    /**
     * World-art item url
     *
     * @var string
     */
    const WORLD_ART_URL = 'http://www.world-art.ru/animation/animation.php?id=#ID#';

    /**
     * MyAnimeList item url
     *
     * @var string
     */
    const MY_ANIME_LIST_URL = 'http://myanimelist.net/anime/#ID#';

    /**
     * AniDB item url
     *
     * @var string
     */
    const ANI_DB_URL = 'http://anidb.net/perl-bin/animedb.pl?show=anime&aid=#ID#';

    /**
     * Browser
     *
     * @var \AnimeDb\Bundle\ShikimoriBrowserBundle\Service\Browser
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
     * @var \Symfony\Component\Validator\Validator\ValidatorInterface
     */
    private $validator;

    /**
     * Locale
     *
     * @var string
     */
    protected $locale;

    /**
     * Construct
     *
     * @param \AnimeDb\Bundle\ShikimoriBrowserBundle\Service\Browser $browser
     * @param \Doctrine\Bundle\DoctrineBundle\Registry $doctrine
     * @param \Symfony\Component\Validator\Validator\ValidatorInterface $validator
     * @param string $locale
     */
    public function __construct(
        Browser $browser,
        Registry $doctrine,
        ValidatorInterface $validator,
        $locale
    ) {
        $this->browser = $browser;
        $this->doctrine = $doctrine;
        $this->validator = $validator;
        $this->locale = $locale;
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
     * Get form
     *
     * @return \AnimeDb\Bundle\ShikimoriFillerBundle\Form\Filler
     */
    public function getForm()
    {
        return new FillerForm($this->browser->getHost());
    }

    /**
     * Build menu for plugin
     *
     * @param \Knp\Menu\ItemInterface $item
     *
     * @return \Knp\Menu\ItemInterface
     */
    public function buildMenu(ItemInterface $item)
    {
        return parent::buildMenu($item)
            ->setLinkAttribute('class', 'icon-label icon-plugin-shikimori');
    }

    /**
     * Fill item from source
     *
     * @param array $data
     *
     * @return \AnimeDb\Bundle\CatalogBundle\Entity\Item|null
     */
    public function fill(array $data)
    {
        if (empty($data['url']) || !is_string($data['url']) ||
            strpos($data['url'], $this->browser->getHost()) !== 0 ||
            !preg_match(self::REG_ITEM_ID, $data['url'], $match)
        ) {
            return null;
        }
        $path = str_replace('#ID#', $match['id'], self::FILL_URL);
        $body = $this->browser->get($path);

        $item = new Item();
        $item->setDuration($body['duration']);
        $item->setSummary($body['description']);
        $item->setDatePremiere(new \DateTime($body['aired_on']));
        if ($body['released_on']) {
            $item->setDateEnd(new \DateTime($body['released_on']));
        }
        $ep_num = $body['episodes_aired'] ? $body['episodes_aired'] : $body['episodes'];
        $item->setEpisodesNumber($ep_num.($body['ongoing'] ? '+' : ''));

        // set main source
        $source = new Source();
        $source->setUrl($data['url']);
        $item->addSource($source);

        // set complex data
        $this->setSources($item, $body);
        $this->setCover($item, $body);
        $this->setType($item, $body);
        $this->setNames($item, $body);
        $this->setGenres($item, $body);
        $this->setStudio($item, $body);
        if (!empty($data['frames'])) {
            $this->setImages($item, $body);
        }
        return $item;
    }

    /**
     * Set item sources
     *
     * @param \AnimeDb\Bundle\CatalogBundle\Entity\Item $item
     * @param array $body
     *
     * @return \AnimeDb\Bundle\CatalogBundle\Entity\Item
     */
    public function setSources(Item $item, $body)
    {
        if (!empty($body['world_art_id'])) {
            $source = new Source();
            $source->setUrl(str_replace('#ID#', $body['world_art_id'], self::WORLD_ART_URL));
            $item->addSource($source);
        }

        if (!empty($body['myanimelist_id'])) {
            $source = new Source();
            $source->setUrl(str_replace('#ID#', $body['myanimelist_id'], self::MY_ANIME_LIST_URL));
            $item->addSource($source);
        }

        if (!empty($body['ani_db_id'])) {
            $source = new Source();
            $source->setUrl(str_replace('#ID#', $body['ani_db_id'], self::ANI_DB_URL));
            $item->addSource($source);
        }

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
    public function setNames(Item $item, $body)
    {
        $names = [];
        // set a name based on the locale
        if ($this->locale == 'ru' && $body['russian']) {
            $names = array_merge([$body['name']], $body['english'], $body['japanese'], $body['synonyms']);
            $item->setName($body['russian']);
        } elseif ($this->locale == 'ja' && $body['japanese']) {
            $item->setName(array_shift($body['japanese']));
            $names = array_merge([$body['name']], [$body['russian']], $body['english'], $body['japanese'], $body['synonyms']);
        }

        // default list names
        if (!$item->getName()) {
            $names = array_merge([$body['russian']], $body['english'], $body['japanese'], $body['synonyms']);
            $item->setName($body['name']);
        }

        foreach ($names as $value) {
            if ($value != $body['name']) {
                $item->addName((new Name())->setName($value));
            }
        }

        return $item;
    }

    /**
     * Set item cover
     *
     * @param \AnimeDb\Bundle\CatalogBundle\Entity\Item $item
     * @param array $body
     *
     * @return \AnimeDb\Bundle\CatalogBundle\Entity\Item
     */
    public function setCover(Item $item, $body)
    {
        if (!empty($body['image']) && !empty($body['image']['original'])) {
            try {
                if ($path = parse_url($body['image']['original'], PHP_URL_PATH)) {
                    $target = self::NAME.'/'.$body['id'].'/cover.'.pathinfo($path, PATHINFO_EXTENSION);
                    $item->setCover($this->uploadImage($this->browser->getHost().$body['image']['original'], $target));
                }
            } catch (\Exception $e) {} // error while retrieving images is not critical
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
    public function setType(Item $item, $body)
    {
        $rename = [
            'Movie' => 'Feature',
            'Music' => 'Music video',
            'Special' => 'TV-special'
        ];
        $type = isset($rename[$body['kind']]) ? $rename[$body['kind']] : $body['kind'];
        return $item->setType($this->doctrine->getRepository('AnimeDbCatalogBundle:Type')->findOneByName($type));
    }

    /**
     * Set item genres
     *
     * @param \AnimeDb\Bundle\CatalogBundle\Entity\Item $item
     * @param array $body
     *
     * @return \AnimeDb\Bundle\CatalogBundle\Entity\Item
     */
    public function setGenres(Item $item, $body)
    {
        $rename = [
            'Martial Arts' => 'Martial arts',
            'Shoujo Ai' => 'Shoujo-ai',
            'Shounen Ai' => 'Shounen-ai',
            'Sports' => 'Sport',
            'Slice of Life' => 'Slice of life',
            'Sci-Fi' => 'Sci-fi',
            'Historical' => 'History',
            'Military' => 'War'
        ];
        $repository = $this->doctrine->getRepository('AnimeDbCatalogBundle:Genre');
        foreach ($body['genres'] as $genre) {
            $genre = isset($rename[$genre['name']]) ? $rename[$genre['name']] : $genre['name'];
            $genre = $repository->findOneByName($genre);
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
    public function setStudio(Item $item, $body)
    {
        $rename = [
            'Arms' => 'Arms Corporation',
            'Mushi Productions' => 'Mushi Production',
            'Film Roman, Inc.' => 'Film Roman',
            'Tezuka Production' => 'Tezuka Productions',
            'CoMix Wave' => 'CoMix Wave Inc.'
        ];
        $repository = $this->doctrine->getRepository('AnimeDbCatalogBundle:Studio');
        foreach ($body['studios'] as $studio) {
            $name = isset($rename[$studio['name']]) ? $rename[$studio['name']] : $studio['name'];
            $name = $studio['name'] != $studio['filtered_name'] ? [$name, $studio['filtered_name']] : $name;
            $studio = $repository->findOneByName($name);
            if ($studio instanceof Studio) {
                $item->setStudio($studio);
                break;
            }
        }
        return $item;
    }

    /**
     * Set item images
     *
     * @param \AnimeDb\Bundle\CatalogBundle\Entity\Item $item
     * @param array $body
     *
     * @return \AnimeDb\Bundle\CatalogBundle\Entity\Item
     */
    public function setImages(Item $item, $body)
    {
        $images = $this->browser->get(str_replace('#ID#', $body['id'], self::FILL_IMAGES_URL));
        foreach ($images as $image) {
            if ($path = parse_url($image['original'], PHP_URL_PATH)) {
                $target = self::NAME.'/'.$body['id'].'/'.pathinfo($path, PATHINFO_BASENAME);
                if ($path = $this->uploadImage($this->browser->getHost().$image['original'], $target)) {
                    $image = new Image();
                    $image->setSource($path);
                    $item->addImage($image);
                }
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