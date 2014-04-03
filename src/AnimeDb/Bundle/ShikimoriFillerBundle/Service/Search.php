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
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Guzzle\Http\Client;

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
     * API host
     *
     * @var string
     */
    private $host;

    /**
     * API path prefix
     *
     * @var string
     */
    private $prefix;

    /**
     * Construct
     *
     * @param string $host
     * @param string $prefix
     */
    public function __construct($host, $prefix) {
        $this->host = $host;
        $this->prefix = $prefix;
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
        $path = str_replace('#NAME#', urlencode($data['name']), $this->prefix.self::SEARH_URL);
        $path = str_replace('#LIMIT#', self::DEFAULT_LIMIT, $path);
        $client = new Client($this->host);

        /* @var $response \Guzzle\Http\Message\Response */
        $response = $client->get($path)->send();
        if ($response->isError()) {
            throw new \RuntimeException('Failed to query the server '.self::TITLE);
        }
        $body = $response->getBody(true);
        $body = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($body)) {
            throw new \RuntimeException('Invalid response from the server '.self::TITLE);
        }

        // build list
        foreach ($body as $key => $item) {
            $body[$key] = new ItemSearch(
                $item['name'],
                $this->getLinkForFill($this->host.$item['url']),
                $this->host.$item['image']['original'],
                $item['russian']
            );
        }
        return $body;
    }
}