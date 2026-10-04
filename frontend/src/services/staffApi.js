import { api } from './api';

/**
 * Staff Management API Service
 * Endpoint: /auth/staff_register.php
 * Strictly communicates with backend staff registration endpoint using authenticated Admin session.
 */
export const staffApi = {
    /**
     * Get staff list (Admin authorized role only)
     * Backend endpoint: GET /auth/staff_list.php
     * 
     * @param {Object|string} [params] - Query parameters (page, limit, search, status, sort_by, sort_order, staff_id)
     * @returns {Promise<{ success: boolean, data: { status: boolean, message: string, data: { staff: Array, filters: Object, pagination: Object } } }>}
     */
    listStaff: (params = {}) => {
        const query = typeof params === 'string'
            ? (params ? `?${params.replace(/^\?/, '')}` : '')
            : (params && Object.keys(params).length > 0
                ? `?${new URLSearchParams(params).toString()}`
                : '');
        return api.get(`/auth/staff_list.php${query}`);
    },

    /**
     * Register a new staff account (Admin authorized role only)
     * Backend endpoint: POST /auth/staff_register.php
     * 
     * @param {Object} staffData
     * @param {string} staffData.name - Staff full name (letters and spaces only)
     * @param {string} staffData.email - Staff email address
     * @param {string} staffData.phone - 10-digit Indian mobile number starting with 6-9
     * @param {string} staffData.password - Strong password (min 8 chars, uppercase, lowercase, number)
     * @param {string} staffData.confirm_password - Password confirmation
     * @returns {Promise<{ status: boolean, message: string, data?: { staff_id: number, name: string, email: string, phone: string, role: string, status: string } }>}
     */
    registerStaff: (staffData) => {
        return api.post('/auth/staff_register.php', {
            name: staffData.name?.trim(),
            email: staffData.email?.trim().toLowerCase(),
            phone: staffData.phone?.trim(),
            password: staffData.password,
            confirm_password: staffData.confirm_password,
        });
    },
};

export default staffApi;
