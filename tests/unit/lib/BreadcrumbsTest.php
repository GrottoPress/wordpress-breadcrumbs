<?php
declare (strict_types = 1);

namespace GrottoPress\WordPress;

use GrottoPress\WordPress\Breadcrumbs\AbstractTestCase;
use DOMDocument;
use Codeception\Util\Stub;
use tad\FunctionMocker\FunctionMocker;

class BreadcrumbsTest extends AbstractTestCase
{
    public function _before()
    {
        FunctionMocker::replace('is_rtl', false);
        FunctionMocker::replace('home_url', 'http://my.site/');
        FunctionMocker::replace(
            [
                'sanitize_text_field',
                'esc_html__',
                'esc_attr',
                'esc_url',
                'absint'
            ],
            function ($arg) {
                return $arg;
            }
        );

        FunctionMocker::replace(
            'apply_filters',
            function (string $hook, $out) {
                return $out;
            }
        );

        FunctionMocker::replace('get_year_link', function (int $year): string {
            return "http://my.site/{$year}/";
        });

        FunctionMocker::replace('get_month_link', function (
            int $year,
            int $month
        ): string {
            return "http://my.site/{$year}/{$month}/";
        });

        FunctionMocker::replace('get_day_link', function (
            int $year,
            int $month,
            int $day
        ): string {
            return "http://my.site/{$year}/{$month}/{$day}/";
        });

        FunctionMocker::replace(
            'get_term_link',
            function (int $id, string $tax): string {
                return "http://my.site/{$tax}/{$id}/";
            }
        );

        FunctionMocker::replace('get_permalink', function (int $id): string {
            return "http://my.site/stuff/{$id}/";
        });

        FunctionMocker::replace('get_the_title', function (int $id): string {
            return "Title {$id}";
        });

        FunctionMocker::replace(
            'get_post_type_archive_link',
            function (string $post_type): string {
                return "http://my.site/{$post_type}/";
            }
        );
    }

    /**
     * @dataProvider pagedProvider
     */
    public function testFrontPageIsHome(int $page_num)
    {
        $breadcrumbs = $this->getInstance(['front_page', 'home'], $page_num);

        if ($this->isPaged($page_num)) {
            $this->assertCount(2, $breadcrumbs->links);
            $this->checkLink(
                $breadcrumbs->links[0],
                'Front',
                'http://my.site/'
            );
            $this->checkLink($breadcrumbs->links[1], "Page {$page_num}");
        } else {
            $this->assertCount(1, $breadcrumbs->links);
            $this->checkLink($breadcrumbs->links[0], 'Front');
        }
    }

    /**
     * @dataProvider pagedProvider
     */
    public function testFrontPageIsPage(int $page_num)
    {
        $breadcrumbs = $this->getInstance(['front_page', 'page'], $page_num);

        if ($this->isPaged($page_num)) {
            $this->assertCount(2, $breadcrumbs->links);
            $this->checkLink(
                $breadcrumbs->links[0],
                'Front',
                'http://my.site/'
            );
            $this->checkLink($breadcrumbs->links[1], "Page {$page_num}");
        } else {
            $this->assertCount(1, $breadcrumbs->links);
            $this->checkLink($breadcrumbs->links[0], 'Front');
        }
    }

    /**
     * @dataProvider pagedProvider
     */
    public function testBlogPage(int $page_num)
    {
        FunctionMocker::replace('get_option', 11);

        $breadcrumbs = $this->getInstance(['home'], $page_num);

        $this->checkLink($breadcrumbs->links[0], 'Front', 'http://my.site/');

        if ($this->isPaged($page_num)) {
            $this->assertCount(3, $breadcrumbs->links);
            $this->checkLink(
                $breadcrumbs->links[1],
                'Title 11',
                'http://my.site/stuff/11/'
            );
            $this->checkLink($breadcrumbs->links[2], "Page {$page_num}");
        } else {
            $this->assertCount(2, $breadcrumbs->links);
            $this->checkLink($breadcrumbs->links[1], 'Title 11');
        }
    }

