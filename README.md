# CMS API Plugin (Experimental)

[![Latest Stable](https://img.shields.io/packagist/v/softspring/cms-api-plugin?label=stable&style=flat-square)](https://github.com/softspring/cms-api-plugin/releases)
[![Latest Unstable](https://img.shields.io/packagist/v/softspring/cms-api-plugin?label=unstable&style=flat-square&include_prereleases)](https://github.com/softspring/cms-api-plugin/releases)
[![License](https://img.shields.io/packagist/l/softspring/cms-api-plugin?style=flat-square)](https://github.com/softspring/cms-api-plugin/blob/6.1/LICENSE)
[![PHP Version](https://img.shields.io/packagist/dependency-v/softspring/cms-api-plugin/php?style=flat-square)](https://github.com/softspring/cms-api-plugin/blob/6.1/composer.json)
[![Downloads](https://img.shields.io/packagist/dt/softspring/cms-api-plugin?style=flat-square)](https://packagist.org/packages/softspring/cms-api-plugin)
[![CI](https://img.shields.io/github/actions/workflow/status/softspring/cms-api-plugin/ci.yml?branch=6.1&style=flat-square&label=CI)](https://github.com/softspring/cms-api-plugin/actions/workflows/ci.yml)
[![Coverage](https://img.shields.io/codecov/c/github/softspring/cms-api-plugin?branch=6.1&style=flat-square)](https://app.codecov.io/gh/softspring/cms-api-plugin/tree/6.1)

> **Experimental package:** this plugin is in active development and its route contracts, response payloads, permissions, and extension points may change before a stable release.

`softspring/cms-api-plugin` exposes selected Armonic CMS configuration and content data through read-only JSON HTTP endpoints.

It is intended for projects that need a controlled REST API for CMS structure, published content, routes, menus, blocks, sections, and media.

## Documentation

Read the [experimental cms-api-plugin guide](https://armonic.softspring.es/6.1@dev/docs/components/cms-api-plugin).

## Installation

```bash
composer require softspring/cms-api-plugin:6.1.*@dev
```

The plugin requires `softspring/cms-bundle`, `softspring/cms-sections-plugin`, `softspring/media-bundle`, Symfony FrameworkBundle, and NelmioApiDocBundle.

Register the bundle if Symfony Flex does not do it automatically:

```php
// config/bundles.php
return [
    Softspring\CmsApiPlugin\SfsCmsApiPlugin::class => ['all' => true],
];
```

Import the routes under the prefix chosen by the host application:

```yaml
# config/routes/sfs_cms_api.yaml
sfs_cms_api:
    resource: '@SfsCmsApiPlugin/config/routing/api.yaml'
    prefix: /api/cms
```

Protect the imported route prefix with the host application's firewall or gateway rules.

## Usage

The plugin exposes read-only JSON endpoints for CMS configuration and CMS instances.

Configuration endpoints include:

- `/sites`
- `/content-types`
- `/blocks`
- `/menus`
- `/layouts`
- `/modules`
- `/media-types`

Instance endpoints include:

- `/content`
- `/routes`
- `/menus`
- `/blocks`
- `/sections`
- `/media`

Most list endpoints support query parameters such as `query`, `site`, `locale`, `publishedOnly`, `limit`, or `includeRawConfig` depending on the resource.

## Features

See [FEATURES.md](FEATURES.md) for the functional scope of this package.

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md).

[Report issues](https://github.com/softspring/cms-api-plugin/issues) and [send Pull Requests](https://github.com/softspring/cms-api-plugin/pulls)

## Security

See [SECURITY.md](SECURITY.md).

## License

This package is free and released under the [AGPL-3.0 license](LICENSE).
