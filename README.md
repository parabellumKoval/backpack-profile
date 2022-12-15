# Backpack-profile

[![Build Status](https://travis-ci.org/parabellumKoval/backpack-profile.svg?branch=master)](https://travis-ci.org/parabellumKoval/backpack-profile)
[![Coverage Status](https://coveralls.io/repos/github/parabellumKoval/backpack-profile/badge.svg?branch=master)](https://coveralls.io/github/parabellumKoval/backpack-profile?branch=master)

[![Packagist](https://img.shields.io/packagist/v/parabellumKoval/backpack-profile.svg)](https://packagist.org/packages/parabellumKoval/backpack-profile)
[![Packagist](https://poser.pugx.org/parabellumKoval/backpack-profile/d/total.svg)](https://packagist.org/packages/parabellumKoval/backpack-profile)
[![Packagist](https://img.shields.io/packagist/l/parabellumKoval/backpack-profile.svg)](https://packagist.org/packages/parabellumKoval/backpack-profile)

This package provides a quick starter kit for implementing a user profile system for Laravel Backpack. Provides a database, CRUD interface, API routes and more.

## Installation

Install via composer
```bash
composer require parabellumkoval/backpack-profile
```

Migrate
```bash
php artisan migrate
```

### Publish

#### Configuration File
```bash
php artisan vendor:publish --provider="Backpack\Profile\ServiceProvider" --tag="config"
```

#### Views File
```bash
php artisan vendor:publish --provider="Backpack\Profile\ServiceProvider" --tag="views"
```

#### Migrations File
```bash
php artisan vendor:publish --provider="Backpack\Profile\ServiceProvider" --tag="migrations"
```

#### Routes File
```bash
php artisan vendor:publish --provider="Backpack\Profile\ServiceProvider" --tag="routes"
```

## Usage

### Seeders
```bash
php artisan db:seed --class="Backpack\Profile\database\seeders\ProfileSeeder"
```

## Security

If you discover any security related issues, please email 
instead of using the issue tracker.

## Credits

- [](https://github.com/parabellumKoval/backpack-profile)
- [All contributors](https://github.com/parabellumKoval/backpack-profile/graphs/contributors)
