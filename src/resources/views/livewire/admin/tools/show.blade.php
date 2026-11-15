@php
    use App\Enums\ToolStatus;

    $isLive = $status === ToolStatus::Live;
    $features = $tool['features'];
@endphp

<div class="mx-auto max-w-3xl space-y-6">
    <div class="animate-rise">
        <a href="{{ route('admin.tools.index') }}" class="group inline-flex items-center gap-1 rounded-md text-sm font-medium text-muted transition-colors hover:text-fg">
            <svg class="h-4 w-4 transition-transform duration-200 group-hover:-translate-x-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
            Semua alat
        </a>

        <div class="mt-4 flex flex-col gap-4 sm:flex-row sm:items-center">
            <x-dashboard.tool-icon :category="$tool['category'] ?? null" class="h-12 w-12" />
            <div class="min-w-0 flex-1">
                <h2 class="page-title truncate">{{ $tool['name'] }}</h2>
                <div class="mt-1.5 flex flex-wrap items-center gap-2">
                    <x-dashboard.tool-status :status="$status" />
                    <span class="badge badge-muted">{{ $tool['category_name'] }}</span>
                    <span class="font-mono text-xs text-muted">/tools/{{ $tool['slug'] }}</span>
                </div>
            </div>
            @if(Route::has($tool['route'] ?? ''))
                <a href="{{ route($tool['route']) }}" target="_blank" rel="noopener" class="btn-secondary btn-sm shrink-0 self-start sm:self-center">
                    Buka halaman alat
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                </a>
            @endif
        </div>
        @if(! empty($tool['description']))
            <p class="mt-3 max-w-prose text-sm text-muted">{{ $tool['description'] }}</p>
        @endif
    </div>

    @if(session('status'))
        <div class="rounded-xl border border-ok/30 bg-ok/10 px-4 py-3 text-sm font-medium text-ok animate-rise" role="status">{{ session('status') }}</div>
    @endif

    <div class="stagger space-y-6">
        {{-- Status --}}
        <section class="glass p-5 sm:p-6" aria-labelledby="status-heading">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h3 id="status-heading" class="font-display text-lg font-bold tracking-tight text-fg">Status</h3>
                    <p class="mt-1 max-w-prose text-sm text-muted">
                        {{ $isLive ? 'Alat bisa dipakai semua pengunjung.' : 'Alat ditutup. Pengunjung melihat keterangan di bawah.' }}
                    </p>
                </div>
                <x-dashboard.switch :on="$isLive" labelledby="status-heading"
                    wire:click="toggle" wire:loading.attr="disabled" wire:target="toggle">
                    {{ $isLive ? 'Matikan alat' : 'Aktifkan alat' }}
                </x-dashboard.switch>
            </div>

            <div class="mt-5 border-t border-line pt-5">
                <label for="reason" class="label">Keterangan saat nonaktif</label>
                <select id="reason" wire:model.live="reason" @class(['input sm:max-w-xs', 'border-danger' => $errors->has('reason')])
                    aria-describedby="reason-help">
                    @foreach($reasonOptions as $option)
                        <option value="{{ $option->value }}">{{ $option->label() }}</option>
                    @endforeach
                </select>
                <p id="reason-help" class="mt-1.5 text-xs text-muted">
                    {{ $isLive ? 'Dipakai saat alat dimatikan.' : 'Berubah langsung di kartu dan halaman alat.' }}
                </p>
                @error('reason') <p class="field-error">{{ $message }}</p> @enderror
            </div>
        </section>

        {{-- Kategori: master data di Kategori alat --}}
        <section class="glass p-5 sm:p-6" aria-labelledby="category-heading">
            <h3 id="category-heading" class="font-display text-lg font-bold tracking-tight text-fg">Kategori</h3>
            <p class="mt-1 max-w-prose text-sm text-muted">Menentukan filter dan label alat di halaman Semua alat.</p>
            <label for="category" class="label mt-4">Kategori alat</label>
            <select id="category" wire:model.live="category" @class(['input sm:max-w-xs', 'border-danger' => $errors->has('category')])>
                @unless(array_key_exists($category, $categories))
                    <option value="{{ $category }}" disabled>{{ $tool['category_name'] }} (belum terdaftar)</option>
                @endunless
                @foreach($categories as $slugOption => $name)
                    <option value="{{ $slugOption }}">{{ $name }}</option>
                @endforeach
            </select>
            @error('category') <p class="field-error">{{ $message }}</p> @enderror
            <a href="{{ route('admin.tool-categories.index') }}" class="btn-secondary btn-sm mt-4">Kelola kategori</a>
        </section>

        {{-- Fitur berbayar: tiap fitur dimiliki satu paket saja --}}
        @if($features)
            <section class="glass p-5 sm:p-6" aria-labelledby="features-heading">
                <h3 id="features-heading" class="font-display text-lg font-bold tracking-tight text-fg">Fitur berbayar</h3>
                <p class="mt-1 max-w-prose text-sm text-muted">Tiap fitur hanya tersedia untuk paket yang dipilih. "Terkunci" berarti tidak ada paket yang memperolehnya.</p>
                @can('plans.manage')
                    <dl class="mt-4 space-y-4">
                        @foreach($features as $feature => $label)
                            @php $current = $featurePlan[$feature] ?? ''; @endphp
                            <div wire:key="feat-{{ $feature }}" class="flex flex-col gap-2 border-b border-line/70 pb-4 last:border-0 last:pb-0 sm:flex-row sm:items-center sm:justify-between">
                                <dt class="text-sm font-medium text-fg">{{ $label }}</dt>
                                <dd>
                                    <div role="radiogroup" aria-label="Paket untuk {{ $label }}" class="inline-flex flex-wrap gap-1 rounded-xl border border-line bg-sunken p-1">
                                        <button type="button" role="radio" aria-checked="{{ $current === '' ? 'true' : 'false' }}"
                                            wire:key="seg-{{ $feature }}-locked" wire:click="$set('featurePlan.{{ $feature }}', '')"
                                            @class([
                                                'rounded-lg px-3 py-1.5 text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-fg/40',
                                                'bg-surface text-fg shadow-sm ring-1 ring-line' => $current === '',
                                                'text-muted hover:bg-fg/5 hover:text-fg' => $current !== '',
                                            ])>Terkunci</button>
                                        @foreach($plans as $plan)
                                            <button type="button" role="radio" aria-checked="{{ $current === $plan->slug ? 'true' : 'false' }}"
                                                wire:key="seg-{{ $feature }}-{{ $plan->slug }}" wire:click="$set('featurePlan.{{ $feature }}', '{{ $plan->slug }}')"
                                                @class([
                                                    'rounded-lg px-3 py-1.5 text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-fg/40',
                                                    'bg-ok text-white shadow-sm' => $current === $plan->slug,
                                                    'text-muted hover:bg-fg/5 hover:text-fg' => $current !== $plan->slug,
                                                ])>{{ $plan->name }}</button>
                                        @endforeach
                                    </div>
                                    @error('featurePlan.'.$feature) <p class="field-error mt-1">{{ $message }}</p> @enderror
                                </dd>
                            </div>
                        @endforeach
                    </dl>
                @else
                    <p class="mt-2 text-sm text-muted">{{ implode(', ', $features) }}.</p>
                @endcan
            </section>
        @endif
    </div>
</div>
