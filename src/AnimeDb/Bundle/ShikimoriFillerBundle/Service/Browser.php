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

use Symfony\Component\HttpFoundation\Request;
use Guzzle\Http\Client;

/**
 * Browser
 *
 * @link http://shikimori.org/
 * @package AnimeDb\Bundle\ShikimoriFillerBundle\Service
 * @author  Peter Gribanov <info@peter-gribanov.ru>
 */
class Browser
{
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
     * HTTP client
     *
     * @var \Guzzle\Http\Client
     */
    private $client;

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
     * Get HTTP client
     *
     * @param \Guzzle\Http\Client
     */
    protected function getClient()
    {
        if (!($this->client instanceof Client)) {
            $this->client = new Client($this->host);
        }
        return $this->client;
    }

    /**
     * Get data from path
     *
     * @param string $path
     *
     * @return mixed
     */
    public function get($path) {
        /* @var $response \Guzzle\Http\Message\Response */
        $response = $this->getClient()->get($this->prefix.$path)->send();
        if ($response->isError()) {
            throw new \RuntimeException('Failed to query the server '.$this->host);
        }
        $body = @json_decode($response->getBody(true), true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($body)) {
            throw new \RuntimeException('Invalid response from the server '.$this->host);
        }
        return $body;
    }
}