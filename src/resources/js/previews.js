// Live previews for the file tools. pdf.js is a separate chunk, loaded only when a PDF is shown.
// The image maths mirror App\Services\Files\ImageProcessor and JpegPdfWriter; results are estimates.

const MIME = { jpg: 'image/jpeg', png: 'image/png', webp: 'image/webp' };
const FORMAT_OF = { 'image/jpeg': 'jpg', 'image/png': 'png', 'image/webp': 'webp' };
const COMPRESS_QUALITY = { low: 0.85, medium: 0.7, high: 0.5 };
const DEFAULT_QUALITY = 0.9;
// Canvas encoding runs on the main thread; bigger images would freeze the page.
const MAX_PREVIEW_PIXELS = 24_000_000;
// Preview canvas box and 100% crop: enough to judge quality, too small to replace the result.
const VIEW_BOX = [640, 420];
const ZOOM_CROP = [360, 240];
const WATERMARK = 'PRATINJAU';
const PT_PER_MM = 72 / 25.4;
const PT_PER_PX = 72 / 96;
const PAGE_SIZES_MM = { a4: [210, 297], letter: [215.9, 279.4] };

const fileKeys = new WeakMap();
let lastFileKey = 0;

// Stable x-for key per File, so thumbnails follow their file when the list changes.
function fileKey(file) {
    if (!fileKeys.has(file)) fileKeys.set(file, ++lastFileKey);
    return fileKeys.get(file);
}

const sizeFormat = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 1 });

export function humanSize(bytes) {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1048576) return `${Math.round(bytes / 1024)} KB`;
    return `${sizeFormat.format(bytes / 1048576)} MB`;
}

/* ---------- PDF ---------- */

let pdfjs;
// Only the visitor's own picked files are opened here; results are previewed server-side.
const documents = new Map(); // File -> Promise<PDFDocumentProxy>
let renderQueue = Promise.resolve();

function loadPdfjs() {
    pdfjs ??= Promise.all([import('pdfjs-dist'), import('pdfjs-dist/build/pdf.worker.min.mjs?url')]).then(
        ([lib, worker]) => {
            lib.GlobalWorkerOptions.workerSrc = worker.default;
            return lib;
        },
    );
    return pdfjs;
}

function openPdf(source) {
    if (!documents.has(source)) {
        const task = loadPdfjs().then(async (lib) =>
            lib.getDocument({
                data: new Uint8Array(await source.arrayBuffer()),
                isEvalSupported: false,
                enableXfa: false,
            }).promise,
        );
        task.catch(() => documents.delete(source));
        documents.set(source, task);
    }
    return documents.get(source);
}

function releasePdf(source) {
    const task = documents.get(source);
    documents.delete(source);
    task?.then((doc) => doc.destroy()).catch(() => {});
}

// One page at a time: many parallel renders would stall the worker and spike memory.
function renderPage(source, pageNumber, width) {
    const job = renderQueue.then(async () => {
        const page = await (await openPdf(source)).getPage(pageNumber);
        const base = page.getViewport({ scale: 1 });
        const viewport = page.getViewport({ scale: (width * Math.min(window.devicePixelRatio || 1, 2)) / base.width });
        const canvas = document.createElement('canvas');
        canvas.width = Math.ceil(viewport.width);
        canvas.height = Math.ceil(viewport.height);
        await page.render({ canvasContext: canvas.getContext('2d'), viewport, background: '#ffffff' }).promise;
        page.cleanup();
        // A JPEG blob is far lighter to keep around than a live canvas.
        const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', 0.85));
        canvas.width = canvas.height = 0;
        return URL.createObjectURL(blob);
    });
    renderQueue = job.catch(() => {});
    return job;
}

