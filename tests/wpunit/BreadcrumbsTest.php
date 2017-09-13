<?php
namespace GrottoPress\WordPress\Breadcrumbs;

use Codeception\TestCase\WPTestCase;
use GrottoPress\WordPress\Page\Page;

class BreadcrumbsTest extends WPTestCase
{
    public function setUp()
    {
        parent::setUp();

        // your setup here
    }

    public function tearDown()
    {
        // your tear down methods here

        // then
        parent::tearDown();
    }

    // tests
    public function testHomepageBreadcrumbs()
    {
        $this->go_to(\home_url('/'));

        $breadcrumbs = new Breadcrumbs(new Page(), ['before' => 'Path: ']);
        
        //
    }
}
