const PALETTE = {
    indigo: { bg: 'bg-primary-50 dark:bg-primary-500/10', text: 'text-primary-600 dark:text-primary-400', ring: 'ring-primary-500/20', solid: 'bg-primary-500' },
    emerald: { bg: 'bg-emerald-50 dark:bg-emerald-500/10', text: 'text-emerald-600 dark:text-emerald-400', ring: 'ring-emerald-500/20', solid: 'bg-emerald-500' },
    rose: { bg: 'bg-rose-50 dark:bg-rose-500/10', text: 'text-rose-600 dark:text-rose-400', ring: 'ring-rose-500/20', solid: 'bg-rose-500' },
    amber: { bg: 'bg-amber-50 dark:bg-amber-500/10', text: 'text-amber-600 dark:text-amber-400', ring: 'ring-amber-500/20', solid: 'bg-amber-500' },
    sky: { bg: 'bg-sky-50 dark:bg-sky-500/10', text: 'text-sky-600 dark:text-sky-400', ring: 'ring-sky-500/20', solid: 'bg-sky-500' },
    violet: { bg: 'bg-violet-50 dark:bg-violet-500/10', text: 'text-violet-600 dark:text-violet-400', ring: 'ring-violet-500/20', solid: 'bg-violet-500' },
    slate: { bg: 'bg-slate-100 dark:bg-slate-500/10', text: 'text-slate-600 dark:text-slate-400', ring: 'ring-slate-500/20', solid: 'bg-slate-400' },
};

export function colorClasses(color) {
    return PALETTE[color] || PALETTE.indigo;
}

export function statusBadgeClass(status) {
    const s = String(status).toLowerCase();
    if (['active', 'present', 'confirmed', 'paid', 'completed', 'current', 'checked in', 'admitted'].includes(s)) return 'bg-emerald-50 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-500/10 dark:text-emerald-400 dark:ring-emerald-500/20';
    if (['inactive', 'absent', 'rejected', 'cancelled', 'overdue', 'low stock', 'closed'].includes(s)) return 'bg-rose-50 text-rose-700 ring-rose-600/20 dark:bg-rose-500/10 dark:text-rose-400 dark:ring-rose-500/20';
    if (['pending', 'leave', 'late', 'upcoming', 'scheduled', 'new', 'contacted'].includes(s)) return 'bg-amber-50 text-amber-700 ring-amber-600/20 dark:bg-amber-500/10 dark:text-amber-400 dark:ring-amber-500/20';
    if (['registered', 'converted'].includes(s)) return 'bg-sky-50 text-sky-700 ring-sky-600/20 dark:bg-sky-500/10 dark:text-sky-400 dark:ring-sky-500/20';
    return 'bg-slate-100 text-slate-700 ring-slate-500/20 dark:bg-slate-500/10 dark:text-slate-300 dark:ring-slate-500/20';
}
