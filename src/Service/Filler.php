<?php
/**
 * AnimeDb package.
 *
 * @author    Peter Gribanov <info@peter-gribanov.ru>
 * @copyright Copyright (c) 2011, Peter Gribanov
 * @license   http://opensource.org/licenses/GPL-3.0 GPL v3
 */
namespace AnimeDb\Bundle\ShikimoriFillerBundle\Service;

use AnimeDb\Bundle\CatalogBundle\Plugin\Fill\Filler\Filler as FillerPlugin;
use AnimeDb\Bundle\ShikimoriBrowserBundle\Service\Browser;
use Doctrine\Bundle\DoctrineBundle\Registry;
use AnimeDb\Bundle\CatalogBundle\Entity\Item;
use AnimeDb\Bundle\CatalogBundle\Entity\Name;
use AnimeDb\Bundle\CatalogBundle\Entity\Source;
use AnimeDb\Bundle\CatalogBundle\Entity\Genre;
use AnimeDb\Bundle\CatalogBundle\Entity\Studio;
use AnimeDb\Bundle\CatalogBundle\Entity\Image;
use AnimeDb\Bundle\AppBundle\Service\Downloader;
use AnimeDb\Bundle\ShikimoriFillerBundle\Form\Type\Filler as FillerForm;
use Knp\Menu\ItemInterface;
use AnimeDb\Bundle\AppBundle\Service\Downloader\Entity\EntityInterface;

