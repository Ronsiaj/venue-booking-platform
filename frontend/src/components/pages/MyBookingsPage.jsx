import { useState, useEffect, useCallback, useMemo } from 'react';
import Header from '../Header';
import Footer from '../Footer';
import { bookingApi, paymentApi } from '../../services/api';
import { getAuthSession } from '../../services/Utils';

// Helper to load Razorpay Standard Checkout SDK dynamically
const loadRazorpayScript = () => {
  return new Promise((resolve) => {
    if (typeof window !== 'undefined' && window.Razorpay) {
      resolve(true);
      return;
    }
    const script = document.createElement('script');
    script.src = 'https://checkout.razorpay.com/v1/checkout.js';
    script.async = true;
    script.onload = () => resolve(true);
    script.onerror = () => resolve(false);
    document.body.appendChild(script);
  });
};

/**
 * Check if a booking is eligible for cancellation by the customer:
 * 1. Booking status must be 'pending' or 'confirmed'
 * 2. Check-in date must be strictly in the future (tomorrow or later)
 * 3. Cannot be cancelled if checked_in, checked_out, cancelled, or no_show
 */
const isBookingCancellable = (booking) => {
  if (!booking) return false;
  const nonCancellable = ['cancelled', 'checked_in', 'checked_out', 'no_show'];
  if (nonCancellable.includes(booking.booking_status)) return false;

  const validStatuses = ['pending', 'confirmed'];
  if (!validStatuses.includes(booking.booking_status)) return false;

  if (!booking.check_in_date) return false;
  const todayStr = new Date().toLocaleDateString('en-CA'); // 'YYYY-MM-DD'
  return booking.check_in_date > todayStr;
};

/**
 * Check if a booking is eligible for online payment / retry payment:
 * 1. Booking status must be 'pending' or 'confirmed'
 * 2. Payment status must not be 'paid' or 'refunded'
 * 3. Total amount must be greater than 0
 */
const isBookingPayable = (booking) => {
  if (!booking) return false;
  const disallowedBookingStatuses = ['cancelled', 'checked_in', 'checked_out', 'no_show'];
  if (disallowedBookingStatuses.includes(booking.booking_status)) return false;

  const allowedBookingStatuses = ['pending', 'confirmed'];
  if (!allowedBookingStatuses.includes(booking.booking_status)) return false;

  const disallowedPaymentStatuses = ['paid', 'refunded'];
  if (disallowedPaymentStatuses.includes(booking.payment_status)) return false;

  const totalAmount = Number(booking.total_amount || 0);
  return totalAmount > 0;
};

