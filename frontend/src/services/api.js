import axios from 'axios';
import { getAuthSession, clearAuthSession, AUTH_KEYS } from './Utils.js';

// Predefined Base URL (can be read from environment variables)
const BASE_URL = import.meta.env?.VITE_API_BASE_URL || 'http://localhost/sm_groups/backend';

const apiClient = axios.create({
    baseURL: BASE_URL,
    timeout: 20000,
    headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
    },
});

// Request Interceptor: Inject token automatically and configure headers for JSON / FormData
apiClient.interceptors.request.use(
    (config) => {
        // Retrieve token with role-isolation support (Admin/Staff vs Customer)
        let token = config.token;
        if (!token) {
            const adminToken = localStorage.getItem(AUTH_KEYS.ADMIN.ACCESS_TOKEN);
            const customerToken = localStorage.getItem(AUTH_KEYS.CUSTOMER.ACCESS_TOKEN);
            const lastRole = localStorage.getItem(AUTH_KEYS.LAST_ACTIVE_ROLE);

            if (config.authRole === 'customer') {
                token = customerToken;
            } else if (config.authRole === 'admin' || config.authRole === 'staff') {
                token = adminToken;
            } else if (lastRole === 'customer') {
                token = customerToken || adminToken;
            } else if (lastRole === 'admin' || lastRole === 'staff') {
                token = adminToken || customerToken;
            } else {
                token = customerToken || adminToken;
            }
        }

        if (token) {
            config.headers = config.headers || {};
            config.headers.Authorization = `Bearer ${token}`;
        }

        // Automatic FormData header handling: allow Axios / browser to set multipart boundary
        if (config.data instanceof FormData) {
            delete config.headers['Content-Type'];
        }

        return config;
    },
    (error) => {
        return Promise.reject(error);
    }
);

// Response Interceptor: Standardized response & error handling matching reference architecture
apiClient.interceptors.response.use(
    (response) => {
        const data = response.data;
        const isPhpSuccess = data?.status !== false;

        return {
            success: isPhpSuccess,
            data,
            status: response.status,
            error: isPhpSuccess ? null : (data?.message || `Request failed with status ${response.status}`),
        };
    },
    async (error) => {
        const status = error.response?.status || 500;
        const errorData = error.response?.data || null;
        const errorMessage = errorData?.message || error.message || 'Something went wrong';

        const customError = {
            success: false,
            data: errorData,
            status,
            error: errorMessage,
        };

        // Auto logout and session cleanup on 401 Unauthorized
        if (status === 401) {
            clearAuthSession('admin');
            clearAuthSession('customer');
            if (typeof window !== 'undefined' && !window.location.pathname.includes('/login')) {
                const isAdminRoute = window.location.pathname.startsWith('/dashboard') || window.location.pathname.startsWith('/admin');
                window.location.href = isAdminRoute ? '/admin/login' : '/login';
            }
        }

        // Resolve instead of reject so calling components don't crash and receive consistent { success, data, status, error }
        return Promise.resolve(customError);
    }
);

// Simple Wrapper APIs matching reference architecture
export const api = {
    get: (url, config = {}) => apiClient.get(url, config),
    post: (url, data = {}, config = {}) => apiClient.post(url, data, config),
    put: (url, data = {}, config = {}) => apiClient.put(url, data, config),
    delete: (url, dataOrConfig = {}, config = {}) => {
        // Handle both api.delete(url, config) and api.delete(url, payload, config)
        if (dataOrConfig && (dataOrConfig.headers || dataOrConfig.params || dataOrConfig.token)) {
            return apiClient.delete(url, dataOrConfig);
        }
        return apiClient.delete(url, { ...config, data: dataOrConfig });
    },
    patch: (url, data = {}, config = {}) => apiClient.patch(url, data, config),
};

/**
 * Standard Hall API Service Contract
 */
export const hallApi = {
    list: (params = '') => api.get(`/hall/list.php${params ? `?${params}` : ''}`),
    view: (hallId) => api.get(`/hall/view.php?hall_id=${Number(hallId)}`),
    create: (data) => api.post('/hall/create.php', data),
    update: (data) => api.post('/hall/update.php', data),
    delete: (hallId) => api.post('/hall/delete.php', { hall_id: Number(hallId) }),
    uploadImage: (hallId, file, altText = '', isPrimary = false) => {
        if (!hallId || !file) {
            return Promise.resolve({
                success: false,
                data: null,
                status: 422,
                error: 'Both hall_id and image file are required to upload.'
            });
        }
        const formData = new FormData();
        formData.append('hall_id', String(hallId));
        formData.append('image', file);
        if (altText) formData.append('alt_text', altText.trim());
        formData.append('is_primary', isPrimary ? '1' : '0');
        return api.post('/hall/upload_img.php', formData);
    },
    deleteImage: (hallId, imageId) => {
        if (!hallId || !imageId) {
            return Promise.resolve({
                success: false,
                data: null,
                status: 422,
                error: 'Both hall_id and image_id are required to delete an image.'
            });
        }
        return api.post('/hall/delete_img.php', {
            hall_id: Number(hallId),
            image_id: Number(imageId)
        });
    },
    updateFacilities: (hallId, facilityIds = []) => {
        return api.post('/hall/update_facilities.php', {
            hall_id: Number(hallId),
            facility_ids: facilityIds
        });
    },
    createLead: (data) => api.post('/hall/lead/create.php', data),
};

