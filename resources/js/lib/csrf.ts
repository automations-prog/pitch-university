/**
 * Laravel sets an encrypted `XSRF-TOKEN` cookie on every `web`-middleware
 * response. Inertia's own client reads this automatically for its
 * requests, but plain `fetch()` calls (used here for the realtime call's
 * session/complete endpoints, since they aren't Inertia visits) need to
 * forward it manually as the `X-XSRF-TOKEN` header.
 */
export function xsrfHeader(): Record<string, string> {
    const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);

    if (!match) {
        return {};
    }

    return { 'X-XSRF-TOKEN': decodeURIComponent(match[1]) };
}
