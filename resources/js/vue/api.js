// Small wrapper around fetch() for the StagePilot REST API (/api/v1).
// Authentication: the session cookie of the logged-in student (Laravel Sanctum, "SPA" mode).
// For PATCH/POST, Laravel checks the CSRF token: it is read from the XSRF-TOKEN cookie
// and sent back in the X-XSRF-TOKEN header.

function xsrfToken() {
    const cookie = document.cookie.split('; ').find((c) => c.startsWith('XSRF-TOKEN='));

    return cookie ? decodeURIComponent(cookie.split('=')[1]) : '';
}

export class ApiError extends Error {
    constructor(status, message) {
        super(message);
        this.status = status;
    }
}

async function request(method, url, body = null) {
    const response = await fetch(`/api/v1${url}`, {
        method,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-XSRF-TOKEN': xsrfToken(),
        },
        body: body ? JSON.stringify(body) : null,
    });

    const json = await response.json().catch(() => ({}));
    if (!response.ok) {
        const messages = {
            401: 'Session expirée : reconnecte-toi.',
            403: 'Cette candidature ne t\'appartient pas.',
            404: 'Candidature introuvable.',
            429: 'Trop de requêtes, patiente une minute.',
        };
        const validation = json.errors ? Object.values(json.errors).flat().join(' ') : null;
        throw new ApiError(response.status, validation || messages[response.status] || json.message || 'Erreur du serveur.');
    }

    return json;
}

export const api = {
    get: (url) => request('GET', url),
    patch: (url, body) => request('PATCH', url, body),
};
