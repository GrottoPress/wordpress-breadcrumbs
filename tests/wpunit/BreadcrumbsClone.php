<?php
declare (strict_types = 1);

namespace GrottoPress\WordPress\Breadcrumbs;

class BreadcrumbsClone extends Breadcrumbs
{
    public function getLinks()
    {
        return $this->links;
    }

    public function getHomeLabel()
    {
        return $this->home_label;
    }
}