function pdfPages({ limit = 60, width = 140 } = {}) {
    return {
        pages: [],
        total: 0,
        loading: false,
        error: '',
        clickable: false,
        run: 0,
        observer: null,

        async setFiles(files) {
            const pdfs = files.filter((f) => f.type === 'application/pdf');
            const run = ++this.run;
            this.clear();
            if (!pdfs.length) return;

            this.loading = true;
            try {
                const counts = await Promise.all(pdfs.map((f) => openPdf(f).then((doc) => doc.numPages)));
                if (run !== this.run) return;

                const pages = [];
                let number = 0;
                pdfs.forEach((file, i) => {
                    for (let n = 1; n <= counts[i]; n++) {
                        number++;
                        if (pages.length >= limit) continue;
                        pages.push({
                            key: `${run}-${i}-${n}`, run, source: file, n, number,
                            label: String(number), title: file.name,
                            badge: pdfs.length > 1 ? i + 1 : null,
                            url: null, failed: false,
                        });
                    }
                });
                this.total = number;
                this.pages = pages;
            } catch {
                if (run === this.run) this.error = 'PDF tidak bisa dipratinjau. File mungkin rusak atau terkunci sandi.';
            } finally {
                if (run === this.run) this.loading = false;
            }
        },

        // Pages render only once scrolled near the viewport.
        watch(el, page) {
            this.observer ??= new IntersectionObserver(
                (entries) => entries.filter((e) => e.isIntersecting).forEach((e) => {
                    this.observer.unobserve(e.target);
                    this.draw(e.target._page);
                }),
                { rootMargin: '200px' },
            );
            el._page = page;
            this.observer.observe(el);
        },

        async draw(page) {
            try {
                const url = await renderPage(page.source, page.n, width);
                page.run === this.run ? (page.url = url) : URL.revokeObjectURL(url);
            } catch {
                page.failed = true;
            }
        },

        hidden() {
            return Math.max(0, this.total - this.pages.length);
        },

        pageClass() {
            return '';
        },

        pick() {},

        clear() {
            this.pages.forEach((p) => p.url && URL.revokeObjectURL(p.url));
            this.pages = [];
            this.total = 0;
            this.error = '';
        },

        destroy() {
            this.run++;
            this.observer?.disconnect();
            this.clear();
        },
    };
}

// "1-3,5" <-> Set of page numbers, capped at the document length.
function parseRanges(text, total) {
    const pages = new Set();
    let outside = false;
    for (const part of String(text).split(',')) {
        const match = part.trim().match(/^(\d+)(?:-(\d+))?$/);
        if (!match) continue;
        const [a, b] = [Number(match[1]), Number(match[2] ?? match[1])];
        const [from, to] = [Math.min(a, b), Math.max(a, b)];
        if (from < 1 || to > total) outside = true;
        for (let n = Math.max(1, from); n <= Math.min(to, total); n++) pages.add(n);
    }
    return { pages, outside };
}

function formatRanges(pages) {
    const sorted = [...pages].sort((a, b) => a - b);
    const parts = [];
    for (let i = 0; i < sorted.length; i++) {
        const start = sorted[i];
        while (sorted[i + 1] === sorted[i] + 1) i++;
        parts.push(start === sorted[i] ? `${start}` : `${start}-${sorted[i]}`);
    }
    return parts.join(',');
}

/* ---------- Images ---------- */

function targetSize(w, h, { mode, percent, width, height, keepAspect }) {
    if (mode !== 'dimension') {
        const ratio = Math.max(1, Math.min(100, Number(percent) || 100)) / 100;
        return [Math.max(1, Math.round(w * ratio)), Math.max(1, Math.round(h * ratio))];
    }
    const [tw, th] = [Number(width) || null, Number(height) || null];
    if (!tw && !th) return null;
    if (keepAspect) {
        const scale = Math.min(tw ? tw / w : Infinity, th ? th / h : Infinity);
        return [Math.max(1, Math.round(w * scale)), Math.max(1, Math.round(h * scale))];
    }
    return [tw || w, th || h];
}

async function encodeImage(file, { mime, quality, width, height, flatten }) {
    const bitmap = await createImageBitmap(file);
    const canvas = document.createElement('canvas');
    canvas.width = width;
    canvas.height = height;
    const context = canvas.getContext('2d');
    if (flatten) {
        // JPEG has no alpha: the server paints transparency white too.
        context.fillStyle = '#ffffff';
        context.fillRect(0, 0, width, height);
    }
    context.imageSmoothingQuality = 'high';
    context.drawImage(bitmap, 0, 0, width, height);
    bitmap.close();
    const blob = await new Promise((resolve) => canvas.toBlob(resolve, mime, quality));
    canvas.width = canvas.height = 0;
    if (!blob) throw new Error('encode failed');
    return blob;
}

async function imageDimensions(file) {
    const bitmap = await createImageBitmap(file);
    const size = { width: bitmap.width, height: bitmap.height };
    bitmap.close();
    return size;
}

