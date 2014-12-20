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
use AnimeDb\Bundle\ShikimoriBrowserBundle\Service\Browser;
use AnimeDb\Bundle\CatalogBundle\Event\Storage\AddNewItem;
use AnimeDb\Bundle\CatalogBundle\Event\Storage\StoreEvents;

/**
 * Refiller for new item
 *
 * @package AnimeDb\Bundle\ShikimoriFillerBundle\Event\Listener
 * @author  Peter Gribanov <info@peter-gribanov.ru>
 */
class Refiller
{
    /**
     * Dispatcher
     *
     * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
     */
    protected $dispatcher;

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
     * Browser
     *
     * @var \AnimeDb\Bundle\ShikimoriBrowserBundle\Service\Browser
     */
    private $browser;

    /**
     * Construct
     *
     * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $dispatcher
     * @param \AnimeDb\Bundle\ShikimoriFillerBundle\Service\Refiller $refiller
     * @param \AnimeDb\Bundle\ShikimoriFillerBundle\Service\Filler $filler
     * @param \AnimeDb\Bundle\ShikimoriBrowserBundle\Service\Browser $browser
     */
    public function __construct(
        EventDispatcherInterface $dispatcher,
        RefillerService $refiller,
        Filler $filler,
        Browser $browser
    ) {
        $this->dispatcher = $dispatcher;
        $this->refiller = $refiller;
        $this->filler = $filler;
        $this->browser = $browser;
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

            // get data
            preg_match(Filler::REG_ITEM_ID, $url, $match);
            $path = str_replace('#ID#', $match['id'], Filler::FILL_URL);
            $body = $this->browser->get($path);

            // fill item
            if (!$item->getDateEnd() && $body['released_on']) {
                $item->setDateEnd(new \DateTime($body['released_on']));
            }
            if (!$item->getDatePremiere()) {
                $item->setDatePremiere(new \DateTime($body['aired_on']));
            }
            if (!$item->getDuration()) {
                $item->setDuration($body['duration']);
            }
            if (!$item->getEpisodesNumber()) {
                $ep_num = $body['episodes_aired'] ? $body['episodes_aired'] : $body['episodes'];
                $item->setEpisodesNumber($ep_num.($body['ongoing'] ? '+' : ''));
            }
            if (!$item->getSummary()) {
                $item->setSummary($body['description']);
            }

            // set complex data
            if (!$item->getStudio()) {
                $this->filler->setStudio($item, $body);
            }
            if (!$item->getType()) {
                $this->filler->setType($item, $body);
            }
            if (!$item->getCover()) {
                $this->filler->setCover($item, $body);
            }
            $this->filler->setGenres($item, $body);
            $this->filler->setSources($item, $body);
            $this->filler->setNames($item, $body);

            $event->addFiller($this->filler);
            // resend event
            $this->dispatcher->dispatch(StoreEvents::ADD_NEW_ITEM, clone $event);
            $event->stopPropagation();
        }
    }
}
