<?php
/**
 * AnimeDb package.
 *
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

class Refiller
{
    /**
     * @var EventDispatcherInterface
     */
    protected $dispatcher;

    /**
     * @var RefillerService
     */
    protected $refiller;

    /**
     * @var Filler
     */
    protected $filler;

    /**
     * @var Browser
     */
    private $browser;

    /**
     * @param EventDispatcherInterface $dispatcher
     * @param RefillerService $refiller
     * @param Filler $filler
     * @param Browser $browser
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
     * @param AddNewItem $event
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
