# Database Dumps Package

[![Tests](https://img.shields.io/badge/tests-passing-brightgreen)]()
[![Coverage](https://img.shields.io/badge/coverage-90%25-brightgreen)]()
[![PHP Version](https://img.shields.io/badge/php-%5E7.4%20%7C%20%5E8.0-blue)]()
[![License](https://img.shields.io/badge/license-MIT-blue.svg)]()

Framework-agnostic PHP package for managing database dumps with SQL exports/imports, supporting PostgreSQL, MySQL, and SQLite.

## Features

- ✅ **Framework-agnostic**: Works with Symfony, Laravel, and any PHP project
- ✅ **Multiple databases**: PostgreSQL, MySQL, SQLite support
- ✅ **Smart batching**: Automatic INSERT batching (1000 rows per statement)
- ✅ **Transaction safety**: Automatic rollback on errors
- ✅ **Production guard**: Prevents accidental imports in production
- ✅ **Before/After hooks**: Execute custom SQL scripts before/after import
- ✅ **Flexible configuration**: YAML-based export rules (full/partial)
- ✅ **Sequence reset**: Automatic primary key sequence reset (PostgreSQL)
- ✅ **Well tested**: >90% code coverage

## Installation

```bash
composer require smartcrm/database-dumps
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
php bin/console app:dump:export all
```

4. Import dumps:

```bash
php bin/console app:db:init
```

### Laravel Integration

1. Register the service provider in `config/app.php`:

```php
'providers' => [
    // ...
    SmartCrm\DatabaseDumps\Bridge\Laravel\DatabaseDumpsServiceProvider::class,
],
```

2. Publish configuration:

```bash
php artisan vendor:publish --provider="SmartCrm\DatabaseDumps\Bridge\Laravel\DatabaseDumpsServiceProvider"
```

3. Export dumps:

```bash
php artisan dump:export all
```

4. Import dumps:

```bash
php artisan dump:init
```

## Architecture

### Core Components

- **Service Layer**: Business logic (Dumper, Importer, Generator, Parser)
- **Contracts**: Interfaces for database, filesystem, configuration
- **Adapters**: Database-specific implementations (PostgreSQL, MySQL, SQLite)
- **Bridges**: Framework integrations (Symfony, Laravel)
- **Security**: Environment checks and production guards

### SOLID Principles

- **Single Responsibility**: Each class has one clear purpose
- **Dependency Inversion**: All dependencies through interfaces
- **Open/Closed**: Extensible through adapters and plugins

## Configuration

### Symfony Configuration

#### 1. Create Configuration File

Create `config/dump_config.yaml` in your Symfony project root:

```yaml
# config/dump_config.yaml
full_export:
  # Schema: public
  public:
    - users      # Table: users (full export)
    - roles      # Table: roles (full export)
    - settings   # Table: settings (full export)

  # Schema: system
  system:
    - logs       # Table: logs (full export)

partial_export:
  # Schema: clients
  clients:
    clients:     # Table: clients (partial export with filters)
      limit: 1000
      order_by: created_at DESC
      where: "is_active = true"

    orders:      # Table: orders (partial export)
      limit: 5000
      order_by: created_at DESC

exclude:
  - temp_table
  - cache_table
```

**Understanding Schema and Table:**
- **Schema** = database schema (namespace) in PostgreSQL/MySQL
- **Table** = table name within the schema
- Format: `schema.table` (e.g., `public.users`, `clients.orders`)

#### 2. Directory Structure

Create these directories in your Symfony project:

```
your-symfony-project/
├── config/
│   └── dump_config.yaml          # Configuration file
├── database/
│   ├── before_exec/              # SQL scripts to run BEFORE import
│   │   └── 01_prepare.sql
│   ├── dumps/                    # Generated dumps directory
│   │   ├── public/               # Schema: public
│   │   │   ├── users.sql
│   │   │   └── roles.sql
│   │   └── clients/              # Schema: clients
│   │       ├── clients.sql
│   │       └── orders.sql
│   └── after_exec/               # SQL scripts to run AFTER import
│       └── 01_finalize.sql
```

#### 3. Service Configuration

The package auto-registers via Symfony Flex. If not, add to `config/bundles.php`:

```php
// config/bundles.php
return [
    // ...
    SmartCrm\DatabaseDumps\Bridge\Symfony\DatabaseDumpsBundle::class => ['all' => true],
];
```

#### 4. Usage

```bash
# Export all tables from config
php bin/console app:dump:export all

# Export specific table
php bin/console app:dump:export public.users

# Export with schema filter
php bin/console app:dump:export all --schema=public

# Import all dumps
php bin/console app:db:init

# Import with options
php bin/console app:db:init --skip-before --skip-after
php bin/console app:db:init --schema=public
```

### Laravel Configuration

#### 1. Create Configuration File

Create `config/database-dumps.php`:

```php
// config/database-dumps.php
return [
    'config_path' => base_path('config/dump_config.yaml'),
    'project_dir' => base_path(),
];
```

#### 2. Create YAML Configuration

Create `config/dump_config.yaml`:

```yaml
# config/dump_config.yaml
full_export:
  # Schema: public (or your default schema)
  public:
    - users
    - roles

partial_export:
  public:
    posts:
      limit: 1000
      order_by: created_at DESC
```

#### 3. Directory Structure

```
your-laravel-project/
├── config/
│   ├── database-dumps.php        # Package config
│   └── dump_config.yaml          # Dump config
├── database/
│   ├── before_exec/
│   │   └── 01_prepare.sql
│   ├── dumps/
│   │   └── public/
│   │       ├── users.sql
│   │       └── posts.sql
│   └── after_exec/
│       └── 01_finalize.sql
```

#### 4. Usage

```bash
# Export all tables
php artisan dump:export all

# Export specific table
php artisan dump:export public.users

# Import dumps
php artisan dump:init
```

### Configuration Options

#### Full Export

Export **all data** from specified tables:

```yaml
full_export:
  users:        # Schema name
    - users     # Table names (will export ALL rows)
    - roles
  system:
    - settings
```

#### Partial Export

Export **limited data** with filters:

```yaml
partial_export:
  clients:      # Schema name
    clients:    # Table name
      limit: 1000                    # Max rows to export
      order_by: created_at DESC      # Sort order
      where: "is_active = true"      # Filter condition

    orders:
      limit: 5000
      order_by: id DESC
```

**Available options:**
- `limit` - Maximum number of rows to export
- `order_by` - SQL ORDER BY clause (e.g., `created_at DESC`, `id ASC`)
- `where` - SQL WHERE condition (e.g., `status = 'active' AND created_at > '2024-01-01'`)

#### Exclude Tables

Prevent certain tables from being exported:

```yaml
exclude:
  - temp_data
  - cache_entries
  - session_data
```

### Before/After Execution Scripts

Execute custom SQL scripts before and after import to optimize performance or clean up data.

#### Before Exec Scripts

Located in `database/before_exec/`. Executed **before** importing dumps.

**Use cases:**
- Disable triggers/constraints for faster import
- Set session variables
- Prepare database state

**Example: database/before_exec/01_optimize.sql**
```sql
-- Disable replication triggers for faster import
SET session_replication_role = 'replica';

-- Increase work memory
SET work_mem = '256MB';

-- Disable auto-analyze during import
SET session_replication_role TO REPLICA;
```

**Example: database/before_exec/02_cleanup.sql**
```sql
-- Truncate all tables first (optional)
TRUNCATE TABLE public.users CASCADE;
TRUNCATE TABLE public.roles CASCADE;
```

#### After Exec Scripts

Located in `database/after_exec/`. Executed **after** importing dumps.

**Use cases:**
- Re-enable triggers/constraints
- Run ANALYZE for query optimization
- Create indexes
- Update sequences

**Example: database/after_exec/01_finalize.sql**
```sql
-- Re-enable replication triggers
SET session_replication_role = 'origin';

-- Analyze tables for query planner
ANALYZE;
```

**Example: database/after_exec/02_indexes.sql**
```sql
-- Recreate indexes if dropped before import
CREATE INDEX idx_users_email ON public.users(email);
CREATE INDEX idx_orders_created ON public.orders(created_at);
```

#### Script Execution Order

Scripts are executed in **alphabetical order** (using filename sort).

**Example order:**
```
database/before_exec/
  01_prepare.sql       # Executed first
  02_disable_triggers.sql  # Executed second

database/after_exec/
  01_analyze.sql       # Executed first
  02_indexes.sql       # Executed second
  99_finalize.sql      # Executed last
```

**Tip:** Use numeric prefixes (01_, 02_, etc.) to control execution order.

#### Skip Scripts

You can skip before/after scripts when importing:

```bash
# Skip before_exec scripts
php bin/console app:db:init --skip-before

# Skip after_exec scripts
php bin/console app:db:init --skip-after

# Skip both
php bin/console app:db:init --skip-before --skip-after
```

## Usage Examples

### Programmatic Usage

```php
use SmartCrm\DatabaseDumps\Service\Dumper\DatabaseDumper;
use SmartCrm\DatabaseDumps\Service\Importer\DatabaseImporter;

// Export
$dumper = new DatabaseDumper($connection, $configLoader, $fileSystem);
$dumper->exportTable('users', 'users');

// Import
$importer = new DatabaseImporter($connection, $fileSystem, $environmentChecker);
$importer->import();
```

### CLI Commands

```bash
# Export single table
php bin/console app:dump:export users.users

# Export all tables
php bin/console app:dump:export all

# Export specific schema
php bin/console app:dump:export --schema=users all

# Import with before/after scripts
php bin/console app:db:init

# Import specific schema only
php bin/console app:db:init --schema=users

# Skip before/after scripts
php bin/console app:db:init --skip-before --skip-after
```

## Testing

Run unit tests:

```bash
composer test
```

Run tests with coverage:

```bash
composer test-coverage
```

Run static analysis:

```bash
composer phpstan
```

## Documentation

- [Installation Guide](docs/installation.md)
- [Symfony Integration](docs/symfony-integration.md)
- [Laravel Integration](docs/laravel-integration.md)
- [API Documentation](docs/api.md)

## Requirements

- PHP ^7.4 or ^8.0
- Symfony YAML component ^5.4|^6.0|^7.0

## License

MIT License. See [LICENSE](LICENSE) for details.

## Contributing

Contributions are welcome! Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details.

## Credits

Developed by SmartCRM Team.
