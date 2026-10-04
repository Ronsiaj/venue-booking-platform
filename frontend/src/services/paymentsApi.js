import { api } from './api';

/**
 * Payments Module API Service
 * Dedicated API connector for Admin Payments Dashboard and Transaction Audits
 * Strictly connects to backend endpoints without modifying database/gateway logic.
 */
export const paymentsApi = {
    /**
     * Get Payment Transactions & Financial Summary
     * Backed by /reports/payment_report.php
     * Supports filtering, search, date range, pagination, and KPI aggregation.
     */
    getPayments: (params = {}) => {
        const query = typeof params === 'string'
            ? (params ? `?${params.replace(/^\?/, '')}` : '')
            : (params && Object.keys(params).length > 0
                ? `?${new URLSearchParams(params).toString()}`
                : '');
        return api.get(`/reports/payment_report.php${query}`);
    },

    /**
     * Get Payment Transaction Details by Payment ID
     */
    getPaymentById: (paymentId) => {
        return api.get(`/reports/payment_report.php?payment_id=${encodeURIComponent(paymentId)}`);
    },

    /**
     * Get Payment Transactions by Booking ID
     */
    getPaymentsByBookingId: (bookingId) => {
        return api.get(`/reports/payment_report.php?booking_id=${encodeURIComponent(bookingId)}`);
    },

    /**
     * Get Payment Transactions by Customer ID
     */
    getPaymentsByCustomerId: (customerId) => {
        return api.get(`/reports/payment_report.php?customer_id=${encodeURIComponent(customerId)}`);
    },
};

export default paymentsApi;
