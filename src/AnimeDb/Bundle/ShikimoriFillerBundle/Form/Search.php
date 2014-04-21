<?php
/**
 * AnimeDb package
 *
 * @package   AnimeDb
 * @author    Peter Gribanov <info@peter-gribanov.ru>
 * @copyright Copyright (c) 2011, Peter Gribanov
 * @license   http://opensource.org/licenses/GPL-3.0 GPL v3
 */

namespace AnimeDb\Bundle\ShikimoriFillerBundle\Form;

use AnimeDb\Bundle\CatalogBundle\Form\Plugin\Search as SearchPlugin;
use AnimeDb\Bundle\ShikimoriBrowserBundle\Service\Browser;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * Search from
 * 
 * @link http://shikimori.org/
 * @package AnimeDb\Bundle\ShikimoriFillerBundle\Form
 * @author  Peter Gribanov <info@peter-gribanov.ru>
 */
class Search extends SearchPlugin
{

    /**
     * Browser
     *
     * @var \AnimeDb\Bundle\ShikimoriBrowserBundle\Service\Browser
     */
    private $browser;

    /**
     * Locale
     *
     * @var string
     */
    protected $locale;

    /**
     * Types
     *
     * @var array
     */
    protected $types = [
        'Movie',
        'Music',
        'ONA',
        'OVA',
        'Special',
        'TV'
    ];

    /**
     * Construct
     *
     * @param \AnimeDb\Bundle\ShikimoriBrowserBundle\Service\Browser $browser
     * @param string $locale
     */
    public function __construct(Browser $browser, $locale) {
        $this->browser = $browser;
        $this->locale = $locale;
    }

    /**
     * (non-PHPdoc)
     * @see \Symfony\Component\Form\AbstractType::buildForm()
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        parent::buildForm($builder, $options);

        // get genres
        $list = $this->browser->get('/genres');
        $genres = [];
        while ($genre = array_shift($list)) {
            if ($this->locale == 'ru') {
                $genres[$genre['id']] = $genre['russian'];
            } else {
                $genres[$genre['id']] = $genre['name'];
            }
        }

        $builder
            ->add('genre', 'choice', [
                'choices'  => $genres,
                'required' => false
            ])
            ->add('type', 'choice', [
                'choices'  => array_combine($this->types, $this->types),
                'required' => false,
            ]);
    }
}