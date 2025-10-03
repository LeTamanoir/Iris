# 🏛️ Typographos

Generate TypeScript types from your PHP classes.

> ⚠️ **Early Development**: This package is in extremely early development and is not yet available on Packagist.

<!-- Packagist badges (uncomment after publishing) -->
<!-- [![Latest Version on Packagist](https://img.shields.io/packagist/v/letamanoir/typographos.svg?style=flat-square)](https://packagist.org/packages/letamanoir/typographos) -->
[![Tests](https://img.shields.io/github/actions/workflow/status/LeTamanoir/Typographos/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/LeTamanoir/Typographos/actions/workflows/run-tests.yml)
[![Lint](https://img.shields.io/github/actions/workflow/status/LeTamanoir/Typographos/run-lint.yml?branch=main&label=lint&style=flat-square)](https://github.com/LeTamanoir/Typographos/actions/workflows/run-lint.yml)
[![Format](https://img.shields.io/github/actions/workflow/status/LeTamanoir/Typographos/run-format.yml?branch=main&label=format&style=flat-square)](https://github.com/LeTamanoir/Typographos/actions/workflows/run-format.yml)
<!-- [![Total Downloads](https://img.shields.io/packagist/dt/letamanoir/typographos.svg?style=flat-square)](https://packagist.org/packages/letamanoir/typographos) -->


## Quick Example

**Turn this PHP class:**
```php
#[TypeScript]
class User
{
    public function __construct(
        public string $name,
        public int $age,
        public ?string $email = null,
    ) {}
}
```

**Into TypeScript types:**
```ts
export interface User {
    name: string
    age: number
    email: string | null
}
```

## Installation

```bash
composer require letamanoir/typographos
```

## Documentation

**📖 [Full Documentation](https://typographos.dev/)**

- [Quick Start](https://typographos.dev/quick-start) - Get up and running in minutes
- [Guide](https://typographos.dev/guide/basic-usage) - Comprehensive usage examples
- [API Reference](https://typographos.dev/api/generator) - Complete method documentation

## Contributing

Please see [CONTRIBUTING](https://github.com/spatie/.github/blob/main/CONTRIBUTING.md) for details.

## Credits

- [Martin Saldinger](https://github.com/LeTamanoir)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
