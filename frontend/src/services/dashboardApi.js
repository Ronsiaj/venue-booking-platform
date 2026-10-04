import { api } from './api';

/**
 * Admin Dashboard API Service
 * Connects to the backend dashboard endpoints: overview, bookings, leads, and revenue.
 */
export const dashboardApi = {
    /**
     * Get primary dashboard overview metrics, KPIs, inventory, trends, and recent records.
     * @param {string|object} params - Optional query string or params object (e.g., from_date, to_date)
     */
    getOverview: (params = '', config = {}) => {
        const query = typeof params === 'string'
            ? (params ? `?${params.replace(/^\?/, '')}` : '')
            : (params && Object.keys(params).length > 0
                ? `?${new URLSearchParams(params).toString()}`
                : '');
        return api.get(`/dashboard/overview.php${query}`, { authRole: 'admin', ...config });
    },

    /**
     * Get booking-specific analytics and metrics.
     * @param {string|object} params - Optional query parameters
     * @param {object} config - Optional axios request config
     */
    getBookingStats: (params = '', config = {}) => {
        const query = typeof params === 'string'
            ? (params ? `?${params.replace(/^\?/, '')}` : '')
            : (params && Object.keys(params).length > 0
                ? `?${new URLSearchParams(params).toString()}`
                : '');
        return api.get(`/dashboard/booking.php${query}`, { authRole: 'admin', ...config });
    },

    /**
     * Get lead-specific metrics and conversion data.
     * @param {string|object} params - Optional query parameters
     * @param {object} config - Optional axios request config
     */
    getLeadStats: (params = '', config = {}) => {
        const query = typeof params === 'string'
            ? (params ? `?${params.replace(/^\?/, '')}` : '')
            : (params && Object.keys(params).length > 0
                ? `?${new URLSearchParams(params).toString()}`
                : '');
        return api.get(`/dashboard/lead.php${query}`, { authRole: 'admin', ...config });
    },

    /**
     * Get revenue and financial performance breakdown.
     * @param {string|object} params - Optional query parameters
     * @param {object} config - Optional axios request config
     */
    getRevenueStats: (params = '', config = {}) => {
        const query = typeof params === 'string'
            ? (params ? `?${params.replace(/^\?/, '')}` : '')
            : (params && Object.keys(params).length > 0
                ? `?${new URLSearchParams(params).toString()}`
                : '');
        return api.get(`/dashboard/revenue.php${query}`, { authRole: 'admin', ...config });
    },
};

export default dashboardApi;
