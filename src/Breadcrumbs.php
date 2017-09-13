<?php

/**
 * Breadcrumbs
 *
 * Renders breadcrumbs trail for a given page.
 *
 * @package GrottoPress\WordPress\Breadcrumbs
 * @since 0.1.0
 *
 * @author GrottoPress <info@grottopress.com>
 * @author N Atta Kus Adusei
 */

declare (strict_types = 1);

namespace GrottoPress\WordPress\Breadcrumbs;

use GrottoPress\WordPress\Page\Page;
use function Stringy\create as s;

/**
 * Breadcrumbs
 *
 * @since 0.1.0
 */
class Breadcrumbs
{
    /**
     * Home label
     *
     * @since 0.1.0
     * @access protected
     *
     * @var string $home_label Label for the home link.
     */
    protected $home_label;
    
    /**
     * Delimiter
     *
     * @since 0.1.0
     * @access protected
     *
     * @var string $delimiter Links delimiter.
     */
    protected $delimiter;
    
    /**
     * Before
     *
     * @since 0.1.0
     * @access protected
     *
     * @var string $before Text to prepend to breadcrumbs.
     */
    protected $before;
    
    /**
     * After
     *
     * @since 0.1.0
     * @access   protected
     *
     * @var string $after Text to append to breadcrumbs.
     */
    protected $after;
    
    /**
     * Breadcrumb links
     *
     * @since 0.1.0
     * @access protected
     *
     * @var array $links The breadcrumb links.
     */
    protected $links;

    /**
     * Page
     *
     * @since 0.1.0
     * @access protected
     *
     * @var Page $page Page.
     */
    protected $page;
    
    /**
     * Constructor
     *
     * @param GrottoPress\WordPress\Page\Page $page Page.
     * @param array $args Breadcrumb args supplied as associative array
     *
     * @since 0.1.0
     * @access public
     */
    public function __construct(Page $page, array $args = [])
    {
        $this->page = $page;

        $this->setAttributes($args);
        $this->sanitizeAttributes();
    }
    
    /**
     * The breadcrumbs trail
     *
     * @since 0.1.0
     * @access public
     *
     * @return string The breadcrumbs trail.
     */
    public function render(): string
    {
        $this->addLinks();
        
        $trail = '<nav class="breadcrumbs" itemprop="breadcrumb" itemscope itemtype="http://schema.org/BreadcrumbList">';
        
        if (\is_rtl()) {
            $trail .= $this->renderRTL();
        } else {
            $trail .= $this->renderLTR();
        }
        
        $trail .= '</nav>';
        
        return $trail;
    }
    
    /**
     * The breadcrumbs trail - RTL
     *
     * @since 0.1.0
     * @access protected
     *
     * @return string The breadcrumbs trail for right-to-left languages.
     */
    protected function renderRTL(): string
    {
        $trail = '';
        
        if ($this->after) {
            $trail .= '<span class="after">'.$this->after.'</span> ';
        }
        
        $trail .= \join(
            ' <span class="sep delimiter">'.$this->delimiter.'</span> ',
            \array_reverse($this->links)
        );
        
        if ($this->before) {
            $trail .= ' <span class="before">'.$this->before.'</span>';
        }
        
        return $trail;
    }
    
    /**
     * The breadcrumbs trail - LTR
     *
     * @since 0.1.0
     * @access protected
     *
     * @return string The breadcrumbs trail for left-to-right languages.
     */
    protected function renderLTR(): string
    {
        $trail = '';
        
        if ($this->before) {
            $trail .= '<span class="before">'.$this->before.'</span> ';
        }
        
        $trail .= \join(
            ' <span class="sep delimiter">'.$this->delimiter.'</span> ',
            $this->links
        );
        
        if ($this->after) {
            $trail .= ' <span class="after">'.$this->after.'</span>';
        }
        
        return $trail;
    }
    