export const roomTypeApi = {
    list: (params = '') => api.get(`/room_type/list.php${params ? `?${params}` : ''}`),
    create: (data) => api.post('/room_type/create.php', data),
    update: (data) => api.post('/room_type/update.php', data),
    delete: (roomTypeId) => api.post('/room_type/delete.php', { room_type_id: Number(roomTypeId) }),
};

export const amenityApi = {
    list: (params = '') => api.get(`/aminity/list.php${params ? `?${params}` : ''}`),
    create: (data) => api.post('/aminity/create.php', data),
    update: (data) => api.post('/aminity/update.php', data),
    delete: (amenityId) => api.post('/aminity/delete.php', { amenity_id: Number(amenityId) }),
};

/**
 * Standard Room API Service Contract
 */
export const roomApi = {
    list: (params = '') => api.get(`/room/list.php${params ? `?${params}` : ''}`),
    view: (roomId) => api.get(`/room/view.php?room_id=${Number(roomId)}`),
    availability: (params = '') => api.get(`/room/availablity.php${params ? `?${params}` : ''}`),
    amenityList: (params = '') => api.get(`/room/aminity_list.php${params ? `?${params}` : ''}`),
    create: (data) => api.post('/room/create.php', data),
    update: (data) => api.post('/room/update.php', data),
    delete: (roomId) => api.post('/room/delete.php', { room_id: Number(roomId) }),
    updateAmenities: (roomId, amenityIds = []) => {
        return api.post('/room/update_aminity.php', {
            room_id: Number(roomId),
            amenity_ids: amenityIds.map((id) => Number(id)).filter((id) => !isNaN(id) && id > 0)
        });
    },
    uploadImage: (roomId, file, altText = '', isPrimary = false) => {
        if (!roomId || !file) {
            return Promise.resolve({
                success: false,
                data: null,
                status: 422,
                error: 'Both room_id and image file are required to upload.'
            });
        }
        const formData = new FormData();
        formData.append('room_id', String(roomId));
        formData.append('image', file);
        if (altText) formData.append('alt_text', altText.trim());
        formData.append('is_primary', isPrimary ? '1' : '0');
        return api.post('/room/upload_img.php', formData);
    },
    deleteImage: (roomId, imageId) => {
        if (!roomId || !imageId) {
            return Promise.resolve({
                success: false,
                data: null,
                status: 422,
                error: 'Both room_id and image_id are required to delete an image.'
            });
        }
        return api.post('/room/delete_img.php', {
            room_id: Number(roomId),
            image_id: Number(imageId)
        });
    }
};

/**
 * Standard Hall Facility API Service Contract
 */
export const hallFacilityApi = {
    list: (params = '') => api.get(`/hall_facilities/list.php${params ? `?${params}` : ''}`),
    create: (data) => api.post('/hall_facilities/create.php', data),
    update: (data) => api.post('/hall_facilities/update.php', data),
    delete: (facilityId) => api.post('/hall_facilities/delete.php', { facility_id: Number(facilityId) }),
};

/**
 * Standard Food Items & Categories API Service Contract
 */
export const foodItemApi = {
    list: (params = '') => api.get(`/food/item/list.php${params ? `?${params}` : ''}`),
    view: (itemId) => api.get(`/food/item/view.php?food_item_id=${Number(itemId)}`),
    create: (formData) => api.post('/food/item/create.php', formData),
    update: (formData) => api.post('/food/item/update.php', formData),
    delete: (itemId) => api.post('/food/item/delete.php', { food_item_id: Number(itemId) }),
};

export const foodCategoryApi = {
    list: (params = '') => api.get(`/food/categories/list.php${params ? `?${params}` : ''}`),
    create: (data) => api.post('/food/categories/create.php', data),
    update: (data) => api.post('/food/categories/update.php', data),
    delete: (catId) => api.post('/food/categories/delete.php', { category_id: Number(catId) }),
};

export const roomFoodPackageApi = {
    list: (params = '') => api.get(`/food/room_package/list.php${params ? `?${params}` : ''}`),
    view: (packageId) => api.get(`/food/room_package/view.php?package_id=${Number(packageId)}`),
    create: (data) => api.post('/food/room_package/create.php', data),
    update: (data) => api.post('/food/room_package/update.php', data),
    delete: (packageId) => api.post('/food/room_package/delete.php', { package_id: Number(packageId) }),
};

/**
 * Standard Dining API Service Contract
 */
