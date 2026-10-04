import React, { useState, useMemo, useEffect, useCallback } from 'react';
import Select from 'react-select';
import Pagination from './Pagination';
import api, { hallFacilityApi } from '../services/api';

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

// Backend regex for facility name: letters, numbers, spaces, and allowed symbols: & ( ) . , / -
const FACILITY_NAME_REGEX = /^[A-Za-z0-9&().,\/-]+(?: [A-Za-z0-9&().,\/-]+)*$/;

/**
 * Hall Facilities Admin Module
 * Uses identical design system, classes, and layouts as Room Types and Rooms.
 */
const HallFacilitiesModule = () => {
    const [facilities, setFacilities] = useState([]);
    const [searchQuery, setSearchQuery] = useState('');
    const [statusFilter, setStatusFilter] = useState('all'); // 'all' | 'active' | 'inactive'

    // Server-side Pagination state
    const [currentPage, setCurrentPage] = useState(1);
    const [pageSize, setPageSize] = useState(10);
    const [totalRecords, setTotalRecords] = useState(0);
    const [totalPages, setTotalPages] = useState(1);
    const [isLoading, setIsLoading] = useState(false);

    // Modals state
    const [isFormModalOpen, setIsFormModalOpen] = useState(false);
    const [formMode, setFormMode] = useState('create'); // 'create' | 'edit'
    const [editingFacility, setEditingFacility] = useState(null);

    const [isViewModalOpen, setIsViewModalOpen] = useState(false);
    const [viewingFacility, setViewingFacility] = useState(null);

    const [isDeleteModalOpen, setIsDeleteModalOpen] = useState(false);
    const [deletingFacility, setDeletingFacility] = useState(null);
    const [deleteErrorMessage, setDeleteErrorMessage] = useState(null);

    // Form inputs state
    const [formData, setFormData] = useState({
        name: '',
        status: 'active',
    });
    const [formErrors, setFormErrors] = useState({});
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [toastMessage, setToastMessage] = useState(null);

    // Auto-dismiss toast
    const showToast = (msg, type = 'success') => {
        setToastMessage({ text: msg, type });
        setTimeout(() => {
            setToastMessage(null);
        }, 3500);
    };

    /**
     * Fetch Hall Facilities using backend pagination endpoint
     */
    const fetchFacilities = useCallback(async (
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
            if (search && search.trim()) {
                params.append('search', search.trim());
            }
            if (status && status !== 'all') {
                params.append('status', status);
            }

            const res = await hallFacilityApi.list(params.toString());
            if (res.success && res.data?.status && Array.isArray(res.data.data)) {
                setFacilities(res.data.data);
                if (res.data.pagination) {
                    setTotalRecords(res.data.pagination.total_records ?? res.data.data.length);
                    setTotalPages(res.data.pagination.total_pages ?? 1);
                }
            } else if (res.data?.data && Array.isArray(res.data.data)) {
                setFacilities(res.data.data);
                if (res.data.pagination) {
                    setTotalRecords(res.data.pagination.total_records ?? res.data.data.length);
                    setTotalPages(res.data.pagination.total_pages ?? 1);
                }
            } else {
                setFacilities([]);
                setTotalRecords(0);
                setTotalPages(1);
            }
        } catch (err) {
            console.warn('Backend hall_facilities fetch error:', err.message);
            showToast('Failed to load hall facilities from server.', 'error');
        } finally {
            setIsLoading(false);
        }
    }, [currentPage, pageSize, searchQuery, statusFilter]);

    useEffect(() => {
        fetchFacilities(currentPage, pageSize, searchQuery, statusFilter);
    }, [fetchFacilities, currentPage, pageSize, searchQuery, statusFilter]);

    // Summary counts
    const stats = useMemo(() => {
        const total = totalRecords || facilities.length;
        const active = facilities.filter((f) => f.status === 'active').length;
        const inactive = facilities.filter((f) => f.status === 'inactive').length;
        return { total, active, inactive };
    }, [facilities, totalRecords]);

    // Validation matching backend rules
    const validateForm = () => {
        const errors = {};
        const trimmedName = formData.name.trim();

        if (!trimmedName) {
            errors.name = 'Facility name is required.';
        } else if (trimmedName.length < 2) {
            errors.name = 'Facility name must be at least 2 characters.';
        } else if (trimmedName.length > 150) {
            errors.name = 'Facility name cannot exceed 150 characters.';
        } else if (!FACILITY_NAME_REGEX.test(trimmedName)) {
            errors.name = 'Only letters, numbers, spaces, and & ( ) . , / - are allowed.';
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
        setEditingFacility(null);
        setFormData({
            name: '',
            status: 'active',
        });
        setFormErrors({});
        setIsFormModalOpen(true);
    };

    // Open Edit Modal
    const handleOpenEdit = (facility) => {
        setFormMode('edit');
        setEditingFacility(facility);
        setFormData({
            name: facility.name,
            status: facility.status || 'active',
        });
        setFormErrors({});
        setIsFormModalOpen(true);
    };

    // Open View Details Modal
    const handleOpenView = (facility) => {
        setViewingFacility(facility);
        setIsViewModalOpen(true);
    };

    // Open Delete Modal
    const handleOpenDelete = (facility) => {
        setDeletingFacility(facility);
        setDeleteErrorMessage(null);
        setIsDeleteModalOpen(true);
    };

    // Handle Form Submit (Create or Update)
    const handleFormSubmit = async (e) => {
        e.preventDefault();
        if (!validateForm()) return;

        setIsSubmitting(true);
        try {
            if (formMode === 'create') {
                const payload = {
                    name: formData.name.trim(),
                    status: formData.status,
                };
                const res = await hallFacilityApi.create(payload);
                if (res.success && res.data?.status) {
                    showToast('Hall facility created successfully!', 'success');
                    setIsFormModalOpen(false);
                    fetchFacilities(1, pageSize, searchQuery, statusFilter);
                } else {
                    setFormErrors({ submit: res.error || res.data?.message || 'Failed to create hall facility.' });
                }
            } else {
                const payload = {
                    facility_id: Number(editingFacility.id),
                    name: formData.name.trim(),
                    status: formData.status,
                };
                const res = await hallFacilityApi.update(payload);
                if (res.success && res.data?.status) {
                    showToast('Hall facility updated successfully!', 'success');
                    setIsFormModalOpen(false);
                    fetchFacilities(currentPage, pageSize, searchQuery, statusFilter);
                } else {
                    setFormErrors({ submit: res.error || res.data?.message || 'Failed to update hall facility.' });
                }
            }
        } catch (err) {
            setFormErrors({ submit: err.message || 'An unexpected error occurred.' });
        } finally {
            setIsSubmitting(false);
        }
    };

    // Quick Status Toggle
    const handleToggleStatus = async (facility) => {
        const newStatus = facility.status === 'active' ? 'inactive' : 'active';
        try {
            const payload = {
                facility_id: Number(facility.id),
                name: facility.name,
                status: newStatus,
            };
            const res = await hallFacilityApi.update(payload);
            if (res.success && res.data?.status) {
                showToast(`Facility status updated to ${newStatus}.`, 'success');
                fetchFacilities(currentPage, pageSize, searchQuery, statusFilter);
            } else {
                showToast(res.error || res.data?.message || 'Could not update status.', 'error');
            }
        } catch (err) {
            showToast(err.message || 'Failed to toggle status.', 'error');
        }
    };

    // Confirm Delete
    const handleConfirmDelete = async () => {
        if (!deletingFacility) return;
        setIsSubmitting(true);
        setDeleteErrorMessage(null);
        try {
            const res = await hallFacilityApi.delete(deletingFacility.id);
            if (res.success && res.data?.status) {
                showToast('Hall facility deleted successfully.', 'success');
                setIsDeleteModalOpen(false);
                setDeletingFacility(null);
                fetchFacilities(currentPage, pageSize, searchQuery, statusFilter);
            } else {
                const errorText = res.data?.message || res.error || 'Failed to delete hall facility.';
                const suggestion = res.data?.suggestion ? ` Suggestion: ${res.data.suggestion}` : '';
                setDeleteErrorMessage(`${errorText}${suggestion}`);
            }
        } catch (err) {
            setDeleteErrorMessage(err.message || 'Failed to delete hall facility.');
        } finally {
            setIsSubmitting(false);
        }
    };

    // React Select search suggestions built from fetched facilities
    const searchSelectOptions = useMemo(() => {
        return facilities.map((f) => ({
            value: f.name,
            label: f.name,
        }));
    }, [facilities]);

    const currentSelectValue = useMemo(() => {
        if (!searchQuery) return null;
        return searchSelectOptions.find((opt) => opt.value.toLowerCase() === searchQuery.toLowerCase()) || {
            value: searchQuery,
            label: searchQuery,
        };
    }, [searchQuery, searchSelectOptions]);

    const handleClearFilters = () => {
        setSearchQuery('');
        setStatusFilter('all');
        setCurrentPage(1);
    };

    return (
        <div className="room-types-module">
            {/* Toast Notification */}
            {toastMessage && (
                <div className={`admin-toast admin-toast--${toastMessage.type}`}>
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                        {toastMessage.type === 'error' ? (
                            <>
                                <circle cx="12" cy="12" r="10" />
                                <line x1="12" y1="8" x2="12" y2="12" />
                                <line x1="12" y1="16" x2="12.01" y2="16" />
                            </>
                        ) : (
                            <>
                                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
                                <polyline points="22 4 12 14.01 9 11.01" />
                            </>
                        )}
                    </svg>
                    <span>{toastMessage.text}</span>
                </div>
            )}

            {/* Page Header */}
            <div className="dashboard-page-header">
                <div className="dashboard-page-header__info">
                    <h2 className="dashboard-page-header__title">Hall Facilities Management</h2>
                    <p className="dashboard-page-header__desc">
                        Define and organize equipment, audio-visual setups, dining amenities, and features for banquet and event halls.
                    </p>
                </div>
                <div className="dashboard-page-header__actions">
                    <button
                        type="button"
                        className="admin-primary-btn"
                        onClick={handleOpenAdd}
                        id="add-facility-btn"
                    >
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round">
                            <line x1="12" y1="5" x2="12" y2="19" />
                            <line x1="5" y1="12" x2="19" y2="12" />
                        </svg>
                        <span>Add Facility</span>
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
                        <span className="room-type-stat-card__label">Total Facilities</span>
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
                        <span className="room-type-stat-card__label">Active Facilities</span>
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
                        <span className="room-type-stat-card__label">Inactive Facilities</span>
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
                        placeholder="Search or select facility..."
                        isClearable
                        isSearchable
                        styles={reactSelectStyles}
                        className="react-select-container"
                        classNamePrefix="react-select"
                        noOptionsMessage={({ inputValue }) =>
                            inputValue ? `No facility matches "${inputValue}"` : 'No options'
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
                        <p style={{ fontSize: '0.9rem', fontWeight: 500 }}>Loading facilities from server...</p>
                    </div>
                ) : facilities.length === 0 ? (
                    <div className="dashboard-empty-state">
                        <div className="dashboard-empty-state__icon-wrap">
                            <svg width="42" height="42" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round">
                                <rect x="3" y="3" width="18" height="18" rx="2" />
                                <path d="M9 3v18" />
                                <path d="M14 9h4" />
                                <path d="M14 15h4" />
                            </svg>
                        </div>
                        <h4 className="dashboard-empty-state__title">No facilities found</h4>
                        <p className="dashboard-empty-state__desc">
                            {searchQuery || statusFilter !== 'all'
                                ? 'No facilities matched your filter criteria. Try clearing search.'
                                : 'No hall facilities have been created yet. Click below to add your first facility.'}
                        </p>
                        {searchQuery || statusFilter !== 'all' ? (
                            <button
                                type="button"
                                className="admin-secondary-btn"
                                onClick={handleClearFilters}
                            >
                                Reset Filters
                            </button>
                        ) : (
                            <button
                                type="button"
                                className="admin-primary-btn"
                                onClick={handleOpenAdd}
                            >
                                + Add First Facility
                            </button>
                        )}
                    </div>
                ) : (
                    <>
                        <div className="admin-table-responsive">
                            <table className="admin-table">
                                <thead>
                                    <tr>
                                        <th style={{ width: '80px' }}>#ID</th>
                                        <th>Facility Name</th>
                                        <th>Status</th>
                                        <th>Created At</th>
                                        <th style={{ textAlign: 'right', width: '130px' }}>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {facilities.map((facility) => (
                                        <tr key={facility.id} className="admin-table-row">
                                            <td className="admin-table-cell--id">#{facility.id}</td>
                                            <td>
                                                <div className="room-type-name-cell">
                                                    <span className="room-type-name-text">{facility.name}</span>
                                                </div>
                                            </td>
                                            <td>
                                                <button
                                                    type="button"
                                                    onClick={() => handleToggleStatus(facility)}
                                                    className={`admin-status-badge admin-status-badge--${facility.status}`}
                                                    style={{ cursor: 'pointer' }}
                                                    title={`Click to switch to ${facility.status === 'active' ? 'inactive' : 'active'}`}
                                                >
                                                    <span className="admin-status-dot" />
                                                    {facility.status === 'active' ? 'Active' : 'Inactive'}
                                                </button>
                                            </td>
                                            <td className="admin-table-cell--desc" style={{ color: 'var(--text-secondary)', fontSize: '0.85rem' }}>
                                                {facility.created_at
                                                    ? new Date(facility.created_at).toLocaleDateString('en-US', {
                                                          year: 'numeric',
                                                          month: 'short',
                                                          day: 'numeric',
                                                      })
                                                    : '—'}
                                            </td>
                                            <td style={{ textAlign: 'right' }}>
                                                <div className="admin-table-actions">
                                                    <button
                                                        type="button"
                                                        className="admin-action-btn admin-action-btn--view"
                                                        onClick={() => handleOpenView(facility)}
                                                        title="View Details"
                                                        aria-label={`View ${facility.name}`}
                                                    >
                                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                                            <path d="M1 12 s4 -8 11 -8 11 8 11 8 -4 8 -11 8 -11 -8 -11 -8 z" />
                                                            <circle cx="12" cy="12" r="3" />
                                                        </svg>
                                                    </button>
                                                    <button
                                                        type="button"
                                                        className="admin-action-btn admin-action-btn--edit"
                                                        onClick={() => handleOpenEdit(facility)}
                                                        title="Edit Facility"
                                                        aria-label={`Edit ${facility.name}`}
                                                    >
                                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                                            <path d="M11 4 H4 a2 2 0 0 0 -2 2 v14 a2 2 0 0 0 2 2 h14 a2 2 0 0 0 2 -2 v-7" />
                                                            <path d="M18.5 2.5 a2.121 2.121 0 0 1 3 3 L12 15 l-4 1 1 -4 9.5 -9.5 z" />
                                                        </svg>
                                                    </button>
                                                    <button
                                                        type="button"
                                                        className="admin-action-btn admin-action-btn--delete"
                                                        onClick={() => handleOpenDelete(facility)}
                                                        title="Delete Facility"
                                                        aria-label={`Delete ${facility.name}`}
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

                        {/* Pagination Integration */}
                        <Pagination
                            currentPage={currentPage}
                            totalItems={totalRecords}
                            pageSize={pageSize}
                            onPageChange={(page) => setCurrentPage(page)}
                            onPageSizeChange={(size) => {
                                setPageSize(size);
                                setCurrentPage(1);
                            }}
                            pageSizeOptions={[10, 20, 50, 100]}
                        />
                    </>
                )}
            </div>

            {/* ─── ADD / EDIT MODAL ─── */}
            {isFormModalOpen && (
                <div className="admin-modal-overlay" onClick={() => !isSubmitting && setIsFormModalOpen(false)}>
                    <div className="admin-modal" onClick={(e) => e.stopPropagation()} role="dialog" aria-modal="true">
                        <div className="admin-modal__header">
                            <div className="admin-modal__title-wrap">
                                <h3 className="admin-modal__title">
                                    {formMode === 'create' ? 'Create New Hall Facility' : 'Edit Hall Facility'}
                                </h3>
                                <p className="admin-modal__subtitle">
                                    {formMode === 'create'
                                        ? 'Add a new facility or feature available for event and banquet halls.'
                                        : `Updating configuration for ${editingFacility?.name}`}
                                </p>
                            </div>
                            <button
                                type="button"
                                className="admin-modal__close-btn"
                                onClick={() => !isSubmitting && setIsFormModalOpen(false)}
                                aria-label="Close dialog"
                            >
                                ✕
                            </button>
                        </div>

                        <form onSubmit={handleFormSubmit}>
                            <div className="admin-modal__body">
                                {/* Server submit error banner */}
                                {formErrors.submit && (
                                    <div className="admin-delete-warning-box" style={{ marginBottom: '16px', borderColor: '#fecaca', backgroundColor: '#fef2f2', color: '#991b1b' }}>
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#dc2626" strokeWidth="2">
                                            <circle cx="12" cy="12" r="10" />
                                            <line x1="12" y1="8" x2="12" y2="12" />
                                            <line x1="12" y1="16" x2="12.01" y2="16" />
                                        </svg>
                                        <span>{formErrors.submit}</span>
                                    </div>
                                )}

                                {/* Facility Name */}
                                <div className="admin-form-group">
                                    <label className="admin-form-label" htmlFor="facility-name">
                                        Facility Name <span className="admin-form-required">*</span>
                                    </label>
                                    <input
                                        id="facility-name"
                                        type="text"
                                        className={`admin-form-input ${formErrors.name ? 'admin-form-input--error' : ''}`}
                                        placeholder="e.g. HD LED Projector Screen, Banquet Stage, AC Hall"
                                        value={formData.name}
                                        onChange={(e) => {
                                            setFormData((prev) => ({ ...prev, name: e.target.value }));
                                            if (formErrors.name) setFormErrors((prev) => ({ ...prev, name: null }));
                                        }}
                                        maxLength={150}
                                        autoFocus
                                    />
                                    {formErrors.name ? (
                                        <span className="admin-form-error">
                                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                                            {formErrors.name}
                                        </span>
                                    ) : (
                                        <span className="admin-form-helper">
                                            Allowed: Letters, numbers, spaces, and & ( ) . , / - (2 to 150 characters)
                                        </span>
                                    )}
                                </div>

                                {/* Status Selection */}
                                <div className="admin-form-group">
                                    <label className="admin-form-label" htmlFor="facility-status">
                                        Status <span className="admin-form-required">*</span>
                                    </label>
                                    <select
                                        id="facility-status"
                                        className="admin-form-select"
                                        value={formData.status}
                                        onChange={(e) => setFormData((prev) => ({ ...prev, status: e.target.value }))}
                                    >
                                        <option value="active">Active (Available to assign to halls)</option>
                                        <option value="inactive">Inactive (Disabled / Hidden)</option>
                                    </select>
                                </div>
                            </div>

                            {/* Modal Footer Actions */}
                            <div className="admin-modal__footer">
                                <button
                                    type="button"
                                    className="admin-secondary-btn"
                                    onClick={() => setIsFormModalOpen(false)}
                                    disabled={isSubmitting}
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    className="admin-primary-btn"
                                    disabled={isSubmitting}
                                >
                                    {isSubmitting ? 'Saving...' : formMode === 'create' ? 'Create Facility' : 'Save Changes'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* ─── VIEW DETAILS MODAL ─── */}
            {isViewModalOpen && viewingFacility && (
                <div className="admin-modal-overlay" onClick={() => setIsViewModalOpen(false)}>
                    <div className="admin-modal admin-modal--view" onClick={(e) => e.stopPropagation()} role="dialog" aria-modal="true">
                        <div className="admin-modal__header">
                            <div className="admin-modal__title-wrap">
                                <div className="admin-modal__badge-row">
                                    <span className="room-type-id-pill">ID #{viewingFacility.id}</span>
                                    <span className={`admin-status-badge admin-status-badge--${viewingFacility.status}`}>
                                        <span className="admin-status-dot" />
                                        {viewingFacility.status === 'active' ? 'Active' : 'Inactive'}
                                    </span>
                                </div>
                                <h3 className="admin-modal__title">{viewingFacility.name}</h3>
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
                                    <span className="room-type-detail-label">Facility Name</span>
                                    <span className="room-type-detail-val">
                                        <strong>{viewingFacility.name}</strong>
                                    </span>
                                </div>
                                <div className="room-type-detail-item">
                                    <span className="room-type-detail-label">Status</span>
                                    <span
                                        className={`admin-status-badge admin-status-badge--${viewingFacility.status}`}
                                        style={{ width: 'fit-content', alignSelf: 'flex-start' }}
                                    >
                                        <span className="admin-status-dot" />
                                        {viewingFacility.status === 'active' ? 'Active' : 'Inactive'}
                                    </span>
                                </div>
                                <div className="room-type-detail-item">
                                    <span className="room-type-detail-label">Created At</span>
                                    <span className="room-type-detail-val text-muted">
                                        {viewingFacility.created_at
                                            ? new Date(viewingFacility.created_at).toLocaleString()
                                            : '—'}
                                    </span>
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
                                    handleOpenEdit(viewingFacility);
                                }}
                            >
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M11 4 H4 a2 2 0 0 0 -2 2 v14 a2 2 0 0 0 2 2 h14 a2 2 0 0 0 2 -2 v-7" /><path d="M18.5 2.5 a2.121 2.121 0 0 1 3 3 L12 15 l-4 1 1 -4 9.5 -9.5 z" /></svg>
                                Edit Facility
                            </button>
                        </div>
                    </div>
                </div>
            )}

            {/* ─── DELETE CONFIRMATION MODAL ─── */}
            {isDeleteModalOpen && deletingFacility && (
                <div className="admin-modal-overlay" onClick={() => !isSubmitting && setIsDeleteModalOpen(false)}>
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
                                <h3 className="admin-modal__title">Delete Hall Facility</h3>
                                <p className="admin-modal__subtitle">This action cannot be undone.</p>
                            </div>
                        </div>

                        <div className="admin-modal__body">
                            <p className="admin-delete-prompt">
                                Are you sure you want to permanently delete <strong>"{deletingFacility.name}"</strong>?
                            </p>

                            {deleteErrorMessage && (
                                <div className="admin-delete-warning-box">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#b45309" strokeWidth="2">
                                        <path d="M10.29 3.86 L1.82 18 a2 2 0 0 0 1.71 3 h16.94 a2 2 0 0 0 1.71 -3 L13.71 3.86 a2 2 0 0 0 -3.42 0 z"/>
                                        <line x1="12" y1="9" x2="12" y2="13"/>
                                        <line x1="12" y1="17" x2="12.01" y2="17"/>
                                    </svg>
                                    <span>
                                        <strong>Notice:</strong> {deleteErrorMessage}
                                    </span>
                                </div>
                            )}
                        </div>

                        <div className="admin-modal__footer">
                            <button
                                type="button"
                                className="admin-secondary-btn"
                                onClick={() => setIsDeleteModalOpen(false)}
                                disabled={isSubmitting}
                            >
                                Cancel
                            </button>
                            <button
                                type="button"
                                className="admin-danger-btn"
                                onClick={handleConfirmDelete}
                                disabled={isSubmitting}
                            >
                                {isSubmitting ? 'Deleting...' : 'Delete Facility'}
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
};

export default HallFacilitiesModule;
