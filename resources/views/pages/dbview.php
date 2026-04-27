@php($errors ??= new \Illuminate\Support\ViewErrorBag)
<x-filament::page>
    <style>
        .fi-db-query-result {
            @apply rounded-lg p-6 text-sm;
            background: linear-gradient(to bottom right, rgb(17 24 39), rgb(31 41 55), rgb(0 0 0));
            color: rgb(209 250 229);
            border: 1px solid rgb(16 185 129 / 0.2);
            box-shadow: 0 25px 50px -12px rgb(0 0 0 / 0.25);
            font-family: 'JetBrains Mono', 'Fira Code', 'Monaco', 'Consolas', monospace;
            overflow: auto;
            max-height: 70vh;
        }

        .fi-db-query-result pre {
            @apply whitespace-pre font-mono;
            color: rgb(209 250 229);
            line-height: 1.4;
        }

        .fi-db-structure-section {
            background-color: rgb(255 255 255);
            border: 1px solid rgb(229 231 235);
            border-radius: 0.5rem;
            padding: 1.5rem;
        }

        .dark .fi-db-structure-section {
            background-color: rgb(17 24 39);
            border-color: rgb(55 65 81);
        }

        .fi-db-structure-table {
            @apply w-full text-sm;
            border-collapse: collapse;
        }

        .fi-db-structure-table th,
        .fi-db-structure-table td {
            @apply px-4 py-2 border text-left;
            border-color: rgb(229 231 235);
        }

        .dark .fi-db-structure-table th,
        .dark .fi-db-structure-table td {
            border-color: rgb(55 65 81);
        }

        .fi-db-structure-table th {
            background-color: rgb(249 250 251);
            font-weight: 600;
        }

        .dark .fi-db-structure-table th {
            background-color: rgb(31 41 55);
        }

        /* Align table header actions and search on the same row */
        .fi-ta-header,
        .fi-ta-header-toolbar {
            display: flex;
            align-items: center;
            gap: .75rem;
            flex-wrap: wrap;
        }
        .fi-ta-header-actions,
        .fi-ta-header-toolbar .fi-actions {
            display: flex;
            align-items: center;
            gap: .5rem;
        }
        /* Keep search at the end (right) */
        .fi-ta-header-toolbar { justify-content: space-between; width: 100%; }
        .fi-ta-header-toolbar .fi-input, .fi-ta-header-toolbar .fi-input-wrapper { margin-left: auto; }

        /* Discreet action button styles */
        .fi-action .fi-btn.fi-color-gray.fi-btn-size-sm {
            background-color: transparent;
            border-color: rgb(203 213 225);
            color: rgb(71 85 105);
        }
        .dark .fi-action .fi-btn.fi-color-gray.fi-btn-size-sm {
            border-color: rgb(51 65 85);
            color: rgb(148 163 184);
        }
    </style>

    <div class="fi-page-content space-y-6">
        <!-- Main Table Browser Section -->
        <div class="space-y-6">
            @if($activeTable)
                <style>
                    /* Zero out section content padding for the table browser */
                    .dbv-table-browser .fi-section-content { padding: 0 !important; }
                </style>
                <div class="dbv-table-browser">
                <x-filament::section>
                    <x-slot name="heading"></x-slot>
                    <x-slot name="description"></x-slot>

                    @if($currentView === 'structure')
                        <!-- Table Structure View -->
                        <div class="fi-db-structure-section">
                            <div class="mb-4 flex items-center justify-between">
                                <h3 class="text-lg font-semibold">Table Structure: {{ $activeTable }}</h3>
                                <x-filament::button
                                    wire:click="toggleStructureView"
                                    size="sm"
                                    outlined
                                >
                                    Back to Data
                                </x-filament::button>
                            </div>

                            @if(!empty($tableStructure))
                                <div class="overflow-x-auto">
                                    <table class="fi-db-structure-table">
                                        <thead>
                                            <tr>
                                                <th>Field</th>
                                                <th>Type</th>
                                                <th>Null</th>
                                                <th>Key</th>
                                                <th>Default</th>
                                                <th>Extra</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($tableStructure as $column)
                                                <tr>
                                                    <td><strong>{{ $column['Field'] ?? '' }}</strong></td>
                                                    <td>{{ $column['Type'] ?? '' }}</td>
                                                    <td>{{ $column['Null'] ?? '' }}</td>
                                                    <td>{{ $column['Key'] ?? '' }}</td>
                                                    <td>{{ $column['Default'] ?? 'NULL' }}</td>
                                                    <td>{{ $column['Extra'] ?? '' }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <div class="text-center py-8 text-gray-500">
                                    No structure information available.
                                </div>
                            @endif
                        </div>
                    @else
                        <!-- Filament Table Component -->
                        {{ $this->table }}
                    @endif
                </x-filament::section>
                </div>
            @else
                <!-- Default Empty State -->
                <x-filament::section>
                    <x-slot name="heading">Table Browser</x-slot>
                    <x-slot name="description">
                        Select a database connection to begin browsing tables.
                    </x-slot>

                    <div class="text-center py-12">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.375 19.5h17.25m-17.25 0a1.125 1.125 0 01-1.125-1.125M3.375 19.5h1.5C5.496 19.5 6 18.996 6 18.375m-1.125 1.125a3.375 3.375 0 010-6.75C2.831 11.625 3 10.83 3 9.75V5.625c0-1.036.84-1.875 1.875-1.875h3.75c.621 0 1.125.504 1.125 1.125v4.125c0 1.035.84 1.875 1.875 1.875h9.75c1.035 0 1.875.84 1.875 1.875v4.125c0 1.035-.84 1.875-1.875 1.875H12M3.375 19.5h1.5C5.496 19.5 6 18.996 6 18.375v-3.75c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v3.75c0 .621.504 1.125 1.125 1.125z" />
                        </svg>
                        <h3 class="mt-2 text-sm font-semibold text-gray-900 dark:text-gray-100">No tables available</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Please select a database connection above to view available tables.
                        </p>
                    </div>
                </x-filament::section>
            @endif
        </div>
    </div>
</x-filament::page>
