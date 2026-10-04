import React, { useState, useMemo, useEffect, useCallback } from 'react';
import Select from 'react-select';
import Pagination from './Pagination';
import api, { amenityApi } from '../services/api';

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
        zIndex: 50,
        overflow: 'hidden',
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
 * Standard Hotel Amenity Icons Library
 */
export const AMENITY_ICONS = [
    {
        id: 'wifi',
        name: 'High-speed Wi-Fi',
        svg: (
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                <path d="M5 12.55a11 11 0 0 1 14.08 0" />
                <path d="M1.42 9a16 16 0 0 1 21.16 0" />
                <path d="M8.53 16.11a6 6 0 0 1 6.95 0" />
                <line x1="12" y1="20" x2="12.01" y2="20" />
            </svg>
        )
    },
    {
        id: 'ac',
        name: 'Air Conditioning',
        svg: (
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                <path d="M12 2v20M2 12h20M4.93 4.93l14.14 14.14M4.93 19.07l14.14-14.14" />
            </svg>
        )
    },
    {
        id: 'tv',
        name: 'Smart TV',
        svg: (
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                <rect x="2" y="7" width="20" height="15" rx="2" ry="2" />
                <polyline points="17 2 12 7 7 2" />
            </svg>
        )
    },
    {
        id: 'hot-water',
        name: '24/7 Hot Water',
        svg: (
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                <path d="M12 2v6" />
                <path d="M12 18v4" />
                <path d="M4.93 4.93l4.24 4.24" />
                <path d="M14.83 14.83l4.24 4.24" />
                <path d="M2 12h6" />
                <path d="M18 12h4" />
                <path d="M4.93 19.07l4.24-4.24" />
                <path d="M14.83 9.17l4.24-4.24" />
            </svg>
        )
    },
    {
        id: 'mini-fridge',
        name: 'Mini Fridge',
        svg: (
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                <rect x="5" y="2" width="14" height="20" rx="2" />
                <line x1="5" y1="10" x2="19" y2="10" />
                <line x1="9" y1="6" x2="9" y2="7" />
                <line x1="9" y1="14" x2="9" y2="16" />
            </svg>
        )
    },
    {
        id: 'room-service',
        name: 'Room Service',
        svg: (
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9" />
                <path d="M13.73 21a2 2 0 0 1-3.46 0" />
            </svg>
        )
    },
    {
        id: 'wardrobe',
        name: 'Wardrobe & Dresser',
        svg: (
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                <rect x="3" y="3" width="18" height="18" rx="2" />
                <line x1="12" y1="3" x2="12" y2="21" />
                <line x1="9" y1="11" x2="9" y2="13" />
                <line x1="15" y1="11" x2="15" y2="13" />
            </svg>
        )
    },
    {
        id: 'work-desk',
        name: 'Work Desk & Chair',
        svg: (
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                <rect x="2" y="3" width="20" height="14" rx="2" />
                <line x1="8" y1="21" x2="16" y2="21" />
                <line x1="12" y1="17" x2="12" y2="21" />
            </svg>
        )
    },
    {
        id: 'kettle',
        name: 'Electric Kettle & Tea Kit',
        svg: (
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                <path d="M18 8h1a4 4 0 0 1 0 8h-1" />
                <path d="M2 8h16v9a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4V8z" />
                <line x1="6" y1="1" x2="6" y2="4" />
                <line x1="10" y1="1" x2="10" y2="4" />
                <line x1="14" y1="1" x2="14" y2="4" />
            </svg>
        )
    },
    {
        id: 'balcony',
        name: 'Balcony / City View',
        svg: (
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                <path d="M3 21h18" />
                <path d="M5 21V9a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v12" />
                <path d="M9 3v4" />
                <path d="M15 3v4" />
            </svg>
        )
    },
    {
        id: 'locker',
        name: 'Personal Safe / Locker',
        svg: (
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                <rect x="3" y="11" width="18" height="11" rx="2" ry="2" />
                <path d="M7 11V7a5 5 0 0 1 10 0v4" />
            </svg>
        )
    },
    {
        id: 'parking',
        name: 'Valet / Parking',
        svg: (
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                <rect x="3" y="3" width="18" height="18" rx="2" />
                <path d="M9 17V7h4a3 3 0 0 1 0 6H9" />
            </svg>
        )
    },
    {
        id: 'power-backup',
        name: '24/7 Power Backup',
        svg: (
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2" />
            </svg>
        )
    },
    {
        id: 'housekeeping',
        name: 'Daily Housekeeping',
        svg: (
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" />
            </svg>
        )
    }
];

/**
 * Helper to render Amenity SVG Icon
 */
