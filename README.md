# WordPress Breadcrumbs

Build and display breadcrumbs for WordPress pages

## Installation

Install via composer:

```bash
composer require grottopress/wordpress-breadcrumbs
```

## Usage

```php
<?php
declare (strict_types = 1);

use GrottoPress\WordPress\Breadcrumbs;
use GrottoPress\WordPress\Page;

// Instantiate
$breadcrumbs = new Breadcrumbs(new Page(), [
    'home_label' => \esc_html__('Home'),
    'delimiter' => '/',
    'before' => \esc_html__('Path: '),
]);

// Render
$breadcrumbs->render();
```

## Development

Run tests with `composer run test`.

## Contributing

1. [Fork it](https://github.com/GrottoPress/wordpress-breadcrumbs/fork)
1. Switch to the `master` branch: `git checkout master`
1. Create your feature branch: `git checkout -b my-new-feature`
1. Make your changes, updating changelog and documentation as appropriate.
1. Commit your changes: `git commit`
1. Push to the branch: `git push origin my-new-feature`
1. Submit a new *Pull Request* against the `GrottoPress:master` branch.
