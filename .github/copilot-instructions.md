# GitHub Copilot Instructions

## Priority Guidelines

When generating code for this repository:

1. **Version Compatibility**: This is a Cacti plugin (`routerconfigs`, version 1.7) targeting Cacti 1.2.23+
2. **Context Files**: Prioritize patterns and standards defined in this file (`.github/copilot-instructions.md`)
3. **Codebase Patterns**: When context files don't provide specific guidance, scan the codebase for established patterns
4. **Architectural Consistency**: Maintain plugin-based architecture extending Cacti core
5. **Code Quality**: Prioritize security, maintainability, and compatibility in all generated code

## Technology Stack

### Core Technologies
- **PHP**: Compatible with Cacti 1.2.x supported versions; `#[AllowDynamicProperties]` is used in `classes/PHPConnection.php` to handle PHP 8.2 dynamic-property deprecations
- **Platform**: Cacti Plugin Architecture — backs up and diffs router/switch configurations
- **Database**: MySQL/MariaDB
- **Connectivity**: SSH/Telnet/SCP/SFTP device connections via optional `ssh2` PHP extension

### Key Dependencies
- Cacti core framework (`api_plugin_*`, `db_*`, `read_config_option()`, `cacti_log()`)
- Vendored Horde-style text diff utilities under `Text/`
- Optional runtime dependency: `ssh2` extension (guarded with defensive checks in `classes/PHPSsh.php`)

## Project Structure

```
routerconfigs/          # Repository root (install to plugins/routerconfigs/ in Cacti)
├── classes/            # PHPConnection, PHPSsh, PHPTelnet, PHPScp, PHPSftp transport classes
├── includes/           # database.php (schema), functions.php (core logic), arrays.php/constants.php (config/field maps), HordeText.php (Horde diff loader)
├── locales/            # Internationalization files
├── tests/              # Test suite
├── Text/               # Vendored diff utilities (Horde-style classes/renderers)
├── router-devices.php  # Device administration
├── router-accounts.php # Credential/account administration
├── router-backups.php  # Backup listing/administration
├── router-compare.php  # Config diff/compare view
├── router-devtypes.php # Device type administration
├── router-download.php # CLI-only backup download/export flow
├── css/                # diff.css (diff-rendering stylesheet)
├── INFO                # Plugin metadata (name, version, compat)
├── README.md
└── setup.php           # Plugin install/uninstall/upgrade hooks
```

## Naming Conventions

### Function Names
- Hook/lifecycle functions use the `routerconfigs_` prefix: `routerconfigs_show_tab()`, `routerconfigs_config_arrays()`, `routerconfigs_poller_bottom()`.
- Plugin-specific logging uses `plugin_routerconfigs_log()`.
- Match the existing prefix used by the function you are editing; do not introduce a new naming scheme.

### Database Tables
All plugin tables are prefixed `plugin_routerconfigs_`.

### Connection Classes
Class naming follows the `PHP*` pattern (`PHPConnection`, `PHPSsh`, `PHPTelnet`, `PHPScp`, `PHPSftp`) with explicit `Connect()`/`Disconnect()` methods — OOP is used only for these transport classes; procedural style dominates controllers and page handlers. Do not introduce namespaces or strict typing unless the touched area already uses them.

## Code Style

### Indentation and Formatting
- **Tabs**: Use tabs (not spaces) for indentation throughout all PHP files.
- **Braces**: Opening brace on the same line for functions and control structures.
- **Spacing**: Space after control structure keywords (`if`, `foreach`, `while`).

