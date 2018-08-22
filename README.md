# WordPress Breadcrumbs

Build and display breadcrumbs for WordPress pages

## Usage

Install via composer:

```bash
composer require grottopress/wordpress-breadcrumbs
```

Instantiate and use thus:

```php
<?php
declare (strict_types = 1);

use GrottoPress\WordPress\Breadcrumbs\Breadcrumbs;
use GrottoPress\WordPress\Page\Page;

// Instantiate
$breadcrumbs = new Breadcrumbs(new Page(), [
    'home_label' => \esc_html__('Home'),
    'delimiter' => '/',
    'before' => \esc_html__('Path: '),
]);

// Render
$breadcrumbs->render();
```
