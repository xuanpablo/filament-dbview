# Changelog

All notable changes to `xuanpablo/filament-dbview` will be documented in this file.

## v0.2.0

- Require Filament `^5.0`, Laravel `^13.0`, PHP `^8.3` (older versions dropped).
- Bump `spatie/laravel-package-tools` to `^1.93` for Laravel 13 support.
- Fix composer autoload + provider registration to match the `Xuanpablo\Dbview` namespace used in `src/`.
- Use the v5-idiomatic `Action::schema()` for the `selectTable` header action (was deprecated `->form()`).

## v1.0.0

- Forked from filaforge/filament-database-viewer
- Bump to Filament 5
