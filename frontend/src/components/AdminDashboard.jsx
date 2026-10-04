import React, { useState, useEffect, useCallback } from 'react';
import { dashboardApi } from '../services/dashboardApi';
import { normalizeLead, getAuthSession } from '../services/Utils';

/**
 * Clean Empty State Component
 * Follows the Room Type / Room Admin design pattern.
 */
const EmptyState = ({ title, description, icon }) => {
    return (
        <div className="dashboard-empty-state" style={{ padding: '40px 20px' }}>
            <div className="dashboard-empty-state__icon-wrap">
                {icon || (
                    <svg width="38" height="38" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round">
                        <circle cx="12" cy="12" r="10" />
                        <line x1="12" y1="8" x2="12" y2="12" />
                        <line x1="12" y1="16" x2="12.01" y2="16" />
                    </svg>
                )}
            </div>
            <h4 className="dashboard-empty-state__title">{title}</h4>
            <p className="dashboard-empty-state__desc">{description}</p>
        </div>
    );
};

const AdminDashboard = () => {
    const [overviewData, setOverviewData] = useState(null);
    const [isLoading, setIsLoading] = useState(true);
    const [error, setError] = useState(null);
    const [activeTab, setActiveTab] = useState('bookings'); // 'bookings' | 'leads' | 'payments' | 'notifications'
    const [lastSyncTime, setLastSyncTime] = useState(null);

    // Fetch dashboard overview data
    const fetchDashboardData = useCallback(async () => {
        setIsLoading(true);
        setError(null);
        try {
            const response = await dashboardApi.getOverview();
            // The backend returns { status: true, message: '...', data: { kpis, master_data, recent, trends, meta, user_scope } }
            // The Axios interceptor wraps this as { success, data: <PHP_RESPONSE>, status }
            // Safely unpack the inner payload
            if (response && response.success && response.data) {
                const payload = (response.data.data && typeof response.data.data === 'object')
                    ? response.data.data
                    : response.data;
                setOverviewData(payload);
                setLastSyncTime(new Date());
            } else if (response && response.data && typeof response.data === 'object') {
                const payload = (response.data.data && typeof response.data.data === 'object')
                    ? response.data.data
                    : response.data;
                setOverviewData(payload);
                setLastSyncTime(new Date());
            } else {
                setOverviewData(null);
                if (response && response.error) {
                    setError(response.error);
                }
            }
        } catch (err) {
            console.error('Error fetching dashboard overview:', err);
            setError(err?.message || 'Unable to connect to dashboard API.');
        } finally {
            setIsLoading(false);
        }
    }, []);

    useEffect(() => {
        fetchDashboardData();
    }, [fetchDashboardData]);

    // Currency Formatter
    const currencySymbol = overviewData?.meta?.currency_symbol || '₹';
    const formatCurrency = (val) => {
        if (val === null || val === undefined || isNaN(Number(val))) {
            return `${currencySymbol}0.00`;
        }
        return `${currencySymbol}${Number(val).toLocaleString('en-IN', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
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
                year: 'numeric'
            });
        } catch {
            return dateString;
        }
    };

    // DateTime Formatter
    const formatDateTime = (dateString) => {
        if (!dateString) return '—';
        try {
            const d = new Date(dateString);
            if (isNaN(d.getTime())) return dateString;
            return d.toLocaleDateString('en-IN', {
                day: '2-digit',
                month: 'short',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
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
            .map(word => word.charAt(0).toUpperCase() + word.slice(1).toLowerCase())
            .join(' ');
    };

    // Helper for Status Badge Class
    const getStatusBadgeClass = (status) => {
        const s = (status || '').toLowerCase();
        if (['confirmed', 'active', 'completed', 'paid', 'captured', 'sent'].includes(s)) {
            return 'admin-status-badge--confirmed';
        }
        if (['checked_in', 'contacted'].includes(s)) {
            return 'admin-status-badge--checked_in';
        }
        if (['checked_out', 'converted', 'follow_up'].includes(s)) {
            return 'admin-status-badge--checked_out';
        }
        if (['cancelled', 'lost', 'failed', 'inactive', 'revoked', 'blocked'].includes(s)) {
            return 'admin-status-badge--cancelled';
        }
        return 'admin-status-badge--pending';
    };

    // Extracting backend data safely
    const kpis = overviewData?.kpis;
    const masterData = overviewData?.master_data;
    const recent = overviewData?.recent;
    const recentBookings = recent?.bookings || [];
    const recentLeads = recent?.leads || [];
    const recentPayments = recent?.payments || [];
    const recentNotifications = recent?.notifications || [];
    const monthlyTrends = overviewData?.trends?.monthly || [];

    // Role detection (Admin vs Staff)
    const adminSession = getAuthSession('admin');
    const userRole = overviewData?.user_scope?.role || adminSession?.user?.role || 'admin';
    const isStaff = userRole === 'staff';

    // Calculate room availability percentages
    const totalRooms = masterData?.rooms?.total || 0;
    const availableRooms = masterData?.rooms?.available || 0;
    const maintenanceRooms = masterData?.rooms?.maintenance || 0;
    const inactiveRooms = masterData?.rooms?.inactive || 0;
    const roomAvailPct = totalRooms > 0 ? Math.round((availableRooms / totalRooms) * 100) : 0;
    const roomMaintPct = totalRooms > 0 ? Math.round((maintenanceRooms / totalRooms) * 100) : 0;
    const roomInactPct = totalRooms > 0 ? Math.round((inactiveRooms / totalRooms) * 100) : 0;

    // Calculate hall availability percentages
    const totalHalls = masterData?.halls?.total || 0;
    const availableHalls = masterData?.halls?.available || 0;
    const maintenanceHalls = masterData?.halls?.maintenance || 0;
    const inactiveHalls = masterData?.halls?.inactive || 0;
    const hallAvailPct = totalHalls > 0 ? Math.round((availableHalls / totalHalls) * 100) : 0;
    const hallMaintPct = totalHalls > 0 ? Math.round((maintenanceHalls / totalHalls) * 100) : 0;
    const hallInactPct = totalHalls > 0 ? Math.round((inactiveHalls / totalHalls) * 100) : 0;

    // Trend activity check
    const hasTrendActivity = monthlyTrends.length > 0 && monthlyTrends.some(
        m => (Number(m.bookings || m.room_bookings || 0) > 0 || Number(m.total_revenue || 0) > 0)
    );

    return (
        <div className="admin-dashboard-container">
            {/* ─── Dashboard Header ─── */}
            <div className="dashboard-page-header">
                <div className="dashboard-page-header__info">
                    <h2 className="dashboard-page-header__title">
                        {isStaff ? 'Staff Dashboard' : 'Admin Dashboard'}
                    </h2>
                    <p className="dashboard-page-header__desc">
                        {isStaff
                            ? 'Real-time operations console for your assigned leads, reservations, and venue activity.'
                            : 'Real-time management console for SM Groups wedding halls, guest suites, catering, and reservations.'}
                    </p>
                </div>
                <div className="dashboard-header-actions">
                    <div className="venue-status-chip">
                        <span className="venue-status-chip__dot" />
                        <span className="venue-status-chip__text">
                            {isLoading ? 'Syncing...' : 'System Live'}
                        </span>
                    </div>
                    {lastSyncTime && (
                        <span style={{ fontSize: '0.76rem', color: 'var(--text-muted)' }}>
                            Updated {lastSyncTime.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
                        </span>
                    )}
                    <button
                        type="button"
                        className="dashboard-refresh-btn"
                        onClick={fetchDashboardData}
                        disabled={isLoading}
                        title="Refresh dashboard data"
                    >
                        <svg
                            width="16"
                            height="16"
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
                    </button>
                </div>
            </div>

            {/* ─── Error State Banner ─── */}
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
                        fontSize: '0.86rem'
                    }}
                >
                    <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                            <circle cx="12" cy="12" r="10" />
                            <line x1="12" y1="8" x2="12" y2="12" />
                            <line x1="12" y1="16" x2="12.01" y2="16" />
                        </svg>
                        <span><strong>Dashboard Sync Error:</strong> {error}</span>
                    </div>
                    <button
                        onClick={fetchDashboardData}
                        className="admin-secondary-btn"
                        style={{ padding: '4px 12px', fontSize: '0.78rem' }}
                    >
                        Retry
                    </button>
                </div>
            )}

            {/* ─── 6-Card KPI Metrics Grid ─── */}
            <section className="dashboard-kpi-grid" aria-label="Key Performance Indicators">
                {/* 1. Total Bookings */}
                <div className="dashboard-kpi-card">
                    <div className="dashboard-kpi-card__top">
                        <div className="dashboard-kpi-card__icon dashboard-kpi-card__icon--gold">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                <rect x="3" y="4" width="18" height="18" rx="2" ry="2" />
                                <line x1="16" y1="2" x2="16" y2="6" />
                                <line x1="8" y1="2" x2="8" y2="6" />
                                <line x1="3" y1="10" x2="21" y2="10" />
                            </svg>
                        </div>
                        <span className="dashboard-kpi-card__badge">Bookings</span>
                    </div>
                    <div className="dashboard-kpi-card__body">
                        <span className="dashboard-kpi-card__label">Total Bookings</span>
                        <span className="dashboard-kpi-card__value">
                            {isLoading ? '...' : (kpis?.room_booking?.total_bookings !== undefined ? kpis.room_booking.total_bookings.toLocaleString() : '0')}
                        </span>
                    </div>
                    <div className="dashboard-kpi-card__footer">
                        {kpis?.room_booking?.total_guests ? `${kpis.room_booking.total_guests} total guests` : 'Room & venue reservations'}
                    </div>
                </div>

                {/* 2. Total Revenue */}
                <div className="dashboard-kpi-card">
                    <div className="dashboard-kpi-card__top">
                        <div className="dashboard-kpi-card__icon dashboard-kpi-card__icon--green">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                <line x1="12" y1="1" x2="12" y2="23" />
                                <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6" />
                            </svg>
                        </div>
                        <span className="dashboard-kpi-card__badge">Finance</span>
                    </div>
                    <div className="dashboard-kpi-card__body">
                        <span className="dashboard-kpi-card__label">Total Revenue</span>
                        <span className="dashboard-kpi-card__value" style={{ fontSize: '1.25rem' }}>
                            {isLoading ? '...' : formatCurrency(kpis?.revenue?.total_revenue)}
                        </span>
                    </div>
                    <div className="dashboard-kpi-card__footer">
                        Captured: {formatCurrency(kpis?.payments?.captured_amount || 0)}
                    </div>
                </div>

                {/* 3. Lodge Rooms */}
                <div className="dashboard-kpi-card">
                    <div className="dashboard-kpi-card__top">
                        <div className="dashboard-kpi-card__icon dashboard-kpi-card__icon--blue">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" />
                                <polyline points="9 22 9 12 15 12 15 22" />
                            </svg>
                        </div>
                        <span className="dashboard-kpi-card__badge">Lodging</span>
                    </div>
                    <div className="dashboard-kpi-card__body">
                        <span className="dashboard-kpi-card__label">Lodge Rooms</span>
                        <span className="dashboard-kpi-card__value">
                            {isLoading ? '...' : totalRooms}
                        </span>
                    </div>
                    <div className="dashboard-kpi-card__footer">
                        {availableRooms} Available · {maintenanceRooms} Maint.
                    </div>
                </div>

                {/* 4. Event Halls */}
                <div className="dashboard-kpi-card">
                    <div className="dashboard-kpi-card__top">
                        <div className="dashboard-kpi-card__icon dashboard-kpi-card__icon--purple">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                <path d="M3 21h18" />
                                <path d="M5 21V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v16" />
                                <path d="M9 9h1" />
                                <path d="M14 9h1" />
                                <path d="M9 13h1" />
                                <path d="M14 13h1" />
                            </svg>
                        </div>
                        <span className="dashboard-kpi-card__badge">Venues</span>
                    </div>
                    <div className="dashboard-kpi-card__body">
                        <span className="dashboard-kpi-card__label">Event Halls</span>
                        <span className="dashboard-kpi-card__value">
                            {isLoading ? '...' : totalHalls}
                        </span>
                    </div>
                    <div className="dashboard-kpi-card__footer">
                        {availableHalls} Available · {masterData?.halls?.total_capacity || 0} Capacity
                    </div>
                </div>

                {/* 5. Global Leads */}
                <div className="dashboard-kpi-card">
                    <div className="dashboard-kpi-card__top">
                        <div className="dashboard-kpi-card__icon dashboard-kpi-card__icon--orange">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3" />
                            </svg>
                        </div>
                        <span className="dashboard-kpi-card__badge">Leads</span>
                    </div>
                    <div className="dashboard-kpi-card__body">
                        <span className="dashboard-kpi-card__label">
                            {isStaff ? 'Assigned Leads' : 'Global Leads'}
                        </span>
                        <span className="dashboard-kpi-card__value">
                            {isLoading ? '...' : (kpis?.leads?.total !== undefined ? kpis.leads.total : '0')}
                        </span>
                    </div>
                    <div className="dashboard-kpi-card__footer">
                        {kpis?.leads?.converted || 0} Converted ({kpis?.leads?.conversion_rate || 0}%)
                    </div>
                </div>

                {/* 6. Customers */}
                <div className="dashboard-kpi-card">
                    <div className="dashboard-kpi-card__top">
                        <div className="dashboard-kpi-card__icon dashboard-kpi-card__icon--slate">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                                <circle cx="9" cy="7" r="4" />
                                <path d="M23 21v-2a4 4 0 0 0-3-3.87" />
                                <path d="M16 3.13a4 4 0 0 1 0 7.75" />
                            </svg>
                        </div>
                        <span className="dashboard-kpi-card__badge">Clients</span>
                    </div>
                    <div className="dashboard-kpi-card__body">
                        <span className="dashboard-kpi-card__label">Clients</span>
                        <span className="dashboard-kpi-card__value">
                            {isLoading ? '...' : (masterData?.customers?.total !== undefined ? masterData.customers.total : '0')}
                        </span>
                    </div>
                    <div className="dashboard-kpi-card__footer">
                        {masterData?.customers?.active || 0} Active registered accounts
                    </div>
                </div>
            </section>

            {/* ─── 2-Column Analytics & Operational Section ─── */}
            <div className="dashboard-analytics-grid">
                {/* Panel 1: Performance & Monthly Trends */}
                <div className="dashboard-panel-card">
                    <div className="dashboard-panel-card__header">
                        <div className="dashboard-panel-card__title-group">
                            <h3 className="dashboard-panel-card__title">Monthly Performance Trends</h3>
                            <p className="dashboard-panel-card__subtitle">Revenue trajectory and reservation velocity</p>
                        </div>
                        <span className="dashboard-panel-card__badge">Financial Velocity</span>
                    </div>

                    <div className="dashboard-panel-card__body">
                        {/* Mini Metrics Row */}
                        <div className="dashboard-mini-metrics-row">
                            <div className="dashboard-mini-metric-item">
                                <span className="dashboard-mini-metric-item__label">Room Revenue</span>
                                <span className="dashboard-mini-metric-item__value">
                                    {isLoading ? '...' : formatCurrency(kpis?.revenue?.room_revenue)}
                                </span>
                            </div>
                            <div className="dashboard-mini-metric-item">
                                <span className="dashboard-mini-metric-item__label">Open Pipeline</span>
                                <span className="dashboard-mini-metric-item__value">
                                    {isLoading ? '...' : formatCurrency(kpis?.leads?.open_pipeline)}
                                </span>
                            </div>
                            <div className="dashboard-mini-metric-item">
                                <span className="dashboard-mini-metric-item__label">Avg Booking Value</span>
                                <span className="dashboard-mini-metric-item__value">
                                    {isLoading ? '...' : formatCurrency(kpis?.room_booking?.average_booking_value)}
                                </span>
                            </div>
                        </div>

                        {/* Bar Chart Visualization / Clean Empty State */}
                        <div className="dashboard-bar-chart-container">
                            {hasTrendActivity ? (
                                <div className="dashboard-bar-chart-bars">
                                    {monthlyTrends.map((trend, idx) => {
                                        const maxRev = Math.max(...monthlyTrends.map(m => Number(m.total_revenue || 0)), 1000);
                                        const heightPct = Math.max(Math.min((Number(trend.total_revenue || 0) / maxRev) * 100, 100), 10);
                                        const bookingsCount = trend.bookings ?? trend.room_bookings ?? 0;
                                        return (
                                            <div key={idx} className="dashboard-bar-col">
                                                <span className="dashboard-bar-col__num">
                                                    {bookingsCount}
                                                </span>
                                                <div
                                                    className="dashboard-bar-col__fill"
                                                    style={{ height: `${heightPct}%` }}
                                                    title={`${trend.label || trend.month}: ${formatCurrency(trend.total_revenue)} (${bookingsCount} bookings)`}
                                                />
                                                <span className="dashboard-bar-col__label">
                                                    {trend.label ? trend.label.split(' ')[0] : (trend.month || '').slice(-2)}
                                                </span>
                                            </div>
                                        );
                                    })}
                                </div>
                            ) : (
                                <EmptyState
                                    title="No Trend Data Yet"
                                    description="Monthly revenue and booking metrics will automatically populate when customer stays and transactions are recorded."
                                    icon={
                                        <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round">
                                            <line x1="18" y1="20" x2="18" y2="10" />
                                            <line x1="12" y1="20" x2="12" y2="4" />
                                            <line x1="6" y1="20" x2="6" y2="14" />
                                            <line x1="2" y1="20" x2="22" y2="20" />
                                        </svg>
                                    }
                                />
                            )}
                        </div>
                    </div>
                </div>

                {/* Panel 2: Operational Capacity & Resource Distribution */}
                <div className="dashboard-panel-card">
                    <div className="dashboard-panel-card__header">
                        <div className="dashboard-panel-card__title-group">
                            <h3 className="dashboard-panel-card__title">Operational Capacity & Pipelines</h3>
                            <p className="dashboard-panel-card__subtitle">Venue readiness and lead conversion status</p>
                        </div>
                        <span className="dashboard-panel-card__badge">Live Inventory</span>
                    </div>

                    <div className="dashboard-panel-card__body">
                        {/* Progress Meter 1: Rooms Status */}
                        <div className="dashboard-progress-list">
                            <div className="dashboard-progress-item">
                                <div className="dashboard-progress-item__header">
                                    <span className="dashboard-progress-item__title">Lodge Rooms Availability</span>
                                    <span className="dashboard-progress-item__count">
                                        {availableRooms} of {totalRooms} Ready ({roomAvailPct}%)
                                    </span>
                                </div>
                                <div className="dashboard-progress-track">
                                    <div className="dashboard-progress-segment dashboard-progress-segment--available" style={{ width: `${roomAvailPct}%` }} title={`Available: ${availableRooms}`} />
                                    <div className="dashboard-progress-segment dashboard-progress-segment--maintenance" style={{ width: `${roomMaintPct}%` }} title={`Maintenance: ${maintenanceRooms}`} />
                                    <div className="dashboard-progress-segment dashboard-progress-segment--inactive" style={{ width: `${roomInactPct}%` }} title={`Inactive: ${inactiveRooms}`} />
                                </div>
                            </div>

                            {/* Progress Meter 2: Halls Status */}
                            <div className="dashboard-progress-item">
                                <div className="dashboard-progress-item__header">
                                    <span className="dashboard-progress-item__title">Event Halls Readiness</span>
                                    <span className="dashboard-progress-item__count">
                                        {availableHalls} of {totalHalls} Ready ({hallAvailPct}%)
                                    </span>
                                </div>
                                <div className="dashboard-progress-track">
                                    <div className="dashboard-progress-segment dashboard-progress-segment--available" style={{ width: `${hallAvailPct}%` }} title={`Available: ${availableHalls}`} />
                                    <div className="dashboard-progress-segment dashboard-progress-segment--maintenance" style={{ width: `${hallMaintPct}%` }} title={`Maintenance: ${maintenanceHalls}`} />
                                    <div className="dashboard-progress-segment dashboard-progress-segment--inactive" style={{ width: `${hallInactPct}%` }} title={`Inactive: ${inactiveHalls}`} />
                                </div>
                            </div>
                        </div>

                        {/* Quick Catalog Stats Grid */}
                        <div className="dashboard-catalog-grid">
                            <div className="dashboard-catalog-item">
                                <span className="dashboard-catalog-item__label">Dining Pkgs</span>
                                <span className="dashboard-catalog-item__val">{masterData?.dining_packages?.total || 0}</span>
                            </div>
                            <div className="dashboard-catalog-item">
                                <span className="dashboard-catalog-item__label">Catering Pkgs</span>
                                <span className="dashboard-catalog-item__val">{masterData?.catering_packages?.total || 0}</span>
                            </div>
                            <div className="dashboard-catalog-item">
                                <span className="dashboard-catalog-item__label">Menu Items</span>
                                <span className="dashboard-catalog-item__val">{masterData?.food_items?.total || 0}</span>
                            </div>
                            <div className="dashboard-catalog-item">
                                <span className="dashboard-catalog-item__label">Staff / Admins</span>
                                <span className="dashboard-catalog-item__val">{masterData?.admins?.total || 0}</span>
                            </div>
                        </div>

                        {/* Lead Pipeline Breakdown Pills */}
                        <div className="dashboard-pipeline-pills">
                            <div className="dashboard-pipeline-pill">
                                <span className="admin-status-dot" style={{ background: '#3b82f6' }} />
                                <span>New Leads:</span>
                                <span className="dashboard-pipeline-pill__count">{kpis?.leads?.new || 0}</span>
                            </div>
                            <div className="dashboard-pipeline-pill">
                                <span className="admin-status-dot" style={{ background: '#f59e0b' }} />
                                <span>Contacted:</span>
                                <span className="dashboard-pipeline-pill__count">{kpis?.leads?.contacted || 0}</span>
                            </div>
                            <div className="dashboard-pipeline-pill">
                                <span className="admin-status-dot" style={{ background: '#10b981' }} />
                                <span>Converted:</span>
                                <span className="dashboard-pipeline-pill__count">{kpis?.leads?.converted || 0}</span>
                            </div>
                            <div className="dashboard-pipeline-pill">
                                <span className="admin-status-dot" style={{ background: '#ef4444' }} />
                                <span>Lost / Closed:</span>
                                <span className="dashboard-pipeline-pill__count">{kpis?.leads?.lost || 0}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {/* ─── Live Activity Queue & Table Section ─── */}
            <div className="admin-table-card">
                <div className="dashboard-table-header-tabs">
                    <h3 className="dashboard-table-title">Live System Activity & Queue</h3>
                    <div className="dashboard-tabs-group">
                        <button
                            type="button"
                            className={`dashboard-tab-pill ${activeTab === 'bookings' ? 'active' : ''}`}
                            onClick={() => setActiveTab('bookings')}
                        >
                            Recent Bookings
                            <span className="dashboard-tab-count">{recentBookings.length}</span>
                        </button>
                        <button
                            type="button"
                            className={`dashboard-tab-pill ${activeTab === 'leads' ? 'active' : ''}`}
                            onClick={() => setActiveTab('leads')}
                        >
                            Global Leads
                            <span className="dashboard-tab-count">{recentLeads.length}</span>
                        </button>
                        <button
                            type="button"
                            className={`dashboard-tab-pill ${activeTab === 'payments' ? 'active' : ''}`}
                            onClick={() => setActiveTab('payments')}
                        >
                            Payments
                            <span className="dashboard-tab-count">{recentPayments.length}</span>
                        </button>
                        <button
                            type="button"
                            className={`dashboard-tab-pill ${activeTab === 'notifications' ? 'active' : ''}`}
                            onClick={() => setActiveTab('notifications')}
                        >
                            Notifications
                            <span className="dashboard-tab-count">{recentNotifications.length}</span>
                        </button>
                    </div>
                </div>

                {isLoading ? (
                    <div className="dashboard-loading-state" style={{ padding: '48px', textAlign: 'center', color: 'var(--text-secondary)' }}>
                        <div className="admin-spinner" style={{ width: '32px', height: '32px', border: '3px solid rgba(212, 160, 23, 0.2)', borderTopColor: '#d4a017', borderRadius: '50%', animation: 'spin 0.8s linear infinite', margin: '0 auto 12px' }} />
                        <p style={{ fontSize: '0.9rem', fontWeight: 500 }}>Loading dashboard records from server...</p>
                    </div>
                ) : (
                    <div className="admin-table-responsive">
                        {/* Tab 1: Bookings */}
                        {activeTab === 'bookings' && (
                            recentBookings.length > 0 ? (
                                <table className="admin-table">
                                    <thead>
                                        <tr>
                                            <th style={{ width: '110px' }}>Booking Ref</th>
                                            <th>Customer Details</th>
                                            <th>Stay Dates</th>
                                            <th>Rooms & Guests</th>
                                            <th>Status</th>
                                            <th style={{ textAlign: 'right' }}>Total Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {recentBookings.map((b) => (
                                            <tr key={b.id} className="admin-table-row">
                                                <td className="admin-table-cell--id">
                                                    <span style={{ color: 'var(--gold-700)', fontWeight: 700 }}>
                                                        {b.booking_reference ? `#${b.booking_reference}` : `#${b.id}`}
                                                    </span>
                                                </td>
                                                <td>
                                                    <div style={{ display: 'flex', flexDirection: 'column' }}>
                                                        <span style={{ fontWeight: 700, color: 'var(--text-primary)' }}>
                                                            {b.customer_name || 'Guest'}
                                                        </span>
                                                        {b.customer_phone && (
                                                            <span style={{ fontSize: '0.76rem', color: 'var(--text-muted)' }}>
                                                                {b.customer_phone}
                                                            </span>
                                                        )}
                                                    </div>
                                                </td>
                                                <td>
                                                    <span style={{ fontSize: '0.82rem', whiteSpace: 'nowrap' }}>
                                                        {formatDate(b.check_in_date)}
                                                        {b.check_out_date && b.check_out_date !== b.check_in_date && (
                                                            <> → {formatDate(b.check_out_date)}</>
                                                        )}
                                                    </span>
                                                </td>
                                                <td>
                                                    <div style={{ display: 'flex', flexDirection: 'column', gap: '2px' }}>
                                                        <span style={{ fontSize: '0.82rem', fontWeight: 600 }}>
                                                            {b.room_count ? `${b.room_count} Room${b.room_count > 1 ? 's' : ''}` : 'Room Stay'}
                                                        </span>
                                                        <span style={{ fontSize: '0.74rem', color: 'var(--text-muted)' }}>
                                                            👤 {b.total_guests || 1} Guest{b.total_guests > 1 ? 's' : ''}
                                                        </span>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span className={`admin-status-badge ${getStatusBadgeClass(b.booking_status)}`}>
                                                        <span className="admin-status-dot" />
                                                        {formatStatusLabel(b.booking_status)}
                                                    </span>
                                                </td>
                                                <td style={{ textAlign: 'right' }}>
                                                    <span style={{ fontWeight: 800, color: 'var(--text-primary)' }}>
                                                        {formatCurrency(b.total_amount)}
                                                    </span>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            ) : (
                                <EmptyState
                                    title="No Recent Bookings"
                                    description="No guest reservations or hall bookings have been recorded yet in the database."
                                    icon={
                                        <svg width="38" height="38" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round">
                                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2" />
                                            <line x1="16" y1="2" x2="16" y2="6" />
                                            <line x1="8" y1="2" x2="8" y2="6" />
                                            <line x1="3" y1="10" x2="21" y2="10" />
                                        </svg>
                                    }
                                />
                            )
                        )}

                        {/* Tab 2: Leads */}
                        {activeTab === 'leads' && (
                            recentLeads.length > 0 ? (
                                <table className="admin-table">
                                    <thead>
                                        <tr>
                                            <th style={{ width: '80px' }}>Lead #</th>
                                            <th>Customer Name</th>
                                            <th>Domain / Event</th>
                                            <th>Guest Count</th>
                                            <th>Status</th>
                                            <th style={{ textAlign: 'right' }}>Est. Budget</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {recentLeads.map((lead) => {
                                            const norm = normalizeLead(lead);
                                            return (
                                                <tr key={norm.id || lead.id} className="admin-table-row">
                                                    <td className="admin-table-cell--id">#{norm.id || lead.id}</td>
                                                    <td>
                                                        <div style={{ display: 'flex', flexDirection: 'column' }}>
                                                            <span style={{ fontWeight: 700, color: 'var(--text-primary)' }}>
                                                                {norm.customerName || 'Inquiry'}
                                                            </span>
                                                            {norm.customerPhone && (
                                                                <span style={{ fontSize: '0.76rem', color: 'var(--text-muted)' }}>
                                                                    {norm.customerPhone}
                                                                </span>
                                                            )}
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <span className="room-type-assigned-pill" style={{ textTransform: 'capitalize' }}>
                                                            {norm.domain || lead.domain || 'General Inquiry'}
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span style={{ fontSize: '0.82rem' }}>
                                                            {norm.guestCount ? `👥 ${norm.guestCount} Guests` : '—'}
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span className={`admin-status-badge ${getStatusBadgeClass(norm.status)}`}>
                                                            <span className="admin-status-dot" />
                                                            {formatStatusLabel(norm.status)}
                                                        </span>
                                                    </td>
                                                    <td style={{ textAlign: 'right' }}>
                                                        <span style={{ fontWeight: 800, color: 'var(--text-primary)' }}>
                                                            {norm.estimatedAmount ? formatCurrency(norm.estimatedAmount) : '—'}
                                                        </span>
                                                    </td>
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                </table>
                            ) : (
                                <EmptyState
                                    title="No Recent Leads"
                                    description="No global inquiries or lead requests found in the recent activity queue."
                                    icon={
                                        <svg width="38" height="38" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round">
                                            <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3" />
                                        </svg>
                                    }
                                />
                            )
                        )}

                        {/* Tab 3: Payments */}
                        {activeTab === 'payments' && (
                            recentPayments.length > 0 ? (
                                <table className="admin-table">
                                    <thead>
                                        <tr>
                                            <th style={{ width: '90px' }}>Payment #</th>
                                            <th>Transaction Ref</th>
                                            <th>Customer Details</th>
                                            <th>Status</th>
                                            <th>Date</th>
                                            <th style={{ textAlign: 'right' }}>Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {recentPayments.map((p) => {
                                            const paymentStatus = p.payment_status || p.status;
                                            return (
                                                <tr key={p.id} className="admin-table-row">
                                                    <td className="admin-table-cell--id">#{p.id}</td>
                                                    <td>
                                                        <span style={{ fontFamily: 'monospace', fontSize: '0.82rem', fontWeight: 600 }}>
                                                            {p.razorpay_payment_id || p.transaction_id || '—'}
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <div style={{ display: 'flex', flexDirection: 'column' }}>
                                                            <span style={{ fontWeight: 600, color: 'var(--text-primary)' }}>
                                                                {p.customer_name || `Customer #${p.customer_id || p.booking_id}`}
                                                            </span>
                                                            {p.booking_reference && (
                                                                <span style={{ fontSize: '0.74rem', color: 'var(--text-muted)' }}>
                                                                    Ref: #{p.booking_reference}
                                                                </span>
                                                            )}
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <span className={`admin-status-badge ${getStatusBadgeClass(paymentStatus)}`}>
                                                            <span className="admin-status-dot" />
                                                            {formatStatusLabel(paymentStatus)}
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span style={{ fontSize: '0.8rem' }}>
                                                            {formatDate(p.paid_at || p.created_at)}
                                                        </span>
                                                    </td>
                                                    <td style={{ textAlign: 'right' }}>
                                                        <span style={{ fontWeight: 800, color: 'var(--text-primary)' }}>
                                                            {formatCurrency(p.amount)}
                                                        </span>
                                                    </td>
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                </table>
                            ) : (
                                <EmptyState
                                    title="No Recent Payments"
                                    description="No captured or processed payment transactions currently in the queue."
                                    icon={
                                        <svg width="38" height="38" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round">
                                            <line x1="12" y1="1" x2="12" y2="23" />
                                            <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6" />
                                        </svg>
                                    }
                                />
                            )
                        )}

                        {/* Tab 4: Notifications */}
                        {activeTab === 'notifications' && (
                            recentNotifications.length > 0 ? (
                                <table className="admin-table">
                                    <thead>
                                        <tr>
                                            <th style={{ width: '80px' }}>Log #</th>
                                            <th>Recipient Details</th>
                                            <th>Channel & Type</th>
                                            <th>Status</th>
                                            <th style={{ textAlign: 'right' }}>Dispatched At</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {recentNotifications.map((notif) => (
                                            <tr key={notif.id} className="admin-table-row">
                                                <td className="admin-table-cell--id">#{notif.id}</td>
                                                <td>
                                                    <div style={{ display: 'flex', flexDirection: 'column' }}>
                                                        <span style={{ fontWeight: 600, color: 'var(--text-primary)' }}>
                                                            {notif.customer_name || notif.recipient || notif.email || notif.phone || 'Subscriber'}
                                                        </span>
                                                        {notif.lead_reference && (
                                                            <span style={{ fontSize: '0.74rem', color: 'var(--text-muted)' }}>
                                                                Lead: #{notif.lead_reference}
                                                            </span>
                                                        )}
                                                    </div>
                                                </td>
                                                <td>
                                                    <span className="room-type-slug-badge" style={{ textTransform: 'uppercase' }}>
                                                        {notif.channel || notif.type || 'Email'}
                                                    </span>
                                                </td>
                                                <td>
                                                    <span className={`admin-status-badge ${getStatusBadgeClass(notif.status)}`}>
                                                        <span className="admin-status-dot" />
                                                        {formatStatusLabel(notif.status)}
                                                    </span>
                                                </td>
                                                <td style={{ textAlign: 'right' }}>
                                                    <span style={{ fontSize: '0.8rem', color: 'var(--text-muted)' }}>
                                                        {formatDateTime(notif.sent_at || notif.created_at)}
                                                    </span>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            ) : (
                                <EmptyState
                                    title="No Recent Notifications"
                                    description="No notification audit records or dispatch logs found in the recent queue."
                                    icon={
                                        <svg width="38" height="38" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round">
                                            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9" />
                                            <path d="M13.73 21a2 2 0 0 1-3.46 0" />
                                        </svg>
                                    }
                                />
                            )
                        )}
                    </div>
                )}
            </div>
        </div>
    );
};

export default AdminDashboard;