    /**
     * @dataProvider pagedProvider
     */
    public function testCustomPostTypeArchive(int $page_num)
    {
        FunctionMocker::replace('post_type_archive_title', 'Tutorials');
        FunctionMocker::replace('get_query_var', 'tutorial');

        $breadcrumbs = $this->getInstance(
            ['archive', 'post_type_archive'],
            $page_num
        );

        $this->checkLink($breadcrumbs->links[0], 'Front', 'http://my.site/');

        if ($this->isPaged($page_num)) {
            $this->assertCount(3, $breadcrumbs->links);
            $this->checkLink(
                $breadcrumbs->links[1],
                'Tutorials',
                'http://my.site/tutorial/'
            );
            $this->checkLink($breadcrumbs->links[2], "Page {$page_num}");
        } else {
            $this->assertCount(2, $breadcrumbs->links);
            $this->checkLink($breadcrumbs->links[1], 'Tutorials');
        }
    }

    /**
     * @dataProvider pagedProvider
     */
    public function testCategoryArchive(int $page_num)
    {
        $cats = [
            new class {
                public $term_id = 3;
                public $name = 'Politics';
                public $parent = 5;
            },
            new class {
                public $term_id = 5;
                public $name = 'News';
                public $parent = 0;
            },
        ];

        FunctionMocker::replace('get_query_var', $cats[0]->term_id);

        FunctionMocker::replace(
            'get_category',
            function (int $id) use ($cats) {
                foreach ($cats as $cat) {
                    if ($id === $cat->term_id) {
                        return $cat;
                    }
                }

                return new class {
                    public $term_id = 0;
                    public $name = '';
                    public $parent = 0;
                };
            }
        );

        FunctionMocker::replace(
            'get_category_link',
            function (int $id): string {
                return "http://my.site/category/{$id}/";
            }
        );

        $breadcrumbs = $this->getInstance(['archive', 'category'], $page_num);

        $this->checkLink($breadcrumbs->links[0], 'Front', 'http://my.site/');
        $this->checkLink(
            $breadcrumbs->links[1],
            'News',
            'http://my.site/category/5/'
        );

        if ($this->isPaged($page_num)) {
            $this->assertCount(4, $breadcrumbs->links);
            $this->checkLink(
                $breadcrumbs->links[2],
                'Politics',
                'http://my.site/category/3/'
            );
            $this->checkLink($breadcrumbs->links[3], "Page {$page_num}");
        } else {
            $this->assertCount(3, $breadcrumbs->links);
            $this->checkLink($breadcrumbs->links[2], 'Politics');
        }
    }

    /**
     * @dataProvider pagedProvider
     */
    public function testYearArchive(int $page_num)
    {
        FunctionMocker::replace('get_query_var', function (
            string $var,
            int $default = 0
        ): int {
            return ('year' === $var ? 1999 : 1);
        });

        $breadcrumbs = $this->getInstance(
            ['date', 'year', 'archive'],
            $page_num
        );

        $this->checkLink($breadcrumbs->links[0], 'Front', 'http://my.site/');

        if ($this->isPaged($page_num)) {
            $this->assertCount(3, $breadcrumbs->links);
            $this->checkLink(
                $breadcrumbs->links[1],
                '1999',
                'http://my.site/1999/'
            );
            $this->checkLink($breadcrumbs->links[2], "Page {$page_num}");
        } else {
            $this->assertCount(2, $breadcrumbs->links);
            $this->checkLink($breadcrumbs->links[1], '1999');
        }
    }

    /**
     * @dataProvider pagedProvider
     */
    public function testMonthArchive(int $page_num)
    {
        FunctionMocker::replace('get_query_var', function (
            string $var,
            int $default = 0
        ): int {
            return ('year' === $var ? 1998 : 9);
        });

        $breadcrumbs = $this->getInstance(
            ['date', 'month', 'archive'],
            $page_num
        );

        $this->checkLink($breadcrumbs->links[0], 'Front', 'http://my.site/');
        $this->checkLink(
            $breadcrumbs->links[1],
            '1998',
            'http://my.site/1998/'
        );

        if ($this->isPaged($page_num)) {
            $this->assertCount(4, $breadcrumbs->links);
            $this->checkLink(
                $breadcrumbs->links[2],
                'Sep 1998',
                'http://my.site/1998/9/'
            );
            $this->checkLink($breadcrumbs->links[3], "Page {$page_num}");
        } else {
            $this->assertCount(3, $breadcrumbs->links);
            $this->checkLink($breadcrumbs->links[2], 'Sep 1998');
        }
    }