const MyBookingsPage = ({ onNavigate }) => {
  const [bookings, setBookings] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  // Modals state
  const [selectedBooking, setSelectedBooking] = useState(null);
  const [viewModalOpen, setViewModalOpen] = useState(false);
  const [loadingDetails, setLoadingDetails] = useState(false);

  // Cancellation modal state
  const [cancelModalOpen, setCancelModalOpen] = useState(false);
  const [cancellingBooking, setCancellingBooking] = useState(null);
  const [cancelReason, setCancelReason] = useState('');
  const [cancelError, setCancelError] = useState('');
  const [isSubmittingCancel, setIsSubmittingCancel] = useState(false);

  // Toast notification state
  const [toastMessage, setToastMessage] = useState(null);
  const showToast = (text, type = 'success') => {
    setToastMessage({ text, type });
    setTimeout(() => setToastMessage(null), 4000);
  };

  // Payment processing state
  const [payingBookingId, setPayingBookingId] = useState(null);

  // Tab filter & transaction history state
  const [filterTab, setFilterTab] = useState('all'); // 'all' | 'pending' | 'paid' | 'transactions'
  const [transactions, setTransactions] = useState([]);
  const [loadingTransactions, setLoadingTransactions] = useState(false);
  const [transactionError, setTransactionError] = useState(null);

  // ─────────────────────────────────────────────────────────────
  // FETCH CUSTOMER BOOKINGS (/booking/list.php)
  // ─────────────────────────────────────────────────────────────
  const fetchCustomerBookings = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const session = getAuthSession('customer');
      if (!session.isAuthenticated) {
        onNavigate('/login');
        return;
      }

      const res = await bookingApi.list('limit=50', { authRole: 'customer' });
      if (res.success && res.data?.status && Array.isArray(res.data.data)) {
        setBookings(res.data.data);
      } else if (res.data?.data && Array.isArray(res.data.data)) {
        setBookings(res.data.data);
      } else {
        const errorMsg = res.error || res.data?.message || 'Failed to load your bookings.';
        setError(errorMsg);
      }
    } catch (err) {
      setError(err.message || 'An unexpected error occurred while loading bookings.');
    } finally {
      setLoading(false);
    }
  }, [onNavigate]);

  useEffect(() => {
    fetchCustomerBookings();
  }, [fetchCustomerBookings]);

  // ─────────────────────────────────────────────────────────────
  // FETCH CUSTOMER PAYMENT HISTORY (/payment/history.php)
  // ─────────────────────────────────────────────────────────────
  const fetchCustomerPaymentHistory = useCallback(async () => {
    setLoadingTransactions(true);
    setTransactionError(null);
    try {
      const res = await paymentApi.history('limit=50');
      if (res.success && res.data?.status !== false && res.data?.data?.payments) {
        setTransactions(res.data.data.payments);
      } else if (Array.isArray(res.data?.data)) {
        setTransactions(res.data.data);
      } else {
        const errMsg = res.error || res.data?.message || 'Failed to load transaction history.';
        setTransactionError(errMsg);
      }
    } catch (err) {
      setTransactionError(err.message || 'Error occurred while loading payment history.');
    } finally {
      setLoadingTransactions(false);
    }
  }, []);

  // Filtered bookings calculation
  const filteredBookings = useMemo(() => {
    if (filterTab === 'pending') {
      return bookings.filter((b) => b.payment_status !== 'paid' && b.booking_status !== 'cancelled');
    }
    if (filterTab === 'paid') {
      return bookings.filter((b) => b.payment_status === 'paid');
    }
    return bookings;
  }, [bookings, filterTab]);

  const pendingCount = useMemo(() => {
    return bookings.filter((b) => b.payment_status !== 'paid' && b.booking_status !== 'cancelled').length;
  }, [bookings]);

  const paidCount = useMemo(() => {
    return bookings.filter((b) => b.payment_status === 'paid').length;
  }, [bookings]);

  // Handle Tab Switch
  const handleTabChange = (tab) => {
    setFilterTab(tab);
    if (tab === 'transactions' && transactions.length === 0) {
      fetchCustomerPaymentHistory();
    }
  };

  // ─────────────────────────────────────────────────────────────
  // OPEN VIEW DETAILS MODAL (/booking/view.php)
  // ─────────────────────────────────────────────────────────────
  const handleOpenViewModal = async (booking) => {
    setSelectedBooking(booking);
    setViewModalOpen(true);
    setLoadingDetails(true);
    try {
      const res = await bookingApi.view(booking.id, { authRole: 'customer' });
      if (res.success && res.data?.status && res.data?.data) {
        setSelectedBooking(res.data.data);
      }
    } catch (err) {
      console.warn('Could not fetch full booking details:', err.message);
    } finally {
      setLoadingDetails(false);
    }
  };

  // ─────────────────────────────────────────────────────────────
  // OPEN CANCEL MODAL
  // ─────────────────────────────────────────────────────────────
  const handleOpenCancel = (booking) => {
    if (!isBookingCancellable(booking)) {
      showToast('This reservation cannot be cancelled.', 'error');
      return;
    }
    setCancellingBooking(booking);
    setCancelReason('');
    setCancelError('');
    setCancelModalOpen(true);
  };

  // ─────────────────────────────────────────────────────────────
  // SUBMIT CANCELLATION (/booking/cancel.php)
  // ─────────────────────────────────────────────────────────────
  const handleConfirmCancel = async (e) => {
    e.preventDefault();
    if (!cancellingBooking || isSubmittingCancel) return;

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

    setIsSubmittingCancel(true);
    setCancelError('');

    try {
      const payload = {
        booking_id: cancellingBooking.id,
        reason: trimmedReason,
      };

      const res = await bookingApi.cancel(payload, { authRole: 'customer' });

      if (res.success && res.data?.status) {
        const successMsg =
          res.data?.message ||
          `Booking ${cancellingBooking.booking_reference || `#${cancellingBooking.id}`} was cancelled successfully.`;
        showToast(successMsg, 'success');
        setCancelModalOpen(false);
        setCancellingBooking(null);

        // Refresh list from backend
        fetchCustomerBookings();

        // Update details modal if currently open for this booking
        if (selectedBooking && selectedBooking.id === cancellingBooking.id) {
          setSelectedBooking((prev) => ({
            ...prev,
            booking_status: 'cancelled',
            cancellation_reason: trimmedReason,
            refund_status: res.data?.data?.refund_status || prev?.refund_status,
          }));
        }
      } else {
        const errMsg = res.error || res.data?.message || 'Unable to cancel booking at this time.';
        setCancelError(errMsg);
      }
    } catch (err) {
      setCancelError(err.message || 'An unexpected error occurred during cancellation.');
    } finally {
      setIsSubmittingCancel(false);
    }
  };

  // ─────────────────────────────────────────────────────────────
  // INITIATE / RETRY PAYMENT (POST /payment/create_order.php + verify)
  // ─────────────────────────────────────────────────────────────
  const handleInitiatePayment = async (booking) => {
    if (!booking || !booking.id || payingBookingId) return;

    setPayingBookingId(booking.id);
    try {
      // 1. Create order on backend
      const orderRes = await paymentApi.createOrder(booking.id);
      if (!orderRes.success || orderRes.data?.status === false) {
        const errMsg = orderRes.error || orderRes.data?.message || 'Failed to initialize payment order with server.';
        showToast(errMsg, 'error');
        setPayingBookingId(null);
        return;
      }

      const orderData = orderRes.data?.data || orderRes.data || {};
      const razorpayOrderId = orderData.razorpay_order_id;
      const razorpayKeyId = orderData.razorpay_key_id || import.meta.env?.VITE_RAZORPAY_KEY_ID || '';
      const amountInPaise = Number(orderData.amount_in_paise) || Math.round((Number(orderData.amount) || Number(booking.total_amount)) * 100);
      const currency = orderData.currency || 'INR';

      if (!razorpayOrderId) {
        showToast('Payment order ID was not received from payment gateway.', 'error');
        setPayingBookingId(null);
        return;
      }

      // 2. Load SDK
      const isLoaded = await loadRazorpayScript();
      if (!isLoaded || typeof window.Razorpay !== 'function') {
        showToast('Razorpay Checkout SDK could not be loaded. Please check your internet connection.', 'error');
        setPayingBookingId(null);
        return;
      }

      // 3. Open Razorpay Checkout Modal
      const authSession = getAuthSession('customer');
      const customerUser = authSession?.user || {};

      const options = {
        key: razorpayKeyId,
        amount: amountInPaise,
        currency: currency,
        name: 'SM Groups',
        description: `Room Booking ${booking.booking_reference ? `(${booking.booking_reference})` : `#${booking.id}`}`,
        order_id: razorpayOrderId,
        prefill: {
          name: customerUser.name || '',
          email: customerUser.email || '',
          contact: customerUser.phone || ''
        },
        theme: {
          color: '#b45309'
        },
        handler: async (response) => {
          try {
            const verifyRes = await paymentApi.verify({
              booking_id: booking.id,
              razorpay_order_id: response.razorpay_order_id || razorpayOrderId,
              razorpay_payment_id: response.razorpay_payment_id,
              razorpay_signature: response.razorpay_signature
            });

            if (verifyRes.success && verifyRes.data?.status !== false) {
              const paidAmountFormatted = Number(orderData.amount || booking.total_amount).toLocaleString('en-IN');
              showToast(`Payment of ₹${paidAmountFormatted} verified successfully! Your booking is confirmed.`, 'success');
              fetchCustomerBookings();
              if (selectedBooking && selectedBooking.id === booking.id) {
                setSelectedBooking((prev) => ({
                  ...prev,
                  payment_status: 'paid',
                  booking_payment_status: 'paid',
                  booking_status: 'confirmed',
                  razorpay_payment_id: response.razorpay_payment_id
                }));
              }
            } else {
              const verifyErr = verifyRes.error || verifyRes.data?.message || 'Payment verification failed.';
              showToast(verifyErr, 'error');
            }
          } catch (vErr) {
            showToast(vErr.message || 'Error occurred during payment verification.', 'error');
          } finally {
            setPayingBookingId(null);
          }
        },
        modal: {
          ondismiss: () => {
            setPayingBookingId(null);
            showToast('Payment window closed. Your booking remains pending.', 'info');
          }
        }
      };

      const rzp = new window.Razorpay(options);
      rzp.on('payment.failed', (response) => {
        setPayingBookingId(null);
        const failureReason = response.error?.description || response.error?.reason || 'Payment failed.';
        showToast(`Payment failed: ${failureReason}`, 'error');
      });

      rzp.open();
    } catch (err) {
      showToast(err.message || 'An unexpected error occurred while launching payment.', 'error');
      setPayingBookingId(null);
    }
  };

  const formatDate = (dateStr) => {
    if (!dateStr) return '—';
    try {
      const d = new Date(dateStr);
      if (isNaN(d.getTime())) return dateStr;
      return d.toLocaleDateString('en-IN', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
      });
    } catch {
      return dateStr;
    }
  };

  const getStatusBadge = (status) => {
    switch (status) {
      case 'confirmed':
        return <span className="customer-badge customer-badge--confirmed">Confirmed</span>;
      case 'pending':
        return <span className="customer-badge customer-badge--pending">Pending</span>;
      case 'checked_in':
        return <span className="customer-badge customer-badge--active">Checked In</span>;
      case 'checked_out':
        return <span className="customer-badge customer-badge--completed">Completed</span>;
      case 'cancelled':
        return <span className="customer-badge customer-badge--cancelled">Cancelled</span>;
      case 'no_show':
        return <span className="customer-badge customer-badge--cancelled">No Show</span>;
      default:
        return <span className="customer-badge customer-badge--neutral">{status || 'Unknown'}</span>;
    }
  };

  const getPaymentBadge = (status) => {
    switch (status) {
      case 'paid':
        return <span className="customer-payment-badge customer-payment-badge--paid">Paid</span>;
      case 'partial':
        return <span className="customer-payment-badge customer-payment-badge--partial">Partial</span>;
      case 'refunded':
        return <span className="customer-payment-badge customer-payment-badge--refunded">Refunded</span>;
      case 'failed':
        return <span className="customer-payment-badge customer-payment-badge--failed">Failed</span>;
      default:
        return <span className="customer-payment-badge customer-payment-badge--pending">Payment Pending</span>;
    }
  };

  return (
    <div className="my-bookings-page">
      <Header onNavigate={onNavigate} currentPage="/my-bookings" />

      {/* Toast Notification */}
      {toastMessage && (
        <div className={`customer-toast customer-toast--${toastMessage.type}`} role="alert">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round">
            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
            <polyline points="22 4 12 14.01 9 11.01" />
          </svg>
          <span>{toastMessage.text}</span>
        </div>
      )}

      <main className="my-bookings-main">
        {/* Page Hero Banner */}
        <section className="my-bookings-hero">
          <div className="my-bookings-hero__inner">
            <div className="my-bookings-hero__badge">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
                <rect x="3" y="4" width="18" height="18" rx="2" ry="2" />
                <line x1="16" y1="2" x2="16" y2="6" />
                <line x1="8" y1="2" x2="8" y2="6" />
                <line x1="3" y1="10" x2="21" y2="10" />
              </svg>
              <span>Stay Reservations</span>
            </div>
            <h1 className="my-bookings-hero__title">My Room Bookings</h1>
            <p className="my-bookings-hero__subtitle">
              Manage your upcoming reservations, stay history, attached dining packages, and reservation requests.
            </p>
          </div>
        </section>

        {/* Content Container */}
        <div className="my-bookings-container">
          {/* Top Quick Actions Bar */}
          <div className="my-bookings-nav-bar">
            <div className="my-bookings-nav-links" style={{ flexWrap: 'wrap', gap: '6px' }}>
              <button
                type="button"
                className={`my-bookings-tab-btn ${filterTab === 'all' ? 'my-bookings-tab-btn--active' : ''}`}
                onClick={() => handleTabChange('all')}
              >
                All Bookings ({bookings.length})
              </button>
              <button
                type="button"
                className={`my-bookings-tab-btn ${filterTab === 'pending' ? 'my-bookings-tab-btn--active' : ''}`}
                onClick={() => handleTabChange('pending')}
                style={{
                  color: filterTab === 'pending' ? '#ffffff' : pendingCount > 0 ? '#b45309' : undefined,
                  background: filterTab === 'pending' ? '#d97706' : pendingCount > 0 ? '#fffbeb' : undefined,
                  borderColor: pendingCount > 0 ? '#fde68a' : undefined
                }}
              >
                Pending Payment ({pendingCount})
              </button>
              <button
                type="button"
                className={`my-bookings-tab-btn ${filterTab === 'paid' ? 'my-bookings-tab-btn--active' : ''}`}
                onClick={() => handleTabChange('paid')}
              >
                Paid ({paidCount})
              </button>
              <button
                type="button"
                className={`my-bookings-tab-btn ${filterTab === 'transactions' ? 'my-bookings-tab-btn--active' : ''}`}
                onClick={() => handleTabChange('transactions')}
              >
                💳 Payment Receipts {transactions.length > 0 ? `(${transactions.length})` : ''}
              </button>
              <button
                type="button"
                className="my-bookings-tab-btn"
                onClick={() => onNavigate('/profile')}
              >
                Profile & Account
              </button>
            </div>

            <button
              type="button"
              className="my-bookings-cta-btn"
              onClick={() => onNavigate('/lodge')}
            >
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round">
                <line x1="12" y1="5" x2="12" y2="19" />
                <line x1="5" y1="12" x2="19" y2="12" />
              </svg>
              <span>Book Another Room</span>
            </button>
          </div>

          {/* Loading State for Bookings */}
          {loading && filterTab !== 'transactions' && (
            <div className="my-bookings-state-card">
              <div className="my-bookings-spinner"></div>
              <p>Fetching your verified room bookings...</p>
            </div>
          )}

          {/* Error State for Bookings */}
          {!loading && error && filterTab !== 'transactions' && (
            <div className="my-bookings-state-card my-bookings-state-card--error">
              <div className="my-bookings-error-icon">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                  <circle cx="12" cy="12" r="10" />
                  <line x1="12" y1="8" x2="12" y2="12" />
                  <line x1="12" y1="16" x2="12.01" y2="16" />
                </svg>
              </div>
              <h3>Unable to Load Bookings</h3>
              <p>{error}</p>
              <button type="button" className="my-bookings-retry-btn" onClick={fetchCustomerBookings}>
                Retry Loading
              </button>
            </div>
          )}

          {/* Empty State for Bookings */}
          {!loading && !error && filteredBookings.length === 0 && filterTab !== 'transactions' && (
            <div className="my-bookings-empty-card">
              <div className="my-bookings-empty-icon">🏨</div>
              <h3 className="my-bookings-empty-title">
                {filterTab === 'pending'
                  ? 'No Pending Payment Bookings'
                  : filterTab === 'paid'
                  ? 'No Paid Bookings Found'
                  : 'No Room Bookings Found'}
              </h3>
              <p className="my-bookings-empty-desc">
                {filterTab === 'pending'
                  ? 'All your room bookings are currently settled or confirmed.'
                  : 'Explore our luxury rooms, family suites, and curated dining packages at SM Grand Lodge.'}
              </p>
              <button
                type="button"
                className="my-bookings-explore-btn"
                onClick={() => onNavigate('/lodge')}
              >
                Browse Rooms & Suites
              </button>
            </div>
          )}

          {/* PAYMENT TRANSACTIONS TAB VIEW */}
          {filterTab === 'transactions' && (
            <div>
              {loadingTransactions && (
                <div className="my-bookings-state-card">
                  <div className="my-bookings-spinner"></div>
                  <p>Fetching payment transactions from payment ledger...</p>
                </div>
              )}

              {!loadingTransactions && transactionError && (
                <div className="my-bookings-state-card my-bookings-state-card--error">
                  <div className="my-bookings-error-icon">💳</div>
                  <h3>Unable to Load Payment History</h3>
                  <p>{transactionError}</p>
                  <button type="button" className="my-bookings-retry-btn" onClick={fetchCustomerPaymentHistory}>
                    Retry
                  </button>
                </div>
              )}

              {!loadingTransactions && !transactionError && transactions.length === 0 && (
                <div className="my-bookings-empty-card">
                  <div className="my-bookings-empty-icon">💳</div>
                  <h3 className="my-bookings-empty-title">No Payment Transactions Recorded</h3>
                  <p className="my-bookings-empty-desc">
                    When you complete room bookings using Razorpay, your digital payment receipts and transaction records will appear here.
                  </p>
                </div>
              )}

              {!loadingTransactions && !transactionError && transactions.length > 0 && (
                <div style={{ display: 'flex', flexDirection: 'column', gap: '12px' }}>
                  {transactions.map((t, idx) => {
                    const pay = t.payment || {};
                    const bkg = t.booking || {};
                    return (
                      <div key={t.id || pay.id || idx} className="customer-transaction-card" style={{ padding: '16px' }}>
                        <div className="customer-transaction-header">
                          <div style={{ display: 'flex', alignItems: 'center', gap: '10px' }}>
                            <span style={{ fontSize: '1.2rem' }}>💳</span>
                            <div>
                              <strong style={{ fontSize: '0.92rem', color: '#1e293b', display: 'block' }}>
                                {pay.razorpay_payment_id || `Transaction #${pay.id || t.id}`}
                              </strong>
                              <span style={{ fontSize: '0.76rem', color: '#64748b' }}>
                                For Booking {bkg.booking_reference || `#${bkg.id}`}
                              </span>
                            </div>
                          </div>
                          <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                            {getPaymentBadge(pay.payment_status || bkg.booking_payment_status)}
                            {bkg.id && (
                              <button
                                type="button"
                                className="my-booking-btn my-booking-btn--details"
                                style={{ padding: '6px 12px', fontSize: '0.78rem' }}
                                onClick={() => handleOpenViewModal(bkg)}
                              >
                                View Stay Details
                              </button>
                            )}
                          </div>
                        </div>

                        <div className="customer-transaction-grid" style={{ marginTop: '12px' }}>
                          <div className="customer-transaction-cell">
                            <span className="customer-transaction-cell-label">Razorpay Order ID</span>
                            <span className="customer-transaction-cell-value" style={{ fontFamily: 'monospace', fontSize: '0.78rem' }}>
                              {pay.razorpay_order_id || '—'}
                            </span>
                          </div>
                          <div className="customer-transaction-cell">
                            <span className="customer-transaction-cell-label">Amount Paid</span>
                            <span className="customer-transaction-cell-value" style={{ color: 'var(--gold-800, #b8860b)', fontSize: '0.95rem', fontWeight: 800 }}>
                              ₹{Number(pay.amount || bkg.booking_total_amount || 0).toLocaleString('en-IN', { minimumFractionDigits: 2 })}
                            </span>
                          </div>
                          <div className="customer-transaction-cell">
                            <span className="customer-transaction-cell-label">Payment Method</span>
                            <span className="customer-transaction-cell-value" style={{ textTransform: 'uppercase' }}>
                              {pay.payment_method || pay.payment_gateway || 'Razorpay Online'}
                            </span>
                          </div>
                          <div className="customer-transaction-cell">
                            <span className="customer-transaction-cell-label">Transaction Date</span>
                            <span className="customer-transaction-cell-value">
                              {formatDate(pay.paid_at || pay.created_at)}
                            </span>
                          </div>
                        </div>
                      </div>
                    );
                  })}
                </div>
              )}
            </div>
          )}

          {/* Bookings List Cards */}
          {!loading && !error && filteredBookings.length > 0 && filterTab !== 'transactions' && (
            <div className="my-bookings-cards-grid">
              {filteredBookings.map((b) => (
                <div key={b.id} className="my-booking-card">
                  <div className="my-booking-card__header">
                    <div className="my-booking-card__ref-wrap">
                      <span className="my-booking-card__ref">
                        {b.booking_reference || `Booking #${b.id}`}
                      </span>
                      <span className="my-booking-card__booked-date">
                        Booked on {formatDate(b.created_at)}
                      </span>
                    </div>

                    <div className="my-booking-card__badges">
                      {getStatusBadge(b.booking_status)}
                      {getPaymentBadge(b.payment_status)}
                    </div>
                  </div>

                  <div className="my-booking-card__body">
                    <div className="my-booking-card__room-title">
                      <h4>{b.room_type_name || 'Standard Luxury Room'}</h4>
                      <span className="my-booking-card__room-num">
                        Room {b.room_number || b.room_id} • {b.lodge_name || 'SM Grand Lodge'}
                      </span>
                    </div>

                    <div className="my-booking-card__dates-grid">
                      <div className="my-booking-card__date-box">
                        <span className="my-booking-card__date-label">Check-In</span>
                        <span className="my-booking-card__date-val">📅 {b.check_in_date}</span>
                      </div>

                      <div className="my-booking-card__stay-nights">
                        <span className="my-booking-card__nights-badge">
                          {b.total_nights || 1} Night(s)
                        </span>
                      </div>

                      <div className="my-booking-card__date-box">
                        <span className="my-booking-card__date-label">Check-Out</span>
                        <span className="my-booking-card__date-val">📅 {b.check_out_date}</span>
                      </div>
                    </div>

                    <div className="my-booking-card__pricing-row">
                      <div className="my-booking-card__guests-info">
                        👥 {b.total_guests || (b.adults ? `${b.adults} Adult(s)` : '1 Guest')}
                      </div>
                      <div className="my-booking-card__total-wrap">
                        <span className="my-booking-card__total-label">Total Amount:</span>
                        <span className="my-booking-card__total-val">
                          ₹{Number(b.total_amount || 0).toLocaleString('en-IN', { minimumFractionDigits: 2 })}
                        </span>
                      </div>
                    </div>

                    {/* Cancellation details if cancelled */}
                    {b.booking_status === 'cancelled' && b.cancellation_reason && (
                      <div className="my-booking-card__cancel-banner">
                        <strong>Reason for Cancellation:</strong> {b.cancellation_reason}
                      </div>
                    )}
                  </div>

                  <div className="my-booking-card__footer">
                    {/* Pay Now / Retry Payment Action */}
                    {isBookingPayable(b) && (
                      <button
                        type="button"
                        className="my-booking-btn my-booking-btn--pay"
                        onClick={() => handleInitiatePayment(b)}
                        disabled={payingBookingId === b.id}
                        title="Complete payment for this booking"
                      >
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round">
                          <rect x="1" y="4" width="22" height="16" rx="2" ry="2" />
                          <line x1="1" y1="10" x2="23" y2="10" />
                        </svg>
                        <span>
                          {payingBookingId === b.id
                            ? 'Connecting...'
                            : b.payment_status === 'failed'
                            ? 'Retry Payment'
                            : 'Pay Now'}
                        </span>
                      </button>
                    )}

                    <button
                      type="button"
                      className="my-booking-btn my-booking-btn--details"
                      onClick={() => handleOpenViewModal(b)}
                    >
                      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                        <path d="M1 12 s4 -8 11 -8 11 8 11 8 -4 8 -11 8 -11 -8 -11 -8 z" />
                        <circle cx="12" cy="12" r="3" />
                      </svg>
                      <span>View Details</span>
                    </button>

                    {/* Contextual Cancel Reservation Action */}
                    {isBookingCancellable(b) && (
                      <button
                        type="button"
                        className="my-booking-btn my-booking-btn--cancel"
                        onClick={() => handleOpenCancel(b)}
                        title="Cancel this reservation"
                      >
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                          <line x1="18" y1="6" x2="6" y2="18" />
                          <line x1="6" y1="6" x2="18" y2="18" />
                        </svg>
                        <span>Cancel Booking</span>
                      </button>
                    )}
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>
      </main>

      {/* ─── FULL BOOKING DETAILS MODAL ─── */}
      {viewModalOpen && selectedBooking && (
        <div className="customer-modal-overlay" onClick={() => setViewModalOpen(false)}>
          <div
            className="customer-modal customer-modal--large"
            onClick={(e) => e.stopPropagation()}
            role="dialog"
            aria-modal="true"
          >
            <div className="customer-modal__header">
              <div className="customer-modal__title-wrap">
                <h3 className="customer-modal__title">
                  Reservation Details: {selectedBooking.booking_reference || `#${selectedBooking.id}`}
                </h3>
                <span className="customer-modal__subtitle">
                  Room reservation information & billing breakdown
                </span>
              </div>
              <button
                type="button"
                className="customer-modal__close-btn"
                onClick={() => setViewModalOpen(false)}
                aria-label="Close dialog"
              >
                ✕
              </button>
            </div>

            <div className="customer-modal__body">
              {loadingDetails && (
                <div style={{ textAlign: 'center', padding: '10px', color: '#d97706', fontSize: '0.85rem' }}>
                  Loading latest details from reservation desk...
                </div>
              )}

              {/* Status Header Bar */}
              <div className="customer-modal-status-bar">
                <div>
                  <span style={{ fontSize: '0.8rem', color: '#78716c', display: 'block' }}>Current Status</span>
                  {getStatusBadge(selectedBooking.booking_status)}
                </div>
                <div>
                  <span style={{ fontSize: '0.8rem', color: '#78716c', display: 'block' }}>Payment Status</span>
                  {getPaymentBadge(selectedBooking.payment_status)}
                </div>
                <div>
                  <span style={{ fontSize: '0.8rem', color: '#78716c', display: 'block' }}>Total Bill</span>
                  <span style={{ fontSize: '1.1rem', fontWeight: 800, color: 'var(--gold-800, #b8860b)' }}>
                    ₹{Number(selectedBooking.total_amount || 0).toLocaleString('en-IN', { minimumFractionDigits: 2 })}
                  </span>
                </div>
              </div>

              {/* Information Grid */}
              <div className="customer-details-grid">
                <div className="customer-detail-box">
                  <span className="customer-detail-label">Room / Suite</span>
                  <span className="customer-detail-val" style={{ color: 'var(--gold-800, #b8860b)' }}>
                    Room {selectedBooking.room_number || selectedBooking.room_id} ({selectedBooking.room_type_name || 'Standard'})
                  </span>
                </div>

                <div className="customer-detail-box">
                  <span className="customer-detail-label">Primary Guest</span>
                  <span className="customer-detail-val">{selectedBooking.customer_name || '—'}</span>
                </div>

                <div className="customer-detail-box">
                  <span className="customer-detail-label">Check-In Date</span>
                  <span className="customer-detail-val" style={{ color: '#047857', fontWeight: 700 }}>
                    📅 {selectedBooking.check_in_date}
                  </span>
                </div>

                <div className="customer-detail-box">
                  <span className="customer-detail-label">Check-Out Date</span>
                  <span className="customer-detail-val" style={{ color: '#b91c1c', fontWeight: 700 }}>
                    📅 {selectedBooking.check_out_date}
                  </span>
                </div>

                <div className="customer-detail-box">
                  <span className="customer-detail-label">Stay Duration</span>
                  <span className="customer-detail-val">{selectedBooking.total_nights || 1} Night(s)</span>
                </div>

                <div className="customer-detail-box">
                  <span className="customer-detail-label">Guests Count</span>
                  <span className="customer-detail-val">
                    {selectedBooking.adults || 1} Adult(s) {selectedBooking.children ? `, ${selectedBooking.children} Child` : ''}
                  </span>
                </div>

                {/* Special Requests */}
                {selectedBooking.special_requests && (
                  <div className="customer-detail-box customer-detail-box--full">
                    <span className="customer-detail-label">Special Requests</span>
                    <p style={{ margin: '4px 0 0', fontSize: '0.88rem', color: '#44403c' }}>
                      {selectedBooking.special_requests}
                    </p>
                  </div>
                )}

                {/* Cancellation Details if cancelled */}
                {selectedBooking.booking_status === 'cancelled' && (
                  <div className="customer-detail-box customer-detail-box--full" style={{ backgroundColor: '#fef2f2', borderColor: '#fecaca' }}>
                    <span className="customer-detail-label" style={{ color: '#991b1b' }}>Cancellation Reason</span>
                    <p style={{ margin: '4px 0 0', fontSize: '0.88rem', color: '#b91c1c', fontWeight: 600 }}>
                      {selectedBooking.cancellation_reason || 'No reason specified.'}
                    </p>
                  </div>
                )}

                {/* Bundled Food Packages */}
                {selectedBooking.food_packages && selectedBooking.food_packages.length > 0 && (
                  <div className="customer-detail-box customer-detail-box--full">
                    <span className="customer-detail-label">
                      Attached Food Packages ({selectedBooking.food_packages.length})
                    </span>
                    <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(220px, 1fr))', gap: '8px', marginTop: '6px' }}>
                      {selectedBooking.food_packages.map((pkg, idx) => (
                        <div key={idx} className="customer-food-card">
                          <div>
                            <span style={{ fontWeight: 600, fontSize: '0.86rem', display: 'block' }}>
                              {pkg.package_name || pkg.name}
                            </span>
                            <span style={{ fontSize: '0.74rem', color: '#78716c', textTransform: 'capitalize' }}>
                              {pkg.meal_type}
                            </span>
                          </div>
                          <span style={{ fontWeight: 700, color: 'var(--gold-800, #b8860b)', fontSize: '0.85rem' }}>
                            ×{pkg.quantity || 1}
                          </span>
                        </div>
                      ))}
                    </div>
                  </div>
                )}

                {/* Ordered Food Menu Items */}
                {selectedBooking.food_items && selectedBooking.food_items.length > 0 && (
                  <div className="customer-detail-box customer-detail-box--full">
                    <span className="customer-detail-label">
                      Ordered Menu Dishes ({selectedBooking.food_items.length})
                    </span>
                    <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(200px, 1fr))', gap: '8px', marginTop: '6px' }}>
                      {selectedBooking.food_items.map((fi, idx) => (
                        <div key={idx} className="customer-food-card customer-food-card--item">
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

                {/* Payment Breakdown & Gateway Transactions Ledger */}
                <div className="customer-detail-box customer-detail-box--full">
                  <span className="customer-detail-label">Payment & Billing Summary</span>
                  <div className="customer-payment-ledger">
                    <div className="customer-ledger-row">
                      <span>Room Stay Subtotal:</span>
                      <span>₹{Number(selectedBooking.room_subtotal || selectedBooking.total_amount || 0).toLocaleString('en-IN', { minimumFractionDigits: 2 })}</span>
                    </div>
                    {Number(selectedBooking.gst_amount || 0) > 0 && (
                      <div className="customer-ledger-row">
                        <span>Applicable GST / Taxes:</span>
                        <span>₹{Number(selectedBooking.gst_amount || 0).toLocaleString('en-IN', { minimumFractionDigits: 2 })}</span>
                      </div>
                    )}
                    <div className="customer-ledger-row customer-ledger-row--total">
                      <span>Total Booking Bill:</span>
                      <span>₹{Number(selectedBooking.total_amount || 0).toLocaleString('en-IN', { minimumFractionDigits: 2 })}</span>
                    </div>
                  </div>
                </div>

                {/* Verified Gateway Transactions & Receipts */}
                <div className="customer-detail-box customer-detail-box--full">
                  <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: '6px' }}>
                    <span className="customer-detail-label" style={{ margin: 0 }}>
                      Verified Gateway Transactions ({selectedBooking.payments?.length || 0})
                    </span>
                    {getPaymentBadge(selectedBooking.payment_status)}
                  </div>

                  {selectedBooking.payments && selectedBooking.payments.length > 0 ? (
                    <div style={{ display: 'flex', flexDirection: 'column', gap: '8px', marginTop: '6px' }}>
                      {selectedBooking.payments.map((p, pIdx) => (
                        <div key={p.id || pIdx} className="customer-transaction-card">
                          <div className="customer-transaction-header">
                            <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                              <span style={{ fontSize: '1rem' }}>💳</span>
                              <span className="customer-transaction-id">
                                {p.razorpay_payment_id || `Payment #${p.id}`}
                              </span>
                            </div>
                            <div>{getPaymentBadge(p.payment_status)}</div>
                          </div>

                          <div className="customer-transaction-grid">
                            <div className="customer-transaction-cell">
                              <span className="customer-transaction-cell-label">Razorpay Order ID</span>
                              <span className="customer-transaction-cell-value" style={{ fontFamily: 'monospace', fontSize: '0.78rem' }}>
                                {p.razorpay_order_id || '—'}
                              </span>
                            </div>
                            <div className="customer-transaction-cell">
                              <span className="customer-transaction-cell-label">Amount</span>
                              <span className="customer-transaction-cell-value" style={{ color: 'var(--gold-800, #b8860b)', fontWeight: 800 }}>
                                ₹{Number(p.amount || 0).toLocaleString('en-IN', { minimumFractionDigits: 2 })} {p.currency || 'INR'}
                              </span>
                            </div>
                            <div className="customer-transaction-cell">
                              <span className="customer-transaction-cell-label">Payment Method</span>
                              <span className="customer-transaction-cell-value" style={{ textTransform: 'uppercase' }}>
                                {p.payment_method || p.payment_gateway || 'Razorpay Secure'}
                              </span>
                            </div>
                            <div className="customer-transaction-cell">
                              <span className="customer-transaction-cell-label">Date & Time</span>
                              <span className="customer-transaction-cell-value">
                                {formatDate(p.paid_at || p.created_at)}
                              </span>
                            </div>
                          </div>
                        </div>
                      ))}
                    </div>
                  ) : (
                    <div style={{ padding: '12px', background: '#f8fafc', borderRadius: '8px', border: '1px dashed #cbd5e1', marginTop: '6px', fontSize: '0.84rem', color: '#64748b' }}>
                      {selectedBooking.payment_status === 'paid' ? (
                        <span>✓ Full booking payment has been settled.</span>
                      ) : (
                        <span>No online payment transaction recorded yet. Use the <strong>Pay Now</strong> button below to complete payment securely via Razorpay.</span>
                      )}
                    </div>
                  )}
                </div>
              </div>
            </div>

            <div className="customer-modal__footer">
              {isBookingPayable(selectedBooking) && (
                <button
                  type="button"
                  className="customer-btn customer-btn--pay"
                  onClick={() => handleInitiatePayment(selectedBooking)}
                  disabled={payingBookingId === selectedBooking.id}
                >
                  <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round" style={{ verticalAlign: 'middle', marginRight: '6px' }}>
                    <rect x="1" y="4" width="22" height="16" rx="2" ry="2" />
                    <line x1="1" y1="10" x2="23" y2="10" />
                  </svg>
                  <span>
                    {payingBookingId === selectedBooking.id
                      ? 'Connecting to Gateway...'
                      : selectedBooking.payment_status === 'failed'
                      ? 'Retry Payment'
                      : `Pay Now (₹${Number(selectedBooking.total_amount || 0).toLocaleString('en-IN')})`}
                  </span>
                </button>
              )}
              {isBookingCancellable(selectedBooking) && (
                <button
                  type="button"
                  className="customer-btn customer-btn--danger"
                  onClick={() => {
                    setViewModalOpen(false);
                    handleOpenCancel(selectedBooking);
                  }}
                >
                  Cancel This Reservation
                </button>
              )}
              <button
                type="button"
                className="customer-btn customer-btn--secondary"
                onClick={() => setViewModalOpen(false)}
              >
                Close
              </button>
            </div>
          </div>
        </div>
      )}

      {/* ─── CANCEL RESERVATION CONFIRMATION MODAL ─── */}
      {cancelModalOpen && cancellingBooking && (
        <div className="customer-modal-overlay" onClick={() => !isSubmittingCancel && setCancelModalOpen(false)}>
          <div className="customer-modal customer-modal--danger" onClick={(e) => e.stopPropagation()} role="dialog" aria-modal="true">
            <div className="customer-modal__header">
              <div className="customer-modal__title-wrap">
                <div className="customer-modal__danger-icon">
                  <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                    <circle cx="12" cy="12" r="10" />
                    <line x1="12" y1="8" x2="12" y2="12" />
                    <line x1="12" y1="16" x2="12.01" y2="16" />
                  </svg>
                </div>
                <h3 className="customer-modal__title">Cancel Room Reservation?</h3>
                <span className="customer-modal__subtitle">
                  This action will cancel your booking in our reservation system.
                </span>
              </div>
              <button
                type="button"
                className="customer-modal__close-btn"
                onClick={() => !isSubmittingCancel && setCancelModalOpen(false)}
                disabled={isSubmittingCancel}
                aria-label="Close dialog"
              >
                ✕
              </button>
            </div>

            <form onSubmit={handleConfirmCancel}>
              <div className="customer-modal__body">
                <div className="customer-warning-box">
                  <p>
                    Are you sure you want to cancel booking <strong>{cancellingBooking.booking_reference || `#${cancellingBooking.id}`}</strong> for stay starting on <strong>{cancellingBooking.check_in_date}</strong>?
                  </p>
                </div>

                {cancelError && (
                  <div className="customer-error-banner">
                    <strong>Cancellation Failed:</strong> {cancelError}
                  </div>
                )}

                <div className="customer-form-group">
                  <label className="customer-form-label" htmlFor="customer-cancel-reason">
                    Reason for Cancellation <span style={{ color: '#dc2626' }}>*</span>
                  </label>
                  <textarea
                    id="customer-cancel-reason"
                    className="customer-form-textarea"
                    rows={3}
                    placeholder="e.g. Travel schedule changed, personal reasons..."
                    value={cancelReason}
                    onChange={(e) => {
                      setCancelReason(e.target.value);
                      if (cancelError) setCancelError('');
                    }}
                    maxLength={1000}
                    disabled={isSubmittingCancel}
                    required
                  />
                  <span className="customer-form-hint">
                    Please provide at least 3 characters.
                  </span>
                </div>
              </div>

              <div className="customer-modal__footer">
                <button
                  type="button"
                  className="customer-btn customer-btn--secondary"
                  onClick={() => setCancelModalOpen(false)}
                  disabled={isSubmittingCancel}
                >
                  Keep Reservation
                </button>
                <button
                  type="submit"
                  className="customer-btn customer-btn--danger"
                  disabled={isSubmittingCancel}
                >
                  {isSubmittingCancel ? 'Cancelling...' : 'Yes, Cancel Reservation'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      <Footer onNavigate={onNavigate} />
    </div>
  );
};

export default MyBookingsPage;
