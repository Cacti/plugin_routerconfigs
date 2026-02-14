# GitHub Copilot Instructions

## Priority Guidelines

When generating code for this repository:

1. **Version Compatibility First**: Match only versions and APIs evidenced in this repo.
2. **Context Files First**: If `.github/agents/*` files are added later, prioritize them over these defaults.
3. **Codebase Patterns Second**: If no context file applies, copy patterns from neighboring files.
4. **Architectural Consistency**: Preserve plugin boundaries and Cacti integration points.
5. **Consistency Over Novelty**: Prefer existing repo patterns over external or newer style guidance.

## Detected Technology and Version Constraints

### Confirmed from Repository Metadata

- **Project type**: Cacti plugin (`routerconfigs`) written in PHP.
- **Plugin version**: `1.7` (from `INFO`).
- **Cacti compatibility**: `compat = 1.2.23` (from `INFO`).
- **Versioning style**: SemVer-like in `CHANGELOG.md` (examples: `1.7`, `1.6.1`, `1.5.3`).

### PHP Compatibility (Observed, Not Fully Pinned)

- No `composer.json` or other explicit PHP engine constraint exists in this repository.
- Changelog explicitly mentions fixes for **PHP 8.1** and **PHP 8.2** warnings.
- Code includes `#[AllowDynamicProperties]` in `classes/PHPConnection.php` to handle dynamic-property deprecations.
- **Instruction**: Keep generated PHP compatible with Cacti plugin runtime and the patterns already used here; do not introduce language features not already present in this codebase.

### Libraries/Runtime Dependencies (Observed)

- Cacti plugin APIs and globals (`api_plugin_register_hook`, `db_*`, `read_config_option`, `cacti_log`, etc.).
- Optional SSH extension at runtime (`ssh2_*` checks in `classes/PHPSsh.php`).
- Vendored text diff utilities under `Text/` (Horde-style classes and renderers).

## Architecture and Boundaries

This repository follows a **monolithic plugin with layered concerns**:

- **Entry pages/controllers**: top-level `router-*.php` files route actions and render pages.
- **Core business logic**: `include/functions.php`.
- **Configuration and field maps**: `include/arrays.php`, `include/constants.php`.
- **Transport/connection abstractions**: `classes/*` (`PHPConnection`, `PHPSsh`, `PHPTelnet`, `PHPScp`, `PHPSftp`).
- **Plugin lifecycle and schema**: `setup.php` (hooks, install/upgrade DB schema).
- **Localization**: `__()` / `__esc()` with domain `routerconfigs`, translation files in `locales/`.

### Architectural Rules

- Keep plugin wiring in `setup.php`; do not move hook registration logic into page files.
- Keep request handling in `router-*.php` and reusable logic in `include/functions.php` or `classes/`.
- Preserve DB table ownership under `plugin_routerconfigs_*`.
- Reuse existing Cacti helper APIs rather than introducing custom framework layers.

## Established Code Patterns

## 1) File Structure and Includes

- Use `include` / `include_once` / `require_once` with existing relative patterns.
- Most pages start with:
  - `chdir('../../');`
  - `include('./include/auth.php');`
  - plugin includes from `__DIR__`.

## 2) Request Routing and Actions

- Route with `set_default_action();` and `switch (get_request_var('action'))`.
- Keep action handlers as plain functions in the same file (existing pattern).

## 3) Input Validation and Sanitization

- Use explicit validation blocks marked by:
  - `// ================= input validation =================`
  - `// ====================================================`
- Prefer Cacti request helpers:
  - `get_filter_request_var(...)`
  - `input_validate_input_number(...)`
  - `sanitize_unserialize_selected_items(...)`
  - `sanitize_search_string` callbacks where used.

## 4) Database Access

- Prefer prepared variants where pattern exists:
  - `db_fetch_row_prepared`, `db_fetch_assoc_prepared`, `db_fetch_cell_prepared`, `db_execute_prepared`.
- Follow existing SQL style (multiline SQL strings and Cacti DB helpers).
- Keep schema creation/upgrades in `setup.php` via `api_plugin_db_table_create` and `db_column_exists` guards.

