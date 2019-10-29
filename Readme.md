# Pimp My Header

Update the headers of a given folder. This tools extracts the command originally available in the PrestaShop Core.

## Installation

Coming soon: This projet should be available on Packagist and being downloadable via Composer, the PHP Package Manager.

```
composer require --dev prestashop/pimp-my-header
```

## Usage

If installed via Composer, the application is available in its binaries folder

```
php vendor/bin/pimp-my-header
```

The default behavior is to apply the OSL license in every compatible file found in the current folder.

Parameters:

```
- Filter folders
- List of file extensions to update
- License block to use
- Configuration file to use
```