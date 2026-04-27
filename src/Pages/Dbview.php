<?php

namespace Xuanpablo\Dbview\Pages;

use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema as DBSchema;

class Dbview extends Page implements HasForms, HasTable
{
    use InteractsWithForms, InteractsWithTable;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-circle-stack';

    protected static ?string $navigationLabel = 'Database Viewer';

    protected static ?string $title = 'Database Viewer';

    protected static string|\UnitEnum|null $navigationGroup = 'System';

    protected string $view = 'database-viewer::pages.database-viewer';

    public array $data = [];

    public ?string $selectedConnection = null;

    public ?string $selectedTable = null;

    public array $tableData = [];

    public array $tableColumns = [];

    public int $currentPage = 1;

    public int $perPage = 50;

    public int $totalRecords = 0;

    public ?string $queryResult = null;

    public ?string $selectedStructureTable = null;

    public array $tableStructure = [];

    public string $currentView = 'content'; // 'content' or 'structure'

    public ?string $activeTable = null;

    public array $dynamicColumns = [];

    public array $visibleColumns = [];

    public $tableRecords = [];

    public function getHeading(): Htmlable|string
    {
        return 'Database Viewer';
    }

    public function mount(): void
    {
        $this->form->fill();
        $this->loadDefaultTable();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->columns($this->getTableColumns())
            ->filters([
                SelectFilter::make('columns')
                    ->label('Show Columns')
                    ->multiple()
                    ->options($this->getColumnOptions())
                    ->default($this->dynamicColumns)
                    ->query(function (Builder $query, array $data) {
                        if (! empty($data['values'])) {
                            $this->visibleColumns = $data['values'];
                        } else {
                            $this->visibleColumns = $this->dynamicColumns;
                        }

                        return $query;
                    }),
            ])
            ->defaultSort($this->getDefaultSortColumn())
            // Move controls into the header toolbar alongside search
            ->headerActions([
                Action::make('selectTable')
                    ->label($this->activeTable ? 'Table: '.$this->activeTable : 'Select Table')
                    ->icon('heroicon-m-table-cells')
                    ->color('gray')
                    ->outlined()
                    ->size('sm')
                    ->schema([
                        Select::make('table')
                            ->label('Select Table')
                            ->options(fn () => $this->getAllTables())
                            ->required()
                            ->default($this->activeTable)
                            ->searchable(),
                    ])
                    ->action(function (array $data) {
                        if (! empty($data['table'])) {
                            $this->switchTable($data['table']);
                            if (method_exists($this, 'resetTable')) {
                                $this->resetTable();
                            }
                            $this->dispatch('$refresh');
                        }
                    }),

                Action::make('toggleStructure')
                    ->label($this->currentView === 'structure' ? 'View Data' : 'View Structure')
                    ->icon($this->currentView === 'structure' ? 'heroicon-m-table-cells' : 'heroicon-m-cog-6-tooth')
                    ->color('gray')
                    ->outlined()
                    ->size('sm')
                    ->action(fn () => $this->toggleStructureView())
                    ->visible(fn () => ! empty($this->activeTable)),

                Action::make('refresh')
                    ->label('Refresh')
                    ->icon('heroicon-m-arrow-path')
                    ->color('gray')
                    ->outlined()
                    ->size('sm')
                    ->action(fn () => $this->refreshTable())
                    ->visible(fn () => ! empty($this->activeTable)),
            ])
            // Position API not available; keep actions inline and align via CSS in blade
            ->searchable()
            ->emptyStateIcon('heroicon-o-table-cells')
            ->emptyStateHeading('No Table Selected')
            ->emptyStateDescription('Please select a table to view its data.')
            ->paginated([10, 25, 50, 100]);
    }

    protected function getTableQuery()
    {
        if (! $this->activeTable) {
            // Return empty eloquent query
            $model = $this->createDynamicModel('empty_table');

            return $model->newQuery()->whereRaw('1 = 0');
        }

        $model = $this->createDynamicModel($this->activeTable);

        return $model->newQuery();
    }

