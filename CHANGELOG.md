# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/en/1.0.0/)
and this project adheres to [Semantic Versioning](http://semver.org/spec/v2.0.0.html).

## Unreleased 0.3.2 - 

### Added
- Added `test` script to `composer.json`

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