    /**
     * Add breadcrumb links
     *
     * @since 0.1.0
     * @access protected
     */
    protected function addLinks()
    {
        if (!$this->page->is('front_page')) {
            $this->addHomeLink();
        }

        $this_page = $this->page->type();
        
        foreach ($this_page as $page) {
            $add_links = (string) s('add_'.$page.'_links')->camelize();
            
            if (\is_callable([$this, $add_links])) {
                $this->$add_links();

                break;
            }
        }
        
        if ($this->page->is('paged') && !$this->page->is('404')) {
            $this->addPageNumberLink();
        }

        /**
         * @filter grotto_breadcrumbs_links
         *
         * @param array $this->links Breadcrub links for current page.
         *
         * @since 0.1.0
         */
        $this->links = (array) \apply_filters(
            'grotto_breadcrumbs_links',
            $this->links
        );
    }

    /**
     * Front page breadcrumb links
     *
     * @since 0.1.0
     * @access protected
     */
    protected function addFrontPageLinks()
    {
        $this->links[] = $this->currentLink($this->home_label, \home_url('/'));
    }
    
    /**
     * Home breadcrumb links
     *
     * @since 0.1.0
     * @access protected
     */
    protected function addHomeLinks()
    {
        $home = \get_option('page_for_posts');
        $title = \get_the_title($home);
        $url = \get_permalink($home);
        
        $this->links[] = $this->currentLink($title, $url);
    }
    
    /**
     * Category archive breadcrumb links
     *
     * @since 0.1.0
     * @access protected
     */
    protected function addCategoryLinks()
    {
        $cat_id = \absint(\get_query_var('cat'));
        $cat = \get_category($cat_id);
        $cat_parent_id = \absint($cat->parent);
        
        $cat_links = [];
        
        if ($cat_parent_id) {
            while ($cat_parent_id) {
                $cat_parent = \get_category($cat_parent_id);
                $cat_links[] = $this->makeLink(
                    $cat_parent->name,
                    \get_category_link(\absint($cat_parent->term_id))
                );
                $cat_parent_id = \absint($cat_parent->parent);
            }
            
            $this->links = \array_merge(
                $this->links,
                \array_reverse($cat_links)
            );
        }
        
        $this->links[] = $this->currentLink(
            \single_cat_title('', false),
            \get_category_link($cat_id)
        );
    }
    
    /**
     * Day archive breadcrumb links
     *
     * @since 0.1.0
     * @access protected
     */
    protected function addDayLinks()
    {
        $year = \get_query_var('year');
        $month = \get_query_var('monthnum');
        $day = \get_query_var('day');

        $timestamp = \strtotime($year.'-'.$month.'-'.$day);
        
        $this->links[] = $this->makeLink(
            \date('Y', $timestamp),
            \get_year_link($year)
        );
        $this->links[] = $this->makeLink(
            \date('F Y', $timestamp),
            \get_month_link($year, $month)
        );
        $this->links[] = $this->currentLink(
            \date(\get_option('date_format'), $timestamp),
            \get_day_link($year, $month, $day)
        );
    }
    
    /**
     * Month archive breadcrumb links
     * \
     * @since 0.1.0
     * @access protected
     */
    protected function addMonthLinks()
    {
        $year = \get_query_var('year');
        $month = \get_query_var('monthnum');

        $timestamp = \strtotime($year.'-'.$month);
        
        $this->links[] = $this->makeLink(
            \date('Y', $timestamp),
            \get_year_link($year)
        );

        $this->links[] = $this->currentLink(
            \date('F Y', $timestamp),
            \get_month_link($year, $month)
        );
    }
    
    /**
     * Year archive breadcrumb links
     *
     * @since 0.1.0
     * @access protected
     */
    protected function addYearLinks()
    {
        $year = \get_query_var('year');
        
        $this->links[] = $this->currentLink($year, \get_year_link($year));
    }
    
    /**
     * Search archive breadcrumb links
     *
     * @since 0.1.0
     * @access protected
     */
    protected function addSearchLinks()
    {
        $this->links[] = $this->currentLink(
            \get_search_query(),
            \get_search_link(\get_search_query())
        );
    }
    
    /**
     * Tag archive breadcrumb links
     *
     * @since 0.1.0
     * @access protected
     */
    protected function addTagLinks()
    {
        $tag_id = \get_query_var('tag_id');
        $tag_label = \single_tag_title('', false);
        
        $this->links[] = $this->currentLink($tag_label, \get_tag_link($tag_id));
    }
    
