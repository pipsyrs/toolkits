@php
    use App\Actions\Admin\ManageToolCategory;

    $iconBtn = 'grid h-8 w-8 place-items-center rounded-md text-muted transition-colors hover:bg-fg/6 hover:text-fg disabled:cursor-not-allowed disabled:opacity-30 disabled:hover:bg-transparent';
@endphp

<div class="mx-auto max-w-4xl space-y-6">
    <div class="flex flex-col gap-3 animate-rise sm:flex-row sm:items-center sm:justify-between">
        <p class="max-w-prose text-sm text-muted">Kategori muncul sebagai filter di halaman Semua alat, dengan urutan seperti di bawah. Kategori tiap alat diubah di halaman <a href="{{ route('admin.tools.index') }}" class="font-medium text-fg underline underline-offset-2">Alat</a>.</p>
        <button type="button" class="btn-primary shrink-0" wire:click="openCreate" wire:loading.attr="disabled" wire:target="openCreate">Tambah kategori</button>
    </div>

    @if(session('status'))
        <div class="rounded-xl border border-ok/30 bg-ok/10 px-4 py-3 text-sm font-medium text-ok animate-rise" role="status">{{ session('status') }}</div>
    @endif
    @error('list')
        <div class="rounded-xl border border-danger/30 bg-danger/10 px-4 py-3 text-sm text-danger animate-rise" role="alert">{{ $message }}</div>
    @enderror

    <div class="glass animate-rise" wire:loading.class="opacity-60" wire:target="move,delete">
        @if($categories->isEmpty())
            <div class="px-6 py-14 text-center">
                <p class="font-semibold text-fg">Belum ada kategori.</p>
                <p class="mt-1 text-sm text-muted">Tambahkan kategori untuk mengelompokkan alat.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-[36rem] text-sm">
                    <thead class="border-b border-line text-left text-xs uppercase tracking-wide text-muted">
                        <tr>
                            <th scope="col" class="px-5 py-3 font-medium">Nama</th>
                            <th scope="col" class="px-5 py-3 font-medium">Slug</th>
                            <th scope="col" class="px-5 py-3 font-medium">Dipakai</th>
                            <th scope="col" class="px-5 py-3"><span class="sr-only">Aksi</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach($categories as $category)
                            <tr wire:key="category-{{ $category->id }}">
                                <td class="px-5 py-3">
                                    <div class="flex items-center gap-3">
                                        <x-dashboard.tool-icon :category="$category->slug" class="h-8 w-8" />
                                        <span class="font-medium text-fg">{{ $category->name }}</span>
                                    </div>
                                </td>
                                <td class="px-5 py-3 font-mono text-xs text-muted">{{ $category->slug }}</td>
                                <td class="px-5 py-3 tabular-nums text-muted">{{ $usage[$category->slug] ?? 0 }} alat</td>
                                <td class="px-5 py-3">
                                    <div class="flex items-center justify-end gap-1">
                                        <button type="button" class="{{ $iconBtn }}" wire:click="move('{{ $category->id }}', true)" @disabled($loop->first) aria-label="Naikkan {{ $category->name }}">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7"/></svg>
                                        </button>
                                        <button type="button" class="{{ $iconBtn }}" wire:click="move('{{ $category->id }}', false)" @disabled($loop->last) aria-label="Turunkan {{ $category->name }}">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                                        </button>
                                        <button type="button" class="btn-secondary btn-sm ml-1" wire:click="openEdit('{{ $category->id }}')" aria-label="Edit {{ $category->name }}">Edit</button>
                                        <x-confirm-button class="{{ $iconBtn }} hover:text-danger" :action="'delete(\''.$category->id.'\')'"
                                            :title="'Hapus kategori '.$category->name.'?'" message="Hanya bisa bila tidak ada alat di kategori ini." confirm="Hapus" aria-label="Hapus {{ $category->name }}">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </x-confirm-button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <x-dashboard.modal wire:model="formOpen" submit="save" :submit-label="$editing ? 'Simpan' : 'Tambah'"
        :title="$editing ? 'Edit kategori' : 'Tambah kategori'"
        :description="$editing ? 'Slug '.$editing->slug.' tidak bisa diubah.' : 'Slug dipakai sebagai pengenal dan tidak bisa diubah setelah dibuat. Kategori baru memakai ikon umum.'">
        @unless($editing)
            <div>
                <label for="category-slug" class="label">Slug</label>
                <input id="category-slug" type="text" wire:model="slug" maxlength="{{ ManageToolCategory::SLUG_MAX }}" placeholder="mis. teks" autocomplete="off" required
                    @class(['input font-mono text-sm', 'border-danger' => $errors->has('slug')]) @error('slug') aria-invalid="true" aria-describedby="category-slug-error" @enderror>
                @error('slug') <p id="category-slug-error" class="field-error">{{ $message }}</p> @enderror
            </div>
        @endunless
        <div>
            <label for="category-name" class="label">Nama</label>
            <input id="category-name" type="text" wire:model="name" maxlength="{{ ManageToolCategory::NAME_MAX }}" placeholder="mis. Teks" required
                @class(['input', 'border-danger' => $errors->has('name')]) @error('name') aria-invalid="true" aria-describedby="category-name-error" @enderror>
            @error('name') <p id="category-name-error" class="field-error">{{ $message }}</p> @enderror
        </div>
    </x-dashboard.modal>
</div>
