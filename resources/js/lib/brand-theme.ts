// Shared brand-accent classes reused across the auth pages (login, forgot/reset
// password, confirm password, verify email, two-factor challenge — all rendered
// through auth-simple-layout.tsx's card), the agent-facing resource pages
// (courses/browse.tsx, dashboard-agent.tsx), and the primary "create" actions on
// the Users, Categories, and Resources management pages — same palette,
// different surfaces.
export const authLabelClass = 'text-sm font-bold';

export const authInputClass =
    'h-11 rounded-[10px] border-slate-200 bg-[#fbfaff] focus-visible:border-[#f598ff] focus-visible:ring-[#f598ff]/40 dark:border-input dark:bg-input/30';

export const authLinkClass =
    'font-semibold text-[#473364] no-underline hover:underline dark:text-[#f5b8ff]';

export const authCheckboxClass =
    'data-[state=checked]:border-transparent data-[state=checked]:bg-gradient-to-br data-[state=checked]:from-[#f598ff] data-[state=checked]:to-[#5a4177]';

export const authButtonClass =
    'h-12 rounded-xl border-0 font-extrabold text-[#3a2a54] shadow-[0_12px_24px_-8px_rgba(245,152,255,0.55)] bg-[linear-gradient(135deg,#c774ff_0%,#f598ff_50%,#b98cff_100%)] hover:opacity-95';

export const resourceCardClass =
    'overflow-hidden rounded-2xl border-[#ece7f5] transition-all hover:border-[#e6cdf7] hover:shadow-md dark:border-sidebar-border dark:hover:border-sidebar-ring';

export const resourceBadgeClass =
    'border-0 bg-[#f598ff]/18 font-semibold text-[#7a3fa0] dark:bg-[#f598ff]/15 dark:text-[#f5b8ff]';

// The primary brand CTA button — used both for the agent-facing "Open resource"
// buttons and the "New user" / "New category" / "New resource" / "Publish"
// management actions.
export const brandButtonClass =
    'gap-2 rounded-lg border-0 font-bold text-white bg-[linear-gradient(135deg,#473364_0%,#5a4177_60%,#8a5fae_100%)] hover:opacity-95';

export const resourceInputClass =
    'rounded-lg border-slate-200 focus-visible:border-[#f598ff] focus-visible:ring-2 focus-visible:ring-[#f598ff]/40 dark:border-input';
