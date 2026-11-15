@props(['seconds' => 5])

{{-- Iklan layar penuh setelah operasi tool berhasil, sebelum hasil terlihat. Dipicu event Livewire "operation-done". --}}
<dialog
    x-data="{
        total: {{ max(1, (int) $seconds) }},
        left: 0,
        timer: null,
        show() {
            if (this.$root.open) return;
            this.left = this.total;
            this.$root.showModal();
            this.$refs.title.focus();
            clearInterval(this.timer);
            this.timer = setInterval(() => {
                this.left = Math.max(0, this.left - 1);
                if (this.left === 0) {
                    clearInterval(this.timer);
                    this.$nextTick(() => this.$refs.done.focus());
                }
            }, 1000);
        },
        close() {
            if (this.left === 0) this.$root.close();
        },
    }"
    x-on:operation-done.window="show()"
    x-on:cancel="$event.preventDefault(); close()"
    aria-labelledby="ad-gate-title"
    class="m-0 h-dvh max-h-none w-screen max-w-none bg-ink p-0 text-[#ecede8] backdrop:bg-transparent open:flex open:flex-col"
>
    <div class="flex items-center justify-between gap-4 border-b border-white/10 px-4 py-3 sm:px-6">
        <span class="font-display text-base font-extrabold tracking-tight">ToolKits</span>
        <a href="{{ route('pricing') }}" class="text-sm text-[#ecede8]/70 underline decoration-white/25 underline-offset-4 hover:text-white hover:decoration-tool">Tanpa iklan dengan Pro</a>
    </div>

    <div class="grid flex-1 place-items-center overflow-y-auto px-4 py-8">
        <x-ad-slot position="operation" />
    </div>

    <div class="border-t border-white/10 px-4 py-5 sm:px-6">
        <div class="mx-auto flex max-w-xl flex-col gap-4 sm:flex-row sm:items-center">
            <div class="min-w-0 flex-1">
                <p id="ad-gate-title" x-ref="title" tabindex="-1" class="font-display text-xl font-extrabold tracking-tight focus:outline-none">Hasilmu sudah jadi</p>
                <p class="mt-0.5 text-sm text-[#ecede8]/70">
                    <span x-show="left > 0">Bisa dibuka dalam <span class="tabular-nums" x-text="left"></span> detik.</span>
                    <span x-show="left === 0" x-cloak role="status">Silakan lihat hasilnya.</span>
                </p>
                <div class="mt-3 h-1 overflow-hidden rounded-full bg-white/10" aria-hidden="true">
                    <div class="h-full bg-tool transition-[width] duration-1000 ease-linear" :style="`width: ${(1 - left / total) * 100}%`"></div>
                </div>
            </div>
            <button type="button" x-ref="done" @click="close()" :disabled="left > 0"
                class="btn-primary shrink-0 disabled:bg-white/10 disabled:text-[#ecede8]/60 disabled:opacity-100 disabled:shadow-none">
                Lihat hasil<span x-show="left > 0" class="tabular-nums" x-text="` (${left})`"></span>
            </button>
        </div>
    </div>
</dialog>
