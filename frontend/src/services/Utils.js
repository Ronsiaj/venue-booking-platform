// Helper utilities for Cookie management, Page actions, and Role-Isolated Storage

export const AUTH_KEYS = {
    CUSTOMER: {
        ACCESS_TOKEN: 'sm_customer_access_token',
        REFRESH_TOKEN: 'sm_customer_refresh_token',
        USER: 'sm_customer_user',
    },
    ADMIN: {
        ACCESS_TOKEN: 'sm_admin_access_token',
        REFRESH_TOKEN: 'sm_admin_refresh_token',
        USER: 'sm_admin_user',
    },
    LAST_ACTIVE_ROLE: 'sm_last_active_role'
};

/**
 * Save authentication data with complete role isolation.
 * Admin/Staff and Customer sessions NEVER overwrite each other.
 * 
 * @param {object} authData - Response data from login.php: { user_id, name, email, phone, role, access_token, refresh_token }
 */
export const saveAuthSession = (authData) => {
    if (!authData || !authData.role) return;

    const role = authData.role;
    const user = {
        id: authData.user_id,
        name: authData.name,
        email: authData.email,
        phone: authData.phone,
        role: authData.role,
    };

    if (role === 'customer') {
        localStorage.setItem(AUTH_KEYS.CUSTOMER.ACCESS_TOKEN, authData.access_token || '');
        localStorage.setItem(AUTH_KEYS.CUSTOMER.REFRESH_TOKEN, authData.refresh_token || '');
        localStorage.setItem(AUTH_KEYS.CUSTOMER.USER, JSON.stringify(user));
        localStorage.setItem(AUTH_KEYS.LAST_ACTIVE_ROLE, 'customer');
    } else if (role === 'admin' || role === 'staff') {
        localStorage.setItem(AUTH_KEYS.ADMIN.ACCESS_TOKEN, authData.access_token || '');
        localStorage.setItem(AUTH_KEYS.ADMIN.REFRESH_TOKEN, authData.refresh_token || '');
        localStorage.setItem(AUTH_KEYS.ADMIN.USER, JSON.stringify(user));
        localStorage.setItem(AUTH_KEYS.LAST_ACTIVE_ROLE, role);
    }

    if (typeof window !== 'undefined') {
        window.dispatchEvent(new Event('auth-change'));
    }
};

/**
 * Retrieve active authentication session for a specific role or the latest active role.
 * 
 * @param {string} [role] - 'customer' | 'admin' | 'staff'
 */
export const getAuthSession = (role) => {
    try {
        const targetRole = role || localStorage.getItem(AUTH_KEYS.LAST_ACTIVE_ROLE) || 'customer';
        
        if (targetRole === 'customer') {
            const token = localStorage.getItem(AUTH_KEYS.CUSTOMER.ACCESS_TOKEN);
            const refreshToken = localStorage.getItem(AUTH_KEYS.CUSTOMER.REFRESH_TOKEN);
            const userStr = localStorage.getItem(AUTH_KEYS.CUSTOMER.USER);
            const user = userStr ? JSON.parse(userStr) : null;
            return {
                token: token || null,
                refreshToken: refreshToken || null,
                user,
                isAuthenticated: !!token,
                role: 'customer',
            };
        } else if (targetRole === 'admin' || targetRole === 'staff') {
            const token = localStorage.getItem(AUTH_KEYS.ADMIN.ACCESS_TOKEN);
            const refreshToken = localStorage.getItem(AUTH_KEYS.ADMIN.REFRESH_TOKEN);
            const userStr = localStorage.getItem(AUTH_KEYS.ADMIN.USER);
            const user = userStr ? JSON.parse(userStr) : null;
            return {
                token: token || null,
                refreshToken: refreshToken || null,
                user,
                isAuthenticated: !!token,
                role: user?.role || targetRole,
            };
        }

        return { token: null, refreshToken: null, user: null, isAuthenticated: false, role: null };
    } catch {
        return { token: null, refreshToken: null, user: null, isAuthenticated: false, role: null };
    }
};

/**
 * Check if a specific role is currently authenticated.
 */
export const isRoleAuthenticated = (role) => {
    if (role === 'customer') {
        return !!localStorage.getItem(AUTH_KEYS.CUSTOMER.ACCESS_TOKEN);
    }
    if (role === 'admin' || role === 'staff') {
        return !!localStorage.getItem(AUTH_KEYS.ADMIN.ACCESS_TOKEN);
    }
    return false;
};

