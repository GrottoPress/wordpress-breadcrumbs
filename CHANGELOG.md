# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/en/1.0.0/)
and this project adheres to [Semantic Versioning](http://semver.org/spec/v2.0.0.html).

## [Unreleased] - 

### Added
- Add support for PH 7.4

### Removed
- Remove support for microdata

## 0.7.3 - 2019-04-17

### Added
- Add `.gitattributes`

## 0.7.2 - 2019-04-16

### Fixed
- Fix composer dependency resolution failures in travis-ci

## 0.7.1 - 2019-04-16

### Added
- Add PHP 7.3 to travis-ci build matrix

## 0.7.0 - 2018-10-06

### Changed
- Rename `LICENSE.md` TO `LICENSE`
- Move `lang/` and `lib/` to a new `src/` directory

## 0.6.1 - 2018-09-12

### Changed
- Update translations template

## 0.6.0 - 2018-09-11

### Added
- Add translations template

### Changed
- Change `src/` directory name to `lib/`
- Change `grotto_breadcrumbs_links` filter hook to `grotto_wp_breadcrumbs_links`
- Rename `$home_label` attribute to `$homeLabel`

## 0.5.1 - 2018-08-22

### Fixed
- Update documentation to reflect previous release

## 0.5.0 - 2018-08-22

### Changed
- Move classes one level up for shorter namespaces

## 0.4.0 - 2018-03-08

### Added
- `.security.txt`
- `test` script to `composer.json`
- Set up [travis-ci](https://travis-ci.org/GrottoPress/wordpress-breadcrumbs)

### Changed
- Replace WP tests with isolated unit tests.

### Removed
- Redundant doc blocks, comments.

## 0.3.1 - 2017-11-16

### Changes
- Using strict equality operator (`===` and `!==`) for checks

## 0.3.0 - 2017-10-23

### Added
- Added unit tests

### Fixed
- Fixed argument type error when passing certain WordPress functions into `currentLink()` method.

### Changed
- Decoupled `collectLinks()` method from `render()` method. Users are required to call `collectLinks()` explicitly before `render()`

## 0.2.0 - 2017-09-28

### Changed
- Undo camelize render callbacks

## 0.1.0 - 2017-09-13

### Added
- `Breadcrumbs` class
- Set up test suite
