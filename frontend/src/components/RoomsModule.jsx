import React, { useState, useMemo, useRef, useEffect, useCallback } from 'react';
import Select from 'react-select';
import Pagination from './Pagination';
import api, { roomApi, roomTypeApi, amenityApi } from '../services/api';
import serviceLodge from '../assets/images/service-lodge.jpg';
import aboutVenue from '../assets/images/about-venue.jpg';
import aboutTeam from '../assets/images/about-team.jpg';

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
 * Common Bed Types
 */
const BED_TYPES = [
    'King Bed',
    'Queen Bed',
    'Double Bed',
    'Twin Beds',
    'Single Bed',
    'Bunk Beds',
];

/**
 * Rooms Admin Module
 */
const RoomsModule = () => {
    const [rooms, setRooms] = useState([]);
    const [roomTypes, setRoomTypes] = useState([]);
    const [availableAmenities, setAvailableAmenities] = useState([]);

    // Filter states
    const [searchQuery, setSearchQuery] = useState('');
    const [selectedTypeFilter, setSelectedTypeFilter] = useState('all');
    const [selectedStatusFilter, setSelectedStatusFilter] = useState('all');

    // Backend Pagination state
    const [currentPage, setCurrentPage] = useState(1);
    const [pageSize, setPageSize] = useState(5);
    const [totalRecords, setTotalRecords] = useState(0);
    const [isLoading, setIsLoading] = useState(false);
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [modalError, setModalError] = useState(null);

    // Modal states
    const [isFormModalOpen, setIsFormModalOpen] = useState(false);
    const [formMode, setFormMode] = useState('create');
    const [editingRoom, setEditingRoom] = useState(null);

    const [isViewModalOpen, setIsViewModalOpen] = useState(false);
    const [viewingRoom, setViewingRoom] = useState(null);
    const [activeViewImageIndex, setActiveViewImageIndex] = useState(0);

    const [isDeleteModalOpen, setIsDeleteModalOpen] = useState(false);
    const [deletingRoom, setDeletingRoom] = useState(null);

    // Toast state
    const [toastMessage, setToastMessage] = useState(null);

    // Form inputs state
    const [formData, setFormData] = useState({
        room_number: '',
        name: '',
        room_type_id: '',
        guest_capacity: 2,
        bed_count: 1,
        bed_type: 'King Bed',
        price_per_night: '',
        status: 'available',
        description: '',
        amenity_ids: [],
        images: [],
    });
    const [formErrors, setFormErrors] = useState({});

    // New Image Upload input state in Form
    const [newImageAlt, setNewImageAlt] = useState('');
    const [newImageIsPrimary, setNewImageIsPrimary] = useState(false);
    const fileInputRef = useRef(null);

    // Toast helper
    const showToast = (msg, type = 'success') => {
        setToastMessage({ text: msg, type });
        setTimeout(() => {
            setToastMessage(null);
        }, 3500);
    };

    // Helper to get room type name
    const getRoomTypeName = (typeId, roomObj = null) => {
        if (roomObj?.room_type?.name) return roomObj.room_type.name;
        if (roomObj?.room_type_name) return roomObj.room_type_name;
        const found = roomTypes.find((rt) => Number(rt.id) === Number(typeId));
        return found ? found.name : 'Unassigned';
    };

    // Helper to resolve an image URL
    const resolveImageUrl = (img) => {
        if (!img) return serviceLodge;
        if (img.url) return img.url;
        if (img.image_path) {
            if (img.image_path.startsWith('http')) return img.image_path;
            return `http://localhost/sm_groups/backend/${img.image_path.replace(/^\/+/, '')}`;
        }
        return serviceLodge;
    };

    // Get primary image of a room
    const getPrimaryImage = (room) => {
        if (!room.images || room.images.length === 0) return serviceLodge;
        const primary = room.images.find((img) => img.is_primary === 1 || img.is_primary === true);
        const target = primary || room.images[0];
        return resolveImageUrl(target);
    };

    // Helper to get amenity names array
    const getAmenityNames = (room) => {
        if (!room.amenities || !Array.isArray(room.amenities)) return [];
        return room.amenities.map((a) => (typeof a === 'object' && a !== null ? a.name : a));
    };

    // Fetch Room Types from backend
    const fetchRoomTypesList = useCallback(async () => {
        try {
            const res = await roomTypeApi.list('limit=100');
            if (res.success && res.data?.status && res.data?.data) {
                const list = Array.isArray(res.data.data) ? res.data.data : res.data.data.room_types || [];
                setRoomTypes(list);
                return list;
            }
        } catch (err) {
            console.warn('Backend room types list note:', err.message);
        }
        return [];
    }, []);

    // Fetch Amenities from backend
    const fetchAmenitiesList = useCallback(async () => {
        try {
            const res = await roomApi.amenityList('limit=100');
            if (res.success && res.data?.status && res.data?.data) {
                const list = Array.isArray(res.data.data)
                    ? res.data.data
                    : res.data.data.amenities || [];
                if (list.length > 0) {
                    setAvailableAmenities(list);
                    return list;
                }
            }
            // Fallback to amenity master endpoint
            const fallbackRes = await amenityApi.list('limit=100');
            if (fallbackRes.success && fallbackRes.data?.status && fallbackRes.data?.data) {
                const list = Array.isArray(fallbackRes.data.data)
                    ? fallbackRes.data.data
                    : fallbackRes.data.data.amenities || [];
                setAvailableAmenities(list);
                return list;
            }
        } catch (err) {
            console.warn('Backend amenities list note:', err.message);
        }
        return [];
    }, []);

    // Load master lists on mount
    useEffect(() => {
        fetchRoomTypesList();
        fetchAmenitiesList();
    }, [fetchRoomTypesList, fetchAmenitiesList]);

    /**
     * Fetch Rooms using backend pagination endpoint (/room/list.php)
     */
    const fetchRooms = useCallback(
        async (
            page = currentPage,
            limit = pageSize,
            search = searchQuery,
            roomTypeId = selectedTypeFilter,
            status = selectedStatusFilter
        ) => {
            setIsLoading(true);
            try {
                const params = new URLSearchParams();
                params.append('page', String(page));
                params.append('limit', String(limit));
                if (search && search.trim()) params.append('search', search.trim());
                if (roomTypeId && roomTypeId !== 'all') params.append('room_type_id', String(roomTypeId));
                if (status && status !== 'all') params.append('status', status);

                const res = await roomApi.list(params.toString());
                if (res.success && res.data?.status && res.data?.data) {
                    const roomList = res.data.data.rooms || (Array.isArray(res.data.data) ? res.data.data : []);
                    setRooms(roomList);
                    if (res.data.data.pagination) {
                        setTotalRecords(res.data.data.pagination.total_records ?? roomList.length);
                    } else {
                        setTotalRecords(roomList.length);
                    }
                } else if (res.data?.data?.rooms) {
                    setRooms(res.data.data.rooms);
                    if (res.data.data.pagination) {
                        setTotalRecords(res.data.data.pagination.total_records ?? res.data.data.rooms.length);
                    }
                }
            } catch (err) {
                console.warn('Backend room fetch note:', err.message);
            } finally {
                setIsLoading(false);
            }
        },
        [currentPage, pageSize, searchQuery, selectedTypeFilter, selectedStatusFilter]
    );

    useEffect(() => {
        fetchRooms(currentPage, pageSize, searchQuery, selectedTypeFilter, selectedStatusFilter);
    }, [fetchRooms, currentPage, pageSize, searchQuery, selectedTypeFilter, selectedStatusFilter]);

    // Summary counts
    const stats = useMemo(() => {
        const total = totalRecords || rooms.length;
        const available = rooms.filter((r) => r.status === 'available').length;
        const maintenance = rooms.filter((r) => r.status === 'maintenance').length;
        const inactive = rooms.filter((r) => r.status === 'inactive').length;
        return { total, available, maintenance, inactive };
    }, [rooms, totalRecords]);

    // Validation matching backend rules
    const validateForm = () => {
        const errors = {};
        const roomNum = (formData.room_number || '').trim();
        const name = (formData.name || '').trim();

        if (!roomNum) {
            errors.room_number = 'Room number is required (e.g. 101, A-201).';
        } else if (roomNum.length > 20) {
            errors.room_number = 'Room number cannot exceed 20 characters.';
        } else {
            const duplicate = rooms.find(
                (r) =>
                    r.room_number &&
                    r.room_number.toLowerCase() === roomNum.toLowerCase() &&
                    (formMode === 'create' || Number(r.id) !== Number(editingRoom?.id))
            );
            if (duplicate) {
                errors.room_number = `Room number "${roomNum}" already exists.`;
            }
        }

        if (!name) {
            errors.name = 'Room name is required.';
        } else if (name.length < 2 || name.length > 100) {
            errors.name = 'Room name must be between 2 and 100 characters.';
        }

        if (!formData.room_type_id) {
            errors.room_type_id = 'Please select an assigned Room Type.';
        }

        const price = Number(formData.price_per_night);
        if (!formData.price_per_night || isNaN(price) || price <= 0) {
            errors.price_per_night = 'Price per night must be a valid positive amount.';
        }

        if (!formData.guest_capacity || Number(formData.guest_capacity) < 1) {
            errors.guest_capacity = 'Guest capacity must be at least 1.';
        }

        if (!formData.bed_count || Number(formData.bed_count) < 1) {
            errors.bed_count = 'Bed count must be at least 1.';
        }

        if (formData.description && formData.description.length > 1000) {
            errors.description = 'Description cannot exceed 1000 characters.';
        }

        setFormErrors(errors);
        return Object.keys(errors).length === 0;
    };

    // Open Add Modal
    const handleOpenAdd = async () => {
        setFormMode('create');
        setEditingRoom(null);
        setModalError(null);

        // Fetch latest room types and amenities dynamically
        const [latestTypes] = await Promise.all([fetchRoomTypesList(), fetchAmenitiesList()]);
        const defaultTypeId = (latestTypes && latestTypes[0]?.id) || (roomTypes[0]?.id) || '';

        setFormData({
            room_number: '',
            name: '',
            room_type_id: defaultTypeId,
            guest_capacity: 2,
            bed_count: 1,
            bed_type: 'King Bed',
            price_per_night: '',
            status: 'available',
            description: '',
            amenity_ids: [],
            images: [],
        });
        setFormErrors({});
        setIsFormModalOpen(true);
    };

    // Open Edit Modal
    const handleOpenEdit = async (room) => {
        setFormMode('edit');
        setEditingRoom(room);
        setModalError(null);

        const [, latestAmenities] = await Promise.all([fetchRoomTypesList(), fetchAmenitiesList()]);
        const pool = (latestAmenities && latestAmenities.length > 0) ? latestAmenities : availableAmenities;

        // Map existing room amenities to numeric IDs
        const existingAmenityIds = (room.amenities || []).map((a) => {
            if (typeof a === 'object' && a !== null) {
                return Number(a.id || a.amenity_id);
            }
            if (typeof a === 'number') {
                return a;
            }
            const match = pool.find((item) => item.name?.toLowerCase() === String(a).toLowerCase());
            return match ? Number(match.id) : null;
        }).filter((id) => id != null && !isNaN(id) && id > 0);

        setFormData({
            room_number: room.room_number || '',
            name: room.name || '',
            room_type_id: room.room_type_id || '',
            guest_capacity: room.guest_capacity ?? 2,
            bed_count: room.bed_count ?? 1,
            bed_type: room.bed_type || 'King Bed',
            price_per_night: room.price_per_night ?? '',
            status: room.status || 'available',
            description: room.description || '',
            amenity_ids: existingAmenityIds,
            images: room.images ? [...room.images] : [],
        });
        setFormErrors({});
        setIsFormModalOpen(true);
    };

    // Open View Modal
    const handleOpenView = (room) => {
        setViewingRoom(room);
        setActiveViewImageIndex(0);
        setIsViewModalOpen(true);
    };

    // Open Delete Room Modal
    const handleOpenDelete = (room) => {
        setDeletingRoom(room);
        setIsDeleteModalOpen(true);
    };

    // Toggle amenity checkbox in form
    const toggleAmenity = (amenityId) => {
        const numericId = Number(amenityId);
        setFormData((prev) => {
            const exists = prev.amenity_ids.includes(numericId);
            return {
                ...prev,
                amenity_ids: exists
                    ? prev.amenity_ids.filter((id) => id !== numericId)
                    : [...prev.amenity_ids, numericId],
            };
        });
    };

    /* =========================================================
       IMAGE UPLOAD & DELETE HANDLERS
       ========================================================= */

    // Handle Local File Selection / Upload
    const handleImageFileChange = (e) => {
        const file = e.target.files[0];
        if (!file) return;

        // Check file size (max 5MB)
        if (file.size > 5 * 1024 * 1024) {
            showToast('Image size cannot exceed 5MB.', 'error');
            return;
        }

        // Check file type (jpg, png, webp)
        const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
        if (!allowedTypes.includes(file.type)) {
            showToast('Only JPG, PNG and WebP images are supported.', 'error');
            return;
        }

        const objectUrl = URL.createObjectURL(file);
        const newImgObj = {
            id: Date.now(),
            url: objectUrl,
            file,
            alt_text: newImageAlt.trim() || file.name,
            is_primary: formData.images.length === 0 || newImageIsPrimary ? 1 : 0,
        };

        setFormData((prev) => {
            let updatedList = [...prev.images];
            if (newImgObj.is_primary === 1) {
                updatedList = updatedList.map((img) => ({ ...img, is_primary: 0 }));
            }
            return {
                ...prev,
                images: [...updatedList, newImgObj],
            };
        });

        // Also if we are actively viewing a room, update it
        if (viewingRoom && formMode === 'edit') {
            setViewingRoom((prev) => ({
                ...prev,
                images: [...prev.images, newImgObj]
            }));
        }

        // Reset upload inputs
        setNewImageAlt('');
        setNewImageIsPrimary(false);
        if (fileInputRef.current) fileInputRef.current.value = '';
        showToast('Image selected and added to room images.');
    };

    // Set an image as primary cover
    const handleSetPrimaryImage = (imgId) => {
        setFormData((prev) => ({
            ...prev,
            images: prev.images.map((img) => ({
                ...img,
                is_primary: img.id === imgId ? 1 : 0,
            })),
        }));

        if (viewingRoom) {
            setViewingRoom((prev) => ({
                ...prev,
                images: prev.images.map((img) => ({
                    ...img,
                    is_primary: img.id === imgId ? 1 : 0,
                })),
            }));
        }

        showToast('Primary cover image updated.');
    };

    // Remove an image from form state
    const handleRemoveImage = (imgId) => {
        setFormData((prev) => {
            const filtered = prev.images.filter((img) => img.id !== imgId);
            if (filtered.length > 0 && !filtered.some((img) => img.is_primary === 1)) {
                filtered[0].is_primary = 1;
            }
            return {
                ...prev,
                images: filtered,
            };
        });

        if (viewingRoom) {
            setViewingRoom((prev) => {
                const filtered = prev.images.filter((img) => img.id !== imgId);
                if (filtered.length > 0 && !filtered.some((img) => img.is_primary === 1)) {
                    filtered[0].is_primary = 1;
                }
                return {
                    ...prev,
                    images: filtered,
                };
            });
            setActiveViewImageIndex(0);
        }

        showToast('Image removed.');
    };

    // Submit Form (Create / Edit)
    const handleFormSubmit = async (e) => {
        e.preventDefault();
        if (!validateForm()) return;

        setIsSubmitting(true);
        setModalError(null);

        const trimmedNumber = formData.room_number.trim();
        const trimmedName = formData.name.trim();

        const payload = {
            room_type_id: Number(formData.room_type_id),
            room_number: trimmedNumber,
            name: trimmedName,
            description: formData.description.trim(),
            bed_count: Number(formData.bed_count),
            guest_capacity: Number(formData.guest_capacity),
            price_per_night: Number(formData.price_per_night),
            status: formData.status,
        };

        try {
            let targetRoomId = null;

            if (formMode === 'create') {
                const res = await roomApi.create(payload);
                if (!res.success) {
                    setModalError(res.error || 'Failed to create room.');
                    setIsSubmitting(false);
                    return;
                }
                targetRoomId = res.data?.data?.room_id || res.data?.data?.id || res.data?.room_id;
                showToast(`Room "${trimmedNumber} - ${trimmedName}" created successfully.`);
            } else {
                const updatePayload = {
                    ...payload,
                    room_id: Number(editingRoom.id),
                };
                const res = await roomApi.update(updatePayload);
                if (!res.success) {
                    setModalError(res.error || 'Failed to update room.');
                    setIsSubmitting(false);
                    return;
                }
                targetRoomId = Number(editingRoom.id);
                showToast(`Room "${trimmedNumber}" updated successfully.`);
            }

            // Update Amenities on backend
            if (targetRoomId && formData.amenity_ids) {
                try {
                    await roomApi.updateAmenities(targetRoomId, formData.amenity_ids);
                } catch (amenityErr) {
                    console.warn('Room amenities update note:', amenityErr);
                }
            }

            // Handle image uploads for newly selected local files
            const newImageFiles = formData.images.filter((img) => img.file);
            for (const img of newImageFiles) {
                if (targetRoomId) {
                    try {
                        await roomApi.uploadImage(
                            targetRoomId,
                            img.file,
                            img.alt_text,
                            img.is_primary === 1
                        );
                    } catch (imgErr) {
                        console.warn('Room image upload note:', imgErr);
                    }
                }
            }

            setIsFormModalOpen(false);
            fetchRooms(currentPage, pageSize, searchQuery, selectedTypeFilter, selectedStatusFilter);
        } catch (err) {
            setModalError(err.message || 'An unexpected error occurred.');
        } finally {
            setIsSubmitting(false);
        }
    };

    // Confirm Delete Room
    const handleConfirmDelete = async () => {
        if (!deletingRoom) return;
        setIsSubmitting(true);
        try {
            const res = await roomApi.delete(deletingRoom.id);
            if (res.success) {
                showToast(`Room "${deletingRoom.room_number}" deleted successfully.`);
                setIsDeleteModalOpen(false);
                setDeletingRoom(null);
                fetchRooms(currentPage, pageSize, searchQuery, selectedTypeFilter, selectedStatusFilter);
            } else {
                showToast(res.error || 'Failed to delete room.', 'error');
            }
        } catch (err) {
            showToast(err.message || 'Error deleting room.', 'error');
        } finally {
            setIsSubmitting(false);
        }
    };

    // Search options for react-select
    const searchSelectOptions = useMemo(() => {
        return rooms.map((r) => ({
            value: r.room_number,
            label: `Room ${r.room_number} — ${r.name} (${getRoomTypeName(r.room_type_id, r)})`,
        }));
    }, [rooms, roomTypes]);

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

    // Room Type filter options for react-select
    const roomTypeFilterOptions = useMemo(() => {
        return [
            { value: 'all', label: 'All Room Types' },
            ...roomTypes.map((rt) => ({
                value: String(rt.id),
                label: rt.name,
            })),
        ];
    }, [roomTypes]);

    return (
        <div className="rooms-module">
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
                    <h2 className="dashboard-page-header__title">Rooms Management</h2>
                    <p className="dashboard-page-header__desc">
                        Manage physical lodge rooms, room photos, assign categories, set pricing, and control occupancy status.
                    </p>
                </div>
                <div className="dashboard-page-header__actions">
                    <button
                        type="button"
                        className="admin-primary-btn"
                        onClick={handleOpenAdd}
                        id="add-room-btn"
                    >
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round">
                            <line x1="12" y1="5" x2="12" y2="19" />
                            <line x1="5" y1="12" x2="19" y2="12" />
                        </svg>
                        <span>Add New Room</span>
                    </button>
                </div>
            </div>

            {/* Stats Overview Grid */}
            <div className="room-types-stats-grid">
                <div className="room-type-stat-card">
                    <div className="room-type-stat-card__icon room-type-stat-card__icon--gold">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                            <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" />
                            <polyline points="9 22 9 12 15 12 15 22" />
                        </svg>
                    </div>
                    <div className="room-type-stat-card__info">
                        <span className="room-type-stat-card__label">Total Rooms</span>
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
                        <span className="room-type-stat-card__label">Available</span>
                        <span className="room-type-stat-card__value">{stats.available}</span>
                    </div>
                </div>

                <div className="room-type-stat-card">
                    <div className="room-type-stat-card__icon room-type-stat-card__icon--gold">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                            <path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z" />
                        </svg>
                    </div>
                    <div className="room-type-stat-card__info">
                        <span className="room-type-stat-card__label">Maintenance</span>
                        <span className="room-type-stat-card__value">{stats.maintenance}</span>
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
                        <span className="room-type-stat-card__label">Inactive</span>
                        <span className="room-type-stat-card__value">{stats.inactive}</span>
                    </div>
                </div>
            </div>

            {/* Filter & Search Toolbar with React Select */}
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
                        placeholder="Search or select room #, name, or type..."
                        isClearable
                        isSearchable
                        styles={reactSelectStyles}
                        className="react-select-container"
                        classNamePrefix="react-select"
                        noOptionsMessage={({ inputValue }) =>
                            inputValue ? `No rooms match "${inputValue}"` : 'No options'
                        }
                    />
                </div>

                <div className="room-types-search-select-wrap" style={{ maxWidth: '240px', minWidth: '180px' }}>
                    <Select
                        options={roomTypeFilterOptions}
                        value={
                            roomTypeFilterOptions.find((opt) => String(opt.value) === String(selectedTypeFilter)) ||
                            roomTypeFilterOptions[0]
                        }
                        onChange={(selected) => {
                            setSelectedTypeFilter(selected ? selected.value : 'all');
                            setCurrentPage(1);
                        }}
                        placeholder="Filter by Room Type"
                        styles={reactSelectStyles}
                        className="react-select-container"
                        classNamePrefix="react-select"
                        isSearchable={false}
                    />
                </div>

                <div className="room-types-filter-group">
                    <button
                        type="button"
                        className={`room-types-filter-pill ${selectedStatusFilter === 'all' ? 'active' : ''}`}
                        onClick={() => { setSelectedStatusFilter('all'); setCurrentPage(1); }}
                    >
                        All ({stats.total})
                    </button>
                    <button
                        type="button"
                        className={`room-types-filter-pill ${selectedStatusFilter === 'available' ? 'active' : ''}`}
                        onClick={() => { setSelectedStatusFilter('available'); setCurrentPage(1); }}
                    >
                        Available ({stats.available})
                    </button>
                    <button
                        type="button"
                        className={`room-types-filter-pill ${selectedStatusFilter === 'maintenance' ? 'active' : ''}`}
                        onClick={() => { setSelectedStatusFilter('maintenance'); setCurrentPage(1); }}
                    >
                        Maintenance ({stats.maintenance})
                    </button>
                    <button
                        type="button"
                        className={`room-types-filter-pill ${selectedStatusFilter === 'inactive' ? 'active' : ''}`}
                        onClick={() => { setSelectedStatusFilter('inactive'); setCurrentPage(1); }}
                    >
                        Inactive ({stats.inactive})
                    </button>
                </div>
            </div>

            {/* Rooms Data Table */}
            <div className="admin-table-card">
                {isLoading ? (
                    <div className="dashboard-loading-state" style={{ padding: '40px', textAlign: 'center', color: 'var(--text-secondary)' }}>
                        <div className="admin-spinner" style={{ width: '32px', height: '32px', border: '3px solid rgba(212, 160, 23, 0.2)', borderTopColor: '#d4a017', borderRadius: '50%', animation: 'spin 0.8s linear infinite', margin: '0 auto 12px' }} />
                        <p style={{ fontSize: '0.9rem', fontWeight: 500 }}>Loading rooms from server...</p>
                    </div>
                ) : rooms.length === 0 ? (
                    <div className="dashboard-empty-state">
                        <div className="dashboard-empty-state__icon-wrap">
                            <svg width="42" height="42" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round">
                                <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" />
                                <polyline points="9 22 9 12 15 12 15 22" />
                            </svg>
                        </div>
                        <h4 className="dashboard-empty-state__title">No rooms found</h4>
                        <p className="dashboard-empty-state__desc">
                            {searchQuery || selectedTypeFilter !== 'all' || selectedStatusFilter !== 'all'
                                ? 'No rooms matched your filter parameters. Try resetting your search.'
                                : 'No rooms created yet. Click below to add your first lodge room.'}
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
                                + Add First Room
                            </button>
                        )}
                    </div>
                ) : (
                    <>
                        <div className="admin-table-responsive">
                            <table className="admin-table">
                                <thead>
                                    <tr>
                                        <th style={{ width: '90px' }}>Room #</th>
                                        <th>Room Details</th>
                                        <th>Room Type</th>
                                        <th>Specs & Beds</th>
                                        <th>Price / Night</th>
                                        <th>Status</th>
                                        <th style={{ textAlign: 'right', width: '130px' }}>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {rooms.map((room) => {
                                        const typeName = getRoomTypeName(room.room_type_id, room);
                                        const coverImage = getPrimaryImage(room);
                                        const amenityNames = getAmenityNames(room);
                                        return (
                                            <tr key={room.id} className="admin-table-row">
                                                <td>
                                                    <span className="room-number-badge">{room.room_number}</span>
                                                </td>
                                                <td>
                                                    <div className="room-table-info-cell">
                                                        <div className="room-table-avatar">
                                                            <img src={coverImage} alt={room.name} />
                                                            {room.images && room.images.length > 1 && (
                                                                <span className="room-avatar-count">+{room.images.length}</span>
                                                            )}
                                                        </div>
                                                        <div className="room-table-text">
                                                            <span className="room-table-name">{room.name}</span>
                                                            <span className="room-table-amenities-snippet">
                                                                {amenityNames.slice(0, 2).join(' · ')}
                                                                {amenityNames.length > 2 && ` +${amenityNames.length - 2} more`}
                                                            </span>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span className="room-type-assigned-pill">{typeName}</span>
                                                </td>
                                                <td>
                                                    <div className="room-specs-cell">
                                                        <span className="room-spec-text">
                                                            👤 {room.guest_capacity} Guests
                                                        </span>
                                                        <span className="room-spec-subtext">
                                                            🛏️ {room.bed_count} {room.bed_type}
                                                        </span>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span className="room-price-val">₹{Number(room.price_per_night).toLocaleString('en-IN')}</span>
                                                </td>
                                                <td>
                                                    <span className={`admin-status-badge admin-status-badge--${room.status}`}>
                                                        <span className="admin-status-dot" />
                                                        {room.status === 'available'
                                                            ? 'Available'
                                                            : room.status === 'maintenance'
                                                            ? 'Maintenance'
                                                            : 'Inactive'}
                                                    </span>
                                                </td>
                                                <td style={{ textAlign: 'right' }}>
                                                    <div className="admin-table-actions">
                                                        <button
                                                            type="button"
                                                            className="admin-action-btn admin-action-btn--view"
                                                            onClick={() => handleOpenView(room)}
                                                            title="View Room Details & Gallery"
                                                            aria-label={`View room ${room.room_number}`}
                                                        >
                                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                                                <path d="M1 12 s4 -8 11 -8 11 8 11 8 -4 8 -11 8 -11 -8 -11 -8 z" />
                                                                <circle cx="12" cy="12" r="3" />
                                                            </svg>
                                                        </button>
                                                        <button
                                                            type="button"
                                                            className="admin-action-btn admin-action-btn--edit"
                                                            onClick={() => handleOpenEdit(room)}
                                                            title="Edit Room & Photos"
                                                            aria-label={`Edit room ${room.room_number}`}
                                                        >
                                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                                                <path d="M11 4 H4 a2 2 0 0 0 -2 2 v14 a2 2 0 0 0 2 2 h14 a2 2 0 0 0 2 -2 v-7" />
                                                                <path d="M18.5 2.5 a2.121 2.121 0 0 1 3 3 L12 15 l-4 1 1 -4 9.5 -9.5 z" />
                                                            </svg>
                                                        </button>
                                                        <button
                                                            type="button"
                                                            className="admin-action-btn admin-action-btn--delete"
                                                            onClick={() => handleOpenDelete(room)}
                                                            title="Delete Room"
                                                            aria-label={`Delete room ${room.room_number}`}
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
                            totalItems={totalRecords}
                            pageSize={pageSize}
                            onPageChange={setCurrentPage}
                            onPageSizeChange={setPageSize}
                        />
                    </>
                )}
            </div>

            {/* ─── ADD / EDIT ROOM MODAL ─── */}
            {isFormModalOpen && (
                <div className="admin-modal-overlay" onClick={() => setIsFormModalOpen(false)}>
                    <div className="admin-modal admin-modal--large" onClick={(e) => e.stopPropagation()} role="dialog" aria-modal="true">
                        <div className="admin-modal__header">
                            <div className="admin-modal__title-wrap">
                                <h3 className="admin-modal__title">
                                    {formMode === 'create' ? 'Add New Room' : `Edit Room ${editingRoom?.room_number}`}
                                </h3>
                                <p className="admin-modal__subtitle">
                                    {formMode === 'create'
                                        ? 'Assign category, upload photos, specify bed setup, pricing, and amenities.'
                                        : `Update room specifications, photo gallery, and availability settings.`}
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
                                {modalError && (
                                    <div className="admin-error-banner" style={{ display: 'flex', alignItems: 'center', gap: '8px', padding: '12px 16px', background: 'rgba(220, 38, 38, 0.08)', border: '1px solid rgba(220, 38, 38, 0.3)', borderRadius: '8px', color: '#dc2626', marginBottom: '18px', fontSize: '0.9rem' }}>
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                                        <span>{modalError}</span>
                                    </div>
                                )}

                                {/* Row 1: Room Number & Room Name */}
                                <div className="admin-form-row-2">
                                <div className="admin-form-group">
                                    <label className="admin-form-label" htmlFor="room-number-input">
                                        Room Number <span className="admin-form-required">*</span>
                                    </label>
                                    <input
                                        id="room-number-input"
                                        type="text"
                                        className={`admin-form-input ${formErrors.room_number ? 'admin-form-input--error' : ''}`}
                                        placeholder="e.g. 101, 204, A-301"
                                        value={formData.room_number}
                                        onChange={(e) => {
                                            setFormData((prev) => ({ ...prev, room_number: e.target.value }));
                                            if (formErrors.room_number) setFormErrors((prev) => ({ ...prev, room_number: null }));
                                        }}
                                        maxLength={20}
                                        autoFocus
                                    />
                                    {formErrors.room_number && (
                                        <span className="admin-form-error">{formErrors.room_number}</span>
                                    )}
                                </div>

                                <div className="admin-form-group">
                                    <label className="admin-form-label" htmlFor="room-name-input">
                                        Room Display Name <span className="admin-form-required">*</span>
                                    </label>
                                    <input
                                        id="room-name-input"
                                        type="text"
                                        className={`admin-form-input ${formErrors.name ? 'admin-form-input--error' : ''}`}
                                        placeholder="e.g. Deluxe King 101"
                                        value={formData.name}
                                        onChange={(e) => {
                                            setFormData((prev) => ({ ...prev, name: e.target.value }));
                                            if (formErrors.name) setFormErrors((prev) => ({ ...prev, name: null }));
                                        }}
                                        maxLength={100}
                                    />
                                    {formErrors.name && (
                                        <span className="admin-form-error">{formErrors.name}</span>
                                    )}
                                </div>
                            </div>

                            {/* Row 2: Room Type Assignment & Status */}
                            <div className="admin-form-row-2">
                                <div className="admin-form-group">
                                    <label className="admin-form-label" htmlFor="room-type-select">
                                        Assigned Room Type <span className="admin-form-required">*</span>
                                    </label>
                                    <select
                                        id="room-type-select"
                                        className={`admin-form-select ${formErrors.room_type_id ? 'admin-form-input--error' : ''}`}
                                        value={formData.room_type_id}
                                        onChange={(e) => {
                                            setFormData((prev) => ({ ...prev, room_type_id: e.target.value }));
                                            if (formErrors.room_type_id) setFormErrors((prev) => ({ ...prev, room_type_id: null }));
                                        }}
                                    >
                                        <option value="">Select a Room Type</option>
                                        {roomTypes.map((rt) => (
                                            <option key={rt.id} value={rt.id}>
                                                {rt.name}
                                            </option>
                                        ))}
                                    </select>
                                    {formErrors.room_type_id && (
                                        <span className="admin-form-error">{formErrors.room_type_id}</span>
                                    )}
                                </div>

                                <div className="admin-form-group">
                                    <label className="admin-form-label" htmlFor="room-status-select">
                                        Room Status <span className="admin-form-required">*</span>
                                    </label>
                                    <select
                                        id="room-status-select"
                                        className="admin-form-select"
                                        value={formData.status}
                                        onChange={(e) => setFormData((prev) => ({ ...prev, status: e.target.value }))}
                                    >
                                        <option value="available">Available (Ready for guest reservation)</option>
                                        <option value="maintenance">Maintenance (Under repair / cleaning)</option>
                                        <option value="inactive">Inactive (Temporarily decommissioned)</option>
                                    </select>
                                </div>
                            </div>

                            {/* Row 3: Capacity, Bed Count & Bed Type */}
                            <div className="admin-form-row-3">
                                <div className="admin-form-group">
                                    <label className="admin-form-label" htmlFor="guest-capacity-input">
                                        Guest Capacity <span className="admin-form-required">*</span>
                                    </label>
                                    <input
                                        id="guest-capacity-input"
                                        type="number"
                                        min={1}
                                        max={30}
                                        className="admin-form-input"
                                        value={formData.guest_capacity}
                                        onChange={(e) => setFormData((prev) => ({ ...prev, guest_capacity: e.target.value }))}
                                    />
                                    {formErrors.guest_capacity && (
                                        <span className="admin-form-error">{formErrors.guest_capacity}</span>
                                    )}
                                </div>

                                <div className="admin-form-group">
                                    <label className="admin-form-label" htmlFor="bed-count-input">
                                        Bed Count <span className="admin-form-required">*</span>
                                    </label>
                                    <input
                                        id="bed-count-input"
                                        type="number"
                                        min={1}
                                        max={15}
                                        className="admin-form-input"
                                        value={formData.bed_count}
                                        onChange={(e) => setFormData((prev) => ({ ...prev, bed_count: e.target.value }))}
                                    />
                                    {formErrors.bed_count && (
                                        <span className="admin-form-error">{formErrors.bed_count}</span>
                                    )}
                                </div>

                                <div className="admin-form-group">
                                    <label className="admin-form-label" htmlFor="bed-type-select">
                                        Bed Type
                                    </label>
                                    <select
                                        id="bed-type-select"
                                        className="admin-form-select"
                                        value={formData.bed_type}
                                        onChange={(e) => setFormData((prev) => ({ ...prev, bed_type: e.target.value }))}
                                    >
                                        {BED_TYPES.map((bt) => (
                                            <option key={bt} value={bt}>{bt}</option>
                                        ))}
                                    </select>
                                </div>
                            </div>

                            {/* Row 4: Price per night */}
                            <div className="admin-form-group">
                                <label className="admin-form-label" htmlFor="price-per-night-input">
                                    Price Per Night (₹ INR) <span className="admin-form-required">*</span>
                                </label>
                                <div className="admin-input-prefix-wrap">
                                    <span className="admin-input-prefix">₹</span>
                                    <input
                                        id="price-per-night-input"
                                        type="number"
                                        min={0}
                                        step="50"
                                        className={`admin-form-input admin-form-input--prefixed ${formErrors.price_per_night ? 'admin-form-input--error' : ''}`}
                                        placeholder="e.g. 1800"
                                        value={formData.price_per_night}
                                        onChange={(e) => {
                                            setFormData((prev) => ({ ...prev, price_per_night: e.target.value }));
                                            if (formErrors.price_per_night) setFormErrors((prev) => ({ ...prev, price_per_night: null }));
                                        }}
                                    />
                                </div>
                                {formErrors.price_per_night && (
                                    <span className="admin-form-error">{formErrors.price_per_night}</span>
                                )}
                            </div>

                            {/* =========================================================
                                ROOM IMAGES MANAGEMENT (Upload & Delete)
                                ========================================================= */}
                            <div className="admin-form-group admin-room-images-section">
                                <div className="admin-form-label-row">
                                    <label className="admin-form-label">
                                        Room Photos ({formData.images.length})
                                    </label>
                                    <span className="admin-char-count">JPG, PNG, WebP (Max 5MB)</span>
                                </div>

                                {/* Upload Controls */}
                                <div className="admin-image-upload-box">
                                    <input
                                        type="file"
                                        accept="image/jpeg, image/jpg, image/png, image/webp"
                                        ref={fileInputRef}
                                        onChange={handleImageFileChange}
                                        style={{ display: 'none' }}
                                        id="room-photo-file-picker"
                                    />
                                    <div className="admin-image-upload-controls">
                                        <button
                                            type="button"
                                            className="admin-image-picker-btn"
                                            onClick={() => fileInputRef.current && fileInputRef.current.click()}
                                        >
                                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                                <rect x="3" y="3" width="18" height="18" rx="2" />
                                                <circle cx="8.5" cy="8.5" r="1.5" />
                                                <polyline points="21 15 16 10 5 21" />
                                            </svg>
                                            <span>Select Photo from Device</span>
                                        </button>
                                        <input
                                            type="text"
                                            placeholder="Optional photo caption / alt text..."
                                            value={newImageAlt}
                                            onChange={(e) => setNewImageAlt(e.target.value)}
                                            className="admin-form-input admin-image-alt-input"
                                            maxLength={255}
                                        />
                                        <label className="admin-image-primary-checkbox">
                                            <input
                                                type="checkbox"
                                                checked={newImageIsPrimary}
                                                onChange={(e) => setNewImageIsPrimary(e.target.checked)}
                                            />
                                            <span>Set as Cover</span>
                                        </label>
                                    </div>
                                </div>

                                {/* Thumbnails Gallery Grid */}
                                {formData.images.length > 0 && (
                                    <div className="admin-room-images-grid">
                                        {formData.images.map((img) => (
                                            <div key={img.id} className={`admin-room-img-card ${img.is_primary === 1 ? 'is-primary' : ''}`}>
                                                <img src={img.url} alt={img.alt_text || 'Room photo'} />
                                                {img.is_primary === 1 && (
                                                    <span className="admin-primary-cover-badge">★ Cover Photo</span>
                                                )}
                                                <div className="admin-room-img-card-actions">
                                                    {img.is_primary !== 1 && (
                                                        <button
                                                            type="button"
                                                            className="admin-img-action-btn admin-img-action-btn--primary"
                                                            onClick={() => handleSetPrimaryImage(img.id)}
                                                            title="Set as Cover Photo"
                                                        >
                                                            Make Cover
                                                        </button>
                                                    )}
                                                    <button
                                                        type="button"
                                                        className="admin-img-action-btn admin-img-action-btn--delete"
                                                        onClick={() => handleRemoveImage(img.id)}
                                                        title="Delete Photo"
                                                        aria-label="Delete photo"
                                                    >
                                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
                                                            <polyline points="3 6 5 6 21 6" />
                                                            <path d="M19 6 v14 a2 2 0 0 1 -2 2 H7 a2 2 0 0 1 -2 -2 V6 m3 0 V4 a2 2 0 0 1 2 -2 h4 a2 2 0 0 1 2 2 v2" />
                                                        </svg>
                                                    </button>
                                                </div>
                                                {img.alt_text && (
                                                    <span className="admin-room-img-caption">{img.alt_text}</span>
                                                )}
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </div>

                            {/* Amenities Checklist */}
                            <div className="admin-form-group">
                                <div className="admin-form-label-row">
                                    <label className="admin-form-label">Room Amenities</label>
                                    <span className="admin-char-count">
                                        {formData.amenity_ids.length} selected
                                    </span>
                                </div>
                                {availableAmenities.length === 0 ? (
                                    <p style={{ margin: '8px 0', fontSize: '0.85rem', color: 'var(--text-secondary)' }}>
                                        No amenities found. You can configure amenities in the Amenities master module.
                                    </p>
                                ) : (
                                    <div className="admin-amenities-selector-grid">
                                        {availableAmenities.map((amenity) => {
                                            const isChecked = formData.amenity_ids.includes(Number(amenity.id));
                                            return (
                                                <label
                                                    key={amenity.id}
                                                    className={`admin-amenity-checkbox-pill ${isChecked ? 'active' : ''}`}
                                                >
                                                    <input
                                                        type="checkbox"
                                                        checked={isChecked}
                                                        onChange={() => toggleAmenity(amenity.id)}
                                                        className="admin-amenity-hidden-check"
                                                    />
                                                    <span className="admin-amenity-check-icon">{isChecked ? '✓' : '+'}</span>
                                                    <span className="admin-amenity-text">{amenity.name}</span>
                                                </label>
                                            );
                                        })}
                                    </div>
                                )}
                            </div>

                            {/* Room Description */}
                            <div className="admin-form-group">
                                <div className="admin-form-label-row">
                                    <label className="admin-form-label" htmlFor="room-description-input">
                                        Room Description & Notes
                                    </label>
                                    <span className="admin-char-count">
                                        {(formData.description || '').length} / 1000
                                    </span>
                                </div>
                                <textarea
                                    id="room-description-input"
                                    rows={3}
                                    className="admin-form-textarea"
                                    placeholder="Enter specific room view, bathroom features, or floor location..."
                                    value={formData.description}
                                    onChange={(e) => setFormData((prev) => ({ ...prev, description: e.target.value }))}
                                    maxLength={1000}
                                />
                            </div>
                            </div>

                            {/* Modal Footer */}
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
                                        ? 'Create Room'
                                        : 'Save Changes'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* ─── VIEW ROOM DETAILS & GALLERY MODAL ─── */}
            {isViewModalOpen && viewingRoom && (
                <div className="admin-modal-overlay" onClick={() => setIsViewModalOpen(false)}>
                    <div className="admin-modal admin-modal--view" onClick={(e) => e.stopPropagation()} role="dialog" aria-modal="true">
                        <div className="admin-modal__header">
                            <div className="admin-modal__title-wrap">
                                <div className="admin-modal__badge-row">
                                    <span className="room-number-badge">Room {viewingRoom.room_number}</span>
                                    <span className="room-type-assigned-pill">{getRoomTypeName(viewingRoom.room_type_id)}</span>
                                    <span className={`admin-status-badge admin-status-badge--${viewingRoom.status}`}>
                                        <span className="admin-status-dot" />
                                        {viewingRoom.status === 'available'
                                            ? 'Available'
                                            : viewingRoom.status === 'maintenance'
                                            ? 'Maintenance'
                                            : 'Inactive'}
                                    </span>
                                </div>
                                <h3 className="admin-modal__title">{viewingRoom.name}</h3>
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
                            {/* Room Image Hero Viewer */}
                            {viewingRoom.images && viewingRoom.images.length > 0 ? (
                                <div className="room-view-gallery-wrap">
                                    <div className="room-view-modal-hero">
                                        <img
                                            src={resolveImageUrl(viewingRoom.images[activeViewImageIndex])}
                                            alt={viewingRoom.images[activeViewImageIndex]?.alt_text || viewingRoom.name}
                                        />
                                        <div className="room-view-modal-price-tag">
                                            ₹{Number(viewingRoom.price_per_night).toLocaleString('en-IN')} <span className="text-muted">/ night</span>
                                        </div>
                                        {(viewingRoom.images[activeViewImageIndex]?.is_primary === 1 || viewingRoom.images[activeViewImageIndex]?.is_primary === true) && (
                                            <span className="room-view-cover-badge">★ Primary Cover</span>
                                        )}
                                    </div>

                                    {/* Thumbnail Switcher & Delete Option */}
                                    <div className="room-view-thumbnails-row">
                                        {viewingRoom.images.map((img, idx) => (
                                            <div
                                                key={img.id || idx}
                                                className={`room-view-thumb-item ${activeViewImageIndex === idx ? 'active' : ''}`}
                                                onClick={() => setActiveViewImageIndex(idx)}
                                            >
                                                <img src={resolveImageUrl(img)} alt={img.alt_text || `Photo ${idx + 1}`} />
                                                <button
                                                    type="button"
                                                    className="room-view-thumb-delete-btn"
                                                    title="Delete this photo"
                                                    onClick={(e) => {
                                                        e.stopPropagation();
                                                        handleRemoveImage(img.id);
                                                    }}
                                                >
                                                    ✕
                                                </button>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            ) : (
                                <div className="room-view-modal-hero">
                                    <img src={serviceLodge} alt={viewingRoom.name} />
                                    <div className="room-view-modal-price-tag">
                                        ₹{Number(viewingRoom.price_per_night).toLocaleString('en-IN')} <span className="text-muted">/ night</span>
                                    </div>
                                </div>
                            )}

                            {/* Details Grid */}
                            <div className="room-type-details-grid">
                                <div className="room-type-detail-item">
                                    <span className="room-type-detail-label">Guest Capacity</span>
                                    <span className="room-type-detail-val">👤 {viewingRoom.guest_capacity} Guests</span>
                                </div>
                                <div className="room-type-detail-item">
                                    <span className="room-type-detail-label">Bed Setup</span>
                                    <span className="room-type-detail-val">🛏️ {viewingRoom.bed_count} {viewingRoom.bed_type}</span>
                                </div>
                                <div className="room-type-detail-item full-width">
                                    <span className="room-type-detail-label">Room Amenities</span>
                                    <div className="room-view-amenities-tags">
                                        {getAmenityNames(viewingRoom).length > 0 ? (
                                            getAmenityNames(viewingRoom).map((amenity, idx) => (
                                                <span key={idx} className="room-amenity-tag">
                                                    ✓ {amenity}
                                                </span>
                                            ))
                                        ) : (
                                            <span className="text-muted-italic">No amenities specified</span>
                                        )}
                                    </div>
                                </div>
                                <div className="room-type-detail-item full-width">
                                    <span className="room-type-detail-label">Description</span>
                                    <p className="room-type-detail-text">
                                        {viewingRoom.description || 'No description entered for this room.'}
                                    </p>
                                </div>
                                <div className="room-type-detail-item">
                                    <span className="room-type-detail-label">Created At</span>
                                    <span className="room-type-detail-val text-muted">{viewingRoom.created_at || '—'}</span>
                                </div>
                                <div className="room-type-detail-item">
                                    <span className="room-type-detail-label">Last Updated</span>
                                    <span className="room-type-detail-val text-muted">{viewingRoom.updated_at || '—'}</span>
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
                                    handleOpenEdit(viewingRoom);
                                }}
                            >
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M11 4 H4 a2 2 0 0 0 -2 2 v14 a2 2 0 0 0 2 2 h14 a2 2 0 0 0 2 -2 v-7" /><path d="M18.5 2.5 a2.121 2.121 0 0 1 3 3 L12 15 l-4 1 1 -4 9.5 -9.5 z" /></svg>
                                Edit Room & Photos
                            </button>
                        </div>
                    </div>
                </div>
            )}

            {/* ─── DELETE ROOM CONFIRMATION MODAL ─── */}
            {isDeleteModalOpen && deletingRoom && (
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
                                <h3 className="admin-modal__title">Delete Room {deletingRoom.room_number}</h3>
                                <p className="admin-modal__subtitle">Permanent deletion from system.</p>
                            </div>
                        </div>

                        <div className="admin-modal__body">
                            <p className="admin-delete-prompt">
                                Are you sure you want to permanently delete room <strong>"{deletingRoom.room_number} - {deletingRoom.name}"</strong>?
                            </p>
                            <p className="admin-delete-warning-text">
                                This will remove room specs, pricing, and all associated photo records.
                            </p>
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
                                Delete Room
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
};

export default RoomsModule;
