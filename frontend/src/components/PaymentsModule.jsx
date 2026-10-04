import React, { useState, useEffect, useCallback, useMemo } from 'react';
import Select from 'react-select';
import Pagination from './Pagination';
import { paymentsApi } from '../services/paymentsApi';
import { normalizePayment } from '../services/Utils';

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

/**
 * Payment Status Options supported by backend (/reports/payment_report.php)
 */
const PAYMENT_STATUS_OPTIONS = [
    { value: 'all', label: 'All Statuses' },
    { value: 'captured', label: 'Captured' },
    { value: 'created', label: 'Pending' },
    { value: 'failed', label: 'Failed' },
    { value: 'refunded', label: 'Refunded' },
];

/**
 * Payment Method Options supported by backend
 */
const PAYMENT_METHOD_OPTIONS = [
    { value: 'all', label: 'All Payment Methods' },
    { value: 'upi', label: 'UPI / QR' },
    { value: 'card', label: 'Credit / Debit Card' },
    { value: 'netbanking', label: 'Net Banking' },
    { value: 'wallet', label: 'Digital Wallet' },
];

/**
 * Booking Status Options supported by backend
 */
const BOOKING_STATUS_OPTIONS = [
    { value: 'all', label: 'All Booking Statuses' },
    { value: 'confirmed', label: 'Confirmed' },
    { value: 'checked_in', label: 'Checked In' },
    { value: 'checked_out', label: 'Checked Out' },
    { value: 'pending', label: 'Pending' },
    { value: 'cancelled', label: 'Cancelled' },
    { value: 'no_show', label: 'No Show' },
];

/**
 * Admin & Staff Payments Management & Transaction Audit Module
 */
