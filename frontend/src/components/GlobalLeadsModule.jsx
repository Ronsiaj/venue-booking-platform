import React, { useState, useEffect, useCallback, useMemo } from 'react';
import Select from 'react-select';
import { globalLeadApi } from '../services/globalLeadApi';
import { staffApi } from '../services/staffApi';
import Pagination from './Pagination';
import { getAuthSession, normalizeLead } from '../services/Utils';

// Standard React Select styling adhering to the SM Groups Admin design system
const reactSelectStyles = {
    control: (base, state) => ({
        ...base,
        backgroundColor: '#ffffff',
        borderColor: state.isFocused ? '#d4a017' : 'rgba(40, 30, 20, 0.16)',
        borderRadius: '10px',
        minHeight: '42px',
        boxShadow: state.isFocused ? '0 0 0 3px rgba(212, 160, 23, 0.15)' : 'none',
        '&:hover': {
            borderColor: '#d4a017',
        },
        cursor: 'text',
    }),
    option: (base, state) => ({
        ...base,
        backgroundColor: state.isSelected
            ? '#d4a017'
            : state.isFocused
            ? '#faf7f2'
            : '#ffffff',
        color: state.isSelected ? '#1a1612' : 'var(--text-primary)',
        fontWeight: state.isSelected ? '700' : '500',
        fontSize: '0.86rem',
        cursor: 'pointer',
        '&:active': {
            backgroundColor: '#f5edd8',
        },
    }),
    placeholder: (base) => ({
        ...base,
        color: '#a8a29e',
        fontSize: '0.86rem',
    }),
    singleValue: (base) => ({
        ...base,
        color: 'var(--text-primary)',
        fontSize: '0.88rem',
        fontWeight: '600',
    }),
    menu: (base) => ({
        ...base,
        borderRadius: '12px',
        border: '1px solid rgba(201, 148, 20, 0.25)',
        boxShadow: '0 10px 25px rgba(0, 0, 0, 0.1)',
        zIndex: 9999,
        overflow: 'hidden',
    }),
    menuPortal: (base) => ({
        ...base,
        zIndex: 9999,
    }),
    clearIndicator: (base) => ({
        ...base,
        color: '#94a3b8',
        cursor: 'pointer',
        '&:hover': {
            color: '#dc2626',
        },
    }),
    dropdownIndicator: (base) => ({
        ...base,
        color: '#94a3b8',
        cursor: 'pointer',
        '&:hover': {
            color: '#d4a017',
        },
    }),
    indicatorSeparator: (base) => ({
        ...base,
        backgroundColor: 'rgba(40, 30, 20, 0.1)',
    }),
};

// Compact React Select styling for the Assign Lead dialog
const compactSelectStyles = {
    ...reactSelectStyles,
    control: (base, state) => ({
        ...base,
        backgroundColor: '#ffffff',
        borderColor: state.isFocused ? '#d4a017' : 'rgba(40, 30, 20, 0.16)',
        borderRadius: '8px',
        minHeight: '38px',
        height: '38px',
        boxShadow: state.isFocused ? '0 0 0 2px rgba(212, 160, 23, 0.15)' : 'none',
        '&:hover': {
            borderColor: '#d4a017',
        },
        cursor: 'text',
    }),
    valueContainer: (base) => ({
        ...base,
        padding: '0 10px',
    }),
    input: (base) => ({
        ...base,
        margin: 0,
        padding: 0,
    }),
    option: (base, state) => ({
        ...base,
        backgroundColor: state.isSelected
            ? '#d4a017'
            : state.isFocused
            ? '#faf7f2'
            : '#ffffff',
        color: state.isSelected ? '#1a1612' : 'var(--text-primary)',
        fontWeight: state.isSelected ? '700' : '500',
        fontSize: '0.82rem',
        padding: '7px 12px',
        cursor: 'pointer',
        '&:active': {
            backgroundColor: '#f5edd8',
        },
    }),
    placeholder: (base) => ({
        ...base,
        color: '#a8a29e',
        fontSize: '0.82rem',
    }),
    singleValue: (base) => ({
        ...base,
        color: 'var(--text-primary)',
        fontSize: '0.84rem',
        fontWeight: '600',
    }),
    menu: (base) => ({
        ...base,
        borderRadius: '10px',
        border: '1px solid rgba(201, 148, 20, 0.25)',
        boxShadow: '0 8px 24px rgba(0, 0, 0, 0.12)',
        zIndex: 99999,
        marginTop: '4px',
    }),
    menuList: (base) => ({
        ...base,
        padding: '4px 0',
        maxHeight: '180px',
    }),
};

// Domain options for filtering
const DOMAIN_OPTIONS = [
    { value: 'all', label: 'All Domains' },
    { value: 'hall', label: 'Hall Bookings' },
    { value: 'dining', label: 'Dining & Restaurant' },
    { value: 'catering', label: 'Catering & Events' },
];

// Backend supported lead statuses
const LEAD_STATUS_OPTIONS = [
    { value: 'all', label: 'All Statuses' },
    { value: 'new', label: 'New Inquiry' },
    { value: 'contacted', label: 'Contacted' },
    { value: 'follow_up', label: 'Follow Up' },
    { value: 'converted', label: 'Converted' },
    { value: 'lost', label: 'Lost' },
    { value: 'cancelled', label: 'Cancelled' },
];

/**
 * Global Lead Management Admin Module
 * Matches the exact design language, layout, stats grid, toolbar, table, and modal patterns of Room Type and Room.
 */
