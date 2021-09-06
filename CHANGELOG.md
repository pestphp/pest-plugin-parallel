# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/)
and this project adheres to [Semantic Versioning](http://semver.org/).

## [v0.3.1 (2021-09-06)](https://github.com/pestphp/pest-plugin-parallel/compare/v0.3.0...v0.3.1)
### Fixed
- `stopOnError` will now respectfully stop the test suite ([#9](https://github.com/pestphp/pest-plugin-parallel/pull/9))
- Running `vendor/bin/pest --parallel` now has identical functionality to `php artisan test --parallel` when in a Laravel environment ([#10](https://github.com/pestphp/pest-plugin-parallel/pull/10))

## [v0.3.0 (2021-08-25)](https://github.com/pestphp/pest-plugin-parallel/compare/v0.2.1...v0.3.0)
### Added
- Support for code syntax highlighting in collision error output ([#7](https://github.com/pestphp/pest-plugin-parallel/pull/7))

## [v0.2.1 (2021-08-25)](https://github.com/pestphp/pest-plugin-parallel/compare/v0.2.0...v0.2.1)
### Fixed
- Test files that do not perform any tests will no longer output any message, to keep in tandem with standard Pest output ([#5](https://github.com/pestphp/pest-plugin-parallel/pull/5))