export const AmenityIcon = ({ iconId, className = 'amenity-icon-svg' }) => {
    const found = AMENITY_ICONS.find((item) => item.id === iconId);
    if (found) {
        return <span className={className}>{found.svg}</span>;
    }
    // Fallback icon
    return (
        <span className={className}>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                <circle cx="12" cy="12" r="10" />
                <line x1="12" y1="8" x2="12" y2="12" />
                <line x1="12" y1="16" x2="12.01" y2="16" />
            </svg>
        </span>
    );
};

/**
 * Generate URL safe slug
 */
const generateSlug = (text) => {
    return text
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
};

/**
 * Initial standard amenities list for SM Groups Lodge & Suites
 */
const INITIAL_AMENITIES = [
    {
        id: 1,
        name: 'High-speed Wi-Fi',
        slug: 'high-speed-wi-fi',
        icon: 'wifi',
        description: 'Complimentary high-speed optical fiber wireless internet throughout all rooms and common guest areas.',
        status: 'active',
        created_at: '2026-09-01 10:00:00',
        updated_at: '2026-09-01 10:00:00'
    },
    {
        id: 2,
        name: 'Air Conditioning',
        slug: 'air-conditioning',
        icon: 'ac',
        description: 'Individual temperature and climate control with silent modern split AC unit in every room.',
        status: 'active',
        created_at: '2026-09-01 10:00:00',
        updated_at: '2026-09-01 10:00:00'
    },
    {
        id: 3,
        name: 'Smart LED TV',
        slug: 'smart-led-tv',
        icon: 'tv',
        description: 'Wall-mounted 43-inch high-definition smart television with digital satellite channels and OTT apps.',
        status: 'active',
        created_at: '2026-09-01 10:00:00',
        updated_at: '2026-09-01 10:00:00'
    },
    {
        id: 4,
        name: '24/7 Hot Water',
        slug: '24-7-hot-water',
        icon: 'hot-water',
        description: 'Round-the-clock solar and geyser hot water supply with pressurized rainfall shower fixtures.',
        status: 'active',
        created_at: '2026-09-01 10:00:00',
        updated_at: '2026-09-01 10:00:00'
    },
    {
        id: 5,
        name: 'Mini Refrigerator',
        slug: 'mini-refrigerator',
        icon: 'mini-fridge',
        description: 'Compact in-room mini bar refrigerator for storing personal drinks, medicines, and refreshments.',
        status: 'active',
        created_at: '2026-09-02 11:30:00',
        updated_at: '2026-09-02 11:30:00'
    },
    {
        id: 6,
        name: 'Room Service',
        slug: 'room-service',
        icon: 'room-service',
        description: '24-hour in-room dining, fresh morning breakfast delivery, and front-desk concierge assistance.',
        status: 'active',
        created_at: '2026-09-02 12:00:00',
        updated_at: '2026-09-02 12:00:00'
    },
    {
        id: 7,
        name: 'Wardrobe & Dresser',
        slug: 'wardrobe-dresser',
        icon: 'wardrobe',
        description: 'Spacious full-height wooden wardrobe with premium hangers, full-length dressing mirror, and luggage rack.',
        status: 'active',
        created_at: '2026-09-03 14:00:00',
        updated_at: '2026-09-03 14:00:00'
    },
    {
        id: 8,
        name: 'Work Desk & Chair',
        slug: 'work-desk-chair',
        icon: 'work-desk',
        description: 'Dedicated ergonomic work table with comfortable chair, accessible international power sockets, and lamp.',
        status: 'active',
        created_at: '2026-09-03 14:30:00',
        updated_at: '2026-09-03 14:30:00'
    },
    {
        id: 9,
        name: 'Electric Kettle & Tea Kit',
        slug: 'electric-kettle-tea-kit',
        icon: 'kettle',
        description: 'Electric quick-boil kettle accompanied by daily complimentary premium tea bags, coffee sachets, and sugar.',
        status: 'active',
        created_at: '2026-09-04 09:15:00',
        updated_at: '2026-09-04 09:15:00'
    },
    {
        id: 10,
        name: 'Balcony / City View',
        slug: 'balcony-city-view',
        icon: 'balcony',
        description: 'Private attached open-air sit-out balcony with expansive garden landscape and city skyline views.',
        status: 'active',
        created_at: '2026-09-04 10:00:00',
        updated_at: '2026-09-04 10:00:00'
    },
    {
        id: 11,
        name: 'Personal Safe / Locker',
        slug: 'personal-safe-locker',
        icon: 'locker',
        description: 'Electronic digital keypad safe box built inside the wardrobe for secure storage of valuables and documents.',
        status: 'active',
        created_at: '2026-09-05 16:00:00',
        updated_at: '2026-09-05 16:00:00'
    },
    {
        id: 12,
        name: 'Valet Parking',
        slug: 'valet-parking',
        icon: 'parking',
        description: 'Dedicated 24-hour guarded on-site parking for four-wheelers and two-wheelers with round-the-clock CCTV surveillance.',
        status: 'inactive',
        created_at: '2026-09-10 18:00:00',
        updated_at: '2026-09-20 11:00:00'
    }
];