const GlobalLeadsModule = () => {
    // Current authenticated session
    const adminSession = getAuthSession('admin');
    const currentUser = adminSession?.user;
    const isSuperAdmin = currentUser?.role === 'admin' || currentUser?.role === 'super_admin';

    // ─── Leads Data State ───
    const [leads, setLeads] = useState([]);
    const [isLoading, setIsLoading] = useState(false);
    const [totalRecords, setTotalRecords] = useState(0);

    // ─── Filter & Search State (Strict Backend Parameters) ───
    const [selectedDomain, setSelectedDomain] = useState('all');
    const [selectedStatus, setSelectedStatus] = useState('all');
    const [searchQuery, setSearchQuery] = useState('');
    const [currentPage, setCurrentPage] = useState(1);
    const [pageSize, setPageSize] = useState(10);

    // ─── Summary Metrics ───
    const [dashboardMetrics, setDashboardMetrics] = useState(null);

    // ─── Modals State ───
    const [isViewModalOpen, setIsViewModalOpen] = useState(false);
    const [viewingLeadDetails, setViewingLeadDetails] = useState(null);
    const [isViewLoading, setIsViewLoading] = useState(false);

    const [isStatusModalOpen, setIsStatusModalOpen] = useState(false);
    const [statusTargetLead, setStatusTargetLead] = useState(null);
    const [newStatusValue, setNewStatusValue] = useState('new');

    const [isAssignModalOpen, setIsAssignModalOpen] = useState(false);
    const [assignTargetLead, setAssignTargetLead] = useState(null);
    const [assignAdminIdValue, setAssignAdminIdValue] = useState('');

    const [isConvertModalOpen, setIsConvertModalOpen] = useState(false);
    const [convertTargetLead, setConvertTargetLead] = useState(null);
    const [convertedAmountValue, setConvertedAmountValue] = useState('');

    // Form Submission & Toast States
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [toastMessage, setToastMessage] = useState(null);

    const showToast = (text, type = 'success') => {
        setToastMessage({ text, type });
        setTimeout(() => {
            setToastMessage(null);
        }, 3500);
    };

    // ─── Fetch Dashboard KPI Metrics ───
    const fetchDashboardMetrics = useCallback(async () => {
        try {
            const params = new URLSearchParams();
            if (selectedDomain !== 'all') params.append('domain', selectedDomain);
            if (selectedStatus !== 'all') params.append('status', selectedStatus);

            const res = await globalLeadApi.getLeadDashboard(params.toString());
            if (res.success && res.data?.data) {
                setDashboardMetrics(res.data.data);
            }
        } catch (err) {
            console.error('Failed to fetch lead dashboard metrics:', err);
        }
    }, [selectedDomain, selectedStatus]);

    // ─── Fetch Server Paginated Leads ───
    const fetchLeads = useCallback(async () => {
        setIsLoading(true);
        try {
            const params = new URLSearchParams();
            params.append('page', String(currentPage));
            params.append('limit', String(pageSize));

            if (selectedDomain && selectedDomain !== 'all') {
                params.append('domain', selectedDomain);
            }
            if (selectedStatus && selectedStatus !== 'all') {
                params.append('status', selectedStatus);
            }
            if (searchQuery.trim()) {
                params.append('search', searchQuery.trim().slice(0, 150));
            }

            params.append('sort_by', 'created_at');
            params.append('sort_order', 'DESC');

            const res = await globalLeadApi.listLeads(params.toString());

            if (res.success && res.data) {
                setLeads(res.data.data || []);
                if (res.data.pagination) {
                    setTotalRecords(res.data.pagination.total_records || 0);
                } else {
                    setTotalRecords((res.data.data || []).length);
                }
            } else {
                setLeads([]);
                setTotalRecords(0);
            }
        } catch (err) {
            console.error('Error fetching leads:', err);
            showToast('Failed to load leads list.', 'error');
        } finally {
            setIsLoading(false);
        }
    }, [currentPage, pageSize, selectedDomain, selectedStatus, searchQuery]);

    useEffect(() => {
        fetchLeads();
    }, [fetchLeads]);

    useEffect(() => {
        fetchDashboardMetrics();
    }, [fetchDashboardMetrics]);

    // Derived Statistics matching the exact Room Type stats cards pattern
    const stats = useMemo(() => {
        const total = dashboardMetrics?.summary?.total_leads ?? totalRecords;
        const newCount = dashboardMetrics?.summary?.new ?? leads.filter((l) => l.status === 'new').length;
        const converted = dashboardMetrics?.summary?.converted ?? leads.filter((l) => l.status === 'converted').length;
        const inProgress =
            (dashboardMetrics?.summary?.contacted || 0) +
            (dashboardMetrics?.summary?.follow_up || 0) ||
            leads.filter((l) => l.status === 'contacted' || l.status === 'follow_up').length;

        return { total, newCount, converted, inProgress };
    }, [dashboardMetrics, totalRecords, leads]);

    // Search options for React Select
    const searchSelectOptions = useMemo(() => {
        const options = [];
        leads.forEach((l) => {
            const norm = normalizeLead(l);
            if (norm.leadReference) {
                options.push({
                    value: norm.leadReference,
                    label: `${norm.leadReference} - ${norm.customerName || 'Customer'}`
                });
            }
            if (norm.customerName && !options.some((o) => o.value === norm.customerName)) {
                options.push({
                    value: norm.customerName,
                    label: `Customer: ${norm.customerName}`
                });
            }
            if (norm.customerPhone && !options.some((o) => o.value === norm.customerPhone)) {
                options.push({
                    value: norm.customerPhone,
                    label: `Phone: ${norm.customerPhone}`
                });
            }
        });
        return options;
    }, [leads]);

    const currentSelectValue = useMemo(() => {
        if (!searchQuery) return null;
        const match = searchSelectOptions.find((o) => o.value.toLowerCase() === searchQuery.toLowerCase());
        return match || { value: searchQuery, label: searchQuery };
    }, [searchQuery, searchSelectOptions]);

    // ─── Staff Options for Assign Modal (Retrieved from /auth/staff_list.php) ───
    const [assignableStaff, setAssignableStaff] = useState([]);

    const fetchAssignableStaff = useCallback(async () => {
        try {
            const res = await staffApi.listStaff({ limit: 100, status: 'active' });
            const staffData = res?.data?.data?.staff || res?.data?.staff || res?.data?.data || [];
            if (Array.isArray(staffData)) {
                setAssignableStaff(staffData);
            }
        } catch (err) {
            console.warn('Failed to load assignable staff from /auth/staff_list.php:', err);
        }
    }, []);

    useEffect(() => {
        fetchAssignableStaff();
    }, [fetchAssignableStaff]);

    const staffOptions = useMemo(() => {
        return assignableStaff.map((s) => ({
            value: Number(s.id || s.staff_id),
            label: `${s.name} (${s.email || 'Staff'})`
        }));
    }, [assignableStaff]);

    // ─── Reset All Filters ───
    const handleResetFilters = () => {
        setSelectedDomain('all');
        setSelectedStatus('all');
        setSearchQuery('');
        setCurrentPage(1);
    };

    // ─── Open Lead Details Modal ───
    const handleOpenView = async (lead) => {
        setIsViewModalOpen(true);
        setIsViewLoading(true);
        try {
            const res = await globalLeadApi.viewLead(lead.id);
            if (res.success && res.data?.data) {
                setViewingLeadDetails(res.data.data);
            } else {
                showToast(res.error || 'Failed to fetch lead details.', 'error');
                setIsViewModalOpen(false);
            }
        } catch (err) {
            console.error('Error fetching lead details:', err);
            showToast('Failed to fetch lead details.', 'error');
            setIsViewModalOpen(false);
        } finally {
            setIsViewLoading(false);
        }
    };

    // ─── Open Status Modal ───
    const handleOpenStatusModal = (lead) => {
        setStatusTargetLead(lead);
        setNewStatusValue(lead.status || 'new');
        setIsStatusModalOpen(true);
    };

    // ─── Submit Status Update ───
    const handleSubmitStatusUpdate = async (e) => {
        e.preventDefault();
        if (!statusTargetLead) return;

        setIsSubmitting(true);
        try {
            const res = await globalLeadApi.updateStatus({
                lead_id: statusTargetLead.id,
                status: newStatusValue
            });

            if (res.success) {
                showToast(res.data?.message || 'Lead status updated successfully.');
                setIsStatusModalOpen(false);
                fetchLeads();
                fetchDashboardMetrics();
                if (viewingLeadDetails && viewingLeadDetails.lead?.id === statusTargetLead.id) {
                    handleOpenView(statusTargetLead);
                }
            } else {
                showToast(res.error || 'Failed to update lead status.', 'error');
            }
        } catch (err) {
            console.error('Error updating status:', err);
            showToast('Failed to update lead status.', 'error');
        } finally {
            setIsSubmitting(false);
        }
    };

    // ─── Open Convert Modal ───
    const handleOpenConvertModal = (lead) => {
        setConvertTargetLead(lead);
        setConvertedAmountValue(
            lead.converted_amount !== null && lead.converted_amount !== undefined
                ? String(lead.converted_amount)
                : lead.estimated_amount !== null && lead.estimated_amount !== undefined
                ? String(lead.estimated_amount)
                : ''
        );
        setIsConvertModalOpen(true);
    };

    // ─── Submit Convert ───
    const handleSubmitConvert = async (e) => {
        e.preventDefault();
        if (!convertTargetLead) return;

        if (convertedAmountValue === '' || isNaN(Number(convertedAmountValue)) || Number(convertedAmountValue) < 0) {
            showToast('Please enter a valid non-negative converted amount.', 'error');
            return;
        }

        setIsSubmitting(true);
        try {
            const res = await globalLeadApi.convertLead({
                lead_id: convertTargetLead.id,
                converted_amount: Number(convertedAmountValue)
            });

            if (res.success) {
                showToast(res.data?.message || 'Lead converted successfully.');
                setIsConvertModalOpen(false);
                fetchLeads();
                fetchDashboardMetrics();
                if (viewingLeadDetails && viewingLeadDetails.lead?.id === convertTargetLead.id) {
                    handleOpenView(convertTargetLead);
                }
            } else {
                showToast(res.error || 'Failed to convert lead.', 'error');
            }
        } catch (err) {
            console.error('Error converting lead:', err);
            showToast('Failed to convert lead.', 'error');
        } finally {
            setIsSubmitting(false);
        }
    };

    // ─── Open Assign Modal ───
    const handleOpenAssignModal = (lead) => {
        fetchAssignableStaff();
        setAssignTargetLead(lead);
        setAssignAdminIdValue(lead.assigned_admin?.id ? String(lead.assigned_admin.id) : '');
        setIsAssignModalOpen(true);
    };

    // ─── Submit Assign ───
    const handleSubmitAssign = async (e) => {
        e.preventDefault();
        if (!assignTargetLead) return;

        setIsSubmitting(true);
        try {
            const res = await globalLeadApi.assignLead({
                lead_id: assignTargetLead.id,
                assigned_admin_id: assignAdminIdValue ? Number(assignAdminIdValue) : null
            });

            if (res.success) {
                showToast(res.data?.message || 'Lead assignment updated successfully.');
                setIsAssignModalOpen(false);
                fetchLeads();
                fetchDashboardMetrics();
                if (viewingLeadDetails && viewingLeadDetails.lead?.id === assignTargetLead.id) {
                    handleOpenView(assignTargetLead);
                }
            } else {
                showToast(res.error || 'Failed to assign lead.', 'error');
            }
        } catch (err) {
            console.error('Error assigning lead:', err);
            showToast('Failed to assign lead.', 'error');
        } finally {
            setIsSubmitting(false);
        }
    };

    // Formatters
    const formatCurrency = (val) => {
        if (val === null || val === undefined || isNaN(Number(val))) return '—';
        return `₹${Number(val).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
    };

    const formatDate = (dateString) => {
        if (!dateString) return '—';
        try {
            const d = new Date(dateString);
            return d.toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
        } catch {
            return dateString;
        }
    };

    const formatStatusLabel = (st) => {
        if (!st) return 'New';
        switch (st.toLowerCase()) {
            case 'new': return 'New';
            case 'contacted': return 'Contacted';
            case 'follow_up': return 'Follow Up';
            case 'converted': return 'Converted';
            case 'lost': return 'Lost';
            case 'cancelled': return 'Cancelled';
            default: return st.charAt(0).toUpperCase() + st.slice(1);
        }
    };

    return (
        <div className="room-types-module">
            {/* Toast Notification */}
            {toastMessage && (
                <div className={`admin-toast admin-toast--${toastMessage.type}`}>
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
                        <polyline points="22 4 12 14.01 9 11.01" />
                    </svg>
                    <span>{toastMessage.text}</span>
                </div>
            )}

            {/* Page Header matching exact Room Type pattern */}
            <div className="dashboard-page-header">
                <div className="dashboard-page-header__info">
                    <h2 className="dashboard-page-header__title">Global Leads Management</h2>
                    <p className="dashboard-page-header__desc">
                        Track, assign, and convert customer inquiries across Hall Bookings, Dining, and Catering services.
                    </p>
                </div>
                <div className="dashboard-page-header__actions">
                    <button
                        type="button"
                        className="admin-secondary-btn"
                        onClick={() => {
                            fetchLeads();
                            fetchDashboardMetrics();
                            showToast('Leads refreshed.');
                        }}
                    >
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                            <polyline points="23 4 23 10 17 10" />
                            <polyline points="1 20 1 14 7 14" />
                            <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15" />
                        </svg>
                        <span>Refresh Leads</span>
                    </button>
                </div>
            </div>

            {/* Stats Overview Bar matching Room Types 4-card grid */}
            <div className="room-types-stats-grid">
                <div className="room-type-stat-card">
                    <div className="room-type-stat-card__icon room-type-stat-card__icon--gold">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                            <rect x="3" y="3" width="7" height="7" />
                            <rect x="14" y="3" width="7" height="7" />
                            <rect x="14" y="14" width="7" height="7" />
                            <rect x="3" y="14" width="7" height="7" />
                        </svg>
                    </div>
                    <div className="room-type-stat-card__info">
                        <span className="room-type-stat-card__label">Total Inquiries</span>
                        <span className="room-type-stat-card__value">{stats.total}</span>
                    </div>
                </div>

                <div className="room-type-stat-card">
                    <div className="room-type-stat-card__icon room-type-stat-card__icon--blue">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
                            <polyline points="22 4 12 14.01 9 11.01" />
                        </svg>
                    </div>
                    <div className="room-type-stat-card__info">
                        <span className="room-type-stat-card__label">New Inquiries</span>
                        <span className="room-type-stat-card__value">{stats.newCount}</span>
                    </div>
                </div>

                <div className="room-type-stat-card">
                    <div className="room-type-stat-card__icon room-type-stat-card__icon--green">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                            <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" />
                        </svg>
                    </div>
                    <div className="room-type-stat-card__info">
                        <span className="room-type-stat-card__label">Converted</span>
                        <span className="room-type-stat-card__value">{stats.converted}</span>
                    </div>
                </div>

                <div className="room-type-stat-card">
                    <div className="room-type-stat-card__icon room-type-stat-card__icon--slate">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                            <circle cx="12" cy="12" r="10" />
                            <polyline points="12 6 12 12 16 14" />
                        </svg>
                    </div>
                    <div className="room-type-stat-card__info">
                        <span className="room-type-stat-card__label">In Progress</span>
                        <span className="room-type-stat-card__value">{stats.inProgress}</span>
                    </div>
                </div>
            </div>

            {/* Filter and Search Toolbar with React Select & Category Dropdown */}
            <div className="room-types-toolbar" style={{ flexWrap: 'wrap', gap: '10px', justifyContent: 'flex-start' }}>
                <div className="room-types-search-select-wrap" style={{ minWidth: '240px', maxWidth: '340px', flex: '1 1 240px' }}>
                    <Select
                        options={searchSelectOptions}
                        value={currentSelectValue}
                        onChange={(selected) => {
                            setSearchQuery(selected ? selected.value : '');
                            setCurrentPage(1);
                        }}
                        onInputChange={(inputValue, { action }) => {
                            if (action === 'input-change') {
                                setSearchQuery(inputValue);
                                setCurrentPage(1);
                            }
                        }}
                        placeholder="Search or select lead ref, customer, phone..."
                        isClearable
                        isSearchable
                        styles={reactSelectStyles}
                        className="react-select-container"
                        classNamePrefix="react-select"
                        noOptionsMessage={({ inputValue }) =>
                            inputValue ? `No lead matches "${inputValue}"` : 'No options'
                        }
                    />
                </div>

                <div style={{ display: 'flex', alignItems: 'center', gap: '8px', flexWrap: 'wrap' }}>
                    <div style={{ minWidth: '180px' }}>
                        <Select
                            options={DOMAIN_OPTIONS}
                            value={DOMAIN_OPTIONS.find((opt) => opt.value === selectedDomain) || DOMAIN_OPTIONS[0]}
                            onChange={(selected) => {
                                setSelectedDomain(selected ? selected.value : 'all');
                                setCurrentPage(1);
                            }}
                            isSearchable={false}
                            styles={reactSelectStyles}
                            className="react-select-container"
                            classNamePrefix="react-select"
                        />
                    </div>

                    <div style={{ minWidth: '180px' }}>
                        <Select
                            options={LEAD_STATUS_OPTIONS}
                            value={LEAD_STATUS_OPTIONS.find((opt) => opt.value === selectedStatus) || LEAD_STATUS_OPTIONS[0]}
                            onChange={(selected) => {
                                setSelectedStatus(selected ? selected.value : 'all');
                                setCurrentPage(1);
                            }}
                            placeholder="All Statuses"
                            isSearchable={false}
                            styles={reactSelectStyles}
                            className="react-select-container"
                            classNamePrefix="react-select"
                        />
                    </div>
                </div>
            </div>

            {/* Leads Data Table Card */}
            <div className="admin-table-card">
                {isLoading ? (
                    <div className="dashboard-loading-state" style={{ padding: '40px', textAlign: 'center', color: 'var(--text-secondary)' }}>
                        <div className="admin-spinner" style={{ width: '32px', height: '32px', border: '3px solid rgba(212, 160, 23, 0.2)', borderTopColor: '#d4a017', borderRadius: '50%', animation: 'spin 0.8s linear infinite', margin: '0 auto 12px' }} />
                        <p style={{ fontSize: '0.9rem', fontWeight: 500 }}>Loading leads from server...</p>
                    </div>
                ) : leads.length === 0 ? (
                    <div className="dashboard-empty-state">
                        <div className="dashboard-empty-state__icon-wrap">
                            <svg width="42" height="42" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round">
                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                                <circle cx="9" cy="7" r="4" />
                                <path d="M23 21v-2a4 4 0 0 0-3-3.87" />
                                <path d="M16 3.13a4 4 0 0 1 0 7.75" />
                            </svg>
                        </div>
                        <h4 className="dashboard-empty-state__title">No leads found</h4>
                        <p className="dashboard-empty-state__desc">
                            {searchQuery || selectedDomain !== 'all' || selectedStatus !== 'all'
                                ? 'No inquiries matched your filter parameters. Try resetting your search.'
                                : 'No customer inquiries recorded yet.'}
                        </p>
                        {searchQuery || selectedDomain !== 'all' || selectedStatus !== 'all' ? (
                            <button
                                type="button"
                                className="admin-secondary-btn"
                                onClick={handleResetFilters}
                            >
                                Reset Filters
                            </button>
                        ) : null}
                    </div>
                ) : (
                    <>
                        <div className="admin-table-responsive">
                            <table className="admin-table">
                                <thead>
                                    <tr>
                                        <th style={{ width: '60px' }}>#ID</th>
                                        <th>Lead Reference</th>
                                        <th>Domain</th>
                                        <th>Customer</th>
                                        <th>Event Date</th>
                                        <th>Guests</th>
                                        <th>Financials</th>
                                        <th>Status</th>
                                        <th>Assigned Staff</th>
                                        <th style={{ textAlign: 'right', width: '140px' }}>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {leads.map((lead) => {
                                        const norm = normalizeLead(lead);
                                        return (
                                            <tr key={norm.id || lead.id} className="admin-table-row">
                                                <td className="admin-table-cell--id">#{norm.id || lead.id}</td>
                                                <td>
                                                    <span className="room-type-slug-badge">
                                                        {norm.leadReference || `LEAD-${norm.id || lead.id}`}
                                                    </span>
                                                </td>
                                                <td>
                                                    <span className={`admin-domain-tag admin-domain-tag--${norm.domain || 'hall'}`}>
                                                        {norm.domain || 'general'}
                                                    </span>
                                                </td>
                                                <td>
                                                    <div className="room-type-name-cell">
                                                        <div>
                                                            <span className="room-type-name-text">{norm.customerName || '—'}</span>
                                                            <div style={{ fontSize: '0.78rem', color: 'var(--text-secondary)', marginTop: '2px' }}>
                                                                {norm.customerPhone || ''}
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span style={{ fontSize: '0.86rem', fontWeight: 600 }}>
                                                        {formatDate(norm.eventDate)}
                                                    </span>
                                                </td>
                                                <td>
                                                    <span style={{ fontSize: '0.86rem' }}>
                                                        {norm.guestCount !== null && norm.guestCount !== undefined
                                                            ? `${norm.guestCount} Guests`
                                                            : '—'}
                                                    </span>
                                                </td>
                                                <td>
                                                    {norm.convertedAmount !== null && norm.convertedAmount !== undefined ? (
                                                        <div>
                                                            <span style={{ fontWeight: 800, color: '#047857', fontSize: '0.88rem' }}>
                                                                {formatCurrency(norm.convertedAmount)}
                                                            </span>
                                                            <div style={{ fontSize: '0.72rem', color: '#047857', fontWeight: 700 }}>
                                                                Converted
                                                            </div>
                                                        </div>
                                                    ) : norm.estimatedAmount !== null && norm.estimatedAmount !== undefined ? (
                                                        <div>
                                                            <span style={{ fontWeight: 600, color: 'var(--text-primary)', fontSize: '0.88rem' }}>
                                                                {formatCurrency(norm.estimatedAmount)}
                                                            </span>
                                                            <div style={{ fontSize: '0.72rem', color: 'var(--text-muted)' }}>
                                                                Estimated
                                                            </div>
                                                        </div>
                                                    ) : (
                                                        <span className="text-muted-italic">—</span>
                                                    )}
                                                </td>
                                                <td>
                                                    <span className={`admin-status-badge admin-status-badge--${norm.status || 'new'}`}>
                                                        <span className="admin-status-dot" />
                                                        {formatStatusLabel(norm.status)}
                                                    </span>
                                                </td>
                                                <td>
                                                    <span style={{ fontSize: '0.84rem' }}>
                                                        {norm.assignedAdmin?.name || <span className="text-muted-italic">Unassigned</span>}
                                                    </span>
                                                </td>
                                            <td style={{ textAlign: 'right' }}>
                                                <div className="admin-table-actions">
                                                    {/* View Details */}
                                                    <button
                                                        type="button"
                                                        className="admin-action-btn admin-action-btn--view"
                                                        onClick={() => handleOpenView(lead)}
                                                        title="View Details"
                                                        aria-label={`View ${lead.lead_reference}`}
                                                    >
                                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                                            <path d="M1 12 s4 -8 11 -8 11 8 11 8 -4 8 -11 8 -11 -8 -11 -8 z" />
                                                            <circle cx="12" cy="12" r="3" />
                                                        </svg>
                                                    </button>

                                                    {/* Update Status */}
                                                    <button
                                                        type="button"
                                                        className="admin-action-btn admin-action-btn--edit"
                                                        onClick={() => handleOpenStatusModal(lead)}
                                                        title="Update Status"
                                                        aria-label={`Update Status for ${lead.lead_reference}`}
                                                    >
                                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                                            <path d="M11 4 H4 a2 2 0 0 0 -2 2 v14 a2 2 0 0 0 2 2 h14 a2 2 0 0 0 2 -2 v-7" />
                                                            <path d="M18.5 2.5 a2.121 2.121 0 0 1 3 3 L12 15 l-4 1 1 -4 9.5 -9.5 z" />
                                                        </svg>
                                                    </button>

                                                    {/* Convert Lead */}
                                                    {lead.status !== 'converted' && (
                                                        <button
                                                            type="button"
                                                            className="admin-action-btn admin-action-btn--convert"
                                                            onClick={() => handleOpenConvertModal(lead)}
                                                            title="Convert Lead & Record Amount"
                                                            aria-label={`Convert ${lead.lead_reference}`}
                                                        >
                                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round">
                                                                <polyline points="20 6 9 17 4 12" />
                                                            </svg>
                                                        </button>
                                                    )}

                                                    {/* Assign Staff (Admin Only) */}
                                                    {isSuperAdmin && (
                                                        <button
                                                            type="button"
                                                            className="admin-action-btn admin-action-btn--assign"
                                                            onClick={() => handleOpenAssignModal(lead)}
                                                            title="Assign Staff / Admin"
                                                            aria-label={`Assign ${lead.lead_reference}`}
                                                        >
                                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                                                <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                                                                <circle cx="8.5" cy="7" r="4" />
                                                                <line x1="20" y1="8" x2="20" y2="14" />
                                                                <line x1="23" y1="11" x2="17" y2="11" />
                                                            </svg>
                                                        </button>
                                                    )}
                                                </div>
                                            </td>
                                        </tr>
                                    ); })}
                                </tbody>
                            </table>
                        </div>
                        <Pagination
                            currentPage={currentPage}
                            totalItems={totalRecords}
                            pageSize={pageSize}
                            onPageChange={setCurrentPage}
                            onPageSizeChange={setPageSize}
                        />
                    </>
                )}
            </div>

            {/* ─── VIEW DETAILS MODAL ─── */}
            {isViewModalOpen && (() => {
                const norm = normalizeLead(viewingLeadDetails);
                return (
                    <div className="admin-modal-overlay" onClick={() => setIsViewModalOpen(false)}>
                        <div className="admin-modal admin-modal--view" onClick={(e) => e.stopPropagation()} role="dialog" aria-modal="true">
                            <div className="admin-modal__header">
                                <div className="admin-modal__title-wrap">
                                    <div className="admin-modal__badge-row">
                                        <span className="room-type-id-pill">
                                            {norm.leadReference || (norm.id ? `#${norm.id}` : 'Lead')}
                                        </span>
                                        {norm.domain && (
                                            <span className={`admin-domain-tag admin-domain-tag--${norm.domain}`}>
                                                {norm.domain}
                                            </span>
                                        )}
                                        {norm.status && (
                                            <span className={`admin-status-badge admin-status-badge--${norm.status}`}>
                                                <span className="admin-status-dot" />
                                                {formatStatusLabel(norm.status)}
                                            </span>
                                        )}
                                    </div>
                                    <h3 className="admin-modal__title">Lead Details</h3>
                                    <p className="admin-modal__subtitle">
                                        Submitted on {norm.createdAt || '—'}
                                    </p>
                                </div>
                                <button
                                    type="button"
                                    className="admin-modal__close-btn"
                                    onClick={() => setIsViewModalOpen(false)}
                                    aria-label="Close dialog"
                                >
                                    ✕
                                </button>
                            </div>

                        <div className="admin-modal__body">
                            {isViewLoading ? (
                                <div className="dashboard-loading-state" style={{ padding: '30px', textAlign: 'center' }}>
                                    <div className="admin-spinner" style={{ width: '28px', height: '28px', border: '3px solid rgba(212, 160, 23, 0.2)', borderTopColor: '#d4a017', borderRadius: '50%', animation: 'spin 0.8s linear infinite', margin: '0 auto 10px' }} />
                                    <p style={{ fontSize: '0.85rem' }}>Loading full inquiry details...</p>
                                </div>
                            ) : viewingLeadDetails ? (() => {
                                const norm = normalizeLead(viewingLeadDetails);
                                return (
                                    <div className="room-type-details-grid">
                                        {/* Customer Profile */}
                                        <div className="room-type-detail-item">
                                            <span className="room-type-detail-label">Customer Name</span>
                                            <span className="room-type-detail-val">
                                                <strong>{norm.customerName || '—'}</strong>
                                            </span>
                                        </div>
                                        <div className="room-type-detail-item">
                                            <span className="room-type-detail-label">Phone & Email</span>
                                            <span className="room-type-detail-val">
                                                {norm.customerPhone || '—'}
                                                {norm.customerEmail && (
                                                    <span style={{ display: 'block', fontSize: '0.78rem', color: 'var(--text-muted)' }}>
                                                        {norm.customerEmail}
                                                    </span>
                                                )}
                                            </span>
                                        </div>

                                        {/* Event Details */}
                                        <div className="room-type-detail-item">
                                            <span className="room-type-detail-label">Event Date & Time</span>
                                            <span className="room-type-detail-val">
                                                {formatDate(norm.eventDate)}
                                                {norm.eventTime && ` (${norm.eventTime})`}
                                            </span>
                                        </div>
                                        <div className="room-type-detail-item">
                                            <span className="room-type-detail-label">Guest Count</span>
                                            <span className="room-type-detail-val">
                                                {norm.guestCount !== null ? `${norm.guestCount} Guests` : '—'}
                                            </span>
                                        </div>

                                        {/* Financials */}
                                        <div className="room-type-detail-item">
                                            <span className="room-type-detail-label">Estimated Amount</span>
                                            <span className="room-type-detail-val">
                                                {formatCurrency(norm.estimatedAmount)}
                                            </span>
                                        </div>
                                        <div className="room-type-detail-item">
                                            <span className="room-type-detail-label">Converted Amount</span>
                                            <span className="room-type-detail-val" style={{ color: '#047857', fontWeight: 700 }}>
                                                {formatCurrency(norm.convertedAmount)}
                                            </span>
                                        </div>

                                        {/* Staff Assignment */}
                                        <div className="room-type-detail-item full-width">
                                            <span className="room-type-detail-label">Assigned Staff</span>
                                            <span className="room-type-detail-val">
                                                {norm.assignedAdmin?.name ? (
                                                    <span>
                                                        <strong>{norm.assignedAdmin.name}</strong> ({norm.assignedAdmin.role || 'Staff'}) — {norm.assignedAdmin.email}
                                                    </span>
                                                ) : (
                                                    <span className="text-muted-italic">Not assigned to any staff member</span>
                                                )}
                                            </span>
                                        </div>

                                        {/* Domain Specifics */}
                                        {viewingLeadDetails.domain_details && (
                                            <div className="room-type-detail-item full-width">
                                                <span className="room-type-detail-label">Domain Specifications ({(norm.domain || viewingLeadDetails.lead?.domain)?.toUpperCase()})</span>
                                                <div style={{ fontSize: '0.86rem', marginTop: '4px', lineHeight: '1.5' }}>
                                                    {(norm.domain === 'hall' || viewingLeadDetails.lead?.domain === 'hall') && viewingLeadDetails.domain_details.hall && (
                                                        <div>
                                                            <strong>Venue:</strong> {viewingLeadDetails.domain_details.hall.name} ({viewingLeadDetails.domain_details.hall_type?.name || 'Hall'})
                                                            {viewingLeadDetails.domain_details.event?.type && ` • Event: ${viewingLeadDetails.domain_details.event.type}`}
                                                        </div>
                                                    )}
                                                    {(norm.domain === 'dining' || viewingLeadDetails.lead?.domain === 'dining') && viewingLeadDetails.domain_details.dining_package && (
                                                        <div>
                                                            <strong>Package:</strong> {viewingLeadDetails.domain_details.dining_package.name}
                                                            {viewingLeadDetails.domain_details.event?.meal_type && ` • Meal: ${viewingLeadDetails.domain_details.event.meal_type}`}
                                                        </div>
                                                    )}
                                                    {(norm.domain === 'catering' || viewingLeadDetails.lead?.domain === 'catering') && viewingLeadDetails.domain_details.catering_package && (
                                                        <div>
                                                            <strong>Package:</strong> {viewingLeadDetails.domain_details.catering_package.name}
                                                            {viewingLeadDetails.domain_details.event?.venue_address && ` • Venue: ${viewingLeadDetails.domain_details.event.venue_address}`}
                                                        </div>
                                                    )}
                                                </div>
                                            </div>
                                        )}

                                        {/* Requirement Text */}
                                        <div className="room-type-detail-item full-width">
                                            <span className="room-type-detail-label">Customer Requirements & Notes</span>
                                            <p className="room-type-detail-text">
                                                {norm.otherRequirements || norm.requirement || <span className="text-muted-italic">No specific requirements noted.</span>}
                                            </p>
                                        </div>
                                    </div>
                                );
                            })() : null}
                        </div>

                        <div className="admin-modal__footer">
                            <button
                                type="button"
                                className="admin-secondary-btn"
                                onClick={() => setIsViewModalOpen(false)}
                            >
                                Close
                            </button>
                            {viewingLeadDetails?.lead && (
                                <>
                                    <button
                                        type="button"
                                        className="admin-primary-btn"
                                        onClick={() => {
                                            setIsViewModalOpen(false);
                                            handleOpenStatusModal(viewingLeadDetails.lead);
                                        }}
                                    >
                                        Update Status
                                    </button>
                                    {viewingLeadDetails.lead.status !== 'converted' && (
                                        <button
                                            type="button"
                                            className="admin-primary-btn"
                                            style={{ background: '#059669', borderColor: '#059669' }}
                                            onClick={() => {
                                                setIsViewModalOpen(false);
                                                handleOpenConvertModal(viewingLeadDetails.lead);
                                            }}
                                        >
                                            Convert Lead
                                        </button>
                                    )}
                                </>
                            )}
                        </div>
                    </div>
                </div>
                );
            })()}

            {/* ─── UPDATE STATUS MODAL ─── */}
            {isStatusModalOpen && (
                <div className="admin-modal-overlay" onClick={() => setIsStatusModalOpen(false)}>
                    <div className="admin-modal admin-modal--small" onClick={(e) => e.stopPropagation()} role="dialog" aria-modal="true">
                        <div className="admin-modal__header">
                            <div className="admin-modal__title-wrap">
                                <h3 className="admin-modal__title">Update Lead Status</h3>
                                <p className="admin-modal__subtitle">
                                    {statusTargetLead?.lead_reference || `#${statusTargetLead?.id}`} ({statusTargetLead?.customer?.name || 'Customer'})
                                </p>
                            </div>
                            <button
                                type="button"
                                className="admin-modal__close-btn"
                                onClick={() => setIsStatusModalOpen(false)}
                                aria-label="Close dialog"
                            >
                                ✕
                            </button>
                        </div>

                        <form onSubmit={handleSubmitStatusUpdate} className="admin-modal__form">
                            <div className="admin-modal__body" style={{ padding: '20px 26px' }}>
                                <div className="admin-form-group">
                                    <label className="admin-form-label" htmlFor="lead-status-select">
                                        Status <span className="admin-form-required">*</span>
                                    </label>
                                    <select
                                        id="lead-status-select"
                                        className="admin-form-select"
                                        value={newStatusValue}
                                        onChange={(e) => setNewStatusValue(e.target.value)}
                                    >
                                        <option value="new">New Inquiry</option>
                                        <option value="contacted">Contacted</option>
                                        <option value="follow_up">Follow Up</option>
                                        <option value="converted">Converted</option>
                                        <option value="lost">Lost</option>
                                        <option value="cancelled">Cancelled</option>
                                    </select>
                                </div>
                            </div>

                            <div className="admin-modal__footer">
                                <button
                                    type="button"
                                    className="admin-secondary-btn"
                                    onClick={() => setIsStatusModalOpen(false)}
                                    disabled={isSubmitting}
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    className="admin-primary-btn"
                                    disabled={isSubmitting}
                                >
                                    {isSubmitting ? 'Saving...' : 'Save Status'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* ─── CONVERT LEAD MODAL ─── */}
            {isConvertModalOpen && (
                <div className="admin-modal-overlay" onClick={() => setIsConvertModalOpen(false)}>
                    <div className="admin-modal admin-modal--small" onClick={(e) => e.stopPropagation()} role="dialog" aria-modal="true">
                        <div className="admin-modal__header">
                            <div className="admin-modal__title-wrap">
                                <h3 className="admin-modal__title">Convert Lead & Record Revenue</h3>
                                <p className="admin-modal__subtitle">
                                    {convertTargetLead?.lead_reference || `#${convertTargetLead?.id}`} ({convertTargetLead?.customer?.name || 'Customer'})
                                </p>
                            </div>
                            <button
                                type="button"
                                className="admin-modal__close-btn"
                                onClick={() => setIsConvertModalOpen(false)}
                                aria-label="Close dialog"
                            >
                                ✕
                            </button>
                        </div>

                        <form onSubmit={handleSubmitConvert} className="admin-modal__form">
                            <div className="admin-modal__body" style={{ padding: '20px 26px' }}>
                                <div className="admin-form-group">
                                    <label className="admin-form-label" htmlFor="converted-amount-input">
                                        Final Converted Amount (₹) <span className="admin-form-required">*</span>
                                    </label>
                                    <input
                                        id="converted-amount-input"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        required
                                        className="admin-form-input"
                                        placeholder="e.g. 50000.00"
                                        value={convertedAmountValue}
                                        onChange={(e) => setConvertedAmountValue(e.target.value)}
                                        autoFocus
                                    />
                                    <span className="admin-form-helper" style={{ marginTop: '8px', display: 'block' }}>
                                        Converting this lead will update its status to <strong>Converted</strong> and record the conversion timestamp.
                                    </span>
                                </div>
                            </div>

                            <div className="admin-modal__footer">
                                <button
                                    type="button"
                                    className="admin-secondary-btn"
                                    onClick={() => setIsConvertModalOpen(false)}
                                    disabled={isSubmitting}
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    className="admin-primary-btn"
                                    style={{ background: '#059669', borderColor: '#059669' }}
                                    disabled={isSubmitting}
                                >
                                    {isSubmitting ? 'Converting...' : 'Confirm Conversion'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* ─── ASSIGN LEAD MODAL ─── */}
            {isAssignModalOpen && (
                <div className="admin-modal-overlay" onClick={() => setIsAssignModalOpen(false)}>
                    <div className="admin-modal admin-modal--assign-lead" onClick={(e) => e.stopPropagation()} role="dialog" aria-modal="true">
                        <div className="admin-modal__header">
                            <div className="admin-modal__title-wrap">
                                <h3 className="admin-modal__title">Assign Lead to Staff / Admin</h3>
                                <p className="admin-modal__subtitle">
                                    {assignTargetLead?.lead_reference || `#${assignTargetLead?.id}`} ({assignTargetLead?.customer?.name || 'Customer'})
                                </p>
                            </div>
                            <button
                                type="button"
                                className="admin-modal__close-btn"
                                onClick={() => setIsAssignModalOpen(false)}
                                aria-label="Close dialog"
                            >
                                ✕
                            </button>
                        </div>

                        <form onSubmit={handleSubmitAssign} className="admin-modal__form">
                            <div className="admin-modal__body">
                                <div className="admin-form-group">
                                    <label className="admin-form-label" htmlFor="assigned-staff-select">
                                        Assign to Staff Member
                                    </label>
                                    <Select
                                        id="assigned-staff-select"
                                        options={staffOptions}
                                        value={
                                            assignAdminIdValue
                                                ? staffOptions.find((o) => o.value === Number(assignAdminIdValue)) || {
                                                      value: Number(assignAdminIdValue),
                                                      label: `Staff ID #${assignAdminIdValue}`
                                                  }
                                                : null
                                        }
                                        onChange={(selected) => setAssignAdminIdValue(selected ? String(selected.value) : '')}
                                        placeholder="Search and select staff member..."
                                        isClearable
                                        isSearchable
                                        styles={compactSelectStyles}
                                        menuPortalTarget={typeof document !== 'undefined' ? document.body : null}
                                        menuPosition="fixed"
                                        className="react-select-container"
                                        classNamePrefix="react-select"
                                        noOptionsMessage={() => 'No staff members registered in backend'}
                                    />
                                    <span className="admin-form-helper">
                                        Select an authorized Staff user from the backend, or clear to unassign this lead.
                                    </span>
                                </div>
                            </div>

                            <div className="admin-modal__footer">
                                <button
                                    type="button"
                                    className="admin-secondary-btn"
                                    onClick={() => setIsAssignModalOpen(false)}
                                    disabled={isSubmitting}
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    className="admin-primary-btn"
                                    disabled={isSubmitting}
                                >
                                    {isSubmitting ? 'Saving...' : 'Save Assignment'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </div>
    );
};

export default GlobalLeadsModule;