class Filler extends FillerPlugin
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
     * Path to item.
     *
     * @var string
     */
    const FILL_URL = '/animes/#ID#';

    /**
     * RegExp for get item id.
     *
     * @var string
     */
    const REG_ITEM_ID = '#/animes/z?(?<id>\d+)\-#';

    /**
     * Path to item screenshots.
     *
     * @var string
     */
    const FILL_IMAGES_URL = '/animes/#ID#/screenshots';

    /**
     * World-art item url.
     *
     * @var string
     */
    const WORLD_ART_URL = 'http://www.world-art.ru/animation/animation.php?id=#ID#';

    /**
     * MyAnimeList item url.
     *
     * @var string
     */
    const MY_ANIME_LIST_URL = 'http://myanimelist.net/anime/#ID#';

    /**
     * AniDB item url.
     *
     * @var string
     */
    const ANI_DB_URL = 'http://anidb.net/perl-bin/animedb.pl?show=anime&aid=#ID#';

    /**
     * @var Browser
     */
    private $browser;

    /**
     * @var Registry
     */
    private $doctrine;

    /**
     * @var Downloader
     */
    private $downloader;

    /**
     * @var string
     */
    protected $locale;

    /**
     * @param Browser $browser
     * @param Registry $doctrine
     * @param Downloader $downloader
     * @param string $locale
     */
    public function __construct(Browser $browser, Registry $doctrine, Downloader $downloader, $locale)
    {
        $this->browser = $browser;
        $this->doctrine = $doctrine;
        $this->downloader = $downloader;
        $this->locale = $locale;
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
     * @return FillerForm
     */
    public function getForm()
    {
        return new FillerForm($this->browser->getHost());
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
     * Fill item from source.
     *
     * @param array $data
     *
     * @return Item|null
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
     * @param Item $item
     * @param array $body
     *
     * @return Item
     */
    public function setSources(Item $item, $body)
    {
        $sources = [
            'ani_db_id' => self::ANI_DB_URL,
            'world_art_id' => self::WORLD_ART_URL,
            'myanimelist_id' => self::MY_ANIME_LIST_URL,
        ];

        foreach ($sources as $key => $url) {
            if (!empty($body[$key])) {
                $source = new Source();
                $source->setUrl(str_replace('#ID#', $body[$key], $url));
                $item->addSource($source);
            }
        }

        return $item;
    }

    /**
     * @param Item $item
     * @param array $body
     *
     * @return Item
     */
    public function setNames(Item $item, $body)
    {
        $names = [];
        // set a name based on the locale
        if ($this->locale == 'ru' && $body['russian']) {
            $names = array_merge(
                [$body['name']],
                $body['english'],
                $body['japanese'],
                $body['synonyms']
            );
            $item->setName($body['russian']);
        } elseif ($this->locale == 'ja' && $body['japanese']) {
            $item->setName(array_shift($body['japanese']));
            $names = array_merge(
                [$body['name']],
                [$body['russian']],
                $body['english'],
                $body['japanese'],
                $body['synonyms']
            );
        }

        // default list names
        if (!$item->getName()) {
            $names = array_merge(
                [$body['russian']],
                $body['english'],
                $body['japanese'],
                $body['synonyms']
            );
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
     * @param Item $item
     * @param array $body
     *
     * @return Item
     */
    public function setCover(Item $item, $body)
    {
        if (!empty($body['image']) && !empty($body['image']['original'])) {
            try {
                if ($path = parse_url($body['image']['original'], PHP_URL_PATH)) {
                    $item->setCover(self::NAME.'/'.$body['id'].'/cover.'.pathinfo($path, PATHINFO_EXTENSION));
                    $this->uploadImage($this->browser->getHost().$body['image']['original'], $item);
                }
            } catch (\Exception $e) {
                // error while retrieving images is not critical
            }
        }

        return $item;
    }

    /**
     * @param Item $item
     * @param array $body
     *
     * @return Item
     */
    public function setType(Item $item, $body)
    {
        $rename = [
            'Movie' => 'Feature',
            'Music' => 'Music video',
            'Special' => 'TV-special',
        ];
        $type = ucfirst($body['kind']);
        $type = isset($rename[$type]) ? $rename[$type] : $type;

        return $item->setType(
            $this
                ->doctrine
                ->getRepository('AnimeDbCatalogBundle:Type')
                ->findOneBy(['name' => $type])
        );
    }

    /**
     * @param Item $item
     * @param array $body
     *
     * @return Item
     */
    public function setGenres(Item $item, $body)
    {
        $repository = $this->doctrine->getRepository('AnimeDbCatalogBundle:Genre');
        $rename = [
            'Martial Arts' => 'Martial arts',
            'Shoujo Ai' => 'Shoujo-ai',
            'Shounen Ai' => 'Shounen-ai',
            'Sports' => 'Sport',
            'Slice of Life' => 'Slice of life',
            'Sci-Fi' => 'Sci-fi',
            'Historical' => 'History',
            'Military' => 'War',
        ];

        foreach ($body['genres'] as $genre) {
            $genre = isset($rename[$genre['name']]) ? $rename[$genre['name']] : $genre['name'];
            $genre = $repository->findOneBy(['name' => $genre]);
            if ($genre instanceof Genre) {
                $item->addGenre($genre);
            }
        }

        return $item;
    }

    /**
     * @param Item $item
     * @param array $body
     *
     * @return Item
     */
    public function setStudio(Item $item, $body)
    {
        $repository = $this->doctrine->getRepository('AnimeDbCatalogBundle:Studio');
        $rename = [
            'Arms' => 'Arms Corporation',
            'Mushi Productions' => 'Mushi Production',
            'Film Roman, Inc.' => 'Film Roman',
            'Tezuka Production' => 'Tezuka Productions',
            'CoMix Wave' => 'CoMix Wave Inc.',
        ];

        foreach ($body['studios'] as $studio) {
            $name = isset($rename[$studio['name']]) ? $rename[$studio['name']] : $studio['name'];
            $name = $studio['name'] != $studio['filtered_name'] ? [$name, $studio['filtered_name']] : $name;
            $studio = $repository->findOneBy(['name' => $name]);
            if ($studio instanceof Studio) {
                $item->setStudio($studio);
                break;
            }
        }

        return $item;
    }

    /**
     * @param Item $item
     * @param array $body
     *
     * @return Item
     */
    public function setImages(Item $item, $body)
    {
        $images = $this->browser->get(str_replace('#ID#', $body['id'], self::FILL_IMAGES_URL));

        foreach ($images as $image) {
            if ($path = parse_url($image['original'], PHP_URL_PATH)) {
                $image = new Image();
                $image->setSource(self::NAME.'/'.$body['id'].'/'.pathinfo($path, PATHINFO_BASENAME));
                if ($this->uploadImage($this->browser->getHost().$image['original'], $image)) {
                    $item->addImage($image);
                }
            }
        }

        return $item;
    }

    /**
     * @param string $url
     * @param EntityInterface $entity
     *
     * @return bool
     */
    protected function uploadImage($url, EntityInterface $entity)
    {
        return $this->downloader->image($url, $this->downloader->getRoot().$entity->getWebPath());
    }

    /**
     * @param string $url
     *
     * @return bool
     */
    public function isSupportedUrl($url)
    {
        return strpos($url, $this->browser->getHost()) === 0;
    }
}
