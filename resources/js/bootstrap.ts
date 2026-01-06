import axios from 'axios';

if (typeof window !== 'undefined' && typeof (window as any).process === 'undefined') {
    (window as any).process = { env: {} };
}

declare global {
    interface Window {
        axios: typeof axios;
    }
}

window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
window.axios.defaults.withCredentials = true;
window.axios.defaults.withXSRFToken = true;

// Function to get CSRF token
function getCsrfToken(): string | null {
    // Try meta tag first
    const metaToken = document.head.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    if (metaToken) return metaToken;
    
    // Try cookie as fallback
    const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]+)/);
    if (match && match[1]) {
        return decodeURIComponent(match[1]);
    }
    
    return null;
}

// Set initial CSRF token
const token = getCsrfToken();
if (token) {
    window.axios.defaults.headers.common['X-CSRF-TOKEN'] = token;
}

// Update CSRF token before each request
window.axios.interceptors.request.use((config) => {
    const currentToken = getCsrfToken();
    if (currentToken) {
        (config.headers as any)['X-CSRF-TOKEN'] = currentToken;
        (config.headers as any)['X-XSRF-TOKEN'] = currentToken;
    }
    return config;
});

window.axios.interceptors.response.use(
    (response) => response,
    (error) => {
        const status = error?.response?.status;
        if (status === 401) {
            const redirectFlag = 'redirecting_to_login';
            if (window.location.pathname !== '/login' && !sessionStorage.getItem(redirectFlag)) {
                sessionStorage.setItem(redirectFlag, '1');
                const key = 'login_redirect_attempted_at';
                const last = Number(sessionStorage.getItem(key) || '0');
                const now = Date.now();
                if (now - last > 60_000) {
                    sessionStorage.setItem(key, String(now));
                    window.location.href = '/login';
                }
            }
        }
        // Don't auto-reload on 419 - let Inertia handle it
        return Promise.reject(error);
    }
);
