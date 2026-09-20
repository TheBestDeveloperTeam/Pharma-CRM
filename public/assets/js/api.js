/**
 * Pharma CRM API Client (v3.0)
 * Access token kept in module memory only.
 * Refresh token kept in sessionStorage only.
 * Zero tokens in localStorage.
 */

const API_BASE = '/api/v1';

let _memoryAccessToken = null;

export const apiClient = {
    getAccessToken() {
        return _memoryAccessToken;
    },
    
    setAccessToken(token) {
        _memoryAccessToken = token;
    },
    
    clearTokens() {
        _memoryAccessToken = null;
        try {
            sessionStorage.removeItem('crm_rt');
        } catch (e) {}
    },

    getRefreshToken() {
        try {
            return sessionStorage.getItem('crm_rt');
        } catch (e) {
            return null;
        }
    },

    setRefreshToken(token) {
        try {
            sessionStorage.setItem('crm_rt', token);
        } catch (e) {}
    },

    async request(endpoint, options = {}) {
        const url = `${API_BASE}${endpoint}`;
        
        const headers = {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            ...(options.headers || {})
        };

        const token = this.getAccessToken();
        if (token) {
            headers['Authorization'] = `Bearer ${token}`;
        }

        const config = {
            ...options,
            headers
        };

        try {
            const response = await fetch(url, config);
            const data = await response.json();

            if (!response.ok) {
                if (response.status === 401) {
                    this.clearTokens();
                }
                throw new Error(data.error?.message || `HTTP error ${response.status}`);
            }

            return data;
        } catch (error) {
            console.error('API Error:', error);
            throw error;
        }
    }
};

export default apiClient;