    /**
     * Author archive breadcrumb links
     *
     * @since 0.1.0
     * @access protected
     */
    protected function addAuthorLinks()
    {
        $author_id = \get_query_var('author');
        $author_name = \get_the_author_meta('display_name', $author_id);
        
        $this->links[] = $this->currentLink(
            $author_name,
            \get_author_posts_url($author_id)
        );
    }
    
    /**
     * 404 breadcrumb links
     *
     * @since 0.1.0
     * @access protected
     */
    protected function add404links()
    {
        $this->links[] = $this->currentLink(
            \esc_html__('Error 404', 'jentil')
        );
    }
    
    /**
     * Post type archive breadcrumb links
     *
     * @since 0.1.0
     * @access protected
     */
    protected function addPostTypeArchiveLinks()
    {
        $post_type = \get_query_var('post_type');
        $post_type_link = \get_post_type_archive_link($post_type);
        $post_type_label = \post_type_archive_title('', false);
        
        $this->links[] = $this->currentLink(
            $post_type_label,
            \get_post_type_archive_link($post_type)
        );
    }
    
    /**
     * Taxonomy breadcrumb links
     *
     * @since 0.1.0
     * @access protected
     */
    protected function addTaxLinks()
    {
        $tax_slug = \get_query_var('taxonomy');
        $term_slug = \get_query_var('term');
        $term = \get_term_by('slug', $term_slug, $tax_slug);
        $term_id = \absint($term->term_id);
        $term_parent_id = \absint($term->parent);
        
        $tax_links = [];
        
        if ($term_parent_id) {
            while ($term_parent_id) {
                $term_parent = \get_term_by(
                    'id',
                    $term_parent_id,
                    $term->taxonomy
                );
                $tax_links[] = $this->makeLink(
                    $term_parent->name,
                    \get_term_link(\absint($term_parent->term_id))
                );
                $term_parent_id = \absint($term_parent->parent);
            }
            
            $this->links = \array_merge(
                $this->links,
                \array_reverse($tax_links)
            );
        }
        
        $this->links[] = $this->currentLink(
            \single_term_title('', false),
            \get_term_link($term_id, $tax_slug)
        );
    }
    
    /**
     * Single page/post/attachment breadcrumb links
     *
     * @since 0.1.0
     * @access protected
     */
    protected function addSingularLinks()
    {
        global $post;

        $use_post = $post->post_parent ? \get_post($post->post_parent) : $post;

        if (!\is_post_type_hierarchical(\get_post_type($use_post))) {
            $taxonomies = $this->getTaxonomies($use_post->post_type);
            
            $taxonomy_selected = '';
            $term_selected = 0;
            
            if ($taxonomies) {
                foreach ($taxonomies as $taxonomy => $terms) {
                    $taxonomy_selected = $taxonomy;
                    $post_terms = \wp_get_post_terms(
                        $use_post->ID,
                        $taxonomy_selected
                    );
                    
                    /** Get the first term of the first taxonomy */
                    if ($post_terms && !\is_wp_error($post_terms)) {
                        foreach ($post_terms as $term_object) {
                            $term_selected = \absint($term_object->term_id);
                            break 2;
                        }
                    }
                }
                
                $term_id = $term_selected;
                $single_links = [];
                
                while ($term_id) {
                    $term = \get_term_by('id', $term_id, $taxonomy_selected);
                    $single_links[] = $this->makeLink(
                        $term->name,
                        \get_term_link(\absint($term->term_id), $term->taxonomy)
                    );
                    $term_id = \absint($term->parent);
                }
                
                $this->links = \array_merge(
                    $this->links,
                    \array_reverse($single_links)
                );
            } else /*if (!$post->post_parent)*/ { // Add post type archive link
                if ('post' != $post->post_type
                    || ($page_for_posts = \get_option('page_for_posts'))
                    // NB: 'post' archive is the same as frontpage unless page_for_posts is set
                ) {
                    $post_type_object = \get_post_type_object($post->post_type);

                    $label = (
                        'post' == $post->post_type && $page_for_posts
                        ? \get_the_title($page_for_posts)
                        : $post_type_object->labels->name
                    );

                    if (($post_type_link =
                        \get_post_type_archive_link($post->post_type))
                    ) {
                        $this->links[] = $this->makeLink(
                            $label,
                            $post_type_link
                        );
                    }
                }
            }
        }
        
        if ($post->post_parent) {
            $parent_id = $post->post_parent;
            
            $single_links = [];
            
            while ($parent_id) {
                $parent = \get_post($parent_id);
                $single_links[] = $this->makeLink(
                    \get_the_title($parent->ID),
                    \get_permalink($parent->ID)
                );
                $parent_id = $parent->post_parent;
            }
            
            $this->links = \array_merge(
                $this->links,
                \array_reverse($single_links)
            );
        }
        
        $this->links[] = $this->currentLink(
            \get_the_title($post->ID),
            \get_permalink($post->ID)
        );
    }
    
