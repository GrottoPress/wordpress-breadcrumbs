<?php
declare (strict_types = 1);

namespace GrottoPress\WordPress\Breadcrumbs;

use Codeception\TestCase\WPTestCase;
use GrottoPress\WordPress\Page\Page;
use DOMDocument;

class BreadcrumbsTest extends WPTestCase
{
    /**
     * @var Page
     */
    private $page;

    /**
     * @var BreadcrumbsClone
     */
    private $breadcrumbs;

    /**
     * @var array
     */
    private $post_ids;

    /**
     * @var array
     */
    private $page_ids;

    /**
     * @var array
     */
    private $attachment_ids;

    /**
     * @var array
     */
    private $tutorial_ids;

    /**
     * @var array
     */
    private $user_ids;

    /**
     * @var DOMDocument
     */
    private $dom;
    
    public function _before()
    {
        $this->dom = new \DOMDocument();
        $this->page = new Page();
        $this->breadcrumbs = new BreadcrumbsClone($this->page, [
            'before' => 'Path: /',
            'after' => '/',
            'home_label' => \esc_html__('Front'),
        ]);

        \register_post_type('tutorial', [
            'public' => true,
            'has_archive' => true,
            'rewrite' => ['slug' => 'tutorials', 'with_front' => false],
            'taxonomy' => ['level'],
        ]);

        \register_taxonomy('level', ['tutorial'], [
            'rewrite' => ['with_front' => false],
            'hierarchical' => true,
        ]);

        \wp_insert_term('basic', 'level');
        \wp_insert_term('best', 'category');
        \wp_insert_term('test', 'post_tag');

        // \update_option('permalink_structure', '/%postname%/');

        $this->post_ids = $this->factory->post->create_many(12, [
            'post_type' => 'post',
            'post_status' => 'publish',
        ]);

        $this->page_ids = $this->factory->post->create_many(12, [
            'post_type' => 'page',
            'post_status' => 'publish',
        ]);

        $this->tutorial_ids = $this->factory->post->create_many(12, [
            'post_type' => 'tutorial',
            'post_status' => 'publish',
        ]);

        $this->attachment_ids = $this->factory->post->create_many(12, [
            'post_type' => 'attachment',
            'post_parent' => $this->post_ids[\array_rand($this->post_ids)],
        ]);

        $this->user_ids = $this->factory->user->create_many(12);
    }

    public function _after()
    {
        \delete_option('show_on_front');
        \delete_option('page_on_front');
        \delete_option('page_for_posts');
    }

    public function testFrontPageIsHomeBreadcrumbs()
    {
        $this->go_to(\home_url('/'));
        $links = $this->getLinksForPage();

        $this->assertCount(1, $links);
        $this->checkLink($links[0], $this->breadcrumbs->getHomeLabel(), false);
    }

    public function testFrontPageIsPageBreadcrumbs()
    {
        \update_option('show_on_front', 'page');
        \update_option('page_on_front', $this->page_ids[0]);

        $this->go_to(\home_url('/'));
        $links = $this->getLinksForPage();

        $this->assertCount(1, $links);
        $this->checkLink($links[0], $this->breadcrumbs->getHomeLabel(), false);
    }

    public function testBlogPageBreadcrumbs()
    {
        $blog_page = $this->page_ids[2];
        $front_page = $this->page_ids[4];
        
        \update_option('show_on_front', 'page');
        \update_option('page_on_front', $front_page);
        \update_option('page_for_posts', $blog_page);

        $this->go_to(\get_permalink($blog_page));
        $links = $this->getLinksForPage();

        $this->assertCount(2, $links);
        $this->checkLink($links[0], $this->breadcrumbs->getHomeLabel());
        $this->checkLink($links[1], \get_the_title($blog_page), false);
    }

    public function testCustomPostTypeArchiveBreadcrumbs()
    {
        $post_id = $this->tutorial_ids[0];

        $this->go_to(\get_post_type_archive_link(\get_post_type($post_id)));
        $links = $this->getLinksForPage();

        $this->assertCount(2, $links);
        $this->checkLink($links[0], $this->breadcrumbs->getHomeLabel());
        $this->checkLink(
            $links[1],
            \post_type_archive_title('', false),
            false
        );
    }

    public function testSinglePostBreadcrumbs()
    {
        $post_id = $this->post_ids[6];
        $post_cat = \get_term_by('slug', 'best', 'category');

        \wp_set_post_categories($post_id, $post_cat->term_id);

        $this->go_to(\get_permalink($post_id));
        $links = $this->getLinksForPage();

        $this->assertCount(3, $links);
        $this->checkLink($links[0], $this->breadcrumbs->getHomeLabel());
        $this->checkLink($links[1], $post_cat->name);
        $this->checkLink($links[2], \get_the_title($post_id), false);
    }

    public function testSingleCustomPostBreadcrumbs()
    {
        $post_id = $this->tutorial_ids[2];
        $post_cat = \get_term_by('slug', 'basic', 'level');

        \wp_set_post_terms($post_id, $post_cat->term_id, 'level');

        $this->go_to(\get_permalink($post_id));
        $links = $this->getLinksForPage();

        $this->assertCount(3, $links);
        $this->checkLink($links[0], $this->breadcrumbs->getHomeLabel());
        $this->checkLink($links[1], $post_cat->name);
        $this->checkLink($links[2], \get_the_title($post_id), false);
    }

