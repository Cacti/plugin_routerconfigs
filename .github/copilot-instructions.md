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
routerconfigs/               # Repository root (install to plugins/routerconfigs/ in Cacti)
├── classes/                   # PHPConnection, PHPSsh, PHPTelnet, PHPScp, PHPSftp transport classes
├── include/                      # functions.php (core logic), arrays.php/constants.php (config/field maps)
├── locales/                         # Internationalization files
├── tests/                              # Test suite
├── Text/                                  # Vendored diff utilities (Horde-style classes/renderers)
├── router-devices.php                       # Device administration
├── router-accounts.php                        # Credential/account administration
├── router-backups.php                           # Backup listing/administration
├── router-compare.php                             # Config diff/compare view
├── router-devtypes.php                              # Device type administration
├── router-download.php                                # CLI-only backup download/export flow
├── diff.css / HordeTextInclude.php                       # Diff rendering assets
├── INFO                                                     # Plugin metadata (name, version, compat)
├── README.md
└── setup.php                                                  # Plugin install/uninstall/upgrade hooks
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
Most pages start with `chdir('../../');` then `include('./include/auth.php');`, followed by plugin includes from `__DIR__`. Route actions with `set_default_action();` and `switch (get_request_var('action'))`, keeping action handlers as plain functions in the same file.

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

Keep schema creation/upgrades in `setup.php` via `api_plugin_db_table_create()` and `db_column_exists()` guards.

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
2. Keep request handling in `router-*.php` and reusable logic in `include/functions.php` or `classes/`.
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

## References

- [Cacti main repo](https://github.com/Cacti/cacti/tree/1.2.x)
- [Cacti Documentation](https://www.github.com/Cacti/documentation)
- `README.md` for feature descriptions
- `CHANGELOG.md` for version history
