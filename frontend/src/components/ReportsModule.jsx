import React, { useState, useEffect, useCallback, useMemo } from 'react';
import Select from 'react-select';
import Pagination from './Pagination';
import { reportsApi } from '../services/reportsApi';
import { normalizeLead } from '../services/Utils';

/**
 * Custom React Select styling matching SM Groups Admin theme
 */
const reactSelectStyles = {
    control: (base, state) => ({
        ...base,
        backgroundColor: '#ffffff',
        borderColor: state.isFocused ? '#d4a017' : 'rgba(40, 30, 20, 0.16)',
        borderRadius: '10px',
        minHeight: '40px',
        fontSize: '0.86rem',
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
        fontSize: '0.84rem',
        cursor: 'pointer',
        '&:active': {
            backgroundColor: '#f5edd8',
        },
    }),
    placeholder: (base) => ({
        ...base,
        color: '#a8a29e',
        fontSize: '0.84rem',
    }),
    singleValue: (base) => ({
        ...base,
        color: 'var(--text-primary)',
        fontWeight: '600',
        fontSize: '0.86rem',
    }),
    input: (base) => ({
        ...base,
        color: 'var(--text-primary)',
        fontSize: '0.86rem',
    }),
    menu: (base) => ({
        ...base,
        borderRadius: '12px',
        border: '1px solid rgba(201, 148, 20, 0.25)',
        boxShadow: '0 10px 25px rgba(0, 0, 0, 0.1)',
        zIndex: 50,
        overflow: 'hidden',
    }),
};

const REPORT_TABS = [
    { id: 'room', label: 'Room Bookings' },
    { id: 'revenue', label: 'Revenue & Finance' },
    { id: 'hall', label: 'Event Halls' },
    { id: 'lead', label: 'Global Leads' },
    { id: 'payment', label: 'Payments Audit' },
    { id: 'dining', label: 'Dining Reports' },
    { id: 'catering', label: 'Catering Reports' },
];

