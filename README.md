# Database Dumps Package

[![Tests](https://img.shields.io/badge/tests-passing-brightgreen)]()
[![PHP Version](https://img.shields.io/badge/php-%5E7.4%20%7C%20%5E8.0-blue)]()
[![License](https://img.shields.io/badge/license-MIT-blue.svg)]()

**[Русский](#русский)** | **[English](#english)**

---

<a id="русский"></a>

# Русский

Фреймворк-агностичный PHP-пакет для управления дампами баз данных — экспорт и импорт SQL с поддержкой PostgreSQL и MySQL.

## Оглавление

- [Возможности](#возможности)
- [Установка](#установка)
- [Быстрый старт](#быстрый-старт)
  - [Symfony](#быстрый-старт-symfony)
  - [Laravel](#быстрый-старт-laravel)
- [Конфигурация](#конфигурация)
  - [Формат YAML](#формат-yaml)
    - [Полный экспорт (full_export)](#полный-экспорт-full_export)
    - [Частичный экспорт (partial_export)](#частичный-экспорт-partial_export)
  - [Несколько подключений (multi-connection)](#несколько-подключений-multi-connection)
  - [Автогенерация конфигурации (prepare-config)](#автогенерация-конфигурации-prepare-config)
- [Настройка Symfony](#настройка-symfony)
  - [Регистрация бандла](#регистрация-бандла)
  - [Структура каталогов (Symfony)](#структура-каталогов-symfony)
  - [Команды Symfony](#команды-symfony)
- [Настройка Laravel](#настройка-laravel)
  - [Регистрация провайдера](#регистрация-провайдера)
  - [Публикация конфигурации](#публикация-конфигурации)
  - [Команды Laravel](#команды-laravel)
- [Скрипты before/after](#скрипты-beforeafter)
- [Поддержка IDE (JSON Schema)](#поддержка-ide-json-schema)
- [Архитектура](#архитектура)
  - [Поток экспорта](#поток-экспорта)
  - [Поток импорта](#поток-импорта)
  - [Абстракция платформы](#абстракция-платформы)
  - [Структура каталогов src/](#структура-каталогов-src)
- [Безопасность](#безопасность)
- [Тестирование](#тестирование)
- [Локальная разработка](#локальная-разработка)
- [Требования](#требования)
- [Лицензия](#лицензия)

---

## Возможности

- **Фреймворк-агностичность** — работает с Symfony, Laravel и любым PHP-проектом
- **PostgreSQL и MySQL** — абстракция диалектов SQL через платформы
- **Несколько подключений** — экспорт/импорт для нескольких баз данных одновременно
- **Умный батчинг** — автоматическая группировка INSERT (1000 строк на выражение)
- **Транзакционность** — автоматический откат при ошибках
- **Защита от продакшена** — блокировка импорта в production-окружениях
- **Хуки before/after** — пользовательские SQL-скрипты до и после импорта
- **Гибкая конфигурация** — YAML-правила для полного и частичного экспорта
- **Сброс последовательностей** — автоматический сброс sequence/auto-increment
- **Автогенерация конфигурации** — команда `prepare-config` создаёт YAML на основе структуры БД

## Установка

```bash
composer require backvista/database-dumps
```

## Быстрый старт

<a id="быстрый-старт-symfony"></a>

### Symfony

1. Бандл авторегистрируется через Symfony Flex.

2. Создайте `config/dump_config.yaml`:

```yaml
full_export:
  public:
    - users
    - roles

partial_export:
  public:
    clients:
      limit: 1000
      order_by: created_at DESC
```

3. Экспорт:

```bash
php bin/console app:dbdump:export all
```

4. Импорт:

```bash
php bin/console app:dbdump:import
```

<a id="быстрый-старт-laravel"></a>

### Laravel

1. Сервис-провайдер обнаруживается автоматически. Файл `database/dump_config.yaml` создаётся при первом запуске.

2. Отредактируйте `database/dump_config.yaml` (формат тот же, что и для Symfony).

3. Экспорт:

```bash
php artisan dbdump:export all
```

4. Импорт:

```bash
php artisan dbdump:import
```

## Конфигурация

### Формат YAML

#### Полный экспорт (full_export)

Экспорт **всех строк** из указанных таблиц:

```yaml
full_export:
  public:          # имя схемы
    - users        # таблицы, все строки
    - roles
  system:
    - settings
```

#### Частичный экспорт (partial_export)

Экспорт **ограниченного количества строк** с фильтрацией:

```yaml
partial_export:
  public:
    clients:
      limit: 1000                    # максимум строк
      order_by: created_at DESC      # сортировка
      where: "is_active = true"      # фильтр WHERE
    orders:
      limit: 5000
      order_by: id DESC
```

**Доступные опции:**

| Опция | Описание |
|-------|----------|
| `limit` | Максимальное количество строк для экспорта |
| `order_by` | SQL ORDER BY (должен заканчиваться на `ASC` или `DESC`) |
| `where` | SQL WHERE условие |

### Несколько подключений (multi-connection)

Для работы с несколькими базами данных добавьте секцию `connections`:

```yaml
# Конфигурация подключения по умолчанию
full_export:
  public:
    - users
    - roles

partial_export:
  public:
    posts:
      limit: 100

# Дополнительные подключения
connections:
  analytics:                 # имя подключения (совпадает с именем в framework)
    full_export:
      analytics:
        - events
        - metrics
    partial_export:
      analytics:
        logs:
          limit: 50
          order_by: id DESC
```

**Пути дампов:**
- Подключение по умолчанию: `database/dumps/{schema}/{table}.sql`
- Именованное подключение: `database/dumps/{connection}/{schema}/{table}.sql`

**Использование с опцией `--connection`:**

```bash
# Экспорт только из подключения по умолчанию (без опции)
php artisan dbdump:export all

# Экспорт только из указанного подключения
php artisan dbdump:export all --connection=analytics

# Экспорт из всех подключений
php artisan dbdump:export all --connection=all
```

### Автогенерация конфигурации (prepare-config)

Команда `prepare-config` анализирует структуру БД и генерирует `dump_config.yaml` автоматически:

```bash
# Symfony
php bin/console app:dbdump:prepare-config

# Laravel
php artisan dbdump:prepare-config
```

**Опции:**

| Опция | Описание | По умолчанию |
|-------|----------|-------------|
| `--threshold`, `-t` | Порог строк: таблицы <= порога попадают в full_export, выше — в partial_export | 500 |
| `--force`, `-f` | Перезаписать существующий файл без подтверждения | — |

**Логика распределения:**
- Строк <= `threshold` — `full_export` (все строки)
- Строк > `threshold` — `partial_export` (с limit и автоопределённой сортировкой)
- Пустые таблицы — пропускаются
- Служебные таблицы (migrations, sessions, cache_*, telescope_*, oauth_*, audit_*) — пропускаются

## Настройка Symfony

### Регистрация бандла

Бандл авторегистрируется через Symfony Flex. Если нет, добавьте в `config/bundles.php`:

```php
return [
    // ...
    BackVista\DatabaseDumps\Bridge\Symfony\DatabaseDumpsBundle::class => ['all' => true],
];
```

Укажите параметр платформы в `services.yaml`:

```yaml
parameters:
    database_dumps.platform: 'postgresql'  # или 'mysql'
```

<a id="структура-каталогов-symfony"></a>

### Структура каталогов (Symfony)

```
your-symfony-project/
├── config/
│   └── dump_config.yaml          # конфигурация экспорта
├── database/
│   ├── before_exec/              # скрипты до импорта
│   │   └── 01_prepare.sql
│   ├── dumps/                    # SQL-дампы
│   │   ├── public/
│   │   │   ├── users.sql
│   │   │   └── roles.sql
│   │   └── analytics/            # именованное подключение
│   │       └── analytics/
│   │           └── events.sql
│   └── after_exec/               # скрипты после импорта
│       └── 01_finalize.sql
```

### Команды Symfony

```bash
# Экспорт всех таблиц
php bin/console app:dbdump:export all

# Экспорт конкретной таблицы
php bin/console app:dbdump:export public.users

# Экспорт с фильтром по схеме
php bin/console app:dbdump:export all --schema=public

# Экспорт с указанием подключения
php bin/console app:dbdump:export all --connection=analytics
php bin/console app:dbdump:export all --connection=all

# Импорт всех дампов
php bin/console app:dbdump:import

# Импорт с опциями
php bin/console app:dbdump:import --skip-before --skip-after
php bin/console app:dbdump:import --schema=public
php bin/console app:dbdump:import --connection=all

# Автогенерация конфигурации
php bin/console app:dbdump:prepare-config
php bin/console app:dbdump:prepare-config --threshold=1000 --force
```

## Настройка Laravel

### Регистрация провайдера

Сервис-провайдер обнаруживается автоматически. Если нет, зарегистрируйте вручную в `config/app.php`:

```php
'providers' => [
    // ...
    BackVista\DatabaseDumps\Bridge\Laravel\DatabaseDumpsServiceProvider::class,
],
```

### Публикация конфигурации

Для изменения путей опубликуйте PHP-конфигурацию:

```bash
php artisan vendor:publish --tag=database-dumps-config
```

Создаётся `config/database-dumps.php`:

```php
return [
    'config_path' => base_path('database/dump_config.yaml'),
    'project_dir' => base_path(),
];
```

### Команды Laravel

```bash
# Экспорт всех таблиц
php artisan dbdump:export all

# Экспорт конкретной таблицы
php artisan dbdump:export public.users

# Экспорт с фильтром по схеме
php artisan dbdump:export all --schema=public

# Экспорт с указанием подключения
php artisan dbdump:export all --connection=analytics
php artisan dbdump:export all --connection=all

# Импорт всех дампов
php artisan dbdump:import

# Импорт с опциями
php artisan dbdump:import --skip-before --skip-after
php artisan dbdump:import --schema=public
php artisan dbdump:import --connection=all

# Автогенерация конфигурации
php artisan dbdump:prepare-config
php artisan dbdump:prepare-config --threshold=1000 --force
```

## Скрипты before/after

Выполнение пользовательских SQL-скриптов до и после импорта.

| Каталог | Момент выполнения |
|---------|-------------------|
| `database/before_exec/` | **до** импорта дампов |
| `database/after_exec/` | **после** импорта дампов |

Скрипты выполняются в **алфавитном порядке**. Используйте числовые префиксы для управления порядком:

```
database/before_exec/
├── 01_disable_triggers.sql
├── 02_prepare_temp.sql
database/after_exec/
├── 01_enable_triggers.sql
├── 02_refresh_views.sql
```

Для пропуска скриптов используйте опции `--skip-before` и `--skip-after`:

```bash
php artisan dbdump:import --skip-before
php artisan dbdump:import --skip-after
php artisan dbdump:import --skip-before --skip-after
```

## Поддержка IDE (JSON Schema)

Пакет включает JSON Schema для `dump_config.yaml` в `resources/dump_config.schema.json`. Это даёт автодополнение, валидацию и подсказки в PHPStorm и других IDE.

### Вариант 1: YAML-комментарий (рекомендуется)

Добавьте в начало `dump_config.yaml`:

```yaml
# yaml-language-server: $schema=../vendor/backvista/database-dumps/resources/dump_config.schema.json
```

> Для Symfony путь относительно `config/`, для Laravel — относительно `database/`.

### Вариант 2: Настройка PHPStorm вручную

1. Откройте **Settings > Languages & Frameworks > Schemas and DTDs > JSON Schema Mappings**
2. Добавьте маппинг:
   - **Schema file**: `vendor/backvista/database-dumps/resources/dump_config.schema.json`
   - **File path pattern**: `dump_config.yaml`

## Архитектура

### Поток экспорта

```
Command → TableConfigResolver → DatabaseDumper → DataFetcher → SqlGenerator → .sql файлы
```

1. **TableConfigResolver** — разрешает YAML-конфигурацию в массив `TableConfig[]`
2. **DatabaseDumper** — оркестрирует процесс экспорта
3. **DataFetcher** — получает данные из БД через `ConnectionRegistry`
4. **SqlGenerator** — генерирует SQL (TRUNCATE + INSERT + сброс последовательностей)
5. Результат записывается в `database/dumps/{schema}/{table}.sql`

### Поток импорта

```
Command → DatabaseImporter → ProductionGuard → TransactionManager → ScriptExecutor → SqlParser → выполнение
```

1. **ProductionGuard** — блокирует импорт в production
2. **TransactionManager** — оборачивает операции в транзакцию
3. **ScriptExecutor** — выполняет `before_exec/` скрипты
4. **SqlParser** / **StatementSplitter** — разбирает .sql файлы на отдельные выражения
5. Выражения выполняются в БД
6. **ScriptExecutor** — выполняет `after_exec/` скрипты

### Абстракция платформы

Пакет использует `DatabasePlatformInterface` для генерации SQL, совместимого с каждой СУБД:

| | PostgreSQL | MySQL |
|---|---|---|
| Идентификаторы | `"table"` (двойные кавычки) | `` `table` `` (обратные кавычки) |
| TRUNCATE | `TRUNCATE ... CASCADE` | `SET FOREIGN_KEY_CHECKS=0` |
| Последовательности | `setval()` / `pg_get_serial_sequence()` | `ALTER TABLE ... AUTO_INCREMENT` |

Платформа автоматически определяется из подключения к БД.

<a id="структура-каталогов-src"></a>

### Структура каталогов src/

```
src/
├── Adapter/                          # Адаптеры БД
│   ├── DoctrineDbalAdapter.php       #   Doctrine DBAL
│   └── LaravelDatabaseAdapter.php    #   Laravel DB
├── Bridge/                           # Интеграции с фреймворками
│   ├── Laravel/
│   │   ├── Command/                  #   Artisan-команды
│   │   ├── DatabaseDumpsServiceProvider.php
│   │   └── LaravelLogger.php
│   └── Symfony/
│       ├── Command/                  #   Console-команды
│       ├── DependencyInjection/      #   DI-расширение
│       ├── ConnectionRegistryFactory.php
│       ├── ConsoleLogger.php
│       └── DatabaseDumpsBundle.php
├── Config/                           # DTO конфигурации
│   ├── DumpConfig.php                #   Общая конфигурация дампов
│   ├── EnvironmentConfig.php         #   Детекция окружения
│   └── TableConfig.php              #   Настройки экспорта таблицы
├── Contract/                         # Интерфейсы
│   ├── ConfigLoaderInterface.php
│   ├── ConnectionRegistryInterface.php
│   ├── DatabaseConnectionInterface.php
│   ├── DatabasePlatformInterface.php
│   ├── FileSystemInterface.php
│   └── LoggerInterface.php
├── Exception/                        # Исключения
├── Platform/                         # Абстракция SQL-диалектов
│   ├── MySqlPlatform.php
│   ├── PostgresPlatform.php
│   └── PlatformFactory.php
├── Service/
│   ├── ConfigGenerator/              # Автогенерация конфигурации
│   ├── ConnectionRegistry.php        # Реестр подключений
│   ├── Dumper/                       # Экспорт дампов
│   ├── Generator/                    # Генерация SQL
│   ├── Importer/                     # Импорт дампов
│   ├── Parser/                       # Парсинг SQL
│   └── Security/                     # Защита продакшена
└── Util/                             # Утилиты
    ├── FileSystemHelper.php
    └── YamlConfigLoader.php
```

## Безопасность

Пакет включает защиту от случайного импорта в production-окружениях. Импорт блокируется, когда переменная окружения `APP_ENV` установлена в `prod` или `predprod`.

## Тестирование

```bash
# Все тесты
composer test

# Тесты с покрытием
composer test-coverage

# Статический анализ (PHPStan level 8)
composer phpstan

# Исправление стиля кода
composer cs-fix
```

## Локальная разработка

Для тестирования пакета в локальном проекте без Packagist добавьте в `composer.json` проекта:

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

Затем выполните `composer update backvista/database-dumps`. Composer создаст симлинк на локальный пакет.

## Требования

**Обязательные:**

- PHP ^7.4 | ^8.0
- `symfony/yaml` ^5.4 | ^6.0 | ^7.0
- `symfony/finder` ^5.4 | ^6.0 | ^7.0

**Опциональные (в зависимости от фреймворка):**

| Зависимость | Назначение |
|---|---|
| `doctrine/dbal` ^2.13 \| ^3.0 \| ^4.0 | Адаптер Doctrine DBAL (Symfony) |
| `symfony/console` ^5.4 \| ^6.0 \| ^7.0 | Консольные команды Symfony |
| `symfony/http-kernel` ^5.4 \| ^6.0 \| ^7.0 | Регистрация бандла Symfony |
| `illuminate/support` ^8.0 \| ^9.0 \| ^10.0 \| ^11.0 | Сервис-провайдер Laravel |
| `illuminate/console` ^8.0 \| ^9.0 \| ^10.0 \| ^11.0 | Artisan-команды Laravel |
| `illuminate/database` ^8.0 \| ^9.0 \| ^10.0 \| ^11.0 | Адаптер БД Laravel |

## Лицензия

MIT License. Подробнее в файле [LICENSE](LICENSE).

---

<a id="english"></a>

# English

Framework-agnostic PHP package for managing database dumps — SQL export/import supporting PostgreSQL and MySQL.

## Table of Contents

- [Features](#features)
- [Installation](#installation)
- [Quick Start](#quick-start)
  - [Symfony](#quick-start-symfony)
  - [Laravel](#quick-start-laravel)
- [Configuration](#configuration)
  - [YAML Format](#yaml-format)
    - [Full Export](#full-export)
    - [Partial Export](#partial-export)
  - [Multiple Connections](#multiple-connections)
  - [Auto-generate Configuration (prepare-config)](#auto-generate-configuration-prepare-config)
- [Symfony Setup](#symfony-setup)
  - [Bundle Registration](#bundle-registration)
  - [Directory Structure (Symfony)](#directory-structure-symfony)
  - [Symfony Commands](#symfony-commands)
- [Laravel Setup](#laravel-setup)
  - [Provider Registration](#provider-registration)
  - [Publishing Configuration](#publishing-configuration)
  - [Laravel Commands](#laravel-commands)
- [Before/After Scripts](#beforeafter-scripts)
- [IDE Support (JSON Schema)](#ide-support-json-schema)
- [Architecture](#architecture)
  - [Export Flow](#export-flow)
  - [Import Flow](#import-flow)
  - [Platform Abstraction](#platform-abstraction)
  - [Source Directory Structure](#source-directory-structure)
- [Security](#security)
- [Testing](#testing)
- [Local Development](#local-development)
- [Requirements](#requirements)
- [License](#license)

---

## Features

- **Framework-agnostic** — works with Symfony, Laravel, and any PHP project
- **PostgreSQL & MySQL** — SQL dialect abstraction via platforms
- **Multiple connections** — export/import across multiple databases simultaneously
- **Smart batching** — automatic INSERT batching (1000 rows per statement)
- **Transaction safety** — automatic rollback on errors
- **Production guard** — prevents accidental imports in production environments
- **Before/After hooks** — custom SQL scripts before and after import
- **Flexible configuration** — YAML-based rules for full and partial exports
- **Sequence reset** — automatic sequence/auto-increment reset
- **Auto-generate config** — `prepare-config` command creates YAML based on DB structure

## Installation

```bash
composer require backvista/database-dumps
```

<a id="quick-start"></a>

## Quick Start

<a id="quick-start-symfony"></a>

### Symfony

1. The bundle is auto-registered via Symfony Flex.

2. Create `config/dump_config.yaml`:

```yaml
full_export:
  public:
    - users
    - roles

partial_export:
  public:
    clients:
      limit: 1000
      order_by: created_at DESC
```

3. Export:

```bash
php bin/console app:dbdump:export all
```

4. Import:

```bash
php bin/console app:dbdump:import
```

<a id="quick-start-laravel"></a>

### Laravel

1. The service provider is auto-discovered. The file `database/dump_config.yaml` is created on first run.

2. Edit `database/dump_config.yaml` (same format as Symfony).

3. Export:

```bash
php artisan dbdump:export all
```

4. Import:

```bash
php artisan dbdump:import
```

## Configuration

### YAML Format

#### Full Export

Export **all rows** from specified tables:

```yaml
full_export:
  public:          # schema name
    - users        # tables — all rows
    - roles
  system:
    - settings
```

#### Partial Export

Export a **limited number of rows** with filtering:

```yaml
partial_export:
  public:
    clients:
      limit: 1000                    # max rows
      order_by: created_at DESC      # sorting
      where: "is_active = true"      # WHERE filter
    orders:
      limit: 5000
      order_by: id DESC
```

**Available options:**

| Option | Description |
|--------|-------------|
| `limit` | Maximum number of rows to export |
| `order_by` | SQL ORDER BY clause (must end with `ASC` or `DESC`) |
| `where` | SQL WHERE condition |

### Multiple Connections

To work with multiple databases, add a `connections` section:

```yaml
# Default connection configuration
full_export:
  public:
    - users
    - roles

partial_export:
  public:
    posts:
      limit: 100

# Additional connections
connections:
  analytics:                 # connection name (matches framework config)
    full_export:
      analytics:
        - events
        - metrics
    partial_export:
      analytics:
        logs:
          limit: 50
          order_by: id DESC
```

**Dump paths:**
- Default connection: `database/dumps/{schema}/{table}.sql`
- Named connection: `database/dumps/{connection}/{schema}/{table}.sql`

**Usage with `--connection` option:**

```bash
# Export from default connection only (no option)
php artisan dbdump:export all

# Export from a specific connection
php artisan dbdump:export all --connection=analytics

# Export from all connections
php artisan dbdump:export all --connection=all
```

### Auto-generate Configuration (prepare-config)

The `prepare-config` command analyzes the DB structure and generates `dump_config.yaml` automatically:

```bash
# Symfony
php bin/console app:dbdump:prepare-config

# Laravel
php artisan dbdump:prepare-config
```

**Options:**

| Option | Description | Default |
|--------|-------------|---------|
| `--threshold`, `-t` | Row threshold: tables <= threshold go to full_export, above — to partial_export | 500 |
| `--force`, `-f` | Overwrite existing file without confirmation | — |

**Distribution logic:**
- Rows <= `threshold` — `full_export` (all rows)
- Rows > `threshold` — `partial_export` (with limit and auto-detected sorting)
- Empty tables — skipped
- Service tables (migrations, sessions, cache_*, telescope_*, oauth_*, audit_*) — skipped

## Symfony Setup

### Bundle Registration

The bundle is auto-registered via Symfony Flex. If not, add to `config/bundles.php`:

```php
return [
    // ...
    BackVista\DatabaseDumps\Bridge\Symfony\DatabaseDumpsBundle::class => ['all' => true],
];
```

Set the platform parameter in `services.yaml`:

```yaml
parameters:
    database_dumps.platform: 'postgresql'  # or 'mysql'
```

<a id="directory-structure-symfony"></a>

### Directory Structure (Symfony)

```
your-symfony-project/
├── config/
│   └── dump_config.yaml          # export configuration
├── database/
│   ├── before_exec/              # pre-import scripts
│   │   └── 01_prepare.sql
│   ├── dumps/                    # SQL dumps
│   │   ├── public/
│   │   │   ├── users.sql
│   │   │   └── roles.sql
│   │   └── analytics/            # named connection
│   │       └── analytics/
│   │           └── events.sql
│   └── after_exec/               # post-import scripts
│       └── 01_finalize.sql
```

### Symfony Commands

```bash
# Export all tables
php bin/console app:dbdump:export all

# Export a specific table
php bin/console app:dbdump:export public.users

# Export with schema filter
php bin/console app:dbdump:export all --schema=public

# Export with connection
php bin/console app:dbdump:export all --connection=analytics
php bin/console app:dbdump:export all --connection=all

# Import all dumps
php bin/console app:dbdump:import

# Import with options
php bin/console app:dbdump:import --skip-before --skip-after
php bin/console app:dbdump:import --schema=public
php bin/console app:dbdump:import --connection=all

# Auto-generate configuration
php bin/console app:dbdump:prepare-config
php bin/console app:dbdump:prepare-config --threshold=1000 --force
```

## Laravel Setup

### Provider Registration

The service provider is auto-discovered. If not, register manually in `config/app.php`:

```php
'providers' => [
    // ...
    BackVista\DatabaseDumps\Bridge\Laravel\DatabaseDumpsServiceProvider::class,
],
```

### Publishing Configuration

To customize paths, publish the PHP config:

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

### Laravel Commands

```bash
# Export all tables
php artisan dbdump:export all

# Export a specific table
php artisan dbdump:export public.users

# Export with schema filter
php artisan dbdump:export all --schema=public

# Export with connection
php artisan dbdump:export all --connection=analytics
php artisan dbdump:export all --connection=all

# Import all dumps
php artisan dbdump:import

# Import with options
php artisan dbdump:import --skip-before --skip-after
php artisan dbdump:import --schema=public
php artisan dbdump:import --connection=all

# Auto-generate configuration
php artisan dbdump:prepare-config
php artisan dbdump:prepare-config --threshold=1000 --force
```

## Before/After Scripts

Execute custom SQL scripts before and after import.

| Directory | Execution time |
|-----------|---------------|
| `database/before_exec/` | **before** importing dumps |
| `database/after_exec/` | **after** importing dumps |

Scripts are executed in **alphabetical order**. Use numeric prefixes to control the order:

```
database/before_exec/
├── 01_disable_triggers.sql
├── 02_prepare_temp.sql
database/after_exec/
├── 01_enable_triggers.sql
├── 02_refresh_views.sql
```

To skip scripts, use the `--skip-before` and `--skip-after` options:

```bash
php artisan dbdump:import --skip-before
php artisan dbdump:import --skip-after
php artisan dbdump:import --skip-before --skip-after
```

## IDE Support (JSON Schema)

The package includes a JSON Schema for `dump_config.yaml` at `resources/dump_config.schema.json`. This provides autocompletion, validation, and documentation hints in PHPStorm and other IDEs.

### Option 1: YAML comment (recommended)

Add this line at the top of your `dump_config.yaml`:

```yaml
# yaml-language-server: $schema=../vendor/backvista/database-dumps/resources/dump_config.schema.json
```

> For Symfony the path is relative to `config/`, for Laravel — relative to `database/`.

### Option 2: PHPStorm manual mapping

1. Open **Settings > Languages & Frameworks > Schemas and DTDs > JSON Schema Mappings**
2. Add a new mapping:
   - **Schema file**: `vendor/backvista/database-dumps/resources/dump_config.schema.json`
   - **File path pattern**: `dump_config.yaml`

## Architecture

### Export Flow

```
Command → TableConfigResolver → DatabaseDumper → DataFetcher → SqlGenerator → .sql files
```

1. **TableConfigResolver** — resolves YAML config into `TableConfig[]` array
2. **DatabaseDumper** — orchestrates the export process
3. **DataFetcher** — fetches data from DB via `ConnectionRegistry`
4. **SqlGenerator** — generates SQL (TRUNCATE + INSERT + sequence reset)
5. Result is written to `database/dumps/{schema}/{table}.sql`

### Import Flow

```
Command → DatabaseImporter → ProductionGuard → TransactionManager → ScriptExecutor → SqlParser → execution
```

1. **ProductionGuard** — blocks import in production
2. **TransactionManager** — wraps operations in a transaction
3. **ScriptExecutor** — runs `before_exec/` scripts
4. **SqlParser** / **StatementSplitter** — parses .sql files into individual statements
5. Statements are executed against the DB
6. **ScriptExecutor** — runs `after_exec/` scripts

### Platform Abstraction

The package uses `DatabasePlatformInterface` to generate SQL compatible with each DBMS:

| | PostgreSQL | MySQL |
|---|---|---|
| Identifiers | `"table"` (double quotes) | `` `table` `` (backticks) |
| TRUNCATE | `TRUNCATE ... CASCADE` | `SET FOREIGN_KEY_CHECKS=0` |
| Sequences | `setval()` / `pg_get_serial_sequence()` | `ALTER TABLE ... AUTO_INCREMENT` |

The platform is automatically detected from the database connection.

### Source Directory Structure

```
src/
├── Adapter/                          # DB adapters
│   ├── DoctrineDbalAdapter.php       #   Doctrine DBAL
│   └── LaravelDatabaseAdapter.php    #   Laravel DB
├── Bridge/                           # Framework integrations
│   ├── Laravel/
│   │   ├── Command/                  #   Artisan commands
│   │   ├── DatabaseDumpsServiceProvider.php
│   │   └── LaravelLogger.php
│   └── Symfony/
│       ├── Command/                  #   Console commands
│       ├── DependencyInjection/      #   DI extension
│       ├── ConnectionRegistryFactory.php
│       ├── ConsoleLogger.php
│       └── DatabaseDumpsBundle.php
├── Config/                           # Configuration DTOs
│   ├── DumpConfig.php                #   Overall dump configuration
│   ├── EnvironmentConfig.php         #   Environment detection
│   └── TableConfig.php              #   Per-table export settings
├── Contract/                         # Interfaces
│   ├── ConfigLoaderInterface.php
│   ├── ConnectionRegistryInterface.php
│   ├── DatabaseConnectionInterface.php
│   ├── DatabasePlatformInterface.php
│   ├── FileSystemInterface.php
│   └── LoggerInterface.php
├── Exception/                        # Exceptions
├── Platform/                         # SQL dialect abstraction
│   ├── MySqlPlatform.php
│   ├── PostgresPlatform.php
│   └── PlatformFactory.php
├── Service/
│   ├── ConfigGenerator/              # Config auto-generation
│   ├── ConnectionRegistry.php        # Connection registry
│   ├── Dumper/                       # Dump export
│   ├── Generator/                    # SQL generation
│   ├── Importer/                     # Dump import
│   ├── Parser/                       # SQL parsing
│   └── Security/                     # Production guard
└── Util/                             # Utilities
    ├── FileSystemHelper.php
    └── YamlConfigLoader.php
```

## Security

The package includes a production guard that prevents accidental database imports in production environments. Import is blocked when the `APP_ENV` environment variable is set to `prod` or `predprod`.

## Testing

```bash
# All tests
composer test

# Tests with coverage
composer test-coverage

# Static analysis (PHPStan level 8)
composer phpstan

# Code style fix
composer cs-fix
```

## Local Development

To test the package in a local project without Packagist, add to the project's `composer.json`:

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

- PHP ^7.4 | ^8.0
- `symfony/yaml` ^5.4 | ^6.0 | ^7.0
- `symfony/finder` ^5.4 | ^6.0 | ^7.0

**Optional (depending on framework):**

| Dependency | Required for |
|---|---|
| `doctrine/dbal` ^2.13 \| ^3.0 \| ^4.0 | Doctrine DBAL adapter (Symfony) |
| `symfony/console` ^5.4 \| ^6.0 \| ^7.0 | Symfony console commands |
| `symfony/http-kernel` ^5.4 \| ^6.0 \| ^7.0 | Symfony bundle registration |
| `illuminate/support` ^8.0 \| ^9.0 \| ^10.0 \| ^11.0 | Laravel service provider |
| `illuminate/console` ^8.0 \| ^9.0 \| ^10.0 \| ^11.0 | Laravel artisan commands |
| `illuminate/database` ^8.0 \| ^9.0 \| ^10.0 \| ^11.0 | Laravel database adapter |

## License

MIT License. See [LICENSE](LICENSE) for details.

---

Developed by Timur Bayan (BackVista).