### File Structure and Includes
Most pages start with `chdir('../../');` then `require('./include/auth.php');` (Cacti core's include dir), followed by plugin includes from `__DIR__ . '/includes/'`. Route actions with `set_default_action();` and `switch (get_request_var('action'))`, keeping action handlers as plain functions in the same file.

### Input Validation Blocks
Mark explicit validation sections with the existing comment convention:
```php
// ================= input validation =================
...
// ====================================================
```

`get_filter_request_var()` (and its `gfrv()` shorthand, where available) called with only the
`$name` argument (no regex/filter as the 2nd/3rd argument) already validates the value as numeric
and returns it as a **string** -- it does not return an int, and it halts execution if the request
value is not numeric. Because of this, do NOT cast its output to `(int)` when the result is only
used for string output (e.g. `print`/`echo`, string concatenation, embedding in HTML/JS); the cast
is redundant. Only cast when the value is genuinely used in an integer/numeric context (e.g.
arithmetic, strict `===` comparisons).

### File Headers
ALL PHP files MUST include the standard GPL v2 license header used throughout this repository (see `setup.php`), crediting "The Cacti Group".

## Security Standards

### SQL Query Security
Prefer prepared variants where the pattern exists: `db_fetch_row_prepared()`, `db_fetch_assoc_prepared()`, `db_fetch_cell_prepared()`, `db_execute_prepared()`.

```php
// CORRECT
db_fetch_row_prepared('SELECT * FROM plugin_routerconfigs_devices WHERE id = ?', array($id));

// WRONG
db_fetch_row("SELECT * FROM plugin_routerconfigs_devices WHERE id = $id");
```

### Input Validation
Use Cacti request helpers: `get_filter_request_var()`, `input_validate_input_number()`, `sanitize_unserialize_selected_items()`, or existing `sanitize_search_string` callbacks.

### Output Escaping
Escape output using established functions (`html_escape()`, `html_escape_request_var()`, `htmlspecialchars()`).

### Credential Handling
Continue masking sensitive values (device passwords/credentials) in logs, matching the existing password-masking helpers; never log raw credentials.

### Optional Extension Guards
Preserve defensive checks for optional runtime dependencies (e.g., `ssh2` extension availability) before attempting to use them.

## Database Operations

All schema management lives in `includes/database.php` (the thold model), not in `setup.php`. `setup.php`'s
install/upgrade paths `require_once($config['base_path'] . '/plugins/routerconfigs/includes/database.php')` and
delegate. Each of the four `plugin_routerconfigs_*` tables is defined once in a `routerconfigs_*_table_data()`
helper (primaries/keys as arrays) and created via `api_plugin_db_table_create('routerconfigs', ...)`. On a
version change, `routerconfigs_check_upgrade()` runs the historical version-gated column renames/drops/data
fix-ups first (these preserve data and cannot be expressed by `db_update_table()`), then calls
`routerconfigs_upgrade_tables()` to refresh each table via `db_update_table()`, and updates the full
`plugin_config` row. Never write raw `CREATE TABLE`, and prefer `db_update_table()` over new
`ALTER TABLE ... ADD COLUMN` for adding columns to plugin tables.

## Internationalization

Wrap user-facing text in `__()` or `__esc()`, always with the text domain `'routerconfigs'`.

## Plugin Architecture

### Plugin Hooks
Register hooks in `setup.php`:

```php
api_plugin_register_hook('routerconfigs', 'top_header_tabs',       'routerconfigs_show_tab', 'setup.php');
api_plugin_register_hook('routerconfigs', 'top_graph_header_tabs', 'routerconfigs_show_tab', 'setup.php');
api_plugin_register_hook('routerconfigs', 'config_arrays',         'routerconfigs_config_arrays',        'setup.php');
api_plugin_register_hook('routerconfigs', 'draw_navigation_text',  'routerconfigs_draw_navigation_text', 'setup.php');
api_plugin_register_hook('routerconfigs', 'config_settings',       'routerconfigs_config_settings',      'setup.php');
api_plugin_register_hook('routerconfigs', 'poller_bottom',         'routerconfigs_poller_bottom',        'setup.php');
api_plugin_register_hook('routerconfigs', 'page_head',             'routerconfigs_page_head',            'setup.php');

api_plugin_register_realm('routerconfigs', 'router-devices.php,router-accounts.php,router-backups.php,router-compare.php,router-devtypes.php', __('Router Configs', 'routerconfigs'), 1);
```

### Logging
Use `plugin_routerconfigs_log()` for plugin-specific logs and `cacti_log()` for environment-level logging where already used; use `raise_message()` for user-facing result notifications, keeping existing severity wording (`DEBUG`, `NOTICE`, `WARNING`, `ERROR`, `FATAL`, `STATS`).

## Best Practices

1. Keep plugin wiring in `setup.php`; do not move hook registration into page files.
2. Keep request handling in `router-*.php` and reusable logic in `includes/functions.php` or `classes/`.
3. Preserve DB table ownership under `plugin_routerconfigs_*`.
4. Always mask credential values in logs.

## Common Pitfalls to Avoid

```php
// WRONG - logging a raw credential
cacti_log("Connecting with password $password");

// CORRECT - mask sensitive values
cacti_log('Connecting with password ' . str_repeat('*', strlen($password)));
```

## Version Control

Follow the existing `CHANGELOG.md` format; keep version numbers SemVer-like to match current history.

## CI & Dependency Baselines

- Do not commit a `composer.json` or `composer.lock` in this plugin's own repo root — the shared CI workflow installs Pest/dev dependencies into Cacti's own Composer-managed vendor tree (checked out alongside the plugin). Use Cacti's `composer.json`, not a plugin-local one.
- Do not add a plugin-local `.phpstan.neon`/`phpstan.neon` or `.php-cs-fixer.php`/`.php-cs-fixer.dist.php` — lint/static-analysis steps run against Cacti's own config from the Cacti core checkout, targeting this plugin's directory. Use the Cacti version, not a plugin-local config.
- Prefer Cacti's `cacti_count()`/`cacti_sizeof()` wrappers over the raw `count()`/`sizeof()` builtins in new or edited code.

## Internationalization (i18n)

- Translatable strings are managed with GNU gettext via `locales/build_gettext.sh`. `locales/po/cacti.pot` is the source template; Weblate owns syncing the per-language `.po`/`.mo` files from it.
- **Never commit the per-language `.po` or compiled `.mo` files** (`locales/po/*.po`, `locales/LC_MESSAGES/*.mo`) in a plugin PR. Weblate is the sole owner of those catalogs, and regenerating them here produces spurious diffs and merge conflicts. `locales/po/cacti.pot` is the ONLY translation artifact a PR may add or modify.
- When a pull request adds or changes a string wrapped in `__()`/`__n()`/`__esc()`/`__x()`/`__xn()`/`__gettext()`, run `locales/build_gettext.sh` before pushing and stage `locales/po/cacti.pot` only. `build_gettext.sh` also rewrites the `.po`/`.mo` files as a side effect; revert those before committing (`git checkout -- locales/po/*.po locales/LC_MESSAGES`), or run only the `xgettext` step that targets `cacti.pot`.

## References

- [Cacti main repo](https://github.com/Cacti/cacti/tree/1.2.x)
- [Cacti Documentation](https://www.github.com/Cacti/documentation)
- `README.md` for feature descriptions
- `CHANGELOG.md` for version history

## Security & Quality Conventions

These conventions apply across the Cacti plugin fleet and should be followed whenever touching
existing code or adding new code, not just in dedicated cleanup passes:

- **No hardcoded third-party hosts.** Never hardcode a third-party IP address, hostname, or URL
  in plugin code (even for tooling/download helpers). Expose it as a plugin setting instead, with
  secure-by-default values (e.g. an SSL-verification setting that defaults to verify-on).
- **Prepared statements over `db_qstr()`.** Build dynamic `WHERE` clauses using the
  `$sql_where`/`$sql_params` prepared-statement pattern, not string concatenation via `db_qstr()`.
- **Use `html_escape_request_var()`.** Prefer it over the `html_escape(get_request_var(...))` call
  chain.
- **Harden `unserialize()`.** Always pass `['allowed_classes' => false]` as the second argument.
- **i18n text domain.** Every `__()`/`__esc()` call must include this plugin's text domain as the
  final argument, except when deliberately comparing against a literal, untranslated Cacti-core
  label.
- **File inclusion uses `require`/`require_once`.** Always use `require`/`require_once` (never
  `include`/`include_once`) so a missing dependency fails fast and loudly. This plugin's own library
  directory is `includes/` (note the plural: `includes/functions.php`, `includes/database.php`,
  `includes/arrays.php`, `includes/constants.php`); reference plugin files from that path. Cacti
  core's own `./include/auth.php`/`./include/global.php` keep the core (singular) path.
- **Plugin schema management.** Keep every schema function (table definitions, create, upgrade) in
  `includes/database.php` (the thold model), required from `setup.php`. Create with
  `api_plugin_db_table_create()`; refresh an existing plugin table with `db_update_table($table, $data)`
  from the SAME definition (create fallback when missing). Historical column renames/drops that
  `db_update_table()` can not express stay as guarded pre-steps. Both are idempotent.
- **Plugin upgrade bookkeeping.** On a version change, update the FULL `plugin_config` row
  (`version`, `name`, `author`, `webpage`) from the INFO file, not just the version column.
- **PHPDoc shape.** Every function gets a PHPDoc block: a one-line description, a blank comment
  line, `@param` lines, a blank comment line, then `@return`. Infer parameter/return types from
  actual usage; don't change the function's real type-hints in the same pass (let static analysis
  flag mismatches separately). Skip vendored third-party library files.

## File manifest & upgrade pruning

The plugin ships a root `manifest.json` with three arrays: `tombstones` (files/directories older versions shipped that have since moved or been removed), `expected` (the top-level files and directories that ship today, directories written with a trailing `/`), and `whitelist` (paths holding user data that must never be touched). Keep `expected` current: CI runs `tests/bin/validate-manifest.php`, which fails on any drift between `expected` and the real top-level tree (it ignores `tests/`, `phpunit.xml`, `.git*`, `.md*`, and whitelisted paths). Custom customer CSS/theme files belong in `expected`, and stylesheets live in `css/` (not `themes/`). On upgrade, `plugin_routerconfigs_prune_files()` deletes the tombstoned paths, the dev-only `tests/` tree, and the `phpunit.xml` test config, leaves `whitelist`, `.git*`, and `.md*` alone, and logs (without removing) any top-level entry the manifest does not account for. As a safety measure it refuses any tombstone that resolves outside the plugin directory (a tampered manifest.json) and logs a warning for any file or directory it cannot remove. When you move or delete a shipped file, add its old path to `tombstones` and update `expected` in the same change.
