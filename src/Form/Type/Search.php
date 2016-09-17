<?php
/**
 * AnimeDb package.
 *
 * @author    Peter Gribanov <info@peter-gribanov.ru>
 * @copyright Copyright (c) 2011, Peter Gribanov
 * @license   http://opensource.org/licenses/GPL-3.0 GPL v3
 */
namespace AnimeDb\Bundle\ShikimoriFillerBundle\Form\Type;

use AnimeDb\Bundle\CatalogBundle\Form\Type\Plugin\Search as SearchPlugin;
use AnimeDb\Bundle\ShikimoriBrowserBundle\Service\Browser;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

class Search extends SearchPlugin
{
    /**
     * @var Browser
     */
    private $browser;

    /**
     * @var string
     */
    protected $locale;

    /**
     * @var array
     */
    protected $types = [
        'Movie',
        'Music',
        'ONA',
        'OVA',
        'Special',
        'TV',
    ];

    /**
     * @param Browser $browser
     * @param string $locale
     */
    public function __construct(Browser $browser, $locale)
    {
        $this->browser = $browser;
        $this->locale = $locale;
    }

    /**
     * @param FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        parent::buildForm($builder, $options);
        $builder->get('name')->setRequired(false);

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
                'choices' => $genres,
                'required' => false,
            ])
            ->add('type', 'choice', [
                'choices' => array_combine($this->types, $this->types),
                'required' => false,
            ])
            ->add('season', 'text', [
                'required' => false,
                'label' => 'Year of the premier',
                'help' => 'You can select the period of the years indicated by a dash: 2002-2004',
            ]);
    }

    /**
     * @param OptionsResolverInterface $resolver
     */
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults([
            'csrf_protection' => false,
        ]);
    }
}
