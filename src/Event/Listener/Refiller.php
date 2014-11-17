<?php
/**
 * AnimeDb package
 *
 * @package   AnimeDb
 * @author    Peter Gribanov <info@peter-gribanov.ru>
 * @copyright Copyright (c) 2011, Peter Gribanov
 * @license   http://opensource.org/licenses/GPL-3.0 GPL v3
 */

namespace AnimeDb\Bundle\ShikimoriFillerBundle\Event\Listener;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use AnimeDb\Bundle\ShikimoriFillerBundle\Service\Refiller as RefillerService;
use AnimeDb\Bundle\ShikimoriFillerBundle\Service\Filler;
use AnimeDb\Bundle\CatalogBundle\Event\Storage\AddNewItem;
use AnimeDb\Bundle\CatalogBundle\Event\Storage\StoreEvents;
use AnimeDb\Bundle\CatalogBundle\Entity\Name;

/**
 * Refiller for new item
 *
 * @package AnimeDb\Bundle\ShikimoriFillerBundle\Event\Listener
 * @author  Peter Gribanov <info@peter-gribanov.ru>
 */
class Refiller
{
    /**
     * Refiller
     *
     * @var \AnimeDb\Bundle\ShikimoriFillerBundle\Service\Refiller
     */
    protected $refiller;

    /**
     * Filler
     *
     * @var \AnimeDb\Bundle\ShikimoriFillerBundle\Service\Filler
     */
    protected $filler;

    /**
     * Dispatcher
     *
     * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
     */
    protected $dispatcher;

    /**
     * Construct
     *
     * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $dispatcher
     * @param \AnimeDb\Bundle\ShikimoriFillerBundle\Service\Refiller $refiller
     * @param \AnimeDb\Bundle\ShikimoriFillerBundle\Service\Filler $filler
     */
    public function __construct(EventDispatcherInterface $dispatcher, RefillerService $refiller, Filler $filler)
    {
        $this->dispatcher = $dispatcher;
        $this->refiller = $refiller;
        $this->filler = $filler;
    }

    /**
     * On add new item
     *
     * @param \AnimeDb\Bundle\CatalogBundle\Event\Storage\AddNewItem $event
     */
    public function onAddNewItem(AddNewItem $event)
    {
        $item = $event->getItem();
        if (!$event->getFillers()->contains($this->filler) && ($url = $this->refiller->getSourceForFill($item))) {
            $new_item = $this->filler->fill(['url' => $url]);

            // fill item
            if (!$item->getDateEnd()) {
                $item->setDateEnd($new_item->getDateEnd());
            }
            if (!$item->getDatePremiere()) {
                $item->setDatePremiere($new_item->getDatePremiere());
            }
            if (!$item->getDuration()) {
                $item->setDuration($new_item->getDuration());
            }
            if (!$item->getEpisodesNumber()) {
                $item->setEpisodesNumber($new_item->getEpisodesNumber());
            }
            if (!$item->getSummary()) {
                $item->setSummary($new_item->getSummary());
            }
            if (!$item->getStudio()) {
                $item->setStudio($new_item->getStudio());
            }
            foreach ($new_item->getGenres() as $genre) {
                $item->addGenre($genre);
            }
            // set main name in top of names list
            $new_names = $new_item->getNames()->toArray();
            array_unshift($new_names, (new Name())->setName($new_item->getName()));
            foreach ($new_names as $new_name) {
                $item->addName($new_name);
            }
            foreach ($new_item->getSources() as $source) {
                $item->addSource($source);
            }

            $event->addFiller($this->filler);
            // resend event
            $this->dispatcher->dispatch(StoreEvents::ADD_NEW_ITEM, clone $event);
            $event->stopPropagation();
        }
    }
}