async function imageInfo(file) {
    return { ...(await imageDimensions(file)), url: URL.createObjectURL(file), size: file.size };
}

// Fit inside the preview box, never upscale.
function fitView(width, height) {
    const scale = Math.min(1, VIEW_BOX[0] / width, VIEW_BOX[1] / height);
    return [Math.max(1, Math.round(width * scale)), Math.max(1, Math.round(height * scale))];
}

function drawWatermark(context, width, height) {
    const size = Math.max(14, Math.round(width / 11));
    const diagonal = Math.hypot(width, height);
    context.save();
    context.font = `700 ${size}px sans-serif`;
    context.fillStyle = 'rgba(255, 255, 255, 0.5)';
    context.strokeStyle = 'rgba(0, 0, 0, 0.35)';
    context.lineWidth = Math.max(1, size / 14);
    context.translate(width / 2, height / 2);
    context.rotate(-Math.PI / 7);
    const step = context.measureText(WATERMARK).width + size * 1.5;
    for (let row = 0, y = -diagonal / 2; y < diagonal / 2; row++, y += size * 2.4) {
        for (let x = -diagonal / 2 - (row % 2) * (step / 2); x < diagonal / 2; x += step) {
            context.strokeText(WATERMARK, x, y);
            context.fillText(WATERMARK, x, y);
        }
    }
    context.restore();
}