## 5) UI/Output and Escaping

- Follow Cacti page wrapper pattern (`top_header()`/`general_header()`, `bottom_footer()`).
- Use Cacti HTML helpers (`html_start_box`, `form_selectable_cell`, `html_nav_bar`, etc.).
- Escape output using established functions (`html_escape`, `html_escape_request_var`, `htmlspecialchars`).

## 6) Logging and Messaging

- Use `plugin_routerconfigs_log()` for plugin-specific logs.
- Use `cacti_log()` for environment-level logging where already used.
- Use `raise_message()` for user-facing result notifications.
- Keep existing severity wording patterns (`DEBUG`, `NOTICE`, `WARNING`, `ERROR`, `FATAL`, `STATS`).

## 7) Internationalization

- Wrap user-facing text in `__()` or `__esc()`.
- Always use text domain `'routerconfigs'`.
- Keep translation-aware strings and avoid hardcoded UI text.

## 8) OOP Conventions in This Repo

- OOP is used mainly in connection classes; procedural style dominates controllers and page handlers.
- Keep class naming and inheritance patterns consistent (`PHP*` classes, explicit `Connect()`/`Disconnect()` methods).
- Do not introduce namespaces or strict typing unless existing file patterns in the touched area already use them.

## Security and Reliability Patterns to Preserve

- Validate/sanitize all request input before use.
- Prefer prepared SQL helpers for dynamic inputs.
- Preserve CLI-only guard pattern in `router-download.php` for command-line scripts.
- Preserve defensive checks for optional runtime dependencies (e.g., `ssh2` extension).
- Continue masking sensitive values in logs (e.g., password masking helpers).

## Documentation Requirements (Repository-Observed)

- Match current documentation style:
  - File-level header blocks are common in primary plugin files.
  - Inline comments are used sparingly, mostly for intent and validation sections.
- Do not add large doc blocks where surrounding code does not use them.
- Keep comments short and practical.

## Testing Guidance (Repository-Observed)

- No dedicated automated test suite directories or test framework configuration are present in this repo.
- Validate changes with focused manual checks and existing runtime flows:
  - page actions in `router-*.php`
  - CLI flow in `router-download.php`
  - install/upgrade paths in `setup.php`.
- If adding tests in the future, place them in a clearly separated structure and document the chosen framework first.

## Version Control and Changelog Guidance

- Follow existing changelog format in `CHANGELOG.md`.
- Keep version numbers SemVer-like to match current history.
- Document fixes/features in concise bullet style consistent with existing entries.

## Copilot Operational Instructions

Before generating or modifying code:

1. Scan the target file and nearby files for established patterns.
2. Reuse existing helper functions/APIs before creating new abstractions.
3. Keep changes minimal and localized.
4. Do not introduce new frameworks, coding paradigms, or build systems.
5. If version compatibility is unclear, choose the most conservative option consistent with existing code.

## Project-Specific “Do/Don’t”

### Do

- Follow Cacti plugin conventions used in this repo.
- Keep i18n and escaping consistent with existing usage.
- Preserve database schema naming and upgrade guard patterns.
- Mirror naming and formatting conventions from sibling files.

### Don’t

- Don’t add assumptions about undeclared toolchain versions.
- Don’t introduce modern PHP features that are not already used nearby.
- Don’t bypass Cacti request validation, DB helper, or logging APIs.
- Don’t change architectural boundaries (controller pages vs. shared logic vs. classes).

## Concrete Reference Files

Use these files as primary exemplars when generating code:

- Plugin lifecycle/hooks/schema: `setup.php`
- Shared logic: `include/functions.php`
- Config arrays/constants: `include/arrays.php`, `include/constants.php`
- Controller/action pages: `router-devices.php`, `router-backups.php`, `router-accounts.php`, `router-devtypes.php`, `router-compare.php`
- CLI flow and argument parsing: `router-download.php`
- Connection class design: `classes/PHPConnection.php`, `classes/PHPSsh.php`, `classes/PHPTelnet.php`, `classes/PHPScp.php`, `classes/PHPSftp.php`
