// Small JSON helpers for the few endpoints that are not Inertia visits
// (chunked uploads, autosaved test answers). Laravel's CSRF check accepts the
// XSRF-TOKEN cookie echoed back in the X-XSRF-TOKEN header.

export function xsrfToken(): string {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]) : '';
}

export function jsonHeaders(): Record<string, string> {
    return {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-XSRF-TOKEN': xsrfToken(),
    };
}
