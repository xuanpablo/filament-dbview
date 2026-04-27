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

    <style>
        .dbv-shell { display: grid; grid-template-columns: 280px minmax(0, 1fr); gap: 1rem; align-items: start; }
        .dbv-shell > * { min-width: 0; }
        .dbv-table-browser { overflow-x: auto; }
        @media (max-width: 768px) { .dbv-shell { grid-template-columns: 1fr; } }

        .dbv-tree { font-size: .875rem; max-height: calc(100vh - 12rem); overflow: auto; padding: .25rem; }
        .dbv-tree details { margin: 0; }
        .dbv-tree summary {
            list-style: none; cursor: pointer; padding: .25rem .5rem; border-radius: .375rem;
            display: flex; align-items: center; gap: .375rem; user-select: none;
            color: rgb(71 85 105);
        }
        .dbv-tree summary::-webkit-details-marker { display: none; }
        .dbv-tree summary:hover { background: rgb(241 245 249); }
        .dark .dbv-tree summary { color: rgb(148 163 184); }
        .dark .dbv-tree summary:hover { background: rgb(30 41 59); }
        .dbv-tree summary .chev { transition: transform .15s; flex-shrink: 0; }
        .dbv-tree details[open] > summary .chev { transform: rotate(90deg); }

        .dbv-tree .leaf {
            display: flex; align-items: center; gap: .375rem; width: 100%;
            padding: .25rem .5rem; border-radius: .375rem; cursor: pointer;
            background: transparent; border: 0; text-align: left;
            color: rgb(51 65 85); font: inherit;
        }
        .dbv-tree .leaf:hover { background: rgb(241 245 249); }
        .dbv-tree .leaf.is-active { background: rgb(224 242 254); color: rgb(2 132 199); font-weight: 500; }
        .dark .dbv-tree .leaf { color: rgb(203 213 225); }
        .dark .dbv-tree .leaf:hover { background: rgb(30 41 59); }
        .dark .dbv-tree .leaf.is-active { background: rgb(12 74 110); color: rgb(125 211 252); }

        .dbv-tree .nested { padding-left: 1rem; border-left: 1px dashed rgb(226 232 240); margin-left: .75rem; }
        .dark .dbv-tree .nested { border-left-color: rgb(51 65 81); }

        .dbv-tree .filter {
            width: 100%; padding: .375rem .5rem; margin-bottom: .5rem;
            border: 1px solid rgb(226 232 240); border-radius: .375rem; font-size: .813rem;
            background: rgb(255 255 255); color: rgb(15 23 42);
        }
        .dark .dbv-tree .filter { background: rgb(15 23 42); border-color: rgb(51 65 81); color: rgb(226 232 240); }

        .dbv-actions { display: flex; gap: .375rem; margin-bottom: .5rem; flex-wrap: wrap; }
        .dbv-actions .fi-btn { flex: 1 1 auto; justify-content: center; }

        .dbv-sidebar-section .fi-section-content { padding: .75rem !important; }
        .dbv-table-browser .fi-section-content { padding: 0 !important; }
    </style>

    <div class="fi-page-content">
        <div class="dbv-shell">

            <!-- Sidebar: filesystem-style tree of tables -->
            <div class="dbv-sidebar-section">
                <x-filament::section>
                    <x-slot name="heading">Tables</x-slot>

                    <input
                        type="text"
                        class="filter"
                        placeholder="Filter tables…"
                        wire:model.live.debounce.200ms="tableFilter"
                    />

                    @if($activeTable)
                        <div class="dbv-actions">
                            <x-filament::button
                                wire:click="toggleStructureView"
                                size="xs"
                                color="gray"
                                outlined
                                :icon="$currentView === 'structure' ? 'heroicon-m-table-cells' : 'heroicon-m-cog-6-tooth'"
                            >
                                {{ $currentView === 'structure' ? 'View Data' : 'View Structure' }}
                            </x-filament::button>

                            <x-filament::button
                                wire:click="refreshTable"
                                size="xs"
                                color="gray"
                                outlined
                                icon="heroicon-m-arrow-path"
                            >
                                Refresh
                            </x-filament::button>
                        </div>
                    @endif

                    <div class="dbv-tree">
                        @php($tree = $this->getTablesTree())
                        @php($activeFolder = $activeTable ? (str_contains($activeTable, '_') ? strtok($activeTable, '_') : null) : null)
                        @php($hasResults = ! empty($tree['_root']) || count($tree) > 1)

                        {{-- Root-level tables (no prefix or single-child folders demoted up) --}}
                        @foreach($tree['_root'] ?? [] as $entry)
                            <button
                                type="button"
                                wire:click="switchTable('{{ $entry['name'] }}')"
                                class="leaf {{ $activeTable === $entry['name'] ? 'is-active' : '' }}"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4 flex-shrink-0 opacity-60">
                                    <path d="M3 3.5A1.5 1.5 0 014.5 2h11A1.5 1.5 0 0117 3.5v13a1.5 1.5 0 01-1.5 1.5h-11A1.5 1.5 0 013 16.5v-13zM5 5h10v2H5V5zm0 4h10v2H5V9zm0 4h10v2H5v-2z" />
                                </svg>
                                <span class="truncate">{{ $entry['name'] }}</span>
                            </button>
                        @endforeach

                        {{-- Folder groups --}}
                        @foreach($tree as $folder => $children)
                            @continue($folder === '_root')
                            {{-- Auto-expand if active table is inside, or if a filter is narrowing results --}}
                            <details {{ ($activeFolder === $folder || trim($tableFilter) !== '') ? 'open' : '' }}>
                                <summary>
                                    <svg class="chev" width="12" height="12" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd" />
                                    </svg>
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4 opacity-70">
                                        <path d="M3.5 4A1.5 1.5 0 002 5.5V6h16v-.5A1.5 1.5 0 0016.5 4h-5.879a1.5 1.5 0 01-1.06-.44L8.439 2.439A1.5 1.5 0 007.378 2H3.5zM2 7.5v7A1.5 1.5 0 003.5 16h13a1.5 1.5 0 001.5-1.5v-7H2z" />
                                    </svg>
                                    <span class="truncate font-medium">{{ $folder }}</span>
                                    <span class="ml-auto text-xs opacity-60">{{ count($children) }}</span>
                                </summary>
                                <div class="nested">
                                    @foreach($children as $child)
                                        <button
                                            type="button"
                                            wire:click="switchTable('{{ $child['name'] }}')"
                                            class="leaf {{ $activeTable === $child['name'] ? 'is-active' : '' }}"
                                        >
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4 flex-shrink-0 opacity-60">
                                                <path d="M3 3.5A1.5 1.5 0 014.5 2h11A1.5 1.5 0 0117 3.5v13a1.5 1.5 0 01-1.5 1.5h-11A1.5 1.5 0 013 16.5v-13zM5 5h10v2H5V5zm0 4h10v2H5V9zm0 4h10v2H5v-2z" />
                                            </svg>
                                            <span class="truncate">{{ $child['leaf'] }}</span>
                                        </button>
                                    @endforeach
                                </div>
                            </details>
                        @endforeach

                        @if(! $hasResults)
                            <div class="text-center py-6 text-sm text-gray-500">
                                @if(trim($tableFilter) !== '')
                                    No matches for &ldquo;{{ $tableFilter }}&rdquo;.
                                @else
                                    No tables found.
                                @endif
                            </div>
                        @endif
                    </div>
                </x-filament::section>
            </div>

            <!-- Main pane: table content / structure -->
            <div class="dbv-table-browser">
                @if($activeTable)
                    <x-filament::section>
                        <x-slot name="heading"></x-slot>
                        <x-slot name="description"></x-slot>

                        @if($currentView === 'structure')
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
                            {{ $this->table }}
                        @endif
                    </x-filament::section>
                @else
                    <x-filament::section>
                        <x-slot name="heading">Pick a table</x-slot>
                        <x-slot name="description">
                            Choose a table from the sidebar to start browsing.
                        </x-slot>
                        <div class="text-center py-12 text-sm text-gray-500">
                            Tables are grouped by name prefix (e.g. <code>simpro_</code>) and listed alphabetically.
                        </div>
                    </x-filament::section>
                @endif
            </div>

        </div>
    </div>
</x-filament::page>
