# Development

## Tests and static analysis

Tests need a MySQL/MariaDB database: copy `test/config/test.ini.dist` to `test/config/test.ini`
and set the connection. Since all tests share this database, do not run several test runs at once.

```sh
composer dev:all                 # syntax, PHPStan, PHPUnit
composer dev:phpunit             # unit tests, writing coverage reports to doc/Test/
composer dev:phpunit-coverage    # unit tests, showing code coverage on the console, too
composer dev:phpstan             # static analysis (level 9 + strict rules)
composer dev:test-syntax         # PHP syntax check
```

## Documentation

This documentation is built with [MkDocs](https://www.mkdocs.org/) and
[Material for MkDocs](https://squidfunk.github.io/mkdocs-material/).

```sh
composer dev:docs                # generate API reference and build site, open doc/Site/index.html
composer dev:docs-serve          # live preview on http://localhost:8000
composer dev:docs-api            # only generate API reference (doc/Manual/api/)
composer dev:docs-install        # install MkDocs into a Python venv (optional, else Docker is used)
```

| Path | Content |
|------|---------|
| `util/mkdocs.yml` | configuration and navigation |
| `doc/Site/` | built HTML site, **generated** |
| `doc/Manual/` | hand-written pages (guides) and assets |
| `doc/Manual/api/` | API reference, **generated** – do not edit |
| `util/docs/generate-api.php` | generator: reads classes by reflection and their doc blocks |
| `util/docs/examples/` | examples, included into the API page of a class (same path as class) |
| `util/docs/docs.sh` | runs MkDocs: local installation, Python venv or Docker |

MkDocs is taken from the first available source:

1. a Python virtual environment in `util/docs/.venv` (created by `composer dev:docs-install`),
2. `mkdocs` in `PATH` (with `mkdocs-material` installed),
3. the Docker image `squidfunk/mkdocs-material`.

The deprecated namespace `DAO` is not part of the API reference (see `EXCLUDED_NAMESPACES` in the generator).

!!! tip "Documenting a class"
    Summary and description of classes and methods come from doc blocks, types from the signatures.
    To add examples to a class page, create a Markdown file in `util/docs/examples/`,
    e.g. `util/docs/examples/PDO/Table.md`.