const ReportsModule = ({ onNavigate }) => {
    const [activeReportTab, setActiveReportTab] = useState('room');
    const [reportData, setReportData] = useState(null);
    const [isLoading, setIsLoading] = useState(true);
    const [error, setError] = useState(null);

    // Common Filter State
    const [searchQuery, setSearchQuery] = useState('');
    const [fromDate, setFromDate] = useState('');
    const [toDate, setToDate] = useState('');
    const [statusFilter, setStatusFilter] = useState('all');
    const [domainFilter, setDomainFilter] = useState('all');
    const [minAmount, setMinAmount] = useState('');
    const [maxAmount, setMaxAmount] = useState('');

    // Pagination State
    const [currentPage, setCurrentPage] = useState(1);
    const [pageSize, setPageSize] = useState(20);
    const [totalRecords, setTotalRecords] = useState(0);

    // Selected record detail modal
    const [viewingRecord, setViewingRecord] = useState(null);

    // Currency Formatter
    const formatCurrency = (val) => {
        if (val === null || val === undefined || isNaN(Number(val))) return '₹0.00';
        return `₹${Number(val).toLocaleString('en-IN', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        })}`;
    };

    // Date Formatter
    const formatDate = (dateString) => {
        if (!dateString) return '—';
        try {
            const d = new Date(dateString);
            if (isNaN(d.getTime())) return dateString;
            return d.toLocaleDateString('en-IN', {
                day: '2-digit',
                month: 'short',
                year: 'numeric',
            });
        } catch {
            return dateString;
        }
    };

    // Format Status String
    const formatStatusLabel = (status) => {
        if (!status) return 'Pending';
        return status
            .split('_')
            .map((word) => word.charAt(0).toUpperCase() + word.slice(1).toLowerCase())
            .join(' ');
    };

    // Helper for Status Badge Class
    const getStatusBadgeClass = (status) => {
        const s = (status || '').toLowerCase();
        if (['confirmed', 'active', 'completed', 'paid', 'captured', 'sent'].includes(s)) {
            return 'admin-status-badge--confirmed';
        }
        if (['checked_in', 'contacted', 'authorized'].includes(s)) {
            return 'admin-status-badge--checked_in';
        }
        if (['checked_out', 'converted', 'follow_up'].includes(s)) {
            return 'admin-status-badge--checked_out';
        }
        if (['cancelled', 'lost', 'failed', 'inactive', 'refunded', 'blocked'].includes(s)) {
            return 'admin-status-badge--cancelled';
        }
        return 'admin-status-badge--pending';
    };

    // Fetch report data based on active tab and filters
    const fetchReport = useCallback(async () => {
        setIsLoading(true);
        setError(null);
        try {
            const params = {
                page: currentPage,
                limit: pageSize,
            };

            if (searchQuery.trim()) params.search = searchQuery.trim();
            if (fromDate) params.from_date = fromDate;
            if (toDate) params.to_date = toDate;
            if (minAmount) params.min_amount = minAmount;
            if (maxAmount) params.max_amount = maxAmount;

            let res;
            if (activeReportTab === 'room') {
                if (statusFilter !== 'all') {
                    if (['created', 'authorized', 'captured', 'refunded', 'failed', 'paid'].includes(statusFilter)) {
                        params.payment_status = statusFilter === 'paid' ? 'captured' : statusFilter;
                    } else {
                        params.booking_status = statusFilter;
                    }
                }
                res = await reportsApi.getRoomReport(params);
            } else if (activeReportTab === 'revenue') {
                if (domainFilter !== 'all') params.domain = domainFilter;
                if (statusFilter !== 'all') params.payment_status = statusFilter;
                res = await reportsApi.getRevenueReport(params);
            } else if (activeReportTab === 'hall') {
                if (statusFilter !== 'all') params.lead_status = statusFilter;
                res = await reportsApi.getHallReport(params);
            } else if (activeReportTab === 'lead') {
                if (domainFilter !== 'all') params.domain = domainFilter;
                if (statusFilter !== 'all') params.status = statusFilter;
                res = await reportsApi.getLeadReport(params);
            } else if (activeReportTab === 'payment') {
                if (statusFilter !== 'all') params.status = statusFilter;
                res = await reportsApi.getPaymentReport(params);
            } else if (activeReportTab === 'dining') {
                if (statusFilter !== 'all') params.status = statusFilter;
                res = await reportsApi.getDiningReport(params);
            } else if (activeReportTab === 'catering') {
                if (statusFilter !== 'all') params.lead_status = statusFilter;
                res = await reportsApi.getCateringReport(params);
            }

            // Safely unpack the inner payload from backend response:
            if (res && res.success && res.data) {
                const payload = (res.data.data && typeof res.data.data === 'object')
                    ? res.data.data
                    : res.data;
                setReportData(payload);
                const pagination = payload.pagination || payload.meta?.pagination;
                if (pagination) {
                    const total = pagination.total_records !== undefined
                        ? pagination.total_records
                        : (pagination.total !== undefined ? pagination.total : 0);
                    setTotalRecords(Number(total) || 0);
                } else {
                    const list = payload.records || payload.bookings || payload.leads || payload.payments || [];
                    setTotalRecords(Array.isArray(list) ? list.length : 0);
                }
            } else if (res && res.data && typeof res.data === 'object') {
                const payload = (res.data.data && typeof res.data.data === 'object')
                    ? res.data.data
                    : res.data;
                setReportData(payload);
                const pagination = payload.pagination || payload.meta?.pagination;
                if (pagination) {
                    const total = pagination.total_records !== undefined
                        ? pagination.total_records
                        : (pagination.total !== undefined ? pagination.total : 0);
                    setTotalRecords(Number(total) || 0);
                } else {
                    const list = payload.records || payload.bookings || payload.leads || payload.payments || [];
                    setTotalRecords(Array.isArray(list) ? list.length : 0);
                }
            } else {
                setReportData(null);
                setTotalRecords(0);
                if (res && res.error) {
                    setError(res.error);
                }
            }
        } catch (err) {
            console.error('Error fetching report:', err);
            setError(err?.message || 'Failed to load report from server.');
            setReportData(null);
        } finally {
            setIsLoading(false);
        }
    }, [activeReportTab, currentPage, pageSize, searchQuery, fromDate, toDate, statusFilter, domainFilter, minAmount, maxAmount]);

    useEffect(() => {
        fetchReport();
    }, [fetchReport]);

    // Handle tab change
    const handleTabChange = (tabId) => {
        setActiveReportTab(tabId);
        setCurrentPage(1);
        setStatusFilter('all');
        setDomainFilter('all');
        setSearchQuery('');
        setFromDate('');
        setToDate('');
        setMinAmount('');
        setMaxAmount('');
    };

    // Reset filters
    const handleResetFilters = () => {
        setSearchQuery('');
        setFromDate('');
        setToDate('');
        setStatusFilter('all');
        setDomainFilter('all');
        setMinAmount('');
        setMaxAmount('');
        setCurrentPage(1);
    };

    // Extract report datasets
    const summary = reportData?.summary || reportData?.kpis || reportData?.revenue || reportData?.payment_summary || {};
    const rawRecords =
        reportData?.bookings ||
        reportData?.records ||
        reportData?.leads ||
        reportData?.payments ||
        reportData?.hall_leads ||
        reportData?.dining_leads ||
        reportData?.catering_leads ||
        [];

    const records = Array.isArray(rawRecords) ? rawRecords : [];

    // Search options for React Select
    const searchSelectOptions = useMemo(() => {
        if (!records || records.length === 0) return [];
        const opts = [];
        records.forEach((rec) => {
            if (rec.booking_reference) opts.push({ value: rec.booking_reference, label: `Ref: ${rec.booking_reference}` });
            if (rec.reference_number) opts.push({ value: rec.reference_number, label: `Ref: ${rec.reference_number}` });
            if (rec.customer_name) opts.push({ value: rec.customer_name, label: rec.customer_name });
            if (rec.name) opts.push({ value: rec.name, label: rec.name });
            if (rec.customer_phone) opts.push({ value: rec.customer_phone, label: rec.customer_phone });
            if (rec.phone) opts.push({ value: rec.phone, label: rec.phone });
            if (rec.customer_email) opts.push({ value: rec.customer_email, label: rec.customer_email });
            if (rec.email) opts.push({ value: rec.email, label: rec.email });
            if (rec.room_number) opts.push({ value: String(rec.room_number), label: `Room ${rec.room_number}` });
            if (rec.hall_name) opts.push({ value: rec.hall_name, label: rec.hall_name });
        });
        const unique = [];
        const seen = new Set();
        for (const opt of opts) {
            const key = opt.value.toLowerCase().trim();
            if (!seen.has(key)) {
                seen.add(key);
                unique.push(opt);
            }
        }
        return unique;
    }, [records]);

    const currentSearchSelectValue = useMemo(() => {
        if (!searchQuery) return null;
        return searchSelectOptions.find((opt) => opt.value.toLowerCase() === searchQuery.toLowerCase()) || {
            value: searchQuery,
            label: searchQuery,
        };
    }, [searchQuery, searchSelectOptions]);


    return (
        <div className="room-types-module" style={{ gap: '22px' }}>
            {/* ─── Page Header ─── */}
            <div className="dashboard-page-header">
                <div className="dashboard-page-header__info">
                    <h2 className="dashboard-page-header__title">Reports & Analytics</h2>
                    <p className="dashboard-page-header__desc">
                        Comprehensive backend-powered business reports for Room Bookings, Venues, Revenue, Leads, and Catering.
                    </p>
                </div>
                <div className="dashboard-header-actions">
                    <button
                        type="button"
                        className="admin-secondary-btn"
                        onClick={fetchReport}
                        disabled={isLoading}
                        style={{ display: 'inline-flex', alignItems: 'center', gap: '8px' }}
                    >
                        <svg
                            width="15"
                            height="15"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            strokeWidth="2"
                            strokeLinecap="round"
                            strokeLinejoin="round"
                            style={{ animation: isLoading ? 'spin 0.8s linear infinite' : 'none' }}
                        >
                            <polyline points="23 4 23 10 17 10" />
                            <polyline points="1 20 1 14 7 14" />
                            <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15" />
                        </svg>
                        Refresh Report
                    </button>
                </div>
            </div>

            {/* ─── Report Category Tabs ─── */}
            <div style={{ display: 'flex', gap: '8px', overflowX: 'auto', paddingBottom: '4px' }}>
                {REPORT_TABS.map((tab) => {
                    const isActive = activeReportTab === tab.id;
                    return (
                        <button
                            key={tab.id}
                            type="button"
                            onClick={() => handleTabChange(tab.id)}
                            className={`room-types-filter-pill ${isActive ? 'active' : ''}`}
                            style={{ padding: '8px 16px', fontSize: '0.84rem', fontWeight: isActive ? '700' : '600' }}
                        >
                            {tab.label}
                        </button>
                    );
                })}
            </div>

            {/* ─── Error Notice ─── */}
            {error && (
                <div
                    style={{
                        background: '#fef2f2',
                        border: '1px solid #fecaca',
                        borderRadius: '12px',
                        padding: '12px 18px',
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'space-between',
                        color: '#991b1b',
                        fontSize: '0.86rem',
                    }}
                >
                    <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                            <circle cx="12" cy="12" r="10" />
                            <line x1="12" y1="8" x2="12" y2="12" />
                            <line x1="12" y1="16" x2="12.01" y2="16" />
                        </svg>
                        <span><strong>Report Generation Error:</strong> {error}</span>
                    </div>
                    <button onClick={fetchReport} className="admin-secondary-btn" style={{ padding: '4px 12px', fontSize: '0.78rem' }}>
                        Retry
                    </button>
                </div>
            )}

            {/* ─── Summary Metric Cards Grid (Report-Specific) ─── */}
            <div className="room-types-stats-grid" style={{ gridTemplateColumns: 'repeat(4, 1fr)' }}>
                {activeReportTab === 'room' && (
                    <>
                        <div className="room-type-stat-card">
                            <div className="room-type-stat-card__icon room-type-stat-card__icon--gold">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><rect x="3" y="4" width="18" height="18" rx="2" /><line x1="16" y1="2" x2="16" y2="6" /><line x1="8" y1="2" x2="8" y2="6" /><line x1="3" y1="10" x2="21" y2="10" /></svg>
                            </div>
                            <div className="room-type-stat-card__info">
                                <span className="room-type-stat-card__label">Total Bookings</span>
                                <span className="room-type-stat-card__value">{summary.total_bookings || 0}</span>
                            </div>
                        </div>
                        <div className="room-type-stat-card">
                            <div className="room-type-stat-card__icon room-type-stat-card__icon--green">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><line x1="12" y1="1" x2="12" y2="23" /><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6" /></svg>
                            </div>
                            <div className="room-type-stat-card__info">
                                <span className="room-type-stat-card__label">Total Room Revenue</span>
                                <span className="room-type-stat-card__value">{formatCurrency(summary.total_revenue || summary.paid_revenue)}</span>
                            </div>
                        </div>
                        <div className="room-type-stat-card">
                            <div className="room-type-stat-card__icon room-type-stat-card__icon--blue">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" /><polyline points="22 4 12 14.01 9 11.01" /></svg>
                            </div>
                            <div className="room-type-stat-card__info">
                                <span className="room-type-stat-card__label">Confirmed / Paid</span>
                                <span className="room-type-stat-card__value">{summary.confirmed_bookings || summary.paid_bookings || 0}</span>
                            </div>
                        </div>
                        <div className="room-type-stat-card">
                            <div className="room-type-stat-card__icon room-type-stat-card__icon--slate">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" /><circle cx="9" cy="7" r="4" /></svg>
                            </div>
                            <div className="room-type-stat-card__info">
                                <span className="room-type-stat-card__label">Total Guests Recorded</span>
                                <span className="room-type-stat-card__value">{summary.total_guests || 0}</span>
                            </div>
                        </div>
                    </>
                )}

                {activeReportTab === 'revenue' && (
                    <>
                        <div className="room-type-stat-card">
                            <div className="room-type-stat-card__icon room-type-stat-card__icon--green">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><line x1="12" y1="1" x2="12" y2="23" /><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6" /></svg>
                            </div>
                            <div className="room-type-stat-card__info">
                                <span className="room-type-stat-card__label">Gross Total Revenue</span>
                                <span className="room-type-stat-card__value">{formatCurrency(summary.total_revenue)}</span>
                            </div>
                        </div>
                        <div className="room-type-stat-card">
                            <div className="room-type-stat-card__icon room-type-stat-card__icon--blue">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" /></svg>
                            </div>
                            <div className="room-type-stat-card__info">
                                <span className="room-type-stat-card__label">Room Revenue</span>
                                <span className="room-type-stat-card__value">{formatCurrency(summary.room_revenue)}</span>
                            </div>
                        </div>
                        <div className="room-type-stat-card">
                            <div className="room-type-stat-card__icon room-type-stat-card__icon--gold">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M3 21h18" /><path d="M5 21V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v16" /></svg>
                            </div>
                            <div className="room-type-stat-card__info">
                                <span className="room-type-stat-card__label">Hall Revenue</span>
                                <span className="room-type-stat-card__value">{formatCurrency(summary.hall_revenue)}</span>
                            </div>
                        </div>
                        <div className="room-type-stat-card">
                            <div className="room-type-stat-card__icon room-type-stat-card__icon--slate">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M18 8h1a4 4 0 0 1 0 8h-1" /><path d="M2 8h16v9a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4V8z" /></svg>
                            </div>
                            <div className="room-type-stat-card__info">
                                <span className="room-type-stat-card__label">Dining & Catering</span>
                                <span className="room-type-stat-card__value">{formatCurrency(Number(summary.dining_revenue || 0) + Number(summary.catering_revenue || 0))}</span>
                            </div>
                        </div>
                    </>
                )}

                {activeReportTab === 'hall' && (
                    <>
                        <div className="room-type-stat-card">
                            <div className="room-type-stat-card__icon room-type-stat-card__icon--gold">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M3 21h18" /><path d="M5 21V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v16" /></svg>
                            </div>
                            <div className="room-type-stat-card__info">
                                <span className="room-type-stat-card__label">Total Hall Leads</span>
                                <span className="room-type-stat-card__value">{summary.total_leads || summary.total_records || totalRecords}</span>
                            </div>
                        </div>
                        <div className="room-type-stat-card">
                            <div className="room-type-stat-card__icon room-type-stat-card__icon--green">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><line x1="12" y1="1" x2="12" y2="23" /><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6" /></svg>
                            </div>
                            <div className="room-type-stat-card__info">
                                <span className="room-type-stat-card__label">Converted Hall Revenue</span>
                                <span className="room-type-stat-card__value">{formatCurrency(summary.converted_revenue || summary.total_revenue)}</span>
                            </div>
                        </div>
                        <div className="room-type-stat-card">
                            <div className="room-type-stat-card__icon room-type-stat-card__icon--blue">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" /></svg>
                            </div>
                            <div className="room-type-stat-card__info">
                                <span className="room-type-stat-card__label">Converted Leads</span>
                                <span className="room-type-stat-card__value">{summary.converted_leads || 0}</span>
                            </div>
                        </div>
                        <div className="room-type-stat-card">
                            <div className="room-type-stat-card__icon room-type-stat-card__icon--slate">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" /></svg>
                            </div>
                            <div className="room-type-stat-card__info">
                                <span className="room-type-stat-card__label">Conversion Rate</span>
                                <span className="room-type-stat-card__value">{summary.conversion_rate ? `${summary.conversion_rate}%` : '0%'}</span>
                            </div>
                        </div>
                    </>
                )}

                {activeReportTab === 'lead' && (
                    <>
                        <div className="room-type-stat-card">
                            <div className="room-type-stat-card__icon room-type-stat-card__icon--orange">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3" /></svg>
                            </div>
                            <div className="room-type-stat-card__info">
                                <span className="room-type-stat-card__label">Total Leads</span>
                                <span className="room-type-stat-card__value">{summary.total_leads || totalRecords}</span>
                            </div>
                        </div>
                        <div className="room-type-stat-card">
                            <div className="room-type-stat-card__icon room-type-stat-card__icon--green">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" /></svg>
                            </div>
                            <div className="room-type-stat-card__info">
                                <span className="room-type-stat-card__label">Converted Leads</span>
                                <span className="room-type-stat-card__value">{summary.converted_leads || 0}</span>
                            </div>
                        </div>
                        <div className="room-type-stat-card">
                            <div className="room-type-stat-card__icon room-type-stat-card__icon--gold">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><line x1="12" y1="1" x2="12" y2="23" /><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6" /></svg>
                            </div>
                            <div className="room-type-stat-card__info">
                                <span className="room-type-stat-card__label">Converted Revenue</span>
                                <span className="room-type-stat-card__value">{formatCurrency(summary.converted_revenue)}</span>
                            </div>
                        </div>
                        <div className="room-type-stat-card">
                            <div className="room-type-stat-card__icon room-type-stat-card__icon--blue">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><circle cx="12" cy="12" r="10" /><line x1="12" y1="8" x2="12" y2="12" /></svg>
                            </div>
                            <div className="room-type-stat-card__info">
                                <span className="room-type-stat-card__label">Open Pipeline</span>
                                <span className="room-type-stat-card__value">{formatCurrency(summary.open_pipeline)}</span>
                            </div>
                        </div>
                    </>
                )}

                {activeReportTab === 'payment' && (
                    <>
                        <div className="room-type-stat-card">
                            <div className="room-type-stat-card__icon room-type-stat-card__icon--gold">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><rect x="1" y="4" width="22" height="16" rx="2" ry="2" /><line x1="1" y1="10" x2="23" y2="10" /></svg>
                            </div>
                            <div className="room-type-stat-card__info">
                                <span className="room-type-stat-card__label">Total Transactions</span>
                                <span className="room-type-stat-card__value">{summary.total_payments || totalRecords}</span>
                            </div>
                        </div>
                        <div className="room-type-stat-card">
                            <div className="room-type-stat-card__icon room-type-stat-card__icon--green">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><line x1="12" y1="1" x2="12" y2="23" /><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6" /></svg>
                            </div>
                            <div className="room-type-stat-card__info">
                                <span className="room-type-stat-card__label">Captured Amount</span>
                                <span className="room-type-stat-card__value">{formatCurrency(summary.captured_amount)}</span>
                            </div>
                        </div>
                        <div className="room-type-stat-card">
                            <div className="room-type-stat-card__icon room-type-stat-card__icon--blue">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" /></svg>
                            </div>
                            <div className="room-type-stat-card__info">
                                <span className="room-type-stat-card__label">Successful Payments</span>
                                <span className="room-type-stat-card__value">{summary.captured_payments || 0}</span>
                            </div>
                        </div>
                        <div className="room-type-stat-card">
                            <div className="room-type-stat-card__icon room-type-stat-card__icon--slate">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><circle cx="12" cy="12" r="10" /><line x1="15" y1="9" x2="9" y2="15" /><line x1="9" y1="9" x2="15" y2="15" /></svg>
                            </div>
                            <div className="room-type-stat-card__info">
                                <span className="room-type-stat-card__label">Failed / Refunded</span>
                                <span className="room-type-stat-card__value">{(summary.failed_payments || 0) + (summary.refunded_payments || 0)}</span>
                            </div>
                        </div>
                    </>
                )}

                {(activeReportTab === 'dining' || activeReportTab === 'catering') && (
                    <>
                        <div className="room-type-stat-card">
                            <div className="room-type-stat-card__icon room-type-stat-card__icon--gold">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M18 8h1a4 4 0 0 1 0 8h-1" /><path d="M2 8h16v9a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4V8z" /></svg>
                            </div>
                            <div className="room-type-stat-card__info">
                                <span className="room-type-stat-card__label">Total Inquiries</span>
                                <span className="room-type-stat-card__value">{summary.total_leads || totalRecords}</span>
                            </div>
                        </div>
                        <div className="room-type-stat-card">
                            <div className="room-type-stat-card__icon room-type-stat-card__icon--green">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><line x1="12" y1="1" x2="12" y2="23" /><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6" /></svg>
                            </div>
                            <div className="room-type-stat-card__info">
                                <span className="room-type-stat-card__label">Converted Revenue</span>
                                <span className="room-type-stat-card__value">{formatCurrency(summary.converted_revenue)}</span>
                            </div>
                        </div>
                        <div className="room-type-stat-card">
                            <div className="room-type-stat-card__icon room-type-stat-card__icon--blue">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" /></svg>
                            </div>
                            <div className="room-type-stat-card__info">
                                <span className="room-type-stat-card__label">Converted Orders</span>
                                <span className="room-type-stat-card__value">{summary.converted_leads || 0}</span>
                            </div>
                        </div>
                        <div className="room-type-stat-card">
                            <div className="room-type-stat-card__icon room-type-stat-card__icon--slate">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3" /></svg>
                            </div>
                            <div className="room-type-stat-card__info">
                                <span className="room-type-stat-card__label">Open Pipeline</span>
                                <span className="room-type-stat-card__value">{formatCurrency(summary.open_pipeline)}</span>
                            </div>
                        </div>
                    </>
                )}
            </div>

            {/* ─── Filter Toolbar ─── */}
            <div className="room-types-toolbar" style={{ flexWrap: 'wrap', gap: '10px', justifyContent: 'flex-start' }}>
                {/* Search Box with React Select */}
                <div className="room-types-search-select-wrap" style={{ minWidth: '240px', maxWidth: '340px', flex: '1 1 240px' }}>
                    <Select
                        options={searchSelectOptions}
                        value={currentSearchSelectValue}
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
                        placeholder="Search ref, customer, phone..."
                        isClearable
                        isSearchable
                        styles={reactSelectStyles}
                        className="react-select-container"
                        classNamePrefix="react-select"
                        noOptionsMessage={({ inputValue }) =>
                            inputValue ? `No matching record for "${inputValue}"` : 'Type to search records...'
                        }
                    />
                </div>

                <div style={{ display: 'flex', alignItems: 'center', gap: '8px', flexWrap: 'wrap' }}>
                    {/* Date Filters */}
                    <div style={{ display: 'flex', alignItems: 'center', gap: '6px' }}>
                        <input
                            type="date"
                            value={fromDate}
                            onChange={(e) => {
                                setFromDate(e.target.value);
                                setCurrentPage(1);
                            }}
                            className="admin-form-input"
                            style={{ height: '40px', padding: '6px 10px', fontSize: '0.82rem', borderRadius: '10px' }}
                            title="From Date"
                        />
                        <span style={{ color: 'var(--text-muted)', fontSize: '0.8rem' }}>to</span>
                        <input
                            type="date"
                            value={toDate}
                            onChange={(e) => {
                                setToDate(e.target.value);
                                setCurrentPage(1);
                            }}
                            className="admin-form-input"
                            style={{ height: '40px', padding: '6px 10px', fontSize: '0.82rem', borderRadius: '10px' }}
                            title="To Date"
                        />
                    </div>

                    {/* Status Dropdown */}
                    <div style={{ minWidth: '150px' }}>
                        <Select
                            styles={reactSelectStyles}
                            isSearchable={false}
                            value={{
                                value: statusFilter,
                                label: statusFilter === 'all' ? 'All Statuses' : formatStatusLabel(statusFilter),
                            }}
                            onChange={(opt) => {
                                setStatusFilter(opt.value);
                                setCurrentPage(1);
                            }}
                            options={[
                                { value: 'all', label: 'All Statuses' },
                                { value: 'confirmed', label: 'Confirmed' },
                                { value: 'pending', label: 'Pending' },
                                { value: 'checked_in', label: 'Checked In' },
                                { value: 'checked_out', label: 'Checked Out' },
                                { value: 'cancelled', label: 'Cancelled' },
                                { value: 'paid', label: 'Paid / Captured' },
                                { value: 'converted', label: 'Converted' },
                                { value: 'contacted', label: 'Contacted' },
                                { value: 'lost', label: 'Lost' },
                            ]}
                        />
                    </div>

                    {/* Domain filter if on revenue or lead report */}
                    {(activeReportTab === 'revenue' || activeReportTab === 'lead') && (
                        <div style={{ minWidth: '140px' }}>
                            <Select
                                styles={reactSelectStyles}
                                isSearchable={false}
                                value={{
                                    value: domainFilter,
                                    label: domainFilter === 'all' ? 'All Domains' : domainFilter.toUpperCase(),
                                }}
                                onChange={(opt) => {
                                    setDomainFilter(opt.value);
                                    setCurrentPage(1);
                                }}
                                options={[
                                    { value: 'all', label: 'All Domains' },
                                    { value: 'room', label: 'Room' },
                                    { value: 'hall', label: 'Hall' },
                                    { value: 'dining', label: 'Dining' },
                                    { value: 'catering', label: 'Catering' },
                                ]}
                            />
                        </div>
                    )}

                    {/* Reset Filters */}
                    {(searchQuery || fromDate || toDate || statusFilter !== 'all' || domainFilter !== 'all') && (
                        <button type="button" onClick={handleResetFilters} className="admin-secondary-btn" style={{ height: '40px', padding: '0 14px', fontSize: '0.8rem' }}>
                            Reset
                        </button>
                    )}
                </div>
            </div>

            {/* ─── Data Table Panel ─── */}
            <div className="admin-table-card">
                {isLoading ? (
                    <div className="dashboard-loading-state" style={{ padding: '48px', textAlign: 'center', color: 'var(--text-secondary)' }}>
                        <div className="admin-spinner" style={{ width: '32px', height: '32px', border: '3px solid rgba(212, 160, 23, 0.2)', borderTopColor: '#d4a017', borderRadius: '50%', animation: 'spin 0.8s linear infinite', margin: '0 auto 12px' }} />
                        <p style={{ fontSize: '0.9rem', fontWeight: 500 }}>Loading report data from server...</p>
                    </div>
                ) : records.length === 0 ? (
                    <div className="dashboard-empty-state">
                        <div className="dashboard-empty-state__icon-wrap">
                            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round">
                                <circle cx="12" cy="12" r="10" />
                                <line x1="12" y1="8" x2="12" y2="12" />
                                <line x1="12" y1="16" x2="12.01" y2="16" />
                            </svg>
                        </div>
                        <h4 className="dashboard-empty-state__title">No Records Found</h4>
                        <p className="dashboard-empty-state__desc">
                            No report rows matched your active filter parameters or date range.
                        </p>
                        <button type="button" className="admin-secondary-btn" onClick={handleResetFilters}>
                            Clear Filters
                        </button>
                    </div>
                ) : (
                    <>
                        <div className="admin-table-responsive">
                            <table className="admin-table">
                                <thead>
                                    {activeReportTab === 'room' && (
                                        <tr>
                                            <th style={{ width: '110px' }}>Booking Ref</th>
                                            <th>Customer Details</th>
                                            <th>Stay Dates</th>
                                            <th>Rooms & Guests</th>
                                            <th>Booking Status</th>
                                            <th>Payment</th>
                                            <th style={{ textAlign: 'right' }}>Total Amount</th>
                                            <th style={{ textAlign: 'right', width: '80px' }}>Action</th>
                                        </tr>
                                    )}

                                    {activeReportTab === 'revenue' && (
                                        <tr>
                                            <th style={{ width: '100px' }}>Ref / ID</th>
                                            <th>Domain</th>
                                            <th>Customer / Client</th>
                                            <th>Transaction Date</th>
                                            <th>Status</th>
                                            <th style={{ textAlign: 'right' }}>Amount</th>
                                            <th style={{ textAlign: 'right', width: '80px' }}>Action</th>
                                        </tr>
                                    )}

                                    {(activeReportTab === 'hall' || activeReportTab === 'lead' || activeReportTab === 'dining' || activeReportTab === 'catering') && (
                                        <tr>
                                            <th style={{ width: '80px' }}>Lead #</th>
                                            <th>Customer / Client</th>
                                            <th>Domain / Category</th>
                                            <th>Event Date</th>
                                            <th>Guests</th>
                                            <th>Status</th>
                                            <th style={{ textAlign: 'right' }}>Budget / Amount</th>
                                            <th style={{ textAlign: 'right', width: '80px' }}>Action</th>
                                        </tr>
                                    )}

                                    {activeReportTab === 'payment' && (
                                        <tr>
                                            <th style={{ width: '90px' }}>Payment #</th>
                                            <th>Razorpay Payment ID</th>
                                            <th>Customer ID</th>
                                            <th>Payment Date</th>
                                            <th>Status</th>
                                            <th style={{ textAlign: 'right' }}>Amount</th>
                                            <th style={{ textAlign: 'right', width: '80px' }}>Action</th>
                                        </tr>
                                    )}
                                </thead>
                                <tbody>
                                    {records.map((item, idx) => {
                                        return (
                                            <tr key={item.id || item.payment_id || idx} className="admin-table-row">
                                                {/* Room Bookings Table Row */}
                                                {activeReportTab === 'room' && (
                                                    <>
                                                        <td className="admin-table-cell--id">
                                                            <span style={{ color: 'var(--gold-700)', fontWeight: 700 }}>
                                                                {item.booking_reference ? `#${item.booking_reference}` : `#${item.id}`}
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <div style={{ display: 'flex', flexDirection: 'column' }}>
                                                                <span style={{ fontWeight: 700, color: 'var(--text-primary)' }}>
                                                                    {item.customer_name || 'Guest'}
                                                                </span>
                                                                {item.customer_phone && (
                                                                    <span style={{ fontSize: '0.76rem', color: 'var(--text-muted)' }}>
                                                                        {item.customer_phone}
                                                                    </span>
                                                                )}
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <span style={{ fontSize: '0.82rem', whiteSpace: 'nowrap' }}>
                                                                {formatDate(item.check_in_date)}
                                                                {item.check_out_date && item.check_out_date !== item.check_in_date && (
                                                                    <> → {formatDate(item.check_out_date)}</>
                                                                )}
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <span style={{ fontSize: '0.82rem' }}>
                                                                {item.room_count ? `${item.room_count} Room(s)` : 'Room Stay'} · {item.total_guests || 1} Guests
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <span className={`admin-status-badge ${getStatusBadgeClass(item.booking_status)}`}>
                                                                <span className="admin-status-dot" />
                                                                {formatStatusLabel(item.booking_status)}
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <span className={`admin-status-badge ${getStatusBadgeClass(item.payment_status)}`}>
                                                                <span className="admin-status-dot" />
                                                                {formatStatusLabel(item.payment_status)}
                                                            </span>
                                                        </td>
                                                        <td style={{ textAlign: 'right' }}>
                                                            <span style={{ fontWeight: 800, color: 'var(--text-primary)' }}>
                                                                {formatCurrency(item.total_amount)}
                                                            </span>
                                                        </td>
                                                    </>
                                                )}

                                                {/* Revenue Report Table Row */}
                                                {activeReportTab === 'revenue' && (
                                                    <>
                                                        <td className="admin-table-cell--id">
                                                            <span style={{ color: 'var(--gold-700)', fontWeight: 700 }}>
                                                                {item.reference_number ? `#${item.reference_number}` : `#${item.id}`}
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <span className="room-type-slug-badge" style={{ textTransform: 'uppercase' }}>
                                                                {item.domain || 'Room'}
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <span style={{ fontWeight: 700, color: 'var(--text-primary)' }}>
                                                                {item.customer_name || item.name || 'Client'}
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <span style={{ fontSize: '0.82rem' }}>
                                                                {formatDate(item.revenue_date || item.created_at)}
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <span className={`admin-status-badge ${getStatusBadgeClass(item.status || item.payment_status || 'captured')}`}>
                                                                <span className="admin-status-dot" />
                                                                {formatStatusLabel(item.status || item.payment_status || 'Paid')}
                                                            </span>
                                                        </td>
                                                        <td style={{ textAlign: 'right' }}>
                                                            <span style={{ fontWeight: 800, color: 'var(--text-primary)' }}>
                                                                {formatCurrency(item.amount || item.total_amount || item.converted_amount)}
                                                            </span>
                                                        </td>
                                                    </>
                                                )}

                                                {/* Leads / Halls / Dining / Catering Table Row */}
                                                {(activeReportTab === 'hall' || activeReportTab === 'lead' || activeReportTab === 'dining' || activeReportTab === 'catering') && (() => {
                                                    const norm = normalizeLead(item);
                                                    return (
                                                        <>
                                                            <td className="admin-table-cell--id">#{norm.id || item.id}</td>
                                                            <td>
                                                                <div style={{ display: 'flex', flexDirection: 'column' }}>
                                                                    <span style={{ fontWeight: 700, color: 'var(--text-primary)' }}>
                                                                        {norm.customerName || item.name || item.customer_name || 'Client'}
                                                                    </span>
                                                                    {(norm.customerPhone || item.phone) && (
                                                                        <span style={{ fontSize: '0.76rem', color: 'var(--text-muted)' }}>
                                                                            {norm.customerPhone || item.phone}
                                                                        </span>
                                                                    )}
                                                                </div>
                                                            </td>
                                                            <td>
                                                                <span className="room-type-assigned-pill" style={{ textTransform: 'capitalize' }}>
                                                                    {norm.domain || norm.packageName || item.domain || item.hall_name || item.package_name || activeReportTab}
                                                                </span>
                                                            </td>
                                                            <td>
                                                                <span style={{ fontSize: '0.82rem' }}>
                                                                    {formatDate(norm.eventDate || item.event_date || item.created_at)}
                                                                </span>
                                                            </td>
                                                            <td>
                                                                <span style={{ fontSize: '0.82rem' }}>
                                                                    {norm.guestCount !== null ? `👥 ${norm.guestCount}` : item.guest_count ? `👥 ${item.guest_count}` : '—'}
                                                                </span>
                                                            </td>
                                                            <td>
                                                                <span className={`admin-status-badge ${getStatusBadgeClass(norm.status || item.status || item.lead_status)}`}>
                                                                    <span className="admin-status-dot" />
                                                                    {formatStatusLabel(norm.status || item.status || item.lead_status)}
                                                                </span>
                                                            </td>
                                                            <td style={{ textAlign: 'right' }}>
                                                                <span style={{ fontWeight: 800, color: 'var(--text-primary)' }}>
                                                                    {formatCurrency(norm.convertedAmount ?? norm.estimatedAmount ?? item.budget ?? item.converted_amount ?? item.total_amount)}
                                                                </span>
                                                            </td>
                                                        </>
                                                    );
                                                })()}

                                                {/* Payment Transactions Table Row */}
                                                {activeReportTab === 'payment' && (
                                                    <>
                                                        <td className="admin-table-cell--id">#{item.id || item.payment_id}</td>
                                                        <td>
                                                            <span style={{ fontFamily: 'monospace', fontSize: '0.82rem', fontWeight: 600 }}>
                                                                {item.razorpay_payment_id || item.transaction_id || item.razorpay?.payment_id || '—'}
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <span style={{ fontSize: '0.82rem', color: 'var(--text-secondary)' }}>
                                                                {item.customer_name || item.customer?.name || (item.customer_id ? `Customer #${item.customer_id}` : (item.customer?.id ? `Customer #${item.customer.id}` : '—'))}
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <span style={{ fontSize: '0.82rem' }}>
                                                                {formatDate(item.created_at || item.payment?.created_at || item.payment_date)}
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <span className={`admin-status-badge ${getStatusBadgeClass(item.status || item.payment?.status || item.payment_status)}`}>
                                                                <span className="admin-status-dot" />
                                                                {formatStatusLabel(item.status || item.payment?.status || item.payment_status)}
                                                            </span>
                                                        </td>
                                                        <td style={{ textAlign: 'right' }}>
                                                            <span style={{ fontWeight: 800, color: 'var(--text-primary)' }}>
                                                                {formatCurrency(item.amount !== undefined ? item.amount : (item.payment?.amount !== undefined ? item.payment.amount : 0))}
                                                            </span>
                                                        </td>
                                                    </>
                                                )}

                                                {/* Action Column */}
                                                <td style={{ textAlign: 'right' }}>
                                                    <button
                                                        type="button"
                                                        className="admin-action-btn admin-action-btn--view"
                                                        onClick={() => setViewingRecord(item)}
                                                        title="View Record Details"
                                                    >
                                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                                            <path d="M1 12 s4 -8 11 -8 11 8 11 8 -4 8 -11 8 -11 -8 -11 -8 z" />
                                                            <circle cx="12" cy="12" r="3" />
                                                        </svg>
                                                    </button>
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>

                        {/* Pagination */}
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

            {/* ─── Detail Modal ─── */}
            {viewingRecord && (
                <div className="admin-modal-overlay" onClick={() => setViewingRecord(null)}>
                    <div className="admin-modal admin-modal--medium" onClick={(e) => e.stopPropagation()} role="dialog">
                        <div className="admin-modal__header">
                            <div className="admin-modal__title-wrap">
                                <h3 className="admin-modal__title">Report Item Details</h3>
                                <p className="admin-modal__subtitle">
                                    Record Reference: #{viewingRecord.booking_reference || viewingRecord.id}
                                </p>
                            </div>
                            <button type="button" className="admin-modal__close-btn" onClick={() => setViewingRecord(null)}>
                                ×
                            </button>
                        </div>

                        <div className="admin-modal__body" style={{ maxHeight: '70vh', overflowY: 'auto' }}>
                            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '14px' }}>
                                <div>
                                    <span style={{ fontSize: '0.74rem', color: 'var(--text-muted)', fontWeight: 700, textTransform: 'uppercase' }}>
                                        Customer / Recipient
                                    </span>
                                    <p style={{ margin: '4px 0 0', fontWeight: 700, fontSize: '0.94rem' }}>
                                        {viewingRecord.customer_name || viewingRecord.customer?.name || viewingRecord.name || (viewingRecord.customer_id ? `Customer #${viewingRecord.customer_id}` : 'Client')}
                                    </p>
                                </div>
                                <div>
                                    <span style={{ fontSize: '0.74rem', color: 'var(--text-muted)', fontWeight: 700, textTransform: 'uppercase' }}>
                                        Contact Phone / Email
                                    </span>
                                    <p style={{ margin: '4px 0 0', fontSize: '0.86rem', color: 'var(--text-secondary)' }}>
                                        {viewingRecord.customer_phone || viewingRecord.customer?.phone || viewingRecord.phone || viewingRecord.customer_email || viewingRecord.customer?.email || viewingRecord.email || '—'}
                                    </p>
                                </div>
                                <div>
                                    <span style={{ fontSize: '0.74rem', color: 'var(--text-muted)', fontWeight: 700, textTransform: 'uppercase' }}>
                                        Status
                                    </span>
                                    <div style={{ marginTop: '4px' }}>
                                        <span className={`admin-status-badge ${getStatusBadgeClass(viewingRecord.status || viewingRecord.payment?.status || viewingRecord.booking_status || viewingRecord.lead_status)}`}>
                                            <span className="admin-status-dot" />
                                            {formatStatusLabel(viewingRecord.status || viewingRecord.payment?.status || viewingRecord.booking_status || viewingRecord.lead_status)}
                                        </span>
                                    </div>
                                </div>
                                <div>
                                    <span style={{ fontSize: '0.74rem', color: 'var(--text-muted)', fontWeight: 700, textTransform: 'uppercase' }}>
                                        Total Amount / Budget
                                    </span>
                                    <p style={{ margin: '4px 0 0', fontWeight: 800, fontSize: '1.05rem', color: 'var(--gold-700)' }}>
                                        {formatCurrency(viewingRecord.total_amount || viewingRecord.amount || viewingRecord.payment?.amount || viewingRecord.budget || viewingRecord.converted_amount)}
                                    </p>
                                </div>
                                {(viewingRecord.check_in_date || viewingRecord.booking?.check_in_date) && (
                                    <div>
                                        <span style={{ fontSize: '0.74rem', color: 'var(--text-muted)', fontWeight: 700, textTransform: 'uppercase' }}>
                                            Stay Period
                                        </span>
                                        <p style={{ margin: '4px 0 0', fontSize: '0.86rem' }}>
                                            {formatDate(viewingRecord.check_in_date || viewingRecord.booking?.check_in_date)} to {formatDate(viewingRecord.check_out_date || viewingRecord.booking?.check_out_date)}
                                        </p>
                                    </div>
                                )}
                                {(viewingRecord.created_at || viewingRecord.payment?.created_at) && (
                                    <div>
                                        <span style={{ fontSize: '0.74rem', color: 'var(--text-muted)', fontWeight: 700, textTransform: 'uppercase' }}>
                                            Recorded Timestamp
                                        </span>
                                        <p style={{ margin: '4px 0 0', fontSize: '0.86rem' }}>
                                            {formatDate(viewingRecord.created_at || viewingRecord.payment?.created_at)}
                                        </p>
                                    </div>
                                )}
                            </div>
                        </div>

                        <div className="admin-modal__footer" style={{ justifyContent: 'flex-end' }}>
                            <button type="button" className="admin-secondary-btn" onClick={() => setViewingRecord(null)}>
                                Close
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
};

export default ReportsModule;
