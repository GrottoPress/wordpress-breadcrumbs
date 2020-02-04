<?php
declare (strict_types = 1);

namespace GrottoPress\WordPress;

use GrottoPress\Getter\GetterTrait;
use WP_Post;

class Breadcrumbs
{
    use GetterTrait;

    /**
     * @var string
     */
    protected $homeLabel;

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
        $trail = '<nav class="breadcrumbs">';

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
         * @filter grotto_wp_breadcrumbs_links
         *
         * @var array $this->links Breadcrub links for current page.
         */
        $this->links = (array)\apply_filters(
            'grotto_wp_breadcrumbs_links',
            $this->links,
            $this_page
        );
    }

    /**
     * Called if $page === 'front_page'
     */
    protected function add_front_page_links()
    {
        $this->links[] = $this->currentLink($this->homeLabel, \home_url('/'));
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
            $cat->name,
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

        $year_args = ["{$year}", \get_year_link($year)];
        $month_args = [
            \date('M Y', $timestamp),
            \get_month_link($year, $month)
        ];
        $day_args = [
            \date('d M Y', $timestamp),
            \get_day_link($year, $month, $day)
        ];

        if ($this->page->is('year')) {
            $this->links[] = $this->currentLink(...$year_args);
        } else {
            $this->links[] = $this->makeLink(...$year_args);
        }

        if ($this->page->is('month')) {
            $this->links[] = $this->currentLink(...$month_args);
        }

        if ($this->page->is('day')) {
            $this->links[] = $this->makeLink(...$month_args);
            $this->links[] = $this->currentLink(...$day_args);
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
            \esc_html__('Error 404', 'grotto-wp-breadcrumbs')
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
            $term->name,
            $this->getTermLink($term_id, $tax_slug)
        );
    }

    /**
     * Called if $page === 'singular'
     */
    protected function add_singular_links()
    {
        if (($post = \get_post())->post_parent) {
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
        } elseif (!\is_post_type_hierarchical($post->post_type)) {
            if ($term = $this->getFirstTerm($post)) {
                $single_links = [];
                $term_id = \absint($term->term_id);

                while ($term_id) {
                    $term = \get_term_by('id', $term_id, $term->taxonomy);
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
            } elseif ('post' !== $post->post_type
                || ($page_for_posts = \get_option('page_for_posts'))
                // NB: 'post' archive is the same as frontpage unless page_for_posts is set
            ) { // Add post type archive link
                $post_type_object = \get_post_type_object($post->post_type);

                $label = (
                    'post' === $post->post_type && $page_for_posts
                    ? \get_the_title($page_for_posts)
                    : $post_type_object->labels->name
                );

                if (($post_type_link =
                    \get_post_type_archive_link($post->post_type))
                ) {
                    $this->links[] = $this->makeLink($label, $post_type_link);
                }
            }
        }

        $this->links[] = $this->currentLink(
            \get_the_title($post->ID),
            (\get_permalink($post->ID) ?: '')
        );
    }

    /**
     * Get post's first term
     */
    protected function getFirstTerm(WP_Post $post)
    {
        $taxonomies = $this->getTaxonomies($post->post_type);

        foreach ($taxonomies as $taxonomy) {
            $post_terms = \get_the_terms($post, $taxonomy);

            if (!$post_terms || \is_wp_error($post_terms)) {
                continue;
            }

            return $post_terms[0];
        }
    }

    /**
     * Get all hierarchical taxonomies
     */
    protected function getTaxonomies(string $post_type): array
    {
        $return = [];

        $taxes = \get_object_taxonomies($post_type, 'objects');

        foreach ($taxes as $tax_slug => $tax_object) {
            if (\is_taxonomy_hierarchical($tax_slug)) {
                $return[] = $tax_slug;
            }
        }

        return $return;
    }

    protected function addHomeLink()
    {
        $this->links[] = $this->makeLink($this->homeLabel, \home_url('/'));
    }

    protected function addPageNumberLink()
    {
        $this->links[] = $this->makeLink(\sprintf(
            \esc_html__('Page %d', 'grotto-wp-breadcrumbs'),
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
            $link .= '<a href="'.\esc_url($url).'">';
        }

        $link .= '<span class="item">'.\sanitize_text_field($title).'</span>';

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
            $this->$key = $args[$key] ?? null;
        }
    }

    private function sanitizeAttributes()
    {
        $this->homeLabel = $this->homeLabel ?
            \sanitize_text_field($this->homeLabel) :
            \esc_html__('Home', 'grotto-wp-breadcrumbs');
        $this->delimiter = $this->delimiter ?
            \esc_attr($this->delimiter) :
            $this->defaultDelimiter();
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
