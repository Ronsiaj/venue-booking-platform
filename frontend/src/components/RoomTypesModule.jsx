import React, { useState, useMemo, useEffect, useCallback } from 'react';
import Select from 'react-select';
import Pagination from './Pagination';
import api, { roomTypeApi } from '../services/api';

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
 * Initial fallback room types
 */
const INITIAL_ROOM_TYPES = [
    {
        id: 1,
        name: 'Standard Room',
        slug: 'standard-room',
        description: 'Comfortable air-conditioned standard room with twin or double bedding and attached bath.',
        status: 'active',
        total_rooms: 8,
        active_rooms: 8,
        created_at: '2026-09-01 10:00:00',
        updated_at: '2026-09-01 10:00:00',
    },
    {
        id: 2,
        name: 'Deluxe Room',
        slug: 'deluxe-room',
        description: 'Spacious deluxe room featuring king size bed, smart LED TV, mini fridge, and premium bathroom.',
        status: 'active',
        total_rooms: 12,
        active_rooms: 10,
        created_at: '2026-09-01 10:15:00',
        updated_at: '2026-09-15 14:30:00',
    },
    {
        id: 3,
        name: 'Executive Suite',
        slug: 'executive-suite',
        description: 'Luxurious suite with private living room lounge, plush king bed, dresser, and luxury amenities.',
        status: 'active',
        total_rooms: 4,
        active_rooms: 4,
        created_at: '2026-09-02 11:00:00',
        updated_at: '2026-09-20 09:45:00',
    },
    {
        id: 4,
        name: 'Family Dormitory',
        slug: 'family-dormitory',
        description: 'Group accommodation with multiple bunk beds, individual lockers, and shared bath facilities.',
        status: 'active',
        total_rooms: 2,
        active_rooms: 2,
        created_at: '2026-09-03 16:20:00',
        updated_at: '2026-09-03 16:20:00',
    },
    {
        id: 5,
        name: 'Standard Non AC',
        slug: 'standard-non-ac',
        description: 'Budget-friendly room with natural ventilation, ceiling fan, and standard amenities.',
        status: 'inactive',
        total_rooms: 0,
        active_rooms: 0,
        created_at: '2026-09-10 12:00:00',
        updated_at: '2026-09-25 18:00:00',
    }
];

/**
 * Generate slug helper matching backend regex
 */
const generateSlug = (text) => {
    return text
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
};

/**
 * Room Types Admin Module
 */
