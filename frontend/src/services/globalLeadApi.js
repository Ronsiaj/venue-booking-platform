import { api } from './api';

/**
 * Global Lead Module API Service
 * Handles all backend contracts for Global Leads (Hall, Dining, Catering inquiries),
 * including listing with multi-parameter filtering, viewing complete lead details & notifications,
 * updating status, assigning admin/staff, and converting leads with revenue tracking.
 */
export const globalLeadApi = {
    // ─── Lead Listing with Pagination & Filters ───
    listLeads: (queryString = '') =>
        api.get(`/lead/list.php${queryString ? `?${queryString}` : ''}`),

    // ─── Single Lead View with Domain Specifics & Notification Logs ───
    viewLead: (leadId) =>
        api.get(`/lead/view.php?lead_id=${encodeURIComponent(leadId)}`),

    // ─── Update Lead Status (new, contacted, follow_up, converted, lost, cancelled) ───
    updateStatus: ({ lead_id, status }) =>
        api.post('/lead/update_status.php', {
            lead_id: Number(lead_id),
            status: String(status).toLowerCase().trim()
        }),

    // ─── Assign / Reassign / Unassign Lead ───
    assignLead: ({ lead_id, assigned_admin_id }) =>
        api.post('/lead/assign.php', {
            lead_id: Number(lead_id),
            assigned_admin_id: assigned_admin_id !== null && assigned_admin_id !== '' && assigned_admin_id !== undefined
                ? Number(assigned_admin_id)
                : null
        }),

    // ─── Convert Lead with Converted Amount ───
    convertLead: ({ lead_id, converted_amount }) =>
        api.post('/lead/convert.php', {
            lead_id: Number(lead_id),
            converted_amount: Number(converted_amount)
        }),

    // ─── Lead Dashboard Analytics & Summary ───
    getLeadDashboard: (queryString = '') =>
        api.get(`/dashboard/lead.php${queryString ? `?${queryString}` : ''}`),
};

export default globalLeadApi;