    /**
     * @dataProvider pagedProvider
     */
    public function testDayArchive(int $page_num)
    {
        FunctionMocker::replace('get_query_var', function (
            string $var,
            int $default = 0
        ): int {
            return ('year' === $var ? 2001 : 4);
        });

        $breadcrumbs = $this->getInstance(
            ['date', 'day', 'archive'],
            $page_num
        );

        $this->checkLink($breadcrumbs->links[0], 'Front', 'http://my.site/');
        $this->checkLink(
            $breadcrumbs->links[1],
            '2001',
            'http://my.site/2001/'
        );
        $this->checkLink(
            $breadcrumbs->links[2],
            'Apr 2001',
            'http://my.site/2001/4/'
        );

        if ($this->isPaged($page_num)) {
            $this->assertCount(5, $breadcrumbs->links);
            $this->checkLink(
                $breadcrumbs->links[3],
                '04 Apr 2001',
                'http://my.site/2001/4/4/'
            );
            $this->checkLink($breadcrumbs->links[4], "Page {$page_num}");
        } else {
            $this->assertCount(4, $breadcrumbs->links);
            $this->checkLink($breadcrumbs->links[3], '04 Apr 2001');
        }
    }

    /**
     * @dataProvider pagedProvider
     */
    public function testSearchArchive(int $page_num)
    {
        FunctionMocker::replace('get_search_query', 'search query');
        FunctionMocker::replace(
            'get_search_link',
            'http://my.site/search/search-query/'
        );

        $breadcrumbs = $this->getInstance(['search', 'archive'], $page_num);

        $this->checkLink($breadcrumbs->links[0], 'Front', 'http://my.site/');

        if ($this->isPaged($page_num)) {
            $this->assertCount(3, $breadcrumbs->links);
            $this->checkLink(
                $breadcrumbs->links[1],
                '“search query”',
                'http://my.site/search/search-query/'
            );
            $this->checkLink($breadcrumbs->links[2], "Page {$page_num}");
        } else {
            $this->assertCount(2, $breadcrumbs->links);
            $this->checkLink(
                $breadcrumbs->links[1],
                '“search query”'
            );
        }
    }

    /**
     * @dataProvider pagedProvider
     */
    public function testTagArchive(int $page_num)
    {
        FunctionMocker::replace('get_query_var', 44);
        FunctionMocker::replace('single_tag_title', 'Hotel Rooms');
        FunctionMocker::replace('get_tag_link', function (int $id): string {
            return "http://my.site/tag/{$id}/";
        });

        $breadcrumbs = $this->getInstance(['tag', 'archive'], $page_num);

        $this->checkLink($breadcrumbs->links[0], 'Front', 'http://my.site/');

        if ($this->isPaged($page_num)) {
            $this->assertCount(3, $breadcrumbs->links);
            $this->checkLink(
                $breadcrumbs->links[1],
                'Hotel Rooms',
                'http://my.site/tag/44/'
            );
            $this->checkLink($breadcrumbs->links[2], "Page {$page_num}");
        } else {
            $this->assertCount(2, $breadcrumbs->links);
            $this->checkLink($breadcrumbs->links[1], 'Hotel Rooms');
        }
    }

    /**
     * @dataProvider pagedProvider
     */
    public function testAuthorArchive(int $page_num)
    {
        FunctionMocker::replace('get_query_var', 15);
        FunctionMocker::replace('get_the_author_meta', 'Kofi Boakye');
        FunctionMocker::replace(
            'get_author_posts_url',
            function (int $id): string {
                return "http://my.site/author/{$id}/";
            }
        );

        $breadcrumbs = $this->getInstance(['author', 'archive'], $page_num);

        $this->checkLink($breadcrumbs->links[0], 'Front', 'http://my.site/');

        if ($this->isPaged($page_num)) {
            $this->assertCount(3, $breadcrumbs->links);
            $this->checkLink(
                $breadcrumbs->links[1],
                'Kofi Boakye',
                'http://my.site/author/15/'
            );
            $this->checkLink($breadcrumbs->links[2], "Page {$page_num}");
        } else {
            $this->assertCount(2, $breadcrumbs->links);
            $this->checkLink($breadcrumbs->links[1], 'Kofi Boakye');
        }
    }

    /**
     * @dataProvider pagedProvider
     */
    public function test404Archive(int $page_num)
    {
        $breadcrumbs = $this->getInstance(['404'], $page_num);

        $this->checkLink($breadcrumbs->links[0], 'Front', 'http://my.site/');
        $this->checkLink($breadcrumbs->links[1], 'Error 404');
    }