    protected function createDynamicModel(string $tableName): Model
    {
        $connection = $this->getConnectionName();

        $model = new class extends Model
        {
            protected $guarded = [];

            public $timestamps = false;

            public $incrementing = false;

            protected $keyType = 'string';
        };

        $model->setTable($tableName);
        $model->setConnection($connection);

        // Set primary key to first column if no id column exists
        $columns = DBSchema::connection($connection)->getColumnListing($tableName);
        if (! empty($columns) && ! in_array('id', $columns)) {
            $model->setKeyName($columns[0]);
        }

        return $model;
    }

    protected function getDefaultSortColumn(): ?string
    {
        if (empty($this->dynamicColumns)) {
            return null;
        }

        // Look for common primary key column names
        $primaryColumns = ['id', 'uuid', 'primary_key'];
        foreach ($primaryColumns as $column) {
            if (in_array($column, $this->dynamicColumns)) {
                return $column;
            }
        }

        // Look for timestamp columns for sorting
        $timestampColumns = ['created_at', 'updated_at', 'date_created', 'timestamp'];
        foreach ($timestampColumns as $column) {
            if (in_array($column, $this->dynamicColumns)) {
                return $column;
            }
        }

        // Fallback to first column
        return $this->dynamicColumns[0] ?? null;
    }

    protected function getTableColumns(): array
    {
        if (empty($this->dynamicColumns)) {
            return [
                TextColumn::make('placeholder')
                    ->label('No columns available')
                    ->state('Select a table to view data'),
            ];
        }

        // Use visible columns if set, otherwise use all columns
        $columnsToShow = ! empty($this->visibleColumns) ? $this->visibleColumns : $this->dynamicColumns;

        $columns = [];
        foreach ($columnsToShow as $column) {
            // Make sure the column exists in the table
            if (in_array($column, $this->dynamicColumns)) {
                $columns[] = TextColumn::make($column)
                    ->label(ucfirst(str_replace('_', ' ', $column)))
                    ->sortable()
                    ->searchable()
                    ->formatStateUsing(function ($state) {
                        if ($state === null) {
                            return 'NULL';
                        }
                        if (is_bool($state)) {
                            return $state ? 'true' : 'false';
                        }
                        if (is_string($state) && strlen($state) > 50) {
                            return substr($state, 0, 50).'...';
                        }

                        return $state;
                    })
                    ->tooltip(function ($state) {
                        if (is_string($state) && strlen($state) > 50) {
                            return $state;
                        }

                        return null;
                    });
            }
        }

        return $columns;
    }

    public function loadDefaultTable(): void
    {
        try {
            // Set default connection if not set
            if (empty($this->data['connection'])) {
                $this->data['connection'] = config('database.default');
            }

            $tables = $this->getAllTables();
            if (! empty($tables)) {
                // Try to use 'users' table as default, otherwise use first available
                $defaultTable = array_key_exists('users', $tables) ? 'users' : array_key_first($tables);
                $this->switchTable($defaultTable);
                // Ensure all columns are visible by default
                $this->visibleColumns = $this->dynamicColumns;
                // Persist selection for header dropdown and form state
                $this->activeTable = $defaultTable;
                $this->data['table'] = $defaultTable;
            }
        } catch (\Exception $e) {
            // Silently handle case where no connection is available
        }
    }

