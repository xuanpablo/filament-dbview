# Filament DBView

A plugin that provides database viewing directly in a Filament panel.

## Features

- **Table Browser**: Navigate through all database tables and views
- **Data Viewer**: View table contents with pagination and sorting
- **Schema Explorer**: Examine table structure, columns, and relationships
- **Search & Filter**: Find specific data with advanced filtering options
- **Export Capabilities**: Download table data in multiple formats
- **Relationship Mapping**: Visualize table relationships and foreign keys
- **Query Builder**: Simple query interface for data exploration
- **Performance Monitoring**: Track query execution times
- **Multi-Database Support**: Work with multiple database connections

## Installation

### 1. Install via Composer

```bash
composer require xuanpablo/filament-dbview
```

### 2. Publish & Migrate

```bash
# Publish provider groups (config, views, migrations)
php artisan vendor:publish --provider="Xuanpablo\\Dbview\\Providers\\DatabaseViewerServiceProvider"

# Run migrations
php artisan migrate
```

### 3. Register Plugin

Add the plugin to your Filament panel provider:

```php
use Filament\Panel;

public function panel(Panel $panel): Panel
{
    return $panel
        // ... other configuration
        ->plugin(\Xuanpablo\Dbview\DatabaseViewerPlugin::make());
}
```

## Setup

### Configuration

The plugin will automatically:

- Publish configuration files to `config/database-viewer.php`
- Publish view files to `resources/views/vendor/database-viewer/`
- Publish migration files to `database/migrations/`
- Register necessary routes and middleware

### Database Configuration

Configure the viewer in the published config file:

```php
// config/database-viewer.php
return [
    'connections' => ['mysql', 'pgsql', 'sqlite'],
    'max_rows_per_page' => 100,
    'enable_search' => true,
    'enable_export' => true,
    'allowed_operations' => ['SELECT', 'SHOW', 'DESCRIBE'],
    'cache_results' => true,
    'cache_ttl' => 300, // 5 minutes
];
```

### Environment Variables

Add these to your `.env` file if needed:

```env
DB_VIEWER_ENABLED=true
DB_VIEWER_MAX_ROWS=100
DB_VIEWER_CACHE_TTL=300
```

## Usage

### Accessing the Database Viewer

1. Navigate to your Filament admin panel
2. Look for the "Database Viewer" menu item
3. Start exploring your database structure

### Browsing Tables

1. **Select Connection**: Choose the database connection to explore
2. **Browse Tables**: View all available tables and views
3. **View Structure**: Examine table columns, types, and constraints
4. **Explore Data**: Browse table contents with pagination
5. **Search Data**: Use search and filter options to find specific records

### Data Exploration

1. **Table Navigation**: Switch between different tables
2. **Column Information**: View column details, types, and constraints
3. **Data Sorting**: Sort data by any column
4. **Pagination**: Navigate through large datasets
5. **Export Data**: Download table data in CSV, JSON, or Excel format

### Advanced Features

- **Relationship View**: See foreign key relationships between tables
- **Query Builder**: Build simple queries to explore data
- **Schema Export**: Export table schemas for documentation
- **Performance Stats**: Monitor query performance and execution times

## Troubleshooting

### Common Issues

- **Permission denied**: Ensure the user has database read access
- **Slow performance**: Check pagination settings and enable caching
- **Missing tables**: Verify database connection and user permissions
- **Memory issues**: Reduce max_rows_per_page setting

### Debug Steps

1. Check the plugin configuration:

```bash
php artisan config:show database-viewer
```

1. Verify routes are registered:

```bash
php artisan route:list | grep database-viewer
```

1. Test database connectivity:

```bash
php artisan tinker
# Test database connection manually
```

1. Check database permissions:

```bash
# Verify the database user has SELECT privileges
```

1. Clear caches:

```bash
php artisan optimize:clear
```

1. Check logs for errors:

```bash
tail -f storage/logs/laravel.log
```

### Performance Optimization

- Enable result caching in configuration
- Use appropriate pagination limits
- Optimize database indexes for frequently viewed columns
- Consider read-only database connections for viewing

## Security Considerations

### Access Control

- **Role-based permissions**: Restrict access to authorized users only
- **Read-only access**: Use database users with SELECT privileges only
- **Connection isolation**: Separate viewing connections from application connections
- **Audit logging**: Track all database viewing activities

### Best Practices

- Never expose database viewer to public users
- Use dedicated database users with minimal permissions
- Regularly review and update access controls
- Monitor and log all database access
- Implement query timeouts to prevent long-running queries

## Support

- **Documentation**: [GitHub Repository](https://github.com/xuanpablo/filament-dbview)
- **Issues**: [GitHub Issues](https://github.com/xuanpablo/filament-dbview/issues)
- **Discussions**: [GitHub Discussions](https://github.com/xuanpablo/filament-dbview/discussions)

## Contributing

We welcome contributions! Please see our [Contributing Guide](CONTRIBUTING.md) for details.

## License

This plugin is open-sourced software licensed under the [MIT license](LICENSE).

---

Maintained by xuanpablo
