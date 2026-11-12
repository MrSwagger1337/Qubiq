export function notify(message, variant = 'success') {
    const region = document.getElementById('toast-region');
    if (!region) {
        return;
    }

    const toast = document.createElement('div');
    const palette = variant === 'error'
        ? 'border-rose-500/40 bg-rose-950/95 text-rose-100'
        : variant === 'warning'
            ? 'border-amber-500/40 bg-amber-950/95 text-amber-100'
            : 'border-emerald-500/40 bg-slate-900/95 text-slate-100';

    toast.className = `pointer-events-auto flex items-start justify-between gap-3 px-4 py-3 rounded-xl border shadow-lg text-xs font-medium transition-opacity duration-200 ${palette}`;

    const textSpan = document.createElement('span');
    textSpan.textContent = message;

    const closeBtn = document.createElement('button');
    closeBtn.type = 'button';
    closeBtn.className = 'opacity-70 hover:opacity-100 font-bold text-sm leading-none';
    closeBtn.setAttribute('aria-label', 'Dismiss notification');
    closeBtn.textContent = '×';
    closeBtn.addEventListener('click', () => toast.remove());

    toast.append(textSpan, closeBtn);
    region.appendChild(toast);

    window.setTimeout(() => {
        toast.style.opacity = '0';
        window.setTimeout(() => toast.remove(), 220);
    }, 4200);
}

