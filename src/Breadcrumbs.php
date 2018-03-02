<?php
declare (strict_types = 1);

namespace GrottoPress\WordPress\Breadcrumbs;

use GrottoPress\WordPress\Page\Page;
use GrottoPress\Getter\GetterTrait;

class Breadcrumbs
{
    use GetterTrait;

    /**
     * @var string
     */
    protected $home_label;

    /**
     * @var string
     */
    protected $delimiter;

    /**
     * @var string $before Text to prepend to breadcrumbs.
     */
    protected $before;

    /**
     * @var string $after Text to append to breadcrumbs.
     */
    protected $after;

    /**
     * @var array
     */
    protected $links;

    /**
     * @var Page
     */
    private $page;

    public function __construct(Page $page, array $args = [])
    {
        $this->page = $page;

        $this->setAttributes($args);
        $this->sanitizeAttributes();

        $this->collectLinks();
    }

    protected function getLinks()
    {
        return $this->links;
    }

    public function render(): string
    {
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
     * The breadcrumbs trail for right-to-left languages.
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
     * The breadcrumbs trail for left-to-right languages.
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

    protected function collectLinks()
    {
        if (!$this->page->is('front_page')) {
            $this->addHomeLink();
        }

        $this_page = $this->page->type();

        foreach ($this_page as $page) {
            $add_links = 'add_'.$page.'_links';

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
         * @var array $this->links Breadcrub links for current page.
         */
        $this->links = (array)\apply_filters(
            'grotto_breadcrumbs_links',
            $this->links,
            $this_page
        );
    }

    /**
     * Called if $page === 'front_page'
     */
    protected function add_front_page_links()
    {
        $this->links[] = $this->currentLink($this->home_label, \home_url('/'));
    }

    /**
     * Called if $page === 'home'
     */
    protected function add_home_links()
    {
        if ($this->page->is('front_page')) {
            $this->add_front_page_links();
        } else {
            $home = \get_option('page_for_posts');
            $title = \get_the_title($home);
            $url = (\get_permalink($home) ?: '');

            $this->links[] = $this->currentLink($title, $url);
        }
    }

    /**
     * Called if $page === 'category'
     */
    protected function add_category_links()
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
     * Called if $page === 'date'
     */
    protected function add_date_links()
    {
        $year = \get_query_var('year', (int)\date('Y'));
        $month = \get_query_var('monthnum', 1);
        $day = \get_query_var('day', 1);

        $timestamp = \strtotime("{$year}-{$month}-{$day}");

        $this->links[] = $this->makeLink("{$year}", \get_year_link($year));

        if ($this->page->is('month') || $this->page->is('day')) {
            $this->links[] = $this->makeLink(
                \date('F', $timestamp),
                \get_month_link($year, $month)
            );
        }

        if ($this->page->is('day')) {
            $this->links[] = $this->currentLink(
                \date('d', $timestamp),
                \get_day_link($year, $month, $day)
            );
        }
    }

    /**
     * Called if $page === 'search'
     */
    protected function add_search_links()
    {
        $this->links[] = $this->currentLink(
            '&ldquo;'.\get_search_query().'&rdquo;',
            \get_search_link(\get_search_query())
        );
    }

    /**
     * Called if $page === 'tag'
     */
    protected function add_tag_links()
    {
        $tag_id = \get_query_var('tag_id');
        $tag_label = (\single_tag_title('', false) ?: '');

        $this->links[] = $this->currentLink($tag_label, \get_tag_link($tag_id));
    }

    /**
     * Called if $page === 'author'
     */
    protected function add_author_links()
    {
        $author_id = \get_query_var('author');
        $author_name = \get_the_author_meta('display_name', $author_id);

        $this->links[] = $this->currentLink(
            $author_name,
            \get_author_posts_url($author_id)
        );
    }

    /**
     * Called if $page === '404'
     */
    protected function add_404_links()
    {
        $this->links[] = $this->currentLink(
            \esc_html__('Error 404')
        );
    }

    /**
     * Called if $page === 'post_type_archive'
     */
    protected function add_post_type_archive_links()
    {
        $post_type = \get_query_var('post_type');
        $post_type_link = (\get_post_type_archive_link($post_type) ?: '');
        $post_type_label = \post_type_archive_title('', false);

        $this->links[] = $this->currentLink($post_type_label, $post_type_link);
    }

    /**
     * Called if $page === 'tax'
     */
    protected function add_tax_links()
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
                    $this->getTermLink(
                        \absint($term_parent->term_id),
                        $term->taxonomy
                    )
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
            $this->getTermLink($term_id, $tax_slug)
        );
    }

    /**
     * Called if $page === 'singular'
     */
    protected function add_singular_links()
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
                        $this->getTermLink(
                            \absint($term->term_id),
                            $term->taxonomy
                        )
                    );
                    $term_id = \absint($term->parent);
                }

                $this->links = \array_merge(
                    $this->links,
                    \array_reverse($single_links)
                );
            } else /*if (!$post->post_parent)*/ { // Add post type archive link
                if ('post' !== $post->post_type
                    || ($page_for_posts = \get_option('page_for_posts'))
                    // NB: 'post' archive is the same as frontpage unless page_for_posts is set
                ) {
                    $post_type_object = \get_post_type_object($post->post_type);

                    $label = (
                        'post' === $post->post_type && $page_for_posts
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
                    (\get_permalink($parent->ID) ?: '')
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
            (\get_permalink($post->ID) ?: '')
        );
    }

    /**
     * Get all hierarchical taxonomies
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

    protected function addHomeLink()
    {
        $this->links[] = $this->makeLink($this->home_label, \home_url('/'));
    }

    protected function addPageNumberLink()
    {
        $this->links[] = $this->makeLink(\sprintf(
            \esc_html__('Page %d'),
            $this->page->number()
        ));
    }

    protected function currentLink(
        string $title = '',
        string $url = ''
    ): string {
        if ($this->page->is('paged')) {
            return $this->makeLink($title, $url);
        }

        return $this->makeLink($title);
    }

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

    private function setAttributes(array $args)
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

    private function sanitizeAttributes()
    {
        $this->home_label = $this->home_label
            ? \sanitize_text_field($this->home_label)
            : \esc_html__('Home');
        $this->delimiter = $this->delimiter
            ? \esc_attr($this->delimiter) : $this->defaultDelimiter();
        $this->after = \sanitize_text_field($this->after);
        $this->before = \sanitize_text_field($this->before);

        $this->links = [];
    }

    private function defaultDelimiter(): string
    {
        return (\is_rtl() ? '/' : '\\');
    }

    protected function getTermLink(int $id, string $tax = ''): string
    {
        $str = \get_term_link($id, $tax);

        if (\is_wp_error($str)) {
            return '';
        }

        return $str;
    }
}