export const diningApi = {
    // Packages
    listPackages: (params = '') => api.get(`/dining/package/list.php${params ? `?${params}` : ''}`),
    viewPackage: (packageId) => api.get(`/dining/package/view.php?package_id=${Number(packageId)}`),
    createPackage: (formData) => api.post('/dining/package/create.php', formData),
    updatePackage: (formData) => api.post('/dining/package/update.php', formData),
    deletePackage: (packageId) => api.post('/dining/package/delete.php', { package_id: Number(packageId) }),

    // Leads / Inquiries
    listLeads: (params = '') => api.get(`/dining/lead/list.php${params ? `?${params}` : ''}`),
    viewLead: (leadId) => api.get(`/dining/lead/view.php?lead_id=${Number(leadId)}`),
    createLead: (data) => api.post('/dining/lead/create.php', data),
    updateLeadStatus: (leadId, status, notes = '') => api.post('/lead/update_status.php', {
        lead_id: Number(leadId),
        status,
        ...(notes ? { notes } : {})
    }),

    // Food Catalog Items (for Package Item selection)
    listFoodItems: (params = 'limit=100&status=active') => api.get(`/food/item/list.php${params ? `?${params}` : ''}`),
};

/**
 * Standard Authentication API Service Contract
 */
export const authApi = {
    profile: (config = {}) => api.get('/auth/profile.php', { authRole: 'customer', ...config }),
    logout: (config = {}) => api.post('/auth/logout.php', {}, { authRole: 'customer', ...config }),
    adminProfile: (config = {}) => api.get('/auth/profile.php', { authRole: 'admin', ...config }),
    adminLogout: (config = {}) => api.post('/auth/logout.php', {}, { authRole: 'admin', ...config }),
    staffProfile: (config = {}) => api.get('/auth/profile.php', { authRole: 'staff', ...config }),
    staffLogout: (config = {}) => api.post('/auth/logout.php', {}, { authRole: 'staff', ...config }),
    staffList: (params = '', config = {}) => api.get(`/auth/staff_list.php${params ? `?${params}` : ''}`, { authRole: 'admin', ...config }),
    login: (data) => api.post('/auth/login.php', data),
    customerRegister: (data) => api.post('/auth/customer_register.php', data),
};

/**
 * Standard Booking API Service Contract
 */
export const bookingApi = {
    list: (params = '', config = {}) => api.get(`/booking/list.php${params ? `?${params}` : ''}`, config),
    view: (bookingId, config = {}) => api.get(`/booking/view.php?booking_id=${Number(bookingId)}`, config),
    create: (data, config = {}) => api.post('/booking/create.php', data, { authRole: 'customer', ...config }),
    cancel: (data, config = {}) => api.post('/booking/cancel.php', data, config),
    history: (params = '', config = {}) => api.get(`/booking/history.php${params ? `?${params}` : ''}`, config),
    checkIn: (bookingId, config = {}) => api.post('/booking/check_in.php', { booking_id: Number(bookingId) }, config),
    checkOut: (bookingId, config = {}) => api.post('/booking/check_out.php', { booking_id: Number(bookingId) }, config),
};

/**
 * Standard Customer Payment API Service Contract
 */
export const paymentApi = {
    createOrder: (data, config = {}) => {
        const payload = typeof data === 'number' || typeof data === 'string'
            ? { booking_id: Number(data) }
            : { booking_id: Number(data?.booking_id) };
        return api.post('/payment/create_order.php', payload, { authRole: 'customer', ...config });
    },
    verify: (data, config = {}) => {
        const payload = {
            booking_id: Number(data?.booking_id),
            razorpay_order_id: String(data?.razorpay_order_id || '').trim(),
            razorpay_payment_id: String(data?.razorpay_payment_id || '').trim(),
            razorpay_signature: String(data?.razorpay_signature || '').trim(),
        };
        return api.post('/payment/verify.php', payload, { authRole: 'customer', ...config });
    },
    status: (params = '', config = {}) => {
        let queryString = '';
        if (typeof params === 'object' && params !== null) {
            const searchParams = new URLSearchParams();
            Object.entries(params).forEach(([key, val]) => {
                if (val !== undefined && val !== null && val !== '') {
                    searchParams.append(key, String(val));
                }
            });
            queryString = searchParams.toString();
        } else if (typeof params === 'string' && params) {
            queryString = params.startsWith('?') ? params.slice(1) : params;
        }
        return api.get(`/payment/status.php${queryString ? `?${queryString}` : ''}`, { authRole: 'customer', ...config });
    },
    history: (params = '', config = {}) => {
        let queryString = '';
        if (typeof params === 'object' && params !== null) {
            const searchParams = new URLSearchParams();
            Object.entries(params).forEach(([key, val]) => {
                if (val !== undefined && val !== null && val !== '') {
                    searchParams.append(key, String(val));
                }
            });
            queryString = searchParams.toString();
        } else if (typeof params === 'string' && params) {
            queryString = params.startsWith('?') ? params.slice(1) : params;
        }
        return api.get(`/payment/history.php${queryString ? `?${queryString}` : ''}`, { authRole: 'customer', ...config });
    },
};

export default api;


