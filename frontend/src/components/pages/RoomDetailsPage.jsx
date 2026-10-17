import { useState, useEffect, useCallback, useMemo } from 'react';
import Header from '../Header';
import Footer from '../Footer';
import DatePicker from '../DatePicker';
import { roomApi, bookingApi, roomFoodPackageApi, foodItemApi, paymentApi } from '../../services/api';
import { isRoleAuthenticated, getAuthSession } from '../../services/Utils';

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

// Amenity Icon Mapper
const getAmenityIcon = (iconName, amenityName = '') => {
  const key = (iconName || amenityName).toLowerCase();
  if (key.includes('wifi')) {
    return (
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
        <path d="M5 12.55a11 11 0 0 1 14.08 0" /><path d="M1.42 9a16 16 0 0 1 21.16 0" /><path d="M8.53 16.11a6 6 0 0 1 6.95 0" /><line x1="12" y1="20" x2="12.01" y2="20" />
      </svg>
    );
  }
  if (key.includes('ac') || key.includes('air') || key.includes('condition')) {
    return (
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
        <path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6" />
      </svg>
    );
  }
  if (key.includes('tv') || key.includes('television')) {
    return (
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
        <rect x="2" y="7" width="20" height="15" rx="2" ry="2" /><polyline points="17 2 12 7 7 2" />
      </svg>
    );
  }
  if (key.includes('hot') || key.includes('water') || key.includes('geyser')) {
    return (
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
        <path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z" />
      </svg>
    );
  }
  if (key.includes('parking') || key.includes('car')) {
    return (
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
        <rect x="3" y="3" width="18" height="18" rx="4" /><path d="M9 17V7h4a3 3 0 0 1 0 6H9" />
      </svg>
    );
  }
  if (key.includes('power') || key.includes('backup') || key.includes('generator')) {
    return (
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
        <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2" />
      </svg>
    );
  }
  if (key.includes('reception') || key.includes('desk') || key.includes('service')) {
    return (
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
        <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9" /><path d="M13.73 21a2 2 0 0 1-3.46 0" />
      </svg>
    );
  }
  if (key.includes('bath') || key.includes('toilet') || key.includes('shower')) {
    return (
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
        <path d="M9 6h6a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H9a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2z" />
        <path d="M12 2v4" />
      </svg>
    );
  }
  return (
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
      <polyline points="20 6 9 17 4 12" />
    </svg>
  );
};

// Image URL Resolver - only returns valid backend URLs, never substitutes random stock photos
const resolveRoomImgUrl = (img) => {
  if (!img) return null;
  if (typeof img === 'string') {
    if (img.startsWith('http') || img.startsWith('blob:') || img.startsWith('data:')) return img;
    return `http://localhost/sm_groups/backend/${img.replace(/^\/+/, '')}`;
  }
  if (img.url) return img.url;
  if (img.image_path) {
    if (img.image_path.startsWith('http') || img.image_path.startsWith('blob:') || img.image_path.startsWith('data:')) return img.image_path;
    return `http://localhost/sm_groups/backend/${img.image_path.replace(/^\/+/, '')}`;
  }
  return null;
};

