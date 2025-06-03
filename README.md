# Laravel Msgpack

[![Latest Version on Packagist](https://img.shields.io/packagist/v/smmehdisharifi/laravel-msgpack.svg?style=flat-square)](https://packagist.org/packages/smmehdisharifi/laravel-msgpack)
[![Total Downloads](https://img.shields.io/packagist/dt/smmehdisharifi/laravel-msgpack.svg?style=flat-square)](https://packagist.org/packages/smmehdisharifi/laravel-msgpack)
[![Tests](https://github.com/smmehdisharifi/laravel-msgpack/actions/workflows/run-tests.yml/badge.svg)](https://github.com/smmehdisharifi/laravel-msgpack/actions/workflows/run-tests.yml)

Laravel Msgpack brings [MessagePack](https://msgpack.org/) support to Laravel for fast and compact binary data serialization.  
It adds encoding/decoding utilities, response macros, and a middleware for API requests and responses using Msgpack.

## 🚀 Features

- Encode/Decode any PHP/Laravel data
- `response()->msgpack()` macro like `response()->json()`
- `msgpack` middleware to auto-handle request/response
- Easy Laravel integration via Service Provider
- Works with Laravel >= 9.x

## 📚 Table of Contents

- [📦 Installation](#-installation)
- [⚙️ Configuration (Optional)](#️-configuration-optional)
- [🧠 Basic Usage](#-basic-usage)
    - [Encode / Decode](#encode--decode)
    - [Response Macro](#response-macro)
    - [Middleware](#middleware)
- [🧰 Requirements](#-requirements)
- [📄 License](#-license)
- [✨ Credits](#-credits)
- [🤝 Contributing](#-contributing)
- [📦 Packagist](#-packagist)

## 🧰 Requirements

- PHP 8.1 or higher
- Laravel 9.x or newer

## 📦 Installation

```bash
composer require smmehdisharifi/laravel-msgpack
```
Requires PHP 8.1+ and Laravel 9.x or newer

## ⚙️ Configuration (Optional)

If you want to publish the config file:

```bash
php artisan vendor:publish --tag=msgpack-config
```

## 🧠 Basic Usage

### Encode / Decode

```php
use Msgpack;

$data = ['name' => 'Laravel', 'type' => 'framework'];

$packed = Msgpack::encode($data);
$unpacked = Msgpack::decode($packed);
```

### Response Macro

```php
return response()->msgpack([
    'message' => 'Hello from Msgpack!',
]);
```

Sends a binary response with header:

```bash
Content-Type: application/x-msgpack
```

### Middleware

Register middleware in `app/Http/Kernel.php`:

```php
protected $middlewareAliases = [
    'msgpack' => \SmMehdiSharifi\LaravelMsgpack\Middleware\MsgpackMiddleware::class,
];
```

Apply it to routes:

```php
Route::middleware('msgpack')->post('/api/data', function (Request $request) {
    return response()->msgpack(['received' => $request->all()]);
});
```

## 🧪 Testing
This package includes PHPUnit tests using Orchestra Testbench. To run tests:

```php
composer install
./vendor/bin/phpunit
```

## 📄 License

This package is open-sourced software licensed under the [MIT license](LICENSE).

## ✨ Credits

Made with ❤️ by [Mehdi Sharifi](https://github.com/smmehdisharifi)

Inspired by Laravel’s elegant API response system.

## 🤝 Contributing

Feel free to fork this repo and submit pull requests.

- Found a bug? [Open an issue](https://github.com/smmehdisharifi/laravel-msgpack/issues)
- Have a feature idea? Let’s discuss it!
- PRs with tests are welcome 🙌

## 📦 Packagist

View on Packagist:  
https://packagist.org/packages/smmehdisharifi/laravel-msgpack
