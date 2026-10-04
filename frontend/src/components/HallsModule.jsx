import React, { useState, useMemo, useRef, useEffect, useCallback } from 'react';
import Select from 'react-select';
import Pagination from './Pagination';
import api, { hallApi } from '../services/api';

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
 * Helper to resolve an image URL
 */
const resolveImageUrl = (img) => {
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

/**
 * Get primary image of a hall
 */
const getPrimaryImage = (hall) => {
    if (!hall?.images || !Array.isArray(hall.images) || hall.images.length === 0) return null;
    const primary = hall.images.find((img) => img.is_primary === 1 || img.is_primary === true);
    const target = primary || hall.images[0];
    return resolveImageUrl(target);
};

/**
 * Helper to resolve Hall Type Name from hall object / hallTypes list
 */
const getHallTypeName = (hall, hallTypesList = []) => {
    if (!hall) return 'Unassigned';
    if (hall.hall_type && typeof hall.hall_type === 'object' && hall.hall_type.name) {
        return hall.hall_type.name;
    }
    if (hall.hall_type_name) {
        return hall.hall_type_name;
    }
    const typeId = hall.hall_type_id || (hall.hall_type && typeof hall.hall_type !== 'object' ? hall.hall_type : null);
    if (typeId && Array.isArray(hallTypesList)) {
        const match = hallTypesList.find((ht) => String(ht.id) === String(typeId));
        if (match && match.name) return match.name;
    }
    return 'Unassigned';
};

/**
 * Helper to get Hall Status info (label, CSS badge class, normalized value)
 */
const getHallStatusInfo = (status) => {
    const norm = String(status || '').toLowerCase().trim();
    if (norm === 'available' || norm === 'active') {
        return {
            label: 'Active',
            badgeClass: 'admin-status-badge--active',
            statusValue: 'available',
        };
    }
    if (norm === 'maintenance') {
        return {
            label: 'Maintenance',
            badgeClass: 'admin-status-badge--maintenance',
            statusValue: 'maintenance',
        };
    }
    if (norm === 'inactive') {
        return {
            label: 'Inactive',
            badgeClass: 'admin-status-badge--inactive',
            statusValue: 'inactive',
        };
    }
    return {
        label: norm ? norm.charAt(0).toUpperCase() + norm.slice(1) : 'Inactive',
        badgeClass: 'admin-status-badge--inactive',
        statusValue: norm || 'inactive',
    };
};

/**
 * Helper to normalize and extract numeric area from string or number (e.g. "6500 sq.ft" -> "6500")
 */
const extractNumericArea = (val) => {
    if (val === null || val === undefined) return '';
    if (typeof val === 'number') return isNaN(val) ? '' : String(val);
    const str = String(val).trim();
    if (!str) return '';
    const cleaned = str.replace(/,/g, '');
    const match = cleaned.match(/(\d+(?:\.\d+)?)/);
    return match ? match[1] : '';
};

/**
 * Redesigned Halls Module
 */
const HallsModule = () => {
    const [halls, setHalls] = useState([]);
    const [hallTypes, setHallTypes] = useState([]);
    const [availableFacilities, setAvailableFacilities] = useState([]);

    // Filter states
    const [searchQuery, setSearchQuery] = useState('');
    const [selectedTypeFilter, setSelectedTypeFilter] = useState('all');
    const [selectedStatusFilter, setSelectedStatusFilter] = useState('all');

    // Backend Pagination state
    const [currentPage, setCurrentPage] = useState(1);
    const [pageSize, setPageSize] = useState(10);
    const [totalRecords, setTotalRecords] = useState(0);
    const [totalPages, setTotalPages] = useState(1);
    const [isLoading, setIsLoading] = useState(false);

    // Modal states
    const [isFormModalOpen, setIsFormModalOpen] = useState(false);
    const [formMode, setFormMode] = useState('create'); // 'create' | 'edit'
    const [editingHall, setEditingHall] = useState(null);

    const [isViewModalOpen, setIsViewModalOpen] = useState(false);
    const [viewingHall, setViewingHall] = useState(null);
    const [activeViewImageIndex, setActiveViewImageIndex] = useState(0);

    const [isDeleteModalOpen, setIsDeleteModalOpen] = useState(false);
    const [deletingHall, setDeletingHall] = useState(null);

    // Full View Lightbox Modal state
    const [fullViewImage, setFullViewImage] = useState(null);

    // Form Image & Gallery states
    const [pendingImages, setPendingImages] = useState([]); // [ { file, preview, altText, isPrimary } ]
    const [isUploadingImage, setIsUploadingImage] = useState(false);
    const [newImageAlt, setNewImageAlt] = useState('');
    const [newImageIsPrimary, setNewImageIsPrimary] = useState(false);
    const [imagePreview, setImagePreview] = useState(null);
    const [imageFile, setImageFile] = useState(null);
    const fileInputRef = useRef(null);

    // Form Facilities state
    const [selectedFacilityIds, setSelectedFacilityIds] = useState([]);

    // Toast state
    const [toastMessage, setToastMessage] = useState(null);

    // Form inputs state
    const [formData, setFormData] = useState({
        hall_type_id: '',
        name: '',
        capacity: '',
        area: '',
        price_description: '',
        status: 'available',
        description: '',
    });
    const [formErrors, setFormErrors] = useState({});
    const [isSubmitting, setIsSubmitting] = useState(false);

    // Toast helper
    const showToast = (msg, type = 'success') => {
        setToastMessage({ text: msg, type });
        setTimeout(() => {
            setToastMessage(null);
        }, 3500);
    };

    /**
     * Fetch Hall Types for dropdown filter & assignment
     */
    const fetchHallTypesList = useCallback(async () => {
        try {
            const res = await api.get('/hall_type/list.php?limit=100');
            if (res.success && res.data?.status && Array.isArray(res.data.data)) {
                setHallTypes(res.data.data);
            } else if (res.data?.data && Array.isArray(res.data.data)) {
                setHallTypes(res.data.data);
            }
        } catch (err) {
            console.warn('Backend hall types fetch note:', err.message);
        }
    }, []);

    /**
     * Fetch Hall Facilities
     */
    const fetchFacilitiesList = useCallback(async () => {
        try {
            const res = await api.get('/hall_facilities/list.php?limit=100');
            if (res.success && res.data?.status && Array.isArray(res.data.data)) {
                setAvailableFacilities(res.data.data);
            } else if (res.data?.data && Array.isArray(res.data.data)) {
                setAvailableFacilities(res.data.data);
            }
        } catch (err) {
            console.warn('Backend hall facilities fetch note:', err.message);
        }
    }, []);

    useEffect(() => {
        fetchHallTypesList();
        fetchFacilitiesList();
    }, [fetchHallTypesList, fetchFacilitiesList]);

    /**
     * Fetch Halls using backend pagination endpoint
     */
    const fetchHalls = useCallback(
        async (
            page = currentPage,
            limit = pageSize,
            search = searchQuery,
            typeId = selectedTypeFilter,
            status = selectedStatusFilter
        ) => {
            setIsLoading(true);
            try {
                const params = new URLSearchParams();
                params.append('page', String(page));
                params.append('limit', String(limit));
                if (search && search.trim()) params.append('search', search.trim());
                if (typeId && typeId !== 'all') params.append('hall_type_id', String(typeId));
                if (status && status !== 'all') params.append('status', status);

                const res = await api.get(`/hall/list.php?${params.toString()}`);
                if (res.success && res.data?.status && Array.isArray(res.data.data)) {
                    setHalls(res.data.data);
                    if (res.data.pagination) {
                        setTotalRecords(res.data.pagination.total_records ?? res.data.data.length);
                        setTotalPages(res.data.pagination.total_pages ?? 1);
                    }
                } else if (res.data?.data && Array.isArray(res.data.data)) {
                    setHalls(res.data.data);
                    if (res.data.pagination) {
                        setTotalRecords(res.data.pagination.total_records ?? res.data.data.length);
                        setTotalPages(res.data.pagination.total_pages ?? 1);
                    }
                } else {
                    setHalls([]);
                    setTotalRecords(0);
                    setTotalPages(1);
                }
            } catch (err) {
                console.warn('Backend hall fetch note:', err.message);
                showToast('Failed to load halls from server.', 'error');
            } finally {
                setIsLoading(false);
            }
        },
        [currentPage, pageSize, searchQuery, selectedTypeFilter, selectedStatusFilter]
    );

    useEffect(() => {
        fetchHalls(currentPage, pageSize, searchQuery, selectedTypeFilter, selectedStatusFilter);
    }, [fetchHalls, currentPage, pageSize, searchQuery, selectedTypeFilter, selectedStatusFilter]);

    // Summary stats
    const stats = useMemo(() => {
        const total = totalRecords || halls.length;
        const active = halls.filter((h) => {
            const s = (h.status || '').toLowerCase();
            return s === 'available' || s === 'active';
        }).length;
        const maintenance = halls.filter((h) => (h.status || '').toLowerCase() === 'maintenance').length;
        const inactive = halls.filter((h) => (h.status || '').toLowerCase() === 'inactive').length;
        const totalCapacity = halls.reduce((sum, h) => sum + (Number(h.capacity) || 0), 0);
        return { total, active, maintenance, inactive, totalCapacity };
    }, [halls, totalRecords]);

    // Validation matching backend rules
    const validateForm = () => {
        const errors = {};
        const trimmedName = formData.name.trim();

        if (!formData.hall_type_id) {
            errors.hall_type_id = 'Please select a hall type.';
        }

        if (!trimmedName) {
            errors.name = 'Hall name is required.';
        } else if (trimmedName.length < 2) {
            errors.name = 'Hall name must be at least 2 characters.';
        } else if (trimmedName.length > 150) {
            errors.name = 'Hall name cannot exceed 150 characters.';
        }

        if (!formData.capacity) {
            errors.capacity = 'Capacity is required.';
        } else if (Number(formData.capacity) <= 0 || !Number.isInteger(Number(formData.capacity))) {
            errors.capacity = 'Capacity must be a positive integer.';
        }

        const trimmedArea = formData.area !== null && formData.area !== undefined ? String(formData.area).trim() : '';
        if (trimmedArea !== '') {
            const numArea = Number(trimmedArea);
            if (isNaN(numArea) || numArea <= 0) {
                errors.area = 'Area must be a valid positive number.';
            }
        }

        if (formData.description && formData.description.length > 2000) {
            errors.description = 'Description cannot exceed 2000 characters.';
        }

        if (formData.price_description && formData.price_description.length > 500) {
            errors.price_description = 'Price description cannot exceed 500 characters.';
        }

        setFormErrors(errors);
        return Object.keys(errors).length === 0;
    };

    // Open add modal
    const handleOpenAdd = () => {
        setFormMode('create');
        setEditingHall(null);
        setFormData({
            hall_type_id: hallTypes.length > 0 ? String(hallTypes[0].id) : '',
            name: '',
            capacity: '',
            area: '',
            price_description: '',
            status: 'available',
            description: '',
        });
        setSelectedFacilityIds([]);
        setPendingImages([]);
        setImageFile(null);
        setImagePreview(null);
        setNewImageAlt('');
        setNewImageIsPrimary(false);
        if (fileInputRef.current) fileInputRef.current.value = '';
        setFormErrors({});
        fetchFacilitiesList();
        setIsFormModalOpen(true);
    };

    // Open edit modal
    const handleOpenEdit = async (hall) => {
        setFormMode('edit');
        setEditingHall(hall);
        setFormData({
            hall_type_id: String(hall.hall_type_id || hall.hall_type?.id || ''),
            name: hall.name || '',
            capacity: hall.capacity ? String(hall.capacity) : '',
            area: extractNumericArea(hall.area),
            price_description: hall.price_description || '',
            status: (hall.status === 'active' || hall.status === 'available') ? 'available' : (hall.status || 'available'),
            description: hall.description || '',
        });
        const currentFacIds = (hall.facilities || []).map((f) => (typeof f === 'object' ? f.id : Number(f)));
        setSelectedFacilityIds(currentFacIds);
        setPendingImages([]);
        setImageFile(null);
        setImagePreview(null);
        setNewImageAlt('');
        setNewImageIsPrimary(false);
        if (fileInputRef.current) fileInputRef.current.value = '';
        setFormErrors({});
        fetchFacilitiesList();
        setIsFormModalOpen(true);

        // Fetch fresh hall details to ensure latest images & facilities
        try {
            const freshRes = await hallApi.view(hall.id);
            if (freshRes.success && freshRes.data?.data) {
                const freshHall = freshRes.data.data;
                setEditingHall(freshHall);
                const freshFacIds = (freshHall.facilities || []).map((f) => (typeof f === 'object' ? f.id : Number(f)));
                setSelectedFacilityIds(freshFacIds);
            }
        } catch (err) {
            console.warn('Could not refresh editing hall data:', err.message);
        }
    };

    // Open view modal
    const handleOpenView = (hall) => {
        setViewingHall(hall);
        setActiveViewImageIndex(0);
        setIsViewModalOpen(true);
    };

    // Open delete confirmation modal
    const handleOpenDelete = (hall) => {
        setDeletingHall(hall);
        setIsDeleteModalOpen(true);
    };

    // Add photo to staging queue (Create mode)
    const handleAddPendingPhoto = () => {
        if (!imageFile) {
            showToast('Please select an image file first.', 'error');
            return;
        }
        const newPending = {
            id: Date.now() + Math.random(),
            file: imageFile,
            preview: imagePreview,
            altText: newImageAlt.trim(),
            isPrimary: newImageIsPrimary,
        };
        // If this one is marked primary, unmark others in pending
        if (newImageIsPrimary) {
            setPendingImages((prev) => prev.map((img) => ({ ...img, isPrimary: false })).concat(newPending));
        } else {
            setPendingImages((prev) => [...prev, newPending]);
        }
        setImageFile(null);
        setImagePreview(null);
        setNewImageAlt('');
        setNewImageIsPrimary(false);
        if (fileInputRef.current) fileInputRef.current.value = '';
    };

    // Remove photo from staging queue (Create mode)
    const handleRemovePendingPhoto = (pendingId) => {
        setPendingImages((prev) => prev.filter((img) => img.id !== pendingId));
    };

    // Upload photo directly in Edit mode
    const handleUploadPhotoInEdit = async () => {
        if (!imageFile || !editingHall?.id) {
            showToast('Please choose an image file to upload.', 'error');
            return;
        }
        setIsUploadingImage(true);
        try {
            const res = await hallApi.uploadImage(
                editingHall.id,
                imageFile,
                newImageAlt.trim(),
                newImageIsPrimary
            );
            if (res.success && res.data?.status) {
                showToast('Image uploaded successfully!', 'success');
                setImagePreview(null);
                setImageFile(null);
                setNewImageAlt('');
                setNewImageIsPrimary(false);
                if (fileInputRef.current) fileInputRef.current.value = '';

                // Refresh editing hall & list
                const refreshed = await hallApi.view(editingHall.id);
                if (refreshed.success && refreshed.data?.data) {
                    setEditingHall(refreshed.data.data);
                }
                fetchHalls(currentPage, pageSize, searchQuery, selectedTypeFilter, selectedStatusFilter);
            } else {
                showToast(res.error || res.data?.message || 'Failed to upload image.', 'error');
            }
        } catch (err) {
            showToast(err.message || 'Image upload failed.', 'error');
        } finally {
            setIsUploadingImage(false);
        }
    };

    // Delete existing photo in Edit mode
    const handleDeletePhotoInEdit = async (imageId) => {
        if (!editingHall?.id || !imageId) return;
        try {
            const res = await hallApi.deleteImage(editingHall.id, imageId);
            if (res.success && res.data?.status) {
                showToast('Image removed.', 'success');
                const refreshed = await hallApi.view(editingHall.id);
                if (refreshed.success && refreshed.data?.data) {
                    setEditingHall(refreshed.data.data);
                }
                fetchHalls(currentPage, pageSize, searchQuery, selectedTypeFilter, selectedStatusFilter);
            } else {
                showToast(res.error || res.data?.message || 'Could not delete image.', 'error');
            }
        } catch (err) {
            showToast(err.message || 'Failed to delete image.', 'error');
        }
    };

    // Handle Image file selection
    const handleImageChange = (e) => {
        const file = e.target.files?.[0];
        if (file) {
            if (file.size > 5 * 1024 * 1024) {
                showToast('Image file size must be less than 5MB.', 'error');
                return;
            }
            setImageFile(file);
            const reader = new FileReader();
            reader.onloadend = () => {
                setImagePreview(reader.result);
            };
            reader.readAsDataURL(file);
        }
    };

    // Quick status toggle
    const handleToggleStatus = async (hall) => {
        const current = (hall.status || '').toLowerCase();
        let nextStatus = 'available';
        if (current === 'available' || current === 'active') {
            nextStatus = 'maintenance';
        } else if (current === 'maintenance') {
            nextStatus = 'inactive';
        } else {
            nextStatus = 'available';
        }
        try {
            const payload = {
                hall_id: hall.id,
                status: nextStatus,
            };
            const res = await api.post('/hall/update.php', payload);
            if (res.success && res.data?.status) {
                const info = getHallStatusInfo(nextStatus);
                showToast(`Status updated to ${info.label}.`, 'success');
                fetchHalls(currentPage, pageSize, searchQuery, selectedTypeFilter, selectedStatusFilter);
            } else {
                showToast(res.error || res.data?.message || 'Could not update status.', 'error');
            }
        } catch (err) {
            showToast(err.message || 'Failed to update status.', 'error');
        }
    };

    // Delete Hall
    const handleConfirmDelete = async () => {
        if (!deletingHall) return;
        setIsSubmitting(true);
        try {
            const res = await api.post('/hall/delete.php', {
                hall_id: deletingHall.id,
            });
            if (res.success && res.data?.status) {
                showToast('Hall deleted successfully.', 'success');
                setIsDeleteModalOpen(false);
                setDeletingHall(null);
                fetchHalls(currentPage, pageSize, searchQuery, selectedTypeFilter, selectedStatusFilter);
            } else {
                showToast(res.error || res.data?.message || 'Could not delete hall.', 'error');
            }
        } catch (err) {
            showToast(err.message || 'Failed to delete hall.', 'error');
        } finally {
            setIsSubmitting(false);
        }
    };

    // Submit Hall Create / Edit with unified facilities & images flow
    const handleFormSubmit = async (e) => {
        e.preventDefault();
        if (!validateForm()) return;

        // Facility safety check: prevent newly assigning inactive facilities
        const initialFacIds = formMode === 'edit' && editingHall?.facilities
            ? editingHall.facilities.map((f) => (typeof f === 'object' ? f.id : Number(f)))
            : [];
        const newlyAddedInactive = selectedFacilityIds.filter((id) => {
            if (initialFacIds.includes(id)) return false;
            const fac = availableFacilities.find((f) => f.id === id);
            return fac && fac.status !== 'active';
        });

        if (newlyAddedInactive.length > 0) {
            setFormErrors({ submit: 'Inactive facilities cannot be newly assigned to a hall.' });
            return;
        }

        setIsSubmitting(true);
        try {
            const trimmedArea = formData.area !== null && formData.area !== undefined ? String(formData.area).trim() : '';
            const payload = {
                hall_type_id: Number(formData.hall_type_id),
                name: formData.name.trim(),
                capacity: Number(formData.capacity),
                area: trimmedArea !== '' ? trimmedArea : null,
                price_description: formData.price_description ? formData.price_description.trim() || null : null,
                status: formData.status,
                description: formData.description ? formData.description.trim() || null : null,
            };

            if (formMode === 'create') {
                // Step 1: Create basic hall
                const res = await api.post('/hall/create.php', payload);
                if (!res.success || !res.data?.status) {
                    setFormErrors({ submit: res.error || res.data?.message || 'Failed to create hall venue.' });
                    setIsSubmitting(false);
                    return;
                }

                const newHallId = res.data?.data?.id;

                // Step 2: Update facilities if any selected
                if (newHallId && selectedFacilityIds.length > 0) {
                    try {
                        await api.post('/hall/update_facilities.php', {
                            hall_id: newHallId,
                            facility_ids: selectedFacilityIds,
                        });
                    } catch (facErr) {
                        console.warn('Failed to assign facilities during creation:', facErr.message);
                    }
                }

                // Step 3: Upload any queued / pending images + currently selected image
                const allImagesToUpload = [...pendingImages];
                if (imageFile) {
                    allImagesToUpload.push({
                        file: imageFile,
                        altText: newImageAlt.trim(),
                        isPrimary: newImageIsPrimary,
                    });
                }

                if (newHallId && allImagesToUpload.length > 0) {
                    for (const img of allImagesToUpload) {
                        try {
                            await hallApi.uploadImage(
                                newHallId,
                                img.file,
                                img.altText || '',
                                img.isPrimary
                            );
                        } catch (imgErr) {
                            console.warn('Failed to upload image during creation:', imgErr.message);
                        }
                    }
                }

                showToast('Hall venue created successfully with facilities and images!', 'success');
                setIsFormModalOpen(false);
                fetchHalls(1, pageSize, searchQuery, selectedTypeFilter, selectedStatusFilter);
            } else {
                // Edit Mode:
                // Step 1: Update basic details
                payload.hall_id = editingHall.id;
                const res = await api.post('/hall/update.php', payload);
                if (!res.success || !res.data?.status) {
                    setFormErrors({ submit: res.error || res.data?.message || 'Failed to update hall specifications.' });
                    setIsSubmitting(false);
                    return;
                }

                // Step 2: Update facilities
                try {
                    await api.post('/hall/update_facilities.php', {
                        hall_id: editingHall.id,
                        facility_ids: selectedFacilityIds,
                    });
                } catch (facErr) {
                    console.warn('Failed to update facilities during edit:', facErr.message);
                }

                // Step 3: Upload any remaining un-uploaded image file selected in form
                if (imageFile) {
                    try {
                        await hallApi.uploadImage(
                            editingHall.id,
                            imageFile,
                            newImageAlt.trim(),
                            newImageIsPrimary
                        );
                    } catch (imgErr) {
                        console.warn('Failed to upload image during edit:', imgErr.message);
                    }
                }

                showToast('Hall venue specifications, facilities, and images saved successfully!', 'success');
                setIsFormModalOpen(false);
                fetchHalls(currentPage, pageSize, searchQuery, selectedTypeFilter, selectedStatusFilter);
            }
        } catch (err) {
            setFormErrors({ submit: err.message || 'An error occurred while saving.' });
        } finally {
            setIsSubmitting(false);
        }
    };

    // React Select search options
    const searchSelectOptions = useMemo(() => {
        return halls.map((h) => ({
            value: h.name,
            label: `${h.name} (${getHallTypeName(h, hallTypes)})`,
        }));
    }, [halls, hallTypes]);

    const currentSelectValue = useMemo(() => {
        if (!searchQuery) return null;
        return searchSelectOptions.find((opt) => opt.value === searchQuery) || { value: searchQuery, label: searchQuery };
    }, [searchQuery, searchSelectOptions]);

    // Hall Type options for filter
    const hallTypeFilterOptions = useMemo(() => {
        return [
            { value: 'all', label: 'All Hall Types' },
            ...hallTypes.map((ht) => ({ value: String(ht.id), label: ht.name })),
        ];
    }, [hallTypes]);

    return (
        <div className="rooms-module halls-module">
            {/* Toast Notification */}
            {toastMessage && (
                <div className={`admin-toast admin-toast--${toastMessage.type}`}>
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                        <path d="M22 11.08 V12 a10 10 0 1 1 -5.93 -9.14" />
                        <polyline points="22 4 12 14.01 9 11.01" />
                    </svg>
                    <span>{toastMessage.text}</span>
                </div>
            )}

            {/* Dashboard Page Header */}
            <div className="dashboard-page-header">
                <div className="dashboard-page-header__left">
                    <h2 className="dashboard-page-header__title">Halls & Venues Management</h2>
                    <p className="dashboard-page-header__desc">
                        Manage convention halls, wedding mahals, seating capacities, pricing tariffs, and event amenities.
                    </p>
                </div>
                <div className="dashboard-page-header__actions">
                    <button
                        type="button"
                        className="admin-primary-btn"
                        onClick={handleOpenAdd}
                        id="add-hall-btn"
                    >
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round">
                            <line x1="12" y1="5" x2="12" y2="19" />
                            <line x1="5" y1="12" x2="19" y2="12" />
                        </svg>
                        <span>Add New Hall</span>
                    </button>
                </div>
            </div>

            {/* Stats Cards Overview Grid */}
            <div className="room-types-stats-grid">
                <div className="room-type-stat-card">
                    <div className="room-type-stat-card__icon room-type-stat-card__icon--gold">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                            <rect x="3" y="3" width="7" height="9" rx="1" />
                            <rect x="14" y="3" width="7" height="5" rx="1" />
                            <rect x="14" y="12" width="7" height="9" rx="1" />
                            <rect x="3" y="16" width="7" height="5" rx="1" />
                        </svg>
                    </div>
                    <div className="room-type-stat-card__info">
                        <span className="room-type-stat-card__label">Total Halls</span>
                        <span className="room-type-stat-card__value">{stats.total}</span>
                    </div>
                </div>

                <div className="room-type-stat-card">
                    <div className="room-type-stat-card__icon room-type-stat-card__icon--green">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                            <path d="M22 11.08 V12 a10 10 0 1 1 -5.93 -9.14" />
                            <polyline points="22 4 12 14.01 9 11.01" />
                        </svg>
                    </div>
                    <div className="room-type-stat-card__info">
                        <span className="room-type-stat-card__label">Active Venues</span>
                        <span className="room-type-stat-card__value">{stats.active}</span>
                    </div>
                </div>

                <div className="room-type-stat-card">
                    <div className="room-type-stat-card__icon room-type-stat-card__icon--blue">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                            <path d="M17 21 v-2 a4 4 0 0 0 -4 -4 H5 a4 4 0 0 0 -4 4 v2" />
                            <circle cx="9" cy="7" r="4" />
                            <path d="M23 21 v-2 a4 4 0 0 0 -3 -3.87" />
                            <path d="M16 3.13 a4 4 0 0 1 0 7.75" />
                        </svg>
                    </div>
                    <div className="room-type-stat-card__info">
                        <span className="room-type-stat-card__label">Total Capacity</span>
                        <span className="room-type-stat-card__value">{stats.totalCapacity.toLocaleString()}</span>
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
                        <span className="room-type-stat-card__label">Inactive Venues</span>
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
                        placeholder="Search or select hall venue..."
                        isClearable
                        isSearchable
                        styles={reactSelectStyles}
                        className="react-select-container"
                        classNamePrefix="react-select"
                        noOptionsMessage={({ inputValue }) =>
                            inputValue ? `No hall matches "${inputValue}"` : 'No options'
                        }
                    />
                </div>

                <div className="room-types-search-select-wrap" style={{ maxWidth: '240px' }}>
                    <Select
                        options={hallTypeFilterOptions}
                        value={hallTypeFilterOptions.find((opt) => opt.value === selectedTypeFilter)}
                        onChange={(selected) => {
                            setSelectedTypeFilter(selected ? selected.value : 'all');
                            setCurrentPage(1);
                        }}
                        placeholder="Filter by Hall Type"
                        styles={reactSelectStyles}
                        className="react-select-container"
                        classNamePrefix="react-select"
                    />
                </div>

                <div className="room-types-filter-group">
                    <button
                        type="button"
                        className={`room-types-filter-pill ${selectedStatusFilter === 'all' ? 'active' : ''}`}
                        onClick={() => {
                            setSelectedStatusFilter('all');
                            setCurrentPage(1);
                        }}
                    >
                        All ({stats.total})
                    </button>
                    <button
                        type="button"
                        className={`room-types-filter-pill ${selectedStatusFilter === 'available' ? 'active' : ''}`}
                        onClick={() => {
                            setSelectedStatusFilter('available');
                            setCurrentPage(1);
                        }}
                    >
                        Active ({stats.active})
                    </button>
                    <button
                        type="button"
                        className={`room-types-filter-pill ${selectedStatusFilter === 'maintenance' ? 'active' : ''}`}
                        onClick={() => {
                            setSelectedStatusFilter('maintenance');
                            setCurrentPage(1);
                        }}
                    >
                        Maintenance ({stats.maintenance})
                    </button>
                    <button
                        type="button"
                        className={`room-types-filter-pill ${selectedStatusFilter === 'inactive' ? 'active' : ''}`}
                        onClick={() => {
                            setSelectedStatusFilter('inactive');
                            setCurrentPage(1);
                        }}
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
                        <p style={{ fontSize: '0.9rem', fontWeight: 500 }}>Loading halls from server...</p>
                    </div>
                ) : halls.length === 0 ? (
                    <div className="dashboard-empty-state">
                        <div className="dashboard-empty-state__icon-wrap">
                            <svg width="42" height="42" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round">
                                <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" />
                                <polyline points="9 22 9 12 15 12 15 22" />
                            </svg>
                        </div>
                        <h4 className="dashboard-empty-state__title">No halls found</h4>
                        <p className="dashboard-empty-state__desc">
                            {searchQuery || selectedTypeFilter !== 'all' || selectedStatusFilter !== 'all'
                                ? 'No halls matched your filter criteria. Try resetting filters.'
                                : 'No halls have been created yet. Click below to add your first hall.'}
                        </p>
                        {searchQuery || selectedTypeFilter !== 'all' || selectedStatusFilter !== 'all' ? (
                            <button
                                type="button"
                                className="admin-secondary-btn"
                                onClick={() => {
                                    setSearchQuery('');
                                    setSelectedTypeFilter('all');
                                    setSelectedStatusFilter('all');
                                    setCurrentPage(1);
                                }}
                            >
                                Reset Filters
                            </button>
                        ) : (
                            <button
                                type="button"
                                className="admin-primary-btn"
                                onClick={handleOpenAdd}
                            >
                                + Add First Hall
                            </button>
                        )}
                    </div>
                ) : (
                    <>
                        <div className="admin-table-responsive">
                            <table className="admin-table">
                                <thead>
                                    <tr>
                                        <th style={{ width: '80px' }}>Photo</th>
                                        <th style={{ width: '220px' }}>Hall Details</th>
                                        <th style={{ width: '150px' }}>Hall Type</th>
                                        <th style={{ width: '140px' }}>Capacity / Area</th>
                                        <th style={{ width: '160px' }}>Tariff Details</th>
                                        <th style={{ width: '120px' }}>Status</th>
                                        <th style={{ textAlign: 'right', width: '160px' }}>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {halls.map((hall) => {
                                        const primaryImg = getPrimaryImage(hall);
                                        const imgCount = hall.images?.length || 0;

                                        return (
                                            <tr key={hall.id} className="admin-table-row">
                                                <td>
                                                    {primaryImg ? (
                                                        <div
                                                            className="room-table-thumbnail-wrap"
                                                            onClick={() => setFullViewImage({ url: primaryImg, alt: hall.name, title: hall.name })}
                                                            title="Click to view full image"
                                                            style={{ cursor: 'pointer' }}
                                                        >
                                                            <img
                                                                src={primaryImg}
                                                                alt={hall.name}
                                                                className="room-table-thumbnail"
                                                            />
                                                            {imgCount > 1 && (
                                                                <span className="room-thumbnail-badge">+{imgCount - 1}</span>
                                                            )}
                                                        </div>
                                                    ) : (
                                                        <div
                                                            className="room-table-thumbnail-wrap room-table-thumbnail-wrap--empty"
                                                            onClick={() => handleOpenImages(hall)}
                                                            title="No photo available. Click to upload."
                                                            style={{
                                                                cursor: 'pointer',
                                                                display: 'flex',
                                                                flexDirection: 'column',
                                                                alignItems: 'center',
                                                                justifyContent: 'center',
                                                                background: '#f8fafc',
                                                                border: '1px dashed #cbd5e1',
                                                                borderRadius: '8px',
                                                                width: '60px',
                                                                height: '46px',
                                                                color: '#94a3b8',
                                                                textAlign: 'center',
                                                                padding: '2px',
                                                            }}
                                                        >
                                                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round">
                                                                <rect x="3" y="3" width="18" height="18" rx="2" ry="2" />
                                                                <circle cx="8.5" cy="8.5" r="1.5" />
                                                                <polyline points="21 15 16 10 5 21" />
                                                            </svg>
                                                            <span style={{ fontSize: '0.62rem', lineHeight: 1.1, marginTop: '2px', fontWeight: 600 }}>No photo</span>
                                                        </div>
                                                    )}
                                                </td>
                                                <td>
                                                    <div className="room-name-cell">
                                                        <span className="room-name-title">{hall.name}</span>
                                                        <span className="room-number-badge">#{hall.id} &bull; {hall.slug}</span>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span className="room-type-tag">
                                                        {getHallTypeName(hall, hallTypes)}
                                                    </span>
                                                </td>
                                                <td>
                                                    <div className="room-capacity-cell">
                                                        <span className="room-capacity-badge">
                                                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                                                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                                                                <circle cx="9" cy="7" r="4" />
                                                            </svg>
                                                            {Number(hall.capacity).toLocaleString()} Pax
                                                        </span>
                                                        {hall.area && (
                                                            <small className="room-bed-type-label">
                                                                {extractNumericArea(hall.area)
                                                                    ? `${Number(extractNumericArea(hall.area)).toLocaleString()} sq ft`
                                                                    : hall.area}
                                                            </small>
                                                        )}
                                                    </div>
                                                </td>
                                                <td>
                                                    <span className="room-price-val">
                                                        {hall.price_description || 'On Request'}
                                                    </span>
                                                </td>
                                                <td>
                                                    {(() => {
                                                        const statusInfo = getHallStatusInfo(hall.status);
                                                        return (
                                                            <button
                                                                type="button"
                                                                className={`admin-status-badge ${statusInfo.badgeClass}`}
                                                                onClick={() => handleToggleStatus(hall)}
                                                                title={`Current status: ${statusInfo.label}. Click to change status.`}
                                                                style={{ cursor: 'pointer', border: 'none', background: 'none' }}
                                                            >
                                                                <span className="admin-status-dot" />
                                                                {statusInfo.label}
                                                            </button>
                                                        );
                                                    })()}
                                                </td>
                                                <td style={{ textAlign: 'right' }}>
                                                    <div className="admin-table-actions">
                                                        <button
                                                            type="button"
                                                            className="admin-action-btn admin-action-btn--view"
                                                            onClick={() => handleOpenView(hall)}
                                                            title="View Details"
                                                            aria-label={`View ${hall.name}`}
                                                        >
                                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                                                <path d="M1 12 s4 -8 11 -8 11 8 11 8 -4 8 -11 8 -11 -8 -11 -8 z" />
                                                                <circle cx="12" cy="12" r="3" />
                                                            </svg>
                                                        </button>

                                                        <button
                                                            type="button"
                                                            className="admin-action-btn admin-action-btn--edit"
                                                            onClick={() => handleOpenEdit(hall)}
                                                            title="Edit Hall"
                                                            aria-label={`Edit ${hall.name}`}
                                                        >
                                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                                                <path d="M11 4 H4 a2 2 0 0 0 -2 2 v14 a2 2 0 0 0 2 2 h14 a2 2 0 0 0 2 -2 v-7" />
                                                                <path d="M18.5 2.5 a2.121 2.121 0 0 1 3 3 L12 15 l-4 1 1 -4 9.5 -9.5 z" />
                                                            </svg>
                                                        </button>
                                                        <button
                                                            type="button"
                                                            className="admin-action-btn admin-action-btn--delete"
                                                            onClick={() => handleOpenDelete(hall)}
                                                            title="Delete Hall"
                                                            aria-label={`Delete ${hall.name}`}
                                                        >
                                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                                                <polyline points="3 6 5 6 21 6" />
                                                                <path d="M19 6 v14 a2 2 0 0 1 -2 2 H7 a2 2 0 0 1 -2 -2 V6 m3 0 V4 a2 2 0 0 1 2 -2 h4 a2 2 0 0 1 2 2 v2" />
                                                            </svg>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        );
                                    })}
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
                    <div className="admin-modal admin-modal--large" onClick={(e) => e.stopPropagation()} role="dialog" aria-modal="true">
                        <div className="admin-modal__header">
                            <div className="admin-modal__title-wrap">
                                <h3 className="admin-modal__title">
                                    {formMode === 'create' ? 'Create New Hall Venue' : 'Edit Hall Specifications'}
                                </h3>
                                <p className="admin-modal__subtitle">
                                    {formMode === 'create'
                                        ? 'Configure venue details, assign amenities, and upload gallery photos.'
                                        : `Updating venue details, amenities, and photos for ${editingHall?.name}`}
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

                        <form onSubmit={handleFormSubmit} className="admin-modal__form">
                            <div className="admin-modal__body" style={{ maxHeight: '75vh', overflowY: 'auto' }}>
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

                                {/* ════ SECTION 1: BASIC SPECIFICATIONS ════ */}
                                <div className="admin-form-section">
                                    <div className="admin-form-section-header">
                                        <h4 className="admin-form-section-title">
                                            1. Venue Specifications
                                        </h4>
                                        <span className="admin-form-section-desc">Primary details, seating capacity, and status</span>
                                    </div>

                                    {/* Row 1: Hall Type Selection & Hall Name */}
                                    <div className="admin-form-row-2">
                                        <div className="admin-form-group">
                                            <label className="admin-form-label" htmlFor="form-hall-type">
                                                Hall Type Category <span className="admin-form-required">*</span>
                                            </label>
                                            <Select
                                                id="form-hall-type"
                                                options={hallTypes.map((ht) => ({ value: String(ht.id), label: ht.name }))}
                                                value={
                                                    hallTypes
                                                        .map((ht) => ({ value: String(ht.id), label: ht.name }))
                                                        .find((opt) => opt.value === String(formData.hall_type_id)) || null
                                                }
                                                onChange={(selected) => {
                                                    setFormData((prev) => ({ ...prev, hall_type_id: selected ? selected.value : '' }));
                                                    if (formErrors.hall_type_id) setFormErrors((prev) => ({ ...prev, hall_type_id: null }));
                                                }}
                                                placeholder="Select hall category..."
                                                styles={reactSelectStyles}
                                                className="react-select-container"
                                                classNamePrefix="react-select"
                                            />
                                            {formErrors.hall_type_id && (
                                                <span className="admin-form-error">{formErrors.hall_type_id}</span>
                                            )}
                                        </div>

                                        <div className="admin-form-group">
                                            <div className="admin-form-label-row">
                                                <label className="admin-form-label" htmlFor="hall-name">
                                                    Hall Name <span className="admin-form-required">*</span>
                                                </label>
                                                {formData.name && (
                                                    <span className="admin-form-helper" style={{ margin: 0, fontSize: '0.74rem' }}>
                                                        Slug: <code>{generateSlug(formData.name)}</code>
                                                    </span>
                                                )}
                                            </div>
                                            <input
                                                id="hall-name"
                                                type="text"
                                                className={`admin-form-input ${formErrors.name ? 'admin-form-input--error' : ''}`}
                                                placeholder="e.g. Royal Grand Palace Mahal"
                                                value={formData.name}
                                                maxLength={150}
                                                onChange={(e) => {
                                                    setFormData((prev) => ({ ...prev, name: e.target.value }));
                                                    if (formErrors.name) setFormErrors((prev) => ({ ...prev, name: null }));
                                                }}
                                                disabled={isSubmitting}
                                                required
                                            />
                                            {formErrors.name && (
                                                <span className="admin-form-error">{formErrors.name}</span>
                                            )}
                                        </div>
                                    </div>

                                    {/* Row 2: Capacity & Floor Area */}
                                    <div className="admin-form-row-2">
                                        <div className="admin-form-group">
                                            <label className="admin-form-label" htmlFor="hall-capacity">
                                                Seating Capacity (Pax) <span className="admin-form-required">*</span>
                                            </label>
                                            <input
                                                id="hall-capacity"
                                                type="number"
                                                className={`admin-form-input ${formErrors.capacity ? 'admin-form-input--error' : ''}`}
                                                placeholder="e.g. 750"
                                                min="1"
                                                max="100000"
                                                value={formData.capacity}
                                                onChange={(e) => {
                                                    setFormData((prev) => ({ ...prev, capacity: e.target.value }));
                                                    if (formErrors.capacity) setFormErrors((prev) => ({ ...prev, capacity: null }));
                                                }}
                                                disabled={isSubmitting}
                                                required
                                            />
                                            {formErrors.capacity && (
                                                <span className="admin-form-error">{formErrors.capacity}</span>
                                            )}
                                        </div>

                                        <div className="admin-form-group">
                                            <label className="admin-form-label" htmlFor="hall-area">
                                                Floor Area (Sq. Ft.)
                                            </label>
                                            <input
                                                id="hall-area"
                                                type="number"
                                                step="0.01"
                                                className={`admin-form-input ${formErrors.area ? 'admin-form-input--error' : ''}`}
                                                placeholder="e.g. 6500"
                                                value={formData.area}
                                                onChange={(e) => {
                                                    setFormData((prev) => ({ ...prev, area: e.target.value }));
                                                    if (formErrors.area) setFormErrors((prev) => ({ ...prev, area: null }));
                                                }}
                                                disabled={isSubmitting}
                                            />
                                            {formErrors.area && (
                                                <span className="admin-form-error">{formErrors.area}</span>
                                            )}
                                        </div>
                                    </div>

                                    {/* Row 3: Price Description & Availability Status */}
                                    <div className="admin-form-row-2">
                                        <div className="admin-form-group">
                                            <label className="admin-form-label" htmlFor="hall-price-desc">
                                                Price / Tariff Details
                                            </label>
                                            <input
                                                id="hall-price-desc"
                                                type="text"
                                                className={`admin-form-input ${formErrors.price_description ? 'admin-form-input--error' : ''}`}
                                                placeholder="e.g. Contact management for package details."
                                                maxLength={255}
                                                value={formData.price_description}
                                                onChange={(e) => {
                                                    setFormData((prev) => ({ ...prev, price_description: e.target.value }));
                                                    if (formErrors.price_description) setFormErrors((prev) => ({ ...prev, price_description: null }));
                                                }}
                                                disabled={isSubmitting}
                                            />
                                            {formErrors.price_description && (
                                                <span className="admin-form-error">{formErrors.price_description}</span>
                                            )}
                                        </div>

                                        <div className="admin-form-group">
                                            <label className="admin-form-label" htmlFor="hall-status">
                                                Availability Status <span className="admin-form-required">*</span>
                                            </label>
                                            <select
                                                id="hall-status"
                                                className="admin-form-select"
                                                value={formData.status}
                                                onChange={(e) => setFormData((prev) => ({ ...prev, status: e.target.value }))}
                                                disabled={isSubmitting}
                                            >
                                                <option value="available">Available (Active & bookable)</option>
                                                <option value="maintenance">Maintenance (Under repair / cleaning)</option>
                                                <option value="inactive">Inactive (Hidden)</option>
                                            </select>
                                        </div>
                                    </div>

                                    {/* Description */}
                                    <div className="admin-form-group">
                                        <div className="admin-form-label-row">
                                            <label className="admin-form-label" htmlFor="hall-description">
                                                Description & Venue Features
                                            </label>
                                            <span className="admin-char-count">
                                                {(formData.description || '').length} / 2000
                                            </span>
                                        </div>
                                        <textarea
                                            id="hall-description"
                                            rows={3}
                                            className={`admin-form-textarea ${formErrors.description ? 'admin-form-input--error' : ''}`}
                                            placeholder="Provide venue details such as dining hall capacity, AC type, generator backup, stage dimensions, parking capacity, etc."
                                            value={formData.description}
                                            maxLength={2000}
                                            onChange={(e) => {
                                                setFormData((prev) => ({ ...prev, description: e.target.value }));
                                                if (formErrors.description) setFormErrors((prev) => ({ ...prev, description: null }));
                                            }}
                                            disabled={isSubmitting}
                                        />
                                        {formErrors.description && (
                                            <span className="admin-form-error">{formErrors.description}</span>
                                        )}
                                    </div>
                                </div>

                                {/* ════ SECTION 2: EVENT FACILITIES & AMENITIES ════ */}
                                <div className="admin-form-section" style={{ marginTop: '24px' }}>
                                    <div className="admin-form-section-header">
                                        <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', width: '100%' }}>
                                            <div>
                                                <h4 className="admin-form-section-title">
                                                    2. Event Facilities & Amenities
                                                </h4>
                                                <span className="admin-form-section-desc">Select active features and services provided with this hall</span>
                                            </div>
                                            <span className="admin-facility-count-badge" style={{ fontSize: '0.8rem', fontWeight: 600, color: 'var(--brand-gold-dark, #b48512)', background: 'rgba(212, 160, 23, 0.12)', padding: '4px 10px', borderRadius: '20px' }}>
                                                {selectedFacilityIds.length} Selected
                                            </span>
                                        </div>
                                    </div>

                                    {availableFacilities.filter((f) => f.status === 'active').length === 0 ? (
                                        <div style={{ padding: '16px', background: '#f8fafc', borderRadius: '8px', textAlign: 'center', color: '#64748b', fontSize: '0.85rem' }}>
                                            No active facilities found in the system. Add active facilities via Hall Facilities module.
                                        </div>
                                    ) : (
                                        <div className="facilities-checkbox-grid">
                                            {availableFacilities
                                                .filter((fac) => fac.status === 'active')
                                                .map((fac) => {
                                                    const isChecked = selectedFacilityIds.includes(fac.id);
                                                    return (
                                                        <label
                                                            key={fac.id}
                                                            className={`facility-checkbox-card ${isChecked ? 'selected' : ''}`}
                                                        >
                                                            <input
                                                                type="checkbox"
                                                                checked={isChecked}
                                                                onChange={(e) => {
                                                                    if (e.target.checked) {
                                                                        setSelectedFacilityIds((prev) => [...prev, fac.id]);
                                                                    } else {
                                                                        setSelectedFacilityIds((prev) => prev.filter((id) => id !== fac.id));
                                                                    }
                                                                }}
                                                            />
                                                            <div className="facility-checkbox-info" style={{ width: '100%' }}>
                                                                <span className="facility-title">{fac.name}</span>
                                                                {fac.description && <span className="facility-desc">{fac.description}</span>}
                                                            </div>
                                                        </label>
                                                    );
                                                })}
                                        </div>
                                    )}
                                </div>

                                {/* ════ SECTION 3: VENUE PHOTOS & GALLERY ════ */}
                                <div className="admin-form-section" style={{ marginTop: '24px' }}>
                                    <div className="admin-form-section-header">
                                        <h4 className="admin-form-section-title">
                                            3. Venue Photos & Gallery
                                        </h4>
                                        <span className="admin-form-section-desc">
                                            {formMode === 'create'
                                                ? 'Choose photos to stage; they will automatically upload when creating the hall.'
                                                : 'Upload photos directly or manage existing hall images.'}
                                        </span>
                                    </div>

                                    {/* Upload / Add Photo Input Box */}
                                    <div className="gallery-upload-box" style={{ marginTop: '10px' }}>
                                        <h5 style={{ fontSize: '0.88rem', fontWeight: 600, marginBottom: '10px' }}>
                                            {formMode === 'create' ? 'Add Photo to Upload Queue' : 'Upload New Photo'}
                                        </h5>
                                        <div className="gallery-upload-inputs-row" style={{ display: 'flex', flexWrap: 'wrap', gap: '12px', alignItems: 'center' }}>
                                            <div className="gallery-file-picker" style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                                                <input
                                                    type="file"
                                                    ref={fileInputRef}
                                                    accept="image/jpeg,image/png,image/webp"
                                                    onChange={handleImageChange}
                                                    style={{ display: 'none' }}
                                                    id="hall-form-gallery-file-input"
                                                />
                                                <button
                                                    type="button"
                                                    className="admin-secondary-btn"
                                                    onClick={() => fileInputRef.current?.click()}
                                                    style={{ padding: '7px 14px', fontSize: '0.82rem' }}
                                                >
                                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M21 15 v4 a2 2 0 0 1 -2 2 H5 a2 2 0 0 1 -2 -2 v-4" /><polyline points="17 8 12 3 7 8" /><line x1="12" y1="3" x2="12" y2="15" /></svg>
                                                    <span>Choose Photo</span>
                                                </button>
                                                {imageFile && (
                                                    <span className="gallery-chosen-filename" style={{ fontSize: '0.82rem', color: 'var(--text-primary)', maxWidth: '140px', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                                                        {imageFile.name}
                                                    </span>
                                                )}
                                            </div>

                                            <input
                                                type="text"
                                                className="admin-form-input"
                                                placeholder="Alt text / description..."
                                                value={newImageAlt}
                                                onChange={(e) => setNewImageAlt(e.target.value)}
                                                style={{ flex: 1, minWidth: '160px', height: '38px', fontSize: '0.84rem' }}
                                            />

                                            <label style={{ display: 'flex', alignItems: 'center', gap: '6px', fontSize: '0.84rem', cursor: 'pointer', userSelect: 'none' }}>
                                                <input
                                                    type="checkbox"
                                                    checked={newImageIsPrimary}
                                                    onChange={(e) => setNewImageIsPrimary(e.target.checked)}
                                                />
                                                <span>Primary Cover</span>
                                            </label>

                                            {formMode === 'create' ? (
                                                <button
                                                    type="button"
                                                    className="admin-secondary-btn"
                                                    onClick={handleAddPendingPhoto}
                                                    disabled={!imageFile}
                                                    style={{ padding: '7px 14px', fontSize: '0.82rem', borderColor: '#d4a017', color: '#855e09' }}
                                                >
                                                    + Stage Photo
                                                </button>
                                            ) : (
                                                <button
                                                    type="button"
                                                    className="admin-primary-btn"
                                                    onClick={handleUploadPhotoInEdit}
                                                    disabled={!imageFile || isUploadingImage}
                                                    style={{ padding: '7px 14px', fontSize: '0.82rem' }}
                                                >
                                                    {isUploadingImage ? 'Uploading...' : 'Upload Now'}
                                                </button>
                                            )}
                                        </div>

                                        {imagePreview && (
                                            <div style={{ marginTop: '12px', display: 'flex', alignItems: 'center', gap: '12px' }}>
                                                <img src={imagePreview} alt="Selected preview" style={{ height: '70px', width: '90px', objectFit: 'cover', borderRadius: '8px', border: '1px solid #e2e8f0' }} />
                                                <span style={{ fontSize: '0.78rem', color: '#64748b' }}>
                                                    {formMode === 'create'
                                                        ? 'Click "+ Stage Photo" to queue this image or leave selected to upload upon saving.'
                                                        : 'Click "Upload Now" to upload immediately, or click Save Changes below.'}
                                                </span>
                                            </div>
                                        )}
                                    </div>

                                    {/* Gallery Display: Pending Queue (Create) or Uploaded Gallery (Edit) */}
                                    {formMode === 'create' && (
                                        <div style={{ marginTop: '16px' }}>
                                            <h5 style={{ fontSize: '0.88rem', fontWeight: 600, marginBottom: '10px' }}>
                                                Staged Photos ({pendingImages.length})
                                            </h5>
                                            {pendingImages.length > 0 ? (
                                                <div className="gallery-grid-cards">
                                                    {pendingImages.map((img) => (
                                                        <div key={img.id} className="gallery-grid-card">
                                                            <img
                                                                src={img.preview}
                                                                alt={img.altText || 'Pending Hall Photo'}
                                                                className="gallery-grid-img"
                                                            />
                                                            <div className="gallery-grid-info">
                                                                {img.isPrimary && (
                                                                    <span className="primary-pill-badge">Primary</span>
                                                                )}
                                                                <span className="gallery-alt-text">{img.altText || img.file.name}</span>
                                                            </div>
                                                            <button
                                                                type="button"
                                                                className="gallery-delete-btn"
                                                                title="Remove photo from staging"
                                                                onClick={() => handleRemovePendingPhoto(img.id)}
                                                            >
                                                                ✕
                                                            </button>
                                                        </div>
                                                    ))}
                                                </div>
                                            ) : (
                                                <div style={{ padding: '16px', background: '#f8fafc', borderRadius: '8px', textAlign: 'center', color: '#64748b', fontSize: '0.82rem', border: '1px dashed #cbd5e1' }}>
                                                    No photos staged yet. Choose photos above to stage them for upload upon creation.
                                                </div>
                                            )}
                                        </div>
                                    )}

                                    {formMode === 'edit' && editingHall && (
                                        <div style={{ marginTop: '16px' }}>
                                            <h5 style={{ fontSize: '0.88rem', fontWeight: 600, marginBottom: '10px' }}>
                                                Uploaded Photos ({editingHall.images?.length || 0})
                                            </h5>
                                            {editingHall.images && editingHall.images.length > 0 ? (
                                                <div className="gallery-grid-cards">
                                                    {editingHall.images.map((img) => (
                                                        <div key={img.id} className="gallery-grid-card">
                                                            <img
                                                                src={resolveImageUrl(img)}
                                                                alt={img.alt_text || 'Hall Photo'}
                                                                className="gallery-grid-img"
                                                                style={{ cursor: 'pointer' }}
                                                                title="Click to view full image"
                                                                onClick={() => setFullViewImage({
                                                                    url: resolveImageUrl(img),
                                                                    alt: img.alt_text || editingHall.name,
                                                                    title: editingHall.name,
                                                                })}
                                                            />
                                                            <div className="gallery-grid-info">
                                                                {(img.is_primary === 1 || img.is_primary === true) && (
                                                                    <span className="primary-pill-badge">Primary</span>
                                                                )}
                                                                <span className="gallery-alt-text">{img.alt_text || 'No description'}</span>
                                                            </div>
                                                            <button
                                                                type="button"
                                                                className="gallery-delete-btn"
                                                                title="Delete photo"
                                                                onClick={() => handleDeletePhotoInEdit(img.id)}
                                                            >
                                                                ✕
                                                            </button>
                                                        </div>
                                                    ))}
                                                </div>
                                            ) : (
                                                <div style={{ padding: '16px', background: '#f8fafc', borderRadius: '8px', textAlign: 'center', color: '#64748b', fontSize: '0.82rem', border: '1px dashed #cbd5e1' }}>
                                                    No photos uploaded for this hall yet.
                                                </div>
                                            )}
                                        </div>
                                    )}
                                </div>
                            </div>

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
                                    {isSubmitting
                                        ? 'Saving...'
                                        : formMode === 'create'
                                        ? 'Create Hall Venue'
                                        : 'Save Changes'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* ─── VIEW DETAILS MODAL ─── */}
            {isViewModalOpen && viewingHall && (
                <div className="admin-modal-overlay" onClick={() => setIsViewModalOpen(false)}>
                    <div className="admin-modal admin-modal--view" onClick={(e) => e.stopPropagation()} role="dialog" aria-modal="true">
                        <div className="admin-modal__header">
                            <div className="admin-modal__title-wrap">
                                <div className="admin-modal__badge-row">
                                    <span className="room-type-id-pill">ID #{viewingHall.id}</span>
                                    {(() => {
                                        const viewStatusInfo = getHallStatusInfo(viewingHall.status);
                                        return (
                                            <span className={`admin-status-badge ${viewStatusInfo.badgeClass}`}>
                                                <span className="admin-status-dot" />
                                                {viewStatusInfo.label}
                                            </span>
                                        );
                                    })()}
                                </div>
                                <h3 className="admin-modal__title">{viewingHall.name}</h3>
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
                            {/* Interactive Image Gallery Carousel or No Photo Available Placeholder */}
                            {viewingHall.images && viewingHall.images.length > 0 ? (
                                <div className="view-modal-gallery">
                                    <div
                                        className="view-modal-main-img-wrap"
                                        onClick={() => setFullViewImage({
                                            url: resolveImageUrl(viewingHall.images[activeViewImageIndex]),
                                            alt: viewingHall.images[activeViewImageIndex]?.alt_text || viewingHall.name,
                                            title: viewingHall.name
                                        })}
                                        title="Click to view full image"
                                        style={{ cursor: 'pointer' }}
                                    >
                                        <img
                                            src={resolveImageUrl(viewingHall.images[activeViewImageIndex])}
                                            alt={viewingHall.name}
                                            className="view-modal-main-img"
                                        />
                                        {viewingHall.images.length > 1 && (
                                            <div className="gallery-counter-badge">
                                                {activeViewImageIndex + 1} / {viewingHall.images.length}
                                            </div>
                                        )}
                                    </div>

                                    {viewingHall.images.length > 1 && (
                                        <div className="view-modal-thumbs-row">
                                            {viewingHall.images.map((img, idx) => (
                                                <div
                                                    key={img.id || idx}
                                                    className={`view-modal-thumb-box ${activeViewImageIndex === idx ? 'active' : ''}`}
                                                    onClick={() => setActiveViewImageIndex(idx)}
                                                >
                                                    <img src={resolveImageUrl(img)} alt={`Thumbnail ${idx + 1}`} />
                                                </div>
                                            ))}
                                        </div>
                                    )}
                                </div>
                            ) : (
                                <div
                                    className="view-modal-no-photo-box"
                                    style={{
                                        width: '100%',
                                        height: '140px',
                                        background: '#f8fafc',
                                        border: '1.5px dashed #cbd5e1',
                                        borderRadius: '12px',
                                        display: 'flex',
                                        flexDirection: 'column',
                                        alignItems: 'center',
                                        justifyContent: 'center',
                                        gap: '6px',
                                        color: '#64748b',
                                    }}
                                >
                                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" style={{ color: '#94a3b8' }}>
                                        <rect x="3" y="3" width="18" height="18" rx="2" ry="2" />
                                        <circle cx="8.5" cy="8.5" r="1.5" />
                                        <polyline points="21 15 16 10 5 21" />
                                    </svg>
                                    <span style={{ fontSize: '0.88rem', fontWeight: 600, color: '#475569' }}>No photo available</span>
                                    <span style={{ fontSize: '0.76rem', color: '#94a3b8' }}>No photos have been uploaded for this hall venue.</span>
                                </div>
                            )}

                            {/* Details Grid */}
                            <div className="room-type-details-grid" style={{ marginTop: '20px' }}>
                                <div className="room-type-detail-item">
                                    <span className="room-type-detail-label">Hall Type Category</span>
                                    <span className="room-type-detail-val">{getHallTypeName(viewingHall, hallTypes)}</span>
                                </div>

                                <div className="room-type-detail-item">
                                    <span className="room-type-detail-label">Slug / Identifier</span>
                                    <span className="room-type-detail-val code">{viewingHall.slug}</span>
                                </div>

                                <div className="room-type-detail-item">
                                    <span className="room-type-detail-label">Seating Capacity</span>
                                    <span className="room-type-detail-val">
                                        <strong>{Number(viewingHall.capacity).toLocaleString()}</strong> Guests
                                    </span>
                                </div>

                                <div className="room-type-detail-item">
                                    <span className="room-type-detail-label">Floor Area</span>
                                    <span className="room-type-detail-val">
                                        {viewingHall.area
                                            ? (extractNumericArea(viewingHall.area)
                                                ? `${Number(extractNumericArea(viewingHall.area)).toLocaleString()} sq ft`
                                                : viewingHall.area)
                                            : '—'}
                                    </span>
                                </div>

                                <div className="room-type-detail-item full-width">
                                    <span className="room-type-detail-label">Price / Tariff Details</span>
                                    <span className="room-type-detail-val">{viewingHall.price_description || 'On Request'}</span>
                                </div>

                                <div className="room-type-detail-item full-width">
                                    <span className="room-type-detail-label">Description</span>
                                    <p className="room-type-detail-text">
                                        {viewingHall.description || 'No description provided for this hall.'}
                                    </p>
                                </div>

                                <div className="room-type-detail-item full-width">
                                    <span className="room-type-detail-label">Assigned Facilities</span>
                                    <div className="room-amenities-tags" style={{ display: 'flex', flexWrap: 'wrap', gap: '8px', marginTop: '8px' }}>
                                        {viewingHall.facilities && viewingHall.facilities.length > 0 ? (
                                            viewingHall.facilities.map((fac, idx) => (
                                                <span key={fac.id || idx} className="room-amenity-pill">
                                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#d4a017" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round">
                                                        <polyline points="20 6 9 17 4 12" />
                                                    </svg>
                                                    <span>{typeof fac === 'object' ? fac.name : fac}</span>
                                                </span>
                                            ))
                                        ) : (
                                            <span className="text-muted-italic" style={{ fontSize: '0.84rem' }}>No facilities assigned yet.</span>
                                        )}
                                    </div>
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
                                    handleOpenEdit(viewingHall);
                                }}
                            >
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M11 4 H4 a2 2 0 0 0 -2 2 v14 a2 2 0 0 0 2 2 h14 a2 2 0 0 0 2 -2 v-7" /><path d="M18.5 2.5 a2.121 2.121 0 0 1 3 3 L12 15 l-4 1 1 -4 9.5 -9.5 z" /></svg>
                                Edit This Hall
                            </button>
                        </div>
                    </div>
                </div>
            )}

            {/* ─── DELETE CONFIRMATION MODAL ─── */}
            {isDeleteModalOpen && deletingHall && (
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
                                <h3 className="admin-modal__title">Delete Hall Venue</h3>
                                <p className="admin-modal__subtitle">This action cannot be undone.</p>
                            </div>
                        </div>

                        <div className="admin-modal__body">
                            <p className="admin-delete-prompt">
                                Are you sure you want to permanently delete <strong>"{deletingHall.name}"</strong>?
                            </p>
                            <div className="admin-delete-warning-box">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#b45309" strokeWidth="2"><path d="M10.29 3.86 L1.82 18 a2 2 0 0 0 1.71 3 h16.94 a2 2 0 0 0 1.71 -3 L13.71 3.86 a2 2 0 0 0 -3.42 0 z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                                <span>
                                    All associated images and facility assignments for this hall will be permanently removed.
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
                                {isSubmitting ? 'Deleting...' : 'Delete Hall'}
                            </button>
                        </div>
                    </div>
                </div>
            )}

            {/* ─── FULL VIEW / LIGHTBOX MODAL ─── */}
            {fullViewImage && (
                <div className="admin-lightbox-overlay" onClick={() => setFullViewImage(null)} role="dialog" aria-modal="true">
                    <div className="admin-lightbox-content" onClick={(e) => e.stopPropagation()}>
                        <button
                            type="button"
                            className="admin-lightbox-close-btn"
                            onClick={() => setFullViewImage(null)}
                            title="Close image view"
                            aria-label="Close image view"
                        >
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
                                <line x1="18" y1="6" x2="6" y2="18" />
                                <line x1="6" y1="6" x2="18" y2="18" />
                            </svg>
                        </button>
                        <img
                            src={fullViewImage.url}
                            alt={fullViewImage.alt || 'Full View'}
                            className="admin-lightbox-img"
                        />
                        {fullViewImage.title && (
                            <div className="admin-lightbox-caption">
                                <span>{fullViewImage.title}</span>
                                {fullViewImage.alt && fullViewImage.alt !== fullViewImage.title && (
                                    <small style={{ display: 'block', opacity: 0.8, fontSize: '0.8rem', marginTop: '2px' }}>
                                        {fullViewImage.alt}
                                    </small>
                                )}
                            </div>
                        )}
                    </div>
                </div>
            )}
        </div>
    );
};

export default HallsModule;