// tool: compress | convert | resize. Reads the tool options straight from $wire, so it follows every change.
// The estimate is encoded at full size only to measure it; the page shows a small, watermarked
// canvas (or a 100% centre crop), never a URL to the full image.
function imageLive(tool) {
    let afterBlob = null;
    let views = null; // { before?, after?, width, height } ImageBitmaps sized for the canvas

    const closeViews = () => {
        views?.before?.close();
        views?.after?.close();
        views = null;
    };

    return {
        files: [],
        index: 0,
        before: null,
        after: null,
        busy: false,
        note: '',
        split: 50,
        zoom: false,
        run: 0,
        timer: null,

        setFiles(files) {
            this.files = files.filter((f) => FORMAT_OF[f.type]);
            this.index = Math.min(this.index, Math.max(0, this.files.length - 1));
            this.load();
        },

        select(i) {
            this.index = i;
            this.load();
        },

        options() {
            const w = this.$wire;
            return { level: w.level, targetFormat: w.targetFormat, mode: w.mode, percent: w.percent, width: w.width, height: w.height, keepAspect: w.keepAspect };
        },

        schedule() {
            clearTimeout(this.timer);
            this.timer = setTimeout(() => this.update(), 250);
        },

        async load() {
            const run = ++this.run;
            this.reset();
            const file = this.files[this.index];
            if (!file) return;
            try {
                const dimensions = await imageDimensions(file);
                if (run !== this.run) return;
                this.before = { ...dimensions, size: file.size };
                await this.update();
            } catch {
                if (run === this.run) this.note = 'Gambar ini tidak bisa dipratinjau di browser.';
            }
        },

        plan(file, before, o) {
            const source = FORMAT_OF[file.type];
            if (tool === 'compress') {
                if (source === 'png') {
                    return { note: o.level === 'high'
                        ? 'PNG akan dikurangi menjadi 256 warna. Ukuran pastinya terlihat setelah diproses.'
                        : 'PNG dikompres tanpa mengubah tampilan. Ukuran pastinya terlihat setelah diproses.' };
                }
                return { mime: MIME[source], quality: COMPRESS_QUALITY[o.level] ?? COMPRESS_QUALITY.medium, width: before.width, height: before.height, flatten: source === 'jpg' };
            }
            if (tool === 'convert') {
                const target = MIME[o.targetFormat] ? o.targetFormat : 'webp';
                return { mime: MIME[target], quality: DEFAULT_QUALITY, width: before.width, height: before.height, flatten: target === 'jpg' };
            }
            const size = targetSize(before.width, before.height, o);
            if (!size) return { note: 'Isi lebar atau tinggi untuk melihat pratinjau.' };
            return { mime: MIME[source], quality: DEFAULT_QUALITY, width: size[0], height: size[1], flatten: source === 'jpg' };
        },

        async update() {
            const file = this.files[this.index];
            const before = this.before;
            if (!file || !before) return;

            const run = ++this.run;
            const plan = this.plan(file, before, this.options());
            this.note = plan.note ?? '';
            this.clearAfter();
            if (!plan.mime) return;
            if (plan.width * plan.height > MAX_PREVIEW_PIXELS) {
                this.note = 'Gambar terlalu besar untuk dipratinjau di browser. Hasil tetap diproses normal.';
                return;
            }

            this.busy = true;
            try {
                const blob = await encodeImage(file, plan);
                if (run !== this.run) return;
                if (blob.type !== plan.mime) {
                    this.note = 'Browser ini tidak bisa membuat pratinjau untuk format tersebut. Hasil tetap diproses normal.';
                    return;
                }
                afterBlob = blob;
                this.after = { size: blob.size, width: plan.width, height: plan.height };
                await this.refreshView(run);
            } catch {
                if (run === this.run) this.note = 'Pratinjau gagal dibuat.';
            } finally {
                if (run === this.run) this.busy = false;
            }
        },

        async toggleZoom() {
            this.zoom = !this.zoom;
            await this.refreshView(this.run);
        },

        // Decode full size once, keep only canvas-sized bitmaps.
        async refreshView(run) {
            const file = this.files[this.index];
            if (!file || !afterBlob) return;

            const compare = tool !== 'resize';
            const full = await Promise.all(compare ? [createImageBitmap(file), createImageBitmap(afterBlob)] : [createImageBitmap(afterBlob)]);
            try {
                let box;
                let shrink;
                if (compare && this.zoom) {
                    const [w, h] = [Math.min(ZOOM_CROP[0], this.before.width), Math.min(ZOOM_CROP[1], this.before.height)];
                    const [x, y] = [Math.round((this.before.width - w) / 2), Math.round((this.before.height - h) / 2)];
                    box = [w, h];
                    shrink = (img) => createImageBitmap(img, x, y, w, h);
                } else {
                    box = fitView(this.after.width, this.after.height);
                    shrink = (img) => createImageBitmap(img, { resizeWidth: box[0], resizeHeight: box[1], resizeQuality: 'high' });
                }
                const shrunk = await Promise.all(full.map(shrink));
                if (run !== this.run) return shrunk.forEach((b) => b.close());
                closeViews();
                views = compare ? { before: shrunk[0], after: shrunk[1], width: box[0], height: box[1] } : { after: shrunk[0], width: box[0], height: box[1] };
            } finally {
                full.forEach((b) => b.close());
            }
            this.$nextTick(() => this.draw());
        },

        draw() {
            const canvas = this.$refs.view;
            const split = this.split; // read first so x-effect redraws on slider moves
            if (!canvas || !views) return;

            const { width, height } = views;
            canvas.width = width;
            canvas.height = height;
            const context = canvas.getContext('2d');
            const x = views.before ? Math.round((width * split) / 100) : 0;
            views.before && context.drawImage(views.before, 0, 0, width, height);

            context.save();
            context.beginPath();
            context.rect(x, 0, width - x, height);
            context.clip();
            context.drawImage(views.after, 0, 0, width, height);
            drawWatermark(context, width, height);
            context.restore();

            if (views.before) {
                context.fillStyle = '#ffc629';
                context.fillRect(x - 1, 0, 2, height);
            }
        },

        saving() {
            if (!this.before || !this.after) return '';
            const diff = Math.round((1 - this.after.size / this.before.size) * 100);
            if (diff > 0) return `${diff}% lebih kecil`;
            if (diff < 0) return `${-diff}% lebih besar`;
            return 'ukuran hampir sama';
        },

        clearAfter() {
            afterBlob = null;
            closeViews();
            this.after = null;
        },

        reset() {
            this.before = null;
            this.clearAfter();
            this.note = '';
            this.busy = false;
        },

        destroy() {
            this.run++;
            clearTimeout(this.timer);
            this.reset();
        },
    };
}

