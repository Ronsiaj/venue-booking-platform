import React, { useState, useEffect, useCallback, useMemo } from 'react';
import Select from 'react-select';
import { notificationApi } from '../services/notificationApi';
import Pagination from './Pagination';

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

// Filter options for Channel
const CHANNEL_FILTER_OPTIONS = [
    { value: 'all', label: 'All Channels' },
    { value: 'whatsapp', label: 'WhatsApp' },
    { value: 'email', label: 'Email' },
];

// Filter options for Domain
const DOMAIN_FILTER_OPTIONS = [
    { value: 'all', label: 'All Domains' },
    { value: 'hall', label: 'Hall Bookings' },
    { value: 'dining', label: 'Dining & Restaurant' },
    { value: 'catering', label: 'Catering & Events' },
];

// Backend supported notification statuses
const NOTIFICATION_STATUS_OPTIONS = [
    { value: 'all', label: 'All Statuses' },
    { value: 'sent', label: 'Sent' },
    { value: 'pending', label: 'Pending' },
    { value: 'failed', label: 'Failed' },
];

// Helper functions to safely read fields from backend notification payload
const getNotificationChannel = (item) => item?.notification?.channel || item?.channel || 'email';
const getNotificationRecipient = (item) => item?.notification?.recipient || item?.recipient || '—';
const getNotificationStatus = (item) => item?.notification?.status || item?.status || 'pending';
const getNotificationSentAt = (item) => item?.notification?.sent_at || item?.sent_at || null;
const getNotificationCreatedAt = (item) => item?.notification?.created_at || item?.created_at || null;
const getNotificationMessage = (item) => item?.notification?.message || item?.message || '';
const getNotificationProviderId = (item) => item?.notification?.provider_message_id || item?.provider_message_id || null;
const getNotificationError = (item) => item?.notification?.error_message || item?.error_message || null;
const getLeadId = (item) => item?.lead?.id || item?.lead_id || '—';
const getLeadReference = (item) => item?.lead?.reference || item?.lead?.lead_reference || `#${item?.lead?.id || item?.lead_id || '—'}`;
const getLeadDomain = (item) => item?.lead?.domain || item?.domain || '';
const getCustomerName = (item) => item?.customer?.name || item?.lead?.customer?.name || '—';
const getCustomerPhone = (item) => item?.customer?.phone || item?.lead?.customer?.phone || '';
const getCustomerEmail = (item) => item?.customer?.email || item?.lead?.customer?.email || '';

/**
 * Notifications Admin Module
 * Complete backend integration for notification logs (WhatsApp & Email) and on-demand dispatching.
 * Strictly adheres to the UI/UX patterns of Room Type and Room.
 */