    /**
     * Get all hierarchical taxonomies.
     *
     * @param string $post_type Post type.
     *
     * @since 0.1.0
     * @access protected
     *
     * @return array Hierarchical taxonomies and their respective terms.
     */
    protected function getTaxonomies(string $post_type): array
    {
        $taxonomies = [];

        $taxes = \get_object_taxonomies($post_type, 'objects');
        
        if (!$taxes) {
            return $taxonomies;
        }

        foreach ($taxes as $tax_slug => $tax_object) {
            if (!\is_taxonomy_hierarchical($tax_slug)) {
                continue;
            }
            
            $terms = \get_terms($tax_object->name, ['hide_empty' => false]);
            
            if (!$terms || \is_wp_error($terms)) {
                continue;
            }
            
            foreach ($terms as $term_object) {
                $taxonomies[$tax_slug][] = \absint($term_object->term_id);
            }
        }
        
        return $taxonomies;
    }
    
    /**
     * Home link
     *
     * @since 0.1.0
     * @access protected
     */
    protected function addHomeLink()
    {
        $this->links[] = $this->makeLink($this->home_label, \home_url('/'));
    }
    
    /**
     * Page number link
     *
     * @since 0.1.0
     * @access protected
     */
    protected function addPageNumberLink()
    {
        $this->links[] = $this->makeLink(\sprintf(
            \esc_html__('Page %d'),
            $this->page->number()
        ));
    }
    
    /**
     * Get current page link
     *
     * @param string $url URL
     * @param string $title Link title.
     *
     * @since 0.1.0
     * @access protected
     *
     * @return string Current page link.
     */
    protected function currentLink(
        string $title = '',
        string $url = ''
    ): string {
        if ($this->page->is('paged')) {
            return $this->makeLink($title, $url);
        }

        return $this->makeLink($title);
    }
    
    /**
     * Make a link
     *
     * @param string $url URL
     * @param string $title Link title.
     *
     * @since 0.1.0
     * @access protected
     */
    protected function makeLink(string $title, string $url = ''): string
    {
        $link = '';
        
        if ($url) {
            $link .= '<a href="'.\esc_url($url).'" itemprop="url">';
        }
        
        $link .= '<span itemprop="itemListElement">'.\sanitize_text_field($title).'</span>';
        
        if ($url) {
            $link .= '</a>';
        }
        
        return $link;
    }

    /**
     * Set attributes
     *
     * @param array $arg Arguments supplied to this object.
     *
     * @since 0.1.0
     * @access protected
     */
    protected function setAttributes(array $args)
    {
        if (!($vars = \get_object_vars($this))) {
            return;
        }

        unset($vars['links']);
        unset($vars['page']);

        foreach ($vars as $key => $value) {
            $this->$key = $args[$key] ?? '';
        }
    }

    /**
     * Sanitize attributes
     *
     * @since 0.1.0
     * @access protected
     */
    protected function sanitizeAttributes()
    {
        $this->home_label = $this->home_label ? \sanitize_text_field($this->home_label) : \esc_html__('Home', 'jentil');
        $this->delimiter = $this->delimiter
            ? \esc_attr($this->delimiter) : $this->defaultDelimiter();
        $this->after = \sanitize_text_field($this->after);
        $this->before = \sanitize_text_field($this->before);
        
        $this->links = [];
    }
    
    /**
     * Default Delimiter
     *
     * @since 0.1.0
     * @access protected
     *
     * @return string Default delimiter.
     */
    protected function defaultDelimiter()
    {
        return (\is_rtl() ? '/' : '\\');
    }
}
