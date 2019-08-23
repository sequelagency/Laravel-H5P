

# H5P Plugin in Laravel Framework 

## Description
Fork from Djoudi

## Installation

Require it in the Composer.

```bash
composer require exatech-group/laravel-h5p
```

Publish the Views, Config and so things.

```bash
php artisan vendor:publish
```

Migrate the Database

```bash
php artisan migrate
```

```php
'providers' => [
    Djoudi\LaravelH5p\LaravelH5pServiceProvider::class,
];
```
