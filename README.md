# Database Dumps Package

[![Tests](https://img.shields.io/badge/tests-passing-brightgreen)]()
[![PHP Version](https://img.shields.io/badge/php-%5E7.4%20%7C%20%5E8.0-blue)]()
[![License](https://img.shields.io/badge/license-MIT-blue.svg)]()

**[Русский](#русский)** | **[English](#english)**

---

<a id="русский"></a>

# Русский

PHP-пакет для экспорта и импорта дампов баз данных в SQL. Поддерживает PostgreSQL и MySQL. Работает с Symfony, Laravel и любым PHP-проектом.

## Оглавление

- [Возможности](#возможности)
- [Установка](#установка)
- [Быстрый старт](#быстрый-старт)
  - [Symfony](#быстрый-старт-symfony)
  - [Laravel](#быстрый-старт-laravel)
- [Конфигурация](#конфигурация)
  - [Полный экспорт (full_export)](#полный-экспорт-full_export)
  - [Частичный экспорт (partial_export)](#частичный-экспорт-partial_export)
  - [Несколько подключений](#несколько-подключений)
  - [Автогенерация конфигурации](#автогенерация-конфигурации)
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
  - [Как работает экспорт](#как-работает-экспорт)
  - [Как работает импорт](#как-работает-импорт)
  - [Различия платформ](#различия-платформ)
  - [Структура исходного кода](#структура-исходного-кода)
- [Безопасность](#безопасность)
- [Тестирование](#тестирование)
- [Локальная разработка](#локальная-разработка)
- [Требования](#требования)
- [Лицензия](#лицензия)

---

## Возможности

- **Не привязан к фреймворку** — работает с Symfony, Laravel и любым PHP-проектом
- **PostgreSQL и MySQL** — автоматически генерирует правильный SQL для каждой СУБД
- **Несколько подключений** — экспорт/импорт сразу из нескольких баз данных
- **Пакетные INSERT** — автоматическая группировка по 1000 строк на выражение
- **Откат при ошибках** — импорт выполняется в транзакции
- **Защита от продакшена** — импорт заблокирован при `APP_ENV=prod`
- **Скрипты до/после** — свои SQL-скрипты до и после импорта
- **Гибкая настройка** — YAML-файл с правилами полного и частичного экспорта
- **Сброс счётчиков** — автоматический сброс sequence / auto-increment после импорта
- **Автогенерация конфига** — команда `prepare-config` создаёт YAML по структуре БД

## Установка

```bash
composer require backvista/database-dumps
```

## Быстрый старт

<a id="быстрый-старт-symfony"></a>

### Symfony

1. Бандл регистрируется автоматически через Symfony Flex.

2. Создайте файл `config/dump_config.yaml`:

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

3. Экспортируйте дампы:

```bash
php bin/console app:dbdump:export all
```

4. Импортируйте дампы:

```bash
php bin/console app:dbdump:import
```

<a id="быстрый-старт-laravel"></a>

### Laravel

1. Сервис-провайдер подключается автоматически. Файл `database/dump_config.yaml` создаётся при первом запуске.

2. Отредактируйте `database/dump_config.yaml` (формат тот же, что и для Symfony).

3. Экспортируйте дампы:

```bash
php artisan dbdump:export all
```

4. Импортируйте дампы:

```bash
php artisan dbdump:import
```

## Конфигурация

Экспорт настраивается через YAML-файл. В нём две секции: `full_export` (все строки) и `partial_export` (с ограничениями).

#### Полный экспорт (full_export)

Экспортирует **все строки** из указанных таблиц:

```yaml
full_export:
  public:          # имя схемы
    - users        # таблицы — все строки
    - roles
  system:
    - settings
```

#### Частичный экспорт (partial_export)

Экспортирует **часть строк** с фильтрацией:

```yaml
partial_export:
  public:
    clients:
      limit: 1000                    # максимум строк
      order_by: created_at DESC      # сортировка
      where: "is_active = true"      # условие WHERE
    orders:
      limit: 5000
      order_by: id DESC
```

**Доступные опции:**

| Опция | Описание |
|-------|----------|
| `limit` | Максимум строк |
| `order_by` | Сортировка (должна заканчиваться на `ASC` или `DESC`) |
| `where` | Условие WHERE |

### Несколько подключений

Если нужно работать с несколькими базами данных, добавьте секцию `connections`:

```yaml
# Основное подключение
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
  analytics:                 # имя подключения (как в настройках фреймворка)
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

**Куда сохраняются дампы:**
- Основное подключение: `database/dumps/{schema}/{table}.sql`
- Именованное подключение: `database/dumps/{connection}/{schema}/{table}.sql`

**Опция `--connection`:**

```bash
# Только основное подключение (по умолчанию)
php artisan dbdump:export all

# Только указанное подключение
php artisan dbdump:export all --connection=analytics

# Все подключения сразу
php artisan dbdump:export all --connection=all
```

### Автогенерация конфигурации

Команда `prepare-config` смотрит на структуру БД и сама создаёт `dump_config.yaml`:

```bash
# Symfony
php bin/console app:dbdump:prepare-config

# Laravel
php artisan dbdump:prepare-config
```

**Опции:**

| Опция | Описание | По умолчанию |
|-------|----------|-------------|
| `--threshold`, `-t` | Порог строк: таблицы с количеством строк <= порога идут в full_export, больше — в partial_export | 500 |
| `--force`, `-f` | Перезаписать файл без подтверждения | — |

**Как распределяются таблицы:**
- Строк <= порога — `full_export`
- Строк > порога — `partial_export` (с limit и автоопределённой сортировкой)
- Пустые таблицы — пропускаются
- Служебные таблицы (migrations, sessions, cache_*, telescope_*, oauth_*, audit_*) — пропускаются

## Настройка Symfony

### Регистрация бандла

Бандл регистрируется автоматически через Symfony Flex. Если нет — добавьте в `config/bundles.php`:

```php
return [
    // ...
    BackVista\DatabaseDumps\Bridge\Symfony\DatabaseDumpsBundle::class => ['all' => true],
];
```

Укажите платформу в `services.yaml`:

```yaml
parameters:
    database_dumps.platform: 'postgresql'  # или 'mysql'
```

<a id="структура-каталогов-symfony"></a>

### Структура каталогов (Symfony)

```
your-symfony-project/
├── config/
│   └── dump_config.yaml          # настройки экспорта
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

# Экспорт одной таблицы
php bin/console app:dbdump:export public.users

# Экспорт только из одной схемы
php bin/console app:dbdump:export all --schema=public

# Экспорт из конкретного подключения
php bin/console app:dbdump:export all --connection=analytics
php bin/console app:dbdump:export all --connection=all

# Импорт всех дампов
php bin/console app:dbdump:import

# Импорт с опциями
php bin/console app:dbdump:import --skip-before --skip-after
php bin/console app:dbdump:import --schema=public
php bin/console app:dbdump:import --connection=all

# Сгенерировать конфигурацию по структуре БД
php bin/console app:dbdump:prepare-config
php bin/console app:dbdump:prepare-config --threshold=1000 --force
```

## Настройка Laravel

### Регистрация провайдера

Сервис-провайдер подключается автоматически. Если нет — зарегистрируйте в `config/app.php`:

```php
'providers' => [
    // ...
    BackVista\DatabaseDumps\Bridge\Laravel\DatabaseDumpsServiceProvider::class,
],
```

### Публикация конфигурации

Чтобы изменить пути, опубликуйте PHP-конфигурацию:

```bash
php artisan vendor:publish --tag=database-dumps-config
```

Появится файл `config/database-dumps.php`:

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

# Экспорт одной таблицы
php artisan dbdump:export public.users

# Экспорт только из одной схемы
php artisan dbdump:export all --schema=public

# Экспорт из конкретного подключения
php artisan dbdump:export all --connection=analytics
php artisan dbdump:export all --connection=all

# Импорт всех дампов
php artisan dbdump:import

# Импорт с опциями
php artisan dbdump:import --skip-before --skip-after
php artisan dbdump:import --schema=public
php artisan dbdump:import --connection=all

# Сгенерировать конфигурацию по структуре БД
php artisan dbdump:prepare-config
php artisan dbdump:prepare-config --threshold=1000 --force
```

## Скрипты before/after

Можно выполнять свои SQL-скрипты до и после импорта.

| Каталог | Когда выполняется |
|---------|-------------------|
| `database/before_exec/` | **до** импорта дампов |
| `database/after_exec/` | **после** импорта дампов |

Скрипты выполняются в **алфавитном порядке**. Используйте числовые префиксы для управления очерёдностью:

```
database/before_exec/
├── 01_disable_triggers.sql
├── 02_prepare_temp.sql
database/after_exec/
├── 01_enable_triggers.sql
├── 02_refresh_views.sql
```

Чтобы пропустить скрипты, используйте `--skip-before` и `--skip-after`:

```bash
php artisan dbdump:import --skip-before
php artisan dbdump:import --skip-after
php artisan dbdump:import --skip-before --skip-after
```

## Поддержка IDE (JSON Schema)

В пакете есть JSON Schema для `dump_config.yaml` — файл `resources/dump_config.schema.json`. Он даёт автодополнение и валидацию в PHPStorm и других IDE.

### Вариант 1: YAML-комментарий (рекомендуется)

Добавьте в начало `dump_config.yaml`:

```yaml
# yaml-language-server: $schema=../vendor/backvista/database-dumps/resources/dump_config.schema.json
```

> Путь указывается относительно файла: для Symfony — относительно `config/`, для Laravel — относительно `database/`.

### Вариант 2: Настройка PHPStorm вручную

1. Откройте **Settings > Languages & Frameworks > Schemas and DTDs > JSON Schema Mappings**
2. Добавьте маппинг:
   - **Schema file**: `vendor/backvista/database-dumps/resources/dump_config.schema.json`
   - **File path pattern**: `dump_config.yaml`

## Архитектура

### Как работает экспорт

```
Команда → TableConfigResolver → DatabaseDumper → DataFetcher → SqlGenerator → .sql файлы
```

1. **TableConfigResolver** — читает YAML и собирает список таблиц для экспорта
2. **DatabaseDumper** — управляет процессом экспорта
3. **DataFetcher** — получает данные из БД через `ConnectionRegistry`
4. **SqlGenerator** — генерирует SQL: TRUNCATE + INSERT + сброс счётчиков
5. Результат сохраняется в `database/dumps/{schema}/{table}.sql`

### Как работает импорт

```
Команда → DatabaseImporter → ProductionGuard → TransactionManager → ScriptExecutor → SqlParser → выполнение
```

1. **ProductionGuard** — проверяет, что мы не на продакшене
2. **TransactionManager** — оборачивает всё в транзакцию
3. **ScriptExecutor** — выполняет скрипты из `before_exec/`
4. **SqlParser** / **StatementSplitter** — разбирает .sql файлы на отдельные выражения
5. Выражения выполняются в БД
6. **ScriptExecutor** — выполняет скрипты из `after_exec/`

### Различия платформ

Пакет сам генерирует правильный SQL в зависимости от СУБД:

| | PostgreSQL | MySQL |
|---|---|---|
| Имена таблиц | `"table"` (двойные кавычки) | `` `table` `` (обратные кавычки) |
| TRUNCATE | `TRUNCATE ... CASCADE` | `SET FOREIGN_KEY_CHECKS=0` |
| Счётчики | `setval()` / `pg_get_serial_sequence()` | `ALTER TABLE ... AUTO_INCREMENT` |

Платформа определяется автоматически по подключению к БД.

<a id="структура-исходного-кода"></a>

### Структура исходного кода

```
src/
├── Adapter/                          # Адаптеры подключений к БД
│   ├── DoctrineDbalAdapter.php       #   Doctrine DBAL
│   └── LaravelDatabaseAdapter.php    #   Laravel DB
├── Bridge/                           # Интеграции с фреймворками
│   ├── Laravel/
│   │   ├── Command/                  #   Artisan-команды
│   │   ├── DatabaseDumpsServiceProvider.php
│   │   └── LaravelLogger.php
│   └── Symfony/
│       ├── Command/                  #   Console-команды
│       ├── DependencyInjection/
│       ├── ConnectionRegistryFactory.php
│       ├── ConsoleLogger.php
│       └── DatabaseDumpsBundle.php
├── Config/                           # Классы конфигурации
│   ├── DumpConfig.php                #   Общие настройки дампов
│   ├── EnvironmentConfig.php         #   Определение окружения
│   └── TableConfig.php              #   Настройки экспорта таблицы
├── Contract/                         # Интерфейсы
├── Exception/                        # Исключения
├── Platform/                         # Поддержка SQL-диалектов
│   ├── MySqlPlatform.php
│   ├── PostgresPlatform.php
│   └── PlatformFactory.php
├── Service/
│   ├── ConfigGenerator/              # Автогенерация конфигурации
│   ├── ConnectionRegistry.php        # Реестр подключений
│   ├── Dumper/                       # Экспорт дампов
│   ├── Generator/                    # Генерация SQL
│   ├── Importer/                     # Импорт дампов
│   ├── Parser/                       # Разбор SQL
│   └── Security/                     # Защита от продакшена
└── Util/
    ├── FileSystemHelper.php
    └── YamlConfigLoader.php
```

## Безопасность

Пакет не позволяет случайно импортировать дампы на продакшен. Импорт заблокирован, когда переменная окружения `APP_ENV` равна `prod` или `predprod`.

## Тестирование

```bash
# Все тесты
composer test

# Тесты с покрытием кода
composer test-coverage

# Статический анализ (PHPStan level 8)
composer phpstan

# Исправление стиля кода
composer cs-fix
```

## Локальная разработка

Чтобы подключить пакет из локальной папки (без Packagist), добавьте в `composer.json` вашего проекта:

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

Затем выполните `composer update backvista/database-dumps` — Composer создаст симлинк на локальный пакет.

## Требования

**Обязательные:**

- PHP ^7.4 | ^8.0
- `symfony/yaml` ^5.4 | ^6.0 | ^7.0
- `symfony/finder` ^5.4 | ^6.0 | ^7.0

**Опциональные (зависят от фреймворка):**

| Зависимость | Для чего нужна |
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

PHP package for exporting and importing database dumps as SQL. Supports PostgreSQL and MySQL. Works with Symfony, Laravel, and any PHP project.

## Table of Contents

- [Features](#features)
- [Installation](#installation)
- [Quick Start](#quick-start)
  - [Symfony](#quick-start-symfony)
  - [Laravel](#quick-start-laravel)
- [Configuration](#configuration)
  - [Full Export](#full-export)
  - [Partial Export](#partial-export)
  - [Multiple Connections](#multiple-connections)
  - [Auto-generate Configuration](#auto-generate-configuration)
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
  - [How Export Works](#how-export-works)
  - [How Import Works](#how-import-works)
  - [Platform Differences](#platform-differences)
  - [Source Directory Structure](#source-directory-structure)
- [Security](#security)
- [Testing](#testing)
- [Local Development](#local-development)
- [Requirements](#requirements)
- [License](#license)

---

## Features

- **No framework lock-in** — works with Symfony, Laravel, and any PHP project
- **PostgreSQL & MySQL** — automatically generates the right SQL for each database
- **Multiple connections** — export/import from several databases at once
- **Batched INSERTs** — automatically groups rows (1000 per statement)
- **Rollback on errors** — import runs inside a transaction
- **Production guard** — import is blocked when `APP_ENV=prod`
- **Before/After scripts** — run custom SQL before and after import
- **Flexible config** — YAML file with full and partial export rules
- **Sequence reset** — automatic sequence / auto-increment reset after import
- **Auto-generate config** — `prepare-config` command creates YAML from DB structure

## Installation

```bash
composer require backvista/database-dumps
```

<a id="quick-start"></a>

## Quick Start

<a id="quick-start-symfony"></a>

### Symfony

1. The bundle registers automatically via Symfony Flex.

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

3. Export dumps:

```bash
php bin/console app:dbdump:export all
```

4. Import dumps:

```bash
php bin/console app:dbdump:import
```

<a id="quick-start-laravel"></a>

### Laravel

1. The service provider is discovered automatically. The file `database/dump_config.yaml` is created on first run.

2. Edit `database/dump_config.yaml` (same format as Symfony).

3. Export dumps:

```bash
php artisan dbdump:export all
```

4. Import dumps:

```bash
php artisan dbdump:import
```

## Configuration

Export is configured via a YAML file with two sections: `full_export` (all rows) and `partial_export` (with limits).

#### Full Export

Exports **all rows** from listed tables:

```yaml
full_export:
  public:          # schema name
    - users        # tables — all rows
    - roles
  system:
    - settings
```

#### Partial Export

Exports a **limited number of rows** with filtering:

```yaml
partial_export:
  public:
    clients:
      limit: 1000                    # max rows
      order_by: created_at DESC      # sorting
      where: "is_active = true"      # WHERE condition
    orders:
      limit: 5000
      order_by: id DESC
```

**Available options:**

| Option | Description |
|--------|-------------|
| `limit` | Max rows |
| `order_by` | Sorting (must end with `ASC` or `DESC`) |
| `where` | WHERE condition |

### Multiple Connections

To work with several databases, add a `connections` section:

```yaml
# Main connection
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
  analytics:                 # connection name (as in framework config)
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

**Where dumps are saved:**
- Main connection: `database/dumps/{schema}/{table}.sql`
- Named connection: `database/dumps/{connection}/{schema}/{table}.sql`

**The `--connection` option:**

```bash
# Main connection only (default)
php artisan dbdump:export all

# Specific connection only
php artisan dbdump:export all --connection=analytics

# All connections at once
php artisan dbdump:export all --connection=all
```

### Auto-generate Configuration

The `prepare-config` command looks at your DB structure and creates `dump_config.yaml` for you:

```bash
# Symfony
php bin/console app:dbdump:prepare-config

# Laravel
php artisan dbdump:prepare-config
```

**Options:**

| Option | Description | Default |
|--------|-------------|---------|
| `--threshold`, `-t` | Row threshold: tables with rows <= threshold go to full_export, more — to partial_export | 500 |
| `--force`, `-f` | Overwrite file without asking | — |

**How tables are sorted:**
- Rows <= threshold — `full_export`
- Rows > threshold — `partial_export` (with limit and auto-detected sorting)
- Empty tables — skipped
- Service tables (migrations, sessions, cache_*, telescope_*, oauth_*, audit_*) — skipped

## Symfony Setup

### Bundle Registration

The bundle registers automatically via Symfony Flex. If not, add to `config/bundles.php`:

```php
return [
    // ...
    BackVista\DatabaseDumps\Bridge\Symfony\DatabaseDumpsBundle::class => ['all' => true],
];
```

Set the platform in `services.yaml`:

```yaml
parameters:
    database_dumps.platform: 'postgresql'  # or 'mysql'
```

<a id="directory-structure-symfony"></a>

### Directory Structure (Symfony)

```
your-symfony-project/
├── config/
│   └── dump_config.yaml          # export settings
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

# Export one table
php bin/console app:dbdump:export public.users

# Export from one schema only
php bin/console app:dbdump:export all --schema=public

# Export from a specific connection
php bin/console app:dbdump:export all --connection=analytics
php bin/console app:dbdump:export all --connection=all

# Import all dumps
php bin/console app:dbdump:import

# Import with options
php bin/console app:dbdump:import --skip-before --skip-after
php bin/console app:dbdump:import --schema=public
php bin/console app:dbdump:import --connection=all

# Generate config from DB structure
php bin/console app:dbdump:prepare-config
php bin/console app:dbdump:prepare-config --threshold=1000 --force
```

## Laravel Setup

### Provider Registration

The service provider is discovered automatically. If not, register it in `config/app.php`:

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

# Export one table
php artisan dbdump:export public.users

# Export from one schema only
php artisan dbdump:export all --schema=public

# Export from a specific connection
php artisan dbdump:export all --connection=analytics
php artisan dbdump:export all --connection=all

# Import all dumps
php artisan dbdump:import

# Import with options
php artisan dbdump:import --skip-before --skip-after
php artisan dbdump:import --schema=public
php artisan dbdump:import --connection=all

# Generate config from DB structure
php artisan dbdump:prepare-config
php artisan dbdump:prepare-config --threshold=1000 --force
```

## Before/After Scripts

You can run custom SQL scripts before and after import.

| Directory | When it runs |
|-----------|-------------|
| `database/before_exec/` | **before** importing dumps |
| `database/after_exec/` | **after** importing dumps |

Scripts run in **alphabetical order**. Use numeric prefixes to control the order:

```
database/before_exec/
├── 01_disable_triggers.sql
├── 02_prepare_temp.sql
database/after_exec/
├── 01_enable_triggers.sql
├── 02_refresh_views.sql
```

To skip scripts, use `--skip-before` and `--skip-after`:

```bash
php artisan dbdump:import --skip-before
php artisan dbdump:import --skip-after
php artisan dbdump:import --skip-before --skip-after
```

## IDE Support (JSON Schema)

The package includes a JSON Schema for `dump_config.yaml` at `resources/dump_config.schema.json`. It provides autocompletion and validation in PHPStorm and other IDEs.

### Option 1: YAML comment (recommended)

Add to the top of your `dump_config.yaml`:

```yaml
# yaml-language-server: $schema=../vendor/backvista/database-dumps/resources/dump_config.schema.json
```

> The path is relative to the file: for Symfony — relative to `config/`, for Laravel — relative to `database/`.

### Option 2: PHPStorm manual setup

1. Open **Settings > Languages & Frameworks > Schemas and DTDs > JSON Schema Mappings**
2. Add a mapping:
   - **Schema file**: `vendor/backvista/database-dumps/resources/dump_config.schema.json`
   - **File path pattern**: `dump_config.yaml`

## Architecture

### How Export Works

```
Command → TableConfigResolver → DatabaseDumper → DataFetcher → SqlGenerator → .sql files
```

1. **TableConfigResolver** — reads YAML and builds a list of tables to export
2. **DatabaseDumper** — manages the export process
3. **DataFetcher** — fetches data from the DB via `ConnectionRegistry`
4. **SqlGenerator** — generates SQL: TRUNCATE + INSERT + counter reset
5. Result is saved to `database/dumps/{schema}/{table}.sql`

### How Import Works

```
Command → DatabaseImporter → ProductionGuard → TransactionManager → ScriptExecutor → SqlParser → execution
```

1. **ProductionGuard** — checks we're not on production
2. **TransactionManager** — wraps everything in a transaction
3. **ScriptExecutor** — runs scripts from `before_exec/`
4. **SqlParser** / **StatementSplitter** — splits .sql files into individual statements
5. Statements are executed against the DB
6. **ScriptExecutor** — runs scripts from `after_exec/`

### Platform Differences

The package generates the right SQL depending on the database:

| | PostgreSQL | MySQL |
|---|---|---|
| Table names | `"table"` (double quotes) | `` `table` `` (backticks) |
| TRUNCATE | `TRUNCATE ... CASCADE` | `SET FOREIGN_KEY_CHECKS=0` |
| Counters | `setval()` / `pg_get_serial_sequence()` | `ALTER TABLE ... AUTO_INCREMENT` |

The platform is detected automatically from the DB connection.

<a id="source-directory-structure"></a>

### Source Directory Structure

```
src/
├── Adapter/                          # DB connection adapters
│   ├── DoctrineDbalAdapter.php       #   Doctrine DBAL
│   └── LaravelDatabaseAdapter.php    #   Laravel DB
├── Bridge/                           # Framework integrations
│   ├── Laravel/
│   │   ├── Command/                  #   Artisan commands
│   │   ├── DatabaseDumpsServiceProvider.php
│   │   └── LaravelLogger.php
│   └── Symfony/
│       ├── Command/                  #   Console commands
│       ├── DependencyInjection/
│       ├── ConnectionRegistryFactory.php
│       ├── ConsoleLogger.php
│       └── DatabaseDumpsBundle.php
├── Config/                           # Configuration classes
│   ├── DumpConfig.php                #   Overall dump settings
│   ├── EnvironmentConfig.php         #   Environment detection
│   └── TableConfig.php              #   Per-table export settings
├── Contract/                         # Interfaces
├── Exception/                        # Exceptions
├── Platform/                         # SQL dialect support
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
└── Util/
    ├── FileSystemHelper.php
    └── YamlConfigLoader.php
```

## Security

The package prevents accidental imports on production. Import is blocked when the `APP_ENV` environment variable is `prod` or `predprod`.

## Testing

```bash
# All tests
composer test

# Tests with code coverage
composer test-coverage

# Static analysis (PHPStan level 8)
composer phpstan

# Code style fix
composer cs-fix
```

## Local Development

To use the package from a local folder (without Packagist), add to your project's `composer.json`:

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

Then run `composer update backvista/database-dumps` — Composer will create a symlink to the local package.

## Requirements

**Required:**

- PHP ^7.4 | ^8.0
- `symfony/yaml` ^5.4 | ^6.0 | ^7.0
- `symfony/finder` ^5.4 | ^6.0 | ^7.0

**Optional (depends on framework):**

| Dependency | What it's for |
|---|---|
| `doctrine/dbal` ^2.13 \| ^3.0 \| ^4.0 | Doctrine DBAL adapter (Symfony) |
| `symfony/console` ^5.4 \| ^6.0 \| ^7.0 | Symfony console commands |
| `symfony/http-kernel` ^5.4 \| ^6.0 \| ^7.0 | Symfony bundle registration |
| `illuminate/support` ^8.0 \| ^9.0 \| ^10.0 \| ^11.0 | Laravel service provider |
| `illuminate/console` ^8.0 \| ^9.0 \| ^10.0 \| ^11.0 | Laravel artisan commands |
| `illuminate/database` ^8.0 \| ^9.0 \| ^10.0 \| ^11.0 | Laravel DB adapter |

## License

MIT License. See [LICENSE](LICENSE) for details.

---

Developed by Timur Bayan (BackVista).
