// Halaman two-factor-challenge: tukar form kode authenticator <-> kode pemulihan.
document.addEventListener('click', (event) => {
    const toggle = event.target.closest?.('[data-tf-toggle]');
    if (!toggle) return;

    const panels = document.querySelectorAll('[data-tf-panel]');
    let shown = null;
    panels.forEach((panel) => {
        panel.hidden = !panel.hidden;
        const input = panel.querySelector('input:not([type=hidden])');
        if (panel.hidden && input) input.value = '';
        if (!panel.hidden) shown = panel;
    });

    const mode = shown?.dataset.tfPanel;
    toggle.textContent = mode === 'recovery' ? toggle.dataset.labelRecovery : toggle.dataset.labelCode;
    shown?.querySelector('input:not([type=hidden])')?.focus();
});