// Date Helpers - Formatted as YYYY-MM-DD in local time
const formatDateToYYYYMMDD = (d) => {
  const year = d.getFullYear();
  const month = String(d.getMonth() + 1).padStart(2, '0');
  const day = String(d.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
};

const getTodayDateString = () => {
  return formatDateToYYYYMMDD(new Date());
};

const getTomorrowDateString = () => {
  const d = new Date();
  d.setDate(d.getDate() + 1);
  return formatDateToYYYYMMDD(d);
};

const RoomDetailsPage = ({ roomId, onNavigate }) => {
  const [room, setRoom] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [selectedImageIndex, setSelectedImageIndex] = useState(0);

  // Booking Form State
  const [checkInDate, setCheckInDate] = useState(() => {
    const params = new URLSearchParams(window.location.search);
    return params.get('check_in') || getTodayDateString();
  });
  const [checkOutDate, setCheckOutDate] = useState(() => {
    const params = new URLSearchParams(window.location.search);
    return params.get('check_out') || getTomorrowDateString();
  });
  const [totalGuests, setTotalGuests] = useState(() => {
    const params = new URLSearchParams(window.location.search);
    return Number(params.get('guests')) || 1;
  });
  const [specialRequest, setSpecialRequest] = useState('');

  // Food Integration State
  const [availablePackages, setAvailablePackages] = useState([]);
  const [availableFoodItems, setAvailableFoodItems] = useState([]);
  const [loadingFood, setLoadingFood] = useState(false);
  const [selectedPackages, setSelectedPackages] = useState([]); // [{ package_id, name, meal_type, quantity, notes, items }]
  const [selectedFoodItems, setSelectedFoodItems] = useState([]); // [{ food_item_id, name, food_type, category_name, quantity, notes }]
  const [activeFoodTab, setActiveFoodTab] = useState('packages'); // 'packages' | 'items' | 'selected'
  const [packageSearch, setPackageSearch] = useState('');
  const [packageMealTypeFilter, setPackageMealTypeFilter] = useState('all');
  const [foodItemSearch, setFoodItemSearch] = useState('');
  const [foodTypeFilter, setFoodTypeFilter] = useState('all');

  const [isSubmittingBooking, setIsSubmittingBooking] = useState(false);
  const [isProcessingPayment, setIsProcessingPayment] = useState(false);
  const [paymentPendingBooking, setPaymentPendingBooking] = useState(null);
  const [bookingSuccess, setBookingSuccess] = useState(null);
  const [bookingError, setBookingError] = useState(null);

  // Fetch Room Details from Backend
  const fetchRoomDetails = useCallback(async () => {
    if (!roomId) return;
    setLoading(true);
    setError(null);
    try {
      const res = await roomApi.view(roomId);
      const roomData = res.data?.data?.room || res.data?.room || res.data?.data || (res.data?.id ? res.data : null);
      if (res.success && roomData) {
        setRoom(roomData);
      } else {
        setError(res.error || 'Room not found.');
      }
    } catch (err) {
      setError(err.message || 'Failed to load room details.');
    } finally {
      setLoading(false);
    }
  }, [roomId]);

  // Fetch Food Packages and Food Items from Backend
  const fetchFoodCatalog = useCallback(async () => {
    setLoadingFood(true);
    try {
      const [pkgRes, itemRes] = await Promise.all([
        roomFoodPackageApi.list('status=active&limit=100'),
        foodItemApi.list('status=active&limit=100')
      ]);

      if (pkgRes.success && pkgRes.data?.data && Array.isArray(pkgRes.data.data)) {
        setAvailablePackages(pkgRes.data.data);
      } else if (Array.isArray(pkgRes.data?.data)) {
        setAvailablePackages(pkgRes.data.data);
      }

      if (itemRes.success && itemRes.data?.data?.food_items) {
        setAvailableFoodItems(itemRes.data.data.food_items);
      } else if (Array.isArray(itemRes.data?.data?.food_items)) {
        setAvailableFoodItems(itemRes.data.data.food_items);
      }
    } catch (err) {
      console.warn('Food catalog fetch note:', err.message);
    } finally {
      setLoadingFood(false);
    }
  }, []);

  useEffect(() => {
    fetchRoomDetails();
    fetchFoodCatalog();
  }, [fetchRoomDetails, fetchFoodCatalog]);

  // Gallery Images array
  const galleryImages = useMemo(() => {
    if (!room) return [];
    const imgs = [];
    if (Array.isArray(room.images) && room.images.length > 0) {
      room.images.forEach(img => {
        const url = resolveRoomImgUrl(img);
        if (url) imgs.push(url);
      });
    } else if (room.image_path) {
      const url = resolveRoomImgUrl(room.image_path);
      if (url) imgs.push(url);
    }
    return imgs;
  }, [room]);

  const currentMainImg = galleryImages[selectedImageIndex] || galleryImages[0] || null;

  // Nights calculation
  const numberOfNights = useMemo(() => {
    if (!checkInDate || !checkOutDate) return 1;
    const diff = (new Date(checkOutDate) - new Date(checkInDate)) / (1000 * 60 * 60 * 24);
    return diff > 0 ? Math.ceil(diff) : 0;
  }, [checkInDate, checkOutDate]);

  // Pricing calculations
  const pricePerNight = Number(room?.price_per_night) || 0;
  const staySubtotal = pricePerNight * (numberOfNights > 0 ? numberOfNights : 1);
  const gstRate = Number(room?.gst_rate) || 0;
  const isGstIncluded = Boolean(room?.price_includes_gst);

  const calculatedBasePrice = isGstIncluded && gstRate > 0
    ? staySubtotal / (1 + gstRate / 100)
    : staySubtotal;
  const calculatedGst = isGstIncluded && gstRate > 0
    ? staySubtotal - calculatedBasePrice
    : staySubtotal * (gstRate / 100);
  const finalTotalAmount = isGstIncluded ? staySubtotal : (staySubtotal + calculatedGst);

  // Customer session
  const isCustomerLoggedIn = isRoleAuthenticated('customer');
  const customerSession = getAuthSession('customer');

  // Package Selection Handlers
  const handleTogglePackage = (pkg) => {
    setSelectedPackages((prev) => {
      const exists = prev.find((p) => p.package_id === pkg.id);
      if (exists) {
        return prev.filter((p) => p.package_id !== pkg.id);
      }
      return [
        ...prev,
        {
          package_id: pkg.id,
          name: pkg.name,
          meal_type: pkg.meal_type,
          quantity: 1,
          notes: '',
          items: pkg.items || []
        }
      ];
    });
  };

  const handleUpdatePackageQuantity = (pkgId, delta) => {
    setSelectedPackages((prev) =>
      prev.map((p) => {
        if (p.package_id === pkgId) {
          const newQty = Math.max(1, Math.min(100, (p.quantity || 1) + delta));
          return { ...p, quantity: newQty };
        }
        return p;
      })
    );
  };

  const handleUpdatePackageNotes = (pkgId, notes) => {
    setSelectedPackages((prev) =>
      prev.map((p) => (p.package_id === pkgId ? { ...p, notes } : p))
    );
  };

  // Food Item Selection Handlers
  const handleToggleFoodItem = (item) => {
    setSelectedFoodItems((prev) => {
      const exists = prev.find((i) => i.food_item_id === item.id);
      if (exists) {
        return prev.filter((i) => i.food_item_id !== item.id);
      }
      return [
        ...prev,
        {
          food_item_id: item.id,
          name: item.name,
          food_type: item.food_type,
          category_name: item.category_name,
          quantity: 1,
          notes: ''
        }
      ];
    });
  };

  const handleUpdateFoodItemQuantity = (itemId, delta) => {
    setSelectedFoodItems((prev) =>
      prev.map((i) => {
        if (i.food_item_id === itemId) {
          const newQty = Math.max(1, Math.min(100, (i.quantity || 1) + delta));
          return { ...i, quantity: newQty };
        }
        return i;
      })
    );
  };

  const handleUpdateFoodItemNotes = (itemId, notes) => {
    setSelectedFoodItems((prev) =>
      prev.map((i) => (i.food_item_id === itemId ? { ...i, notes } : i))
    );
  };

  // Filtered Food Packages
  const filteredPackages = useMemo(() => {
    return availablePackages.filter((pkg) => {
      const q = packageSearch.toLowerCase().trim();
      const matchesSearch =
        !q ||
        pkg.name?.toLowerCase().includes(q) ||
        pkg.description?.toLowerCase().includes(q) ||
        (Array.isArray(pkg.items) &&
          pkg.items.some((it) => it.food_item_name?.toLowerCase().includes(q)));
      const matchesMeal =
        packageMealTypeFilter === 'all' || pkg.meal_type === packageMealTypeFilter;
      return matchesSearch && matchesMeal;
    });
  }, [availablePackages, packageSearch, packageMealTypeFilter]);

  // Filtered Food Items
  const filteredFoodItems = useMemo(() => {
    return availableFoodItems.filter((item) => {
      const q = foodItemSearch.toLowerCase().trim();
      const matchesSearch =
        !q ||
        item.name?.toLowerCase().includes(q) ||
        item.category_name?.toLowerCase().includes(q) ||
        item.description?.toLowerCase().includes(q);
      const matchesType = foodTypeFilter === 'all' || item.food_type === foodTypeFilter;
      return matchesSearch && matchesType;
    });
  }, [availableFoodItems, foodItemSearch, foodTypeFilter]);

  // Total food items selected count
  const totalFoodSelectedCount = selectedPackages.length + selectedFoodItems.length;

  // Handle Payment Signature Verification via Backend POST /payment/verify.php
  const handlePaymentVerification = async ({
    booking_id,
    booking_reference,
    razorpay_order_id,
    razorpay_payment_id,
    razorpay_signature,
    booking_total,
    food_packages,
    food_items
  }) => {
    setIsProcessingPayment(true);
    setBookingError(null);
    try {
      const verifyRes = await paymentApi.verify({
        booking_id,
        razorpay_order_id,
        razorpay_payment_id,
        razorpay_signature
      });

      if (verifyRes.success && verifyRes.data?.status !== false) {
        const verifiedData = verifyRes.data?.data || verifyRes.data || {};
        setPaymentPendingBooking(null);
        setBookingSuccess({
          reference: verifiedData.booking_reference || booking_reference || `Ref #${booking_id}`,
          booking_id,
          total_amount: verifiedData.total_booking_amount || verifiedData.amount || booking_total || finalTotalAmount,
          payment_id: verifiedData.payment_id,
          razorpay_payment_id: verifiedData.razorpay_payment_id || razorpay_payment_id,
          payment_method: verifiedData.payment_method || 'Online (Razorpay)',
          payment_status: verifiedData.payment_status || 'paid',
          message: verifyRes.data?.message || 'Payment verified successfully! Your room booking is confirmed.',
          food_packages: food_packages || selectedPackages,
          food_items: food_items || selectedFoodItems
        });
      } else {
        const errorMsg = verifyRes.error || verifyRes.data?.message || 'Payment verification failed. Please contact support if money was deducted.';
        setBookingError(errorMsg);
        setPaymentPendingBooking({
          bookingId: booking_id,
          bookingRef: booking_reference,
          amount: booking_total || finalTotalAmount,
          razorpayOrderId: razorpay_order_id
        });
      }
    } catch (err) {
      setBookingError(err.message || 'An error occurred during payment verification.');
      setPaymentPendingBooking({
        bookingId: booking_id,
        bookingRef: booking_reference,
        amount: booking_total || finalTotalAmount,
        razorpayOrderId: razorpay_order_id
      });
    } finally {
      setIsProcessingPayment(false);
      setIsSubmittingBooking(false);
    }
  };

  // Launch Razorpay Checkout Modal using backend-generated order
  const initiateRazorpayPayment = async ({
    bookingId,
    bookingRef,
    bookingAmount,
    packages,
    items
  }) => {
    setIsProcessingPayment(true);
    setBookingError(null);

    try {
      // Step 1: Create Order in Backend
      const orderRes = await paymentApi.createOrder(bookingId);
      if (!orderRes.success || orderRes.data?.status === false) {
        const errMsg = orderRes.error || orderRes.data?.message || 'Failed to initialize payment order with server.';
        setBookingError(errMsg);
        setPaymentPendingBooking({
          bookingId,
          bookingRef,
          amount: bookingAmount
        });
        setIsProcessingPayment(false);
        setIsSubmittingBooking(false);
        return;
      }

      const orderData = orderRes.data?.data || orderRes.data || {};
      const razorpayOrderId = orderData.razorpay_order_id;
      const razorpayKeyId = orderData.razorpay_key_id || import.meta.env?.VITE_RAZORPAY_KEY_ID || '';
      const amountInPaise = Number(orderData.amount_in_paise) || Math.round((Number(orderData.amount) || Number(bookingAmount)) * 100);
      const currency = orderData.currency || 'INR';

      if (!razorpayOrderId) {
        setBookingError('Payment order identifier was not received from payment gateway.');
        setPaymentPendingBooking({
          bookingId,
          bookingRef,
          amount: bookingAmount
        });
        setIsProcessingPayment(false);
        setIsSubmittingBooking(false);
        return;
      }

      // Step 2: Ensure Razorpay SDK is loaded
      const isLoaded = await loadRazorpayScript();
      if (!isLoaded || typeof window.Razorpay !== 'function') {
        setBookingError('Razorpay Checkout SDK could not be loaded. Please check your internet connection.');
        setPaymentPendingBooking({
          bookingId,
          bookingRef,
          amount: bookingAmount,
          razorpayOrderId
        });
        setIsProcessingPayment(false);
        setIsSubmittingBooking(false);
        return;
      }

      // Step 3: Open Razorpay modal with backend order
      const authSession = getAuthSession('customer');
      const customerUser = authSession?.user || {};

      const options = {
        key: razorpayKeyId,
        amount: amountInPaise,
        currency: currency,
        name: 'SM Groups',
        description: `Room Booking ${bookingRef ? `(${bookingRef})` : `#${bookingId}`}`,
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
          await handlePaymentVerification({
            booking_id: bookingId,
            booking_reference: bookingRef,
            razorpay_order_id: response.razorpay_order_id || razorpayOrderId,
            razorpay_payment_id: response.razorpay_payment_id,
            razorpay_signature: response.razorpay_signature,
            booking_total: bookingAmount,
            food_packages: packages,
            food_items: items
          });
        },
        modal: {
          ondismiss: () => {
            setIsProcessingPayment(false);
            setIsSubmittingBooking(false);
            setPaymentPendingBooking({
              bookingId,
              bookingRef,
              amount: bookingAmount,
              razorpayOrderId
            });
            setBookingError('Payment was not completed. Your room reservation is saved as pending. You can complete payment now or later in My Bookings.');
          }
        }
      };

      const rzp = new window.Razorpay(options);
      rzp.on('payment.failed', (response) => {
        setIsProcessingPayment(false);
        setIsSubmittingBooking(false);
        const failureReason = response.error?.description || response.error?.reason || 'Payment failed.';
        setBookingError(`Payment failed: ${failureReason}. You can retry payment below.`);
        setPaymentPendingBooking({
          bookingId,
          bookingRef,
          amount: bookingAmount,
          razorpayOrderId
        });
      });

      rzp.open();
    } catch (err) {
      setBookingError(err.message || 'An unexpected error occurred while launching payment.');
      setPaymentPendingBooking({
        bookingId,
        bookingRef,
        amount: bookingAmount
      });
      setIsProcessingPayment(false);
      setIsSubmittingBooking(false);
    }
  };

  // Handle Room Booking Submission -> Initiate Payment
  const handleConfirmBooking = async (e) => {
    e.preventDefault();
    setBookingError(null);

    if (!checkInDate || !checkOutDate) {
      setBookingError('Please specify both Check-in and Check-out dates.');
      return;
    }

    if (checkOutDate <= checkInDate) {
      setBookingError('Check-out date must be after check-in date.');
      return;
    }

    if (!isCustomerLoggedIn) {
      setBookingError('Please log in with your customer account to complete room booking.');
      return;
    }

    setIsSubmittingBooking(true);
    try {
      // Build compliant backend payload
      const payload = {
        check_in_date: checkInDate,
        check_out_date: checkOutDate,
        total_guests: Number(totalGuests) || 1,
        rooms: [{ room_id: Number(roomId) }],
        ...(specialRequest.trim() ? { special_request: specialRequest.trim() } : {}),

        // Food Packages
        food_packages: selectedPackages.map((pkg) => ({
          package_id: Number(pkg.package_id),
          quantity: Number(pkg.quantity || 1),
          ...(pkg.notes && pkg.notes.trim() ? { notes: pkg.notes.trim() } : {})
        })),

        // Food Items
        food_items: selectedFoodItems.map((item) => ({
          food_item_id: Number(item.food_item_id),
          quantity: Number(item.quantity || 1),
          ...(item.notes && item.notes.trim() ? { notes: item.notes.trim() } : {})
        }))
      };

      const res = await bookingApi.create(payload);
      if (res.success && res.data?.status !== false) {
        const bookingData = res.data?.data?.booking || res.data?.data || res.data || {};
        const newBookingId = bookingData.id || res.data?.booking_id || res.data?.data?.booking_id;
        const newBookingRef = bookingData.booking_reference || res.data?.booking_reference || `Ref #${newBookingId}`;
        const newBookingTotal = bookingData.pricing?.total_amount || res.data?.pricing?.total_amount || finalTotalAmount;

        // Directly chain into Razorpay order creation and checkout
        await initiateRazorpayPayment({
          bookingId: newBookingId,
          bookingRef: newBookingRef,
          bookingAmount: newBookingTotal,
          packages: selectedPackages,
          items: selectedFoodItems
        });
      } else {
        setBookingError(
          res.error || res.data?.message || 'Failed to complete booking. Please verify dates and try again.'
        );
        setIsSubmittingBooking(false);
      }
    } catch (err) {
      setBookingError(err.message || 'An unexpected error occurred during booking.');
      setIsSubmittingBooking(false);
    }
  };

  // Retry payment for a pending reservation
  const handleRetryPendingPayment = () => {
    if (!paymentPendingBooking) return;
    initiateRazorpayPayment({
      bookingId: paymentPendingBooking.bookingId,
      bookingRef: paymentPendingBooking.bookingRef,
      bookingAmount: paymentPendingBooking.amount,
      packages: selectedPackages,
      items: selectedFoodItems
    });
  };

  return (
    <div className="hall-details">
      <Header onNavigate={onNavigate} currentPage="/lodge" />

      <main className="hall-details__inner">
        {/* Back Link */}
        <button
          type="button"
          className="hall-details__back-btn"
          onClick={() => onNavigate('/lodge')}
        >
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round">
            <line x1="19" y1="12" x2="5" y2="12" />
            <polyline points="12 19 5 12 12 5" />
          </svg>
          <span>Back to Lodge & Rooms</span>
        </button>

        {/* Loading State */}
        {loading && (
          <div className="hall-details__loading">
            <div className="hall-details__spinner" />
            <p style={{ fontSize: '1.05rem', fontWeight: 600 }}>Loading Room Details...</p>
          </div>
        )}

        {/* Error State */}
        {!loading && error && (
          <div className="hall-details__error-state">
            <span style={{ fontSize: '2.5rem', display: 'block', marginBottom: '12px' }}>⚠️</span>
            <h2 style={{ color: '#dc2626', marginBottom: '8px', fontFamily: 'var(--font-sans)' }}>Unable to Load Room</h2>
            <p style={{ color: 'var(--text-muted)', marginBottom: '24px' }}>{error}</p>
            <button type="button" className="lodge-btn-book" style={{ padding: '12px 28px' }} onClick={() => onNavigate('/lodge')}>
              Return to Lodge Catalog
            </button>
          </div>
        )}

        {/* 2-Column Layout */}
        {!loading && !error && room && (
          <div className="hall-details__grid">
            {/* LEFT COLUMN: Showcase, Details & Scalable Food Selection */}
            <div>
              {/* Primary Image or Clean Image-Free State */}
              {currentMainImg ? (
                <div className="hall-details__showcase">
                  <img
                    src={currentMainImg}
                    alt={room.name || `Room ${room.room_number}`}
                  />
                  {room.room_type?.name && (
                    <span className="hall-details__showcase-badge">
                      {room.room_type.name}
                    </span>
                  )}
                </div>
              ) : (
                <div className="hall-details__showcase" style={{ minHeight: '320px', display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center', background: 'linear-gradient(135deg, #fdfbf7 0%, #f5efe6 100%)', border: '1px solid rgba(212, 160, 23, 0.25)', borderRadius: '18px', padding: '32px', textAlign: 'center', position: 'relative' }}>
                  <div style={{ width: '60px', height: '60px', borderRadius: '50%', background: '#ffffff', border: '1.5px solid rgba(212, 160, 23, 0.3)', display: 'flex', alignItems: 'center', justifyContent: 'center', color: '#d4a017', marginBottom: '14px', boxShadow: '0 8px 20px rgba(212, 160, 23, 0.12)' }}>
                    <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round">
                      <path d="M2 4v16M2 8h18a2 2 0 0 1 2 2v10M2 17h20M6 8v9" />
                    </svg>
                  </div>
                  <h3 style={{ fontFamily: 'var(--font-sans)', fontSize: '1.4rem', fontWeight: 700, color: 'var(--text-primary)', margin: '0 0 6px' }}>{room.name || `Room #${room.room_number}`}</h3>
                  <p style={{ color: 'var(--text-muted)', fontSize: '0.88rem', margin: 0 }}>SM Lodge Accommodation</p>
                  {room.room_type?.name && (
                    <span className="hall-details__showcase-badge" style={{ position: 'absolute', top: '16px', left: '16px' }}>
                      {room.room_type.name}
                    </span>
                  )}
                </div>
              )}

              {/* Gallery Thumbnails */}
              {galleryImages.length > 1 && (
                <div className="hall-details__thumbs">
                  {galleryImages.map((thumb, idx) => (
                    <button
                      key={idx}
                      type="button"
                      className={`hall-details__thumb-btn ${selectedImageIndex === idx ? 'hall-details__thumb-btn--active' : ''}`}
                      onClick={() => setSelectedImageIndex(idx)}
                    >
                      <img src={thumb} alt={`View ${idx + 1}`} />
                    </button>
                  ))}
                </div>
              )}

              {/* Title & Pricing */}
              <div className="hall-details__title-area">
                <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: '12px', flexWrap: 'wrap' }}>
                  <h1 className="hall-details__name" style={{ margin: 0 }}>{room.name || `Room ${room.room_number}`}</h1>
                  <span style={{ background: '#f8fafc', border: '1px solid #e2e8f0', borderRadius: '8px', padding: '4px 12px', fontSize: '0.85rem', fontWeight: 700, color: 'var(--text-secondary)' }}>
                    Room #{room.room_number}
                  </span>
                </div>
                <div className="hall-details__price-row">
                  <span className="hall-details__price">
                    ₹{Number(room.price_per_night).toLocaleString('en-IN')}
                  </span>
                  <span className="hall-details__price-note">
                    / night {room.price_includes_gst ? '(GST Included)' : `(+ ${room.gst_rate || 12}% GST)`}
                  </span>
                </div>
              </div>

              {/* 4 Feature Highlight Cards */}
              <div className="hall-details__highlights">
                {/* Capacity */}
                <div className="hall-details__highlight-card">
                  <div className="hall-details__highlight-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                      <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                      <circle cx="9" cy="7" r="4" />
                      <path d="M23 21v-2a4 4 0 0 0-3-3.87" />
                      <path d="M16 3.13a4 4 0 0 1 0 7.75" />
                    </svg>
                  </div>
                  <span className="hall-details__highlight-label">Guest Capacity</span>
                  <span className="hall-details__highlight-value">Up to {room.guest_capacity} Guests</span>
                </div>

                {/* Bed Setup */}
                <div className="hall-details__highlight-card">
                  <div className="hall-details__highlight-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                      <path d="M2 4v16M2 8h18a2 2 0 0 1 2 2v10M2 17h20M6 8v9" />
                    </svg>
                  </div>
                  <span className="hall-details__highlight-label">Bed Setup</span>
                  <span className="hall-details__highlight-value">{room.bed_count} Bed(s)</span>
                </div>

                {/* Room Type */}
                <div className="hall-details__highlight-card">
                  <div className="hall-details__highlight-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                      <rect x="3" y="3" width="18" height="18" rx="4" />
                      <path d="M9 9h6v6H9z" />
                    </svg>
                  </div>
                  <span className="hall-details__highlight-label">Category</span>
                  <span className="hall-details__highlight-value">{room.room_type?.name || 'Standard'}</span>
                </div>

                {/* Availability */}
                <div className="hall-details__highlight-card">
                  <div className="hall-details__highlight-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                      <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
                      <polyline points="22 4 12 14.01 9 11.01" />
                    </svg>
                  </div>
                  <span className="hall-details__highlight-label">Status</span>
                  <span className="hall-details__highlight-value">
                    {room.status === 'available' ? '✓ Available to Book' : room.status}
                  </span>
                </div>
              </div>

              {/* Description */}
              {(room.description || room.room_type?.short_description) && (
                <div className="hall-details__desc-block">
                  <h3 className="hall-details__section-title">Room Overview & Details</h3>
                  <p className="hall-details__desc-text">{room.description || room.room_type?.short_description}</p>
                </div>
              )}

              {/* Amenities Grid */}
              {Array.isArray(room.amenities) && room.amenities.length > 0 && (
                <div className="hall-details__facilities-block">
                  <h3 className="hall-details__section-title">
                    Room Amenities & Inclusions ({room.amenities.length})
                  </h3>
                  <div className="hall-details__facilities-grid">
                    {room.amenities.map((amenity, i) => {
                      const name = typeof amenity === 'string' ? amenity : amenity.name;
                      const iconName = typeof amenity === 'object' ? amenity.icon : '';
                      return (
                        <div key={i} className="hall-details__facility-item">
                          <span className="hall-details__facility-icon">
                            {getAmenityIcon(iconName, name)}
                          </span>
                          <span className="hall-details__facility-name">{name}</span>
                        </div>
                      );
                    })}
                  </div>
                </div>
              )}

              {/* SCALABLE ROOM FOOD & DINING INCLUSIONS GRID (15–20+ Items) */}
              <div className="room-food-section" id="room-food-section">
                <div className="room-food-section__header">
                  <div>
                    <h3 className="room-food-section__title">
                      <span>🍽️</span> Dining & Meal Inclusions (Optional)
                    </h3>
                    <p style={{ color: 'var(--text-muted)', fontSize: '0.82rem', margin: '4px 0 0' }}>
                      Add breakfast combos or special restaurant dishes to your room reservation
                    </p>
                  </div>
                  {totalFoodSelectedCount > 0 && (
                    <span className="room-food-section__badge">
                      {totalFoodSelectedCount} Attached
                    </span>
                  )}
                </div>

                {/* Category Navigation Tabs */}
                <div className="room-food-tabs">
                  <button
                    type="button"
                    className={`room-food-tab-btn ${activeFoodTab === 'packages' ? 'room-food-tab-btn--active' : ''}`}
                    onClick={() => setActiveFoodTab('packages')}
                  >
                    🍱 Room Packages ({availablePackages.length})
                  </button>
                  <button
                    type="button"
                    className={`room-food-tab-btn ${activeFoodTab === 'items' ? 'room-food-tab-btn--active' : ''}`}
                    onClick={() => setActiveFoodTab('items')}
                  >
                    🍲 A La Carte Menu ({availableFoodItems.length})
                  </button>
                  {totalFoodSelectedCount > 0 && (
                    <button
                      type="button"
                      className={`room-food-tab-btn ${activeFoodTab === 'selected' ? 'room-food-tab-btn--active' : ''}`}
                      onClick={() => setActiveFoodTab('selected')}
                      style={{
                        marginLeft: 'auto',
                        background: activeFoodTab === 'selected' ? '#15803d' : '#f0fdf4',
                        color: activeFoodTab === 'selected' ? '#ffffff' : '#166534',
                        borderColor: '#bbf7d0'
                      }}
                    >
                      ✓ Selected Inclusions ({totalFoodSelectedCount})
                    </button>
                  )}
                </div>

                {/* Toolbar Search & Filters */}
                {activeFoodTab === 'packages' && (
                  <div className="room-food-toolbar">
                    <input
                      type="text"
                      className="room-food-search"
                      placeholder="Search meal packages..."
                      value={packageSearch}
                      onChange={(e) => setPackageSearch(e.target.value)}
                    />
                    <select
                      className="room-food-select"
                      value={packageMealTypeFilter}
                      onChange={(e) => setPackageMealTypeFilter(e.target.value)}
                    >
                      <option value="all">All Meal Types</option>
                      <option value="breakfast">Breakfast</option>
                      <option value="lunch">Lunch</option>
                      <option value="dinner">Dinner</option>
                      <option value="combo">Combo</option>
                      <option value="other">Other</option>
                    </select>
                  </div>
                )}

                {activeFoodTab === 'items' && (
                  <div className="room-food-toolbar">
                    <input
                      type="text"
                      className="room-food-search"
                      placeholder="Search dishes or categories..."
                      value={foodItemSearch}
                      onChange={(e) => setFoodItemSearch(e.target.value)}
                    />
                    <select
                      className="room-food-select"
                      value={foodTypeFilter}
                      onChange={(e) => setFoodTypeFilter(e.target.value)}
                    >
                      <option value="all">All Food Types</option>
                      <option value="veg">🌱 Vegetarian</option>
                      <option value="non_veg">🍗 Non-Veg</option>
                      <option value="beverage">☕ Beverages</option>
                      <option value="dessert">🍰 Desserts</option>
                      <option value="other">🍽️ Other</option>
                    </select>
                  </div>
                )}

                {/* Content Grid Display */}
                {loadingFood ? (
                  <div style={{ textAlign: 'center', padding: '30px 0', color: 'var(--text-muted)' }}>
                    <div className="hall-details__spinner" style={{ width: '28px', height: '28px', margin: '0 auto 10px' }} />
                    <span style={{ fontSize: '0.85rem' }}>Loading food choices...</span>
                  </div>
                ) : activeFoodTab === 'packages' ? (
                  /* PACKAGES GRID (3-4 desktop, 2 tablet, 1 mobile) */
                  filteredPackages.length === 0 ? (
                    <div style={{ textAlign: 'center', padding: '30px 16px', background: '#faf8f5', borderRadius: '12px', border: '1px dashed #e2e8f0' }}>
                      <p style={{ margin: 0, color: 'var(--text-muted)', fontSize: '0.86rem' }}>
                        No meal packages match your search filter.
                      </p>
                    </div>
                  ) : (
                    <div className="room-food-grid">
                      {filteredPackages.map((pkg) => {
                        const selected = selectedPackages.find((p) => p.package_id === pkg.id);
                        const mealClass = `room-food-card__badge--${pkg.meal_type || 'combo'}`;
                        return (
                          <div
                            key={pkg.id}
                            className={`room-food-card ${selected ? 'room-food-card--selected' : ''}`}
                          >
                            <div>
                              <div className="room-food-card__header">
                                <h4 className="room-food-card__title">{pkg.name}</h4>
                                {pkg.meal_type && (
                                  <span className={`room-food-card__badge ${mealClass}`}>
                                    {pkg.meal_type}
                                  </span>
                                )}
                              </div>

                              {pkg.description && (
                                <p className="room-food-card__desc">{pkg.description}</p>
                              )}

                              {Array.isArray(pkg.items) && pkg.items.length > 0 && (
                                <div className="room-food-card__inclusions" title={pkg.items.map((i) => i.food_item_name).join(', ')}>
                                  🍱 {pkg.items.map((i) => i.food_item_name).join(', ')}
                                </div>
                              )}
                            </div>

                            <div className="room-food-card__actions">
                              {!selected ? (
                                <button
                                  type="button"
                                  className="room-food-add-btn"
                                  onClick={() => handleTogglePackage(pkg)}
                                >
                                  + Add Package
                                </button>
                              ) : (
                                <div>
                                  <div className="room-food-stepper-row">
                                    <div className="room-food-stepper">
                                      <button
                                        type="button"
                                        className="room-food-step-btn"
                                        onClick={() => handleUpdatePackageQuantity(pkg.id, -1)}
                                      >
                                        -
                                      </button>
                                      <span className="room-food-step-value">{selected.quantity || 1}</span>
                                      <button
                                        type="button"
                                        className="room-food-step-btn"
                                        onClick={() => handleUpdatePackageQuantity(pkg.id, 1)}
                                      >
                                        +
                                      </button>
                                    </div>
                                    <button
                                      type="button"
                                      className="room-food-remove-btn"
                                      onClick={() => handleTogglePackage(pkg)}
                                    >
                                      ✕ Remove
                                    </button>
                                  </div>
                                  <input
                                    type="text"
                                    className="room-food-notes-input"
                                    placeholder="Notes (e.g. Serve at 8:30 AM)"
                                    value={selected.notes || ''}
                                    onChange={(e) => handleUpdatePackageNotes(pkg.id, e.target.value)}
                                  />
                                </div>
                              )}
                            </div>
                          </div>
                        );
                      })}
                    </div>
                  )
                ) : activeFoodTab === 'items' ? (
                  /* DISHES GRID (3-4 desktop, 2 tablet, 1 mobile) */
                  filteredFoodItems.length === 0 ? (
                    <div style={{ textAlign: 'center', padding: '30px 16px', background: '#faf8f5', borderRadius: '12px', border: '1px dashed #e2e8f0' }}>
                      <p style={{ margin: 0, color: 'var(--text-muted)', fontSize: '0.86rem' }}>
                        No food items match your search filter.
                      </p>
                    </div>
                  ) : (
                    <div className="room-food-grid">
                      {filteredFoodItems.map((item) => {
                        const selected = selectedFoodItems.find((i) => i.food_item_id === item.id);
                        const isVeg = item.food_type === 'veg';
                        return (
                          <div
                            key={item.id}
                            className={`room-food-card ${selected ? 'room-food-card--selected' : ''}`}
                          >
                            <div>
                              <div className="room-food-card__header">
                                <h4 className="room-food-card__title">{item.name}</h4>
                                <span className={`room-food-card__badge ${isVeg ? 'room-food-card__badge--veg' : 'room-food-card__badge--nonveg'}`}>
                                  {isVeg ? '🌱 Veg' : '🍗 Non-Veg'}
                                </span>
                              </div>

                              {item.category_name && (
                                <div style={{ fontSize: '0.72rem', color: 'var(--text-muted)', marginBottom: '4px', fontWeight: 600 }}>
                                  {item.category_name}
                                </div>
                              )}

                              {item.description && (
                                <p className="room-food-card__desc">{item.description}</p>
                              )}
                            </div>

                            <div className="room-food-card__actions">
                              {!selected ? (
                                <button
                                  type="button"
                                  className="room-food-add-btn"
                                  onClick={() => handleToggleFoodItem(item)}
                                >
                                  + Add Dish
                                </button>
                              ) : (
                                <div>
                                  <div className="room-food-stepper-row">
                                    <div className="room-food-stepper">
                                      <button
                                        type="button"
                                        className="room-food-step-btn"
                                        onClick={() => handleUpdateFoodItemQuantity(item.id, -1)}
                                      >
                                        -
                                      </button>
                                      <span className="room-food-step-value">{selected.quantity || 1}</span>
                                      <button
                                        type="button"
                                        className="room-food-step-btn"
                                        onClick={() => handleUpdateFoodItemQuantity(item.id, 1)}
                                      >
                                        +
                                      </button>
                                    </div>
                                    <button
                                      type="button"
                                      className="room-food-remove-btn"
                                      onClick={() => handleToggleFoodItem(item)}
                                    >
                                      ✕ Remove
                                    </button>
                                  </div>
                                  <input
                                    type="text"
                                    className="room-food-notes-input"
                                    placeholder="Notes (e.g. Less spicy, extra sauce)"
                                    value={selected.notes || ''}
                                    onChange={(e) => handleUpdateFoodItemNotes(item.id, e.target.value)}
                                  />
                                </div>
                              )}
                            </div>
                          </div>
                        );
                      })}
                    </div>
                  )
                ) : (
                  /* ACTIVE SELECTIONS VIEW */
                  <div>
                    <div style={{ display: 'flex', flexDirection: 'column', gap: '10px' }}>
                      {selectedPackages.map((pkg) => (
                        <div key={pkg.package_id} style={{ background: '#fdfbf7', border: '1px solid var(--gold-500)', borderRadius: '10px', padding: '12px 14px', display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: '10px', flexWrap: 'wrap' }}>
                          <div>
                            <strong style={{ fontSize: '0.9rem', color: 'var(--text-primary)' }}>🍱 {pkg.name}</strong>
                            <span style={{ fontSize: '0.75rem', color: 'var(--text-muted)', display: 'block' }}>Package Inclusions</span>
                          </div>
                          <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                            <div className="room-food-stepper">
                              <button type="button" className="room-food-step-btn" onClick={() => handleUpdatePackageQuantity(pkg.package_id, -1)}>-</button>
                              <span className="room-food-step-value">{pkg.quantity}</span>
                              <button type="button" className="room-food-step-btn" onClick={() => handleUpdatePackageQuantity(pkg.package_id, 1)}>+</button>
                            </div>
                            <button type="button" className="room-food-remove-btn" onClick={() => handleTogglePackage({ id: pkg.package_id })}>✕</button>
                          </div>
                        </div>
                      ))}
                      {selectedFoodItems.map((item) => (
                        <div key={item.food_item_id} style={{ background: '#f0fdf4', border: '1px solid #86efac', borderRadius: '10px', padding: '12px 14px', display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: '10px', flexWrap: 'wrap' }}>
                          <div>
                            <strong style={{ fontSize: '0.9rem', color: 'var(--text-primary)' }}>🍲 {item.name}</strong>
                            <span style={{ fontSize: '0.75rem', color: 'var(--text-muted)', display: 'block' }}>{item.category_name || 'A La Carte'}</span>
                          </div>
                          <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                            <div className="room-food-stepper">
                              <button type="button" className="room-food-step-btn" onClick={() => handleUpdateFoodItemQuantity(item.food_item_id, -1)}>-</button>
                              <span className="room-food-step-value">{item.quantity}</span>
                              <button type="button" className="room-food-step-btn" onClick={() => handleUpdateFoodItemQuantity(item.food_item_id, 1)}>+</button>
                            </div>
                            <button type="button" className="room-food-remove-btn" onClick={() => handleToggleFoodItem({ id: item.food_item_id })}>✕</button>
                          </div>
                        </div>
                      ))}
                    </div>
                  </div>
                )}

                {/* Active Inclusions Summary Pill Bar */}
                {totalFoodSelectedCount > 0 && (
                  <div className="room-food-summary-pills">
                    {selectedPackages.map((pkg) => (
                      <span key={pkg.package_id} className="room-food-pill room-food-pill--package">
                        🍱 {pkg.name} ×{pkg.quantity}
                        <button
                          type="button"
                          className="room-food-pill-remove"
                          onClick={() => handleTogglePackage({ id: pkg.package_id })}
                        >
                          ✕
                        </button>
                      </span>
                    ))}
                    {selectedFoodItems.map((item) => (
                      <span key={item.food_item_id} className="room-food-pill room-food-pill--item">
                        🍲 {item.name} ×{item.quantity}
                        <button
                          type="button"
                          className="room-food-pill-remove"
                          onClick={() => handleToggleFoodItem({ id: item.food_item_id })}
                        >
                          ✕
                        </button>
                      </span>
                    ))}
                  </div>
                )}
              </div>
            </div>

            {/* RIGHT COLUMN: Reservation & Booking Card */}
            <div className="hall-details__enquiry-wrapper">
              <div className="hall-details__enquiry-card">
                <h2 className="hall-details__enquiry-title">Reserve Your Stay</h2>

                {bookingSuccess ? (
                  <div className="hall-details__success">
                    <div className="hall-details__success-icon">
                      <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5">
                        <polyline points="20 6 9 17 4 12" />
                      </svg>
                    </div>
                    <h3 className="hall-details__success-title">Room Booking Confirmed!</h3>
                    <p className="hall-details__success-msg">{bookingSuccess.message}</p>
                    <div className="hall-details__ref-box">
                      <span className="hall-details__ref-label">Booking Reference</span>
                      <span className="hall-details__ref-value">{bookingSuccess.reference}</span>
                    </div>

                    {/* Payment Receipt Badge */}
                    <div style={{ background: '#f0fdf4', border: '1px solid #bbf7d0', borderRadius: '10px', padding: '10px 14px', marginBottom: '16px', textAlign: 'left', fontSize: '0.82rem' }}>
                      <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: '4px' }}>
                        <span style={{ color: '#166534', fontWeight: 700 }}>✓ Payment Verified & Paid</span>
                        <span style={{ color: '#15803d', fontSize: '0.75rem', fontWeight: 600 }}>{bookingSuccess.payment_method}</span>
                      </div>
                      {bookingSuccess.razorpay_payment_id && (
                        <div style={{ color: '#166534', fontSize: '0.76rem', wordBreak: 'break-all' }}>
                          Payment ID: <strong>{bookingSuccess.razorpay_payment_id}</strong>
                        </div>
                      )}
                    </div>

                    <div style={{ background: '#f8fafc', borderRadius: '10px', padding: '14px', marginBottom: '16px', border: '1px solid #e2e8f0', textAlign: 'left', fontSize: '0.85rem' }}>
                      <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: '6px' }}>
                        <span style={{ color: 'var(--text-muted)' }}>Check-in:</span>
                        <strong>{checkInDate}</strong>
                      </div>
                      <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: '6px' }}>
                        <span style={{ color: 'var(--text-muted)' }}>Check-out:</span>
                        <strong>{checkOutDate}</strong>
                      </div>
                      <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: '6px' }}>
                        <span style={{ color: 'var(--text-muted)' }}>Duration:</span>
                        <strong>{numberOfNights} Night(s)</strong>
                      </div>
                      <div style={{ display: 'flex', justifyContent: 'space-between', borderTop: '1px solid #e2e8f0', paddingTop: '6px' }}>
                        <span style={{ color: 'var(--text-primary)', fontWeight: 700 }}>Paid Amount:</span>
                        <strong style={{ color: 'var(--gold-600)', fontSize: '1rem' }}>₹{Number(bookingSuccess.total_amount).toLocaleString('en-IN')}</strong>
                      </div>
                    </div>

                    {/* Attached Food Inclusions in Confirmation */}
                    {((bookingSuccess.food_packages && bookingSuccess.food_packages.length > 0) || (bookingSuccess.food_items && bookingSuccess.food_items.length > 0)) && (
                      <div style={{ background: '#fcfaf5', borderRadius: '10px', padding: '12px 14px', marginBottom: '20px', border: '1px solid rgba(212, 160, 23, 0.3)', textAlign: 'left', fontSize: '0.82rem' }}>
                        <strong style={{ color: '#b45309', display: 'block', marginBottom: '8px' }}>🍽️ Attached Food & Dining Preferences</strong>
                        {bookingSuccess.food_packages && bookingSuccess.food_packages.map((pkg, idx) => (
                          <div key={idx} style={{ display: 'flex', justifyContent: 'space-between', marginBottom: '4px', color: 'var(--text-primary)' }}>
                            <span>🍱 {pkg.name} × {pkg.quantity}</span>
                            {pkg.notes && <span style={{ color: 'var(--text-muted)', fontSize: '0.75rem', fontStyle: 'italic' }}>({pkg.notes})</span>}
                          </div>
                        ))}
                        {bookingSuccess.food_items && bookingSuccess.food_items.map((item, idx) => (
                          <div key={idx} style={{ display: 'flex', justifyContent: 'space-between', marginBottom: '4px', color: 'var(--text-primary)' }}>
                            <span>🍲 {item.name} × {item.quantity}</span>
                            {item.notes && <span style={{ color: 'var(--text-muted)', fontSize: '0.75rem', fontStyle: 'italic' }}>({item.notes})</span>}
                          </div>
                        ))}
                      </div>
                    )}

                    <div style={{ display: 'flex', flexDirection: 'column', gap: '8px' }}>
                      <button
                        type="button"
                        className="lodge-btn-view"
                        style={{ width: '100%', padding: '12px' }}
                        onClick={() => onNavigate('/my-bookings')}
                      >
                        View in My Bookings
                      </button>
                      <button
                        type="button"
                        className="lodge-btn-details"
                        style={{ width: '100%', padding: '10px' }}
                        onClick={() => onNavigate('/lodge')}
                      >
                        Explore More Rooms
                      </button>
                    </div>
                  </div>
                ) : paymentPendingBooking ? (
                  <div style={{ background: '#fffbeb', border: '1px solid #fde68a', borderRadius: '12px', padding: '18px', textAlign: 'center' }}>
                    <div style={{ width: '48px', height: '48px', borderRadius: '50%', background: '#fef3c7', color: '#d97706', display: 'flex', alignItems: 'center', justifyContent: 'center', margin: '0 auto 12px', fontSize: '1.4rem' }}>
                      ⏳
                    </div>
                    <h3 style={{ fontSize: '1.05rem', fontWeight: 800, color: '#92400e', marginBottom: '6px' }}>
                      Booking Reserved (Pending Payment)
                    </h3>
                    <p style={{ fontSize: '0.83rem', color: '#78350f', marginBottom: '14px', lineHeight: 1.45 }}>
                      Reference <strong>{paymentPendingBooking.bookingRef}</strong> has been created. Complete your online payment via Razorpay to confirm your stay.
                    </p>

                    <div style={{ background: '#ffffff', borderRadius: '8px', padding: '12px', border: '1px solid #fef3c7', marginBottom: '16px', textAlign: 'left', fontSize: '0.84rem' }}>
                      <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: '4px' }}>
                        <span style={{ color: 'var(--text-muted)' }}>Amount Payable:</span>
                        <strong style={{ color: 'var(--gold-600)', fontSize: '0.98rem' }}>₹{Number(paymentPendingBooking.amount).toLocaleString('en-IN')}</strong>
                      </div>
                      <div style={{ display: 'flex', justifyContent: 'space-between' }}>
                        <span style={{ color: 'var(--text-muted)' }}>Payment Gateway:</span>
                        <strong>Razorpay Secure</strong>
                      </div>
                    </div>

                    {bookingError && (
                      <div className="hall-details__error-banner" style={{ marginBottom: '14px', textAlign: 'left' }}>
                        {bookingError}
                      </div>
                    )}

                    <div style={{ display: 'flex', flexDirection: 'column', gap: '8px' }}>
                      <button
                        type="button"
                        disabled={isProcessingPayment || isSubmittingBooking}
                        className="hall-details__submit-btn"
                        onClick={handleRetryPendingPayment}
                        style={{ margin: 0 }}
                      >
                        {isProcessingPayment ? 'Connecting to Razorpay...' : `Pay Now (₹${Number(paymentPendingBooking.amount).toLocaleString('en-IN')})`}
                      </button>
                      <button
                        type="button"
                        className="lodge-btn-view"
                        style={{ width: '100%', padding: '10px' }}
                        onClick={() => onNavigate('/my-bookings')}
                      >
                        View in My Bookings
                      </button>
                      <button
                        type="button"
                        style={{ background: 'transparent', border: 'none', color: 'var(--text-muted)', fontSize: '0.78rem', cursor: 'pointer', textDecoration: 'underline', marginTop: '4px' }}
                        onClick={() => {
                          setPaymentPendingBooking(null);
                          setBookingError(null);
                        }}
                      >
                        Create another reservation
                      </button>
                    </div>
                  </div>
                ) : (
                  <form onSubmit={handleConfirmBooking} noValidate>
                    {bookingError && (
                      <div className="hall-details__error-banner">{bookingError}</div>
                    )}

                    {/* Customer Info Pill */}
                    {isCustomerLoggedIn ? (
                      <div style={{ background: '#f0fdf4', border: '1px solid #bbf7d0', borderRadius: '10px', padding: '10px 14px', marginBottom: '16px', display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                        <div>
                          <span style={{ fontSize: '0.72rem', color: '#15803d', textTransform: 'uppercase', letterSpacing: '0.04em', display: 'block', fontWeight: 700 }}>
                            Booking as Customer
                          </span>
                          <strong style={{ fontSize: '0.88rem', color: '#166534' }}>{customerSession?.user?.name || 'Customer'}</strong>
                        </div>
                        <span style={{ fontSize: '0.78rem', color: '#15803d', background: '#dcfce7', padding: '2px 8px', borderRadius: '6px', fontWeight: 600 }}>
                          Verified
                        </span>
                      </div>
                    ) : (
                      <div style={{ background: '#fffbeb', border: '1px solid #fef3c7', borderRadius: '10px', padding: '12px 14px', marginBottom: '16px' }}>
                        <div style={{ display: 'flex', alignItems: 'flex-start', gap: '8px' }}>
                          <span style={{ color: '#d97706', fontSize: '1rem' }}>ℹ️</span>
                          <div>
                            <span style={{ fontSize: '0.84rem', color: '#92400e', display: 'block', lineHeight: '1.4' }}>
                              Customer sign-in is required to secure your room booking.
                            </span>
                            <button
                              type="button"
                              onClick={() => onNavigate('/login')}
                              style={{ background: 'none', border: 'none', color: '#b45309', fontWeight: 700, fontSize: '0.82rem', padding: 0, textDecoration: 'underline', cursor: 'pointer', marginTop: '4px' }}
                            >
                              Click here to Sign In →
                            </button>
                          </div>
                        </div>
                      </div>
                    )}

                    {/* Check-in & Check-out Dates */}
                    <div className="hall-details__field-row">
                      <div>
                        <label className="hall-details__field-label">Check-In *</label>
                        <DatePicker
                          min={getTodayDateString()}
                          value={checkInDate}
                          onChange={(e) => setCheckInDate(e.target.value)}
                          className="hall-details__field-input"
                          placeholder="Select Check-in"
                          required
                        />
                      </div>
                      <div>
                        <label className="hall-details__field-label">Check-Out *</label>
                        <DatePicker
                          min={checkInDate || getTodayDateString()}
                          value={checkOutDate}
                          onChange={(e) => setCheckOutDate(e.target.value)}
                          className="hall-details__field-input"
                          placeholder="Select Check-out"
                          required
                        />
                      </div>
                    </div>

                    {/* Guests */}
                    <div className="hall-details__field">
                      <label className="hall-details__field-label">Total Guests *</label>
                      <select
                        value={totalGuests}
                        onChange={(e) => setTotalGuests(Number(e.target.value))}
                        className="hall-details__field-select"
                      >
                        {Array.from({ length: Math.max(room.guest_capacity || 4, 1) }, (_, i) => i + 1).map((n) => (
                          <option key={n} value={n}>
                            {n} {n === 1 ? 'Guest' : 'Guests'}
                          </option>
                        ))}
                      </select>
                    </div>

                    {/* Special Requests */}
                    <div className="hall-details__field">
                      <label className="hall-details__field-label">Special Request / Notes</label>
                      <textarea
                        rows={2}
                        placeholder="e.g. Early check-in, ground floor room request, extra bed query..."
                        value={specialRequest}
                        onChange={(e) => setSpecialRequest(e.target.value)}
                        className="hall-details__field-textarea"
                      />
                    </div>

                    {/* Food Inclusions Live Summary in Booking Card */}
                    <div style={{ background: totalFoodSelectedCount > 0 ? '#fffdf7' : '#f8fafc', border: totalFoodSelectedCount > 0 ? '1px solid var(--gold-500)' : '1px solid #e2e8f0', borderRadius: '10px', padding: '12px', marginBottom: '16px' }}>
                      <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: totalFoodSelectedCount > 0 ? '8px' : 0 }}>
                        <div style={{ display: 'flex', alignItems: 'center', gap: '6px' }}>
                          <span style={{ fontSize: '1rem' }}>🍽️</span>
                          <strong style={{ fontSize: '0.84rem', color: 'var(--text-primary)' }}>Dining Inclusions</strong>
                        </div>
                        {totalFoodSelectedCount > 0 ? (
                          <span style={{ fontSize: '0.75rem', background: 'var(--gold-500)', color: '#ffffff', padding: '2px 8px', borderRadius: '12px', fontWeight: 700 }}>
                            {totalFoodSelectedCount} Selected
                          </span>
                        ) : (
                          <span style={{ fontSize: '0.74rem', color: 'var(--text-muted)' }}>None attached</span>
                        )}
                      </div>

                      {totalFoodSelectedCount > 0 ? (
                        <div style={{ display: 'flex', flexDirection: 'column', gap: '4px', fontSize: '0.78rem', color: 'var(--text-secondary)' }}>
                          {selectedPackages.map((pkg) => (
                            <div key={pkg.package_id} style={{ display: 'flex', justifyContent: 'space-between' }}>
                              <span>🍱 {pkg.name}</span>
                              <strong>× {pkg.quantity}</strong>
                            </div>
                          ))}
                          {selectedFoodItems.map((item) => (
                            <div key={item.food_item_id} style={{ display: 'flex', justifyContent: 'space-between' }}>
                              <span>🍲 {item.name}</span>
                              <strong>× {item.quantity}</strong>
                            </div>
                          ))}
                          <a
                            href="#room-food-section"
                            style={{ color: 'var(--gold-600)', fontWeight: 700, fontSize: '0.75rem', marginTop: '4px', textDecoration: 'none', display: 'inline-block' }}
                          >
                            + Edit Food Inclusions ↓
                          </a>
                        </div>
                      ) : (
                        <div style={{ marginTop: '4px' }}>
                          <a
                            href="#room-food-section"
                            style={{ color: 'var(--gold-600)', fontWeight: 700, fontSize: '0.78rem', textDecoration: 'none' }}
                          >
                            + Add Food Packages & Dishes ↓
                          </a>
                        </div>
                      )}
                    </div>

                    {/* Live Pricing Breakdown Box */}
                    <div style={{ background: 'var(--bg-main, #f8fafc)', border: '1px solid var(--border-subtle, #e2e8f0)', borderRadius: '12px', padding: '14px', marginBottom: '18px' }}>
                      <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: '0.85rem', marginBottom: '6px' }}>
                        <span style={{ color: 'var(--text-muted)' }}>
                          ₹{pricePerNight.toLocaleString('en-IN')} × {numberOfNights > 0 ? numberOfNights : 1} Night(s)
                        </span>
                        <span>₹{staySubtotal.toLocaleString('en-IN')}</span>
                      </div>
                      <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: '0.85rem', marginBottom: '6px' }}>
                        <span style={{ color: 'var(--text-muted)' }}>
                          Taxes & GST ({gstRate}%)
                        </span>
                        <span>{isGstIncluded ? 'Included' : `+ ₹${calculatedGst.toLocaleString('en-IN')}`}</span>
                      </div>
                      <div style={{ display: 'flex', justifyContent: 'space-between', borderTop: '1px solid var(--border-subtle, #e2e8f0)', paddingTop: '8px', marginTop: '6px', fontWeight: 800 }}>
                        <span style={{ color: 'var(--text-primary)' }}>Estimated Total</span>
                        <span style={{ color: 'var(--gold-600)', fontSize: '1.08rem' }}>
                          ₹{finalTotalAmount.toLocaleString('en-IN')}
                        </span>
                      </div>
                    </div>

                    {/* Submit Button */}
                    <button
                      type="submit"
                      disabled={isSubmittingBooking || isProcessingPayment}
                      className="hall-details__submit-btn"
                    >
                      {isProcessingPayment
                        ? 'Initiating Payment...'
                        : isSubmittingBooking
                        ? 'Reserving Room...'
                        : `Proceed to Payment (₹${finalTotalAmount.toLocaleString('en-IN')})`}
                    </button>
                  </form>
                )}
              </div>
            </div>
          </div>
        )}
      </main>

      <Footer onNavigate={onNavigate} />
    </div>
  );
};

export default RoomDetailsPage;

