import { api } from './api';

/**
 * Notification Module API Service
 * Handles notification dispatch (Email, WhatsApp) and notification audit history logs.
 */
export const notificationApi = {
    // ─── Notification History with multi-filter & backend pagination ───
    getHistory: (queryString = '') =>
        api.get(`/notification/history.php${queryString ? `?${queryString}` : ''}`),

    // ─── Dispatch Email Notification ───
    sendEmail: ({ lead_id, notification_type = 'all' }) =>
        api.post('/notification/email.php', {
            lead_id: Number(lead_id),
            notification_type: String(notification_type).toLowerCase()
        }),

    // ─── Dispatch WhatsApp Notification ───
    sendWhatsApp: ({ lead_id, notification_type = 'all' }) =>
        api.post('/notification/whatsapp.php', {
            lead_id: Number(lead_id),
            notification_type: String(notification_type).toLowerCase()
        }),

    // ─── Fetch Active Leads for searchable dropdown in Send Modal ───
    listLeadsForNotification: (queryString = 'limit=100&sort_by=created_at&sort_order=DESC') =>
        api.get(`/lead/list.php?${queryString}`),
};

export default notificationApi;
