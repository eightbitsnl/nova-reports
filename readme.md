# Nova Reports

## Instalation

### Prepare the database

```
php artisan vendor:publish --provider="Eightbitsnl\NovaReports\NovaReportsServiceProvider" --tag="migrations"
php artisan migrate
```