    /**
     * @dataProvider pagedProvider
     */
    public function testPostTypeArchive(int $page_num)
    {
        FunctionMocker::replace('get_query_var', 'tutorial');
        FunctionMocker::replace('post_type_archive_title', 'Tutorials');

        $breadcrumbs = $this->getInstance(
            ['post_type_archive', 'archive'],
            $page_num
        );

        if ($this->isPaged($page_num)) {
            $this->assertCount(3, $breadcrumbs->links);
            $this->checkLink(
                $breadcrumbs->links[1],
                'Tutorials',
                'http://my.site/tutorial/'
            );
            $this->checkLink($breadcrumbs->links[2], "Page {$page_num}");
        } else {
            $this->assertCount(2, $breadcrumbs->links);
            $this->checkLink($breadcrumbs->links[1], 'Tutorials');
        }
    }

    /**
     * @dataProvider pagedProvider
     */
    public function testTaxonomyArchive(int $page_num)
    {
        $terms = [
            new class {
                public $term_id = 5;
                public $name = 'Beginner';
                public $slug = 'beginner';
                public $parent = 3;
                public $taxonomy = 'level';
            },
            new class {
                public $term_id = 3;
                public $name = 'Basic';
                public $slug = 'basic';
                public $parent = 0;
                public $taxonomy = 'level';
            },
        ];

        FunctionMocker::replace(
            'get_query_var',
            function (string $var) use ($terms): string {
                if ('taxonomy' === $var) {
                    return 'level';
                }

                return $terms[0]->slug;
            }
        );

        FunctionMocker::replace(
            'get_term_by',
            function (string $by, $_term, string $tax) use ($terms) {
                foreach ($terms as $term) {
                    if (('slug' === $by) && ($_term === $term->slug)) {
                        return $term;
                    }

                    if (('id' === $by) && ($_term === $term->term_id)) {
                        return $term;
                    }
                }

                return new class {
                    public $term_id = 0;
                    public $slug = '';
                    public $name = '';
                    public $parent = 0;
                    public $taxonomy = '';
                };
            }
        );

        FunctionMocker::replace('is_wp_error', false);

        $breadcrumbs = $this->getInstance(['tax', 'archive'], $page_num);

        $this->checkLink($breadcrumbs->links[0], 'Front', 'http://my.site/');
        $this->checkLink(
            $breadcrumbs->links[1],
            'Basic',
            'http://my.site/level/3/'
        );

        if ($this->isPaged($page_num)) {
            $this->assertCount(4, $breadcrumbs->links);
            $this->checkLink(
                $breadcrumbs->links[2],
                'Beginner',
                'http://my.site/level/5/'
            );
            $this->checkLink($breadcrumbs->links[3], "Page {$page_num}");
        } else {
            $this->assertCount(3, $breadcrumbs->links);
            $this->checkLink($breadcrumbs->links[2], 'Beginner');
        }
    }

    /**
     * @dataProvider pagedProvider
     */
    public function testSinglePostTypeWithParent(int $page_num)
    {
        $posts = [
            new class {
                public $ID = 4;
                public $post_type = 'page';
                public $post_parent = 0;
            },
            new class {
                public $ID = 5;
                public $post_type = 'page';
                public $post_parent = 4;
            },
        ];

        FunctionMocker::replace(
            'get_post',
            function (int $id = 0) use ($posts) {
                foreach ($posts as $post) {
                    if ($id === $post->ID) {
                        return $post;
                    }
                }

                return $posts[1];
            }
        );

        $breadcrumbs = $this->getInstance(['singular', 'page'], $page_num);

        $this->checkLink($breadcrumbs->links[0], 'Front', 'http://my.site/');
        $this->checkLink(
            $breadcrumbs->links[1],
            'Title 4',
            'http://my.site/stuff/4/'
        );

        if ($this->isPaged($page_num)) {
            $this->assertCount(4, $breadcrumbs->links);
            $this->checkLink(
                $breadcrumbs->links[2],
                'Title 5',
                'http://my.site/stuff/5/'
            );
            $this->checkLink($breadcrumbs->links[3], "Page {$page_num}");
        } else {
            $this->assertCount(3, $breadcrumbs->links);
            $this->checkLink($breadcrumbs->links[2], 'Title 5');
        }
    }