/**
 * Amenity Module Component for SM Groups Admin Dashboard
 */
const AmenitiesModule = () => {
    const [amenities, setAmenities] = useState(INITIAL_AMENITIES);
    const [searchQuery, setSearchQuery] = useState('');
    const [statusFilter, setStatusFilter] = useState('all'); // 'all' | 'active' | 'inactive'

    // Backend Pagination state
    const [currentPage, setCurrentPage] = useState(1);
    const [pageSize, setPageSize] = useState(5);
    const [totalRecords, setTotalRecords] = useState(INITIAL_AMENITIES.length);
    const [isLoading, setIsLoading] = useState(false);

    // Modal state
    const [isFormModalOpen, setIsFormModalOpen] = useState(false);
    const [formMode, setFormMode] = useState('create'); // 'create' | 'edit'
    const [editingAmenity, setEditingAmenity] = useState(null);

    const [isViewModalOpen, setIsViewModalOpen] = useState(false);
    const [viewingAmenity, setViewingAmenity] = useState(null);

    const [isDeleteModalOpen, setIsDeleteModalOpen] = useState(false);
    const [deletingAmenity, setDeletingAmenity] = useState(null);

    // Form inputs state
    const [formData, setFormData] = useState({
        name: '',
        icon: 'wifi',
        description: '',
        status: 'active'
    });
    const [formErrors, setFormErrors] = useState({});
    const [toastMessage, setToastMessage] = useState(null);
    const [isSubmitting, setIsSubmitting] = useState(false);

    // Toast helper
    const showToast = (msg, type = 'success') => {
        setToastMessage({ text: msg, type });
        setTimeout(() => {
            setToastMessage(null);
        }, 3500);
    };

    /**
     * Fetch Amenities from backend pagination API (/aminity/list.php)
     */
    const fetchAmenities = useCallback(
        async (
            page = currentPage,
            limit = pageSize,
            search = searchQuery,
            status = statusFilter
        ) => {
            setIsLoading(true);
            try {
                const params = new URLSearchParams();
                params.append('page', String(page));
                params.append('limit', String(limit));
                if (search && search.trim()) params.append('search', search.trim());
                if (status && status !== 'all') params.append('status', status);

                const res = await api.get(`/aminity/list.php?${params.toString()}`);
                const rawList = res.success && res.data?.status && Array.isArray(res.data.data)
                    ? res.data.data
                    : (res.data?.data && Array.isArray(res.data.data) ? res.data.data : null);

                if (rawList) {
                    const formatted = rawList.map((item) => ({
                        ...item,
                        slug: item.slug || generateSlug(item.name || ''),
                        icon: item.icon || 'wifi',
                        description: item.description || '',
                        updated_at: item.updated_at || item.created_at || '—'
                    }));
                    setAmenities(formatted);
                    if (res.data.pagination) {
                        setTotalRecords(res.data.pagination.total_records ?? formatted.length);
                    }
                }
            } catch (err) {
                console.warn('Backend amenity fetch note:', err.message);
            } finally {
                setIsLoading(false);
            }
        },
        [currentPage, pageSize, searchQuery, statusFilter]
    );

    useEffect(() => {
        fetchAmenities(currentPage, pageSize, searchQuery, statusFilter);
    }, [fetchAmenities, currentPage, pageSize, searchQuery, statusFilter]);

    // Statistics counts
    const stats = useMemo(() => {
        const total = totalRecords || amenities.length;
        const active = amenities.filter((a) => a.status === 'active').length;
        const inactive = amenities.filter((a) => a.status === 'inactive').length;
        return { total, active, inactive };
    }, [amenities, totalRecords]);

    // Search options for react-select
    const searchSelectOptions = useMemo(() => {
        return amenities.map((item) => ({
            value: item.name,
            label: `${item.name} (${item.slug})`,
        }));
    }, [amenities]);

    const currentSelectValue = useMemo(() => {
        if (!searchQuery) return null;
        return (
            searchSelectOptions.find(
                (opt) =>
                    opt.value.toLowerCase() === searchQuery.toLowerCase() ||
                    opt.label.toLowerCase().includes(searchQuery.toLowerCase())
            ) || {
                value: searchQuery,
                label: searchQuery,
            }
        );
    }, [searchQuery, searchSelectOptions]);

    // Handlers for modal interactions
    const handleOpenAdd = () => {
        setFormMode('create');
        setEditingAmenity(null);
        setFormData({
            name: '',
            icon: 'wifi',
            description: '',
            status: 'active',
        });
        setFormErrors({});
        setIsFormModalOpen(true);
    };

    const handleOpenEdit = (amenity) => {
        setFormMode('edit');
        setEditingAmenity(amenity);
        setFormData({
            name: amenity.name,
            icon: amenity.icon || 'wifi',
            description: amenity.description || '',
            status: amenity.status,
        });
        setFormErrors({});
        setIsFormModalOpen(true);
    };

    const handleOpenView = (amenity) => {
        setViewingAmenity(amenity);
        setIsViewModalOpen(true);
    };

    const handleOpenDelete = (amenity) => {
        setDeletingAmenity(amenity);
        setIsDeleteModalOpen(true);
    };

    // Validate form inputs
    const validateForm = () => {
        const errors = {};
        const trimmedName = formData.name.trim();

        if (!trimmedName) {
            errors.name = 'Amenity name is required.';
        } else if (trimmedName.length < 2) {
            errors.name = 'Amenity name must be at least 2 characters.';
        } else if (trimmedName.length > 80) {
            errors.name = 'Amenity name must not exceed 80 characters.';
        } else {
            // Check uniqueness (ignoring current edit item)
            const slug = generateSlug(trimmedName);
            const exists = amenities.some(
                (a) =>
                    a.slug === slug &&
                    (!editingAmenity || a.id !== editingAmenity.id)
            );
            if (exists) {
                errors.name = 'An amenity with this name already exists.';
            }
        }

        if (formData.description && formData.description.length > 300) {
            errors.description = 'Description must not exceed 300 characters.';
        }

        setFormErrors(errors);
        return Object.keys(errors).length === 0;
    };

    // Save Amenity (Create or Edit)
    const handleSaveAmenity = async (e) => {
        e.preventDefault();
        if (!validateForm()) return;

        const trimmedName = formData.name.trim();
        const payload = {
            name: trimmedName,
            icon: formData.icon || 'wifi',
            status: formData.status,
        };

        setIsSubmitting(true);
        try {
            if (formMode === 'create') {
                const res = await amenityApi.create(payload);
                if (res.success && res.data?.status) {
                    showToast(`Amenity "${trimmedName}" created successfully!`, 'success');
                    setIsFormModalOpen(false);
                    fetchAmenities(1, pageSize, searchQuery, statusFilter);
                } else {
                    setFormErrors({ submit: res.error || res.data?.message || 'Failed to create amenity.' });
                }
            } else {
                payload.amenity_id = Number(editingAmenity.id);
                const res = await amenityApi.update(payload);
                if (res.success && res.data?.status) {
                    showToast(`Amenity "${trimmedName}" updated successfully!`, 'success');
                    setIsFormModalOpen(false);
                    fetchAmenities(currentPage, pageSize, searchQuery, statusFilter);
                } else {
                    setFormErrors({ submit: res.error || res.data?.message || 'Failed to update amenity.' });
                }
            }
        } catch (err) {
            setFormErrors({ submit: err.message || 'An unexpected error occurred.' });
        } finally {
            setIsSubmitting(false);
        }
    };

    // Delete Amenity
    const handleConfirmDelete = async () => {
        if (!deletingAmenity) return;
        setIsSubmitting(true);
        try {
            const res = await amenityApi.delete(deletingAmenity.id);
            if (res.success && res.data?.status) {
                showToast(`Amenity "${deletingAmenity.name}" deleted successfully!`, 'success');
                setIsDeleteModalOpen(false);
                setDeletingAmenity(null);
                fetchAmenities(currentPage, pageSize, searchQuery, statusFilter);
            } else {
                showToast(res.error || res.data?.message || 'Failed to delete amenity.', 'error');
            }
        } catch (err) {
            showToast(err.message || 'An unexpected error occurred.', 'error');
        } finally {
            setIsSubmitting(false);
        }
    };

    return (
        <div className="room-types-module amenities-module">
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
                    <h2 className="dashboard-page-header__title">Amenity Management</h2>
                    <p className="dashboard-page-header__desc">
                        Manage all room features, convenience offerings, and guest services for SM Groups Lodge & Suites.
                    </p>
                </div>
                <div className="dashboard-page-header__actions">
                    <button
                        type="button"
                        className="admin-primary-btn"
                        onClick={handleOpenAdd}
                    >
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round">
                            <line x1="12" y1="5" x2="12" y2="19" />
                            <line x1="5" y1="12" x2="19" y2="12" />
                        </svg>
                        <span>Add New Amenity</span>
                    </button>
                </div>
            </div>

            {/* Summary Stat Cards */}
            <div className="room-types-stats-grid">
                <div className="room-type-stat-card">
                    <div className="room-type-stat-card__icon room-type-stat-card__icon--gold">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                            <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" />
                        </svg>
                    </div>
                    <div className="room-type-stat-card__info">
                        <span className="room-type-stat-card__label">Total Amenities</span>
                        <span className="room-type-stat-card__value">{stats.total}</span>
                    </div>
                </div>

                <div className="room-type-stat-card">
                    <div className="room-type-stat-card__icon room-type-stat-card__icon--emerald">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
                            <polyline points="22 4 12 14.01 9 11.01" />
                        </svg>
                    </div>
                    <div className="room-type-stat-card__info">
                        <span className="room-type-stat-card__label">Active Amenities</span>
                        <span className="room-type-stat-card__value">{stats.active}</span>
                    </div>
                </div>

                <div className="room-type-stat-card">
                    <div className="room-type-stat-card__icon room-type-stat-card__icon--rose">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                            <circle cx="12" cy="12" r="10" />
                            <line x1="4.93" y1="4.93" x2="19.07" y2="19.07" />
                        </svg>
                    </div>
                    <div className="room-type-stat-card__info">
                        <span className="room-type-stat-card__label">Inactive Amenities</span>
                        <span className="room-type-stat-card__value">{stats.inactive}</span>
                    </div>
                </div>
            </div>

            {/* Filter and Search Bar with React Select */}
            <div className="room-types-toolbar">
                <div className="room-types-search-select-wrap">
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
                        placeholder="Search or select amenity..."
                        isClearable
                        isSearchable
                        styles={reactSelectStyles}
                        className="react-select-container"
                        classNamePrefix="react-select"
                        noOptionsMessage={({ inputValue }) =>
                            inputValue ? `No amenity matches "${inputValue}"` : 'No options'
                        }
                    />
                </div>

                <div className="room-types-filter-group">
                    <button
                        type="button"
                        className={`room-types-filter-pill ${statusFilter === 'all' ? 'active' : ''}`}
                        onClick={() => { setStatusFilter('all'); setCurrentPage(1); }}
                    >
                        All ({stats.total})
                    </button>
                    <button
                        type="button"
                        className={`room-types-filter-pill ${statusFilter === 'active' ? 'active' : ''}`}
                        onClick={() => { setStatusFilter('active'); setCurrentPage(1); }}
                    >
                        Active ({stats.active})
                    </button>
                    <button
                        type="button"
                        className={`room-types-filter-pill ${statusFilter === 'inactive' ? 'active' : ''}`}
                        onClick={() => { setStatusFilter('inactive'); setCurrentPage(1); }}
                    >
                        Inactive ({stats.inactive})
                    </button>
                </div>
            </div>

            {/* Data Table Panel */}
            <div className="admin-table-card">
                {isLoading ? (
                    <div className="dashboard-loading-state" style={{ padding: '40px', textAlign: 'center', color: 'var(--text-secondary)' }}>
                        <div className="admin-spinner" style={{ width: '32px', height: '32px', border: '3px solid rgba(212, 160, 23, 0.2)', borderTopColor: '#d4a017', borderRadius: '50%', animation: 'spin 0.8s linear infinite', margin: '0 auto 12px' }} />
                        <p style={{ fontSize: '0.9rem', fontWeight: 500 }}>Loading amenities from server...</p>
                    </div>
                ) : amenities.length === 0 ? (
                    <div className="dashboard-empty-state">
                        <div className="dashboard-empty-state__icon-wrap">
                            <svg width="42" height="42" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round">
                                <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" />
                            </svg>
                        </div>
                        <h4 className="dashboard-empty-state__title">No amenities found</h4>
                        <p className="dashboard-empty-state__desc">
                            {searchQuery || statusFilter !== 'all'
                                ? 'No amenities matched your search filter criteria. Try resetting filters.'
                                : 'No amenities have been added yet. Click below to add your first amenity.'}
                        </p>
                        {searchQuery || statusFilter !== 'all' ? (
                            <button
                                type="button"
                                className="admin-secondary-btn"
                                onClick={() => { setSearchQuery(''); setStatusFilter('all'); setCurrentPage(1); }}
                            >
                                Reset Filters
                            </button>
                        ) : (
                            <button
                                type="button"
                                className="admin-primary-btn"
                                onClick={handleOpenAdd}
                            >
                                + Add First Amenity
                            </button>
                        )}
                    </div>
                ) : (
                    <>
                        <div className="admin-table-responsive">
                            <table className="admin-table">
                                <thead>
                                    <tr>
                                        <th style={{ width: '60px' }}>#ID</th>
                                        <th>Amenity Name</th>
                                        <th>Icon</th>
                                        <th>Slug</th>
                                        <th>Description</th>
                                        <th>Status</th>
                                        <th style={{ textAlign: 'right', width: '130px' }}>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {amenities.map((item) => (
                                        <tr key={item.id} className="admin-table-row">
                                            <td className="admin-table-cell--id">#{item.id}</td>
                                            <td>
                                                <div className="room-type-name-cell" style={{ display: 'flex', alignItems: 'center', gap: '10px' }}>
                                                    <div className="amenity-table-icon-badge">
                                                        <AmenityIcon iconId={item.icon} />
                                                    </div>
                                                    <span className="room-type-name-text">{item.name}</span>
                                                </div>
                                            </td>
                                            <td>
                                                <span className="amenity-icon-identifier-pill">
                                                    {item.icon}
                                                </span>
                                            </td>
                                            <td>
                                                <span className="room-type-slug-badge">{item.slug}</span>
                                            </td>
                                            <td className="admin-table-cell--desc">
                                                <p className="room-type-desc-truncate">
                                                    {item.description || <span className="text-muted-italic">No description</span>}
                                                </p>
                                            </td>
                                            <td>
                                                <span className={`admin-status-badge admin-status-badge--${item.status}`}>
                                                    <span className="admin-status-dot" />
                                                    {item.status === 'active' ? 'Active' : 'Inactive'}
                                                </span>
                                            </td>
                                            <td style={{ textAlign: 'right' }}>
                                                <div className="admin-table-actions">
                                                    <button
                                                        type="button"
                                                        className="admin-action-btn admin-action-btn--view"
                                                        onClick={() => handleOpenView(item)}
                                                        title="View Details"
                                                        aria-label={`View ${item.name}`}
                                                    >
                                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                                            <path d="M1 12 s4 -8 11 -8 11 8 11 8 -4 8 -11 8 -11 -8 -11 -8 z" />
                                                            <circle cx="12" cy="12" r="3" />
                                                        </svg>
                                                    </button>
                                                    <button
                                                        type="button"
                                                        className="admin-action-btn admin-action-btn--edit"
                                                        onClick={() => handleOpenEdit(item)}
                                                        title="Edit Amenity"
                                                        aria-label={`Edit ${item.name}`}
                                                    >
                                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" />
                                                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" />
                                                        </svg>
                                                    </button>
                                                    <button
                                                        type="button"
                                                        className="admin-action-btn admin-action-btn--delete"
                                                        onClick={() => handleOpenDelete(item)}
                                                        title="Delete Amenity"
                                                        aria-label={`Delete ${item.name}`}
                                                    >
                                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                                            <polyline points="3 6 5 6 21 6" />
                                                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" />
                                                            <line x1="10" y1="11" x2="10" y2="17" />
                                                            <line x1="14" y1="11" x2="14" y2="17" />
                                                        </svg>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
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

            {/* Modal 1: Create / Edit Amenity Form */}
            {isFormModalOpen && (
                <div className="admin-modal-overlay" onClick={() => setIsFormModalOpen(false)} role="dialog" aria-modal="true">
                    <div className="admin-modal" onClick={(e) => e.stopPropagation()}>
                        <div className="admin-modal__header">
                            <div className="admin-modal__title-wrap">
                                <h3 className="admin-modal__title">
                                    {formMode === 'create' ? 'Add New Amenity' : 'Edit Amenity'}
                                </h3>
                                <p className="admin-modal__subtitle">
                                    {formMode === 'create'
                                        ? 'Define a new convenience or room amenity for lodge guest suites.'
                                        : `Update details for "${editingAmenity?.name}".`}
                                </p>
                            </div>
                            <button
                                type="button"
                                className="admin-modal__close-btn"
                                onClick={() => setIsFormModalOpen(false)}
                                aria-label="Close dialog"
                            >
                                ✕
                            </button>
                        </div>

                        <form onSubmit={handleSaveAmenity} className="admin-modal__form">
                            <div className="admin-modal__body">
                                {formErrors.submit && (
                                    <div style={{ padding: '10px 14px', borderRadius: '8px', background: '#fee2e2', color: '#b91c1c', fontSize: '0.85rem', marginBottom: '16px' }}>
                                        {formErrors.submit}
                                    </div>
                                )}
                                <div className="admin-form-group">
                                    <label className="admin-form-label" htmlFor="amenity-name">
                                        Amenity Name <span className="text-danger">*</span>
                                    </label>
                                    <input
                                        id="amenity-name"
                                        type="text"
                                        className={`admin-form-input ${formErrors.name ? 'admin-form-input--error' : ''}`}
                                        placeholder="e.g., High-speed Wi-Fi, 24/7 Hot Water"
                                        value={formData.name}
                                        onChange={(e) => {
                                            setFormData((prev) => ({ ...prev, name: e.target.value }));
                                            if (formErrors.name) setFormErrors((prev) => ({ ...prev, name: null }));
                                        }}
                                        autoFocus
                                    />
                                    {formErrors.name && (
                                        <span className="admin-form-error">{formErrors.name}</span>
                                    )}
                                    {formData.name && (
                                        <span className="admin-form-hint">
                                            Generated Slug: <code>{generateSlug(formData.name)}</code>
                                        </span>
                                    )}
                                </div>

                                {/* Icon Selection Grid */}
                                <div className="admin-form-group">
                                    <label className="admin-form-label">
                                        Select Amenity Icon <span className="text-danger">*</span>
                                    </label>
                                    <div className="amenity-icon-picker-grid">
                                        {AMENITY_ICONS.map((iconItem) => {
                                            const isSelected = formData.icon === iconItem.id;
                                            return (
                                                <button
                                                    key={iconItem.id}
                                                    type="button"
                                                    className={`amenity-icon-picker-btn ${isSelected ? 'active' : ''}`}
                                                    onClick={() => setFormData((prev) => ({ ...prev, icon: iconItem.id }))}
                                                    title={iconItem.name}
                                                >
                                                    <span className="amenity-icon-picker-icon">{iconItem.svg}</span>
                                                    <span className="amenity-icon-picker-label">{iconItem.name}</span>
                                                    {isSelected && (
                                                        <span className="amenity-icon-check">
                                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="3">
                                                                <polyline points="20 6 9 17 4 12" />
                                                            </svg>
                                                        </span>
                                                    )}
                                                </button>
                                            );
                                        })}
                                    </div>
                                </div>

                                <div className="admin-form-group">
                                    <label className="admin-form-label" htmlFor="amenity-desc">
                                        Description <span className="text-muted">(Optional)</span>
                                    </label>
                                    <textarea
                                        id="amenity-desc"
                                        rows="3"
                                        className={`admin-form-textarea ${formErrors.description ? 'admin-form-input--error' : ''}`}
                                        placeholder="Brief details regarding this amenity or service feature..."
                                        value={formData.description}
                                        onChange={(e) => {
                                            setFormData((prev) => ({ ...prev, description: e.target.value }));
                                            if (formErrors.description) setFormErrors((prev) => ({ ...prev, description: null }));
                                        }}
                                    />
                                    {formErrors.description && (
                                        <span className="admin-form-error">{formErrors.description}</span>
                                    )}
                                    <span className="admin-form-hint">
                                        {formData.description.length} / 300 characters
                                    </span>
                                </div>

                                <div className="admin-form-group">
                                    <label className="admin-form-label" htmlFor="amenity-status">
                                        Status
                                    </label>
                                    <select
                                        id="amenity-status"
                                        className="admin-form-select"
                                        value={formData.status}
                                        onChange={(e) =>
                                            setFormData((prev) => ({ ...prev, status: e.target.value }))
                                        }
                                    >
                                        <option value="active">Active (Available for Room Assignment)</option>
                                        <option value="inactive">Inactive (Hidden / Disabled)</option>
                                    </select>
                                </div>
                            </div>

                            <div className="admin-modal__footer">
                                <button
                                    type="button"
                                    className="admin-secondary-btn"
                                    onClick={() => setIsFormModalOpen(false)}
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    className="admin-primary-btn"
                                >
                                    {formMode === 'create' ? 'Create Amenity' : 'Save Changes'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* Modal 2: View Amenity Details */}
            {isViewModalOpen && viewingAmenity && (
                <div className="admin-modal-overlay" onClick={() => setIsViewModalOpen(false)} role="dialog" aria-modal="true">
                    <div className="admin-modal admin-modal--view" onClick={(e) => e.stopPropagation()}>
                        <div className="admin-modal__header">
                            <div className="admin-modal__title-wrap">
                                <div className="admin-modal__badge-row">
                                    <span className="room-type-id-pill">
                                        AMENITY #{viewingAmenity.id}
                                    </span>
                                    <span className={`admin-status-badge admin-status-badge--${viewingAmenity.status}`}>
                                        <span className="admin-status-dot" />
                                        {viewingAmenity.status === 'active' ? 'ACTIVE' : 'INACTIVE'}
                                    </span>
                                </div>
                                <h3 className="admin-modal__title">Amenity Details</h3>
                                <p className="admin-modal__subtitle">Comprehensive overview of amenity specification.</p>
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
                            {/* Hero Header Card */}
                            <div style={{ display: 'flex', alignItems: 'center', gap: '16px', padding: '16px 20px', background: 'var(--bg-ivory, #faf7f2)', borderRadius: '12px', border: '1px solid rgba(212, 160, 23, 0.25)' }}>
                                <div style={{ width: '52px', height: '52px', borderRadius: '12px', background: 'linear-gradient(135deg, #fbf7ee, #f5ecd6)', border: '1px solid rgba(212, 160, 23, 0.35)', display: 'flex', alignItems: 'center', justifyContent: 'center', color: '#c99414', flexShrink: 0 }}>
                                    <AmenityIcon iconId={viewingAmenity.icon} />
                                </div>
                                <div style={{ minWidth: 0, flex: 1 }}>
                                    <h4 style={{ margin: 0, fontSize: '1.2rem', fontWeight: 700, color: 'var(--text-primary)', wordBreak: 'break-word' }}>
                                        {viewingAmenity.name}
                                    </h4>
                                    <div style={{ display: 'flex', alignItems: 'center', gap: '8px', marginTop: '6px', flexWrap: 'wrap' }}>
                                        <span className="room-type-slug-badge">
                                            {viewingAmenity.slug || generateSlug(viewingAmenity.name)}
                                        </span>
                                        <span className={`admin-status-badge admin-status-badge--${viewingAmenity.status}`}>
                                            <span className="admin-status-dot" />
                                            {viewingAmenity.status === 'active' ? 'Active' : 'Inactive'}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            {/* Details Grid */}
                            <div className="room-type-details-grid">
                                <div className="room-type-detail-item">
                                    <span className="room-type-detail-label">Icon Identifier</span>
                                    <span className="room-type-detail-val code">{viewingAmenity.icon || 'wifi'}</span>
                                </div>
                                <div className="room-type-detail-item">
                                    <span className="room-type-detail-label">Slug / Key</span>
                                    <span className="room-type-detail-val code">
                                        {viewingAmenity.slug || generateSlug(viewingAmenity.name)}
                                    </span>
                                </div>
                                <div className="room-type-detail-item">
                                    <span className="room-type-detail-label">Current Status</span>
                                    <span className="room-type-detail-val" style={{ textTransform: 'capitalize' }}>
                                        {viewingAmenity.status}
                                    </span>
                                </div>
                                <div className="room-type-detail-item">
                                    <span className="room-type-detail-label">Created At</span>
                                    <span className="room-type-detail-val">{viewingAmenity.created_at || '—'}</span>
                                </div>
                                <div className="room-type-detail-item full-width">
                                    <span className="room-type-detail-label">Description</span>
                                    <p style={{ margin: 0, fontSize: '0.88rem', color: viewingAmenity.description ? 'var(--text-primary)' : 'var(--text-muted)', lineHeight: '1.5' }}>
                                        {viewingAmenity.description || 'No description provided for this amenity.'}
                                    </p>
                                </div>
                                <div className="room-type-detail-item full-width">
                                    <span className="room-type-detail-label">Last Updated</span>
                                    <span className="room-type-detail-val">{viewingAmenity.updated_at || viewingAmenity.created_at || '—'}</span>
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
                                    handleOpenEdit(viewingAmenity);
                                }}
                            >
                                Edit Amenity
                            </button>
                        </div>
                    </div>
                </div>
            )}

            {/* Modal 3: Delete Confirmation */}
            {isDeleteModalOpen && deletingAmenity && (
                <div className="admin-modal-overlay" onClick={() => setIsDeleteModalOpen(false)} role="dialog" aria-modal="true">
                    <div className="admin-modal admin-modal--danger" onClick={(e) => e.stopPropagation()}>
                        <div className="admin-modal__header">
                            <div className="admin-modal__danger-icon">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#dc2626" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round">
                                    <circle cx="12" cy="12" r="10" />
                                    <line x1="12" y1="8" x2="12" y2="12" />
                                    <line x1="12" y1="16" x2="12.01" y2="16" />
                                </svg>
                            </div>
                            <div className="admin-modal__title-wrap">
                                <h3 className="admin-modal__title">Delete Amenity</h3>
                                <p className="admin-modal__subtitle">This action cannot be undone.</p>
                            </div>
                            <button
                                type="button"
                                className="admin-modal__close-btn"
                                onClick={() => setIsDeleteModalOpen(false)}
                                aria-label="Close dialog"
                            >
                                ✕
                            </button>
                        </div>
                        <div className="admin-modal__body">
                            <p style={{ margin: 0, fontSize: '0.9rem', color: 'var(--text-secondary)', lineHeight: 1.6 }}>
                                Are you sure you want to permanently delete the amenity <strong>"{deletingAmenity.name}"</strong>? This will remove it from the system and room assignment options.
                            </p>
                        </div>
                        <div className="admin-modal__footer">
                            <button
                                type="button"
                                className="admin-secondary-btn"
                                onClick={() => {
                                    setIsDeleteModalOpen(false);
                                    setDeletingAmenity(null);
                                }}
                            >
                                Cancel
                            </button>
                            <button
                                type="button"
                                className="admin-danger-btn"
                                onClick={handleConfirmDelete}
                            >
                                Delete Amenity
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
};

export default AmenitiesModule;
