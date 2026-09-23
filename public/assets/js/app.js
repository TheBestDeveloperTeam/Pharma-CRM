/**
 * app.js - Main Application Client
 * Handles JWT Bearer authentication, API requests, and view hydration.
 */

const AppClient = {
    apiBase: '/api/v1',
    
    init() {
        const token = sessionStorage.getItem('jwt_token');
        if (!token && !window.location.pathname.includes('/login')) {
            window.location.href = '/admin/login';
            return;
        }
        
        // Expose to window
        window.AppClient = this;
    },

    async fetchAPI(endpoint, options = {}) {
        const token = sessionStorage.getItem('jwt_token');
        const headers = {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            ...options.headers
        };

        if (token) {
            headers['Authorization'] = `Bearer ${token}`;
        }

        try {
            const response = await fetch(`${this.apiBase}${endpoint}`, {
                ...options,
                headers
            });

            if (response.status === 401) {
                // Unauthorized - token expired or invalid
                sessionStorage.removeItem('jwt_token');
                window.location.href = '/admin/login';
                return null;
            }

            const data = await response.json();
            if (!response.ok) {
                throw new Error(data.message || 'API Error');
            }
            return data;
        } catch (error) {
            console.error('API Request Failed:', error);
            throw error;
        }
    },
    
    // Auth helpers
    async login(email, password) {
        const result = await this.fetchAPI('/oauth/token', {
            method: 'POST',
            body: JSON.stringify({ email, password })
        });
        
        if (result && result.token) {
            sessionStorage.setItem('jwt_token', result.token);
            return true;
        }
        return false;
    },
    
    logout() {
        sessionStorage.removeItem('jwt_token');
        window.location.href = '/admin/login';
    }
};

document.addEventListener('DOMContentLoaded', () => {
    AppClient.init();
});
