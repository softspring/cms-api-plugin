# CMS API Plugin Features

Functional definition for `softspring/cms-api-plugin`.

This package exposes Armonic CMS queries and controlled operations through shared services and JSON HTTP endpoints.

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
- Reuse and extend application queries under `Softspring\CmsApiPlugin\Application\Query` instead of adding CMS business logic to HTTP adapters.
- Override or decorate query services when a project needs custom filtering, permissions, or payload shaping.
- Reuse CMS model interfaces from `softspring/cms-bundle`, `softspring/cms-sections-plugin`, and `softspring/media-bundle` to keep API output consistent.

## Current Limits

- Mutations require application-provided CMS_API_READ, CMS_API_WRITE, CMS_API_PUBLISH and CMS_API_DELETE authorization and the existing CMS permissions.
- Destructive operations and publication require explicit confirmation.
- Authentication and authorization are expected to be configured by the host application.
- The package does not ship frontend assets.

## Shared application services

- Shared application queries live in this plugin and serve REST, MCP, and AI consumers. REST route imports and Swagger/Nelmio documentation are optional; legacy *Api services remain deprecated compatibility subclasses.


## Controlled CMS operations

- List operations and inspect actual Symfony forms before executing changes.
- Manage contents, versions, routes, menus, blocks, sections and media using the CMS workflow.
- Read resources, including drafts, and render version previews without publication.
- Enforce operation scopes, CMS permissions and explicit confirmation for publication and deletion.

## Optional version SEO statistics

- Return deterministic frequencies, density and emphasis for a selected content version when the SEO plugin is enabled.
- Require CMS_API_READ and leave versions and metadata unchanged.