const PaymentsModule = ({ onNavigate }) => {
    // API Data & Loading State
    const [paymentsData, setPaymentsData] = useState(null);
    const [isLoading, setIsLoading] = useState(true);
    const [error, setError] = useState(null);

    // Filters State
    const [searchQuery, setSearchQuery] = useState('');
    const [selectedPaymentStatus, setSelectedPaymentStatus] = useState('all');
    const [selectedPaymentMethod, setSelectedPaymentMethod] = useState('all');
    const [selectedBookingStatus, setSelectedBookingStatus] = useState('all');
    const [fromDate, setFromDate] = useState('');
    const [toDate, setToDate] = useState('');
    const [minAmount, setMinAmount] = useState('');
    const [maxAmount, setMaxAmount] = useState('');

    // Pagination State
    const [currentPage, setCurrentPage] = useState(1);
    const [pageSize, setPageSize] = useState(20);

    // Modal State
    const [selectedTransaction, setSelectedTransaction] = useState(null);

    // Currency Formatter
    const formatCurrency = (val) => {
        if (val === null || val === undefined || isNaN(Number(val))) return '₹0.00';
        return `₹${Number(val).toLocaleString('en-IN', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        })}`;
    };

    // Date / Time Formatter
    const formatDateTime = (dateString) => {
        if (!dateString) return '—';
        try {
            const d = new Date(dateString);
            if (isNaN(d.getTime())) return dateString;
            return d.toLocaleString('en-IN', {
                day: '2-digit',
                month: 'short',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
                hour12: true,
            });
        } catch {
            return dateString;
        }
    };

    const formatDateOnly = (dateString) => {
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

    // Fetch Payments Data from Backend
    const fetchPayments = useCallback(async () => {
        setIsLoading(true);
        setError(null);
        try {
            const params = {
                page: currentPage,
                limit: pageSize,
                sort_by: 'created_at',
                sort_order: 'desc',
            };

            if (searchQuery && searchQuery.trim()) params.search = searchQuery.trim();
            if (selectedPaymentStatus && selectedPaymentStatus !== 'all') params.payment_status = selectedPaymentStatus;
            if (selectedPaymentMethod && selectedPaymentMethod !== 'all') params.payment_method = selectedPaymentMethod;
            if (selectedBookingStatus && selectedBookingStatus !== 'all') params.booking_status = selectedBookingStatus;
            if (fromDate) params.from_date = fromDate;
            if (toDate) params.to_date = toDate;
            if (minAmount && !isNaN(Number(minAmount))) params.min_amount = minAmount;
            if (maxAmount && !isNaN(Number(maxAmount))) params.max_amount = maxAmount;

            const res = await paymentsApi.getPayments(params);
            if (res && (res.success || res.status) && res.data) {
                const payload = res.data?.data || (Array.isArray(res.data?.records) ? res.data : (res.data || {}));
                setPaymentsData(payload);
            } else if (res && res.data) {
                const payload = res.data?.data || (Array.isArray(res.data?.records) ? res.data : (res.data || {}));
                setPaymentsData(payload);
            } else {
                setPaymentsData(null);
                if (res && res.error) {
                    setError(res.error);
                }
            }
        } catch (err) {
            console.error('Error fetching payments:', err);
            setError(err?.message || 'Failed to connect to payments server.');
            setPaymentsData(null);
        } finally {
            setIsLoading(false);
        }
    }, [
        currentPage,
        pageSize,
        searchQuery,
        selectedPaymentStatus,
        selectedPaymentMethod,
        selectedBookingStatus,
        fromDate,
        toDate,
        minAmount,
        maxAmount,
    ]);

    useEffect(() => {
        fetchPayments();
    }, [fetchPayments]);

    // Handle Reset Filters
    const handleResetFilters = () => {
        setSearchQuery('');
        setSelectedPaymentStatus('all');
        setSelectedPaymentMethod('all');
        setSelectedBookingStatus('all');
        setFromDate('');
        setToDate('');
        setMinAmount('');
        setMaxAmount('');
        setCurrentPage(1);
    };

    // Extract records, KPIs, pagination with normalization
    const payload = paymentsData?.data || paymentsData || {};
    const rawRecords = Array.isArray(payload?.records)
        ? payload.records
        : (Array.isArray(paymentsData?.records)
            ? paymentsData.records
            : (Array.isArray(payload) ? payload : []));
    const records = useMemo(() => rawRecords.map(normalizePayment), [rawRecords]);
    const kpis = payload?.summary || payload?.kpis || paymentsData?.summary || paymentsData?.kpis || {};
    const bookingSummary = payload?.booking_payment_summary || paymentsData?.booking_payment_summary || {};
    const reconciliation = payload?.reconciliation || paymentsData?.reconciliation || {};
    const pagination = payload?.pagination || paymentsData?.pagination || {};
    const totalRecords = pagination.total_records ?? records.length;

    // Build Search Select Options for React Select
    const searchSelectOptions = useMemo(() => {
        if (!records || records.length === 0) return [];
        const opts = [];
        records.forEach((rec) => {
            if (rec.bookingReference && rec.bookingReference !== '—') opts.push({ value: rec.bookingReference, label: `Ref: ${rec.bookingReference}` });
            if (rec.customerName && rec.customerName !== '—') opts.push({ value: rec.customerName, label: rec.customerName });
            if (rec.customerPhone) opts.push({ value: rec.customerPhone, label: rec.customerPhone });
            if (rec.customerEmail) opts.push({ value: rec.customerEmail, label: rec.customerEmail });
            if (rec.razorpayOrderId && rec.razorpayOrderId !== '—') opts.push({ value: rec.razorpayOrderId, label: `Order: ${rec.razorpayOrderId}` });
            if (rec.razorpayPaymentId && rec.razorpayPaymentId !== '—') opts.push({ value: rec.razorpayPaymentId, label: `Pay ID: ${rec.razorpayPaymentId}` });
            if (rec.method && rec.method !== '—') opts.push({ value: rec.method, label: `Method: ${rec.method}` });
        });
        const unique = [];
        const seen = new Set();
        for (const opt of opts) {
            const key = String(opt.value).toLowerCase().trim();
            if (!seen.has(key)) {
                seen.add(key);
                unique.push(opt);
            }
        }
        return unique;
    }, [records]);

    const currentSearchSelectValue = useMemo(() => {
        if (!searchQuery) return null;
        return searchSelectOptions.find((opt) => String(opt.value).toLowerCase() === searchQuery.toLowerCase()) || {
            value: searchQuery,
            label: searchQuery,
        };
    }, [searchQuery, searchSelectOptions]);

    // Payment Status Badge Helper
    const renderPaymentStatusBadge = (status) => {
        const s = String(status || '').toLowerCase();
        switch (s) {
            case 'captured':
                return (
                    <span className="admin-status-badge" style={{ backgroundColor: '#ecfdf5', color: '#065f46', border: '1px solid #a7f3d0' }}>
                        <span className="admin-status-dot" style={{ backgroundColor: '#059669' }} />
                        Captured (Paid)
                    </span>
                );
            case 'created':
                return (
                    <span className="admin-status-badge" style={{ backgroundColor: '#fffbeb', color: '#92400e', border: '1px solid #fde68a' }}>
                        <span className="admin-status-dot" style={{ backgroundColor: '#d97706' }} />
                        Created (Pending)
                    </span>
                );
            case 'authorized':
                return (
                    <span className="admin-status-badge" style={{ backgroundColor: '#eff6ff', color: '#1e40af', border: '1px solid #bfdbfe' }}>
                        <span className="admin-status-dot" style={{ backgroundColor: '#2563eb' }} />
                        Authorized
                    </span>
                );
            case 'failed':
                return (
                    <span className="admin-status-badge" style={{ backgroundColor: '#fef2f2', color: '#991b1b', border: '1px solid #fecaca' }}>
                        <span className="admin-status-dot" style={{ backgroundColor: '#dc2626' }} />
                        Failed
                    </span>
                );
            case 'refunded':
                return (
                    <span className="admin-status-badge" style={{ backgroundColor: '#f5f3ff', color: '#5b21b6', border: '1px solid #ddd6fe' }}>
                        <span className="admin-status-dot" style={{ backgroundColor: '#7c3aed' }} />
                        Refunded
                    </span>
                );
            default:
                return (
                    <span className="admin-status-badge" style={{ backgroundColor: '#f8fafc', color: '#475569', border: '1px solid #e2e8f0' }}>
                        <span className="admin-status-dot" style={{ backgroundColor: '#64748b' }} />
                        {status || 'Unknown'}
                    </span>
                );
        }
    };

    // Booking Status Badge Helper
    const renderBookingStatusBadge = (status) => {
        const s = String(status || '').toLowerCase();
        switch (s) {
            case 'confirmed':
                return <span className="admin-status-badge" style={{ backgroundColor: '#ecfdf5', color: '#047857' }}>Confirmed</span>;
            case 'checked_in':
                return <span className="admin-status-badge" style={{ backgroundColor: '#eff6ff', color: '#1d4ed8' }}>Checked In</span>;
            case 'checked_out':
                return <span className="admin-status-badge" style={{ backgroundColor: '#f3f4f6', color: '#374151' }}>Checked Out</span>;
            case 'pending':
                return <span className="admin-status-badge" style={{ backgroundColor: '#fffbeb', color: '#b45309' }}>Pending</span>;
            case 'cancelled':
                return <span className="admin-status-badge" style={{ backgroundColor: '#fef2f2', color: '#b91c1c' }}>Cancelled</span>;
            case 'no_show':
                return <span className="admin-status-badge" style={{ backgroundColor: '#f1f5f9', color: '#475569' }}>No Show</span>;
            default:
                return <span className="admin-status-badge" style={{ backgroundColor: '#f8fafc', color: '#64748b' }}>{status || '—'}</span>;
        }
    };

    return (
        <div className="room-types-module" style={{ gap: '22px' }}>
            {/* ─── Page Header ─── */}
            <div className="dashboard-page-header">
                <div className="dashboard-page-header__info">
                    <h2 className="dashboard-page-header__title">Payments & Transaction Audit</h2>
                    <p className="dashboard-page-header__desc">
                        Manage Razorpay payment transactions, booking settlements, financial audits, and reconciliation statuses.
                    </p>
                </div>
                <div className="dashboard-header-actions">
                    <button
                        type="button"
                        className="admin-secondary-btn"
                        onClick={fetchPayments}
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
                        Refresh Transactions
                    </button>
                </div>
            </div>

            {/* ─── Summary KPI Cards Grid ─── */}
            <div className="room-types-stats-grid" style={{ gridTemplateColumns: 'repeat(auto-fit, minmax(210px, 1fr))' }}>
                {/* Net Collected */}
                <div className="room-type-stat-card">
                    <div className="room-type-stat-card__icon room-type-stat-card__icon--gold">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                            <line x1="12" y1="1" x2="12" y2="23" />
                            <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6" />
                        </svg>
                    </div>
                    <div className="room-type-stat-card__info">
                        <span className="room-type-stat-card__label">Net Collected</span>
                        <span className="room-type-stat-card__value" style={{ color: '#047857' }}>
                            {formatCurrency(kpis.net_collected ?? kpis.captured_amount ?? 0)}
                        </span>
                        <span style={{ fontSize: '0.74rem', color: 'var(--text-muted)' }}>
                            {kpis.captured_transactions ?? 0} captured payments
                        </span>
                    </div>
                </div>

                {/* Total Transaction Volume */}
                <div className="room-type-stat-card">
                    <div className="room-type-stat-card__icon room-type-stat-card__icon--blue">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                            <rect x="2" y="5" width="20" height="14" rx="2" />
                            <line x1="2" y1="10" x2="22" y2="10" />
                        </svg>
                    </div>
                    <div className="room-type-stat-card__info">
                        <span className="room-type-stat-card__label">Total Transactions</span>
                        <span className="room-type-stat-card__value">
                            {kpis.total_transactions ?? 0}
                        </span>
                        <span style={{ fontSize: '0.74rem', color: 'var(--text-muted)' }}>
                            Valued at {formatCurrency(kpis.transaction_value ?? 0)}
                        </span>
                    </div>
                </div>

                {/* Success Rate */}
                <div className="room-type-stat-card">
                    <div className="room-type-stat-card__icon room-type-stat-card__icon--green">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
                            <polyline points="22 4 12 14.01 9 11.01" />
                        </svg>
                    </div>
                    <div className="room-type-stat-card__info">
                        <span className="room-type-stat-card__label">Success Rate</span>
                        <span className="room-type-stat-card__value" style={{ color: '#0284c7' }}>
                            {kpis.success_rate !== undefined ? `${kpis.success_rate}%` : '0%'}
                        </span>
                        <span style={{ fontSize: '0.74rem', color: 'var(--text-muted)' }}>
                            {kpis.captured_transactions ?? 0} of {kpis.total_transactions ?? 0}
                        </span>
                    </div>
                </div>

                {/* Failed & Pending */}
                <div className="room-type-stat-card">
                    <div className="room-type-stat-card__icon room-type-stat-card__icon--rose">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                            <circle cx="12" cy="12" r="10" />
                            <line x1="15" y1="9" x2="9" y2="15" />
                            <line x1="9" y1="9" x2="15" y2="15" />
                        </svg>
                    </div>
                    <div className="room-type-stat-card__info">
                        <span className="room-type-stat-card__label">Failed / Incomplete</span>
                        <span className="room-type-stat-card__value" style={{ color: '#b91c1c' }}>
                            {(kpis.failed_transactions ?? 0) + (kpis.created_transactions ?? 0)}
                        </span>
                        <span style={{ fontSize: '0.74rem', color: 'var(--text-muted)' }}>
                            {formatCurrency((kpis.failed_amount ?? 0) + (kpis.created_amount ?? 0))}
                        </span>
                    </div>
                </div>

                {/* Reconciliation Audit */}
                <div className="room-type-stat-card">
                    <div className="room-type-stat-card__icon room-type-stat-card__icon--slate">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                            <polyline points="14 2 14 8 20 8" />
                            <line x1="16" y1="13" x2="8" y2="13" />
                            <line x1="16" y1="17" x2="8" y2="17" />
                        </svg>
                    </div>
                    <div className="room-type-stat-card__info">
                        <span className="room-type-stat-card__label">Reconciliation</span>
                        <span className="room-type-stat-card__value" style={{ color: reconciliation.mismatch_transactions > 0 ? '#d97706' : '#059669' }}>
                            {reconciliation.match_rate !== undefined ? `${reconciliation.match_rate}%` : '100%'}
                        </span>
                        <span style={{ fontSize: '0.74rem', color: 'var(--text-muted)' }}>
                            {reconciliation.matched_transactions ?? 0} matched / {reconciliation.mismatch_transactions ?? 0} discrepancies
                        </span>
                    </div>
                </div>
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
                    <span><strong>Error:</strong> {error}</span>
                    <button onClick={fetchPayments} className="admin-secondary-btn" style={{ padding: '4px 12px', fontSize: '0.78rem' }}>
                        Retry
                    </button>
                </div>
            )}

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
                        placeholder="Search ref, customer, phone, Razorpay ID..."
                        isClearable
                        isSearchable
                        styles={reactSelectStyles}
                        className="react-select-container"
                        classNamePrefix="react-select"
                        noOptionsMessage={({ inputValue }) =>
                            inputValue ? `No matching transaction for "${inputValue}"` : 'Type to search payments...'
                        }
                    />
                </div>

                <div style={{ display: 'flex', alignItems: 'center', gap: '8px', flexWrap: 'wrap' }}>
                    {/* Payment Status Dropdown */}
                    <div style={{ minWidth: '150px' }}>
                        <Select
                            styles={reactSelectStyles}
                            isSearchable={false}
                            value={
                                PAYMENT_STATUS_OPTIONS.find((opt) => opt.value === selectedPaymentStatus) ||
                                PAYMENT_STATUS_OPTIONS[0]
                            }
                            onChange={(opt) => {
                                setSelectedPaymentStatus(opt ? opt.value : 'all');
                                setCurrentPage(1);
                            }}
                            options={PAYMENT_STATUS_OPTIONS}
                            placeholder="All Statuses"
                        />
                    </div>

                    {/* Payment Method Selector */}
                    <div style={{ minWidth: '160px' }}>
                        <Select
                            styles={reactSelectStyles}
                            isSearchable={false}
                            value={PAYMENT_METHOD_OPTIONS.find((opt) => opt.value === selectedPaymentMethod) || PAYMENT_METHOD_OPTIONS[0]}
                            onChange={(opt) => {
                                setSelectedPaymentMethod(opt ? opt.value : 'all');
                                setCurrentPage(1);
                            }}
                            options={PAYMENT_METHOD_OPTIONS}
                            placeholder="Payment Method"
                        />
                    </div>

                    {/* Booking Status Selector */}
                    <div style={{ minWidth: '160px' }}>
                        <Select
                            styles={reactSelectStyles}
                            isSearchable={false}
                            value={BOOKING_STATUS_OPTIONS.find((opt) => opt.value === selectedBookingStatus) || BOOKING_STATUS_OPTIONS[0]}
                            onChange={(opt) => {
                                setSelectedBookingStatus(opt ? opt.value : 'all');
                                setCurrentPage(1);
                            }}
                            options={BOOKING_STATUS_OPTIONS}
                            placeholder="Booking Status"
                        />
                    </div>

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

                    {/* Reset Button */}
                    {(searchQuery || fromDate || toDate || selectedPaymentStatus !== 'all' || selectedPaymentMethod !== 'all' || selectedBookingStatus !== 'all') && (
                        <button
                            type="button"
                            onClick={handleResetFilters}
                            className="admin-secondary-btn"
                            style={{ height: '40px', padding: '0 14px', fontSize: '0.8rem' }}
                        >
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
                        <p style={{ fontSize: '0.9rem', fontWeight: 500 }}>Loading payment transactions from server...</p>
                    </div>
                ) : records.length === 0 ? (
                    <div className="dashboard-empty-state">
                        <div className="dashboard-empty-state__icon-wrap">
                            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round">
                                <rect x="1" y="4" width="22" height="16" rx="2" ry="2" />
                                <line x1="1" y1="10" x2="23" y2="10" />
                            </svg>
                        </div>
                        <h4 className="dashboard-empty-state__title">No Payments Found</h4>
                        <p className="dashboard-empty-state__desc">
                            No payment transactions matched your active filter parameters or date range.
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
                                    <tr>
                                        <th style={{ width: '100px' }}>#ID / Gateway</th>
                                        <th style={{ width: '150px' }}>Booking Ref</th>
                                        <th>Customer Details</th>
                                        <th style={{ width: '140px' }}>Amount</th>
                                        <th style={{ width: '150px' }}>Payment Status</th>
                                        <th style={{ width: '130px' }}>Method</th>
                                        <th style={{ width: '140px' }}>Date & Time</th>
                                        <th style={{ width: '130px' }}>Reconciliation</th>
                                        <th style={{ width: '80px', textAlign: 'center' }}>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {records.map((item) => {
                                        const p = item.payment || {};
                                        const b = item.booking || {};
                                        const c = item.customer || {};
                                        const rz = item.razorpay || {};
                                        const recon = item.reconciliation || {};

                                        return (
                                            <tr key={item.payment_id} className="admin-table-row">
                                                {/* ID & Gateway */}
                                                <td>
                                                    <div style={{ display: 'flex', flexDirection: 'column', gap: '3px' }}>
                                                        <span style={{ fontWeight: 700, fontFamily: 'monospace', fontSize: '0.86rem', color: 'var(--text-primary)' }}>
                                                            #{item.payment_id}
                                                        </span>
                                                        <span
                                                            style={{
                                                                fontSize: '0.72rem',
                                                                fontWeight: 700,
                                                                textTransform: 'uppercase',
                                                                color: '#0284c7',
                                                                background: '#e0f2fe',
                                                                padding: '2px 6px',
                                                                borderRadius: '4px',
                                                                display: 'inline-block',
                                                                width: 'fit-content',
                                                            }}
                                                        >
                                                            {p.gateway || 'Razorpay'}
                                                        </span>
                                                    </div>
                                                </td>

                                                {/* Booking Ref & Stay */}
                                                <td>
                                                    <div style={{ display: 'flex', flexDirection: 'column', gap: '3px' }}>
                                                        <span style={{ fontWeight: 700, color: 'var(--text-primary)', fontSize: '0.86rem' }}>
                                                            {b.reference || `Booking #${b.id}`}
                                                        </span>
                                                        <span style={{ fontSize: '0.76rem', color: 'var(--text-secondary)' }}>
                                                            {b.check_in_date ? `${formatDateOnly(b.check_in_date)}` : '—'}
                                                        </span>
                                                        <div style={{ marginTop: '2px' }}>
                                                            {renderBookingStatusBadge(b.booking_status)}
                                                        </div>
                                                    </div>
                                                </td>

                                                {/* Customer Details */}
                                                <td>
                                                    <div style={{ display: 'flex', flexDirection: 'column', gap: '2px' }}>
                                                        <span style={{ fontWeight: 600, color: 'var(--text-primary)', fontSize: '0.88rem' }}>
                                                            {c.name || '—'}
                                                        </span>
                                                        {c.phone && (
                                                            <span style={{ fontSize: '0.78rem', color: 'var(--text-secondary)' }}>
                                                                📞 {c.phone}
                                                            </span>
                                                        )}
                                                        {c.email && (
                                                            <span style={{ fontSize: '0.74rem', color: 'var(--text-muted)' }}>
                                                                ✉️ {c.email}
                                                            </span>
                                                        )}
                                                    </div>
                                                </td>

                                                {/* Amount */}
                                                <td>
                                                    <div style={{ display: 'flex', flexDirection: 'column', gap: '2px' }}>
                                                        <span style={{ fontWeight: 700, fontSize: '0.96rem', color: p.status === 'captured' ? '#047857' : p.status === 'failed' ? '#b91c1c' : 'var(--text-primary)' }}>
                                                            {formatCurrency(p.amount)}
                                                        </span>
                                                        <span style={{ fontSize: '0.72rem', color: 'var(--text-muted)', textTransform: 'uppercase' }}>
                                                            {p.currency || 'INR'}
                                                        </span>
                                                    </div>
                                                </td>

                                                {/* Payment Status */}
                                                <td>
                                                    {renderPaymentStatusBadge(p.status)}
                                                </td>

                                                {/* Method */}
                                                <td>
                                                    <span style={{ fontSize: '0.82rem', fontWeight: 600, color: 'var(--text-secondary)', textTransform: 'capitalize' }}>
                                                        {p.method ? p.method.replace('_', ' ') : '—'}
                                                    </span>
                                                </td>

                                                {/* Date & Time */}
                                                <td>
                                                    <div style={{ display: 'flex', flexDirection: 'column', gap: '2px' }}>
                                                        <span style={{ fontSize: '0.82rem', fontWeight: 600, color: 'var(--text-primary)' }}>
                                                            {formatDateOnly(p.paid_at || p.created_at)}
                                                        </span>
                                                        <span style={{ fontSize: '0.74rem', color: 'var(--text-muted)' }}>
                                                            {p.created_at ? new Date(p.created_at).toLocaleTimeString('en-IN', { hour: '2-digit', minute: '2-digit', hour12: true }) : ''}
                                                        </span>
                                                    </div>
                                                </td>

                                                {/* Reconciliation */}
                                                <td>
                                                    {recon.status_match ? (
                                                        <span
                                                            style={{
                                                                display: 'inline-flex',
                                                                alignItems: 'center',
                                                                gap: '4px',
                                                                fontSize: '0.74rem',
                                                                fontWeight: 600,
                                                                color: '#047857',
                                                                background: '#ecfdf5',
                                                                padding: '3px 8px',
                                                                borderRadius: '9999px',
                                                                border: '1px solid #a7f3d0',
                                                            }}
                                                        >
                                                            ✓ Match
                                                        </span>
                                                    ) : (
                                                        <span
                                                            style={{
                                                                display: 'inline-flex',
                                                                alignItems: 'center',
                                                                gap: '4px',
                                                                fontSize: '0.74rem',
                                                                fontWeight: 600,
                                                                color: '#b45309',
                                                                background: '#fffbeb',
                                                                padding: '3px 8px',
                                                                borderRadius: '9999px',
                                                                border: '1px solid #fde68a',
                                                            }}
                                                        >
                                                            ⚠ Discrepancy
                                                        </span>
                                                    )}
                                                </td>

                                                {/* Actions */}
                                                <td style={{ textAlign: 'center' }}>
                                                    <button
                                                        type="button"
                                                        onClick={() => setSelectedTransaction(item)}
                                                        className="admin-secondary-btn"
                                                        title="View Transaction Audit Details"
                                                        style={{
                                                            padding: '6px 12px',
                                                            fontSize: '0.78rem',
                                                            display: 'inline-flex',
                                                            alignItems: 'center',
                                                            gap: '4px',
                                                            borderRadius: '8px',
                                                        }}
                                                    >
                                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                                                            <circle cx="12" cy="12" r="3" />
                                                        </svg>
                                                        <span>View</span>
                                                    </button>
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>

                        {/* Pagination Bar */}
                        <Pagination
                            currentPage={currentPage}
                            totalPages={pagination.total_pages ?? Math.ceil(totalRecords / pageSize)}
                            totalRecords={totalRecords}
                            pageSize={pageSize}
                            onPageChange={(p) => setCurrentPage(p)}
                            onPageSizeChange={(sz) => {
                                setPageSize(sz);
                                setCurrentPage(1);
                            }}
                            pageSizeOptions={[5, 10, 20, 50]}
                        />
                    </>
                )}
            </div>

            {/* ─── Transaction Audit Details Modal ─── */}
            {selectedTransaction && (
                <div className="admin-modal-overlay" onClick={() => setSelectedTransaction(null)}>
                    <div
                        className="admin-modal-container"
                        onClick={(e) => e.stopPropagation()}
                        style={{ maxWidth: '640px', width: '92%' }}
                    >
                        <div className="admin-modal-header" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', borderBottom: '1px solid rgba(40,30,20,0.1)', padding: '18px 24px' }}>
                            <div>
                                <h3 style={{ margin: 0, fontSize: '1.15rem', fontWeight: 700, color: 'var(--text-primary)' }}>
                                    Payment Audit Details
                                </h3>
                                <span style={{ fontSize: '0.78rem', color: 'var(--text-secondary)' }}>
                                    Transaction ID: #{selectedTransaction.payment_id} • Gateway: {selectedTransaction.payment?.gateway || 'Razorpay'}
                                </span>
                            </div>
                            <button
                                type="button"
                                onClick={() => setSelectedTransaction(null)}
                                className="room-types-search-clear"
                                style={{ fontSize: '1.4rem', border: 'none', background: 'transparent', cursor: 'pointer' }}
                            >
                                ×
                            </button>
                        </div>

                        <div className="admin-modal-body" style={{ padding: '24px', display: 'flex', flexDirection: 'column', gap: '20px', maxHeight: '75vh', overflowY: 'auto' }}>
                            {/* Key Financial Card */}
                            <div style={{ background: '#faf7f2', border: '1px solid rgba(201, 148, 20, 0.2)', borderRadius: '12px', padding: '16px 20px', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                                <div>
                                    <span style={{ fontSize: '0.78rem', color: 'var(--text-muted)', textTransform: 'uppercase', fontWeight: 700 }}>
                                        Transaction Amount
                                    </span>
                                    <h2 style={{ margin: '4px 0 0', fontSize: '1.5rem', fontWeight: 800, color: selectedTransaction.payment?.status === 'captured' ? '#047857' : 'var(--text-primary)' }}>
                                        {formatCurrency(selectedTransaction.payment?.amount)}
                                    </h2>
                                </div>
                                <div style={{ textAlign: 'right' }}>
                                    <span style={{ fontSize: '0.78rem', color: 'var(--text-muted)', display: 'block', marginBottom: '4px' }}>
                                        Status
                                    </span>
                                    {renderPaymentStatusBadge(selectedTransaction.payment?.status)}
                                </div>
                            </div>

                            {/* Razorpay Gateway Audit */}
                            <div style={{ display: 'flex', flexDirection: 'column', gap: '8px' }}>
                                <h4 style={{ margin: '0 0 6px', fontSize: '0.88rem', fontWeight: 700, color: 'var(--text-primary)', textTransform: 'uppercase', letterSpacing: '0.5px' }}>
                                    Razorpay Gateway Identifiers
                                </h4>
                                <div style={{ background: '#ffffff', border: '1px solid rgba(40,30,20,0.1)', borderRadius: '10px', padding: '14px', display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '12px', fontSize: '0.84rem' }}>
                                    <div>
                                        <span style={{ color: 'var(--text-muted)', display: 'block', fontSize: '0.74rem' }}>Razorpay Order ID</span>
                                        <strong style={{ fontFamily: 'monospace', color: 'var(--text-primary)' }}>
                                            {selectedTransaction.razorpay?.order_id || '—'}
                                        </strong>
                                    </div>
                                    <div>
                                        <span style={{ color: 'var(--text-muted)', display: 'block', fontSize: '0.74rem' }}>Razorpay Payment ID</span>
                                        <strong style={{ fontFamily: 'monospace', color: 'var(--text-primary)' }}>
                                            {selectedTransaction.razorpay?.payment_id || '—'}
                                        </strong>
                                    </div>
                                    <div>
                                        <span style={{ color: 'var(--text-muted)', display: 'block', fontSize: '0.74rem' }}>Payment Method</span>
                                        <strong style={{ color: 'var(--text-primary)', textTransform: 'capitalize' }}>
                                            {selectedTransaction.payment?.method ? selectedTransaction.payment.method.replace('_', ' ') : '—'}
                                        </strong>
                                    </div>
                                    <div>
                                        <span style={{ color: 'var(--text-muted)', display: 'block', fontSize: '0.74rem' }}>Signature Verification</span>
                                        <strong style={{ color: selectedTransaction.razorpay?.signature_available ? '#047857' : '#b45309' }}>
                                            {selectedTransaction.razorpay?.signature_available ? '✓ Verified on Server' : '— Not provided'}
                                        </strong>
                                    </div>
                                </div>
                            </div>

                            {/* Booking & Customer Association */}
                            <div style={{ display: 'flex', flexDirection: 'column', gap: '8px' }}>
                                <h4 style={{ margin: '0 0 6px', fontSize: '0.88rem', fontWeight: 700, color: 'var(--text-primary)', textTransform: 'uppercase', letterSpacing: '0.5px' }}>
                                    Associated Booking & Guest
                                </h4>
                                <div style={{ background: '#ffffff', border: '1px solid rgba(40,30,20,0.1)', borderRadius: '10px', padding: '14px', display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '12px', fontSize: '0.84rem' }}>
                                    <div>
                                        <span style={{ color: 'var(--text-muted)', display: 'block', fontSize: '0.74rem' }}>Booking Reference</span>
                                        <strong style={{ color: 'var(--text-primary)' }}>
                                            {selectedTransaction.booking?.reference || `#${selectedTransaction.booking?.id}`}
                                        </strong>
                                    </div>
                                    <div>
                                        <span style={{ color: 'var(--text-muted)', display: 'block', fontSize: '0.74rem' }}>Customer Name</span>
                                        <strong style={{ color: 'var(--text-primary)' }}>
                                            {selectedTransaction.customer?.name || '—'}
                                        </strong>
                                    </div>
                                    <div>
                                        <span style={{ color: 'var(--text-muted)', display: 'block', fontSize: '0.74rem' }}>Contact Info</span>
                                        <span style={{ color: 'var(--text-secondary)' }}>
                                            {selectedTransaction.customer?.phone || ''} {selectedTransaction.customer?.email ? `• ${selectedTransaction.customer.email}` : ''}
                                        </span>
                                    </div>
                                    <div>
                                        <span style={{ color: 'var(--text-muted)', display: 'block', fontSize: '0.74rem' }}>Booking Payment Status</span>
                                        <span style={{ textTransform: 'capitalize', fontWeight: 600 }}>
                                            {selectedTransaction.booking?.payment_status || '—'}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            {/* Financial Breakdown */}
                            {selectedTransaction.booking_amount && (
                                <div style={{ display: 'flex', flexDirection: 'column', gap: '8px' }}>
                                    <h4 style={{ margin: '0 0 6px', fontSize: '0.88rem', fontWeight: 700, color: 'var(--text-primary)', textTransform: 'uppercase', letterSpacing: '0.5px' }}>
                                        Booking Ledger Breakdown
                                    </h4>
                                    <div style={{ background: '#ffffff', border: '1px solid rgba(40,30,20,0.1)', borderRadius: '10px', padding: '14px', fontSize: '0.84rem', display: 'flex', flexDirection: 'column', gap: '8px' }}>
                                        <div style={{ display: 'flex', justifyContent: 'space-between' }}>
                                            <span style={{ color: 'var(--text-secondary)' }}>Room Subtotal:</span>
                                            <span>{formatCurrency(selectedTransaction.booking_amount.room_subtotal)}</span>
                                        </div>
                                        <div style={{ display: 'flex', justifyContent: 'space-between' }}>
                                            <span style={{ color: 'var(--text-secondary)' }}>GST ({selectedTransaction.booking_amount.gst_rate}%):</span>
                                            <span>{formatCurrency(selectedTransaction.booking_amount.gst_amount)}</span>
                                        </div>
                                        <div style={{ display: 'flex', justifyContent: 'space-between', borderTop: '1px dashed rgba(40,30,20,0.1)', paddingTop: '6px', fontWeight: 700 }}>
                                            <span>Total Booking Price:</span>
                                            <span style={{ color: 'var(--gold-800)' }}>{formatCurrency(selectedTransaction.booking_amount.total_amount)}</span>
                                        </div>
                                    </div>
                                </div>
                            )}

                            {/* Timestamps & Reconciliation Info */}
                            <div style={{ display: 'flex', flexDirection: 'column', gap: '8px' }}>
                                <h4 style={{ margin: '0 0 6px', fontSize: '0.88rem', fontWeight: 700, color: 'var(--text-primary)', textTransform: 'uppercase', letterSpacing: '0.5px' }}>
                                    Audit Timestamps & Reconciliation
                                </h4>
                                <div style={{ background: '#ffffff', border: '1px solid rgba(40,30,20,0.1)', borderRadius: '10px', padding: '14px', display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '12px', fontSize: '0.84rem' }}>
                                    <div>
                                        <span style={{ color: 'var(--text-muted)', display: 'block', fontSize: '0.74rem' }}>Created At</span>
                                        <span>{formatDateTime(selectedTransaction.payment?.created_at)}</span>
                                    </div>
                                    <div>
                                        <span style={{ color: 'var(--text-muted)', display: 'block', fontSize: '0.74rem' }}>Paid At</span>
                                        <span>{formatDateTime(selectedTransaction.payment?.paid_at) || '—'}</span>
                                    </div>
                                    <div>
                                        <span style={{ color: 'var(--text-muted)', display: 'block', fontSize: '0.74rem' }}>Reconciliation Check</span>
                                        <span style={{ fontWeight: 600, color: selectedTransaction.reconciliation?.status_match ? '#047857' : '#b45309' }}>
                                            {selectedTransaction.reconciliation?.status_match
                                                ? '✓ Reconciled with Booking'
                                                : '⚠ Status Mismatch with Booking'}
                                        </span>
                                    </div>
                                    <div>
                                        <span style={{ color: 'var(--text-muted)', display: 'block', fontSize: '0.74rem' }}>Last Updated</span>
                                        <span>{formatDateTime(selectedTransaction.payment?.updated_at) || '—'}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div className="admin-modal-footer" style={{ borderTop: '1px solid rgba(40,30,20,0.1)', padding: '14px 24px', display: 'flex', justifyContent: 'flex-end' }}>
                            <button
                                type="button"
                                className="admin-secondary-btn"
                                onClick={() => setSelectedTransaction(null)}
                            >
                                Close
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
};

export default PaymentsModule;