    /**
     * @dataProvider pagedProvider
     */
    public function testSinglePostTypeWithoutParentAndWithTerms(int $page_num)
    {
        $post_1 = $this->getMockBuilder('WP_Post')->getMock();
        $post_1->ID = 4;
        $post_1->post_type = 'post';
        $post_1->post_parent = 0;
        $post_1->category = [
            new class {
                public $term_id = 5;
                public $name = 'Politics';
                public $slug = 'politics';
                public $parent = 3;
                public $taxonomy = 'category';
            },
            new class {
                public $term_id = 3;
                public $name = 'News';
                public $slug = 'news';
                public $parent = 0;
                public $taxonomy = 'category';
            },
        ];

        $posts = [$post_1];

        $taxonomies = [
            'category' => [
                'name' => 'category',
                'hierarchical' => true,
            ],
            'post_tag' => [
                'name' => 'post_tag',
                'hierarchical' => false,
            ],
        ];

        FunctionMocker::replace('is_post_type_hierarchical', false);

        FunctionMocker::replace(
            'is_taxonomy_hierarchical',
            function (string $tax) use ($taxonomies): bool {
                return $taxonomies[$tax]['hierarchical'];
            }
        );

        FunctionMocker::replace(
            'get_object_taxonomies',
            $taxonomies
        );

        FunctionMocker::replace(
            'get_post',
            function (int $id = 0) use ($posts) {
                foreach ($posts as $post) {
                    if ($id === $post->ID) {
                        return $post;
                    }
                }

                return $posts[0];
            }
        );

        FunctionMocker::replace('is_wp_error', false);

        FunctionMocker::replace(
            'get_the_terms',
            function ($object, $tax) use ($posts): array {
                foreach ($posts as $post) {
                    if ($object->ID === $post->ID) {
                        return $post->{$tax};
                    }
                }
            }
        );

        FunctionMocker::replace(
            'get_term_by',
            function (string $by, $term, string $tax) use ($posts) {
                foreach ($posts as $post) {
                    if (!isset($post->{$tax})) {
                        continue;
                    }

                    foreach ($post->{$tax} as $term_obj) {
                        if (('id' === $by) && ($term === $term_obj->term_id)) {
                            return $term_obj;
                        }

                        if (('slug' === $by) && ($term === $term_obj->slug)) {
                            return $term_obj;
                        }
                    }
                }

                return new class {
                    public $term_id = 0;
                    public $slug = '';
                    public $name = '';
                    public $parent = 0;
                    public $taxonomy = '';
                };
            }
        );

        $breadcrumbs = $this->getInstance(['singular', 'page'], $page_num);

        $this->checkLink($breadcrumbs->links[0], 'Front', 'http://my.site/');
        $this->checkLink(
            $breadcrumbs->links[1],
            'News',
            'http://my.site/category/3/'
        );
        $this->checkLink(
            $breadcrumbs->links[2],
            'Politics',
            'http://my.site/category/5/'
        );

        if ($this->isPaged($page_num)) {
            $this->assertCount(5, $breadcrumbs->links);
            $this->checkLink(
                $breadcrumbs->links[3],
                'Title 4',
                'http://my.site/stuff/4/'
            );
            $this->checkLink($breadcrumbs->links[4], "Page {$page_num}");
        } else {
            $this->assertCount(4, $breadcrumbs->links);
            $this->checkLink($breadcrumbs->links[3], 'Title 4');
        }
    }