const RoomTypesModule = () => {
    const [roomTypes, setRoomTypes] = useState(INITIAL_ROOM_TYPES);
    const [searchQuery, setSearchQuery] = useState('');
    const [statusFilter, setStatusFilter] = useState('all'); // 'all' | 'active' | 'inactive'
    
    // Backend Pagination state
    const [currentPage, setCurrentPage] = useState(1);
    const [pageSize, setPageSize] = useState(5);
    const [totalRecords, setTotalRecords] = useState(INITIAL_ROOM_TYPES.length);
    const [isLoading, setIsLoading] = useState(false);
    
    // Modals state
    const [isFormModalOpen, setIsFormModalOpen] = useState(false);
    const [formMode, setFormMode] = useState('create'); // 'create' | 'edit'
    const [editingRoomType, setEditingRoomType] = useState(null);
    
    const [isViewModalOpen, setIsViewModalOpen] = useState(false);
    const [viewingRoomType, setViewingRoomType] = useState(null);

    const [isDeleteModalOpen, setIsDeleteModalOpen] = useState(false);
    const [deletingRoomType, setDeletingRoomType] = useState(null);

    // Form inputs state
    const [formData, setFormData] = useState({
        name: '',
        description: '',
        status: 'active',
    });
    const [formErrors, setFormErrors] = useState({});
    const [toastMessage, setToastMessage] = useState(null);

    const [isSubmitting, setIsSubmitting] = useState(false);

    // Auto-dismiss toast
    const showToast = (msg, type = 'success') => {
        setToastMessage({ text: msg, type });
        setTimeout(() => {
            setToastMessage(null);
        }, 3500);
    };

    /**
     * Fetch Room Types using backend pagination endpoint
     */
    const fetchRoomTypes = useCallback(async (page = currentPage, limit = pageSize, search = searchQuery, status = statusFilter) => {
        setIsLoading(true);
        try {
            const params = new URLSearchParams();
            params.append('page', String(page));
            params.append('limit', String(limit));
            if (search && search.trim()) params.append('search', search.trim());
            if (status && status !== 'all') params.append('status', status);

            const res = await roomTypeApi.list(params.toString());
            if (res.success && res.data?.status && Array.isArray(res.data.data)) {
                setRoomTypes(res.data.data);
                if (res.data.pagination) {
                    setTotalRecords(res.data.pagination.total_records ?? res.data.data.length);
                }
            } else if (res.data?.data && Array.isArray(res.data.data)) {
                setRoomTypes(res.data.data);
                if (res.data.pagination) {
                    setTotalRecords(res.data.pagination.total_records ?? res.data.data.length);
                }
            }
        } catch (err) {
            console.warn('Backend room_type fetch note:', err.message);
        } finally {
            setIsLoading(false);
        }
    }, [currentPage, pageSize, searchQuery, statusFilter]);

    useEffect(() => {
        fetchRoomTypes(currentPage, pageSize, searchQuery, statusFilter);
    }, [fetchRoomTypes, currentPage, pageSize, searchQuery, statusFilter]);

    // Summary counts
    const stats = useMemo(() => {
        const total = totalRecords || roomTypes.length;
        const active = roomTypes.filter((r) => r.status === 'active').length;
        const inactive = roomTypes.filter((r) => r.status === 'inactive').length;
        const totalRooms = roomTypes.reduce((sum, r) => sum + (r.total_rooms || 0), 0);
        return { total, active, inactive, totalRooms };
    }, [roomTypes, totalRecords]);

    // Validation matching backend rules
    const validateForm = () => {
        const errors = {};
        const trimmedName = formData.name.trim();

        if (!trimmedName) {
            errors.name = 'Room type name is required.';
        } else if (trimmedName.length < 2) {
            errors.name = 'Room type name must be at least 2 characters.';
        } else if (trimmedName.length > 100) {
            errors.name = 'Room type name cannot exceed 100 characters.';
        } else {
            // Check uniqueness
            const duplicate = roomTypes.find(
                (r) =>
                    r.name.toLowerCase() === trimmedName.toLowerCase() &&
                    (formMode === 'create' || r.id !== editingRoomType?.id)
            );
            if (duplicate) {
                errors.name = 'A room type with this name already exists.';
            }
        }

        if (formData.description && formData.description.length > 1000) {
            errors.description = 'Description cannot exceed 1000 characters.';
        }

        if (!['active', 'inactive'].includes(formData.status)) {
            errors.status = 'Status must be active or inactive.';
        }

        setFormErrors(errors);
        return Object.keys(errors).length === 0;
    };

    // Open Add Modal
    const handleOpenAdd = () => {
        setFormMode('create');
        setEditingRoomType(null);
        setFormData({
            name: '',
            description: '',
            status: 'active',
        });
        setFormErrors({});
        setIsFormModalOpen(true);
    };

    // Open Edit Modal
    const handleOpenEdit = (rt) => {
        setFormMode('edit');
        setEditingRoomType(rt);
        setFormData({
            name: rt.name,
            description: rt.description || '',
            status: rt.status || 'active',
        });
        setFormErrors({});
        setIsFormModalOpen(true);
    };

    // Open View Details Modal
    const handleOpenView = (rt) => {
        setViewingRoomType(rt);
        setIsViewModalOpen(true);
    };

    // Open Delete Modal
    const handleOpenDelete = (rt) => {
        setDeletingRoomType(rt);
        setIsDeleteModalOpen(true);
    };

    // Handle Form Submit (Create / Update)
    const handleFormSubmit = async (e) => {
        e.preventDefault();
        if (!validateForm()) return;

        const trimmedName = formData.name.trim();
        const payload = {
            name: trimmedName,
            description: formData.description.trim() || null,
            status: formData.status,
        };

        setIsSubmitting(true);
        try {
            if (formMode === 'create') {
                const res = await roomTypeApi.create(payload);
                if (res.success && res.data?.status) {
                    showToast(`Room type "${trimmedName}" created successfully!`, 'success');
                    setIsFormModalOpen(false);
                    fetchRoomTypes(1, pageSize, searchQuery, statusFilter);
                } else {
                    setFormErrors({ submit: res.error || res.data?.message || 'Failed to create room type.' });
                }
            } else {
                payload.room_type_id = Number(editingRoomType.id);
                const res = await roomTypeApi.update(payload);
                if (res.success && res.data?.status) {
                    showToast(`Room type "${trimmedName}" updated successfully!`, 'success');
                    setIsFormModalOpen(false);
                    fetchRoomTypes(currentPage, pageSize, searchQuery, statusFilter);
                } else {
                    setFormErrors({ submit: res.error || res.data?.message || 'Failed to update room type.' });
                }
            }
        } catch (err) {
            setFormErrors({ submit: err.message || 'An unexpected error occurred.' });
        } finally {
            setIsSubmitting(false);
        }
    };

    // Handle Confirm Delete
    const handleConfirmDelete = async () => {
        if (!deletingRoomType) return;
        setIsSubmitting(true);
        try {
            const res = await roomTypeApi.delete(deletingRoomType.id);
            if (res.success && res.data?.status) {
                showToast(`Room type "${deletingRoomType.name}" deleted successfully!`, 'success');
                setIsDeleteModalOpen(false);
                setDeletingRoomType(null);
                fetchRoomTypes(currentPage, pageSize, searchQuery, statusFilter);
            } else {
                showToast(res.error || res.data?.message || 'Failed to delete room type.', 'error');
            }
        } catch (err) {
            showToast(err.message || 'An unexpected error occurred.', 'error');
        } finally {
            setIsSubmitting(false);
        }
    };

    // Search options for react-select
    const searchSelectOptions = useMemo(() => {
        return roomTypes.map((rt) => ({
            value: rt.name,
            label: `${rt.name} (${rt.slug})`,
        }));
    }, [roomTypes]);

    const currentSelectValue = useMemo(() => {
        if (!searchQuery) return null;
        return searchSelectOptions.find((opt) => opt.value.toLowerCase() === searchQuery.toLowerCase()) || {
            value: searchQuery,
            label: searchQuery,
        };
    }, [searchQuery, searchSelectOptions]);

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
                    <h2 className="dashboard-page-header__title">Room Types Management</h2>
                    <p className="dashboard-page-header__desc">
                        Define and organize lodge accommodation categories, descriptions, and availability status.
                    </p>
                </div>
                <div className="dashboard-page-header__actions">
                    <button
                        type="button"
                        className="admin-primary-btn"
                        onClick={handleOpenAdd}
                        id="add-room-type-btn"
                    >
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round">
                            <line x1="12" y1="5" x2="12" y2="19" />
                            <line x1="5" y1="12" x2="19" y2="12" />
                        </svg>
                        <span>Add Room Type</span>
                    </button>
                </div>
            </div>

            {/* Stats Overview Bar */}
            <div className="room-types-stats-grid">
                <div className="room-type-stat-card">
                    <div className="room-type-stat-card__icon room-type-stat-card__icon--gold">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                            <rect x="3" y="3" width="7" height="7" />
                            <rect x="14" y="3" width="7" height="7" />
                            <rect x="14" y="14" width="7" height="7" />
                            <rect x="3" y="14" width="7" height="7" />
                        </svg>
                    </div>
                    <div className="room-type-stat-card__info">
                        <span className="room-type-stat-card__label">Total Categories</span>
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
                        <span className="room-type-stat-card__label">Active Types</span>
                        <span className="room-type-stat-card__value">{stats.active}</span>
                    </div>
                </div>

                <div className="room-type-stat-card">
                    <div className="room-type-stat-card__icon room-type-stat-card__icon--slate">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                            <circle cx="12" cy="12" r="10" />
                            <line x1="4.93" y1="4.93" x2="19.07" y2="19.07" />
                        </svg>
                    </div>
                    <div className="room-type-stat-card__info">
                        <span className="room-type-stat-card__label">Inactive Types</span>
                        <span className="room-type-stat-card__value">{stats.inactive}</span>
                    </div>
                </div>

                <div className="room-type-stat-card">
                    <div className="room-type-stat-card__icon room-type-stat-card__icon--blue">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                            <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" />
                            <polyline points="9 22 9 12 15 12 15 22" />
                        </svg>
                    </div>
                    <div className="room-type-stat-card__info">
                        <span className="room-type-stat-card__label">Total Assigned Rooms</span>
                        <span className="room-type-stat-card__value">{stats.totalRooms}</span>
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
                        placeholder="Search or select room type..."
                        isClearable
                        isSearchable
                        styles={reactSelectStyles}
                        className="react-select-container"
                        classNamePrefix="react-select"
                        noOptionsMessage={({ inputValue }) =>
                            inputValue ? `No room type matches "${inputValue}"` : 'No options'
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
                        <p style={{ fontSize: '0.9rem', fontWeight: 500 }}>Loading room types from server...</p>
                    </div>
                ) : roomTypes.length === 0 ? (
                    <div className="dashboard-empty-state">
                        <div className="dashboard-empty-state__icon-wrap">
                            <svg width="42" height="42" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round">
                                <rect x="3" y="3" width="18" height="18" rx="2" />
                                <path d="M9 3v18" />
                                <path d="M14 9h4" />
                                <path d="M14 15h4" />
                            </svg>
                        </div>
                        <h4 className="dashboard-empty-state__title">No room types found</h4>
                        <p className="dashboard-empty-state__desc">
                            {searchQuery || statusFilter !== 'all'
                                ? 'No room types matched your filter criteria. Try clearing search.'
                                : 'No room types have been created yet. Click below to add your first type.'}
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
                                + Add First Room Type
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
                                        <th>Room Type Name</th>
                                        <th>Slug</th>
                                        <th>Description</th>
                                        <th>Status</th>
                                        <th>Rooms</th>
                                        <th style={{ textAlign: 'right', width: '130px' }}>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {roomTypes.map((rt) => (
                                        <tr key={rt.id} className="admin-table-row">
                                            <td className="admin-table-cell--id">#{rt.id}</td>
                                            <td>
                                                <div className="room-type-name-cell">
                                                    <span className="room-type-name-text">{rt.name}</span>
                                                </div>
                                            </td>
                                            <td>
                                                <span className="room-type-slug-badge">{rt.slug}</span>
                                            </td>
                                            <td className="admin-table-cell--desc">
                                                <p className="room-type-desc-truncate">
                                                    {rt.description || <span className="text-muted-italic">No description</span>}
                                                </p>
                                            </td>
                                            <td>
                                                <span className={`admin-status-badge admin-status-badge--${rt.status}`}>
                                                    <span className="admin-status-dot" />
                                                    {rt.status === 'active' ? 'Active' : 'Inactive'}
                                                </span>
                                            </td>
                                            <td>
                                                <div className="room-type-count-cell">
                                                    <span className="room-type-count-active">{rt.active_rooms || 0}</span>
                                                    <span className="room-type-count-divider">/</span>
                                                    <span className="room-type-count-total">{rt.total_rooms || 0} Rooms</span>
                                                </div>
                                            </td>
                                            <td style={{ textAlign: 'right' }}>
                                                <div className="admin-table-actions">
                                                    <button
                                                        type="button"
                                                        className="admin-action-btn admin-action-btn--view"
                                                        onClick={() => handleOpenView(rt)}
                                                        title="View Details"
                                                        aria-label={`View ${rt.name}`}
                                                    >
                                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                                            <path d="M1 12 s4 -8 11 -8 11 8 11 8 -4 8 -11 8 -11 -8 -11 -8 z" />
                                                            <circle cx="12" cy="12" r="3" />
                                                        </svg>
                                                    </button>
                                                    <button
                                                        type="button"
                                                        className="admin-action-btn admin-action-btn--edit"
                                                        onClick={() => handleOpenEdit(rt)}
                                                        title="Edit Room Type"
                                                        aria-label={`Edit ${rt.name}`}
                                                    >
                                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                                            <path d="M11 4 H4 a2 2 0 0 0 -2 2 v14 a2 2 0 0 0 2 2 h14 a2 2 0 0 0 2 -2 v-7" />
                                                            <path d="M18.5 2.5 a2.121 2.121 0 0 1 3 3 L12 15 l-4 1 1 -4 9.5 -9.5 z" />
                                                        </svg>
                                                    </button>
                                                    <button
                                                        type="button"
                                                        className="admin-action-btn admin-action-btn--delete"
                                                        onClick={() => handleOpenDelete(rt)}
                                                        title="Delete Room Type"
                                                        aria-label={`Delete ${rt.name}`}
                                                    >
                                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                                            <polyline points="3 6 5 6 21 6" />
                                                            <path d="M19 6 v14 a2 2 0 0 1 -2 2 H7 a2 2 0 0 1 -2 -2 V6 m3 0 V4 a2 2 0 0 1 2 -2 h4 a2 2 0 0 1 2 2 v2" />
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

            {/* ─── ADD / EDIT MODAL ─── */}
            {isFormModalOpen && (
                <div className="admin-modal-overlay" onClick={() => setIsFormModalOpen(false)}>
                    <div className="admin-modal" onClick={(e) => e.stopPropagation()} role="dialog" aria-modal="true">
                        <div className="admin-modal__header">
                            <div className="admin-modal__title-wrap">
                                <h3 className="admin-modal__title">
                                    {formMode === 'create' ? 'Create New Room Type' : 'Edit Room Type'}
                                </h3>
                                <p className="admin-modal__subtitle">
                                    {formMode === 'create'
                                        ? 'Add a new accommodation category to the lodge repository.'
                                        : `Updating specifications for ${editingRoomType?.name}`}
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

                        <form onSubmit={handleFormSubmit}>
                            <div className="admin-modal__body">
                                {formErrors.submit && (
                                    <div style={{ padding: '10px 14px', borderRadius: '8px', background: '#fee2e2', color: '#b91c1c', fontSize: '0.85rem', marginBottom: '16px' }}>
                                        {formErrors.submit}
                                    </div>
                                )}
                                {/* Room Type Name */}
                                <div className="admin-form-group">
                                    <label className="admin-form-label" htmlFor="room-type-name">
                                        Room Type Name <span className="admin-form-required">*</span>
                                    </label>
                                    <input
                                        id="room-type-name"
                                        type="text"
                                        className={`admin-form-input ${formErrors.name ? 'admin-form-input--error' : ''}`}
                                        placeholder="e.g. Deluxe Room, Executive Suite"
                                        value={formData.name}
                                        onChange={(e) => {
                                            setFormData((prev) => ({ ...prev, name: e.target.value }));
                                            if (formErrors.name) setFormErrors((prev) => ({ ...prev, name: null }));
                                        }}
                                        maxLength={100}
                                        autoFocus
                                    />
                                    {formErrors.name && (
                                        <span className="admin-form-error">
                                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                                            {formErrors.name}
                                        </span>
                                    )}
                                    <span className="admin-form-helper">
                                        Generated Slug: <code>{generateSlug(formData.name) || '—'}</code>
                                    </span>
                                </div>

                                {/* Status Selection */}
                                <div className="admin-form-group">
                                    <label className="admin-form-label" htmlFor="room-type-status">
                                        Status <span className="admin-form-required">*</span>
                                    </label>
                                    <select
                                        id="room-type-status"
                                        className="admin-form-select"
                                        value={formData.status}
                                        onChange={(e) => setFormData((prev) => ({ ...prev, status: e.target.value }))}
                                    >
                                        <option value="active">Active (Available for booking & room assignment)</option>
                                        <option value="inactive">Inactive (Disabled / Hidden)</option>
                                    </select>
                                </div>

                                {/* Description */}
                                <div className="admin-form-group">
                                    <div className="admin-form-label-row">
                                        <label className="admin-form-label" htmlFor="room-type-description">
                                            Description
                                        </label>
                                        <span className="admin-char-count">
                                            {(formData.description || '').length} / 1000
                                        </span>
                                    </div>
                                    <textarea
                                        id="room-type-description"
                                        rows={4}
                                        className={`admin-form-textarea ${formErrors.description ? 'admin-form-input--error' : ''}`}
                                        placeholder="Provide detailed information regarding room capacity, key highlights, amenities, and furnishings..."
                                        value={formData.description}
                                        onChange={(e) => {
                                            setFormData((prev) => ({ ...prev, description: e.target.value }));
                                            if (formErrors.description) setFormErrors((prev) => ({ ...prev, description: null }));
                                        }}
                                        maxLength={1000}
                                    />
                                    {formErrors.description && (
                                        <span className="admin-form-error">{formErrors.description}</span>
                                    )}
                                </div>
                            </div>

                            {/* Modal Footer Actions */}
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
                                    {formMode === 'create' ? 'Create Room Type' : 'Save Changes'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* ─── VIEW DETAILS MODAL ─── */}
            {isViewModalOpen && viewingRoomType && (
                <div className="admin-modal-overlay" onClick={() => setIsViewModalOpen(false)}>
                    <div className="admin-modal admin-modal--view" onClick={(e) => e.stopPropagation()} role="dialog" aria-modal="true">
                        <div className="admin-modal__header">
                            <div className="admin-modal__title-wrap">
                                <div className="admin-modal__badge-row">
                                    <span className="room-type-id-pill">ID #{viewingRoomType.id}</span>
                                    <span className={`admin-status-badge admin-status-badge--${viewingRoomType.status}`}>
                                        <span className="admin-status-dot" />
                                        {viewingRoomType.status === 'active' ? 'Active' : 'Inactive'}
                                    </span>
                                </div>
                                <h3 className="admin-modal__title">{viewingRoomType.name}</h3>
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
                                    <span className="room-type-detail-label">Slug / Identifier</span>
                                    <span className="room-type-detail-val code">{viewingRoomType.slug}</span>
                                </div>
                                <div className="room-type-detail-item">
                                    <span className="room-type-detail-label">Assigned Rooms</span>
                                    <span className="room-type-detail-val">
                                        <strong>{viewingRoomType.active_rooms || 0}</strong> Active / <strong>{viewingRoomType.total_rooms || 0}</strong> Total
                                    </span>
                                </div>
                                <div className="room-type-detail-item full-width">
                                    <span className="room-type-detail-label">Description</span>
                                    <p className="room-type-detail-text">
                                        {viewingRoomType.description || 'No description provided for this room type.'}
                                    </p>
                                </div>
                                <div className="room-type-detail-item">
                                    <span className="room-type-detail-label">Created At</span>
                                    <span className="room-type-detail-val text-muted">{viewingRoomType.created_at || '—'}</span>
                                </div>
                                <div className="room-type-detail-item">
                                    <span className="room-type-detail-label">Last Updated</span>
                                    <span className="room-type-detail-val text-muted">{viewingRoomType.updated_at || '—'}</span>
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
                                    handleOpenEdit(viewingRoomType);
                                }}
                            >
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M11 4 H4 a2 2 0 0 0 -2 2 v14 a2 2 0 0 0 2 2 h14 a2 2 0 0 0 2 -2 v-7" /><path d="M18.5 2.5 a2.121 2.121 0 0 1 3 3 L12 15 l-4 1 1 -4 9.5 -9.5 z" /></svg>
                                Edit Room Type
                            </button>
                        </div>
                    </div>
                </div>
            )}

            {/* ─── DELETE CONFIRMATION MODAL ─── */}
            {isDeleteModalOpen && deletingRoomType && (
                <div className="admin-modal-overlay" onClick={() => setIsDeleteModalOpen(false)}>
                    <div className="admin-modal admin-modal--danger" onClick={(e) => e.stopPropagation()} role="dialog" aria-modal="true">
                        <div className="admin-modal__header">
                            <div className="admin-modal__danger-icon">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#dc2626" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round">
                                    <circle cx="12" cy="12" r="10" />
                                    <line x1="12" y1="8" x2="12" y2="12" />
                                    <line x1="12" y1="16" x2="12.01" y2="16" />
                                </svg>
                            </div>
                            <div className="admin-modal__title-wrap">
                                <h3 className="admin-modal__title">Delete Room Type</h3>
                                <p className="admin-modal__subtitle">This action cannot be undone.</p>
                            </div>
                        </div>

                        <div className="admin-modal__body">
                            <p className="admin-delete-prompt">
                                Are you sure you want to permanently delete <strong>"{deletingRoomType.name}"</strong>?
                            </p>
                            {deletingRoomType.total_rooms > 0 && (
                                <div className="admin-delete-warning-box">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#b45309" strokeWidth="2"><path d="M10.29 3.86 L1.82 18 a2 2 0 0 0 1.71 3 h16.94 a2 2 0 0 0 1.71 -3 L13.71 3.86 a2 2 0 0 0 -3.42 0 z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                                    <span>
                                        <strong>Notice:</strong> This room type currently has <strong>{deletingRoomType.total_rooms} assigned rooms</strong>.
                                    </span>
                                </div>
                            )}
                        </div>

                        <div className="admin-modal__footer">
                            <button
                                type="button"
                                className="admin-secondary-btn"
                                onClick={() => setIsDeleteModalOpen(false)}
                            >
                                Cancel
                            </button>
                            <button
                                type="button"
                                className="admin-danger-btn"
                                onClick={handleConfirmDelete}
                            >
                                Delete Room Type
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
};

export default RoomTypesModule;