const NotificationsModule = () => {
    // ─── Notification History State ───
    const [notifications, setNotifications] = useState([]);
    const [summary, setSummary] = useState({
        total: 0,
        sent: 0,
        pending: 0,
        failed: 0,
        email: 0,
        whatsapp: 0
    });
    const [isLoading, setIsLoading] = useState(false);
    const [totalRecords, setTotalRecords] = useState(0);

    // ─── Filter & Search State (Strict Backend Parameters) ───
    const [selectedChannel, setSelectedChannel] = useState('all');
    const [selectedDomain, setSelectedDomain] = useState('all');
    const [selectedStatus, setSelectedStatus] = useState('all');
    const [searchQuery, setSearchQuery] = useState('');
    const [currentPage, setCurrentPage] = useState(1);
    const [pageSize, setPageSize] = useState(10);

    // ─── Lead Options for Sending Notifications ───
    const [leadOptions, setLeadOptions] = useState([]);
    const [isLeadsLoading, setIsLeadsLoading] = useState(false);

    // ─── Modals State ───
    const [isViewModalOpen, setIsViewModalOpen] = useState(false);
    const [viewingNotification, setViewingNotification] = useState(null);

    const [isSendModalOpen, setIsSendModalOpen] = useState(false);
    const [sendFormData, setSendFormData] = useState({
        channel: 'whatsapp',
        lead_id: '',
        notification_type: 'all'
    });
    const [formErrors, setFormErrors] = useState({});
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [toastMessage, setToastMessage] = useState(null);

    const showToast = (text, type = 'success') => {
        setToastMessage({ text, type });
        setTimeout(() => {
            setToastMessage(null);
        }, 3500);
    };

    // ─── Fetch Notification History ───
    const fetchHistory = useCallback(async () => {
        setIsLoading(true);
        try {
            const params = new URLSearchParams();
            params.append('page', String(currentPage));
            params.append('limit', String(pageSize));

            if (selectedChannel && selectedChannel !== 'all') {
                params.append('channel', selectedChannel);
            }
            if (selectedDomain && selectedDomain !== 'all') {
                params.append('domain', selectedDomain);
            }
            if (selectedStatus && selectedStatus !== 'all') {
                params.append('status', selectedStatus);
            }
            if (searchQuery.trim()) {
                params.append('search', searchQuery.trim().slice(0, 255));
            }

            params.append('sort_by', 'created_at');
            params.append('sort_order', 'DESC');

            const res = await notificationApi.getHistory(params.toString());

            if (res.success && res.data?.data) {
                setNotifications(res.data.data.notifications || []);
                if (res.data.data.summary) {
                    setSummary(res.data.data.summary);
                }
                if (res.data.data.pagination) {
                    setTotalRecords(res.data.data.pagination.total_records || 0);
                } else {
                    setTotalRecords((res.data.data.notifications || []).length);
                }
            } else {
                setNotifications([]);
                setTotalRecords(0);
            }
        } catch (err) {
            console.error('Error fetching notification history:', err);
            showToast('Failed to load notification history.', 'error');
        } finally {
            setIsLoading(false);
        }
    }, [currentPage, pageSize, selectedChannel, selectedDomain, selectedStatus, searchQuery]);

    useEffect(() => {
        fetchHistory();
    }, [fetchHistory]);

    // ─── Fetch Active Leads for Send Dropdown ───
    const fetchLeadOptions = useCallback(async () => {
        setIsLeadsLoading(true);
        try {
            const res = await notificationApi.listLeadsForNotification();
            if (res.success && res.data?.data) {
                const opts = res.data.data.map((lead) => ({
                    value: String(lead.id),
                    label: `${lead.lead_reference || `#${lead.id}`} - ${lead.customer?.name || 'Customer'} (${lead.domain?.toUpperCase()})`,
                    leadData: lead
                }));
                setLeadOptions(opts);
            }
        } catch (err) {
            console.error('Failed to load leads for notification:', err);
        } finally {
            setIsLeadsLoading(false);
        }
    }, []);

    useEffect(() => {
        if (isSendModalOpen) {
            fetchLeadOptions();
        }
    }, [isSendModalOpen, fetchLeadOptions]);

    // Derived Statistics matching Room Types stats cards pattern
    const stats = useMemo(() => {
        return {
            total: summary.total ?? totalRecords,
            sent: summary.sent ?? notifications.filter((n) => (n.notification?.status || n.status) === 'sent').length,
            pending: summary.pending ?? notifications.filter((n) => (n.notification?.status || n.status) === 'pending').length,
            failed: summary.failed ?? notifications.filter((n) => (n.notification?.status || n.status) === 'failed').length
        };
    }, [summary, totalRecords, notifications]);

    // Search options for React Select
    const searchSelectOptions = useMemo(() => {
        const options = [];
        notifications.forEach((n) => {
            const recipient = n.notification?.recipient || n.recipient;
            const ref = n.lead?.reference || n.lead?.lead_reference;
            const customerName = n.customer?.name || n.lead?.customer?.name;

            if (recipient && !options.some((o) => o.value === recipient)) {
                options.push({
                    value: recipient,
                    label: `Recipient: ${recipient}`
                });
            }
            if (ref && !options.some((o) => o.value === ref)) {
                options.push({
                    value: ref,
                    label: `Ref: ${ref}`
                });
            }
            if (customerName && !options.some((o) => o.value === customerName)) {
                options.push({
                    value: customerName,
                    label: `Customer: ${customerName}`
                });
            }
        });
        return options;
    }, [notifications]);

    const currentSelectValue = useMemo(() => {
        if (!searchQuery) return null;
        const match = searchSelectOptions.find((o) => o.value.toLowerCase() === searchQuery.toLowerCase());
        return match || { value: searchQuery, label: searchQuery };
    }, [searchQuery, searchSelectOptions]);

    // ─── Reset All Filters ───
    const handleResetFilters = () => {
        setSelectedChannel('all');
        setSelectedDomain('all');
        setSelectedStatus('all');
        setSearchQuery('');
        setCurrentPage(1);
    };

    // ─── Open View Details Modal ───
    const handleOpenView = (item) => {
        setViewingNotification(item);
        setIsViewModalOpen(true);
    };

    // ─── Open Send Notification Modal ───
    const handleOpenSend = (prefilledLead = null) => {
        setSendFormData({
            channel: 'whatsapp',
            lead_id: prefilledLead ? String(prefilledLead.id || prefilledLead.lead_id) : '',
            notification_type: 'all'
        });
        setFormErrors({});
        setIsSendModalOpen(true);
    };

    // ─── Submit Send Notification ───
    const handleSendSubmit = async (e) => {
        e.preventDefault();

        const errors = {};
        if (!sendFormData.lead_id) {
            errors.lead_id = 'Please select a target lead.';
        }

        if (Object.keys(errors).length > 0) {
            setFormErrors(errors);
            return;
        }

        setIsSubmitting(true);
        try {
            let res;
            if (sendFormData.channel === 'whatsapp') {
                res = await notificationApi.sendWhatsApp({
                    lead_id: Number(sendFormData.lead_id),
                    notification_type: sendFormData.notification_type
                });
            } else {
                res = await notificationApi.sendEmail({
                    lead_id: Number(sendFormData.lead_id),
                    notification_type: sendFormData.notification_type
                });
            }

            if (res.success) {
                showToast(res.data?.message || `${sendFormData.channel.toUpperCase()} notification dispatched successfully.`);
                setIsSendModalOpen(false);
                fetchHistory();
            } else {
                showToast(res.error || 'Failed to dispatch notification.', 'error');
            }
        } catch (err) {
            console.error('Error sending notification:', err);
            showToast('Failed to dispatch notification.', 'error');
        } finally {
            setIsSubmitting(false);
        }
    };

    // ─── Format Date Helper ───
    const formatDateTime = (dateString) => {
        if (!dateString) return '—';
        try {
            const d = new Date(dateString);
            return d.toLocaleString('en-IN', {
                day: '2-digit',
                month: 'short',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
                hour12: true
            });
        } catch {
            return dateString;
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

            {/* Page Header */}
            <div className="dashboard-page-header">
                <div className="dashboard-page-header__info">
                    <h2 className="dashboard-page-header__title">Notification Management</h2>
                    <p className="dashboard-page-header__desc">
                        Monitor automated WhatsApp and Email notification delivery, inspect audit logs, and dispatch alerts to staff and admins.
                    </p>
                </div>
                <div className="dashboard-page-header__actions">
                    <button
                        type="button"
                        className="admin-secondary-btn"
                        onClick={() => {
                            fetchHistory();
                            showToast('Notification logs refreshed.');
                        }}
                    >
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                            <polyline points="23 4 23 10 17 10" />
                            <polyline points="1 20 1 14 7 14" />
                            <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15" />
                        </svg>
                        <span>Refresh</span>
                    </button>
                    <button
                        type="button"
                        className="admin-primary-btn"
                        onClick={() => handleOpenSend()}
                    >
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round">
                            <line x1="22" y1="2" x2="11" y2="13" />
                            <polygon points="22 2 15 22 11 13 2 9 22 2" />
                        </svg>
                        <span>Send Notification</span>
                    </button>
                </div>
            </div>

            {/* Stats Overview Bar matching Room Types 4-card grid */}
            <div className="room-types-stats-grid">
                <div className="room-type-stat-card">
                    <div className="room-type-stat-card__icon room-type-stat-card__icon--gold">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9" />
                            <path d="M13.73 21a2 2 0 0 1-3.46 0" />
                        </svg>
                    </div>
                    <div className="room-type-stat-card__info">
                        <span className="room-type-stat-card__label">Total Dispatched</span>
                        <span className="room-type-stat-card__value">{stats.total}</span>
                    </div>
                </div>

                <div className="room-type-stat-card">
                    <div className="room-type-stat-card__icon room-type-stat-card__icon--green">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
                            <polyline points="22 4 12 14.01 9 11.01" />
                        </svg>
                    </div>
                    <div className="room-type-stat-card__info">
                        <span className="room-type-stat-card__label">Delivered (Sent)</span>
                        <span className="room-type-stat-card__value">{stats.sent}</span>
                    </div>
                </div>

                <div className="room-type-stat-card">
                    <div className="room-type-stat-card__icon room-type-stat-card__icon--blue">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                            <circle cx="12" cy="12" r="10" />
                            <polyline points="12 6 12 12 16 14" />
                        </svg>
                    </div>
                    <div className="room-type-stat-card__info">
                        <span className="room-type-stat-card__label">Pending Delivery</span>
                        <span className="room-type-stat-card__value">{stats.pending}</span>
                    </div>
                </div>

                <div className="room-type-stat-card">
                    <div className="room-type-stat-card__icon room-type-stat-card__icon--slate">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                            <circle cx="12" cy="12" r="10" />
                            <line x1="15" y1="9" x2="9" y2="15" />
                            <line x1="9" y1="9" x2="15" y2="15" />
                        </svg>
                    </div>
                    <div className="room-type-stat-card__info">
                        <span className="room-type-stat-card__label">Failed Attempts</span>
                        <span className="room-type-stat-card__value">{stats.failed}</span>
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
                        placeholder="Search or select recipient, ref, or customer..."
                        isClearable
                        isSearchable
                        styles={reactSelectStyles}
                        className="react-select-container"
                        classNamePrefix="react-select"
                        noOptionsMessage={({ inputValue }) =>
                            inputValue ? `No notification matches "${inputValue}"` : 'No options'
                        }
                    />
                </div>

                <div style={{ display: 'flex', gap: '8px', flexWrap: 'wrap', alignItems: 'center' }}>
                    {/* Channel Selector */}
                    <div style={{ minWidth: '150px' }}>
                        <Select
                            options={CHANNEL_FILTER_OPTIONS}
                            value={CHANNEL_FILTER_OPTIONS.find((opt) => opt.value === selectedChannel) || CHANNEL_FILTER_OPTIONS[0]}
                            onChange={(selected) => {
                                setSelectedChannel(selected ? selected.value : 'all');
                                setCurrentPage(1);
                            }}
                            isSearchable={false}
                            styles={reactSelectStyles}
                            className="react-select-container"
                            classNamePrefix="react-select"
                        />
                    </div>

                    {/* Domain Selector */}
                    <div style={{ minWidth: '170px' }}>
                        <Select
                            options={DOMAIN_FILTER_OPTIONS}
                            value={DOMAIN_FILTER_OPTIONS.find((opt) => opt.value === selectedDomain) || DOMAIN_FILTER_OPTIONS[0]}
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

                    {/* Status Selector */}
                    <div style={{ minWidth: '150px' }}>
                        <Select
                            options={NOTIFICATION_STATUS_OPTIONS}
                            value={NOTIFICATION_STATUS_OPTIONS.find((opt) => opt.value === selectedStatus) || NOTIFICATION_STATUS_OPTIONS[0]}
                            onChange={(selected) => {
                                setSelectedStatus(selected ? selected.value : 'all');
                                setCurrentPage(1);
                            }}
                            isSearchable={false}
                            styles={reactSelectStyles}
                            className="react-select-container"
                            classNamePrefix="react-select"
                            placeholder="All Statuses"
                        />
                    </div>
                </div>
            </div>

            {/* Notification Data Table Card */}
            <div className="admin-table-card">
                {isLoading ? (
                    <div className="dashboard-loading-state" style={{ padding: '40px', textAlign: 'center', color: 'var(--text-secondary)' }}>
                        <div className="admin-spinner" style={{ width: '32px', height: '32px', border: '3px solid rgba(212, 160, 23, 0.2)', borderTopColor: '#d4a017', borderRadius: '50%', animation: 'spin 0.8s linear infinite', margin: '0 auto 12px' }} />
                        <p style={{ fontSize: '0.9rem', fontWeight: 500 }}>Loading notification logs from server...</p>
                    </div>
                ) : notifications.length === 0 ? (
                    <div className="dashboard-empty-state">
                        <div className="dashboard-empty-state__icon-wrap">
                            <svg width="42" height="42" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round">
                                <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9" />
                                <path d="M13.73 21a2 2 0 0 1-3.46 0" />
                            </svg>
                        </div>
                        <h4 className="dashboard-empty-state__title">No notifications found</h4>
                        <p className="dashboard-empty-state__desc">
                            {searchQuery || selectedChannel !== 'all' || selectedDomain !== 'all' || selectedStatus !== 'all'
                                ? 'No notification logs matched your filter parameters. Try resetting your search.'
                                : 'No notification dispatches recorded in the system yet.'}
                        </p>
                        {searchQuery || selectedChannel !== 'all' || selectedDomain !== 'all' || selectedStatus !== 'all' ? (
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
                                        <th>Channel</th>
                                        <th>Recipient</th>
                                        <th>Lead & Domain</th>
                                        <th>Customer</th>
                                        <th>Status</th>
                                        <th>Sent / Created At</th>
                                        <th style={{ textAlign: 'right', width: '120px' }}>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {notifications.map((item) => {
                                        const channel = getNotificationChannel(item);
                                        const recipient = getNotificationRecipient(item);
                                        const status = getNotificationStatus(item);
                                        const leadRef = getLeadReference(item);
                                        const leadDomain = getLeadDomain(item);
                                        const customerName = getCustomerName(item);
                                        const customerPhone = getCustomerPhone(item);
                                        const sentTime = formatDateTime(getNotificationSentAt(item) || getNotificationCreatedAt(item));

                                        return (
                                            <tr key={item.id} className="admin-table-row">
                                                <td className="admin-table-cell--id">#{item.id}</td>
                                                <td>
                                                    <div style={{ display: 'inline-flex', alignItems: 'center', gap: '6px' }}>
                                                        {channel === 'whatsapp' ? (
                                                            <span
                                                                style={{
                                                                    padding: '4px 8px',
                                                                    borderRadius: '6px',
                                                                    backgroundColor: '#ecfdf5',
                                                                    color: '#047857',
                                                                    fontWeight: 700,
                                                                    fontSize: '0.78rem',
                                                                    display: 'inline-flex',
                                                                    alignItems: 'center',
                                                                    gap: '4px'
                                                                }}
                                                            >
                                                                <span>💬</span> WhatsApp
                                                            </span>
                                                        ) : (
                                                            <span
                                                                style={{
                                                                    padding: '4px 8px',
                                                                    borderRadius: '6px',
                                                                    backgroundColor: '#eff6ff',
                                                                    color: '#1d4ed8',
                                                                    fontWeight: 700,
                                                                    fontSize: '0.78rem',
                                                                    display: 'inline-flex',
                                                                    alignItems: 'center',
                                                                    gap: '4px'
                                                                }}
                                                            >
                                                                <span>✉️</span> Email
                                                            </span>
                                                        )}
                                                    </div>
                                                </td>
                                                <td>
                                                    <div style={{ fontWeight: 600, color: 'var(--text-primary)', fontSize: '0.88rem' }}>
                                                        {recipient}
                                                    </div>
                                                </td>
                                                <td>
                                                    <div style={{ display: 'flex', flexDirection: 'column', gap: '4px' }}>
                                                        <span className="room-type-slug-badge" style={{ alignSelf: 'flex-start' }}>
                                                            {leadRef}
                                                        </span>
                                                        {leadDomain && (
                                                            <span className={`admin-domain-tag admin-domain-tag--${leadDomain}`} style={{ alignSelf: 'flex-start' }}>
                                                                {leadDomain}
                                                            </span>
                                                        )}
                                                    </div>
                                                </td>
                                                <td>
                                                    <div className="room-type-name-cell">
                                                        <div>
                                                            <span className="room-type-name-text">
                                                                {customerName}
                                                            </span>
                                                            {customerPhone && (
                                                                <div style={{ fontSize: '0.78rem', color: 'var(--text-secondary)' }}>
                                                                    {customerPhone}
                                                                </div>
                                                            )}
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span className={`admin-status-badge admin-status-badge--${status === 'sent' ? 'active' : status === 'failed' ? 'lost' : 'inactive'}`}>
                                                        <span className="admin-status-dot" />
                                                        {status.toUpperCase()}
                                                    </span>
                                                </td>
                                                <td>
                                                    <span style={{ fontSize: '0.84rem' }}>
                                                        {sentTime}
                                                    </span>
                                                </td>
                                                <td style={{ textAlign: 'right' }}>
                                                    <div className="admin-table-actions">
                                                        {/* View Details */}
                                                        <button
                                                            type="button"
                                                            className="admin-action-btn admin-action-btn--view"
                                                            onClick={() => handleOpenView(item)}
                                                            title="View Notification Details"
                                                            aria-label={`View notification ${item.id}`}
                                                        >
                                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                                                <path d="M1 12 s4 -8 11 -8 11 8 11 8 -4 8 -11 8 -11 -8 -11 -8 z" />
                                                                <circle cx="12" cy="12" r="3" />
                                                            </svg>
                                                        </button>

                                                        {/* Resend / Dispatch Again */}
                                                        <button
                                                            type="button"
                                                            className="admin-action-btn admin-action-btn--edit"
                                                            onClick={() => handleOpenSend(item.lead || { id: item.lead_id || getLeadId(item) })}
                                                            title="Resend Notification for this Lead"
                                                            aria-label={`Resend for lead ${item.lead?.id || item.lead_id}`}
                                                        >
                                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                                                <line x1="22" y1="2" x2="11" y2="13" />
                                                                <polygon points="22 2 15 22 11 13 2 9 22 2" />
                                                            </svg>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        );
                                    })}
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

            {/* ─── VIEW NOTIFICATION DETAILS MODAL ─── */}
            {isViewModalOpen && viewingNotification && (() => {
                const channel = getNotificationChannel(viewingNotification);
                const recipient = getNotificationRecipient(viewingNotification);
                const status = getNotificationStatus(viewingNotification);
                const leadRef = getLeadReference(viewingNotification);
                const leadDomain = getLeadDomain(viewingNotification);
                const customerName = getCustomerName(viewingNotification);
                const customerPhone = getCustomerPhone(viewingNotification);
                const customerEmail = getCustomerEmail(viewingNotification);
                const message = getNotificationMessage(viewingNotification);
                const providerMsgId = getNotificationProviderId(viewingNotification);
                const errorMsg = getNotificationError(viewingNotification);
                const sentTime = formatDateTime(getNotificationSentAt(viewingNotification) || getNotificationCreatedAt(viewingNotification));

                return (
                    <div className="admin-modal-overlay" onClick={() => setIsViewModalOpen(false)}>
                        <div className="admin-modal admin-modal--view" onClick={(e) => e.stopPropagation()} role="dialog" aria-modal="true">
                            <div className="admin-modal__header">
                                <div className="admin-modal__title-wrap">
                                    <div className="admin-modal__badge-row">
                                        <span className="room-type-id-pill">
                                            LOG #{viewingNotification.id}
                                        </span>
                                        <span
                                            style={{
                                                padding: '2px 8px',
                                                borderRadius: '6px',
                                                fontSize: '0.72rem',
                                                fontWeight: 700,
                                                backgroundColor: channel === 'whatsapp' ? '#ecfdf5' : '#eff6ff',
                                                color: channel === 'whatsapp' ? '#047857' : '#1d4ed8',
                                                textTransform: 'uppercase'
                                            }}
                                        >
                                            {channel}
                                        </span>
                                        <span className={`admin-status-badge admin-status-badge--${status === 'sent' ? 'active' : status === 'failed' ? 'lost' : 'inactive'}`}>
                                            <span className="admin-status-dot" />
                                            {status.toUpperCase()}
                                        </span>
                                    </div>
                                    <h3 className="admin-modal__title">Notification Log Details</h3>
                                    <p className="admin-modal__subtitle">
                                        Dispatched on {sentTime}
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
                                <div className="room-type-details-grid">
                                    <div className="room-type-detail-item">
                                        <span className="room-type-detail-label">Recipient</span>
                                        <span className="room-type-detail-val">
                                            <strong>{recipient}</strong>
                                        </span>
                                    </div>
                                    <div className="room-type-detail-item">
                                        <span className="room-type-detail-label">Associated Lead</span>
                                        <span className="room-type-detail-val">
                                            {leadRef}
                                            {leadDomain && ` (${leadDomain.toUpperCase()})`}
                                        </span>
                                    </div>

                                    <div className="room-type-detail-item">
                                        <span className="room-type-detail-label">Customer Name</span>
                                        <span className="room-type-detail-val">
                                            {customerName}
                                        </span>
                                    </div>
                                    <div className="room-type-detail-item">
                                        <span className="room-type-detail-label">Customer Phone / Email</span>
                                        <span className="room-type-detail-val">
                                            {customerPhone ? `${customerPhone}${customerEmail ? ` • ${customerEmail}` : ''}` : customerEmail || '—'}
                                        </span>
                                    </div>

                                    {providerMsgId && (
                                        <div className="room-type-detail-item full-width">
                                            <span className="room-type-detail-label">Provider Message ID</span>
                                            <span className="room-type-detail-val code">
                                                {providerMsgId}
                                            </span>
                                        </div>
                                    )}

                                    {errorMsg && (
                                        <div className="room-type-detail-item full-width" style={{ backgroundColor: '#fef2f2', borderColor: '#fecaca' }}>
                                            <span className="room-type-detail-label" style={{ color: '#b91c1c' }}>Delivery Error</span>
                                            <p style={{ margin: 0, fontSize: '0.86rem', color: '#dc2626' }}>
                                                {errorMsg}
                                            </p>
                                        </div>
                                    )}

                                    <div className="room-type-detail-item full-width">
                                        <span className="room-type-detail-label">Message Payload</span>
                                        <pre
                                            style={{
                                                margin: '4px 0 0 0',
                                                fontFamily: 'inherit',
                                                fontSize: '0.86rem',
                                                lineHeight: '1.5',
                                                color: 'var(--text-primary)',
                                                whiteSpace: 'pre-wrap',
                                                backgroundColor: '#ffffff',
                                                padding: '12px',
                                                borderRadius: '8px',
                                                border: '1px solid rgba(40, 30, 20, 0.08)'
                                            }}
                                        >
                                            {message}
                                        </pre>
                                    </div>
                                </div>
                            </div>

                            <div className="admin-modal__footer">
                                <button
                                    type="button"
                                    className="admin-secondary-btn"
                                    onClick={() => setIsViewModalOpen(false)}
                                >
                                    Close
                                </button>
                                <button
                                    type="button"
                                    className="admin-primary-btn"
                                    onClick={() => {
                                        setIsViewModalOpen(false);
                                        handleOpenSend(viewingNotification.lead || { id: viewingNotification.lead_id || getLeadId(viewingNotification) });
                                    }}
                                >
                                    Resend Notification
                                </button>
                            </div>
                        </div>
                    </div>
                );
            })()}

            {/* ─── SEND NOTIFICATION MODAL ─── */}
            {isSendModalOpen && (
                <div className="admin-modal-overlay" onClick={() => setIsSendModalOpen(false)}>
                    <div className="admin-modal" onClick={(e) => e.stopPropagation()} role="dialog" aria-modal="true">
                        <div className="admin-modal__header">
                            <div className="admin-modal__title-wrap">
                                <h3 className="admin-modal__title">Dispatch Notification</h3>
                                <p className="admin-modal__subtitle">
                                    Send immediate WhatsApp or Email alerts for a specific customer inquiry.
                                </p>
                            </div>
                            <button
                                type="button"
                                className="admin-modal__close-btn"
                                onClick={() => setIsSendModalOpen(false)}
                                aria-label="Close dialog"
                            >
                                ✕
                            </button>
                        </div>

                        <form onSubmit={handleSendSubmit} className="admin-modal__form">
                            <div className="admin-modal__body">
                                {/* Notification Channel */}
                                <div className="admin-form-group">
                                    <label className="admin-form-label" htmlFor="notification-channel-select">
                                        Channel <span className="admin-form-required">*</span>
                                    </label>
                                    <select
                                        id="notification-channel-select"
                                        className="admin-form-select"
                                        value={sendFormData.channel}
                                        onChange={(e) => setSendFormData((prev) => ({ ...prev, channel: e.target.value }))}
                                    >
                                        <option value="whatsapp">WhatsApp Alert</option>
                                        <option value="email">Email Notification</option>
                                    </select>
                                </div>

                                {/* Target Lead Selection */}
                                <div className="admin-form-group">
                                    <label className="admin-form-label" htmlFor="notification-lead-select">
                                        Target Lead / Inquiry <span className="admin-form-required">*</span>
                                    </label>
                                    {isLeadsLoading ? (
                                        <div style={{ fontSize: '0.84rem', color: 'var(--text-muted)' }}>Loading leads list...</div>
                                    ) : (
                                        <Select
                                            options={leadOptions}
                                            value={leadOptions.find((o) => o.value === String(sendFormData.lead_id)) || null}
                                            onChange={(opt) => {
                                                setSendFormData((prev) => ({ ...prev, lead_id: opt ? opt.value : '' }));
                                                if (formErrors.lead_id) setFormErrors((prev) => ({ ...prev, lead_id: null }));
                                            }}
                                            placeholder="Search or select a lead..."
                                            isClearable
                                            isSearchable
                                            styles={reactSelectStyles}
                                            className="react-select-container"
                                            classNamePrefix="react-select"
                                        />
                                    )}
                                    {formErrors.lead_id && (
                                        <span className="admin-form-error">
                                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                                            {formErrors.lead_id}
                                        </span>
                                    )}
                                </div>

                                {/* Recipient Audience Type */}
                                <div className="admin-form-group">
                                    <label className="admin-form-label" htmlFor="notification-type-select">
                                        Recipient Scope <span className="admin-form-required">*</span>
                                    </label>
                                    <select
                                        id="notification-type-select"
                                        className="admin-form-select"
                                        value={sendFormData.notification_type}
                                        onChange={(e) => setSendFormData((prev) => ({ ...prev, notification_type: e.target.value }))}
                                    >
                                        <option value="all">All (Active Admins & Assigned Staff)</option>
                                        <option value="admin">Active Admins Only</option>
                                        <option value="assigned">Assigned Staff / Admin Only</option>
                                    </select>
                                    <span className="admin-form-helper">
                                        Controls which internal staff or administrator receives the dispatch.
                                    </span>
                                </div>
                            </div>

                            <div className="admin-modal__footer">
                                <button
                                    type="button"
                                    className="admin-secondary-btn"
                                    onClick={() => setIsSendModalOpen(false)}
                                    disabled={isSubmitting}
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    className="admin-primary-btn"
                                    disabled={isSubmitting}
                                >
                                    {isSubmitting ? 'Dispatching...' : 'Dispatch Now'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </div>
    );
};

export default NotificationsModule;