    public function testSingleAttachmentBreadcrumbs()
    {
        $post_id = $this->attachment_ids[0];
        $parent_id = \get_post($post_id)->post_parent;
        $parent_cat = \get_term_by('slug', 'best', 'category');

        \wp_set_post_categories($parent_id, $parent_cat->term_id);

        $this->go_to(\get_permalink($post_id));
        $links = $this->getLinksForPage();

        $this->assertCount(4, $links);
        $this->checkLink($links[0], $this->breadcrumbs->getHomeLabel());
        $this->checkLink($links[1], $parent_cat->name);
        $this->checkLink($links[2], \get_the_title($parent_id));
        $this->checkLink($links[3], \get_the_title($post_id), false);
    }

    public function testCategoryArchiveBreadcrumbs()
    {
        $this->go_to(\get_term_link('best', 'category'));
        $links = $this->getLinksForPage();

        $this->assertCount(2, $links);
        $this->checkLink($links[0], $this->breadcrumbs->getHomeLabel());
        $this->checkLink(
            $links[1],
            \single_cat_title('', false),
            false
        );
    }

    public function testTagArchiveBreadcrumbs()
    {
        $this->go_to(\get_term_link('test', 'post_tag'));
        $links = $this->getLinksForPage();

        $this->assertCount(2, $links);
        $this->checkLink($links[0], $this->breadcrumbs->getHomeLabel());
        $this->checkLink(
            $links[1],
            \single_tag_title('', false),
            false
        );
    }

    public function testTaxonomyArchiveBreadcrumbs()
    {
        $this->go_to(\get_term_link('basic', 'level'));
        $links = $this->getLinksForPage();

        $this->assertCount(2, $links);
        $this->checkLink($links[0], $this->breadcrumbs->getHomeLabel());
        $this->checkLink(
            $links[1],
            \single_term_title('', false),
            false
        );
    }

    public function testAuthorArchiveBreadcrumbs()
    {
        $user_id = $this->user_ids[\array_rand($this->user_ids)];
        
        $this->go_to(\get_author_posts_url($user_id));
        $links = $this->getLinksForPage();

        $this->assertCount(2, $links);
        $this->checkLink($links[0], $this->breadcrumbs->getHomeLabel());
        $this->checkLink(
            $links[1],
            \get_the_author_meta('display_name', $user_id),
            false
        );
    }

    public function testSearchArchiveBreadcrumbs()
    {
        $this->go_to(\get_search_link('post'));
        $links = $this->getLinksForPage();

        $this->assertCount(2, $links);
        $this->checkLink($links[0], $this->breadcrumbs->getHomeLabel());
        $this->checkLink(
            $links[1],
            \get_search_query(),
            false
        );
    }

    /**
     * @todo Debug: is_date() returns false
     */
    public function testDayArchiveBreadcrumbs()
    {
        $this->markTestIncomplete(
            'This test has not been implemented yet.'
        );
        
        $post_id = $this->tutorial_ids[4];
        
        \wp_update_post([
            'ID' => $post_id,
            'post_date' => '2007-06-05'
        ]);

        $y = (int)\get_the_date('Y', $post_id);
        $m = (int)\get_the_date('m', $post_id);
        $d = (int)\get_the_date('d', $post_id);
        
        $this->go_to(\add_query_arg([
            'year' => $y,
            'monthnum' => $m,
            'day' => $d,
        ], \home_url('/')));
        // $this->go_to(\get_day_link($y, $m, $d));
        $links = $this->getLinksForPage();

        $year = \get_query_var('year');
        $month = \get_query_var('monthnum');
        $day = \get_query_var('day');

        $timestamp = \strtotime("{$year}-{$month}-{$day}");

        // $this->assertSame('', \get_day_link($y, $m, $d));
        // $this->assertTrue(\is_date());

        $this->assertCount(4, $links);
        $this->checkLink($links[0], $this->breadcrumbs->getHomeLabel());
        $this->checkLink($links[1], "$y");
        $this->checkLink($links[2], \date('F', $timestamp));
        $this->checkLink($links[3], \date('d', $timestamp), false);
    }

    /**
     * Get breadcrumbs links for page
     *
     * @return string[]
     */
    private function getLinksForPage(): array
    {
        return $this->breadcrumbs->collectLinks()->getLinks();
    }

    /**
     * Check Link
     *
     * @param string $link
     * @param string $expectedLabel
     * @param bool $a Whether or not to check the presence of <a> tag.
     */
    private function checkLink(
        string $link,
        string $expectedLabel,
        bool $a = true
    ) {
        $this->dom->loadHTML($link);
        $spans = $this->dom->getElementsByTagName('span');
        $as = $this->dom->getElementsByTagName('a');

        if ($a) {
            $this->assertCount(1, $as);
        }

        $this->assertCount(1, $spans);
        $this->assertRegExp(
            "/$expectedLabel/",
            $spans->item(0)->childNodes->item(0)->ownerDocument
                ->saveHTML($spans->item(0)->childNodes->item(0))
        );
    }
}
