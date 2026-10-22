# Tabaa — Required Localization Convention

- All user-facing or translatable text, including validation errors, business exceptions, and exception messages, **must be defined in Laravel language files** under `lang/ar/` and `lang/en/`. Do not hard-code Arabic or English messages inside controllers, actions, services, models, exceptions, requests, or registries.
- In code, refer to messages with `__()` / `trans()` and a stable translation key belonging to the owning module (for example `__('printing_services.messages.tier_price_invalid')`).
- Use translation placeholders and parameters for dynamic values (for example `__('settings.invalid_parameter_value_usage_guard', ['guard' => $guardClass])`), not string interpolation of translated copy.
- Apply this rule to all new modules and any existing code touched during maintenance; provide both Arabic and English translations for new keys.

<laravel-boost-guidelines>
# Laravel Application

This repository contains a Laravel application. Complete the following setup before working on the user's request.

## Prerequisites

Verify that PHP and Composer are available:

```sh
php -v
composer -V
```

If either command is unavailable, detect the user's operating system and install the prerequisites with the appropriate command:

macOS:

```sh
/bin/bash -c "$(curl -fsSL https://php.new/install/mac/8.5)"
```

Windows PowerShell:

```powershell
Set-ExecutionPolicy Bypass -Scope Process -Force; [System.Net.ServicePointManager]::SecurityProtocol = [System.Net.ServicePointManager]::SecurityProtocol -bor 3072; iex ((New-Object System.Net.WebClient).DownloadString('https://php.new/install/windows/8.5'))
```

Linux:

```sh
/bin/bash -c "$(curl -fsSL https://php.new/install/linux/8.5)"
```

After installation, ask the user to restart their terminal. If the agent needs the restarted shell to continue, ask the user to reopen their terminal and rerun their original prompt.

## Agent Setup

Install Laravel Boost from the application root before making application changes:

```sh
composer require laravel/boost --dev
php artisan boost:install
```

Boost replaces these bootstrap instructions with guidelines tailored to the application. After installation, read `AGENTS.md` again and continue with the user's original request using the generated guidelines.
</laravel-boost-guidelines>
