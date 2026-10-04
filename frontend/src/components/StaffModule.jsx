import React, { useState, useEffect, useCallback, useMemo } from 'react';
import Select from 'react-select';
import { staffApi } from '../services/staffApi';
import { getAuthSession } from '../services/Utils';

// Standard React Select styling adhering to the SM Groups Admin design system
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
        fontWeight: '500',
    }),
    singleValue: (base) => ({
        ...base,
        color: 'var(--text-primary)',
        fontSize: '0.88rem',
        fontWeight: '600',
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
 * Staff Management Module
 * Connects to real backend API: GET /auth/staff_list.php and POST /auth/staff_register.php
 * Strictly adheres to UI/UX consistency with Room Type and Room admin modules.
 */
const StaffModule = () => {
    // Current authenticated session
    const adminSession = getAuthSession('admin');
    const currentUser = adminSession?.user;
    const isSuperAdmin = currentUser?.role === 'admin' || currentUser?.role === 'super_admin';

    // Staff state
    const [staffList, setStaffList] = useState([]);
    const [isLoading, setIsLoading] = useState(false);
    const [isRegisterModalOpen, setIsRegisterModalOpen] = useState(false);
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [toastMessage, setToMessage] = useState(null);
    const [searchQuery, setSearchQuery] = useState('');

    // Registration Form State
    const [formData, setFormData] = useState({
        name: '',
        email: '',
        phone: '',
        password: '',
        confirm_password: '',
    });
    const [formErrors, setFormErrors] = useState({});
    const [showPassword, setShowPassword] = useState(false);
    const [showConfirmPassword, setShowConfirmPassword] = useState(false);

    // Toast helper
    const showToast = (text, type = 'success') => {
        setToMessage({ text, type });
        setTimeout(() => setToMessage(null), 4000);
    };

    // Load staff list directly from /auth/staff_list.php
    const fetchStaffList = useCallback(async () => {
        setIsLoading(true);
        try {
            const res = await staffApi.listStaff({ limit: 100 });
            const staffData = res?.data?.data?.staff || res?.data?.staff || res?.data?.data || [];
            if (Array.isArray(staffData)) {
                const mapped = staffData.map((s) => ({
                    staff_id: Number(s.id || s.staff_id),
                    id: Number(s.id || s.staff_id),
                    name: s.name || '',
                    email: s.email || '',
                    phone: s.phone || '',
                    role: s.role || 'staff',
                    status: s.status || 'active',
                    assigned_leads: Number(s.assigned_leads || 0),
                    converted_leads: Number(s.converted_leads || 0),
                    converted_revenue: Number(s.converted_revenue || 0),
                    created_at: s.created_at || '',
                    updated_at: s.updated_at || '',
                }));
                setStaffList(mapped);
            } else {
                setStaffList([]);
            }
        } catch (err) {
            console.error('Error loading staff from /auth/staff_list.php:', err);
            showToast('Failed to load staff list.', 'error');
        } finally {
            setIsLoading(false);
        }
    }, []);

    useEffect(() => {
        fetchStaffList();
    }, [fetchStaffList]);

    // Open modal
    const handleOpenModal = () => {
        setFormData({
            name: '',
            email: '',
            phone: '',
            password: '',
            confirm_password: '',
        });
        setFormErrors({});
        setShowPassword(false);
        setShowConfirmPassword(false);
        setIsRegisterModalOpen(true);
    };

    // Close modal
    const handleCloseModal = () => {
        if (isSubmitting) return;
        setIsRegisterModalOpen(false);
        setFormErrors({});
        setShowPassword(false);
        setShowConfirmPassword(false);
    };

    // Form input handler
    const handleInputChange = (field, value) => {
        setFormData((prev) => ({ ...prev, [field]: value }));
        if (formErrors[field]) {
            setFormErrors((prev) => ({ ...prev, [field]: null }));
        }
    };

    // Strict client-side validation mirroring backend /auth/staff_register.php
    const validateForm = () => {
        const errors = {};

        // Name Validation
        const trimmedName = formData.name.trim();
        if (!trimmedName) {
            errors.name = 'Name is required.';
        } else if (trimmedName.length < 2 || trimmedName.length > 100) {
            errors.name = 'Name must be between 2 and 100 characters.';
        } else if (!/^[A-Za-z]+(?: [A-Za-z]+)*$/.test(trimmedName)) {
            errors.name = 'Name can contain only letters with single spaces between words.';
        }

        // Email Validation
        const trimmedEmail = formData.email.trim();
        if (!trimmedEmail) {
            errors.email = 'Email is required.';
        } else if (trimmedEmail.length > 150) {
            errors.email = 'Email address is too long.';
        } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(trimmedEmail)) {
            errors.email = 'Please enter a valid email address.';
        }

        // Phone Validation (Indian 10-digit mobile)
        const trimmedPhone = formData.phone.trim();
        if (!trimmedPhone) {
            errors.phone = 'Phone number is required.';
        } else if (!/^[0-9]+$/.test(trimmedPhone)) {
            errors.phone = 'Phone number must contain only digits.';
        } else if (!/^[6-9][0-9]{9}$/.test(trimmedPhone)) {
            errors.phone = 'Please enter a valid 10-digit Indian mobile number (starts with 6-9).';
        } else if (/^(\d)\1{9}$/.test(trimmedPhone)) {
            errors.phone = 'Please enter a valid mobile number (repeated digits not allowed).';
        }

        // Password Validation
        if (!formData.password) {
            errors.password = 'Password is required.';
        } else if (formData.password.length < 8 || formData.password.length > 72) {
            errors.password = 'Password must be between 8 and 72 characters.';
        } else if (!/[A-Z]/.test(formData.password)) {
            errors.password = 'Password must contain at least one uppercase letter.';
        } else if (!/[a-z]/.test(formData.password)) {
            errors.password = 'Password must contain at least one lowercase letter.';
        } else if (!/[0-9]/.test(formData.password)) {
            errors.password = 'Password must contain at least one number.';
        }

        // Confirm Password
        if (!formData.confirm_password) {
            errors.confirm_password = 'Confirm password is required.';
        } else if (formData.password !== formData.confirm_password) {
            errors.confirm_password = 'Password and confirm password do not match.';
        }

        setFormErrors(errors);
        return Object.keys(errors).length === 0;
    };

    // Submit handler
    const handleSubmit = async (e) => {
        e.preventDefault();
        if (!validateForm()) return;

        setIsSubmitting(true);
        try {
            const response = await staffApi.registerStaff({
                name: formData.name,
                email: formData.email,
                phone: formData.phone,
                password: formData.password,
                confirm_password: formData.confirm_password,
            });

            const isSuccess = response?.success || response?.data?.status === true || response?.status === 201 || response?.status === 200;
            if (isSuccess) {
                showToast(response?.data?.message || response?.message || 'Staff account created successfully.');
                setIsRegisterModalOpen(false);
                fetchStaffList();
            } else {
                showToast(response?.data?.message || response?.message || 'Failed to create staff account.', 'error');
            }
        } catch (err) {
            const errMsg = err?.response?.data?.message || err?.data?.message || err?.message || 'Server error occurred during staff registration.';
            showToast(errMsg, 'error');
        } finally {
            setIsSubmitting(false);
        }
    };

    // Filtered staff list for search
    const filteredStaff = useMemo(() => {
        if (!searchQuery.trim()) return staffList;
        const q = searchQuery.toLowerCase();
        return staffList.filter(
            (s) =>
                s.name?.toLowerCase().includes(q) ||
                s.email?.toLowerCase().includes(q) ||
                s.phone?.includes(q)
        );
    }, [staffList, searchQuery]);

    // Search options
    const searchOptions = useMemo(() => {
        return staffList.map((s) => ({
            value: s.name,
            label: `${s.name} (${s.email})`,
        }));
    }, [staffList]);

    return (
        <div className="room-types-module">
            {/* Toast Notification */}
            {toastMessage && (
                <div className={`admin-toast admin-toast--${toastMessage.type}`}>
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                        {toastMessage.type === 'error' ? (
                            <>
                                <circle cx="12" cy="12" r="10" />
                                <line x1="15" y1="9" x2="9" y2="15" />
                                <line x1="9" y1="9" x2="15" y2="15" />
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

            {/* Standard Dashboard Page Header matching Room Type & Room */}
            <div className="dashboard-page-header">
                <div className="dashboard-page-header__info">
                    <h2 className="dashboard-page-header__title">Staff Management</h2>
                    <p className="dashboard-page-header__desc">
                        Register, provision, and manage staff member accounts with secure role-based access control.
                    </p>
                </div>
                {isSuperAdmin && (
                    <div className="dashboard-page-header__actions">
                        <button
                            type="button"
                            className="admin-primary-btn"
                            onClick={handleOpenModal}
                            id="register-staff-btn"
                        >
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round">
                                <line x1="12" y1="5" x2="12" y2="19" />
                                <line x1="5" y1="12" x2="19" y2="12" />
                            </svg>
                            <span>Register Staff</span>
                        </button>
                    </div>
                )}
            </div>

            {/* KPI Stats Overview Grid */}
            <div className="room-types-stats-grid">
                <div className="room-type-stat-card">
                    <div className="room-type-stat-card__icon room-type-stat-card__icon--gold">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                            <circle cx="9" cy="7" r="4" />
                            <path d="M23 21v-2a4 4 0 0 0-3-3.87" />
                            <path d="M16 3.13a4 4 0 0 1 0 7.75" />
                        </svg>
                    </div>
                    <div className="room-type-stat-card__info">
                        <span className="room-type-stat-card__label">Session Registered</span>
                        <span className="room-type-stat-card__value">{staffList.length}</span>
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
                        <span className="room-type-stat-card__label">Active Status</span>
                        <span className="room-type-stat-card__value">{staffList.length}</span>
                    </div>
                </div>

                <div className="room-type-stat-card">
                    <div className="room-type-stat-card__icon room-type-stat-card__icon--blue">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2" />
                            <path d="M7 11V7a5 5 0 0 1 10 0v4" />
                        </svg>
                    </div>
                    <div className="room-type-stat-card__info">
                        <span className="room-type-stat-card__label">Role Access</span>
                        <span className="room-type-stat-card__value" style={{ fontSize: '1.05rem', textTransform: 'uppercase' }}>STAFF (RBAC)</span>
                    </div>
                </div>

                <div className="room-type-stat-card">
                    <div className="room-type-stat-card__icon room-type-stat-card__icon--slate">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
                        </svg>
                    </div>
                    <div className="room-type-stat-card__info">
                        <span className="room-type-stat-card__label">Authorized Role</span>
                        <span className="room-type-stat-card__value" style={{ fontSize: '1.05rem', textTransform: 'uppercase' }}>ADMIN ONLY</span>
                    </div>
                </div>
            </div>

            {/* Filter and Search Toolbar */}
            <div className="room-types-toolbar" style={{ flexWrap: 'wrap', gap: '10px', justifyContent: 'flex-start' }}>
                <div className="room-types-search-select-wrap" style={{ minWidth: '260px', maxWidth: '380px', flex: '1 1 260px' }}>
                    <Select
                        options={searchOptions}
                        value={
                            searchQuery
                                ? searchOptions.find((o) => o.value.toLowerCase() === searchQuery.toLowerCase()) || {
                                      value: searchQuery,
                                      label: searchQuery,
                                  }
                                : null
                        }
                        onChange={(selected) => setSearchQuery(selected ? selected.value : '')}
                        onInputChange={(inputValue, { action }) => {
                            if (action === 'input-change') setSearchQuery(inputValue);
                        }}
                        placeholder="Search staff by name or email..."
                        isClearable
                        isSearchable
                        styles={reactSelectStyles}
                        className="react-select-container"
                        classNamePrefix="react-select"
                        noOptionsMessage={({ inputValue }) =>
                            inputValue ? `No staff matches "${inputValue}"` : 'No options'
                        }
                    />
                </div>
            </div>

            {/* Staff Data Table Card */}
            <div className="admin-table-card">
                {filteredStaff.length === 0 ? (
                    <div className="dashboard-empty-state">
                        <div className="dashboard-empty-state__icon-wrap">
                            <svg width="42" height="42" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round">
                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                                <circle cx="9" cy="7" r="4" />
                                <path d="M23 21v-2a4 4 0 0 0-3-3.87" />
                                <path d="M16 3.13a4 4 0 0 1 0 7.75" />
                            </svg>
                        </div>
                        <h4 className="dashboard-empty-state__title">
                            {searchQuery ? 'No Matching Staff Accounts' : 'No Staff Accounts Registered Yet'}
                        </h4>
                        <p className="dashboard-empty-state__desc">
                            {searchQuery
                                ? `No staff records matched your query "${searchQuery}".`
                                : 'Use the "Register Staff" button above to create a staff account in the backend database.'}
                        </p>
                        {isSuperAdmin && (
                            <button
                                type="button"
                                className="admin-primary-btn"
                                style={{ margin: '0 auto', display: 'inline-flex' }}
                                onClick={handleOpenModal}
                            >
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round">
                                    <line x1="12" y1="5" x2="12" y2="19" />
                                    <line x1="5" y1="12" x2="19" y2="12" />
                                </svg>
                                <span>Register Staff Member</span>
                            </button>
                        )}
                    </div>
                ) : (
                    <div className="table-responsive">
                        <table className="admin-table">
                            <thead>
                                <tr>
                                    <th>Staff ID</th>
                                    <th>Staff Member</th>
                                    <th>Email Address</th>
                                    <th>Mobile Phone</th>
                                    <th>System Role</th>
                                    <th>Account Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                {filteredStaff.map((staff, idx) => (
                                    <tr key={staff.staff_id || idx}>
                                        <td style={{ fontWeight: 600, color: 'var(--text-secondary)' }}>
                                            #{staff.staff_id}
                                        </td>
                                        <td>
                                            <div style={{ display: 'flex', alignItems: 'center', gap: '12px' }}>
                                                <div
                                                    style={{
                                                        width: '36px',
                                                        height: '36px',
                                                        borderRadius: '50%',
                                                        backgroundColor: 'rgba(212, 160, 23, 0.15)',
                                                        color: '#d4a017',
                                                        display: 'flex',
                                                        alignItems: 'center',
                                                        justifyContent: 'center',
                                                        fontWeight: 700,
                                                        fontSize: '0.9rem',
                                                        border: '1px solid rgba(212, 160, 23, 0.3)',
                                                        flexShrink: 0,
                                                    }}
                                                >
                                                    {staff.name?.charAt(0)?.toUpperCase() || 'S'}
                                                </div>
                                                <span style={{ fontWeight: 600, color: 'var(--text-primary)' }}>
                                                    {staff.name}
                                                </span>
                                            </div>
                                        </td>
                                        <td style={{ color: 'var(--text-primary)' }}>{staff.email}</td>
                                        <td style={{ color: 'var(--text-primary)', fontFamily: 'monospace', letterSpacing: '0.5px' }}>{staff.phone}</td>
                                        <td>
                                            <span
                                                style={{
                                                    display: 'inline-block',
                                                    padding: '3px 10px',
                                                    borderRadius: '6px',
                                                    fontSize: '0.75rem',
                                                    fontWeight: 700,
                                                    textTransform: 'uppercase',
                                                    backgroundColor: 'rgba(37, 99, 235, 0.1)',
                                                    color: '#2563eb',
                                                    border: '1px solid rgba(37, 99, 235, 0.2)',
                                                }}
                                            >
                                                {staff.role || 'staff'}
                                            </span>
                                        </td>
                                        <td>
                                            <span
                                                className={`room-type-status-badge room-type-status-badge--${staff.status === 'inactive' ? 'inactive' : 'active'}`}
                                            >
                                                {staff.status ? (staff.status.charAt(0).toUpperCase() + staff.status.slice(1)) : 'Active'}
                                            </span>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>

            {/* Staff Registration Modal Dialog */}
            {isRegisterModalOpen && (
                <div className="admin-modal-overlay" onClick={handleCloseModal}>
                    <div
                        className="admin-modal"
                        style={{ maxWidth: '560px' }}
                        onClick={(e) => e.stopPropagation()}
                        role="dialog"
                        aria-modal="true"
                    >
                        <div className="admin-modal__header">
                            <div className="admin-modal__title-wrap">
                                <h3 className="admin-modal__title">Register New Staff Account</h3>
                                <p className="admin-modal__subtitle">
                                    Provision credentials for staff members with role-based access.
                                </p>
                            </div>
                            <button
                                type="button"
                                className="admin-modal__close-btn"
                                onClick={handleCloseModal}
                                disabled={isSubmitting}
                                aria-label="Close dialog"
                            >
                                &times;
                            </button>
                        </div>

                        <form onSubmit={handleSubmit}>
                            <div className="admin-modal__body">
                                {/* Full Name */}
                                <div className="admin-form-group">
                                    <label className="admin-form-label">
                                        Full Name <span className="admin-form-required">*</span>
                                    </label>
                                    <input
                                        type="text"
                                        className={`admin-form-input ${formErrors.name ? 'admin-form-input--error' : ''}`}
                                        placeholder="e.g. Ramesh Kumar"
                                        value={formData.name}
                                        onChange={(e) => handleInputChange('name', e.target.value)}
                                        disabled={isSubmitting}
                                        autoFocus
                                    />
                                    {formErrors.name && (
                                        <span style={{ color: '#dc2626', fontSize: '0.78rem', marginTop: '2px', display: 'block' }}>
                                            {formErrors.name}
                                        </span>
                                    )}
                                    <span style={{ color: '#78716c', fontSize: '0.75rem', marginTop: '2px', display: 'block' }}>
                                        Letters and single spaces only (2 to 100 characters).
                                    </span>
                                </div>

                                {/* Email Address */}
                                <div className="admin-form-group">
                                    <label className="admin-form-label">
                                        Email Address <span className="admin-form-required">*</span>
                                    </label>
                                    <input
                                        type="email"
                                        className={`admin-form-input ${formErrors.email ? 'admin-form-input--error' : ''}`}
                                        placeholder="e.g. staff.ramesh@smgroups.com"
                                        value={formData.email}
                                        onChange={(e) => handleInputChange('email', e.target.value)}
                                        disabled={isSubmitting}
                                    />
                                    {formErrors.email && (
                                        <span style={{ color: '#dc2626', fontSize: '0.78rem', marginTop: '2px', display: 'block' }}>
                                            {formErrors.email}
                                        </span>
                                    )}
                                </div>

                                {/* Mobile Phone */}
                                <div className="admin-form-group">
                                    <label className="admin-form-label">
                                        Mobile Phone Number <span className="admin-form-required">*</span>
                                    </label>
                                    <input
                                        type="tel"
                                        className={`admin-form-input ${formErrors.phone ? 'admin-form-input--error' : ''}`}
                                        placeholder="e.g. 9876543210 (10 digits)"
                                        value={formData.phone}
                                        onChange={(e) => handleInputChange('phone', e.target.value)}
                                        maxLength={10}
                                        disabled={isSubmitting}
                                    />
                                    {formErrors.phone && (
                                        <span style={{ color: '#dc2626', fontSize: '0.78rem', marginTop: '2px', display: 'block' }}>
                                            {formErrors.phone}
                                        </span>
                                    )}
                                    <span style={{ color: '#78716c', fontSize: '0.75rem', marginTop: '2px', display: 'block' }}>
                                        10-digit Indian mobile number starting with 6, 7, 8, or 9.
                                    </span>
                                </div>

                                {/* Password Fields */}
                                <div className="admin-form-row admin-form-row-2">
                                    <div className="admin-form-group">
                                        <label className="admin-form-label">
                                            Password <span className="admin-form-required">*</span>
                                        </label>
                                        <div style={{ position: 'relative', display: 'flex', alignItems: 'center' }}>
                                            <input
                                                type={showPassword ? 'text' : 'password'}
                                                className={`admin-form-input ${formErrors.password ? 'admin-form-input--error' : ''}`}
                                                placeholder="Enter staff password"
                                                value={formData.password}
                                                onChange={(e) => handleInputChange('password', e.target.value)}
                                                disabled={isSubmitting}
                                                style={{ paddingRight: '40px' }}
                                                autoComplete="new-password"
                                            />
                                            <button
                                                type="button"
                                                onClick={() => setShowPassword((prev) => !prev)}
                                                disabled={isSubmitting}
                                                aria-label={showPassword ? 'Hide password' : 'Show password'}
                                                style={{
                                                    position: 'absolute',
                                                    right: '10px',
                                                    top: '50%',
                                                    transform: 'translateY(-50%)',
                                                    background: 'none',
                                                    border: 'none',
                                                    padding: '4px',
                                                    cursor: 'pointer',
                                                    color: showPassword ? '#d4a017' : '#94a3b8',
                                                    display: 'flex',
                                                    alignItems: 'center',
                                                    justifyContent: 'center',
                                                    transition: 'color 0.2s ease',
                                                }}
                                            >
                                                {showPassword ? (
                                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                                        <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z" />
                                                        <circle cx="12" cy="12" r="3" />
                                                    </svg>
                                                ) : (
                                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                                        <path d="M9.88 9.88a3 3 0 1 0 4.24 4.24" />
                                                        <path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68" />
                                                        <path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61" />
                                                        <line x1="2" y1="2" x2="22" y2="22" />
                                                    </svg>
                                                )}
                                            </button>
                                        </div>
                                        {formErrors.password && (
                                            <span style={{ color: '#dc2626', fontSize: '0.78rem', marginTop: '2px', display: 'block' }}>
                                                {formErrors.password}
                                            </span>
                                        )}
                                    </div>

                                    <div className="admin-form-group">
                                        <label className="admin-form-label">
                                            Confirm Password <span className="admin-form-required">*</span>
                                        </label>
                                        <div style={{ position: 'relative', display: 'flex', alignItems: 'center' }}>
                                            <input
                                                type={showConfirmPassword ? 'text' : 'password'}
                                                className={`admin-form-input ${formErrors.confirm_password ? 'admin-form-input--error' : ''}`}
                                                placeholder="Confirm staff password"
                                                value={formData.confirm_password}
                                                onChange={(e) => handleInputChange('confirm_password', e.target.value)}
                                                disabled={isSubmitting}
                                                style={{ paddingRight: '40px' }}
                                                autoComplete="new-password"
                                            />
                                            <button
                                                type="button"
                                                onClick={() => setShowConfirmPassword((prev) => !prev)}
                                                disabled={isSubmitting}
                                                aria-label={showConfirmPassword ? 'Hide confirm password' : 'Show confirm password'}
                                                style={{
                                                    position: 'absolute',
                                                    right: '10px',
                                                    top: '50%',
                                                    transform: 'translateY(-50%)',
                                                    background: 'none',
                                                    border: 'none',
                                                    padding: '4px',
                                                    cursor: 'pointer',
                                                    color: showConfirmPassword ? '#d4a017' : '#94a3b8',
                                                    display: 'flex',
                                                    alignItems: 'center',
                                                    justifyContent: 'center',
                                                    transition: 'color 0.2s ease',
                                                }}
                                            >
                                                {showConfirmPassword ? (
                                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                                        <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z" />
                                                        <circle cx="12" cy="12" r="3" />
                                                    </svg>
                                                ) : (
                                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                                        <path d="M9.88 9.88a3 3 0 1 0 4.24 4.24" />
                                                        <path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68" />
                                                        <path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61" />
                                                        <line x1="2" y1="2" x2="22" y2="22" />
                                                    </svg>
                                                )}
                                            </button>
                                        </div>
                                        {formErrors.confirm_password && (
                                            <span style={{ color: '#dc2626', fontSize: '0.78rem', marginTop: '2px', display: 'block' }}>
                                                {formErrors.confirm_password}
                                            </span>
                                        )}
                                    </div>
                                </div>
                                <span style={{ color: '#78716c', fontSize: '0.75rem', display: 'block', marginTop: '-6px' }}>
                                    Password must contain at least 1 uppercase letter, 1 lowercase letter, and 1 number.
                                </span>
                            </div>

                            {/* Modal Footer Actions */}
                            <div className="admin-modal__footer">
                                <button
                                    type="button"
                                    className="admin-secondary-btn"
                                    onClick={handleCloseModal}
                                    disabled={isSubmitting}
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    className="admin-primary-btn"
                                    disabled={isSubmitting}
                                >
                                    {isSubmitting ? (
                                        <>
                                            <span className="admin-spinner" style={{ width: '16px', height: '16px', borderTopColor: '#1a1612', marginRight: '6px' }} />
                                            <span>Creating Account...</span>
                                        </>
                                    ) : (
                                        <span>Create Staff Account</span>
                                    )}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </div>
    );
};

export default StaffModule;
