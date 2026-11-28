# AGENTS.md

# AI Agent Project Guidelines

## 1. Token and Context Efficiency

The primary objective is to minimize unnecessary context consumption and token usage without sacrificing code quality, correctness, security, or project conventions.

### 1.1 Excluded Directories

Do NOT recursively scan, index, read, or analyze the following directories unless explicitly required:

- `node_modules/`
- `vendor/`
- `.git/`
- `.next/`
- `.nuxt/`
- `.output/`
- `dist/`
- `build/`
- `coverage/`
- `storage/framework/`
- `bootstrap/cache/`

These directories contain third-party dependencies, generated files, caches, or build artifacts.

### 1.2 Excluded Files

Avoid reading the following files unless directly relevant to the task:

- `package-lock.json`
- `pnpm-lock.yaml`
- `yarn.lock`
- `composer.lock`
- `*.min.js`
- `*.min.css`
- `*.map`
- Large log files
- Generated and compiled assets

Exception: Dependency manifests and lockfiles may be inspected when investigating package versions, dependencies, installation issues, or configuration.

### 1.3 Search Strategy

- Use targeted searches instead of recursively scanning the entire project.
- Always exclude irrelevant dependency and generated directories from recursive searches.
- Prioritize project-owned source code.
- Read only files relevant to the current task.
- Avoid repeatedly reading files already inspected.
- Read only relevant sections of large files.
- Do not dump entire files when a small section is sufficient.
- Reuse existing context and previous findings whenever possible.
- Do not investigate unrelated code or functionality.
- Do not generate unnecessary reports, summaries, or documentation.

### 1.4 Dependency Inspection Exceptions

Inspect `vendor/` or `node_modules/` only when:

- Debugging a specific third-party dependency.
- Verifying a package API or implementation.
- Investigating dependency-related errors.
- Checking framework or package behavior that cannot be established through documentation.
- Explicitly requested by the user.

When inspection is necessary, examine only the specific package or files involved.

### 1.5 Command Efficiency

- Prefer commands that return concise and relevant output.
- Avoid commands that dump huge directory listings or entire file contents.
- Use appropriate filters, exclusions, and output limits.
- Do not repeat successful diagnostic commands without a reason.
- Do not run unnecessary builds, tests, or verification commands.

### 1.6 Priority and Safety

Token efficiency must NEVER override:

- Existing project rules.
- Security requirements.
- Correctness.
- Required documentation checks.
- Relevant tests.
- User instructions.
- Required framework and package conventions.

---

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

## Foundational Context

This application is a Laravel application running on PHP 8.3. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:

- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available in `**/skills/**`.

You MUST activate the relevant skill whenever working in that domain. Do not wait until you encounter a problem.

Only activate skills relevant to the current task. Avoid reading unrelated skill documentation.

## Conventions

- Follow all existing code conventions used in this application.
- When creating or editing files, inspect relevant sibling files to understand their structure, implementation, and naming conventions.
- Use descriptive names for variables and methods.
- For example, use `isRegisteredForDiscounts` instead of `discount()`.
- Check for existing components to reuse before creating new ones.

## Verification Scripts

- Do not create verification scripts or use Tinker when existing tests cover the functionality and prove it works.
- Unit and feature tests are more important.

## Application Structure & Architecture

- Follow the existing directory structure.
- Do not create new base folders without approval.
- Do not change application dependencies without approval.

## Frontend Bundling

If a frontend change does not appear in the UI, or an `Unable to locate file in Vite manifest` error occurs:

- Run `npm run build`, or
- Ask the user to run `npm run dev` or `composer run dev`.

## Documentation Files

Only create documentation files when explicitly requested by the user.

=== boost rules ===

# Laravel Boost

## Tools

Laravel Boost is an MCP server with tools designed specifically for this application.

Prefer Boost tools over manual alternatives such as shell commands or file reads when applicable.

- Use `database-query` for read-only database queries instead of writing raw SQL in Tinker.
- Use `database-schema` to inspect table structures before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port before sharing project URLs.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful; ignore old entries.

## Searching Documentation (IMPORTANT)

Use `search-docs` before making changes that depend on Laravel ecosystem APIs, behavior, configuration, or version-specific syntax.

Skip documentation searches for copy-only edits and changes where package documentation is irrelevant.

Reuse sufficient documentation results already available in the current context instead of searching again.

Guidelines:

- Pass a `packages` array when relevant packages are known.
- Use multiple broad, topic-based queries.
- Expect the most relevant results first.
- Do not add package names to queries because package information is already shared.

Examples:

- `['rate limiting', 'routing rate limiting', 'routing']`
- `['test resource table', 'resource testing']`

## Search Syntax

1. Use words for auto-stemmed AND logic.
2. Use quoted phrases for exact phrase matching.
3. Combine words and phrases for mixed queries.
4. Use multiple queries for OR logic.

## Project Rules

This project contains committed, area-grouped rules in `.ai/rules` when that directory exists.

Before entering plan mode or creating/editing any file, you MUST:

1. Check whether `.ai/rules/` exists.
2. Open `@.ai/rules/index.md`.
3. Read every rule file whose globs cover the paths in scope.
4. Run `grep -rin 'keyword' .ai/rules` to catch additional relevant rules that path matching might miss.
5. Follow every applicable rule before writing code.

Do not write code until all applicable rules have been read.

If `.ai/rules` does not exist, continue without it.