    public function switchTable(string $tableName): void
    {
        try {
            $connection = $this->getConnectionName();
            $this->activeTable = $tableName;
            $this->dynamicColumns = DBSchema::connection($connection)->getColumnListing($tableName);
            $this->visibleColumns = $this->dynamicColumns; // Show all columns by default
            $this->selectedTable = $tableName;
            $this->data['table'] = $tableName;
            $this->currentView = 'content'; // Reset to content view

            // Clear previous data
            $this->tableData = [];
            $this->currentPage = 1;
            $this->totalRecords = DB::connection($connection)->table($tableName)->count();

        } catch (\Exception $e) {
            Notification::make()
                ->title('Error loading table')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function refreshTable(): void
    {
        if ($this->activeTable) {
            $this->switchTable($this->activeTable);
        }
    }

    public function toggleStructureView(): void
    {
        $this->currentView = $this->currentView === 'structure' ? 'content' : 'structure';
        if ($this->currentView === 'structure' && $this->activeTable) {
            $this->loadTableStructure($this->activeTable);
        }
    }

    public function getTableRecordKey($record): string
    {
        if (is_object($record)) {
            // Try to get the primary key
            $keyName = $this->getModelKeyName();
            if ($keyName && isset($record->{$keyName})) {
                return (string) $record->{$keyName};
            }

            // Fallback to first available column value
            if (! empty($this->dynamicColumns)) {
                $firstColumn = $this->dynamicColumns[0];
                if (isset($record->{$firstColumn})) {
                    return (string) $record->{$firstColumn};
                }
            }
        }

        // Fallback to a hash of the record
        if (is_array($record)) {
            return md5(serialize($record));
        }

        return (string) spl_object_hash($record);
    }

    protected function getModelKeyName(): ?string
    {
        if (! $this->activeTable || empty($this->dynamicColumns)) {
            return null;
        }

        // Look for common primary key column names
        $primaryColumns = ['id', 'uuid', 'primary_key'];
        foreach ($primaryColumns as $column) {
            if (in_array($column, $this->dynamicColumns)) {
                return $column;
            }
        }

        // Return first column as fallback
        return $this->dynamicColumns[0] ?? null;
    }

    protected function getColumnOptions(): array
    {
        if (empty($this->dynamicColumns)) {
            return [];
        }

        $options = [];
        foreach ($this->dynamicColumns as $column) {
            $options[$column] = ucfirst(str_replace('_', ' ', $column));
        }

        return $options;
    }

    public function form(Schema $schema): Schema
    {
        $connections = $this->getAvailableConnections();

        return $schema
            ->components([
                Select::make('connection')
                    ->label('Database Connection')
                    ->options($connections)
                    ->default(config('database.default'))
                    ->reactive()
                    ->afterStateUpdated(function ($state) {
                        $this->selectedConnection = $state;
                        $this->selectedTable = null;
                        $this->activeTable = null;
                        $this->dynamicColumns = [];
                        $this->visibleColumns = [];
                        $this->tableData = [];
                        $this->tableColumns = [];
                        $this->data['table'] = null;

                        // Load first table from new connection
                        $this->loadDefaultTable();
                    }),

                Select::make('table')
                    ->label('Table')
                    ->options(function () {
                        return $this->getAllTables();
                    })
                    ->searchable()
                    ->reactive()
                    ->afterStateUpdated(function ($state) {
                        $this->selectedTable = $state;
                        $this->tableColumns = [];
                        $this->tableData = [];
                        $this->currentPage = 1;
                        $this->totalRecords = 0;
                        $this->currentView = 'content';

                        // Only get basic info without loading data
                        if ($state) {
                            try {
                                $connection = $this->getConnectionName();
                                $this->tableColumns = DBSchema::connection($connection)->getColumnListing($state);
                                $this->totalRecords = DB::connection($connection)->table($state)->count();
                            } catch (\Exception $e) {
                                // Silent fail
                            }
                        }
                    })
                    ->disabled(fn () => empty($this->data['connection'])),

                Select::make('preset_query')
                    ->label('Preset Queries')
                    ->placeholder('Select a preset query...')
                    ->options(fn () => $this->getPresetQueries())
                    ->reactive()
                    ->afterStateUpdated(function ($state) {
                        if ($state) {
                            $this->data['sql'] = $state;
                            $this->data['preset_query'] = null; // Clear selection after use
                        }
                    }),

                Textarea::make('sql')
                    ->label('SQL Query')
                    ->placeholder('SELECT * FROM users LIMIT 10')
                    ->rows(4)
                    ->helperText('Only SELECT queries are allowed for security.'),
            ])
            ->statePath('data');
    }

    public function loadTable(?string $tableName): void
    {
        if (! $tableName) {
            $this->selectedTable = null;
            $this->tableData = [];
            $this->tableColumns = [];

            return;
        }

        try {
            $connection = $this->getConnectionName();
            $this->selectedTable = $tableName;
            $this->currentPage = 1;

            // Get table columns
            $this->tableColumns = DBSchema::connection($connection)->getColumnListing($tableName);

            // Get total count
            $this->totalRecords = DB::connection($connection)->table($tableName)->count();

            // Get paginated data
            $this->loadTableData();

        } catch (\Exception $e) {
            Notification::make()
                ->title('Error loading table')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function loadTableData(): void
    {
        if (! $this->selectedTable) {
            return;
        }

        try {
            $connection = $this->getConnectionName();
            $offset = ($this->currentPage - 1) * $this->perPage;

            $this->tableData = DB::connection($connection)->table($this->selectedTable)
                ->offset($offset)
                ->limit($this->perPage)
                ->get()
                ->toArray();

        } catch (\Exception $e) {
            Notification::make()
                ->title('Error loading table data')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function executeQuery(): void
    {
        $sql = trim($this->data['sql'] ?? '');

        if (empty($sql)) {
            Notification::make()
                ->title('SQL query is required')
                ->danger()
                ->send();

            return;
        }

        // Basic security check - only allow SELECT statements
        if (! preg_match('/^\s*SELECT\s+/i', $sql)) {
            Notification::make()
                ->title('Only SELECT queries are allowed')
                ->danger()
                ->send();

            return;
        }

        try {
            $connection = $this->getConnectionName();
            $results = DB::connection($connection)->select($sql);

            if (empty($results)) {
                $this->queryResult = 'No results found.';
            } else {
                // Convert to array and format as table
                $this->queryResult = $this->formatQueryResults($results);
            }

            Notification::make()
                ->title('Query executed successfully')
                ->success()
                ->send();

        } catch (QueryException $e) {
            $this->queryResult = 'Error: '.$e->getMessage();
            Notification::make()
                ->title('Query failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function nextPage(): void
    {
        if ($this->hasNextPage()) {
            $this->currentPage++;
            $this->loadTableData();
        }
    }

    public function previousPage(): void
    {
        if ($this->currentPage > 1) {
            $this->currentPage--;
            $this->loadTableData();
        }
    }

    public function hasNextPage(): bool
    {
        return ($this->currentPage * $this->perPage) < $this->totalRecords;
    }

    public function getTotalPages(): int
    {
        return (int) ceil($this->totalRecords / $this->perPage);
    }

    public function getAvailableConnections(): array
    {
        $connections = [];
        $databaseConfig = config('database.connections', []);

        foreach ($databaseConfig as $name => $config) {
            if (isset($config['driver'])) {
                $label = ucfirst($name).' ('.$config['driver'].')';
                $connections[$name] = $label;
            }
        }

        return $connections;
    }

    public function getConnectionName(): string
    {
        return $this->data['connection'] ?? config('database.default');
    }

    public function getPresetQueries(): array
    {
        $connection = $this->getConnectionName();
        $driverName = config("database.connections.{$connection}.driver", 'mysql');
        $selectedTable = $this->data['table'] ?? null;

        $queries = [];

        // Table-specific queries if a table is selected
        if ($selectedTable) {
            $queries["SELECT * FROM {$selectedTable}"] = "Show all from {$selectedTable}";
            $queries["SELECT COUNT(*) as total FROM {$selectedTable}"] = "Count {$selectedTable} records";
            $queries["SELECT * FROM {$selectedTable} LIMIT 10"] = "Show first 10 from {$selectedTable}";
            $queries["SELECT * FROM {$selectedTable} ORDER BY id DESC LIMIT 10"] = "Show latest 10 from {$selectedTable}";

            // Add table-specific structure queries
            if ($driverName === 'sqlite') {
                $queries["PRAGMA table_info({$selectedTable})"] = "Show {$selectedTable} structure";
                $queries["SELECT sql FROM sqlite_master WHERE type='table' AND name='{$selectedTable}'"] = "Show {$selectedTable} DDL";
            } elseif ($driverName === 'mysql') {
                $queries["DESCRIBE {$selectedTable}"] = "Show {$selectedTable} structure";
                $queries["SHOW CREATE TABLE {$selectedTable}"] = "Show {$selectedTable} DDL";
                $queries["SELECT * FROM {$selectedTable} WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"] = "Show recent {$selectedTable} (7 days)";
            } elseif ($driverName === 'pgsql') {
                $queries["SELECT column_name, data_type, is_nullable FROM information_schema.columns WHERE table_name = '{$selectedTable}' ORDER BY ordinal_position"] = "Show {$selectedTable} structure";
            }
        }

        // General database queries
        if ($driverName === 'sqlite') {
            $queries["SELECT name FROM sqlite_master WHERE type='table' ORDER BY name"] = 'List all tables';
            $queries["SELECT name, sql FROM sqlite_master WHERE type='index'"] = 'Show all indexes';
            $queries["SELECT name, tbl_name FROM sqlite_master WHERE type='table' ORDER BY tbl_name"] = 'Tables with info';
            $queries["SELECT COUNT(*) as table_count FROM sqlite_master WHERE type='table'"] = 'Count tables';
        } elseif ($driverName === 'mysql') {
            $queries['SHOW TABLES'] = 'List all tables';
            $queries['SELECT TABLE_NAME, TABLE_ROWS FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() ORDER BY TABLE_ROWS DESC'] = 'Show table sizes';
            $queries['SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() ORDER BY TABLE_NAME, ORDINAL_POSITION'] = 'Show all columns';
            $queries['SELECT TABLE_NAME, COLUMN_NAME, DATA_TYPE, IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()'] = 'Show column details';
            $queries['SELECT * FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE()'] = 'Show foreign keys';
            $queries['SHOW STATUS LIKE "Tables%"'] = 'Database statistics';
        } elseif ($driverName === 'pgsql') {
            $queries["SELECT tablename FROM pg_tables WHERE schemaname = 'public' ORDER BY tablename"] = 'List all tables';
            $queries["SELECT table_name, column_name, data_type, is_nullable FROM information_schema.columns WHERE table_schema = 'public' ORDER BY table_name, ordinal_position"] = 'Show all columns';
            $queries["SELECT conname, conrelid::regclass, confrelid::regclass FROM pg_constraint WHERE contype = 'f'"] = 'Show foreign keys';
            $queries["SELECT schemaname, tablename, attname, n_distinct, correlation FROM pg_stats WHERE schemaname = 'public'"] = 'Table statistics';
        }

        return $queries;
    }

    public function loadTableStructure(?string $tableName): void
    {
        if (! $tableName) {
            $this->selectedStructureTable = null;
            $this->tableStructure = [];

            return;
        }

        try {
            $connection = $this->getConnectionName();
            $driverName = config("database.connections.{$connection}.driver", 'mysql');
            $this->selectedStructureTable = $tableName;

            // Get table structure based on database type
            if ($driverName === 'sqlite') {
                $structure = DB::connection($connection)->select("PRAGMA table_info({$tableName})");
                $this->tableStructure = array_map(function ($row) {
                    $rowArray = (array) $row;

                    return [
                        'Field' => $rowArray['name'] ?? '',
                        'Type' => $rowArray['type'] ?? '',
                        'Null' => $rowArray['notnull'] == 1 ? 'NO' : 'YES',
                        'Key' => $rowArray['pk'] == 1 ? 'PRI' : '',
                        'Default' => $rowArray['dflt_value'] ?? 'NULL',
                        'Extra' => '',
                    ];
                }, $structure);
            } elseif ($driverName === 'mysql') {
                $structure = DB::connection($connection)->select("DESCRIBE {$tableName}");
                $this->tableStructure = array_map(function ($row) {
                    return (array) $row;
                }, $structure);
            } elseif ($driverName === 'pgsql') {
                $structure = DB::connection($connection)->select("
                    SELECT
                        column_name as \"Field\",
                        data_type as \"Type\",
                        is_nullable as \"Null\",
                        column_default as \"Default\",
                        '' as \"Key\",
                        '' as \"Extra\"
                    FROM information_schema.columns
                    WHERE table_name = ?
                    ORDER BY ordinal_position
                ", [$tableName]);
                $this->tableStructure = array_map(function ($row) {
                    return (array) $row;
                }, $structure);
            }

        } catch (\Exception $e) {
            Notification::make()
                ->title('Error loading table structure')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function switchView(string $view): void
    {
        $this->currentView = $view;
        if ($view === 'structure' && $this->selectedTable) {
            $this->loadTableStructure($this->selectedTable);
        } elseif ($view === 'content' && $this->selectedTable && empty($this->tableData)) {
            $this->loadTableData();
        }
    }

    private function getAllTables(): array
    {
        try {
            $connection = $this->getConnectionName();
            $driverName = config("database.connections.{$connection}.driver", 'mysql');

            $tableNames = [];

            if ($driverName === 'sqlite') {
                $tables = DB::connection($connection)->select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name");
                foreach ($tables as $table) {
                    $tableArray = (array) $table;
                    $tableNames[$tableArray['name']] = $tableArray['name'];
                }
            } elseif ($driverName === 'mysql') {
                $tables = DB::connection($connection)->select('SHOW TABLES');
                foreach ($tables as $table) {
                    $tableArray = (array) $table;
                    $tableName = reset($tableArray);
                    $tableNames[$tableName] = $tableName;
                }
            } elseif ($driverName === 'pgsql') {
                $tables = DB::connection($connection)->select("SELECT tablename FROM pg_tables WHERE schemaname = 'public' ORDER BY tablename");
                foreach ($tables as $table) {
                    $tableArray = (array) $table;
                    $tableNames[$tableArray['tablename']] = $tableArray['tablename'];
                }
            }

            return $tableNames;
        } catch (\Exception $e) {
            return [];
        }
    }

    public function formatCellValue($value): string
    {
        if ($value === null) {
            return '<span class="fi-db-null-value">NULL</span>';
        } elseif (is_bool($value)) {
            return $value ? 'true' : 'false';
        } elseif (is_string($value) && strlen($value) > 100) {
            return '<span title="'.htmlspecialchars($value).'">'.htmlspecialchars(substr($value, 0, 100)).'...</span>';
        } else {
            return htmlspecialchars((string) $value);
        }
    }

    private function formatQueryResults(array $results): string
    {
        if (empty($results)) {
            return 'No results found.';
        }

        $first = (array) $results[0];
        $columns = array_keys($first);

        // Calculate column widths for better formatting
        $columnWidths = [];
        foreach ($columns as $column) {
            $columnWidths[$column] = max(strlen($column), 10); // Minimum width of 10
        }

        // Calculate max width for each column based on data
        $displayResults = array_slice($results, 0, 100);
        foreach ($displayResults as $row) {
            $rowArray = (array) $row;
            foreach ($columns as $column) {
                $value = $rowArray[$column] ?? '';
                $displayValue = $value === null ? 'NULL' : (string) $value;
                $columnWidths[$column] = max($columnWidths[$column], strlen($displayValue));
            }
        }

        // Limit column width to 50 characters for readability
        foreach ($columnWidths as $column => $width) {
            $columnWidths[$column] = min($width, 50);
        }

        $output = 'Query Results ('.count($results)." rows):\n\n";

        // Create header
        $headerParts = [];
        $separatorParts = [];
        foreach ($columns as $column) {
            $width = $columnWidths[$column];
            $headerParts[] = str_pad($column, $width);
            $separatorParts[] = str_repeat('─', $width);
        }

        $output .= '┌─'.implode('─┬─', $separatorParts)."─┐\n";
        $output .= '│ '.implode(' │ ', $headerParts)." │\n";
        $output .= '├─'.implode('─┼─', $separatorParts)."─┤\n";

        // Data rows
        foreach ($displayResults as $row) {
            $rowArray = (array) $row;
            $valueParts = [];
            foreach ($columns as $column) {
                $value = $rowArray[$column] ?? '';
                $displayValue = $value === null ? 'NULL' : (string) $value;

                // Truncate if too long
                if (strlen($displayValue) > $columnWidths[$column]) {
                    $displayValue = substr($displayValue, 0, $columnWidths[$column] - 3).'...';
                }

                $valueParts[] = str_pad($displayValue, $columnWidths[$column]);
            }
            $output .= '│ '.implode(' │ ', $valueParts)." │\n";
        }

        $output .= '└─'.implode('─┴─', $separatorParts)."─┘\n";

        if (count($results) > 100) {
            $output .= "\n... and ".(count($results) - 100)." more rows (showing first 100)\n";
        }

        return $output;
    }
}
