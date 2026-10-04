import { api } from './api';

/**
 * Reports Module API Service
 * Connects directly to the backend reporting APIs under /reports/.
 */
export const reportsApi = {
    /**
     * Room Booking & Occupancy Reports
     */
    getRoomReport: (params = {}, config = {}) => {
        const query = typeof params === 'string'
            ? (params ? `?${params.replace(/^\?/, '')}` : '')
            : (params && Object.keys(params).length > 0
                ? `?${new URLSearchParams(params).toString()}`
                : '');
        return api.get(`/reports/room_reports.php${query}`, { authRole: 'admin', ...config });
    },

    /**
     * Event Hall & Venue Utilization Reports
     */
    getHallReport: (params = {}, config = {}) => {
        const query = typeof params === 'string'
            ? (params ? `?${params.replace(/^\?/, '')}` : '')
            : (params && Object.keys(params).length > 0
                ? `?${new URLSearchParams(params).toString()}`
                : '');
        return api.get(`/reports/hall_report.php${query}`, { authRole: 'admin', ...config });
    },

    /**
     * Financial & Comprehensive Revenue Reports
     */
    getRevenueReport: (params = {}, config = {}) => {
        const query = typeof params === 'string'
            ? (params ? `?${params.replace(/^\?/, '')}` : '')
            : (params && Object.keys(params).length > 0
                ? `?${new URLSearchParams(params).toString()}`
                : '');
        return api.get(`/reports/revenue_report.php${query}`, { authRole: 'admin', ...config });
    },

    /**
     * Global Leads, Conversion & Pipeline Reports
     */
    getLeadReport: (params = {}, config = {}) => {
        const query = typeof params === 'string'
            ? (params ? `?${params.replace(/^\?/, '')}` : '')
            : (params && Object.keys(params).length > 0
                ? `?${new URLSearchParams(params).toString()}`
                : '');
        return api.get(`/reports/lead_report.php${query}`, { authRole: 'admin', ...config });
    },

    /**
     * Payment Audit & Razorpay Transaction Reports
     */
    getPaymentReport: (params = {}, config = {}) => {
        const query = typeof params === 'string'
            ? (params ? `?${params.replace(/^\?/, '')}` : '')
            : (params && Object.keys(params).length > 0
                ? `?${new URLSearchParams(params).toString()}`
                : '');
        return api.get(`/reports/payment_report.php${query}`, { authRole: 'admin', ...config });
    },

    /**
     * Restaurant Dining & Consumption Reports
     */
    getDiningReport: (params = {}, config = {}) => {
        const query = typeof params === 'string'
            ? (params ? `?${params.replace(/^\?/, '')}` : '')
            : (params && Object.keys(params).length > 0
                ? `?${new URLSearchParams(params).toString()}`
                : '');
        return api.get(`/reports/dining_report.php${query}`, { authRole: 'admin', ...config });
    },

    /**
     * Catering Event & Food Selection Reports
     */
    getCateringReport: (params = {}, config = {}) => {
        const query = typeof params === 'string'
            ? (params ? `?${params.replace(/^\?/, '')}` : '')
            : (params && Object.keys(params).length > 0
                ? `?${new URLSearchParams(params).toString()}`
                : '');
        return api.get(`/reports/catering_report.php${query}`, { authRole: 'admin', ...config });
    },

    /**
     * Auxiliary fetchers for React Select dropdowns
     */
    listRoomTypes: (config = {}) => api.get('/room_type/list.php?limit=100', { authRole: 'admin', ...config }),
    listRooms: (config = {}) => api.get('/room/list.php?limit=100', { authRole: 'admin', ...config }),
    listHalls: (config = {}) => api.get('/hall/list.php?limit=100', { authRole: 'admin', ...config }),
    listDiningPackages: (config = {}) => api.get('/dining/package_list.php?limit=100', { authRole: 'admin', ...config }),
    listCateringPackages: (config = {}) => api.get('/catering/package_list.php?limit=100', { authRole: 'admin', ...config }),
};

export default reportsApi;