    /**
     * @dataProvider pagedProvider
     */
    public function testSinglePostTypeWithoutParentOrTerms(int $page_num)
    {
        $post_1 = $this->getMockBuilder('WP_Post')->getMock();
        $post_1->ID = 4;
        $post_1->post_type = 'post';
        $post_1->post_parent = 0;
        $post_1->post_tag = [
            new class {
                public $term_id = 5;
                public $name = 'John Rawlings';
                public $slug = 'john-rawlings';
                public $taxonomy = 'post_tag';
            },
            new class {
                public $term_id = 3;
                public $name = 'John Kuffour';
                public $slug = 'john-kuffour';
                public $taxonomy = 'post_tag';
            },
        ];

        $posts = [$post_1];

        $taxonomies = [
            'category' => new class {
                public $name = 'category';
                public $hierarchical = true;
            },
            'post_tag' => new class {
                public $name = 'post_tag';
                public $hierarchical = false;
            },
        ];

        $post_types = [
            'post' => new class {
                public $labels;

                public function __construct()
                {
                    $this->labels = new class {
                        public $name = 'Post';
                    };
                }
            },
        ];

        FunctionMocker::replace('is_post_type_hierarchical', false);

        FunctionMocker::replace(
            'is_taxonomy_hierarchical',
            function (string $tax) use ($taxonomies): bool {
                return $taxonomies[$tax]->hierarchical;
            }
        );

        FunctionMocker::replace('get_object_taxonomies', $taxonomies);

        FunctionMocker::replace(
            'get_post',
            function (int $id = 0) use ($posts) {
                foreach ($posts as $post) {
                    if ($id === $post->ID) {
                        return $post;
                    }
                }

                return $posts[0];
            }
        );

        FunctionMocker::replace('is_wp_error', false);

        FunctionMocker::replace(
            'get_the_terms',
            function ($object, $tax) use ($posts): array {
                foreach ($posts as $post) {
                    if ($object->ID === $post->ID) {
                        return ($post->{$tax} ?? []);
                    }
                }
            }
        );

        FunctionMocker::replace('get_option', 23);

        FunctionMocker::replace(
            'get_term_by',
            function (string $by, $term, string $tax) use ($posts) {
                foreach ($posts as $post) {
                    if (!isset($post->{$tax})) {
                        continue;
                    }

                    foreach ($post->{$tax} as $term_obj) {
                        if (('id' === $by) && ($term === $term_obj->term_id)) {
                            return $term_obj;
                        }

                        if (('slug' === $by) && ($term === $term_obj->slug)) {
                            return $term_obj;
                        }
                    }
                }

                return new class {
                    public $term_id = 0;
                    public $slug = '';
                    public $name = '';
                    public $parent = 0;
                    public $taxonomy = '';
                };
            }
        );

        FunctionMocker::replace(
            'get_post_type_object',
            function (string $type) use ($post_types) {
                return $post_types[$type];
            }
        );

        $breadcrumbs = $this->getInstance(['singular', 'page'], $page_num);

        $this->checkLink($breadcrumbs->links[0], 'Front', 'http://my.site/');
        $this->checkLink(
            $breadcrumbs->links[1],
            'Title 23',
            'http://my.site/post/'
        );

        if ($this->isPaged($page_num)) {
            $this->assertCount(4, $breadcrumbs->links);
            $this->checkLink(
                $breadcrumbs->links[2],
                'Title 4',
                'http://my.site/stuff/4/'
            );
            $this->checkLink($breadcrumbs->links[3], "Page {$page_num}");
        } else {
            $this->assertCount(3, $breadcrumbs->links);
            $this->checkLink($breadcrumbs->links[2], 'Title 4');
        }
    }

    public function pagedProvider()
    {
        return [
            'is paged' => [5],
            'is not paged' => [1],
        ];
    }

    public function singlePostTypeWithoutParentOrTermsProvider()
    {
        return [
            'is paged, posts page is not front page' => [5, 4],
            'is paged, posts page is front page' => [2, 0],
            'is not paged, posts page is not front page' => [1, 3],
            'is not paged, posts page is front page' => [1, 0],
        ];
    }

    /**
     * @var string[] $page_type
     */
    private function getInstance(array $page_type, int $page_num): Breadcrumbs
    {
        if ($this->isPaged($page_num)) {
            $page_type[] = 'paged';
        }

        $page = Stub::makeEmpty(Page::class, [
            'is' => function (string $type) use ($page_type): bool {
                return \in_array($type, $page_type);
            },
            'type' => $page_type,
            'number' => $page_num,
        ]);

        return new Breadcrumbs($page, [
            'before' => 'Path: /',
            'after' => '/',
            'homeLabel' => 'Front',
        ]);
    }

    private function isPaged(int $page_num): bool
    {
        return ($page_num > 1);
    }

    /**
     * @param string $a Expected URL
     */
    private function checkLink(
        string $link,
        string $expected_label,
        string $expected_url = ''
    ) {
        $dom = new DOMDocument();

        $dom->loadHTML($link);
        $spans = $dom->getElementsByTagName('span');
        $as = $dom->getElementsByTagName('a');

        if ($expected_url) {
            $this->assertCount(1, $as);
            $this->assertSame(
                $expected_url,
                $as->item(0)->attributes->getNamedItem('href')->value
            );
        } else {
            $this->assertCount(0, $as);
        }

        $this->assertCount(1, $spans);
        $this->assertSame(
            $expected_label,
            $spans->item(0)->childNodes->item(0)->ownerDocument
                ->saveHTML($spans->item(0)->childNodes->item(0))
        );
    }
}
