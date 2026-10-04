import { api } from './api';

/**
 * Catering Module API Service
 * Handles Catering Packages and Catering Leads/Inquiries with full backend contracts.
 */
export const cateringApi = {
    // ─── Catering Packages ───
    listPackages: (queryString = '') =>
        api.get(`/catering/package/list.php${queryString ? `?${queryString}` : ''}`),

    viewPackage: (packageId) =>
        api.get(`/catering/package/view.php?package_id=${encodeURIComponent(packageId)}`),

    createPackage: (formData) =>
        api.post('/catering/package/create.php', formData),

    updatePackage: (formData) =>
        api.post('/catering/package/update.php', formData),

    deletePackage: (packageId) =>
        api.post('/catering/package/delete.php', { package_id: packageId }),

    // ─── Catering Leads / Inquiries ───
    listLeads: (queryString = '') =>
        api.get(`/catering/lead/list.php${queryString ? `?${queryString}` : ''}`),

    viewLead: (leadId) =>
        api.get(`/catering/lead/view.php?lead_id=${encodeURIComponent(leadId)}`),

    createLead: (leadData) =>
        api.post('/catering/lead/create.php', leadData),

    updateLeadStatus: (leadId, status, notes = '') =>
        api.post('/lead/update_status.php', {
            lead_id: leadId,
            status,
            notes
        }),

    // ─── Food Item Catalog for bundling into catering packages ───
    listFoodItems: (queryString = 'limit=100&status=active') =>
        api.get(`/food/item/list.php${queryString ? `?${queryString}` : ''}`),
};

export default cateringApi;
