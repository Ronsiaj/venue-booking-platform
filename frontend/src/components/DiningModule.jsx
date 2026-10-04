import React, { useState, useMemo, useEffect, useCallback } from 'react';
import Select from 'react-select';
import Pagination from './Pagination';
import api, { diningApi } from '../services/api';
import { normalizeLead } from '../services/Utils';

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

// Allowed meal types from backend
const MEAL_TYPES = [
    { value: 'breakfast', label: 'Breakfast' },
    { value: 'lunch', label: 'Lunch' },
    { value: 'dinner', label: 'Dinner' },
    { value: 'combo', label: 'Combo Meal' },
    { value: 'other', label: 'Other Special' },
];

// Backend regex for dining package name
const PACKAGE_NAME_REGEX = /^[A-Za-z0-9&().,\/-]+(?: [A-Za-z0-9&().,\/-]+)*$/;

/**
 * Dining Admin Module
 * Manages Dining Packages and Restaurant Inquiries/Leads with complete backend integration.
 */
const DiningModule = ({ onNavigate, initialTab }) => {
    // Active Module Tab
    const [activeTab, setActiveTab] = useState(() => {
        if (initialTab === 'leads' || initialTab === 'packages') return initialTab;
        const params = new URLSearchParams(window.location.search);
        return params.get('tab') === 'leads' ? 'leads' : 'packages';
    });

    useEffect(() => {
        if (initialTab && (initialTab === 'leads' || initialTab === 'packages')) {
            setActiveTab(initialTab);
        } else {
            const params = new URLSearchParams(window.location.search);
            const tab = params.get('tab');
            if (tab === 'leads' || tab === 'packages') {
                setActiveTab(tab);
            }
        }
    }, [initialTab]);

    // Packages State
    const [packages, setPackages] = useState([]);
    const [packageSearch, setPackageSearch] = useState('');
    const [packageMealType, setPackageMealType] = useState('all');
    const [packageStatus, setPackageStatus] = useState('all'); // 'all' | 'active' | 'inactive'
    const [packagePage, setPackagePage] = useState(1);
    const [packagePageSize, setPackagePageSize] = useState(10);
    const [packageTotalRecords, setPackageTotalRecords] = useState(0);
    const [isPackagesLoading, setIsPackagesLoading] = useState(false);

    // Leads / Inquiries State
    const [leads, setLeads] = useState([]);
    const [leadSearch, setLeadSearch] = useState('');
    const [leadStatus, setLeadStatus] = useState('all');
    const [leadMealType, setLeadMealType] = useState('all');
    const [leadPage, setLeadPage] = useState(1);
    const [leadPageSize, setLeadPageSize] = useState(10);
    const [leadTotalRecords, setLeadTotalRecords] = useState(0);
    const [isLeadsLoading, setIsLeadsLoading] = useState(false);

    // Food Catalog Items (for package item builder)
    const [foodItems, setFoodItems] = useState([]);
    const [isFoodItemsLoading, setIsFoodItemsLoading] = useState(false);

    // Package Modals
    const [isPackageModalOpen, setIsPackageModalOpen] = useState(false);
    const [packageFormMode, setPackageFormMode] = useState('create'); // 'create' | 'edit'
    const [editingPackage, setEditingPackage] = useState(null);
    const [isViewPackageModalOpen, setIsViewPackageModalOpen] = useState(false);
    const [viewingPackage, setViewingPackage] = useState(null);
    const [isDeletePackageModalOpen, setIsDeletePackageModalOpen] = useState(false);
    const [deletingPackage, setDeletingPackage] = useState(null);

    // Full View Lightbox Image Modal
    const [fullViewImage, setFullViewImage] = useState(null);

    // Image URL Resolver
    const resolveImageUrl = (imagePath) => {
        if (!imagePath) return null;
        if (imagePath.startsWith('http://') || imagePath.startsWith('https://') || imagePath.startsWith('blob:') || imagePath.startsWith('data:')) {
            return imagePath;
        }
        const cleanPath = imagePath.startsWith('/') ? imagePath.slice(1) : imagePath;
        return `http://localhost/sm_groups/backend/${cleanPath}`;
    };

    // Package Form Fields
    const [packageFormData, setPackageFormData] = useState({
        name: '',
        description: '',
        meal_type: 'lunch',
        status: 'active',
        imageFile: null,
        imagePreview: null,
        items: [], // [{ food_item_id, quantity, name, category_name }]
    });
    const [packageFormErrors, setPackageFormErrors] = useState({});

    // Lead Modals & Status Form
    const [viewingLead, setViewingLead] = useState(null);
    const [isViewLeadModalOpen, setIsViewLeadModalOpen] = useState(false);
    const [updatingLeadStatus, setUpdatingLeadStatus] = useState({ id: null, status: 'new', notes: '' });
    const [isUpdateStatusModalOpen, setIsUpdateStatusModalOpen] = useState(false);

    const [isSubmitting, setIsSubmitting] = useState(false);
    const [toastMessage, setToastMessage] = useState(null);

    const showToast = (msg, type = 'success') => {
        setToastMessage({ text: msg, type });
        setTimeout(() => {
            setToastMessage(null);
        }, 3500);
    };

    /**
     * Fetch Food Catalog Items
     */
    const fetchFoodItems = useCallback(async () => {
        setIsFoodItemsLoading(true);
        try {
            const res = await diningApi.listFoodItems('limit=100&status=active');
            if (res.success && res.data?.data) {
                const list = res.data.data.food_items || (Array.isArray(res.data.data) ? res.data.data : []);
                setFoodItems(list);
                return list;
            }
        } catch (err) {
            console.warn('Food items fetch note:', err.message);
        } finally {
            setIsFoodItemsLoading(false);
        }
        return [];
    }, []);

    useEffect(() => {
        fetchFoodItems();
    }, [fetchFoodItems]);

    /**
     * Fetch Dining Packages with server-side pagination
     */
    const fetchPackages = useCallback(async (
        page = packagePage,
        limit = packagePageSize,
        search = packageSearch,
        mealType = packageMealType,
        status = packageStatus
    ) => {
        setIsPackagesLoading(true);
        try {
            const params = new URLSearchParams();
            params.append('page', String(page));
            params.append('limit', String(limit));
            if (search && search.trim()) params.append('search', search.trim());
            if (mealType && mealType !== 'all') params.append('meal_type', mealType);
            if (status && status !== 'all') params.append('status', status);

            const res = await diningApi.listPackages(params.toString());
            if (res.success && res.data?.status && Array.isArray(res.data.data)) {
                setPackages(res.data.data);
                if (res.data.pagination) {
                    setPackageTotalRecords(res.data.pagination.total_records ?? res.data.data.length);
                }
            } else if (res.data?.data && Array.isArray(res.data.data)) {
                setPackages(res.data.data);
                if (res.data.pagination) {
                    setPackageTotalRecords(res.data.pagination.total_records ?? res.data.data.length);
                }
            } else {
                setPackages([]);
                setPackageTotalRecords(0);
            }
        } catch (err) {
            console.warn('Dining packages fetch note:', err.message);
            showToast('Failed to load dining packages.', 'error');
        } finally {
            setIsPackagesLoading(false);
        }
    }, [packagePage, packagePageSize, packageSearch, packageMealType, packageStatus]);

    useEffect(() => {
        if (activeTab === 'packages') {
            fetchPackages(packagePage, packagePageSize, packageSearch, packageMealType, packageStatus);
        }
    }, [fetchPackages, activeTab, packagePage, packagePageSize, packageSearch, packageMealType, packageStatus]);

    /**
     * Fetch Dining Leads / Inquiries with server-side pagination
     */
    const fetchLeads = useCallback(async (
        page = leadPage,
        limit = leadPageSize,
        search = leadSearch,
        status = leadStatus,
        mealType = leadMealType
    ) => {
        setIsLeadsLoading(true);
        try {
            const params = new URLSearchParams();
            params.append('page', String(page));
            params.append('limit', String(limit));
            if (search && search.trim()) params.append('search', search.trim());
            if (status && status !== 'all') params.append('status', status);
            if (mealType && mealType !== 'all') params.append('meal_type', mealType);

            const res = await diningApi.listLeads(params.toString());
            if (res.success && res.data?.status && Array.isArray(res.data.data)) {
                setLeads(res.data.data);
                if (res.data.pagination) {
                    setLeadTotalRecords(res.data.pagination.total_records ?? res.data.data.length);
                }
            } else if (res.data?.data && Array.isArray(res.data.data)) {
                setLeads(res.data.data);
                if (res.data.pagination) {
                    setLeadTotalRecords(res.data.pagination.total_records ?? res.data.data.length);
                }
            } else {
                setLeads([]);
                setLeadTotalRecords(0);
            }
        } catch (err) {
            console.warn('Dining leads fetch note:', err.message);
            showToast('Failed to load dining inquiries.', 'error');
        } finally {
            setIsLeadsLoading(false);
        }
    }, [leadPage, leadPageSize, leadSearch, leadStatus, leadMealType]);

    useEffect(() => {
        if (activeTab === 'leads') {
            fetchLeads(leadPage, leadPageSize, leadSearch, leadStatus, leadMealType);
        }
    }, [fetchLeads, activeTab, leadPage, leadPageSize, leadSearch, leadStatus, leadMealType]);

    // Packages Stats
    const packageStats = useMemo(() => {
        const total = packageTotalRecords || packages.length;
        const active = packages.filter((p) => p.status === 'active').length;
        const inactive = packages.filter((p) => p.status === 'inactive').length;
        const lunchCount = packages.filter((p) => p.meal_type === 'lunch').length;
        const dinnerCount = packages.filter((p) => p.meal_type === 'dinner').length;
        return { total, active, inactive, lunchCount, dinnerCount };
    }, [packages, packageTotalRecords]);

    /**
     * Resolve actual food item name from nested API data or food catalog
     */
    const resolveFoodItemName = useCallback((it, catalogList = foodItems) => {
        if (!it) return 'Dish';
        // 1. Check nested food_item object from backend (package/view.php & package/list.php)
        if (it.food_item?.name) return it.food_item.name;
        if (it.food_item_name) return it.food_item_name;
        if (it.name) return it.name;

        // 2. Lookup in food items catalog by ID
        const foodId = Number(it.food_item_id || it.id);
        if (foodId && Array.isArray(catalogList)) {
            const matched = catalogList.find((f) => Number(f.id) === foodId);
            if (matched?.name) return matched.name;
        }

        return `Item #${it.food_item_id || '—'}`;
    }, [foodItems]);

    /**
     * Resolve food category name
     */
    const resolveFoodItemCategory = useCallback((it, catalogList = foodItems) => {
        if (!it) return '';
        if (it.category?.name) return it.category.name;
        if (it.category_name) return it.category_name;
        if (it.food_category_name) return it.food_category_name;

        const foodId = Number(it.food_item_id || it.id);
        if (foodId && Array.isArray(catalogList)) {
            const matched = catalogList.find((f) => Number(f.id) === foodId);
            if (matched?.food_category_name) return matched.food_category_name;
        }

        return '';
    }, [foodItems]);

    /**
     * Resolve food dietary type (veg / non-veg / vegan / etc.)
     */
    const resolveFoodItemType = useCallback((it, catalogList = foodItems) => {
        if (!it) return '';
        if (it.food_item?.food_type) return it.food_item.food_type;
        if (it.food_type) return it.food_type;

        const foodId = Number(it.food_item_id || it.id);
        if (foodId && Array.isArray(catalogList)) {
            const matched = catalogList.find((f) => Number(f.id) === foodId);
            if (matched?.food_type) return matched.food_type;
        }

        return '';
    }, [foodItems]);

    /**
     * Normalize Dining Lead data from list.php or view.php
     */
    const getDiningLeadInfo = useCallback((lead) => {
        if (!lead) return {};

        const norm = normalizeLead(lead);

        return {
            id: norm.id,
            leadReference: norm.leadReference || (norm.id ? `DL-${norm.id}` : ''),
            name: norm.customerName || 'Guest',
            phone: norm.customerPhone || '',
            email: norm.customerEmail || '',
            diningDate: norm.eventDate || '',
            diningTime: norm.eventTime || '',
            mealType: norm.mealType || '',
            packageName: norm.packageName || '',
            guests: norm.guestCount || 1,
            status: norm.status || 'new',
            notes: norm.otherRequirements || norm.requirement || '',
            createdAt: norm.createdAt || '',
            estimatedAmount: norm.estimatedAmount,
            convertedAmount: norm.convertedAmount,
            assignedAdmin: norm.assignedAdmin,
            packageItems: norm.packageItems || [],
            customFoodItems: norm.customFoodItems || [],
            raw: lead,
        };
    }, []);

    // Lead Stats
    const leadStats = useMemo(() => {
        const total = leadTotalRecords || leads.length;
        const newLeads = leads.filter((l) => {
            const st = normalizeLead(l).status;
            return st === 'new';
        }).length;
        const converted = leads.filter((l) => {
            const st = normalizeLead(l).status;
            return st === 'converted';
        }).length;
        const followUp = leads.filter((l) => {
            const st = normalizeLead(l).status;
            return st === 'follow_up' || st === 'contacted';
        }).length;
        return { total, newLeads, converted, followUp };
    }, [leads, leadTotalRecords]);

    // Open View Lead Modal with full details
    const handleOpenViewLead = async (lead) => {
        setViewingLead(lead);
        setIsViewLeadModalOpen(true);
        try {
            const res = await diningApi.viewLead(lead.id);
            if (res.success && res.data?.status && res.data?.data) {
                setViewingLead(res.data.data);
            } else if (res.data?.data) {
                setViewingLead(res.data.data);
            }
        } catch (err) {
            console.warn('Dining lead detail fetch note:', err.message);
        }
    };

    // Validation for Package Form
    const validatePackageForm = () => {
        const errors = {};
        const trimmedName = packageFormData.name.trim();

        if (!trimmedName) {
            errors.name = 'Package name is required.';
        } else if (trimmedName.length < 2) {
            errors.name = 'Package name must be at least 2 characters.';
        } else if (trimmedName.length > 150) {
            errors.name = 'Package name cannot exceed 150 characters.';
        } else if (!PACKAGE_NAME_REGEX.test(trimmedName)) {
            errors.name = 'Only letters, numbers, spaces, and & ( ) . , / - are allowed.';
        }

        if (!packageFormData.meal_type) {
            errors.meal_type = 'Please select a meal type.';
        }

        if (packageFormData.description && packageFormData.description.length > 1000) {
            errors.description = 'Description cannot exceed 1000 characters.';
        }

        setPackageFormErrors(errors);
        return Object.keys(errors).length === 0;
    };

    // Open Add Package Modal
    const handleOpenAddPackage = () => {
        setPackageFormMode('create');
        setEditingPackage(null);
        fetchFoodItems();
        setPackageFormData({
            name: '',
            description: '',
            meal_type: 'lunch',
            status: 'active',
            imageFile: null,
            imagePreview: null,
            items: [],
        });
        setPackageFormErrors({});
        setIsPackageModalOpen(true);
    };

    // Open Edit Package Modal
    const handleOpenEditPackage = (pkg) => {
        setPackageFormMode('edit');
        setEditingPackage(pkg);
        fetchFoodItems();
        setPackageFormData({
            name: pkg.name || '',
            description: pkg.description || '',
            meal_type: pkg.meal_type || 'lunch',
            status: pkg.status || 'active',
            imageFile: null,
            imagePreview: pkg.image_url || pkg.image_path ? resolveImageUrl(pkg.image_url || pkg.image_path) : null,
            items: Array.isArray(pkg.items)
                ? pkg.items.map((it) => ({
                    food_item_id: Number(it.food_item_id || it.id),
                    quantity: Number(it.quantity) || 1,
                    name: resolveFoodItemName(it, foodItems),
                    category_name: resolveFoodItemCategory(it, foodItems),
                    food_type: resolveFoodItemType(it, foodItems),
                }))
                : [],
        });
        setPackageFormErrors({});
        setIsPackageModalOpen(true);
    };

    // Open View Package Modal with full details
    const handleOpenViewPackage = async (pkg) => {
        setViewingPackage(pkg);
        setIsViewPackageModalOpen(true);
        if (!foodItems || foodItems.length === 0) {
            fetchFoodItems();
        }
        try {
            const res = await diningApi.viewPackage(pkg.id);
            if (res.success && res.data?.status && res.data?.data) {
                setViewingPackage((prev) => ({ ...prev, ...res.data.data }));
            } else if (res.data?.data) {
                setViewingPackage((prev) => ({ ...prev, ...res.data.data }));
            }
        } catch (err) {
            console.warn('Dining package detail fetch note:', err.message);
        }
    };

    // Package Form Submit (Create / Edit)
    const handlePackageFormSubmit = async (e) => {
        e.preventDefault();
        if (!validatePackageForm()) return;

        setIsSubmitting(true);
        try {
            const formData = new FormData();
            formData.append('name', packageFormData.name.trim());
            formData.append('meal_type', packageFormData.meal_type);
            formData.append('status', packageFormData.status);
            if (packageFormData.description.trim()) {
                formData.append('description', packageFormData.description.trim());
            }

            // Items formatted as backend expected JSON string: [{"food_item_id": 1, "quantity": 1}]
            const itemsPayload = packageFormData.items.map((it) => ({
                food_item_id: Number(it.food_item_id),
                quantity: Number(it.quantity) || 1,
            }));
            formData.append('items', JSON.stringify(itemsPayload));

            // Image attachment
            if (packageFormData.imageFile) {
                formData.append('image', packageFormData.imageFile);
            }

            if (packageFormMode === 'create') {
                const res = await diningApi.createPackage(formData);
                if (res.success && res.data?.status) {
                    showToast('Dining package created successfully!', 'success');
                    setIsPackageModalOpen(false);
                    fetchPackages(1, packagePageSize, packageSearch, packageMealType, packageStatus);
                } else {
                    setPackageFormErrors({ submit: res.error || res.data?.message || 'Failed to create dining package.' });
                }
            } else {
                formData.append('package_id', String(editingPackage.id));
                const res = await diningApi.updatePackage(formData);
                if (res.success && res.data?.status) {
                    showToast('Dining package updated successfully!', 'success');
                    setIsPackageModalOpen(false);
                    fetchPackages(packagePage, packagePageSize, packageSearch, packageMealType, packageStatus);
                } else {
                    setPackageFormErrors({ submit: res.error || res.data?.message || 'Failed to update dining package.' });
                }
            }
        } catch (err) {
            setPackageFormErrors({ submit: err.message || 'An unexpected error occurred.' });
        } finally {
            setIsSubmitting(false);
        }
    };

    // Quick Status Toggle for Dining Package
    const handleTogglePackageStatus = async (pkg) => {
        const newStatus = pkg.status === 'active' ? 'inactive' : 'active';
        try {
            const formData = new FormData();
            formData.append('package_id', String(pkg.id));
            formData.append('name', pkg.name);
            formData.append('meal_type', pkg.meal_type);
            formData.append('status', newStatus);

            const res = await diningApi.updatePackage(formData);
            if (res.success && res.data?.status) {
                showToast(`Package status changed to ${newStatus}.`, 'success');
                fetchPackages(packagePage, packagePageSize, packageSearch, packageMealType, packageStatus);
            } else {
                showToast(res.error || res.data?.message || 'Could not update status.', 'error');
            }
        } catch (err) {
            showToast(err.message || 'Failed to toggle status.', 'error');
        }
    };

    // Confirm Delete Dining Package
    const handleConfirmDeletePackage = async () => {
        if (!deletingPackage) return;
        setIsSubmitting(true);
        try {
            const res = await diningApi.deletePackage(deletingPackage.id);
            if (res.success && res.data?.status) {
                showToast('Dining package deleted successfully.', 'success');
                setIsDeletePackageModalOpen(false);
                setDeletingPackage(null);
                fetchPackages(packagePage, packagePageSize, packageSearch, packageMealType, packageStatus);
            } else {
                showToast(res.error || res.data?.message || 'Could not delete dining package.', 'error');
            }
        } catch (err) {
            showToast(err.message || 'Failed to delete package.', 'error');
        } finally {
            setIsSubmitting(false);
        }
    };

    // Add item to package builder
    const handleAddItemToPackage = (selectedOption) => {
        if (!selectedOption) return;
        const foodId = Number(selectedOption.value);
        if (packageFormData.items.some((it) => it.food_item_id === foodId)) {
            showToast('Item is already added to this package.', 'error');
            return;
        }

        const foodItem = foodItems.find((f) => Number(f.id) === foodId);
        const newItem = {
            food_item_id: foodId,
            quantity: 1,
            name: foodItem?.name || selectedOption.label,
            category_name: foodItem?.food_category_name || '',
            food_type: foodItem?.food_type || '',
        };

        setPackageFormData((prev) => ({
            ...prev,
            items: [...prev.items, newItem],
        }));
    };

    // Update item quantity
    const handleUpdateItemQuantity = (foodId, qty) => {
        const numQty = Math.max(1, Number(qty) || 1);
        setPackageFormData((prev) => ({
            ...prev,
            items: prev.items.map((it) => (it.food_item_id === foodId ? { ...it, quantity: numQty } : it)),
        }));
    };

    // Remove item from package
    const handleRemoveItemFromPackage = (foodId) => {
        setPackageFormData((prev) => ({
            ...prev,
            items: prev.items.filter((it) => it.food_item_id !== foodId),
        }));
    };

    // Update Lead Status Submit
    const handleUpdateLeadStatusSubmit = async (e) => {
        e.preventDefault();
        if (!updatingLeadStatus.id) return;

        setIsSubmitting(true);
        try {
            const res = await diningApi.updateLeadStatus(
                updatingLeadStatus.id,
                updatingLeadStatus.status,
                updatingLeadStatus.notes
            );
            if (res.success && res.data?.status) {
                showToast(`Inquiry marked as ${updatingLeadStatus.status.replace('_', ' ')}.`, 'success');
                setIsUpdateStatusModalOpen(false);
                fetchLeads(leadPage, leadPageSize, leadSearch, leadStatus, leadMealType);
            } else {
                showToast(res.error || res.data?.message || 'Could not update inquiry status.', 'error');
            }
        } catch (err) {
            showToast(err.message || 'Failed to update inquiry status.', 'error');
        } finally {
            setIsSubmitting(false);
        }
    };

    // React Select search options for packages
    const packageSearchOptions = useMemo(() => {
        return packages.map((p) => ({
            value: p.name,
            label: `${p.name} (${p.meal_type})`,
        }));
    }, [packages]);

    const currentPackageSelectValue = useMemo(() => {
        if (!packageSearch) return null;
        return packageSearchOptions.find((opt) => opt.value === packageSearch) || {
            value: packageSearch,
            label: packageSearch,
        };
    }, [packageSearch, packageSearchOptions]);

    // Food item select options for package item builder
    const foodItemSelectOptions = useMemo(() => {
        return foodItems.map((f) => ({
            value: f.id,
            label: `${f.name} ${f.food_type ? `[${f.food_type.toUpperCase()}]` : ''} — ${f.food_category_name || 'Item'}`,
        }));
    }, [foodItems]);

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
                    <h2 className="dashboard-page-header__title">Dining & Restaurant Management</h2>
                    <p className="dashboard-page-header__desc">
                        Manage dining meal packages, buffet curations, menu item compositions, and restaurant reservation inquiries.
                    </p>
                </div>
                <div className="dashboard-page-header__actions">
                    {activeTab === 'packages' && (
                        <button
                            type="button"
                            className="admin-primary-btn"
                            onClick={handleOpenAddPackage}
                            id="add-dining-pkg-btn"
                        >
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round">
                                <line x1="12" y1="5" x2="12" y2="19" />
                                <line x1="5" y1="12" x2="19" y2="12" />
                            </svg>
                            <span>Add Dining Package</span>
                        </button>
                    )}
                </div>
            </div>

            {/* TAB 1: DINING PACKAGES */}
            {activeTab === 'packages' && (
                <>
                    {/* Stats Overview Bar */}
                    <div className="room-types-stats-grid">
                        <div className="room-type-stat-card">
                            <div className="room-type-stat-card__icon room-type-stat-card__icon--gold">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                    <path d="M3 2v7c0 1.1.9 2 2 2h4a2 2 0 0 0 2-2V2" />
                                    <path d="M7 2v20" />
                                    <path d="M21 15V2v0a5 5 0 0 0-5 5v6c0 1.1.9 2 2 2h3Zm0 0v7" />
                                </svg>
                            </div>
                            <div className="room-type-stat-card__info">
                                <span className="room-type-stat-card__label">Total Packages</span>
                                <span className="room-type-stat-card__value">{packageStats.total}</span>
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
                                <span className="room-type-stat-card__label">Active Packages</span>
                                <span className="room-type-stat-card__value">{packageStats.active}</span>
                            </div>
                        </div>

                        <div className="room-type-stat-card">
                            <div className="room-type-stat-card__icon room-type-stat-card__icon--blue">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                    <circle cx="12" cy="12" r="5" />
                                    <line x1="12" y1="1" x2="12" y2="3" />
                                    <line x1="12" y1="21" x2="12" y2="23" />
                                    <line x1="4.22" y1="4.22" x2="5.64" y2="5.64" />
                                    <line x1="18.36" y1="18.36" x2="19.78" y2="19.78" />
                                    <line x1="1" y1="12" x2="3" y2="12" />
                                    <line x1="21" y1="12" x2="23" y2="12" />
                                    <line x1="4.22" y1="19.78" x2="5.64" y2="18.36" />
                                    <line x1="18.36" y1="5.64" x2="19.78" y2="4.22" />
                                </svg>
                            </div>
                            <div className="room-type-stat-card__info">
                                <span className="room-type-stat-card__label">Lunch / Dinner Sets</span>
                                <span className="room-type-stat-card__value">{packageStats.lunchCount + packageStats.dinnerCount}</span>
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
                                <span className="room-type-stat-card__label">Inactive Packages</span>
                                <span className="room-type-stat-card__value">{packageStats.inactive}</span>
                            </div>
                        </div>
                    </div>

                    {/* Filters and Search Bar with React Select */}
                    <div className="room-types-toolbar">
                        <div className="room-types-search-select-wrap">
                            <Select
                                options={packageSearchOptions}
                                value={currentPackageSelectValue}
                                onChange={(selected) => {
                                    setPackageSearch(selected ? selected.value : '');
                                    setPackagePage(1);
                                }}
                                onInputChange={(inputValue, { action }) => {
                                    if (action === 'input-change') {
                                        setPackageSearch(inputValue);
                                        setPackagePage(1);
                                    }
                                }}
                                placeholder="Search or select dining package..."
                                isClearable
                                isSearchable
                                styles={reactSelectStyles}
                                className="react-select-container"
                                classNamePrefix="react-select"
                                noOptionsMessage={({ inputValue }) =>
                                    inputValue ? `No package matches "${inputValue}"` : 'No options'
                                }
                            />
                        </div>

                        <div className="room-types-filter-group">
                            <button
                                type="button"
                                className={`room-types-filter-pill ${packageMealType === 'all' ? 'active' : ''}`}
                                onClick={() => { setPackageMealType('all'); setPackagePage(1); }}
                            >
                                All Meals
                            </button>
                            <button
                                type="button"
                                className={`room-types-filter-pill ${packageMealType === 'lunch' ? 'active' : ''}`}
                                onClick={() => { setPackageMealType('lunch'); setPackagePage(1); }}
                            >
                                Lunch
                            </button>
                            <button
                                type="button"
                                className={`room-types-filter-pill ${packageMealType === 'dinner' ? 'active' : ''}`}
                                onClick={() => { setPackageMealType('dinner'); setPackagePage(1); }}
                            >
                                Dinner
                            </button>
                            <button
                                type="button"
                                className={`room-types-filter-pill ${packageMealType === 'breakfast' ? 'active' : ''}`}
                                onClick={() => { setPackageMealType('breakfast'); setPackagePage(1); }}
                            >
                                Breakfast
                            </button>
                            <button
                                type="button"
                                className={`room-types-filter-pill ${packageMealType === 'combo' ? 'active' : ''}`}
                                onClick={() => { setPackageMealType('combo'); setPackagePage(1); }}
                            >
                                Combo
                            </button>
                        </div>
                    </div>

                    {/* Dining Packages Data Table */}
                    <div className="admin-table-card">
                        {isPackagesLoading ? (
                            <div className="dashboard-loading-state" style={{ padding: '40px', textAlign: 'center', color: 'var(--text-secondary)' }}>
                                <div className="admin-spinner" style={{ width: '32px', height: '32px', border: '3px solid rgba(212, 160, 23, 0.2)', borderTopColor: '#d4a017', borderRadius: '50%', animation: 'spin 0.8s linear infinite', margin: '0 auto 12px' }} />
                                <p style={{ fontSize: '0.9rem', fontWeight: 500 }}>Loading dining packages from server...</p>
                            </div>
                        ) : packages.length === 0 ? (
                            <div className="dashboard-empty-state">
                                <div className="dashboard-empty-state__icon-wrap">
                                    <svg width="42" height="42" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round">
                                        <path d="M3 2v7c0 1.1.9 2 2 2h4a2 2 0 0 0 2-2V2" />
                                        <path d="M7 2v20" />
                                        <path d="M21 15V2v0a5 5 0 0 0-5 5v6c0 1.1.9 2 2 2h3Zm0 0v7" />
                                    </svg>
                                </div>
                                <h4 className="dashboard-empty-state__title">No dining packages found</h4>
                                <p className="dashboard-empty-state__desc">
                                    {packageSearch || packageMealType !== 'all' || packageStatus !== 'all'
                                        ? 'No packages match your current filter criteria. Try resetting filters.'
                                        : 'No dining packages have been added yet. Click below to add your first package.'}
                                </p>
                                {packageSearch || packageMealType !== 'all' || packageStatus !== 'all' ? (
                                    <button
                                        type="button"
                                        className="admin-secondary-btn"
                                        onClick={() => {
                                            setPackageSearch('');
                                            setPackageMealType('all');
                                            setPackageStatus('all');
                                            setPackagePage(1);
                                        }}
                                    >
                                        Reset Filters
                                    </button>
                                ) : (
                                    <button
                                        type="button"
                                        className="admin-primary-btn"
                                        onClick={handleOpenAddPackage}
                                    >
                                        + Add First Package
                                    </button>
                                )}
                            </div>
                        ) : (
                            <>
                                <div className="admin-table-responsive">
                                    <table className="admin-table">
                                        <thead>
                                            <tr>
                                                <th style={{ width: '70px' }}>#ID</th>
                                                <th style={{ width: '80px' }}>Photo</th>
                                                <th>Package Name</th>
                                                <th>Meal Type</th>
                                                <th>Included Items</th>
                                                <th>Status</th>
                                                <th>Created At</th>
                                                <th style={{ textAlign: 'right', width: '130px' }}>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {packages.map((pkg) => {
                                                const imageUrl = resolveImageUrl(pkg.image_url || pkg.image_path);
                                                const itemsList = Array.isArray(pkg.items) ? pkg.items : [];
                                                const itemsCount = pkg.items_count ?? itemsList.length;

                                                return (
                                                    <tr key={pkg.id} className="admin-table-row">
                                                        <td className="admin-table-cell--id">#{pkg.id}</td>
                                                        <td>
                                                            {imageUrl ? (
                                                                <div
                                                                    style={{
                                                                        width: '60px',
                                                                        height: '46px',
                                                                        borderRadius: '8px',
                                                                        overflow: 'hidden',
                                                                        cursor: 'pointer',
                                                                        border: '1px solid rgba(40, 30, 20, 0.12)',
                                                                        backgroundColor: '#1a1612',
                                                                        display: 'flex',
                                                                        alignItems: 'center',
                                                                        justifyContent: 'center',
                                                                        padding: '2px',
                                                                    }}
                                                                    onClick={() =>
                                                                        setFullViewImage({
                                                                            url: imageUrl,
                                                                            alt: pkg.name,
                                                                            title: pkg.name,
                                                                        })
                                                                    }
                                                                    title="Click to view full photo"
                                                                >
                                                                    <img
                                                                        src={imageUrl}
                                                                        alt={pkg.name}
                                                                        style={{
                                                                            maxWidth: '100%',
                                                                            maxHeight: '100%',
                                                                            width: 'auto',
                                                                            height: 'auto',
                                                                            objectFit: 'contain',
                                                                            display: 'block',
                                                                        }}
                                                                    />
                                                                </div>
                                                            ) : (
                                                                <div
                                                                    style={{
                                                                        width: '56px',
                                                                        height: '44px',
                                                                        borderRadius: '8px',
                                                                        backgroundColor: '#faf7f2',
                                                                        border: '1px dashed #d1c7b7',
                                                                        color: '#a8a29e',
                                                                        fontSize: '0.65rem',
                                                                        display: 'flex',
                                                                        alignItems: 'center',
                                                                        justifyContent: 'center',
                                                                        textAlign: 'center',
                                                                        lineHeight: 1.1,
                                                                        padding: '2px',
                                                                    }}
                                                                >
                                                                    No photo
                                                                </div>
                                                            )}
                                                        </td>
                                                        <td>
                                                            <div className="room-type-name-cell">
                                                                <span className="room-type-name-text">{pkg.name}</span>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <span
                                                                style={{
                                                                    display: 'inline-block',
                                                                    padding: '3px 10px',
                                                                    borderRadius: '6px',
                                                                    fontSize: '0.78rem',
                                                                    fontWeight: '700',
                                                                    textTransform: 'capitalize',
                                                                    backgroundColor: '#f5edd8',
                                                                    color: '#8a6508',
                                                                }}
                                                            >
                                                                {pkg.meal_type}
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <span style={{ fontSize: '0.86rem', fontWeight: '600', color: 'var(--text-primary)' }}>
                                                                {itemsCount} {itemsCount === 1 ? 'item' : 'items'}
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <button
                                                                type="button"
                                                                onClick={() => handleTogglePackageStatus(pkg)}
                                                                className={`admin-status-badge admin-status-badge--${pkg.status}`}
                                                                style={{ cursor: 'pointer' }}
                                                                title={`Click to switch to ${pkg.status === 'active' ? 'inactive' : 'active'}`}
                                                            >
                                                                <span className="admin-status-dot" />
                                                                {pkg.status === 'active' ? 'Active' : 'Inactive'}
                                                            </button>
                                                        </td>
                                                        <td className="admin-table-cell--desc" style={{ color: 'var(--text-secondary)', fontSize: '0.85rem' }}>
                                                            {pkg.created_at
                                                                ? new Date(pkg.created_at).toLocaleDateString('en-US', {
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
                                                                    onClick={() => handleOpenViewPackage(pkg)}
                                                                    title="View Details"
                                                                    aria-label={`View ${pkg.name}`}
                                                                >
                                                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                                                        <path d="M1 12 s4 -8 11 -8 11 8 11 8 -4 8 -11 8 -11 -8 -11 -8 z" />
                                                                        <circle cx="12" cy="12" r="3" />
                                                                    </svg>
                                                                </button>
                                                                <button
                                                                    type="button"
                                                                    className="admin-action-btn admin-action-btn--edit"
                                                                    onClick={() => handleOpenEditPackage(pkg)}
                                                                    title="Edit Package"
                                                                    aria-label={`Edit ${pkg.name}`}
                                                                >
                                                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                                                        <path d="M11 4 H4 a2 2 0 0 0 -2 2 v14 a2 2 0 0 0 2 2 h14 a2 2 0 0 0 2 -2 v-7" />
                                                                        <path d="M18.5 2.5 a2.121 2.121 0 0 1 3 3 L12 15 l-4 1 1 -4 9.5 -9.5 z" />
                                                                    </svg>
                                                                </button>
                                                                <button
                                                                    type="button"
                                                                    className="admin-action-btn admin-action-btn--delete"
                                                                    onClick={() => {
                                                                        setDeletingPackage(pkg);
                                                                        setIsDeletePackageModalOpen(true);
                                                                    }}
                                                                    title="Delete Package"
                                                                    aria-label={`Delete ${pkg.name}`}
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
                                    currentPage={packagePage}
                                    totalItems={packageTotalRecords}
                                    pageSize={packagePageSize}
                                    onPageChange={(page) => setPackagePage(page)}
                                    onPageSizeChange={(size) => {
                                        setPackagePageSize(size);
                                        setPackagePage(1);
                                    }}
                                    pageSizeOptions={[10, 20, 50, 100]}
                                />
                            </>
                        )}
                    </div>
                </>
            )}

            {/* TAB 2: DINING LEADS / INQUIRIES */}
            {activeTab === 'leads' && (
                <>
                    {/* Stats Overview Bar */}
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
                                <span className="room-type-stat-card__label">Total Inquiries</span>
                                <span className="room-type-stat-card__value">{leadStats.total}</span>
                            </div>
                        </div>

                        <div className="room-type-stat-card">
                            <div className="room-type-stat-card__icon room-type-stat-card__icon--blue">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                    <circle cx="12" cy="12" r="10" />
                                    <line x1="12" y1="8" x2="12" y2="12" />
                                    <line x1="12" y1="16" x2="12.01" y2="16" />
                                </svg>
                            </div>
                            <div className="room-type-stat-card__info">
                                <span className="room-type-stat-card__label">New Inquiries</span>
                                <span className="room-type-stat-card__value">{leadStats.newLeads}</span>
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
                                <span className="room-type-stat-card__label">Confirmed / Converted</span>
                                <span className="room-type-stat-card__value">{leadStats.converted}</span>
                            </div>
                        </div>

                        <div className="room-type-stat-card">
                            <div className="room-type-stat-card__icon room-type-stat-card__icon--slate">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" />
                                </svg>
                            </div>
                            <div className="room-type-stat-card__info">
                                <span className="room-type-stat-card__label">Follow-Up / Contacted</span>
                                <span className="room-type-stat-card__value">{leadStats.followUp}</span>
                            </div>
                        </div>
                    </div>

                    {/* Toolbar for Leads */}
                    <div className="room-types-toolbar">
                        <div className="room-types-search-select-wrap">
                            <input
                                type="text"
                                className="admin-form-input"
                                placeholder="Search by customer name, phone, or email..."
                                value={leadSearch}
                                onChange={(e) => {
                                    setLeadSearch(e.target.value);
                                    setLeadPage(1);
                                }}
                                style={{ height: '42px' }}
                            />
                        </div>

                        <div className="room-types-filter-group">
                            <button
                                type="button"
                                className={`room-types-filter-pill ${leadStatus === 'all' ? 'active' : ''}`}
                                onClick={() => { setLeadStatus('all'); setLeadPage(1); }}
                            >
                                All Status
                            </button>
                            <button
                                type="button"
                                className={`room-types-filter-pill ${leadStatus === 'new' ? 'active' : ''}`}
                                onClick={() => { setLeadStatus('new'); setLeadPage(1); }}
                            >
                                New
                            </button>
                            <button
                                type="button"
                                className={`room-types-filter-pill ${leadStatus === 'contacted' ? 'active' : ''}`}
                                onClick={() => { setLeadStatus('contacted'); setLeadPage(1); }}
                            >
                                Contacted
                            </button>
                            <button
                                type="button"
                                className={`room-types-filter-pill ${leadStatus === 'follow_up' ? 'active' : ''}`}
                                onClick={() => { setLeadStatus('follow_up'); setLeadPage(1); }}
                            >
                                Follow-up
                            </button>
                            <button
                                type="button"
                                className={`room-types-filter-pill ${leadStatus === 'converted' ? 'active' : ''}`}
                                onClick={() => { setLeadStatus('converted'); setLeadPage(1); }}
                            >
                                Converted
                            </button>
                        </div>
                    </div>

                    {/* Leads Table */}
                    <div className="admin-table-card">
                        {isLeadsLoading ? (
                            <div className="dashboard-loading-state" style={{ padding: '40px', textAlign: 'center', color: 'var(--text-secondary)' }}>
                                <div className="admin-spinner" style={{ width: '32px', height: '32px', border: '3px solid rgba(212, 160, 23, 0.2)', borderTopColor: '#d4a017', borderRadius: '50%', animation: 'spin 0.8s linear infinite', margin: '0 auto 12px' }} />
                                <p style={{ fontSize: '0.9rem', fontWeight: 500 }}>Loading inquiries from server...</p>
                            </div>
                        ) : leads.length === 0 ? (
                            <div className="dashboard-empty-state">
                                <div className="dashboard-empty-state__icon-wrap">
                                    <svg width="42" height="42" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round">
                                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                                        <circle cx="9" cy="7" r="4" />
                                    </svg>
                                </div>
                                <h4 className="dashboard-empty-state__title">No dining inquiries found</h4>
                                <p className="dashboard-empty-state__desc">
                                    {leadSearch || leadStatus !== 'all' || leadMealType !== 'all'
                                        ? 'No customer inquiries matched your search criteria.'
                                        : 'No dining or restaurant reservation inquiries currently recorded.'}
                                </p>
                                {(leadSearch || leadStatus !== 'all' || leadMealType !== 'all') && (
                                    <button
                                        type="button"
                                        className="admin-secondary-btn"
                                        onClick={() => {
                                            setLeadSearch('');
                                            setLeadStatus('all');
                                            setLeadMealType('all');
                                            setLeadPage(1);
                                        }}
                                    >
                                        Reset Filters
                                    </button>
                                )}
                            </div>
                        ) : (
                            <>
                                <div className="admin-table-responsive">
                                    <table className="admin-table">
                                        <thead>
                                            <tr>
                                                <th style={{ width: '70px' }}>#ID</th>
                                                <th>Customer Name</th>
                                                <th>Contact Info</th>
                                                <th>Dining Date & Meal</th>
                                                <th>Guests</th>
                                                <th>Status</th>
                                                <th>Created At</th>
                                                <th style={{ textAlign: 'right', width: '130px' }}>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody id="dining-inquiries-table-body">
                                            {leads.map((lead) => {
                                                const info = getDiningLeadInfo(lead);
                                                const statusClass = `admin-status-badge--${info.status === 'converted' ? 'active' : info.status === 'new' ? 'pending' : info.status === 'lost' || info.status === 'cancelled' ? 'inactive' : 'maintenance'}`;

                                                return (
                                                    <tr key={info.id || lead.id} className="admin-table-row">
                                                        <td className="admin-table-cell--id">
                                                            <span style={{ fontWeight: 600 }}>#{info.id || lead.id}</span>
                                                        </td>
                                                        <td>
                                                            <div className="room-type-name-cell">
                                                                <span className="room-type-name-text" style={{ fontWeight: 600 }}>{info.name}</span>
                                                                {info.leadReference && (
                                                                    <span style={{ fontSize: '0.75rem', color: 'var(--text-secondary)' }}>
                                                                        {info.leadReference}
                                                                    </span>
                                                                )}
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <div style={{ fontSize: '0.84rem' }}>
                                                                <div style={{ fontWeight: '600', color: 'var(--text-primary)' }}>
                                                                    {info.phone ? (
                                                                        <a href={`tel:${info.phone}`} style={{ color: 'inherit', textDecoration: 'none' }}>
                                                                            {info.phone}
                                                                        </a>
                                                                    ) : (
                                                                        '—'
                                                                    )}
                                                                </div>
                                                                <div style={{ color: 'var(--text-secondary)', fontSize: '0.78rem' }}>
                                                                    {info.email ? (
                                                                        <a href={`mailto:${info.email}`} style={{ color: 'inherit', textDecoration: 'none' }}>
                                                                            {info.email}
                                                                        </a>
                                                                    ) : (
                                                                        '—'
                                                                    )}
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <div style={{ fontSize: '0.84rem' }}>
                                                                <div style={{ fontWeight: '600', color: 'var(--text-primary)' }}>
                                                                    {info.diningDate || '—'}
                                                                    {info.diningTime && (
                                                                        <span style={{ fontWeight: 'normal', color: 'var(--text-secondary)', fontSize: '0.78rem', marginLeft: '4px' }}>
                                                                            ({info.diningTime})
                                                                        </span>
                                                                    )}
                                                                </div>
                                                                <div style={{ display: 'flex', alignItems: 'center', gap: '6px', marginTop: '2px' }}>
                                                                    <span style={{ color: '#8a6508', textTransform: 'capitalize', fontSize: '0.78rem', fontWeight: '600', background: 'rgba(212, 160, 23, 0.12)', padding: '1px 6px', borderRadius: '4px' }}>
                                                                        {info.mealType || '—'}
                                                                    </span>
                                                                    {info.packageName && (
                                                                        <span style={{ color: 'var(--text-secondary)', fontSize: '0.76rem' }}>
                                                                            {info.packageName}
                                                                        </span>
                                                                    )}
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <span style={{ fontWeight: '700', color: 'var(--text-primary)', fontSize: '0.88rem' }}>
                                                                {info.guests} {info.guests === 1 ? 'Guest' : 'Guests'}
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <button
                                                                type="button"
                                                                className={`admin-status-badge ${statusClass}`}
                                                                onClick={() => {
                                                                    setUpdatingLeadStatus({
                                                                        id: info.id,
                                                                        status: info.status || 'new',
                                                                        notes: '',
                                                                    });
                                                                    setIsUpdateStatusModalOpen(true);
                                                                }}
                                                                style={{ cursor: 'pointer', textTransform: 'capitalize' }}
                                                                title="Click to update status"
                                                            >
                                                                <span className="admin-status-dot" />
                                                                {(info.status || 'new').replace('_', ' ')}
                                                            </button>
                                                        </td>
                                                        <td className="admin-table-cell--desc" style={{ color: 'var(--text-secondary)', fontSize: '0.85rem' }}>
                                                            {info.createdAt
                                                                ? new Date(info.createdAt).toLocaleDateString('en-US', {
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
                                                                    onClick={() => handleOpenViewLead(lead)}
                                                                    title="View Inquiry Details"
                                                                    aria-label={`View Inquiry #${info.id}`}
                                                                >
                                                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                                                        <path d="M1 12 s4 -8 11 -8 11 8 11 8 -4 8 -11 8 -11 -8 -11 -8 z" />
                                                                        <circle cx="12" cy="12" r="3" />
                                                                    </svg>
                                                                </button>
                                                                <button
                                                                    type="button"
                                                                    className="admin-action-btn admin-action-btn--edit"
                                                                    onClick={() => {
                                                                        setUpdatingLeadStatus({
                                                                            id: info.id,
                                                                            status: info.status || 'new',
                                                                            notes: '',
                                                                        });
                                                                        setIsUpdateStatusModalOpen(true);
                                                                    }}
                                                                    title="Update Status"
                                                                >
                                                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                                                        <path d="M11 4 H4 a2 2 0 0 0 -2 2 v14 a2 2 0 0 0 2 2 h14 a2 2 0 0 0 2 -2 v-7" />
                                                                        <path d="M18.5 2.5 a2.121 2.121 0 0 1 3 3 L12 15 l-4 1 1 -4 9.5 -9.5 z" />
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
                                    currentPage={leadPage}
                                    totalItems={leadTotalRecords}
                                    pageSize={leadPageSize}
                                    onPageChange={(page) => setLeadPage(page)}
                                    onPageSizeChange={(size) => {
                                        setLeadPageSize(size);
                                        setLeadPage(1);
                                    }}
                                    pageSizeOptions={[10, 20, 50, 100]}
                                />
                            </>
                        )}
                    </div>
                </>
            )}

            {/* ─── ADD / EDIT PACKAGE MODAL ─── */}
            {isPackageModalOpen && (
                <div className="admin-modal-overlay" onClick={() => !isSubmitting && setIsPackageModalOpen(false)}>
                    <div className="admin-modal" style={{ maxWidth: '680px' }} onClick={(e) => e.stopPropagation()} role="dialog" aria-modal="true">
                        <div className="admin-modal__header">
                            <div className="admin-modal__title-wrap">
                                <h3 className="admin-modal__title">
                                    {packageFormMode === 'create' ? 'Create Dining Package' : 'Edit Dining Package'}
                                </h3>
                                <p className="admin-modal__subtitle">
                                    {packageFormMode === 'create'
                                        ? 'Set up a new dining meal package and bundle included food items.'
                                        : `Updating specifications for ${editingPackage?.name}`}
                                </p>
                            </div>
                            <button
                                type="button"
                                className="admin-modal__close-btn"
                                onClick={() => !isSubmitting && setIsPackageModalOpen(false)}
                                aria-label="Close dialog"
                            >
                                ✕
                            </button>
                        </div>

                        <form onSubmit={handlePackageFormSubmit}>
                            <div className="admin-modal__body">
                                {packageFormErrors.submit && (
                                    <div className="admin-delete-warning-box" style={{ marginBottom: '16px', borderColor: '#fecaca', backgroundColor: '#fef2f2', color: '#991b1b' }}>
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#dc2626" strokeWidth="2">
                                            <circle cx="12" cy="12" r="10" />
                                            <line x1="12" y1="8" x2="12" y2="12" />
                                            <line x1="12" y1="16" x2="12.01" y2="16" />
                                        </svg>
                                        <span>{packageFormErrors.submit}</span>
                                    </div>
                                )}

                                {/* Package Name */}
                                <div className="admin-form-group">
                                    <label className="admin-form-label" htmlFor="pkg-name">
                                        Package Name <span className="admin-form-required">*</span>
                                    </label>
                                    <input
                                        id="pkg-name"
                                        type="text"
                                        className={`admin-form-input ${packageFormErrors.name ? 'admin-form-input--error' : ''}`}
                                        placeholder="e.g. South Indian Executive Thali, Buffet Feast"
                                        value={packageFormData.name}
                                        onChange={(e) => {
                                            setPackageFormData((prev) => ({ ...prev, name: e.target.value }));
                                            if (packageFormErrors.name) setPackageFormErrors((prev) => ({ ...prev, name: null }));
                                        }}
                                        maxLength={150}
                                        autoFocus
                                    />
                                    {packageFormErrors.name && (
                                        <span className="admin-form-error">
                                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5"><circle cx="12" cy="12" r="10" /><line x1="12" y1="8" x2="12" y2="12" /><line x1="12" y1="16" x2="12.01" y2="16" /></svg>
                                            {packageFormErrors.name}
                                        </span>
                                    )}
                                </div>

                                {/* Meal Type & Status Row */}
                                <div className="admin-form-row admin-form-row-2">
                                    <div className="admin-form-group">
                                        <label className="admin-form-label" htmlFor="pkg-meal-type">
                                            Meal Type <span className="admin-form-required">*</span>
                                        </label>
                                        <select
                                            id="pkg-meal-type"
                                            className="admin-form-select"
                                            value={packageFormData.meal_type}
                                            onChange={(e) => setPackageFormData((prev) => ({ ...prev, meal_type: e.target.value }))}
                                        >
                                            {MEAL_TYPES.map((m) => (
                                                <option key={m.value} value={m.value}>
                                                    {m.label}
                                                </option>
                                            ))}
                                        </select>
                                    </div>

                                    <div className="admin-form-group">
                                        <label className="admin-form-label" htmlFor="pkg-status">
                                            Status <span className="admin-form-required">*</span>
                                        </label>
                                        <select
                                            id="pkg-status"
                                            className="admin-form-select"
                                            value={packageFormData.status}
                                            onChange={(e) => setPackageFormData((prev) => ({ ...prev, status: e.target.value }))}
                                        >
                                            <option value="active">Active (Available for booking)</option>
                                            <option value="inactive">Inactive (Hidden)</option>
                                        </select>
                                    </div>
                                </div>

                                {/* Image Upload */}
                                <div className="admin-form-group">
                                    <label className="admin-form-label">Package Cover Photo (Optional)</label>
                                    <div style={{ display: 'flex', alignItems: 'center', gap: '16px' }}>
                                        {packageFormData.imagePreview && (
                                            <div style={{ position: 'relative', width: '80px', height: '60px', backgroundColor: '#faf7f2', borderRadius: '8px', border: '1px solid rgba(40, 30, 20, 0.15)', overflow: 'hidden', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                                                <img
                                                    src={packageFormData.imagePreview}
                                                    alt="Preview"
                                                    style={{
                                                        width: '100%',
                                                        height: '100%',
                                                        objectFit: 'cover',
                                                        display: 'block',
                                                    }}
                                                />
                                                <button
                                                    type="button"
                                                    onClick={() => setPackageFormData((prev) => ({ ...prev, imageFile: null, imagePreview: null }))}
                                                    style={{
                                                        position: 'absolute',
                                                        top: '3px',
                                                        right: '3px',
                                                        background: '#dc2626',
                                                        color: '#ffffff',
                                                        borderRadius: '50%',
                                                        width: '18px',
                                                        height: '18px',
                                                        border: 'none',
                                                        cursor: 'pointer',
                                                        fontSize: '11px',
                                                        display: 'flex',
                                                        alignItems: 'center',
                                                        justifyContent: 'center',
                                                        lineHeight: 1,
                                                    }}
                                                >
                                                    ✕
                                                </button>
                                            </div>
                                        )}
                                        <input
                                            type="file"
                                            accept="image/jpeg,image/png,image/webp,image/jpg"
                                            onChange={(e) => {
                                                const file = e.target.files[0];
                                                if (file) {
                                                    setPackageFormData((prev) => ({
                                                        ...prev,
                                                        imageFile: file,
                                                        imagePreview: URL.createObjectURL(file),
                                                    }));
                                                }
                                            }}
                                            style={{ fontSize: '0.85rem' }}
                                        />
                                    </div>
                                </div>

                                {/* Description */}
                                <div className="admin-form-group">
                                    <div className="admin-form-label-row">
                                        <label className="admin-form-label" htmlFor="pkg-desc">
                                            Description
                                        </label>
                                        <span className="admin-char-count">
                                            {(packageFormData.description || '').length} / 1000
                                        </span>
                                    </div>
                                    <textarea
                                        id="pkg-desc"
                                        rows={3}
                                        className={`admin-form-textarea ${packageFormErrors.description ? 'admin-form-input--error' : ''}`}
                                        placeholder="Describe dietary highlights, preparation style, and serving options..."
                                        value={packageFormData.description}
                                        onChange={(e) => {
                                            setPackageFormData((prev) => ({ ...prev, description: e.target.value }));
                                            if (packageFormErrors.description) setPackageFormErrors((prev) => ({ ...prev, description: null }));
                                        }}
                                        maxLength={1000}
                                    />
                                </div>

                                {/* Included Food Items Builder */}
                                <div className="admin-form-group">
                                    <label className="admin-form-label">
                                        Included Food Items (From Food Catalog)
                                    </label>
                                    <div style={{ marginBottom: '10px' }}>
                                        <Select
                                            options={foodItemSelectOptions}
                                            onChange={handleAddItemToPackage}
                                            placeholder="Pick food items to add into this package..."
                                            styles={reactSelectStyles}
                                            value={null}
                                            isSearchable
                                            isLoading={isFoodItemsLoading}
                                            noOptionsMessage={() => 'No food catalog items found'}
                                        />
                                    </div>

                                    {packageFormData.items.length === 0 ? (
                                        <div style={{ padding: '14px', backgroundColor: '#faf7f2', borderRadius: '10px', fontSize: '0.84rem', color: '#8a6508', textAlign: 'center', border: '1px dashed #e2d3b5' }}>
                                            No items bundled yet. Use the dropdown above to include dishes into this dining package.
                                        </div>
                                    ) : (
                                        <div style={{ display: 'flex', flexDirection: 'column', gap: '8px', maxHeight: '180px', overflowY: 'auto', paddingRight: '4px' }}>
                                            {packageFormData.items.map((item) => (
                                                <div
                                                    key={item.food_item_id}
                                                    style={{
                                                        display: 'flex',
                                                        alignItems: 'center',
                                                        justifyContent: 'space-between',
                                                        backgroundColor: '#faf7f2',
                                                        borderRadius: '8px',
                                                        padding: '8px 12px',
                                                        border: '1px solid rgba(40, 30, 20, 0.08)',
                                                    }}
                                                >
                                                    <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                                                        <span style={{ fontSize: '0.88rem', fontWeight: '700', color: 'var(--text-primary)' }}>
                                                            {item.name}
                                                        </span>
                                                        {item.food_type && (
                                                            <span
                                                                style={{
                                                                    fontSize: '0.7rem',
                                                                    fontWeight: '700',
                                                                    padding: '2px 6px',
                                                                    borderRadius: '4px',
                                                                    backgroundColor: item.food_type === 'veg' ? '#ecfdf5' : '#fef2f2',
                                                                    color: item.food_type === 'veg' ? '#059669' : '#dc2626',
                                                                }}
                                                            >
                                                                {item.food_type.toUpperCase()}
                                                            </span>
                                                        )}
                                                    </div>

                                                    <div style={{ display: 'flex', alignItems: 'center', gap: '10px' }}>
                                                        <div style={{ display: 'flex', alignItems: 'center', gap: '6px' }}>
                                                            <span style={{ fontSize: '0.78rem', color: '#64748b' }}>Qty:</span>
                                                            <input
                                                                type="number"
                                                                min={1}
                                                                max={99}
                                                                value={item.quantity}
                                                                onChange={(e) => handleUpdateItemQuantity(item.food_item_id, e.target.value)}
                                                                style={{
                                                                    width: '54px',
                                                                    height: '28px',
                                                                    borderRadius: '6px',
                                                                    border: '1px solid rgba(40, 30, 20, 0.15)',
                                                                    textAlign: 'center',
                                                                    fontSize: '0.85rem',
                                                                    fontWeight: '700',
                                                                }}
                                                            />
                                                        </div>
                                                        <button
                                                            type="button"
                                                            onClick={() => handleRemoveItemFromPackage(item.food_item_id)}
                                                            style={{
                                                                border: 'none',
                                                                background: 'none',
                                                                color: '#dc2626',
                                                                cursor: 'pointer',
                                                                fontSize: '1rem',
                                                                padding: '2px 6px',
                                                            }}
                                                            title="Remove item"
                                                        >
                                                            ✕
                                                        </button>
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                    )}
                                </div>
                            </div>

                            {/* Modal Footer Actions */}
                            <div className="admin-modal__footer">
                                <button
                                    type="button"
                                    className="admin-secondary-btn"
                                    onClick={() => setIsPackageModalOpen(false)}
                                    disabled={isSubmitting}
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    className="admin-primary-btn"
                                    disabled={isSubmitting}
                                >
                                    {isSubmitting ? 'Saving...' : packageFormMode === 'create' ? 'Create Package' : 'Save Changes'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* ─── VIEW PACKAGE MODAL ─── */}
            {isViewPackageModalOpen && viewingPackage && (
                <div className="admin-modal-overlay" onClick={() => setIsViewPackageModalOpen(false)}>
                    <div className="admin-modal admin-modal--view" style={{ maxWidth: '640px' }} onClick={(e) => e.stopPropagation()} role="dialog" aria-modal="true">
                        <div className="admin-modal__header">
                            <div className="admin-modal__title-wrap">
                                <div className="admin-modal__badge-row">
                                    <span className="room-type-id-pill">ID #{viewingPackage.id}</span>
                                    <span
                                        style={{
                                            padding: '3px 8px',
                                            borderRadius: '6px',
                                            fontSize: '0.78rem',
                                            fontWeight: '700',
                                            textTransform: 'capitalize',
                                            backgroundColor: '#f5edd8',
                                            color: '#8a6508',
                                        }}
                                    >
                                        {viewingPackage.meal_type}
                                    </span>
                                    <span
                                        className={`admin-status-badge admin-status-badge--${viewingPackage.status}`}
                                        style={{ width: 'fit-content', alignSelf: 'flex-start' }}
                                    >
                                        <span className="admin-status-dot" />
                                        {viewingPackage.status === 'active' ? 'Active' : 'Inactive'}
                                    </span>
                                </div>
                                <h3 className="admin-modal__title">{viewingPackage.name}</h3>
                            </div>
                            <button
                                type="button"
                                className="admin-modal__close-btn"
                                onClick={() => setIsViewPackageModalOpen(false)}
                                aria-label="Close dialog"
                            >
                                ✕
                            </button>
                        </div>

                        <div className="admin-modal__body">
                            {/* Photo if available - Shown fully with contain mode and click to expand into Lightbox */}
                            {resolveImageUrl(viewingPackage.image_url || viewingPackage.image_path) && (
                                <div
                                    className="view-modal-main-img-wrap"
                                    style={{
                                        position: 'relative',
                                        width: '100%',
                                        minHeight: '220px',
                                        maxHeight: '380px',
                                        height: 'auto',
                                        borderRadius: '12px',
                                        overflow: 'hidden',
                                        backgroundColor: '#120f0d',
                                        border: '1px solid rgba(40, 30, 20, 0.1)',
                                        display: 'flex',
                                        alignItems: 'center',
                                        justifyContent: 'center',
                                        cursor: 'zoom-in',
                                        marginBottom: '18px',
                                        padding: '4px',
                                    }}
                                    onClick={() =>
                                        setFullViewImage({
                                            url: resolveImageUrl(viewingPackage.image_url || viewingPackage.image_path),
                                            alt: viewingPackage.name,
                                            title: viewingPackage.name,
                                        })
                                    }
                                    title="Click to view full photo in fullscreen"
                                >
                                    <img
                                        src={resolveImageUrl(viewingPackage.image_url || viewingPackage.image_path)}
                                        alt={viewingPackage.name}
                                        className="view-modal-main-img"
                                        style={{
                                            maxWidth: '100%',
                                            maxHeight: '370px',
                                            width: 'auto',
                                            height: 'auto',
                                            objectFit: 'contain',
                                            display: 'block',
                                            borderRadius: '8px',
                                        }}
                                    />
                                    <div
                                        style={{
                                            position: 'absolute',
                                            bottom: '10px',
                                            right: '10px',
                                            backgroundColor: 'rgba(0, 0, 0, 0.75)',
                                            color: '#ffffff',
                                            padding: '4px 12px',
                                            borderRadius: '20px',
                                            fontSize: '0.75rem',
                                            fontWeight: 600,
                                            display: 'flex',
                                            alignItems: 'center',
                                            gap: '5px',
                                            backdropFilter: 'blur(4px)',
                                            boxShadow: '0 2px 8px rgba(0,0,0,0.35)',
                                            pointerEvents: 'none',
                                        }}
                                    >
                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
                                            <circle cx="11" cy="11" r="8" />
                                            <line x1="21" y1="21" x2="16.65" y2="16.65" />
                                            <line x1="11" y1="8" x2="11" y2="14" />
                                            <line x1="8" y1="11" x2="14" y2="11" />
                                        </svg>
                                        <span>Full View</span>
                                    </div>
                                </div>
                            )}

                            <div className="room-type-details-grid">
                                <div className="room-type-detail-item full-width">
                                    <span className="room-type-detail-label">Description</span>
                                    <p className="room-type-detail-text">
                                        {viewingPackage.description || 'No detailed description provided.'}
                                    </p>
                                </div>

                                <div className="room-type-detail-item full-width">
                                    <span className="room-type-detail-label">
                                        Included Dishes ({viewingPackage.items?.length || 0})
                                    </span>
                                    {Array.isArray(viewingPackage.items) && viewingPackage.items.length > 0 ? (
                                        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(220px, 1fr))', gap: '8px', marginTop: '6px' }}>
                                            {viewingPackage.items.map((it, idx) => {
                                                const dishName = resolveFoodItemName(it, foodItems);
                                                const categoryName = resolveFoodItemCategory(it, foodItems);
                                                const foodType = resolveFoodItemType(it, foodItems);

                                                return (
                                                    <div
                                                        key={idx}
                                                        style={{
                                                            backgroundColor: '#faf7f2',
                                                            borderRadius: '8px',
                                                            padding: '8px 12px',
                                                            border: '1px solid rgba(40, 30, 20, 0.08)',
                                                            fontSize: '0.84rem',
                                                            display: 'flex',
                                                            justifyContent: 'space-between',
                                                            alignItems: 'center',
                                                        }}
                                                    >
                                                        <div style={{ display: 'flex', flexDirection: 'column', gap: '2px' }}>
                                                            <div style={{ display: 'flex', alignItems: 'center', gap: '6px' }}>
                                                                <span style={{ fontWeight: '600', color: 'var(--text-primary)' }}>
                                                                    {dishName}
                                                                </span>
                                                                {foodType && (
                                                                    <span
                                                                        style={{
                                                                            fontSize: '0.65rem',
                                                                            fontWeight: '700',
                                                                            padding: '1px 5px',
                                                                            borderRadius: '4px',
                                                                            backgroundColor: foodType === 'veg' ? '#ecfdf5' : '#fef2f2',
                                                                            color: foodType === 'veg' ? '#059669' : '#dc2626',
                                                                        }}
                                                                    >
                                                                        {foodType.toUpperCase()}
                                                                    </span>
                                                                )}
                                                            </div>
                                                            {categoryName && (
                                                                <span style={{ fontSize: '0.74rem', color: '#78716c' }}>
                                                                    {categoryName}
                                                                </span>
                                                            )}
                                                        </div>
                                                        <span style={{ color: '#8a6508', fontWeight: '700', backgroundColor: '#ffffff', padding: '2px 8px', borderRadius: '4px', border: '1px solid rgba(212, 160, 23, 0.2)', marginLeft: '8px' }}>
                                                            ×{it.quantity}
                                                        </span>
                                                    </div>
                                                );
                                            })}
                                        </div>
                                    ) : (
                                        <p style={{ margin: 0, fontSize: '0.85rem', color: '#a8a29e' }}>No food items mapped.</p>
                                    )}
                                </div>

                                <div className="room-type-detail-item">
                                    <span className="room-type-detail-label">Created At</span>
                                    <span className="room-type-detail-val text-muted">
                                        {viewingPackage.created_at ? new Date(viewingPackage.created_at).toLocaleString() : '—'}
                                    </span>
                                </div>

                                <div className="room-type-detail-item">
                                    <span className="room-type-detail-label">Last Updated</span>
                                    <span className="room-type-detail-val text-muted">
                                        {viewingPackage.updated_at ? new Date(viewingPackage.updated_at).toLocaleString() : '—'}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div className="admin-modal__footer">
                            <button
                                type="button"
                                className="admin-secondary-btn"
                                onClick={() => setIsViewPackageModalOpen(false)}
                            >
                                Close
                            </button>
                            <button
                                type="button"
                                className="admin-primary-btn"
                                onClick={() => {
                                    setIsViewPackageModalOpen(false);
                                    handleOpenEditPackage(viewingPackage);
                                }}
                            >
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M11 4 H4 a2 2 0 0 0 -2 2 v14 a2 2 0 0 0 2 2 h14 a2 2 0 0 0 2 -2 v-7" /><path d="M18.5 2.5 a2.121 2.121 0 0 1 3 3 L12 15 l-4 1 1 -4 9.5 -9.5 z" /></svg>
                                Edit Package
                            </button>
                        </div>
                    </div>
                </div>
            )}

            {/* ─── DELETE CONFIRMATION MODAL ─── */}
            {isDeletePackageModalOpen && deletingPackage && (
                <div className="admin-modal-overlay" onClick={() => !isSubmitting && setIsDeletePackageModalOpen(false)}>
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
                                <h3 className="admin-modal__title">Delete Dining Package</h3>
                                <p className="admin-modal__subtitle">This action cannot be undone.</p>
                            </div>
                        </div>

                        <div className="admin-modal__body">
                            <p className="admin-delete-prompt">
                                Are you sure you want to permanently delete <strong>"{deletingPackage.name}"</strong>?
                            </p>
                        </div>

                        <div className="admin-modal__footer">
                            <button
                                type="button"
                                className="admin-secondary-btn"
                                onClick={() => setIsDeletePackageModalOpen(false)}
                                disabled={isSubmitting}
                            >
                                Cancel
                            </button>
                            <button
                                type="button"
                                className="admin-danger-btn"
                                onClick={handleConfirmDeletePackage}
                                disabled={isSubmitting}
                            >
                                {isSubmitting ? 'Deleting...' : 'Delete Package'}
                            </button>
                        </div>
                    </div>
                </div>
            )}

            {/* ─── VIEW LEAD DETAILS MODAL ─── */}
            {isViewLeadModalOpen && viewingLead && (() => {
                const info = getDiningLeadInfo(viewingLead);
                const statusClass = `admin-status-badge--${info.status === 'converted' ? 'active' : info.status === 'new' ? 'pending' : info.status === 'lost' || info.status === 'cancelled' ? 'inactive' : 'maintenance'}`;

                return (
                    <div className="admin-modal-overlay" onClick={() => setIsViewLeadModalOpen(false)}>
                        <div className="admin-modal admin-modal--view" style={{ maxWidth: '620px' }} onClick={(e) => e.stopPropagation()} role="dialog" aria-modal="true">
                            <div className="admin-modal__header">
                                <div className="admin-modal__title-wrap">
                                    <div className="admin-modal__badge-row" style={{ display: 'flex', alignItems: 'center', gap: '8px', marginBottom: '4px' }}>
                                        <span className="room-type-id-pill">
                                            #{info.id} {info.leadReference ? `(${info.leadReference})` : ''}
                                        </span>
                                        <span
                                            className={`admin-status-badge ${statusClass}`}
                                            style={{ width: 'fit-content', textTransform: 'capitalize' }}
                                        >
                                            {(info.status || 'new').replace('_', ' ')}
                                        </span>
                                    </div>
                                    <h3 className="admin-modal__title">{info.name}</h3>
                                </div>
                                <button
                                    type="button"
                                    className="admin-modal__close-btn"
                                    onClick={() => setIsViewLeadModalOpen(false)}
                                    aria-label="Close dialog"
                                >
                                    ✕
                                </button>
                            </div>

                            <div className="admin-modal__body">
                                <div className="room-type-details-grid">
                                    <div className="room-type-detail-item">
                                        <span className="room-type-detail-label">Phone Number</span>
                                        <span className="room-type-detail-val">
                                            {info.phone ? (
                                                <a href={`tel:${info.phone}`} style={{ color: 'inherit', fontWeight: '600', textDecoration: 'none' }}>
                                                    {info.phone}
                                                </a>
                                            ) : (
                                                <strong>—</strong>
                                            )}
                                        </span>
                                    </div>

                                    <div className="room-type-detail-item">
                                        <span className="room-type-detail-label">Email Address</span>
                                        <span className="room-type-detail-val">
                                            {info.email ? (
                                                <a href={`mailto:${info.email}`} style={{ color: 'inherit', textDecoration: 'none' }}>
                                                    {info.email}
                                                </a>
                                            ) : (
                                                '—'
                                            )}
                                        </span>
                                    </div>

                                    <div className="room-type-detail-item">
                                        <span className="room-type-detail-label">Dining Date</span>
                                        <span className="room-type-detail-val">
                                            <strong>{info.diningDate || '—'}</strong>
                                            {info.diningTime && (
                                                <span style={{ color: 'var(--text-secondary)', marginLeft: '6px' }}>
                                                    ({info.diningTime})
                                                </span>
                                            )}
                                        </span>
                                    </div>

                                    <div className="room-type-detail-item">
                                        <span className="room-type-detail-label">Meal Type</span>
                                        <span className="room-type-detail-val" style={{ textTransform: 'capitalize', fontWeight: '600', color: '#8a6508' }}>
                                            {info.mealType || '—'}
                                        </span>
                                    </div>

                                    <div className="room-type-detail-item">
                                        <span className="room-type-detail-label">Selected Package</span>
                                        <span className="room-type-detail-val">
                                            <strong>{info.packageName || 'Custom / None'}</strong>
                                        </span>
                                    </div>

                                    <div className="room-type-detail-item">
                                        <span className="room-type-detail-label">Guests Count</span>
                                        <span className="room-type-detail-val">
                                            <strong>{info.guests} {info.guests === 1 ? 'Guest' : 'Guests'}</strong>
                                        </span>
                                    </div>

                                    <div className="room-type-detail-item">
                                        <span className="room-type-detail-label">Inquiry Date</span>
                                        <span className="room-type-detail-val text-muted">
                                            {info.createdAt ? new Date(info.createdAt).toLocaleString() : '—'}
                                        </span>
                                    </div>

                                    {info.assignedAdmin && (
                                        <div className="room-type-detail-item">
                                            <span className="room-type-detail-label">Assigned Staff</span>
                                            <span className="room-type-detail-val">
                                                {info.assignedAdmin.name || `Admin #${info.assignedAdmin.id}`}
                                            </span>
                                        </div>
                                    )}

                                    {info.notes && (
                                        <div className="room-type-detail-item full-width">
                                            <span className="room-type-detail-label">Special Requests / Notes</span>
                                            <p className="room-type-detail-text">
                                                {info.notes}
                                            </p>
                                        </div>
                                    )}

                                    {info.packageItems && info.packageItems.length > 0 && (
                                        <div className="room-type-detail-item full-width">
                                            <span className="room-type-detail-label">Package Food Items</span>
                                            <div style={{ display: 'flex', flexWrap: 'wrap', gap: '6px', marginTop: '6px' }}>
                                                {info.packageItems.map((item, idx) => (
                                                    <span key={idx} style={{ background: 'var(--bg-secondary, #f1f5f9)', padding: '4px 10px', borderRadius: '16px', fontSize: '0.8rem', color: 'var(--text-primary)' }}>
                                                        {item.food_item?.name || item.name} {item.quantity > 1 ? `(x${item.quantity})` : ''}
                                                    </span>
                                                ))}
                                            </div>
                                        </div>
                                    )}

                                    {info.customFoodItems && info.customFoodItems.length > 0 && (
                                        <div className="room-type-detail-item full-width">
                                            <span className="room-type-detail-label">Custom Requested Food Items</span>
                                            <div style={{ display: 'flex', flexWrap: 'wrap', gap: '6px', marginTop: '6px' }}>
                                                {info.customFoodItems.map((item, idx) => (
                                                    <span key={idx} style={{ background: '#fef3c7', color: '#92400e', padding: '4px 10px', borderRadius: '16px', fontSize: '0.8rem' }}>
                                                        {item.food_item?.name || item.name} {item.quantity > 1 ? `(x${item.quantity})` : ''}
                                                    </span>
                                                ))}
                                            </div>
                                        </div>
                                    )}
                                </div>
                            </div>

                            <div className="admin-modal__footer">
                                <button
                                    type="button"
                                    className="admin-secondary-btn"
                                    onClick={() => setIsViewLeadModalOpen(false)}
                                >
                                    Close
                                </button>
                                <button
                                    type="button"
                                    className="admin-primary-btn"
                                    onClick={() => {
                                        setIsViewLeadModalOpen(false);
                                        setUpdatingLeadStatus({
                                            id: info.id,
                                            status: info.status || 'new',
                                            notes: '',
                                        });
                                        setIsUpdateStatusModalOpen(true);
                                    }}
                                >
                                    Update Status
                                </button>
                            </div>
                        </div>
                    </div>
                );
            })()}

            {/* ─── UPDATE LEAD STATUS MODAL ─── */}
            {isUpdateStatusModalOpen && (
                <div className="admin-modal-overlay" onClick={() => !isSubmitting && setIsUpdateStatusModalOpen(false)}>
                    <div className="admin-modal" style={{ maxWidth: '480px' }} onClick={(e) => e.stopPropagation()} role="dialog" aria-modal="true">
                        <div className="admin-modal__header">
                            <div className="admin-modal__title-wrap">
                                <h3 className="admin-modal__title">Update Inquiry Status</h3>
                                <p className="admin-modal__subtitle">Lead ID #{updatingLeadStatus.id}</p>
                            </div>
                            <button
                                type="button"
                                className="admin-modal__close-btn"
                                onClick={() => !isSubmitting && setIsUpdateStatusModalOpen(false)}
                            >
                                ✕
                            </button>
                        </div>

                        <form onSubmit={handleUpdateLeadStatusSubmit}>
                            <div className="admin-modal__body">
                                <div className="admin-form-group">
                                    <label className="admin-form-label" htmlFor="lead-status-select">
                                        Inquiry Status <span className="admin-form-required">*</span>
                                    </label>
                                    <select
                                        id="lead-status-select"
                                        className="admin-form-select"
                                        value={updatingLeadStatus.status}
                                        onChange={(e) => setUpdatingLeadStatus((prev) => ({ ...prev, status: e.target.value }))}
                                    >
                                        <option value="new">New (Pending Action)</option>
                                        <option value="contacted">Contacted (Customer Called)</option>
                                        <option value="follow_up">Follow Up (Awaiting Decision)</option>
                                        <option value="converted">Converted (Confirmed Reservation)</option>
                                        <option value="lost">Lost</option>
                                        <option value="cancelled">Cancelled</option>
                                    </select>
                                </div>

                                <div className="admin-form-group">
                                    <label className="admin-form-label" htmlFor="lead-status-notes">
                                        Follow-up Notes (Optional)
                                    </label>
                                    <textarea
                                        id="lead-status-notes"
                                        rows={3}
                                        className="admin-form-textarea"
                                        placeholder="Enter internal communication notes or discussion remarks..."
                                        value={updatingLeadStatus.notes}
                                        onChange={(e) => setUpdatingLeadStatus((prev) => ({ ...prev, notes: e.target.value }))}
                                        maxLength={500}
                                    />
                                </div>
                            </div>

                            <div className="admin-modal__footer">
                                <button
                                    type="button"
                                    className="admin-secondary-btn"
                                    onClick={() => setIsUpdateStatusModalOpen(false)}
                                    disabled={isSubmitting}
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    className="admin-primary-btn"
                                    disabled={isSubmitting}
                                >
                                    {isSubmitting ? 'Updating...' : 'Save Status'}
                                </button>
                            </div>
                        </form>
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

export default DiningModule;

