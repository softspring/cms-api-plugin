# CMS API Plugin Features

Functional definition for `softspring/cms-api-plugin`.

This package exposes selected Armonic CMS data through read-only JSON HTTP endpoints.

## Purpose

- Provide a CMS plugin entry point for API consumers.
- Expose CMS configuration resources through predictable REST endpoints.
- Expose CMS instance data such as content, routes, menus, blocks, sections, and media.
- Keep sensitive configuration values redacted when raw configuration is requested.
- Publish OpenAPI metadata for the exposed endpoints.

## Main Features

- Symfony bundle class for registering the plugin in Armonic CMS projects.
- Attribute-based route loading for API controllers.
- JSON endpoints for CMS sites, content types, block types, layouts, modules, media types, and menus.
- JSON endpoints for CMS content, routes, menus, blocks, sections, and media.
- Published content and published section version endpoints.
- Search and filtering helpers for query strings, sites, locales, publication state, and limits.
- OpenAPI attributes for API documentation through NelmioApiDocBundle.
- Standard QA scripts for code style, static analysis, unit tests, and dependency compatibility.
- CI workflow for regular and lowest supported dependency sets.

## Expected Usage

- Install it in a Symfony project that already uses `softspring/cms-bundle`.
- Register `SfsCmsApiPlugin` as a Symfony bundle when Flex does not do it automatically.
- Import the package routes under the API prefix chosen by the application.
- Protect the routes with the host application's firewall, access control, or gateway rules.
- Use the JSON endpoints to read CMS structure and content from external clients.

## Extension Points

- Add controllers under `Softspring\CmsApiPlugin\Controller` for new API resources.
- Add resource services under `Softspring\CmsApiPlugin\Api` for reusable serialization and query logic.
- Override or decorate API services when a project needs custom filtering, permissions, or payload shaping.
- Reuse CMS model interfaces from `softspring/cms-bundle`, `softspring/cms-sections-plugin`, and `softspring/media-bundle` to keep API output consistent.

## Current Limits

- The package currently exposes read-only endpoints.
- Authentication and authorization are expected to be configured by the host application.
- The package does not ship frontend assets.
