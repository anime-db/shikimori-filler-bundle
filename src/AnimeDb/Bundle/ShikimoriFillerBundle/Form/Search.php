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
     * (non-PHPdoc)
     * @see \Symfony\Component\Form\AbstractType::buildForm()
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        parent::buildForm($builder, $options);
    }
}