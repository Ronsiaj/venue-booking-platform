import React, { useState, useMemo, useEffect, useCallback } from 'react';
import Select from 'react-select';
import Pagination from './Pagination';
import api from '../services/api';

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
 * Generate slug helper matching backend regex
 */
const generateSlug = (text) => {
    return text
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
};

/**
 * Hall Types Admin Module (Redesigned)
 */
const HallTypesModule = () => {
    const [hallTypes, setHallTypes] = useState([]);
    const [searchQuery, setSearchQuery] = useState('');
    const [statusFilter, setStatusFilter] = useState('all'); // 'all' | 'active' | 'inactive'

    // Backend Pagination state
    const [currentPage, setCurrentPage] = useState(1);
    const [pageSize, setPageSize] = useState(10);
    const [totalRecords, setTotalRecords] = useState(0);
    const [totalPages, setTotalPages] = useState(1);
    const [isLoading, setIsLoading] = useState(false);

    // Modals state
    const [isFormModalOpen, setIsFormModalOpen] = useState(false);
    const [formMode, setFormMode] = useState('create'); // 'create' | 'edit'
    const [editingHallType, setEditingHallType] = useState(null);

    const [isViewModalOpen, setIsViewModalOpen] = useState(false);
    const [viewingHallType, setViewingHallType] = useState(null);

    const [isDeleteModalOpen, setIsDeleteModalOpen] = useState(false);
    const [deletingHallType, setDeletingHallType] = useState(null);

    // Form inputs state
    const [formData, setFormData] = useState({
        name: '',
        description: '',
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
     * Fetch Hall Types using backend pagination endpoint
     */
    const fetchHallTypes = useCallback(async (page = currentPage, limit = pageSize, search = searchQuery, status = statusFilter) => {
        setIsLoading(true);
        try {
            const params = new URLSearchParams();
            params.append('page', String(page));
            params.append('limit', String(limit));
            if (search && search.trim()) params.append('search', search.trim());
            if (status && status !== 'all') params.append('status', status);

            const res = await api.get(`/hall_type/list.php?${params.toString()}`);
            if (res.success && res.data?.status && Array.isArray(res.data.data)) {
                setHallTypes(res.data.data);
                if (res.data.pagination) {
                    setTotalRecords(res.data.pagination.total_records ?? res.data.data.length);
                    setTotalPages(res.data.pagination.total_pages ?? 1);
                }
            } else if (res.data?.data && Array.isArray(res.data.data)) {
                setHallTypes(res.data.data);
                if (res.data.pagination) {
                    setTotalRecords(res.data.pagination.total_records ?? res.data.data.length);
                    setTotalPages(res.data.pagination.total_pages ?? 1);
                }
            } else {
                setHallTypes([]);
                setTotalRecords(0);
                setTotalPages(1);
            }
        } catch (err) {
            console.warn('Backend hall_type fetch note:', err.message);
            showToast('Failed to load hall types from server.', 'error');
        } finally {
            setIsLoading(false);
        }
    }, [currentPage, pageSize, searchQuery, statusFilter]);

    useEffect(() => {
        fetchHallTypes(currentPage, pageSize, searchQuery, statusFilter);
    }, [fetchHallTypes, currentPage, pageSize, searchQuery, statusFilter]);

    // Summary counts
    const stats = useMemo(() => {
        const total = totalRecords || hallTypes.length;
        const active = hallTypes.filter((h) => h.status === 'active').length;
        const inactive = hallTypes.filter((h) => h.status === 'inactive').length;
        return { total, active, inactive };
    }, [hallTypes, totalRecords]);

    // Validation matching backend rules
    const validateForm = () => {
        const errors = {};
        const trimmedName = formData.name.trim();

        if (!trimmedName) {
            errors.name = 'Hall type name is required.';
        } else if (trimmedName.length < 2) {
            errors.name = 'Hall type name must be at least 2 characters.';
        } else if (trimmedName.length > 100) {
            errors.name = 'Hall type name cannot exceed 100 characters.';
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

    // Open add modal
    const handleOpenAdd = () => {
        setFormMode('create');
        setEditingHallType(null);
        setFormData({
            name: '',
            description: '',
            status: 'active',
        });
        setFormErrors({});
        setIsFormModalOpen(true);
    };

    // Open edit modal
    const handleOpenEdit = (hallType) => {
        setFormMode('edit');
        setEditingHallType(hallType);
        setFormData({
            name: hallType.name,
            description: hallType.description || '',
            status: hallType.status || 'active',
        });
        setFormErrors({});
        setIsFormModalOpen(true);
    };

    // Open view modal
    const handleOpenView = (hallType) => {
        setViewingHallType(hallType);
        setIsViewModalOpen(true);
    };

    // Open delete confirmation modal
    const handleOpenDelete = (hallType) => {
        setDeletingHallType(hallType);
        setIsDeleteModalOpen(true);
    };

    // Handle Form Submit (Create or Edit)
    const handleFormSubmit = async (e) => {
        e.preventDefault();
        if (!validateForm()) return;

        setIsSubmitting(true);
        try {
            if (formMode === 'create') {
                const payload = {
                    name: formData.name.trim(),
                    description: formData.description.trim() || null,
                    status: formData.status,
                };
                const res = await api.post('/hall_type/create.php', payload);
                if (res.success && res.data?.status) {
                    showToast('Hall type created successfully!', 'success');
                    setIsFormModalOpen(false);
                    fetchHallTypes(1, pageSize, searchQuery, statusFilter);
                } else {
                    setFormErrors({ submit: res.error || res.data?.message || 'Failed to create hall type.' });
                }
            } else {
                const payload = {
                    hall_type_id: editingHallType.id,
                    name: formData.name.trim(),
                    description: formData.description.trim() || null,
                    status: formData.status,
                };
                const res = await api.post('/hall_type/update.php', payload);
                if (res.success && res.data?.status) {
                    showToast('Hall type updated successfully!', 'success');
                    setIsFormModalOpen(false);
                    fetchHallTypes(currentPage, pageSize, searchQuery, statusFilter);
                } else {
                    setFormErrors({ submit: res.error || res.data?.message || 'Failed to update hall type.' });
                }
            }
        } catch (err) {
            setFormErrors({ submit: err.message || 'An unexpected error occurred.' });
        } finally {
            setIsSubmitting(false);
        }
    };

    // Quick status toggle
    const handleToggleStatus = async (hallType) => {
        const newStatus = hallType.status === 'active' ? 'inactive' : 'active';
        try {
            const payload = {
                hall_type_id: hallType.id,
                name: hallType.name,
                description: hallType.description || '',
                status: newStatus,
            };
            const res = await api.post('/hall_type/update.php', payload);
            if (res.success && res.data?.status) {
                showToast(`Status changed to ${newStatus}.`, 'success');
                fetchHallTypes(currentPage, pageSize, searchQuery, statusFilter);
            } else {
                showToast(res.error || res.data?.message || 'Could not update status.', 'error');
            }
        } catch (err) {
            showToast(err.message || 'Failed to toggle status.', 'error');
        }
    };

    // Confirm Delete
    const handleConfirmDelete = async () => {
        if (!deletingHallType) return;
        setIsSubmitting(true);
        try {
            const res = await api.post('/hall_type/delete.php', {
                hall_type_id: deletingHallType.id,
            });
            if (res.success && res.data?.status) {
                showToast('Hall type deleted successfully.', 'success');
                setIsDeleteModalOpen(false);
                setDeletingHallType(null);
                fetchHallTypes(currentPage, pageSize, searchQuery, statusFilter);
            } else {
                showToast(res.error || res.data?.message || 'Could not delete hall type.', 'error');
            }
        } catch (err) {
            showToast(err.message || 'Failed to delete hall type.', 'error');
        } finally {
            setIsSubmitting(false);
        }
    };

    // React Select search options
    const searchSelectOptions = useMemo(() => {
        return hallTypes.map((ht) => ({
            value: ht.name,
            label: `${ht.name} (${ht.slug})`,
        }));
    }, [hallTypes]);

    const currentSelectValue = useMemo(() => {
        if (!searchQuery) return null;
        return searchSelectOptions.find((opt) => opt.value === searchQuery) || { value: searchQuery, label: searchQuery };
    }, [searchQuery, searchSelectOptions]);

    return (
        <div className="room-types-module hall-types-module">
            {/* Toast Notification */}
            {toastMessage && (
                <div className={`admin-toast admin-toast--${toastMessage.type}`}>
                    <div className="admin-toast__icon">
                        {toastMessage.type === 'success' ? (
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
                                <polyline points="22 4 12 14.01 9 11.01" />
                            </svg>
                        ) : (
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                                <circle cx="12" cy="12" r="10" />
                                <line x1="12" y1="8" x2="12" y2="12" />
                                <line x1="12" y1="16" x2="12.01" y2="16" />
                            </svg>
                        )}
                    </div>
                    <span className="admin-toast__text">{toastMessage.text}</span>
                </div>
            )}

            {/* Dashboard Page Header */}
            <div className="dashboard-page-header">
                <div className="dashboard-page-header__left">
                    <h2 className="dashboard-page-header__title">Hall Types Management</h2>
                    <p className="dashboard-page-header__desc">
                        Define and manage hall categories, venue types, descriptions, and availability status.
                    </p>
                </div>
                <div className="dashboard-page-header__actions">
                    <button
                        type="button"
                        className="admin-primary-btn"
                        onClick={handleOpenAdd}
                        id="add-hall-type-btn"
                    >
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round">
                            <line x1="12" y1="5" x2="12" y2="19" />
                            <line x1="5" y1="12" x2="19" y2="12" />
                        </svg>
                        <span>Add Hall Type</span>
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
                        placeholder="Search or select hall type..."
                        isClearable
                        isSearchable
                        styles={reactSelectStyles}
                        className="react-select-container"
                        classNamePrefix="react-select"
                        noOptionsMessage={({ inputValue }) =>
                            inputValue ? `No hall type matches "${inputValue}"` : 'No options'
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
                        <p style={{ fontSize: '0.9rem', fontWeight: 500 }}>Loading hall types from server...</p>
                    </div>
                ) : hallTypes.length === 0 ? (
                    <div className="dashboard-empty-state">
                        <div className="dashboard-empty-state__icon-wrap">
                            <svg width="42" height="42" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round">
                                <rect x="3" y="3" width="18" height="18" rx="2" />
                                <path d="M9 3v18" />
                                <path d="M14 9h4" />
                                <path d="M14 15h4" />
                            </svg>
                        </div>
                        <h4 className="dashboard-empty-state__title">No hall types found</h4>
                        <p className="dashboard-empty-state__desc">
                            {searchQuery || statusFilter !== 'all'
                                ? 'No hall types matched your filter criteria. Try clearing search.'
                                : 'No hall types have been created yet. Click below to add your first type.'}
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
                                + Add First Hall Type
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
                                        <th>Hall Type Name</th>
                                        <th>Slug</th>
                                        <th>Description</th>
                                        <th>Status</th>
                                        <th style={{ width: '170px' }}>Created / Updated</th>
                                        <th style={{ textAlign: 'right', width: '130px' }}>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {hallTypes.map((ht) => (
                                        <tr key={ht.id} className="admin-table-row">
                                            <td className="admin-table-cell--id">#{ht.id}</td>
                                            <td>
                                                <div className="room-type-name-cell">
                                                    <span className="room-type-name-text">{ht.name}</span>
                                                </div>
                                            </td>
                                            <td>
                                                <span className="room-type-slug-badge">{ht.slug}</span>
                                            </td>
                                            <td className="admin-table-cell--desc">
                                                <p className="room-type-desc-truncate">
                                                    {ht.description || <span className="text-muted-italic">No description</span>}
                                                </p>
                                            </td>
                                            <td>
                                                <button
                                                    type="button"
                                                    className={`admin-status-badge admin-status-badge--${ht.status}`}
                                                    onClick={() => handleToggleStatus(ht)}
                                                    title={`Click to change status to ${ht.status === 'active' ? 'inactive' : 'active'}`}
                                                    style={{ cursor: 'pointer', border: 'none', background: 'none' }}
                                                >
                                                    <span className="admin-status-dot" />
                                                    {ht.status === 'active' ? 'Active' : 'Inactive'}
                                                </button>
                                            </td>
                                            <td>
                                                <div className="admin-date-cell">
                                                    <span className="admin-date-cell__date">
                                                        {ht.created_at ? new Date(ht.created_at).toLocaleDateString() : '—'}
                                                    </span>
                                                    <span className="admin-date-cell__time">
                                                        {ht.updated_at ? `Upd: ${new Date(ht.updated_at).toLocaleDateString()}` : ''}
                                                    </span>
                                                </div>
                                            </td>
                                            <td style={{ textAlign: 'right' }}>
                                                <div className="admin-table-actions">
                                                    <button
                                                        type="button"
                                                        className="admin-action-btn admin-action-btn--view"
                                                        onClick={() => handleOpenView(ht)}
                                                        title="View Details"
                                                        aria-label={`View ${ht.name}`}
                                                    >
                                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                                            <path d="M1 12 s4 -8 11 -8 11 8 11 8 -4 8 -11 8 -11 -8 -11 -8 z" />
                                                            <circle cx="12" cy="12" r="3" />
                                                        </svg>
                                                    </button>
                                                    <button
                                                        type="button"
                                                        className="admin-action-btn admin-action-btn--edit"
                                                        onClick={() => handleOpenEdit(ht)}
                                                        title="Edit Hall Type"
                                                        aria-label={`Edit ${ht.name}`}
                                                    >
                                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                                            <path d="M11 4 H4 a2 2 0 0 0 -2 2 v14 a2 2 0 0 0 2 2 h14 a2 2 0 0 0 2 -2 v-7" />
                                                            <path d="M18.5 2.5 a2.121 2.121 0 0 1 3 3 L12 15 l-4 1 1 -4 9.5 -9.5 z" />
                                                        </svg>
                                                    </button>
                                                    <button
                                                        type="button"
                                                        className="admin-action-btn admin-action-btn--delete"
                                                        onClick={() => handleOpenDelete(ht)}
                                                        title="Delete Hall Type"
                                                        aria-label={`Delete ${ht.name}`}
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
                            totalPages={totalPages}
                            totalRecords={totalRecords}
                            pageSize={pageSize}
                            onPageChange={(page) => setCurrentPage(page)}
                            onPageSizeChange={(size) => {
                                setPageSize(size);
                                setCurrentPage(1);
                            }}
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
                                    {formMode === 'create' ? 'Create New Hall Type' : 'Edit Hall Type'}
                                </h3>
                                <p className="admin-modal__subtitle">
                                    {formMode === 'create'
                                        ? 'Add a new hall category to the venue repository.'
                                        : `Updating specifications for ${editingHallType?.name}`}
                                </p>
                            </div>
                            <button
                                type="button"
                                className="admin-modal__close-btn"
                                onClick={() => setIsFormModalOpen(false)}
                                aria-label="Close dialog"
                                disabled={isSubmitting}
                            >
                                ✕
                            </button>
                        </div>

                        <form onSubmit={handleFormSubmit}>
                            <div className="admin-modal__body">
                                {formErrors.submit && (
                                    <div className="admin-form-alert admin-form-alert--error" style={{ marginBottom: '16px' }}>
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                                            <circle cx="12" cy="12" r="10" />
                                            <line x1="12" y1="8" x2="12" y2="12" />
                                            <line x1="12" y1="16" x2="12.01" y2="16" />
                                        </svg>
                                        <span>{formErrors.submit}</span>
                                    </div>
                                )}

                                {/* Hall Type Name */}
                                <div className="admin-form-group">
                                    <label className="admin-form-label" htmlFor="hall-type-name">
                                        Hall Type Name <span className="admin-form-required">*</span>
                                    </label>
                                    <input
                                        id="hall-type-name"
                                        type="text"
                                        className={`admin-form-input ${formErrors.name ? 'admin-form-input--error' : ''}`}
                                        placeholder="e.g. Marriage Mahal, Conference Hall, Mini Party Hall"
                                        value={formData.name}
                                        onChange={(e) => {
                                            setFormData((prev) => ({ ...prev, name: e.target.value }));
                                            if (formErrors.name) setFormErrors((prev) => ({ ...prev, name: null }));
                                        }}
                                        maxLength={100}
                                        disabled={isSubmitting}
                                        autoFocus
                                        required
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
                                    <label className="admin-form-label" htmlFor="hall-type-status">
                                        Status <span className="admin-form-required">*</span>
                                    </label>
                                    <select
                                        id="hall-type-status"
                                        className="admin-form-select"
                                        value={formData.status}
                                        onChange={(e) => setFormData((prev) => ({ ...prev, status: e.target.value }))}
                                        disabled={isSubmitting}
                                    >
                                        <option value="active">Active (Available for booking & hall allocation)</option>
                                        <option value="inactive">Inactive (Disabled / Hidden)</option>
                                    </select>
                                </div>

                                {/* Description */}
                                <div className="admin-form-group">
                                    <div className="admin-form-label-row">
                                        <label className="admin-form-label" htmlFor="hall-type-description">
                                            Description
                                        </label>
                                        <span className="admin-char-count">
                                            {(formData.description || '').length} / 1000
                                        </span>
                                    </div>
                                    <textarea
                                        id="hall-type-description"
                                        rows={4}
                                        className={`admin-form-textarea ${formErrors.description ? 'admin-form-input--error' : ''}`}
                                        placeholder="Provide detailed information regarding seating capacity, stage dimensions, acoustic layout, etc."
                                        value={formData.description}
                                        onChange={(e) => {
                                            setFormData((prev) => ({ ...prev, description: e.target.value }));
                                            if (formErrors.description) setFormErrors((prev) => ({ ...prev, description: null }));
                                        }}
                                        maxLength={1000}
                                        disabled={isSubmitting}
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
                                    disabled={isSubmitting}
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    className="admin-primary-btn"
                                    disabled={isSubmitting}
                                >
                                    {isSubmitting ? 'Saving...' : formMode === 'create' ? 'Create Hall Type' : 'Save Changes'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* ─── VIEW DETAILS MODAL ─── */}
            {isViewModalOpen && viewingHallType && (
                <div className="admin-modal-overlay" onClick={() => setIsViewModalOpen(false)}>
                    <div className="admin-modal admin-modal--view" onClick={(e) => e.stopPropagation()} role="dialog" aria-modal="true">
                        <div className="admin-modal__header">
                            <div className="admin-modal__title-wrap">
                                <div className="admin-modal__badge-row">
                                    <span className="room-type-id-pill">ID #{viewingHallType.id}</span>
                                    <span className={`admin-status-badge admin-status-badge--${viewingHallType.status}`}>
                                        <span className="admin-status-dot" />
                                        {viewingHallType.status === 'active' ? 'Active' : 'Inactive'}
                                    </span>
                                </div>
                                <h3 className="admin-modal__title">{viewingHallType.name}</h3>
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
                                    <span className="room-type-detail-val code">{viewingHallType.slug}</span>
                                </div>
                                <div className="room-type-detail-item">
                                    <span className="room-type-detail-label">Status</span>
                                    <span className="room-type-detail-val" style={{ textTransform: 'capitalize' }}>
                                        {viewingHallType.status}
                                    </span>
                                </div>
                                <div className="room-type-detail-item full-width">
                                    <span className="room-type-detail-label">Description</span>
                                    <p className="room-type-detail-text">
                                        {viewingHallType.description || 'No description provided for this hall type.'}
                                    </p>
                                </div>
                                <div className="room-type-detail-item">
                                    <span className="room-type-detail-label">Created At</span>
                                    <span className="room-type-detail-val text-muted">{viewingHallType.created_at || '—'}</span>
                                </div>
                                <div className="room-type-detail-item">
                                    <span className="room-type-detail-label">Last Updated</span>
                                    <span className="room-type-detail-val text-muted">{viewingHallType.updated_at || '—'}</span>
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
                                    handleOpenEdit(viewingHallType);
                                }}
                            >
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M11 4 H4 a2 2 0 0 0 -2 2 v14 a2 2 0 0 0 2 2 h14 a2 2 0 0 0 2 -2 v-7" /><path d="M18.5 2.5 a2.121 2.121 0 0 1 3 3 L12 15 l-4 1 1 -4 9.5 -9.5 z" /></svg>
                                Edit Hall Type
                            </button>
                        </div>
                    </div>
                </div>
            )}

            {/* ─── DELETE CONFIRMATION MODAL ─── */}
            {isDeleteModalOpen && deletingHallType && (
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
                                <h3 className="admin-modal__title">Delete Hall Type</h3>
                                <p className="admin-modal__subtitle">This action cannot be undone.</p>
                            </div>
                        </div>

                        <div className="admin-modal__body">
                            <p className="admin-delete-prompt">
                                Are you sure you want to permanently delete <strong>"{deletingHallType.name}"</strong>?
                            </p>
                            <div className="admin-delete-warning-box">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#b45309" strokeWidth="2"><path d="M10.29 3.86 L1.82 18 a2 2 0 0 0 1.71 3 h16.94 a2 2 0 0 0 1.71 -3 L13.71 3.86 a2 2 0 0 0 -3.42 0 z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                                <span>
                                    <strong>Notice:</strong> If any active halls are currently assigned to this category, deletion will be blocked by the server.
                                </span>
                            </div>
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
                                {isSubmitting ? 'Deleting...' : 'Delete Hall Type'}
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
};

export default HallTypesModule;