/**
 * Clear authentication session for a specific role without affecting the other.
 */
export const clearAuthSession = (role) => {
    if (role === 'customer') {
        localStorage.removeItem(AUTH_KEYS.CUSTOMER.ACCESS_TOKEN);
        localStorage.removeItem(AUTH_KEYS.CUSTOMER.REFRESH_TOKEN);
        localStorage.removeItem(AUTH_KEYS.CUSTOMER.USER);
    } else if (role === 'admin' || role === 'staff') {
        localStorage.removeItem(AUTH_KEYS.ADMIN.ACCESS_TOKEN);
        localStorage.removeItem(AUTH_KEYS.ADMIN.REFRESH_TOKEN);
        localStorage.removeItem(AUTH_KEYS.ADMIN.USER);
    }

    if (typeof window !== 'undefined') {
        window.dispatchEvent(new Event('auth-change'));
    }
};

export const getCookie = (name) => {
    const value = `; ${document.cookie}`;
    const parts = value.split(`; ${name}=`);
    if (parts.length === 2) return parts.pop().split(';').shift();
    return null;
};

export const deleteAllCookies = () => {
    const cookies = document.cookie.split(";");
    for (let i = 0; i < cookies.length; i++) {
        const cookie = cookies[i];
        const eqPos = cookie.indexOf("=");
        const name = eqPos > -1 ? cookie.substr(0, eqPos) : cookie;
        document.cookie = name + "=;expires=Thu, 01 Jan 1970 00:00:00 GMT;path=/";
    }
};

export const reloadWindow = () => {
    if (typeof window !== 'undefined') {
        window.location.reload();
    }
};

/**
 * Global Lead / Inquiry Normalizer
 * Unifies diverse lead response formats from backend APIs
 * (catering/lead/list.php, catering/lead/view.php, dining/lead/list.php,
 * dining/lead/view.php, lead/list.php, lead/view.php, dashboard/overview.php)
 * into a single consistent and reliable structure.
 *
 * @param {object} lead - Raw lead object
 * @returns {object} Normalized lead object
 */
