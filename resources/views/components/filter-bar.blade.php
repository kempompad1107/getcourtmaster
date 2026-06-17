@props([
    'placeholder' => 'Search…',
    'searchName'  => 'search',
    'searchable'  => true,   // set false for pages with no search param (filters only)
    'activeCount' => 0,      // # of active filters (excluding search) → shows a badge
    'clear'       => null,   // URL to reset all filters
    'action'      => null,   // GET target; defaults to current URL
])

{{-- Unified filter bar: a search field + a "Filters" popover button.
     Pass the filter controls (selects, date inputs, etc.) via the `filters` slot;
     omit the slot for search-only pages. Modern + identical on desktop and mobile. --}}
<form method="GET" @if($action) action="{{ $action }}" @endif
      {{ $attributes->merge(['class' => 'mb-4']) }}
      x-data="{ open: false }">
    <div class="d-flex gap-2 align-items-center">
        @if($searchable)
        <div class="search-field flex-grow-1 min-w-0">
            <i class="bi bi-search"></i>
            <input type="text" name="{{ $searchName }}" value="{{ request($searchName) }}"
                   aria-label="Search" placeholder="{{ $placeholder }}">
        </div>
        @else
        <span class="flex-grow-1"></span>
        @endif

        @isset($filters)
        <div class="position-relative flex-shrink-0" @click.outside="open = false">
            <button type="button" @click="open = !open"
                    class="btn btn-sm d-flex align-items-center gap-1 {{ $activeCount > 0 ? 'btn-primary' : 'btn-outline-secondary' }}"
                    :aria-expanded="open.toString()">
                <i class="bi bi-sliders"></i>
                <span class="d-none d-sm-inline">Filters</span>
                @if($activeCount > 0)
                <span class="badge rounded-pill text-bg-light text-dark ms-1">{{ $activeCount }}</span>
                @endif
            </button>

            <div x-show="open" x-cloak x-transition.origin.top.right
                 class="card shadow position-absolute end-0 mt-2"
                 style="width:clamp(240px, 80vw, 300px); max-width:calc(100vw - 1.5rem); z-index:1050">
                <div class="card-body">
                    <div class="d-flex flex-column gap-3">
                        {{ $filters }}
                    </div>
                    <div class="d-flex gap-2 mt-3">
                        <button type="submit" class="btn btn-primary btn-sm flex-grow-1">Apply</button>
                        @if($clear)
                        <a href="{{ $clear }}" class="btn btn-outline-secondary btn-sm">Clear</a>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        @endisset
    </div>
    {{-- Enter in the search box submits the form; this keeps it keyboard-accessible
         on search-only pages with no visible submit button. --}}
    <button type="submit" class="visually-hidden">Search</button>
</form>