For efficiency, inspect only relevant rule files and use targeted keyword searches. Do not recursively scan unrelated directories.

## Artisan

Run Artisan commands directly through the command line.

Examples:

- `php artisan route:list`
- `php artisan config:show app.name`
- `php artisan config:show database.default`

Use `php artisan list` to discover available commands.

Use `php artisan [command] --help` to inspect command parameters.

Filter route inspection when appropriate:

- `--method=GET`
- `--name=users`
- `--path=api`
- `--except-vendor`
- `--only-vendor`

## Tinker

- Use Tinker for debugging and testing PHP in application context when appropriate.
- Do not create models without user approval.
- Prefer existing Artisan commands over custom Tinker code.
- Prefer tests with factories instead of unnecessary manual debugging.
- Always use single quotes around the Tinker command to prevent shell expansion.

Example:

`php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion where appropriate.

Example:

`public function __construct(public GitHub $github) { }`

- Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations.
- Use type hints for all method parameters.
- Use TitleCase for Enum keys.

Examples:

- `FavoritePerson`
- `BestLake`
- `Monthly`

- Prefer PHPDoc blocks over inline comments.
- Only add inline comments for exceptionally complex logic.
- Use array shape definitions in PHPDoc where appropriate.

=== deployments rules ===

# Deployment

Laravel can be deployed using Laravel Cloud, which supports deployment and scaling of production Laravel applications.

Activate the `deploying-to-cloud` skill whenever:

- Deploying to Laravel Cloud.
- Configuring Laravel Cloud environments or resources.
- Using the Laravel Cloud CLI.
- Troubleshooting Laravel Cloud deployments.

=== tests rules ===

# Test Enforcement

- Add or update tests for behavior and logic changes when tests provide meaningful regression coverage.
- Pure copy, styling, and layout-only changes do not require new or updated tests.
- When test coverage applies, run the affected tests and ensure they pass.
- Test changed behavior and important failure modes.
- Do not add unnecessary tests beyond the scope of the change.
- Read the `testing-best-practices` skill before writing tests.

=== inertia-laravel/core rules ===

# Inertia

- Inertia creates fully client-side rendered SPAs while leveraging existing server-side patterns.
- Components live in `resources/js/Pages`, unless configured differently in `vite.config.js`.
- Use `Inertia::render()` for server-side routing instead of Blade views.
- ALWAYS use the `search-docs` tool for version-specific Inertia documentation and updated code examples.
- Activate `inertia-vue-development` when working with Inertia Vue client-side patterns.

=== inertia-laravel/v2 rules ===

# Inertia v2

Use supported features from Inertia v1 and v2.

Check documentation before making changes to ensure the correct implementation.

Available features include:

- Deferred props.
- Infinite scroll.
- Merging props.
- Polling.
- Prefetching.
- Once props.
- Flash data.

When using deferred props, provide an empty state with a pulsing or animated skeleton.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new Laravel files such as migrations, controllers, and models.
- Use `php artisan make:class` for generic PHP classes.
- Check available Artisan commands with `php artisan list`.
- Check command parameters using `php artisan [command] --help`.
- Pass `--no-interaction` to Artisan commands to prevent interactive prompts.
- Pass the appropriate options for the intended operation.

## Model Creation

When creating new models, create useful factories and seeders too.

Ask the user if they need additional related files. Check `php artisan make:model --help` for available options.

## APIs & Eloquent Resources

- For APIs, default to Eloquent API Resources and API versioning.
- If existing API routes do not follow this convention, follow the existing application architecture.

## URL Generation

- Prefer named routes and the `route()` function when generating links to other pages.

## Testing

- Use factories when creating models for tests.
- Check existing factory states before manually setting up test data.
- Follow existing Faker conventions.

Examples:

- `$this->faker->word()`
- `fake()->randomDigit()`

- Use `php artisan make:test [options] {name}` to create feature tests.
- Pass `--unit` for unit tests.
- Most tests should be feature tests.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If PHP files have been modified, run `vendor/bin/pint --dirty --format agent` before finalizing changes.
- Do not run `vendor/bin/pint --test --format agent`.
- Use Pint to fix formatting issues.

=== pest/core rules ===

# Pest

- This project uses Pest.
- Create tests using `php artisan make:test --pest {name}`.
- Do not include the test suite directory in `{name}`.

Example:

Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.

- Read the `testing-best-practices` skill before writing tests.
- Do not delete existing tests or test files without user approval.

## Running Tests

- Run the narrowest test set that covers the change.

Example:

`php artisan test --compact path/to/SomeFeatureTest.php`

You may also use:

`php artisan test --compact --filter=testName`

- Rerun a test after every change to that test.
- Use `vendor/bin/pest` directly when appropriate. It accepts the same file paths and filters.

=== inertia-vue/core rules ===

# Inertia + Vue

- Vue components must have a single root element.
- Activate `inertia-vue-development` when working with Inertia Vue client-side patterns.

</laravel-boost-guidelines>

---

# Final Agent Behavior

Before starting any task:

1. Understand the user's requested outcome.
2. Identify only the relevant files and application areas.
3. Read applicable project rules and activate relevant skills.
4. Check existing implementation before introducing new code.
5. Use targeted searches and avoid unnecessary dependency inspection.
6. Implement the smallest appropriate change.
7. Run relevant tests and formatting checks when required.
8. Report completed work, changed files, and verification results concisely.

Never sacrifice correctness, security, or mandatory project rules for token savings.