export const normalizeLead = (lead) => {
    if (!lead || typeof lead !== 'object') {
        return {
            id: null,
            leadReference: '',
            domain: '',
            customerName: '',
            customerPhone: '',
            customerEmail: '',
            eventType: '',
            eventDate: '',
            eventTime: '',
            guestCount: null,
            venueAddress: '',
            packageName: '',
            packageRate: null,
            packageRateUnit: '',
            mealType: '',
            requirement: '',
            otherRequirements: '',
            cateringRequirement: '',
            status: 'new',
            estimatedAmount: null,
            convertedAmount: null,
            convertedAt: null,
            source: 'website',
            assignedAdmin: null,
            packageItems: [],
            customFoodItems: [],
            createdAt: '',
            updatedAt: '',
            raw: lead,
        };
    }

    const leadObj = lead.lead && typeof lead.lead === 'object' ? lead.lead : lead;
    const customer = lead.customer || leadObj.customer || {};
    const event = lead.event || leadObj.event || {};
    const domainDetails = lead.domain_details || {};
    const cateringDetails = lead.catering_lead_details || lead.catering_details || (domainDetails.catering_package ? domainDetails : (lead.catering || {}));
    const diningDetails = lead.dining_lead_details || lead.dining_details || (domainDetails.dining_package ? domainDetails : (lead.dining || {}));
    const hallDetails = lead.hall_lead_details || lead.hall_details || (domainDetails.hall ? domainDetails : (lead.hall || {}));
    
    const cateringPackage = lead.catering_package || cateringDetails.catering_package || domainDetails.catering_package || {};
    const diningPackage = diningDetails.package || diningDetails.dining_package || domainDetails.dining_package || lead.selected_package || {};
    const hall = hallDetails.hall || domainDetails.hall || {};
    
    const requirements = lead.requirements || {};
    const amounts = lead.amounts || {};
    const meta = lead.meta || {};

    const id = lead.id ?? leadObj.id ?? null;
    const leadReference = lead.lead_reference || leadObj.lead_reference || (id ? `LEAD-${id}` : '');
    const domain = lead.domain || leadObj.domain || (cateringPackage.id || cateringDetails.event_type ? 'catering' : (diningPackage.id || diningDetails.meal_type ? 'dining' : (hall.id ? 'hall' : '')));

    const customerName = customer.name || lead.customer_name || leadObj.customer_name || lead.name || '';
    const customerPhone = customer.phone || lead.customer_phone || leadObj.customer_phone || lead.phone || '';
    const customerEmail = customer.email || lead.customer_email || leadObj.customer_email || lead.email || '';

    const eventType = event.type || cateringDetails.event_type || domainDetails.event?.type || lead.event_type || '';
    const eventDate = event.date || cateringDetails.event_date || diningDetails.dining_date || domainDetails.event?.date || lead.catering_event_date || lead.dining_date || lead.event_date || leadObj.event_date || '';
    const eventTime = event.time || event.preferred_time || cateringDetails.event_time || diningDetails.dining_time || domainDetails.event?.time || lead.preferred_time || lead.event_time || lead.dining_time || leadObj.preferred_time || '';

    const guestCountRaw = event.guest_count ?? cateringDetails.guest_count ?? diningDetails.guest_count ?? domainDetails.event?.guest_count ?? lead.guest_count ?? lead.catering_guest_count ?? lead.dining_guest_count ?? leadObj.guest_count ?? null;
    const guestCount = guestCountRaw !== null && guestCountRaw !== undefined && guestCountRaw !== '' ? Number(guestCountRaw) : null;

    const venueAddress = event.venue_address || cateringDetails.venue_address || domainDetails.event?.venue_address || requirements.venue_address || lead.venue_address || '';

    const packageName = cateringPackage.name || diningPackage.name || hall.name || lead.catering_package_name || lead.dining_package_name || lead.package_name || lead.hall_name || '';
    const packageRate = cateringPackage.rate ?? diningPackage.rate ?? null;
    const packageRateUnit = cateringPackage.rate_unit || diningPackage.rate_unit || '';
    const mealType = diningDetails.meal_type || domainDetails.dining_package?.meal_type || domainDetails.event?.meal_type || lead.meal_type || '';

    const requirement = requirements.lead_requirement || lead.requirement || leadObj.requirement || lead.message || '';
    const otherRequirements = requirements.catering_requirement || cateringDetails.other_requirements || diningDetails.other_requirements || domainDetails.other_requirements || lead.catering_other_requirements || lead.dining_other_requirements || lead.other_requirements || lead.special_requests || '';
    const cateringRequirement = requirements.catering_requirement || cateringDetails.other_requirements || '';

    const status = (typeof lead.status === 'string' ? lead.status : null) ||
        (typeof leadObj.status === 'string' ? leadObj.status : null) ||
        'new';

    const estimatedAmount = amounts.estimated_amount ?? cateringDetails.estimated_amount ?? lead.estimated_amount ?? leadObj.estimated_amount ?? lead.budget ?? null;
    const convertedAmount = amounts.converted_amount ?? lead.converted_amount ?? leadObj.converted_amount ?? null;
    const convertedAt = lead.converted_at || leadObj.converted_at || null;

    const source = meta.source || lead.source || leadObj.source || 'website';
    const assignedAdmin = lead.assigned_admin || leadObj.assigned_admin || lead.assignment || (lead.assigned_admin_id ? {
        id: lead.assigned_admin_id,
        name: lead.assigned_admin_name,
        email: lead.assigned_admin_email,
        phone: lead.assigned_admin_phone,
    } : null);

    const packageItems = lead.package_items?.items || (Array.isArray(lead.package_items) ? lead.package_items : []);
    const customFoodItems = lead.custom_food_items?.items || (Array.isArray(lead.custom_food_items) ? lead.custom_food_items : []);

    const createdAt = meta.created_at || lead.created_at || leadObj.created_at || '';
    const updatedAt = meta.updated_at || lead.updated_at || leadObj.updated_at || '';

    return {
        id,
        leadReference,
        domain,
        customerName,
        customerPhone,
        customerEmail,
        eventType,
        eventDate,
        eventTime,
        guestCount,
        venueAddress,
        packageName,
        packageRate,
        packageRateUnit,
        mealType,
        requirement,
        otherRequirements,
        cateringRequirement,
        status,
        estimatedAmount,
        convertedAmount,
        convertedAt,
        source,
        assignedAdmin,
        packageItems,
        customFoodItems,
        createdAt,
        updatedAt,
        raw: lead,
    };
};