// Page layout for "Gambar ke PDF", same maths as JpegPdfWriter (points).
function imagePdfLayout() {
    return {
        items: [],
        run: 0,

        async setFiles(files) {
            const run = ++this.run;
            const items = await Promise.all(
                files.filter((f) => FORMAT_OF[f.type]).map((f, i) => imageInfo(f).then((info) => ({ ...info, key: `${run}-${i}` })).catch(() => null)),
            );
            if (run !== this.run) return items.forEach((it) => it && URL.revokeObjectURL(it.url));
            this.clear();
            this.items = items.filter(Boolean);
        },

        layout(item) {
            const w = this.$wire;
            const margin = (Number(w.margin) || 0) * PT_PER_MM;
            let [pageW, pageH] = [item.width * PT_PER_PX + 2 * margin, item.height * PT_PER_PX + 2 * margin];
            if (PAGE_SIZES_MM[w.pageSize]) {
                [pageW, pageH] = PAGE_SIZES_MM[w.pageSize].map((mm) => mm * PT_PER_MM);
                if (w.orientation === 'landscape') [pageW, pageH] = [pageH, pageW];
            }
            const scale = Math.min((pageW - 2 * margin) / (item.width * PT_PER_PX), (pageH - 2 * margin) / (item.height * PT_PER_PX), 1);
            const [drawW, drawH] = [item.width * PT_PER_PX * scale, item.height * PT_PER_PX * scale];
            return { pageW, pageH, drawW, drawH };
        },

        pageStyle(item) {
            const { pageW, pageH } = this.layout(item);
            return `aspect-ratio: ${pageW} / ${pageH}`;
        },

        imageStyle(item) {
            const { pageW, pageH, drawW, drawH } = this.layout(item);
            const pct = (v, total) => `${(v / total) * 100}%`;
            return `width: ${pct(drawW, pageW)}; height: ${pct(drawH, pageH)}; left: ${pct((pageW - drawW) / 2, pageW)}; top: ${pct((pageH - drawH) / 2, pageH)}`;
        },

        clear() {
            this.items.forEach((it) => URL.revokeObjectURL(it.url));
            this.items = [];
        },

        destroy() {
            this.run++;
            this.clear();
        },
    };
}

/* ---------- Registration ---------- */

// Previews ([data-no-save]) offer no "save image" menu and cannot be dragged out. This only removes
// the easy route; previews are low-res and watermarked because a screenshot can never be blocked.
for (const type of ['contextmenu', 'dragstart']) {
    document.addEventListener(type, (event) => {
        if (event.target instanceof Element && event.target.closest('[data-no-save]')) event.preventDefault();
    });
}

document.addEventListener('alpine:init', () => {
    const Alpine = window.Alpine;

    Alpine.magic('bytes', () => humanSize);
    Alpine.magic('releasePdf', () => releasePdf);
    Alpine.magic('fileKey', () => fileKey);

    // Small thumbnail + detail line for one picked file.
    Alpine.data('fileThumb', (file) => ({
        url: null,
        detail: '',

        async init() {
            try {
                if (FORMAT_OF[file.type]) {
                    const info = await imageInfo(file);
                    this.url = info.url;
                    this.detail = `${info.width}×${info.height} px`;
                } else if (file.type === 'application/pdf') {
                    const doc = await openPdf(file);
                    this.detail = `${doc.numPages} halaman`;
                    this.url = await renderPage(file, 1, 48);
                }
            } catch {
                this.detail = 'Tidak bisa dipratinjau';
            }
        },

        destroy() {
            this.url && URL.revokeObjectURL(this.url);
        },
    }));

    Alpine.data('pdfPages', pdfPages);

    // Pisah PDF: click pages to build the range, typed ranges highlight live.
    Alpine.data('pdfSplit', () => ({
        ...pdfPages({ limit: 200, width: 110 }),

        get extract() {
            return this.$wire.mode === 'extract';
        },

        get clickable() {
            return this.extract && this.total > 0;
        },

        get selection() {
            return parseRanges(this.$wire.ranges ?? '', this.total);
        },

        pageClass(page) {
            if (!this.extract) return '';
            return this.selection.pages.has(page.n) ? 'ring-2 ring-tool ring-offset-2 ring-offset-surface' : 'opacity-45';
        },

        pick(page) {
            const pages = new Set(this.selection.pages);
            pages.has(page.n) ? pages.delete(page.n) : pages.add(page.n);
            this.$wire.ranges = formatRanges(pages);
        },

        summary() {
            if (!this.total) return '';
            if (!this.extract) return `Hasil: ${this.total} file PDF (1 halaman per file) dalam satu ZIP.`;
            const count = this.selection.pages.size;
            return count ? `Hasil: 1 PDF berisi ${count} dari ${this.total} halaman.` : `Klik halaman untuk memilih, atau ketik rentangnya. PDF ini punya ${this.total} halaman.`;
        },
    }));

    Alpine.data('imageLive', imageLive);
    Alpine.data('imagePdfLayout', imagePdfLayout);
});
