# Contributing

Thank you for helping improve Laravel Msgpack. The project aims to provide a small, predictable, and well-tested MessagePack integration for Laravel applications.

## Before opening an issue

- Search existing issues before opening a new one.
- Use the Bug report form for reproducible problems.
- Use the Feature request form for proposed behavior or API changes.
- Use the Documentation improvement form for unclear or missing documentation.
- Report security vulnerabilities privately as described in `SECURITY.md`.

## Development setup

Requirements:

- PHP 8.1 or newer
- Composer

Install dependencies and run the test suite:

```bash
composer install
./vendor/bin/phpunit tests
```

The CI workflow also runs the compatibility matrix across PHP 8.1 through 8.4 and Laravel Testbench 7 through 10.

## Branches and pull requests

- Create a focused branch from `master`.
- Use a descriptive branch name such as `feature/short-description`, `fix/short-description`, or `docs/short-description`.
- Keep pull requests small enough to review.
- Link the relevant issue when one exists.
- Include or update tests for behavior changes.
- Update the README or other documentation when the public behavior changes.
- Do not commit secrets, generated dependencies, or unrelated formatting changes.

All changes to `master` go through a pull request and the required CI checks. Maintainer bypass is reserved for the repository owner when a pull request is open.

## Code style

Follow the existing PHP style and Laravel conventions. Prefer clear, minimal changes over speculative abstractions or compatibility layers that are not required by the supported versions.

## Commit messages

Use a short, imperative subject and keep each commit focused. There is no requirement to squash commits locally; the repository may squash a pull request when it is merged.

## Review expectations

Reviewers look for correctness, backwards compatibility, tests, documentation, and a clear public API. A passing CI run is required, but it does not replace a careful review of behavior and edge cases.
