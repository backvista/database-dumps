# Database Dumps Package

[![Tests](https://img.shields.io/badge/tests-passing-brightgreen)]()
[![PHP Version](https://img.shields.io/badge/php-%5E7.4%20%7C%20%5E8.0-blue)]()
[![License](https://img.shields.io/badge/license-MIT-blue.svg)]()

Framework-agnostic PHP package for managing database dumps with SQL exports/imports, supporting PostgreSQL and MySQL.

## Features

- **Framework-agnostic**: Works with Symfony, Laravel, and any PHP project
- **Multiple databases**: PostgreSQL and MySQL support with platform abstraction
- **Smart batching**: Automatic INSERT batching (1000 rows per statement)
- **Transaction safety**: Automatic rollback on errors
- **Production guard**: Prevents accidental imports in production
- **Before/After hooks**: Execute custom SQL scripts before/after import
- **Flexible configuration**: YAML-based export rules (full/partial)
- **Sequence reset**: Automatic primary key sequence/auto-increment reset
- **Well tested**: 98 tests covering all core functionality

## Installation

```bash
composer require backvista/database-dumps
```

## Quick Start

### Symfony Integration

1. The package bundle is auto-registered via Symfony Flex.

2. Create configuration file `config/dump_config.yaml`:

```yaml
full_export:
  users:
    - users
    - roles

partial_export:
  clients:
    clients:
      limit: 1000
      order_by: created_at DESC
```

3. Export dumps:

```bash
php bin/console app:dbdump:export all
```

4. Import dumps:

```bash
php bin/console app:dbdump:import
```

### Laravel Integration

1. The service provider is auto-discovered. The file `database/dump_config.yaml` is created automatically on first run. If not auto-discovered, register manually in `config/app.php`:

```php
'providers' => [
    // ...
    BackVista\DatabaseDumps\Bridge\Laravel\DatabaseDumpsServiceProvider::class,
],
```

2. Edit `database/dump_config.yaml` (same format as Symfony).

3. Export dumps:

```bash
php artisan dbdump:export all
```

4. Import dumps:

```bash
php artisan dbdump:import
```

## Architecture

### Core Components

- **Service Layer**: Business logic (Dumper, Importer, Generator, Parser)
- **Contracts**: Interfaces for database, filesystem, configuration, platform
- **Adapters**: Database-specific implementations (Doctrine DBAL, Laravel DB)
- **Platform**: SQL dialect abstraction (PostgresPlatform, MySqlPlatform)
- **Bridges**: Framework integrations (Symfony, Laravel)
- **Security**: Environment checks and production guards

### Platform Abstraction

The package uses `DatabasePlatformInterface` to generate SQL compatible with each DBMS:

- **PostgresPlatform**: Double quotes for identifiers, `CASCADE` in TRUNCATE, `setval()` for sequences
- **MySqlPlatform**: Backticks for identifiers, `FOREIGN_KEY_CHECKS` around TRUNCATE, `AUTO_INCREMENT` reset

Platform is automatically detected from the database connection.

## IDE Support (JSON Schema)

The package includes a JSON Schema for `dump_config.yaml` at `resources/dump_config.schema.json`. This provides autocompletion, validation, and documentation hints in PHPStorm and other IDEs.

### Option 1: YAML comment (recommended)

Add this line at the top of your `dump_config.yaml`:

```yaml
# yaml-language-server: $schema=../vendor/backvista/database-dumps/resources/dump_config.schema.json
```

> For Symfony projects use the path `../vendor/backvista/database-dumps/resources/dump_config.schema.json`, for Laravel — adjust relative to your `database/` directory.

### Option 2: PHPStorm manual mapping

1. Open **Settings > Languages & Frameworks > Schemas and DTOs > JSON Schema Mappings**
2. Add a new mapping:
   - **Schema file**: `vendor/backvista/database-dumps/resources/dump_config.schema.json`
   - **File path pattern**: `dump_config.yaml`

## Configuration

### Symfony Configuration

#### 1. Create Configuration File

Create `config/dump_config.yaml` in your Symfony project root:

```yaml
# config/dump_config.yaml
full_export:
  public:
    - users
    - roles
    - settings

  system:
    - logs

partial_export:
  clients:
    clients:
      limit: 1000
      order_by: created_at DESC
      where: "is_active = true"

    orders:
      limit: 5000
      order_by: created_at DESC
```

#### 2. Directory Structure

```
your-symfony-project/
├── config/
│   └── dump_config.yaml
├── database/
│   ├── before_exec/
│   │   └── 01_prepare.sql
│   ├── dumps/
│   │   ├── public/
│   │   │   ├── users.sql
│   │   │   └── roles.sql
│   │   └── clients/
│   │       ├── clients.sql
│   │       └── orders.sql
│   └── after_exec/
│       └── 01_finalize.sql
```

#### 3. Service Configuration

The package auto-registers via Symfony Flex. If not, add to `config/bundles.php`:

```php
return [
    // ...
    BackVista\DatabaseDumps\Bridge\Symfony\DatabaseDumpsBundle::class => ['all' => true],
];
```

You also need to set the `database_dumps.platform` parameter (e.g. in `services.yaml`):

```yaml
parameters:
    database_dumps.platform: 'postgresql'  # or 'mysql'
```

#### 4. Usage

```bash
# Export all tables from config
php bin/console app:dbdump:export all

# Export specific table
php bin/console app:dbdump:export public.users

# Export with schema filter
php bin/console app:dbdump:export all --schema=public

# Import all dumps
php bin/console app:dbdump:import

# Import with options
php bin/console app:dbdump:import --skip-before --skip-after
php bin/console app:dbdump:import --schema=public
```

### Laravel Configuration

#### 1. Configuration

The file `database/dump_config.yaml` is created automatically on first run. Edit it with your export rules (same format as Symfony).

To customize the config path or project directory, publish the PHP config:

```bash
php artisan vendor:publish --tag=database-dumps-config
```

This creates `config/database-dumps.php`:

```php
return [
    'config_path' => base_path('database/dump_config.yaml'),
    'project_dir' => base_path(),
];
```

#### 2. Usage

```bash
# Export all tables
php artisan dbdump:export all

# Export specific table
php artisan dbdump:export public.users

# Export with schema filter
php artisan dbdump:export all --schema=public

# Import dumps
php artisan dbdump:import

# Import with options
php artisan dbdump:import --skip-before --skip-after
php artisan dbdump:import --schema=public
```

### Configuration Options

#### Full Export

Export **all data** from specified tables:

```yaml
full_export:
  users:
    - users
    - roles
  system:
    - settings
```

#### Partial Export

Export **limited data** with filters:

```yaml
partial_export:
  clients:
    clients:
      limit: 1000
      order_by: created_at DESC
      where: "is_active = true"

    orders:
      limit: 5000
      order_by: id DESC
```

**Available options:**
- `limit` - Maximum number of rows to export
- `order_by` - SQL ORDER BY clause
- `where` - SQL WHERE condition

### Before/After Execution Scripts

Execute custom SQL scripts before and after import.

#### Before Exec Scripts

Located in `database/before_exec/`. Executed **before** importing dumps.

#### After Exec Scripts

Located in `database/after_exec/`. Executed **after** importing dumps.

Scripts are executed in **alphabetical order**. Use numeric prefixes (01_, 02_) to control order.

#### Skip Scripts

Symfony:

```bash
php bin/console app:dbdump:import --skip-before
php bin/console app:dbdump:import --skip-after
php bin/console app:dbdump:import --skip-before --skip-after
```

Laravel:

```bash
php artisan dbdump:import --skip-before
php artisan dbdump:import --skip-after
php artisan dbdump:import --skip-before --skip-after
```

## Testing

```bash
# Run all tests
composer test

# Run tests with coverage
composer test-coverage

# Run static analysis
composer phpstan
```

## Local Development (without Packagist)

To test the package in a local project, add to the project's `composer.json`:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "../database-dumps"
        }
    ],
    "require": {
        "backvista/database-dumps": "*"
    }
}
```

Then run `composer update backvista/database-dumps`. Composer will create a symlink to the local package.

## Requirements

**Required:**

- PHP ^7.4 or ^8.0
- `symfony/yaml` ^5.4|^6.0|^7.0
- `symfony/finder` ^5.4|^6.0|^7.0

**Optional (depending on framework):**

| Dependency | Required for |
|---|---|
| `doctrine/dbal` ^2.13\|^3.0\|^4.0 | Doctrine DBAL adapter (Symfony) |
| `symfony/console` ^5.4\|^6.0\|^7.0 | Symfony console commands |
| `symfony/http-kernel` ^5.4\|^6.0\|^7.0 | Symfony bundle registration |
| `illuminate/support` ^8.0\|^9.0\|^10.0\|^11.0 | Laravel service provider |
| `illuminate/console` ^8.0\|^9.0\|^10.0\|^11.0 | Laravel artisan commands |
| `illuminate/database` ^8.0\|^9.0\|^10.0\|^11.0 | Laravel database adapter |

## Security

The package includes a production guard that prevents accidental database imports in production environments. Import is blocked when the `APP_ENV` environment variable is set to `prod` or `predprod`.

## License

MIT License. See [LICENSE](LICENSE) for details.

## Credits

Developed by Timur Bayan (BackVista).
