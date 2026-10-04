import React, { useState, useMemo, useEffect, useCallback } from 'react';
import Select from 'react-select';
import Pagination from './Pagination';
import api, { bookingApi } from '../services/api';

/**
 * Custom React Select styling matching SM Groups Admin theme
 */
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
        fontWeight: '600',
        fontSize: '0.88rem',
    }),
    input: (base) => ({
        ...base,
        color: 'var(--text-primary)',
        fontSize: '0.88rem',
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
 * Booking Status Options supported by backend (/booking/list.php)
 */
export const BOOKING_STATUS_OPTIONS = [
    { value: 'all', label: 'All Booking Statuses' },
    { value: 'confirmed', label: 'Confirmed (Upcoming)' },
    { value: 'checked_in', label: 'Checked In (Active Stay)' },
    { value: 'checked_out', label: 'Checked Out (Completed)' },
    { value: 'pending', label: 'Pending Approval' },
    { value: 'cancelled', label: 'Cancelled' },
    { value: 'no_show', label: 'No Show' },
];

/**
 * Payment Status Options supported by backend
 */
export const PAYMENT_STATUS_OPTIONS = [
    { value: 'all', label: 'All Payment Statuses' },
    { value: 'paid', label: 'Fully Paid' },
    { value: 'partial', label: 'Partially Paid' },
    { value: 'pending', label: 'Payment Pending' },
    { value: 'failed', label: 'Payment Failed' },
    { value: 'refunded', label: 'Refunded' },
];

/**
 * Check if a booking can be cancelled:
 * - 'cancelled', 'checked_in', 'checked_out', 'no_show' are strictly disallowed.
 * - Status must be 'pending' or 'confirmed'.
 * - check_in_date must be strictly in the future (tomorrow or later in YYYY-MM-DD).
 */
export const isBookingCancellable = (booking) => {
    if (!booking) return false;

    // Strictly hide for clearly non-cancellable statuses
    const nonCancellableStatuses = ['cancelled', 'checked_in', 'checked_out', 'no_show'];
    if (nonCancellableStatuses.includes(booking.booking_status)) {
        return false;
    }

    // Only pending and confirmed bookings can be cancelled
    const cancellableStatuses = ['pending', 'confirmed'];
    if (!cancellableStatuses.includes(booking.booking_status)) {
        return false;
    }

    if (!booking.check_in_date) return false;
    const todayStr = new Date().toLocaleDateString('en-CA'); // 'YYYY-MM-DD'
    return booking.check_in_date > todayStr;
};

/**
 * Booking data helper accessors for seamless handling of nested API response shapes
 */
export const getBookingCustomerName = (b) => b?.customer?.name || b?.customer_name || 'Guest';
export const getBookingCustomerPhone = (b) => b?.customer?.phone || b?.customer_phone || '';
export const getBookingCustomerEmail = (b) => b?.customer?.email || b?.customer_email || '';
export const getBookingCustomerId = (b) => b?.customer?.id || b?.customer_id || b?.user_id || '';
export const getBookingRoomNumber = (b) => {
    if (b?.rooms && Array.isArray(b.rooms) && b.rooms.length > 0) {
        const nums = b.rooms.map((r) => r.room_number || r.name || r.room_name).filter(Boolean);
        if (nums.length > 0) return nums.join(', ');
        return String(b.rooms[0].room_id || '—');
    }
    return b?.room_number || (b?.room_id ? String(b.room_id) : '—');
};
export const getBookingRoomType = (b) => {
    if (b?.rooms && Array.isArray(b.rooms) && b.rooms.length > 0) {
        const types = b.rooms.map((r) => r.room_type?.name || r.room_type_name).filter(Boolean);
        if (types.length > 0) return types.join(', ');
    }
    return b?.room_type_name || 'Standard Room';
};
export const getBookingTotalAmount = (b) => {
    if (b?.pricing?.total_amount !== undefined) return Number(b.pricing.total_amount);
    return Number(b?.total_amount || 0);
};
export const getBookingNights = (b) => b?.number_of_nights || b?.total_nights || 1;
export const getBookingGuests = (b) => {
    if (b?.total_guests) return `${b.total_guests} Guest(s)`;
    const adults = b?.adults || 1;
    const children = b?.children || 0;
    return `${adults} Adult${Number(adults) > 1 ? 's' : ''}${Number(children) > 0 ? `, ${children} Child` : ''}`;
};
export const getBookingSpecialRequests = (b) => b?.special_request || b?.special_requests || '';

/**
 * Main Booking & Reservation Module for Dashboard (Admin & Staff)
 */
const BookingsModule = () => {
    // ─────────────────────────────────────────────────────────────
    // STATE: BOOKINGS DATA & FILTERS
    // ─────────────────────────────────────────────────────────────
    const [bookings, setBookings] = useState([]);
    const [searchQuery, setSearchQuery] = useState('');
    const [statusFilter, setStatusFilter] = useState('all');
    const [paymentStatusFilter, setPaymentStatusFilter] = useState('all');
    const [checkInDateFilter, setCheckInDateFilter] = useState('');
    const [checkOutDateFilter, setCheckOutDateFilter] = useState('');

    // Pagination State (Synced with Backend /booking/list.php)
    const [currentPage, setCurrentPage] = useState(1);
    const [pageSize, setPageSize] = useState(10);
    const [totalRecords, setTotalRecords] = useState(0);
    const [isLoading, setIsLoading] = useState(true);

    // Modals State
    const [viewModalOpen, setViewModalOpen] = useState(false);
    const [viewingBooking, setViewingBooking] = useState(null);
    const [isLoadingDetails, setIsLoadingDetails] = useState(false);

    const [checkInModalOpen, setCheckInModalOpen] = useState(false);
    const [checkInBooking, setCheckInBooking] = useState(null);

    const [checkOutModalOpen, setCheckOutModalOpen] = useState(false);
    const [checkOutBooking, setCheckOutBooking] = useState(null);

    const [cancelModalOpen, setCancelModalOpen] = useState(false);
    const [cancellingBooking, setCancellingBooking] = useState(null);
    const [cancelReason, setCancelReason] = useState('');
    const [cancelError, setCancelError] = useState('');
    const [isSubmittingAction, setIsSubmittingAction] = useState(false);

    // Toast notification
    const [toastMessage, setToastMessage] = useState(null);
    const showToast = (msg, type = 'success') => {
        setToastMessage({ text: msg, type });
        setTimeout(() => setToastMessage(null), 3500);
    };

    // ─────────────────────────────────────────────────────────────
    // FETCH BOOKINGS FROM BACKEND API (/booking/list.php)
    // ─────────────────────────────────────────────────────────────
    const fetchBookings = useCallback(
        async (
            page = currentPage,
            limit = pageSize,
            search = searchQuery,
            status = statusFilter,
            paymentStatus = paymentStatusFilter,
            checkIn = checkInDateFilter,
            checkOut = checkOutDateFilter
        ) => {
            setIsLoading(true);
            try {
                const params = new URLSearchParams();
                params.append('page', String(page));
                params.append('limit', String(limit));
                if (search && search.trim()) params.append('search', search.trim());
                if (status && status !== 'all') params.append('booking_status', status);
                if (paymentStatus && paymentStatus !== 'all') params.append('payment_status', paymentStatus);
                if (checkIn) params.append('check_in_date', checkIn);
                if (checkOut) params.append('check_out_date', checkOut);

                const res = await api.get(`/booking/list.php?${params.toString()}`);
                if (res.success && res.data?.status && Array.isArray(res.data.data)) {
                    setBookings(res.data.data);
                    if (res.data.pagination) {
                        setTotalRecords(res.data.pagination.total_records ?? res.data.data.length);
                    }
                } else if (res.data?.data && Array.isArray(res.data.data)) {
                    setBookings(res.data.data);
                    if (res.data.pagination) {
                        setTotalRecords(res.data.pagination.total_records ?? res.data.data.length);
                    }
                } else {
                    setBookings([]);
                    setTotalRecords(0);
                }
            } catch (err) {
                console.warn('Backend bookings fetch note:', err.message);
                setBookings([]);
                setTotalRecords(0);
            } finally {
                setIsLoading(false);
            }
        },
        [currentPage, pageSize, searchQuery, statusFilter, paymentStatusFilter, checkInDateFilter, checkOutDateFilter]
    );

    useEffect(() => {
        fetchBookings(
            currentPage,
            pageSize,
            searchQuery,
            statusFilter,
            paymentStatusFilter,
            checkInDateFilter,
            checkOutDateFilter
        );
    }, [
        fetchBookings,
        currentPage,
        pageSize,
        searchQuery,
        statusFilter,
        paymentStatusFilter,
        checkInDateFilter,
        checkOutDateFilter
    ]);

    // ─────────────────────────────────────────────────────────────
    // STATS METRICS CALCULATION
    // ─────────────────────────────────────────────────────────────
    const metrics = useMemo(() => {
        const total = totalRecords || bookings.length;
        const activeStays = bookings.filter((b) => b.booking_status === 'checked_in').length;
        const confirmed = bookings.filter((b) => b.booking_status === 'confirmed').length;
        const pending = bookings.filter((b) => b.booking_status === 'pending').length;
        const checkedOut = bookings.filter((b) => b.booking_status === 'checked_out').length;
        return { total, activeStays, confirmed, pending, checkedOut };
    }, [bookings, totalRecords]);

    // Search options for react-select
    const searchSelectOptions = useMemo(() => {
        return bookings.map((b) => {
            const guest = getBookingCustomerName(b);
            const roomNum = getBookingRoomNumber(b);
            const ref = b.booking_reference || `Booking #${b.id}`;
            return {
                value: b.booking_reference || String(b.id),
                label: `${ref} — ${guest} (Room ${roomNum})`,
            };
        });
    }, [bookings]);

    // ─────────────────────────────────────────────────────────────
    // VIEW BOOKING DETAILS (/booking/view.php)
    // ─────────────────────────────────────────────────────────────
    const handleOpenViewModal = async (booking) => {
        setViewingBooking(booking);
        setViewModalOpen(true);
        setIsLoadingDetails(true);
        try {
            const res = await api.get(`/booking/view.php?booking_id=${booking.id}`);
            if (res.success && res.data?.status && res.data.data) {
                setViewingBooking(res.data.data);
            }
        } catch (err) {
            console.warn('Could not fetch extra booking details:', err.message);
        } finally {
            setIsLoadingDetails(false);
        }
    };

    // ─────────────────────────────────────────────────────────────
    // CHECK-IN ACTION (/booking/check_in.php)
    // ─────────────────────────────────────────────────────────────
    const handleOpenCheckIn = (booking) => {
        setCheckInBooking(booking);
        setCheckInModalOpen(true);
    };

    const handleConfirmCheckIn = async () => {
        if (!checkInBooking) return;
        setIsSubmittingAction(true);
        try {
            const res = await bookingApi.checkIn(checkInBooking.id);
            if (res.success && res.data?.status) {
                showToast(res.data?.message || `Guest checked in successfully for ${checkInBooking.booking_reference || `Booking #${checkInBooking.id}`}.`, 'success');
                await fetchBookings();
                setCheckInModalOpen(false);
                setCheckInBooking(null);
                if (viewModalOpen) setViewModalOpen(false);
            } else {
                const errorMsg = res.error || res.data?.message || 'Unable to check in guest.';
                showToast(errorMsg, 'error');
            }
        } catch (err) {
            showToast(err.message || 'An unexpected error occurred during check-in.', 'error');
        } finally {
            setIsSubmittingAction(false);
        }
    };

    // ─────────────────────────────────────────────────────────────
    // CHECK-OUT ACTION (/booking/check_out.php)
    // ─────────────────────────────────────────────────────────────
    const handleOpenCheckOut = (booking) => {
        setCheckOutBooking(booking);
        setCheckOutModalOpen(true);
    };

    const handleConfirmCheckOut = async () => {
        if (!checkOutBooking) return;
        setIsSubmittingAction(true);
        try {
            const res = await bookingApi.checkOut(checkOutBooking.id);
            if (res.success && res.data?.status) {
                showToast(res.data?.message || `Check-out completed for ${checkOutBooking.booking_reference || `Booking #${checkOutBooking.id}`}.`, 'success');
                await fetchBookings();
                setCheckOutModalOpen(false);
                setCheckOutBooking(null);
                if (viewModalOpen) setViewModalOpen(false);
            } else {
                const errorMsg = res.error || res.data?.message || 'Unable to complete check-out.';
                showToast(errorMsg, 'error');
            }
        } catch (err) {
            showToast(err.message || 'An unexpected error occurred during check-out.', 'error');
        } finally {
            setIsSubmittingAction(false);
        }
    };

    // ─────────────────────────────────────────────────────────────
    // CANCEL BOOKING ACTION (/booking/cancel.php)
    // ─────────────────────────────────────────────────────────────
    const handleOpenCancel = (booking) => {
        if (!isBookingCancellable(booking)) {
            showToast('This booking is not eligible for cancellation.', 'error');
            return;
        }
        setCancellingBooking(booking);
        setCancelReason('');
        setCancelError('');
        setCancelModalOpen(true);
    };

    const handleConfirmCancel = async (e) => {
        e.preventDefault();
        if (!cancellingBooking || isSubmittingAction) return;

        const trimmedReason = cancelReason.trim().replace(/\s+/g, ' ');
        if (!trimmedReason) {
            setCancelError('Please enter a cancellation reason.');
            return;
        }

        if (trimmedReason.length < 3) {
            setCancelError('Cancellation reason must contain at least 3 characters.');
            return;
        }

        if (trimmedReason.length > 1000) {
            setCancelError('Cancellation reason cannot exceed 1000 characters.');
            return;
        }

        setIsSubmittingAction(true);
        setCancelError('');
        try {
            const payload = {
                booking_id: cancellingBooking.id,
                reason: trimmedReason,
            };
            const res = await bookingApi.cancel(payload);
            if (res.success && res.data?.status) {
                const successMsg = res.data?.message || `Booking ${cancellingBooking.booking_reference || `#${cancellingBooking.id}`} cancelled successfully.`;
                showToast(successMsg, 'success');
                setCancelModalOpen(false);
                setCancellingBooking(null);

                // Refresh bookings list from backend
                await fetchBookings();

                // If viewing this booking in detail modal, update its status
                if (viewingBooking && (viewingBooking.id === cancellingBooking.id)) {
                    setViewingBooking((prev) => ({
                        ...prev,
                        booking_status: 'cancelled',
                        cancellation_reason: trimmedReason,
                        refund_status: res.data?.data?.refund_status || prev.refund_status,
                    }));
                }
            } else {
                const errMsg = res.error || res.data?.message || 'Unable to cancel booking. Please try again.';
                setCancelError(errMsg);
            }
        } catch (err) {
            setCancelError(err.message || 'An unexpected error occurred during cancellation.');
        } finally {
            setIsSubmittingAction(false);
        }
    };

    // Reset filters
    const handleResetFilters = () => {
        setSearchQuery('');
        setStatusFilter('all');
        setPaymentStatusFilter('all');
        setCheckInDateFilter('');
        setCheckOutDateFilter('');
        setCurrentPage(1);
    };

    // ─────────────────────────────────────────────────────────────
    // UI BADGE HELPERS
    // ─────────────────────────────────────────────────────────────
    const renderBookingStatusBadge = (status) => {
        const found = BOOKING_STATUS_OPTIONS.find((s) => s.value === status);
        const statusLabel = found ? found.label.split(' (')[0] : (status || 'Unknown');
        const badgeClass = `admin-status-badge--${status || 'pending'}`;
        return (
            <span className={`admin-status-badge ${badgeClass}`}>
                <span className="admin-status-dot" />
                {statusLabel}
            </span>
        );
    };

    const renderPaymentStatusBadge = (status) => {
        const found = PAYMENT_STATUS_OPTIONS.find((s) => s.value === status);
        const label = found ? found.label : (status || 'Unknown');
        const badgeClass = `admin-payment-badge--${status || 'pending'}`;
        return (
            <span className={`admin-payment-badge ${badgeClass}`}>
                {label}
            </span>
        );
    };

    return (
        <div className="room-types-module bookings-module">
            {/* Toast Notification */}
            {toastMessage && (
                <div className={`admin-toast admin-toast--${toastMessage.type}`} role="alert">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
                        <polyline points="22 4 12 14.01 9 11.01" />
                    </svg>
                    <span>{toastMessage.text}</span>
                </div>
            )}

            {/* Top Page Header */}
            <div className="dashboard-page-header">
                <div className="dashboard-page-header__info">
                    <h2 className="dashboard-page-header__title">Room Bookings & Reservations</h2>
                    <p className="dashboard-page-header__desc">
                        Manage guest room reservations, perform check-in / check-out operations, review stay schedules, and inspect payment transactions.
                    </p>
                </div>

                <div className="dashboard-page-header__actions">
                    <button
                        type="button"
                        className="admin-secondary-btn"
                        onClick={() => fetchBookings()}
                        disabled={isLoading}
                        style={{ display: 'flex', alignItems: 'center', gap: '8px' }}
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
                            style={{ animation: isLoading ? 'spin 1s linear infinite' : 'none' }}
                        >
                            <polyline points="23 4 23 10 17 10" />
                            <polyline points="1 20 1 14 7 14" />
                            <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15" />
                        </svg>
                        <span>Refresh Bookings</span>
                    </button>
                </div>
            </div>

            {/* Stats Overview Bar */}
            <div className="room-types-stats-grid">
                <div className="room-type-stat-card">
                    <div className="room-type-stat-card__icon room-type-stat-card__icon--gold">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2" />
                            <line x1="16" y1="2" x2="16" y2="6" />
                            <line x1="8" y1="2" x2="8" y2="6" />
                            <line x1="3" y1="10" x2="21" y2="10" />
                        </svg>
                    </div>
                    <div className="room-type-stat-card__info">
                        <span className="room-type-stat-card__label">Total Reservations</span>
                        <span className="room-type-stat-card__value">{metrics.total}</span>
                    </div>
                </div>

                <div className="room-type-stat-card">
                    <div className="room-type-stat-card__icon room-type-stat-card__icon--green">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                            <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4" />
                            <polyline points="10 17 15 12 10 7" />
                            <line x1="15" y1="12" x2="3" y2="12" />
                        </svg>
                    </div>
                    <div className="room-type-stat-card__info">
                        <span className="room-type-stat-card__label">Active Stays (Checked In)</span>
                        <span className="room-type-stat-card__value">{metrics.activeStays}</span>
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
                        <span className="room-type-stat-card__label">Confirmed (Upcoming)</span>
                        <span className="room-type-stat-card__value">{metrics.confirmed}</span>
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
                        <span className="room-type-stat-card__label">Pending / Unconfirmed</span>
                        <span className="room-type-stat-card__value">{metrics.pending}</span>
                    </div>
                </div>
            </div>

            {/* Filter and Search Bar with React Select */}
            <div className="room-types-toolbar" style={{ flexWrap: 'wrap', gap: '12px' }}>
                {/* Search with React Select */}
                <div className="room-types-search-select-wrap" style={{ flex: '1 1 260px', minWidth: '240px' }}>
                    <Select
                        value={
                            searchQuery
                                ? { value: searchQuery, label: searchQuery }
                                : null
                        }
                        onChange={(option) => {
                            setSearchQuery(option ? option.value : '');
                            setCurrentPage(1);
                        }}
                        options={searchSelectOptions}
                        onInputChange={(inputValue, { action }) => {
                            if (action === 'input-change') {
                                setSearchQuery(inputValue);
                                setCurrentPage(1);
                            }
                        }}
                        placeholder="Search by Reference, Guest, or Room..."
                        isClearable
                        isSearchable
                        styles={reactSelectStyles}
                        className="react-select-container"
                        classNamePrefix="react-select"
                        noOptionsMessage={({ inputValue }) =>
                            inputValue ? `No bookings match "${inputValue}"` : 'Type to search bookings'
                        }
                    />
                </div>

                {/* Booking Status Filter with React Select */}
                <div style={{ flex: '0 1 200px', minWidth: '180px' }}>
                    <Select
                        value={
                            BOOKING_STATUS_OPTIONS.find((opt) => opt.value === statusFilter) || BOOKING_STATUS_OPTIONS[0]
                        }
                        onChange={(opt) => {
                            setStatusFilter(opt ? opt.value : 'all');
                            setCurrentPage(1);
                        }}
                        options={BOOKING_STATUS_OPTIONS}
                        placeholder="All Booking Statuses"
                        isSearchable={false}
                        styles={reactSelectStyles}
                        className="react-select-container"
                        classNamePrefix="react-select"
                    />
                </div>

                {/* Payment Status Filter with React Select */}
                <div style={{ flex: '0 1 200px', minWidth: '180px' }}>
                    <Select
                        value={
                            PAYMENT_STATUS_OPTIONS.find((opt) => opt.value === paymentStatusFilter) || PAYMENT_STATUS_OPTIONS[0]
                        }
                        onChange={(opt) => {
                            setPaymentStatusFilter(opt ? opt.value : 'all');
                            setCurrentPage(1);
                        }}
                        options={PAYMENT_STATUS_OPTIONS}
                        placeholder="All Payment Statuses"
                        isSearchable={false}
                        styles={reactSelectStyles}
                        className="react-select-container"
                        classNamePrefix="react-select"
                    />
                </div>

                {/* Check-in Date Filter */}
                <div className="booking-filter-date-group">
                    <span style={{ fontSize: '0.78rem', color: '#78716c', fontWeight: 600 }}>From:</span>
                    <input
                        type="date"
                        className="booking-filter-date-input"
                        value={checkInDateFilter}
                        onChange={(e) => {
                            setCheckInDateFilter(e.target.value);
                            setCurrentPage(1);
                        }}
                        title="Filter by Check-in Date"
                    />
                </div>

                {/* Check-out Date Filter */}
                <div className="booking-filter-date-group">
                    <span style={{ fontSize: '0.78rem', color: '#78716c', fontWeight: 600 }}>To:</span>
                    <input
                        type="date"
                        className="booking-filter-date-input"
                        value={checkOutDateFilter}
                        onChange={(e) => {
                            setCheckOutDateFilter(e.target.value);
                            setCurrentPage(1);
                        }}
                        title="Filter by Check-out Date"
                    />
                </div>

                {/* Reset Filters button */}
                {(searchQuery || statusFilter !== 'all' || paymentStatusFilter !== 'all' || checkInDateFilter || checkOutDateFilter) && (
                    <button
                        type="button"
                        className="admin-secondary-btn"
                        onClick={handleResetFilters}
                        style={{ padding: '8px 14px', height: '42px', fontSize: '0.82rem', color: '#dc2626' }}
                    >
                        Reset Filters
                    </button>
                )}
            </div>

            {/* Bookings Data Table Card */}
            <div className="admin-table-card">
                {isLoading ? (
                    <div className="dashboard-loading-state" style={{ padding: '48px 0', textAlign: 'center' }}>
                        <div className="admin-spinner" style={{ margin: '0 auto 12px' }} />
                        <p style={{ color: '#78716c', fontSize: '0.9rem' }}>Loading bookings from server...</p>
                    </div>
                ) : bookings.length === 0 ? (
                    <div className="dashboard-empty-state">
                        <div className="dashboard-empty-state__icon-wrap">
                            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round">
                                <rect x="3" y="4" width="18" height="18" rx="2" ry="2" />
                                <line x1="16" y1="2" x2="16" y2="6" />
                                <line x1="8" y1="2" x2="8" y2="6" />
                                <line x1="3" y1="10" x2="21" y2="10" />
                            </svg>
                        </div>
                        <h4 className="dashboard-empty-state__title">No Reservations Found</h4>
                        <p className="dashboard-empty-state__desc">
                            {searchQuery || statusFilter !== 'all' || paymentStatusFilter !== 'all' || checkInDateFilter || checkOutDateFilter
                                ? 'No bookings matched your active search and filter criteria.'
                                : 'There are currently no room reservations recorded in the system.'}
                        </p>
                        {(searchQuery || statusFilter !== 'all' || paymentStatusFilter !== 'all' || checkInDateFilter || checkOutDateFilter) && (
                            <button
                                type="button"
                                className="admin-secondary-btn"
                                onClick={handleResetFilters}
                                style={{ marginTop: '12px' }}
                            >
                                Clear Active Filters
                            </button>
                        )}
                    </div>
                ) : (
                    <div className="admin-table-responsive">
                        <table className="admin-table">
                            <thead>
                                <tr>
                                    <th>Booking Ref</th>
                                    <th>Guest Details</th>
                                    <th>Room & Lodge</th>
                                    <th>Stay Schedule</th>
                                    <th>Payment & Total</th>
                                    <th>Status</th>
                                    <th className="text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                {bookings.map((booking) => (
                                    <tr key={booking.id} className="admin-table-row">
                                        {/* Booking Reference & ID */}
                                        <td>
                                            <div style={{ display: 'flex', flexDirection: 'column', gap: '2px' }}>
                                                <span className="admin-booking-ref">
                                                    {booking.booking_reference || `BK-${booking.id}`}
                                                </span>
                                                <span className="admin-booking-subtext">
                                                    ID #{booking.id}
                                                </span>
                                            </div>
                                        </td>
                                        <td>
                                            <div style={{ display: 'flex', flexDirection: 'column', gap: '2px' }}>
                                                <span className="admin-guest-name">
                                                    {getBookingCustomerName(booking)}
                                                </span>
                                                {getBookingCustomerPhone(booking) && (
                                                    <span className="admin-guest-phone">
                                                        📞 {getBookingCustomerPhone(booking)}
                                                    </span>
                                                )}
                                                {getBookingCustomerEmail(booking) && (
                                                    <span className="admin-guest-email">
                                                        {getBookingCustomerEmail(booking)}
                                                    </span>
                                                )}
                                            </div>
                                        </td>

                                        {/* Room & Lodge */}
                                        <td>
                                            <div style={{ display: 'flex', flexDirection: 'column', gap: '2px' }}>
                                                <div style={{ display: 'flex', alignItems: 'center', gap: '6px' }}>
                                                    <span className="room-number-badge" style={{ fontSize: '0.76rem', padding: '2px 6px' }}>
                                                        Room {getBookingRoomNumber(booking)}
                                                    </span>
                                                    <span style={{ fontWeight: 600, fontSize: '0.84rem' }}>
                                                        {getBookingRoomType(booking)}
                                                    </span>
                                                </div>
                                                <span style={{ fontSize: '0.74rem', color: '#78716c' }}>
                                                    {booking.lodge_name || 'SM Grand Lodge'}
                                                </span>
                                            </div>
                                        </td>

                                        {/* Stay Schedule (Dates & Nights) */}
                                        <td>
                                            <div className="admin-stay-dates">
                                                <span className="admin-dates-range">
                                                    {booking.check_in_date} → {booking.check_out_date}
                                                </span>
                                                <div style={{ display: 'flex', gap: '4px', marginTop: '2px' }}>
                                                    <span className="booking-schedule-pill">
                                                        {getBookingNights(booking)} Night(s)
                                                    </span>
                                                    <span className="booking-schedule-pill">
                                                        {getBookingGuests(booking)}
                                                    </span>
                                                </div>
                                            </div>
                                        </td>

                                        {/* Payment Status & Amount */}
                                        <td>
                                            <div style={{ display: 'flex', flexDirection: 'column', gap: '4px' }}>
                                                <span className="admin-booking-amount">
                                                    ₹{getBookingTotalAmount(booking).toLocaleString('en-IN', { minimumFractionDigits: 2 })}
                                                </span>
                                                {renderPaymentStatusBadge(booking.payment_status)}
                                            </div>
                                        </td>

                                        {/* Booking Status */}
                                        <td>
                                            {renderBookingStatusBadge(booking.booking_status)}
                                        </td>

                                        {/* Actions */}
                                        <td className="text-right">
                                            <div className="admin-table-actions" style={{ justifyContent: 'flex-end', gap: '6px' }}>
                                                {/* View Details button */}
                                                <button
                                                    type="button"
                                                    className="admin-action-btn admin-action-btn--view"
                                                    onClick={() => handleOpenViewModal(booking)}
                                                    title="View Full Booking Details"
                                                    aria-label="View Full Booking Details"
                                                >
                                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                                                        <circle cx="12" cy="12" r="3" />
                                                    </svg>
                                                </button>

                                                {/* Check-In Action (When Confirmed / Pending) */}
                                                {(booking.booking_status === 'confirmed' || booking.booking_status === 'pending') && (
                                                    <button
                                                        type="button"
                                                        className="admin-action-btn admin-action-btn--checkin"
                                                        onClick={() => handleOpenCheckIn(booking)}
                                                        title="Check In Guest"
                                                        aria-label="Check In Guest"
                                                    >
                                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                                            <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4" />
                                                            <polyline points="10 17 15 12 10 7" />
                                                            <line x1="15" y1="12" x2="3" y2="12" />
                                                        </svg>
                                                    </button>
                                                )}

                                                {/* Check-Out Action (When Checked-In) */}
                                                {booking.booking_status === 'checked_in' && (
                                                    <button
                                                        type="button"
                                                        className="admin-action-btn admin-action-btn--checkout"
                                                        onClick={() => handleOpenCheckOut(booking)}
                                                        title="Check Out Guest"
                                                        aria-label="Check Out Guest"
                                                    >
                                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                                            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
                                                            <polyline points="16 17 21 12 16 7" />
                                                            <line x1="21" y1="12" x2="9" y2="12" />
                                                        </svg>
                                                    </button>
                                                )}

                                                {/* Cancel Action (Contextual only when backend status allows) */}
                                                {isBookingCancellable(booking) && (
                                                    <button
                                                        type="button"
                                                        className="admin-action-btn admin-action-btn--delete"
                                                        onClick={() => handleOpenCancel(booking)}
                                                        title="Cancel Reservation"
                                                        aria-label="Cancel Reservation"
                                                    >
                                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                                            <line x1="18" y1="6" x2="6" y2="18" />
                                                            <line x1="6" y1="6" x2="18" y2="18" />
                                                        </svg>
                                                    </button>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                {/* Standard Unified Pagination */}
                <Pagination
                    currentPage={currentPage}
                    totalRecords={totalRecords}
                    pageSize={pageSize}
                    onPageChange={(p) => setCurrentPage(p)}
                    onPageSizeChange={(sz) => {
                        setPageSize(sz);
                        setCurrentPage(1);
                    }}
                    pageSizeOptions={[5, 10, 20, 50]}
                />
            </div>

            {/* ─── VIEW FULL BOOKING DETAILS MODAL ─── */}
            {viewModalOpen && viewingBooking && (
                <div className="admin-modal-overlay" onClick={() => setViewModalOpen(false)}>
                    <div
                        className="admin-modal admin-modal--view admin-modal--large"
                        onClick={(e) => e.stopPropagation()}
                        role="dialog"
                        aria-modal="true"
                        style={{ maxWidth: '850px' }}
                    >
                        <div className="admin-modal__header">
                            <div className="admin-modal__title-wrap">
                                <div className="admin-modal__badge-row">
                                    <span className="room-number-badge">
                                        {viewingBooking.booking_reference || `Booking #${viewingBooking.id}`}
                                    </span>
                                    {renderBookingStatusBadge(viewingBooking.booking_status)}
                                    {renderPaymentStatusBadge(viewingBooking.payment_status)}
                                </div>
                                <h3 className="admin-modal__title">
                                    Reservation Details — {getBookingCustomerName(viewingBooking)}
                                </h3>
                            </div>
                            <button
                                type="button"
                                className="admin-modal__close-btn"
                                onClick={() => setViewModalOpen(false)}
                                aria-label="Close dialog"
                            >
                                ✕
                            </button>
                        </div>

                        <div className="admin-modal__body" style={{ maxHeight: '70vh', overflowY: 'auto' }}>
                            {isLoadingDetails && (
                                <div style={{ textAlign: 'center', padding: '12px', color: '#d97706', fontSize: '0.85rem' }}>
                                    Refreshing latest backend details...
                                </div>
                            )}

                            {/* Guest & Room Grid */}
                            <div className="room-type-details-grid">
                                <div className="room-type-detail-item">
                                    <span className="room-type-detail-label">Primary Guest</span>
                                    <span className="room-type-detail-val">{getBookingCustomerName(viewingBooking)}</span>
                                </div>
                                <div className="room-type-detail-item">
                                    <span className="room-type-detail-label">Contact Phone</span>
                                    <span className="room-type-detail-val">{getBookingCustomerPhone(viewingBooking) || '—'}</span>
                                </div>
                                <div className="room-type-detail-item">
                                    <span className="room-type-detail-label">Guest Email</span>
                                    <span className="room-type-detail-val">{getBookingCustomerEmail(viewingBooking) || '—'}</span>
                                </div>
                                <div className="room-type-detail-item">
                                    <span className="room-type-detail-label">Customer ID</span>
                                    <span className="room-type-detail-val">#{getBookingCustomerId(viewingBooking) || '—'}</span>
                                </div>

                                <div className="room-type-detail-item">
                                    <span className="room-type-detail-label">Room Number</span>
                                    <span className="room-type-detail-val" style={{ fontWeight: 700, color: 'var(--gold-800)' }}>
                                        Room {getBookingRoomNumber(viewingBooking)}
                                    </span>
                                </div>
                                <div className="room-type-detail-item">
                                    <span className="room-type-detail-label">Room Type</span>
                                    <span className="room-type-detail-val">{getBookingRoomType(viewingBooking)}</span>
                                </div>
                                <div className="room-type-detail-item">
                                    <span className="room-type-detail-label">Lodge / Property</span>
                                    <span className="room-type-detail-val">{viewingBooking.lodge_name || 'SM Grand Lodge'}</span>
                                </div>
                                <div className="room-type-detail-item">
                                    <span className="room-type-detail-label">Check-In Date</span>
                                    <span className="room-type-detail-val" style={{ color: '#047857', fontWeight: 700 }}>
                                        📅 {viewingBooking.check_in_date}
                                    </span>
                                </div>
                                <div className="room-type-detail-item">
                                    <span className="room-type-detail-label">Check-Out Date</span>
                                    <span className="room-type-detail-val" style={{ color: '#b91c1c', fontWeight: 700 }}>
                                        📅 {viewingBooking.check_out_date}
                                    </span>
                                </div>
                                <div className="room-type-detail-item">
                                    <span className="room-type-detail-label">Stay Duration</span>
                                    <span className="room-type-detail-val">{getBookingNights(viewingBooking)} Night(s)</span>
                                </div>
                                <div className="room-type-detail-item">
                                    <span className="room-type-detail-label">Guests Count</span>
                                    <span className="room-type-detail-val">
                                        {getBookingGuests(viewingBooking)}
                                    </span>
                                </div>

                                {/* Special Requests */}
                                {getBookingSpecialRequests(viewingBooking) && (
                                    <div className="room-type-detail-item full-width">
                                        <span className="room-type-detail-label">Guest Special Requests</span>
                                        <p className="room-type-detail-text">
                                            {getBookingSpecialRequests(viewingBooking)}
                                        </p>
                                    </div>
                                )}

                                {/* Cancellation Details (When Cancelled) */}
                                {viewingBooking.booking_status === 'cancelled' && (
                                    <div className="room-type-detail-item full-width" style={{ backgroundColor: '#fef2f2', border: '1px solid #fecaca', borderRadius: '8px', padding: '12px 14px' }}>
                                        <span className="room-type-detail-label" style={{ color: '#991b1b', fontWeight: 700 }}>
                                            Cancellation Reason
                                        </span>
                                        <p className="room-type-detail-text" style={{ color: '#b91c1c', margin: '4px 0 0 0' }}>
                                            {viewingBooking.cancellation_reason || 'No cancellation reason provided.'}
                                        </p>
                                    </div>
                                )}

                                {/* Attached Food Packages & Meals */}
                                {viewingBooking.food_packages && viewingBooking.food_packages.length > 0 && (
                                    <div className="room-type-detail-item full-width">
                                        <span className="room-type-detail-label">Bundled Food Packages ({viewingBooking.food_packages.length})</span>
                                        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(240px, 1fr))', gap: '8px', marginTop: '6px' }}>
                                            {viewingBooking.food_packages.map((pkg, i) => (
                                                <div
                                                    key={i}
                                                    style={{
                                                        padding: '8px 12px',
                                                        backgroundColor: '#faf7f2',
                                                        border: '1px solid rgba(212, 160, 23, 0.25)',
                                                        borderRadius: '8px',
                                                        display: 'flex',
                                                        justifyContent: 'space-between',
                                                        alignItems: 'center',
                                                    }}
                                                >
                                                    <div>
                                                        <span style={{ fontWeight: 600, fontSize: '0.86rem', display: 'block' }}>
                                                            {pkg.package_name || pkg.name}
                                                        </span>
                                                        <span style={{ fontSize: '0.74rem', color: '#78716c', textTransform: 'capitalize' }}>
                                                            {pkg.meal_type}
                                                        </span>
                                                    </div>
                                                    <span style={{ fontWeight: 700, color: 'var(--gold-800)', fontSize: '0.84rem' }}>
                                                        ×{pkg.quantity || 1}
                                                    </span>
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                )}

                                {/* Attached Individual Food Items */}
                                {viewingBooking.food_items && viewingBooking.food_items.length > 0 && (
                                    <div className="room-type-detail-item full-width">
                                        <span className="room-type-detail-label">Ordered Menu Dishes ({viewingBooking.food_items.length})</span>
                                        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(220px, 1fr))', gap: '8px', marginTop: '6px' }}>
                                            {viewingBooking.food_items.map((fi, i) => (
                                                <div
                                                    key={i}
                                                    style={{
                                                        padding: '8px 12px',
                                                        backgroundColor: '#f8fafc',
                                                        border: '1px solid #cbd5e1',
                                                        borderRadius: '8px',
                                                        display: 'flex',
                                                        justifyContent: 'space-between',
                                                        alignItems: 'center',
                                                    }}
                                                >
                                                    <span style={{ fontWeight: 600, fontSize: '0.84rem' }}>
                                                        {fi.name || fi.food_item_name}
                                                    </span>
                                                    <span style={{ fontWeight: 700, color: '#475569', fontSize: '0.84rem' }}>
                                                        ×{fi.quantity || 1}
                                                    </span>
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                )}

                                {/* Payment Breakdown & Transactions */}
                                <div className="room-type-detail-item full-width" style={{ borderTop: '1px solid rgba(40, 30, 20, 0.1)', paddingTop: '16px', marginTop: '8px' }}>
                                    <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '10px' }}>
                                        <span className="room-type-detail-label" style={{ fontSize: '0.95rem', fontWeight: 700, margin: 0 }}>
                                            Payment & Transaction Ledger
                                        </span>
                                        <div style={{ display: 'flex', gap: '12px', alignItems: 'baseline' }}>
                                            <span style={{ fontSize: '0.85rem', color: '#78716c' }}>Total Bill:</span>
                                            <span style={{ fontSize: '1.1rem', fontWeight: 800, color: 'var(--gold-800)' }}>
                                                ₹{getBookingTotalAmount(viewingBooking).toLocaleString('en-IN', { minimumFractionDigits: 2 })}
                                            </span>
                                        </div>
                                    </div>

                                    {viewingBooking.payments && viewingBooking.payments.length > 0 ? (
                                        <div style={{ display: 'flex', flexDirection: 'column', gap: '8px' }}>
                                            {viewingBooking.payments.map((pmt, idx) => (
                                                <div key={idx} className="booking-ledger-item">
                                                    <div>
                                                        <span className="booking-ledger-title">
                                                            {pmt.payment_gateway ? pmt.payment_gateway.toUpperCase() : 'PAYMENT'} {pmt.razorpay_payment_id ? `(${pmt.razorpay_payment_id})` : ''}
                                                        </span>
                                                        <div className="booking-ledger-sub">
                                                            Method: {pmt.payment_method || 'Online'} • Paid at: {pmt.paid_at || pmt.created_at || '—'}
                                                        </div>
                                                    </div>
                                                    <div style={{ textAlign: 'right' }}>
                                                        <span className="booking-ledger-amount">
                                                            ₹{Number(pmt.amount || 0).toLocaleString('en-IN', { minimumFractionDigits: 2 })}
                                                        </span>
                                                        <span className="booking-ledger-status">
                                                            {pmt.payment_status}
                                                        </span>
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                    ) : (
                                        <div style={{ padding: '12px', backgroundColor: '#fffbeb', borderRadius: '6px', border: '1px solid #fde68a', color: '#b45309', fontSize: '0.84rem' }}>
                                            No payment records logged yet. Outstanding balance: ₹{getBookingTotalAmount(viewingBooking).toLocaleString('en-IN', { minimumFractionDigits: 2 })}
                                        </div>
                                    )}
                                </div>
                            </div>
                        </div>

                        <div className="admin-modal__footer" style={{ justifyContent: 'space-between' }}>
                            <div style={{ display: 'flex', gap: '8px' }}>
                                {/* Check In Action */}
                                {(viewingBooking.booking_status === 'confirmed' || viewingBooking.booking_status === 'pending') && (
                                    <button
                                        type="button"
                                        className="admin-primary-btn"
                                        style={{ backgroundColor: '#10b981', borderColor: '#10b981' }}
                                        onClick={() => handleOpenCheckIn(viewingBooking)}
                                    >
                                        Check In Guest
                                    </button>
                                )}

                                {/* Check Out Action */}
                                {viewingBooking.booking_status === 'checked_in' && (
                                    <button
                                        type="button"
                                        className="admin-primary-btn"
                                        style={{ backgroundColor: '#2563eb', borderColor: '#2563eb' }}
                                        onClick={() => handleOpenCheckOut(viewingBooking)}
                                    >
                                        Check Out Guest
                                    </button>
                                )}

                                {/* Cancel Action (Contextual only when backend status allows) */}
                                {isBookingCancellable(viewingBooking) && (
                                    <button
                                        type="button"
                                        className="admin-danger-btn"
                                        onClick={() => handleOpenCancel(viewingBooking)}
                                    >
                                        Cancel Reservation
                                    </button>
                                )}
                            </div>

                            <button
                                type="button"
                                className="admin-secondary-btn"
                                onClick={() => setViewModalOpen(false)}
                            >
                                Close Details
                            </button>
                        </div>
                    </div>
                </div>
            )}

            {/* ─── CHECK-IN CONFIRMATION MODAL ─── */}
            {checkInModalOpen && checkInBooking && (
                <div className="admin-modal-overlay" onClick={() => setCheckInModalOpen(false)}>
                    <div className="admin-modal" onClick={(e) => e.stopPropagation()} role="dialog" aria-modal="true">
                        <div className="admin-modal__header">
                            <div className="admin-modal__title-wrap">
                                <h3 className="admin-modal__title">Confirm Guest Check-In</h3>
                                <p className="admin-modal__subtitle">
                                    Transition booking status to Active / Checked-In.
                                </p>
                            </div>
                            <button
                                type="button"
                                className="admin-modal__close-btn"
                                onClick={() => setCheckInModalOpen(false)}
                                aria-label="Close dialog"
                            >
                                ✕
                            </button>
                        </div>

                        <div className="admin-modal__body">
                            <div style={{ padding: '16px', backgroundColor: '#ecfdf5', borderRadius: '8px', border: '1px solid #a7f3d0' }}>
                                <p style={{ margin: 0, color: '#065f46', fontSize: '0.9rem', lineHeight: '1.5' }}>
                                    Are you sure you want to mark guest <strong>"{checkInBooking.customer_name}"</strong> as <strong>Checked In</strong> for <strong>Room {checkInBooking.room_number || checkInBooking.room_id}</strong> (Ref: {checkInBooking.booking_reference || `#${checkInBooking.id}`})?
                                </p>
                            </div>
                        </div>

                        <div className="admin-modal__footer">
                            <button
                                type="button"
                                className="admin-secondary-btn"
                                onClick={() => setCheckInModalOpen(false)}
                                disabled={isSubmittingAction}
                            >
                                Cancel
                            </button>
                            <button
                                type="button"
                                className="admin-primary-btn"
                                style={{ backgroundColor: '#10b981', borderColor: '#10b981' }}
                                onClick={handleConfirmCheckIn}
                                disabled={isSubmittingAction}
                            >
                                {isSubmittingAction ? 'Processing...' : 'Yes, Check In Guest'}
                            </button>
                        </div>
                    </div>
                </div>
            )}

            {/* ─── CHECK-OUT CONFIRMATION MODAL ─── */}
            {checkOutModalOpen && checkOutBooking && (
                <div className="admin-modal-overlay" onClick={() => setCheckOutModalOpen(false)}>
                    <div className="admin-modal" onClick={(e) => e.stopPropagation()} role="dialog" aria-modal="true">
                        <div className="admin-modal__header">
                            <div className="admin-modal__title-wrap">
                                <h3 className="admin-modal__title">Confirm Guest Check-Out</h3>
                                <p className="admin-modal__subtitle">
                                    Finalize stay and mark room ready for turnover.
                                </p>
                            </div>
                            <button
                                type="button"
                                className="admin-modal__close-btn"
                                onClick={() => setCheckOutModalOpen(false)}
                                aria-label="Close dialog"
                            >
                                ✕
                            </button>
                        </div>

                        <div className="admin-modal__body">
                            <div style={{ padding: '16px', backgroundColor: '#eff6ff', borderRadius: '8px', border: '1px solid #bfdbfe' }}>
                                <p style={{ margin: 0, color: '#1e40af', fontSize: '0.9rem', lineHeight: '1.5' }}>
                                    Are you sure you want to complete check-out for <strong>"{checkOutBooking.customer_name}"</strong> from <strong>Room {checkOutBooking.room_number || checkOutBooking.room_id}</strong> (Ref: {checkOutBooking.booking_reference || `#${checkOutBooking.id}`})?
                                </p>
                            </div>
                        </div>

                        <div className="admin-modal__footer">
                            <button
                                type="button"
                                className="admin-secondary-btn"
                                onClick={() => setCheckOutModalOpen(false)}
                                disabled={isSubmittingAction}
                            >
                                Cancel
                            </button>
                            <button
                                type="button"
                                className="admin-primary-btn"
                                style={{ backgroundColor: '#2563eb', borderColor: '#2563eb' }}
                                onClick={handleConfirmCheckOut}
                                disabled={isSubmittingAction}
                            >
                                {isSubmittingAction ? 'Processing...' : 'Yes, Complete Check-Out'}
                            </button>
                        </div>
                    </div>
                </div>
            )}

            {/* ─── CANCEL RESERVATION MODAL ─── */}
            {cancelModalOpen && cancellingBooking && (
                <div className="admin-modal-overlay" onClick={() => !isSubmittingAction && setCancelModalOpen(false)}>
                    <div className="admin-modal admin-modal--danger" onClick={(e) => e.stopPropagation()} role="dialog" aria-modal="true">
                        <div className="admin-modal__header">
                            <div className="admin-modal__title-wrap">
                                <div className="admin-modal__danger-icon">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                        <circle cx="12" cy="12" r="10" />
                                        <line x1="12" y1="8" x2="12" y2="12" />
                                        <line x1="12" y1="16" x2="12.01" y2="16" />
                                    </svg>
                                </div>
                                <h3 className="admin-modal__title">Cancel Reservation?</h3>
                                <p className="admin-modal__subtitle">
                                    This action will cancel the booking and mark it as cancelled.
                                </p>
                            </div>
                            <button
                                type="button"
                                className="admin-modal__close-btn"
                                onClick={() => !isSubmittingAction && setCancelModalOpen(false)}
                                aria-label="Close dialog"
                                disabled={isSubmittingAction}
                            >
                                ✕
                            </button>
                        </div>

                        <form onSubmit={handleConfirmCancel}>
                            <div className="admin-modal__body">
                                <div className="delete-warning-box">
                                    <p>
                                        Are you sure you want to cancel booking <strong>{cancellingBooking.booking_reference || `#${cancellingBooking.id}`}</strong> for guest <strong>"{cancellingBooking.customer_name}"</strong> (Check-in: {cancellingBooking.check_in_date})?
                                    </p>
                                </div>

                                {cancelError && (
                                    <div style={{ marginTop: '14px', padding: '10px 14px', backgroundColor: '#fef2f2', border: '1px solid #fecaca', borderRadius: '8px', color: '#b91c1c', fontSize: '0.86rem' }}>
                                        <strong>Cancellation Failed:</strong> {cancelError}
                                    </div>
                                )}

                                <div className="admin-form-group" style={{ marginTop: '16px' }}>
                                    <label className="admin-form-label" htmlFor="cancel-reason-input">
                                        Reason for Cancellation <span style={{ color: '#dc2626' }}>*</span>
                                    </label>
                                    <textarea
                                        id="cancel-reason-input"
                                        className="admin-form-textarea"
                                        rows={3}
                                        placeholder="e.g. Guest requested cancellation, travel schedule changed..."
                                        value={cancelReason}
                                        onChange={(e) => {
                                            setCancelReason(e.target.value);
                                            if (cancelError) setCancelError('');
                                        }}
                                        maxLength={1000}
                                        disabled={isSubmittingAction}
                                        required
                                    />
                                    <span style={{ fontSize: '0.75rem', color: '#78716c', display: 'block', marginTop: '4px' }}>
                                        Provide at least 3 characters.
                                    </span>
                                </div>
                            </div>

                            <div className="admin-modal__footer">
                                <button
                                    type="button"
                                    className="admin-secondary-btn"
                                    onClick={() => setCancelModalOpen(false)}
                                    disabled={isSubmittingAction}
                                >
                                    Don't Cancel
                                </button>
                                <button
                                    type="submit"
                                    className="admin-danger-btn"
                                    disabled={isSubmittingAction}
                                >
                                    {isSubmittingAction ? 'Cancelling...' : 'Yes, Cancel Reservation'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </div>
    );
};

export default BookingsModule;