/**
 * Global Payment Transaction Normalizer
 * Unifies diverse payment response formats from backend APIs
 * (reports/payment_report.php, payment/history.php, dashboard/overview.php, booking/view.php)
 * into a single consistent and reliable structure.
 *
 * @param {object} item - Raw payment transaction object
 * @returns {object} Normalized payment object
 */
export const normalizePayment = (item) => {
    if (!item || typeof item !== 'object') {
        return {
            id: null,
            payment_id: null,
            gateway: 'Razorpay',
            amount: 0,
            currency: 'INR',
            status: 'created',
            method: '—',
            paidAt: null,
            paid_at: null,
            createdAt: null,
            created_at: null,
            updatedAt: null,
            updated_at: null,
            bookingId: null,
            booking_id: null,
            bookingReference: '—',
            booking_reference: '—',
            checkInDate: null,
            check_in_date: null,
            checkOutDate: null,
            check_out_date: null,
            bookingStatus: 'confirmed',
            booking_status: 'confirmed',
            bookingPaymentStatus: 'pending',
            booking_payment_status: 'pending',
            customerId: null,
            customer_id: null,
            customerName: '—',
            customer_name: '—',
            customerPhone: '',
            customer_phone: '',
            customerEmail: '',
            customer_email: '',
            razorpayOrderId: '—',
            razorpay_order_id: '—',
            razorpayPaymentId: '—',
            razorpay_payment_id: '—',
            transactionId: '—',
            transaction_id: '—',
            signatureAvailable: false,
            signature_available: false,
            roomSubtotal: 0,
            room_subtotal: 0,
            gstRate: 18,
            gst_rate: 18,
            gstAmount: 0,
            gst_amount: 0,
            totalAmount: 0,
            total_amount: 0,
            statusMatch: true,
            payment: {
                gateway: 'Razorpay',
                amount: 0,
                currency: 'INR',
                status: 'created',
                method: '—',
                paid_at: null,
                created_at: null,
                updated_at: null,
            },
            booking: {
                id: null,
                reference: '—',
                check_in_date: null,
                check_out_date: null,
                booking_status: 'confirmed',
                payment_status: 'pending',
            },
            customer: {
                id: null,
                name: '—',
                phone: '',
                email: '',
            },
            razorpay: {
                order_id: '—',
                payment_id: '—',
                signature_available: false,
            },
            booking_amount: {
                room_subtotal: 0,
                gst_rate: 18,
                gst_amount: 0,
                total_amount: 0,
            },
            reconciliation: {
                booking_payment_status: 'pending',
                transaction_payment_status: 'created',
                status_match: true,
            },
            raw: item,
        };
    }

    const p = item.payment || {};
    const b = item.booking || {};
    const c = item.customer || {};
    const rz = item.razorpay || {};
    const recon = item.reconciliation || {};
    const bAmt = item.booking_amount || {};

    const id = item.payment_id ?? item.id ?? null;
    const gateway = p.gateway || item.payment_gateway || item.gateway || 'Razorpay';
    const amount = p.amount !== undefined ? Number(p.amount) : (item.amount !== undefined ? Number(item.amount) : 0);
    const currency = p.currency || item.currency || 'INR';
    const status = p.status || item.payment_status || item.status || 'created';
    const method = p.method || item.payment_method || item.method || '—';
    const paidAt = p.paid_at || item.paid_at || null;
    const createdAt = p.created_at || item.created_at || item.payment_date || null;
    const updatedAt = p.updated_at || item.updated_at || null;

    const bookingId = b.id ?? item.booking_id ?? null;
    const bookingReference = b.reference || item.booking_reference || (bookingId ? `Booking #${bookingId}` : '—');
    const checkInDate = b.check_in_date || item.check_in_date || null;
    const checkOutDate = b.check_out_date || item.check_out_date || null;
    const bookingStatus = b.booking_status || item.booking_status || 'confirmed';
    const bookingPaymentStatus = b.payment_status || recon.booking_payment_status || item.booking_payment_status || 'pending';

    const customerId = c.id ?? item.customer_id ?? null;
    const customerName = c.name || item.customer_name || item.name || '—';
    const customerPhone = c.phone || item.customer_phone || item.phone || '';
    const customerEmail = c.email || item.customer_email || item.email || '';

    const razorpayOrderId = rz.order_id || item.razorpay_order_id || item.order_id || '—';
    const razorpayPaymentId = rz.payment_id || item.razorpay_payment_id || item.transaction_id || '—';
    const signatureAvailable = rz.signature_available !== undefined ? Boolean(rz.signature_available) : (!!item.razorpay_signature);

    const roomSubtotal = bAmt.room_subtotal !== undefined ? Number(bAmt.room_subtotal) : (item.room_subtotal !== undefined ? Number(item.room_subtotal) : 0);
    const gstRate = bAmt.gst_rate !== undefined ? Number(bAmt.gst_rate) : (item.gst_rate !== undefined ? Number(item.gst_rate) : 18);
    const gstAmount = bAmt.gst_amount !== undefined ? Number(bAmt.gst_amount) : (item.gst_amount !== undefined ? Number(item.gst_amount) : 0);
    const totalAmount = bAmt.total_amount !== undefined ? Number(bAmt.total_amount) : (item.total_amount !== undefined ? Number(item.total_amount) : amount);

    const statusMatch = recon.status_match !== undefined
        ? Boolean(recon.status_match)
        : (status === bookingPaymentStatus || (status === 'captured' && (bookingPaymentStatus === 'paid' || bookingPaymentStatus === 'confirmed')));

    const paymentObj = {
        gateway,
        amount,
        currency,
        status,
        method,
        paid_at: paidAt,
        created_at: createdAt,
        updated_at: updatedAt,
    };

    const bookingObj = {
        id: bookingId,
        reference: bookingReference,
        check_in_date: checkInDate,
        check_out_date: checkOutDate,
        booking_status: bookingStatus,
        payment_status: bookingPaymentStatus,
    };

    const customerObj = {
        id: customerId,
        name: customerName,
        phone: customerPhone,
        email: customerEmail,
    };

    const razorpayObj = {
        order_id: razorpayOrderId,
        payment_id: razorpayPaymentId,
        signature_available: signatureAvailable,
    };

    const bookingAmountObj = {
        room_subtotal: roomSubtotal,
        gst_rate: gstRate,
        gst_amount: gstAmount,
        total_amount: totalAmount,
    };

    const reconciliationObj = {
        booking_payment_status: bookingPaymentStatus,
        transaction_payment_status: status,
        status_match: statusMatch,
    };

    return {
        id,
        payment_id: id,
        gateway,
        payment_gateway: gateway,
        amount,
        currency,
        status,
        payment_status: status,
        method,
        payment_method: method,
        paidAt,
        paid_at: paidAt,
        createdAt,
        created_at: createdAt,
        updatedAt,
        updated_at: updatedAt,
        bookingId,
        booking_id: bookingId,
        bookingReference,
        booking_reference: bookingReference,
        checkInDate,
        check_in_date: checkInDate,
        checkOutDate,
        check_out_date: checkOutDate,
        bookingStatus,
        booking_status: bookingStatus,
        bookingPaymentStatus,
        booking_payment_status: bookingPaymentStatus,
        customerId,
        customer_id: customerId,
        customerName,
        customer_name: customerName,
        name: customerName,
        customerPhone,
        customer_phone: customerPhone,
        phone: customerPhone,
        customerEmail,
        customer_email: customerEmail,
        email: customerEmail,
        razorpayOrderId,
        razorpay_order_id: razorpayOrderId,
        razorpayPaymentId,
        razorpay_payment_id: razorpayPaymentId,
        transactionId: razorpayPaymentId,
        transaction_id: razorpayPaymentId,
        signatureAvailable,
        signature_available: signatureAvailable,
        roomSubtotal,
        room_subtotal: roomSubtotal,
        gstRate,
        gst_rate: gstRate,
        gstAmount,
        gst_amount: gstAmount,
        totalAmount,
        total_amount: totalAmount,
        statusMatch,
        payment: paymentObj,
        booking: bookingObj,
        customer: customerObj,
        razorpay: razorpayObj,
        booking_amount: bookingAmountObj,
        reconciliation: reconciliationObj,
        raw: item,
    };
};



