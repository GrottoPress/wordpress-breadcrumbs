# Form Field

## Description

This is a library to render breadcrumbs for a WordPress page.

## Usage

Install via composer:

`composer require grottopress/wordpress-breadcrumbs`

Instantiate and use thus:

    <?php

    use GrottoPress\WordPress\Breadcrumbs\Breadcrumbs;
    use GrottoPress\WordPress\Page\Page;

    // Instantiate
    $breadcrumbs = new Breadcrumbs( new Page(), [
        'home_label' => \esc_html__( 'Home' ),
        'delimiter' => '/',
        'before' => \esc_html__( 'Path: ' ),
    ] );

    // Render
    echo $breadcrumbs->render();
