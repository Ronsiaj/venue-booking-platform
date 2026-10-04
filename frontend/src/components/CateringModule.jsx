import React, { useState, useEffect, useCallback, useMemo } from 'react';
import Select from 'react-select';
import { cateringApi } from '../services/cateringApi';
import Pagination from './Pagination';
import { normalizeLead } from '../services/Utils';

// Standard React Select styling adhering to the design system
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

// Backend supported rate units
const RATE_UNITS = [
    { value: 'per_person', label: 'Per Person', short: '/ person' },
    { value: 'per_plate', label: 'Per Plate', short: '/ plate' },
    { value: 'per_event', label: 'Per Event', short: '/ event' },
    { value: 'per_package', label: 'Per Package', short: '/ pkg' },
];

const RATE_UNIT_FILTER_OPTIONS = [
    { value: 'all', label: 'All Rate Units' },
    ...RATE_UNITS.map((u) => ({ value: u.value, label: u.label })),
];

const PACKAGE_STATUS_FILTER_OPTIONS = [
    { value: 'all', label: 'All Status' },
    { value: 'active', label: 'Active' },
    { value: 'inactive', label: 'Inactive' },
];

// Backend allowed lead statuses
const LEAD_STATUSES = [
    { value: 'new', label: 'New Inquiry', color: '#2563eb', bg: '#eff6ff' },
    { value: 'contacted', label: 'Contacted', color: '#d97706', bg: '#fffbeb' },
    { value: 'follow_up', label: 'Follow Up', color: '#7c3aed', bg: '#f5f3ff' },
    { value: 'converted', label: 'Converted', color: '#059669', bg: '#ecfdf5' },
    { value: 'lost', label: 'Lost', color: '#dc2626', bg: '#fef2f2' },
    { value: 'cancelled', label: 'Cancelled', color: '#475569', bg: '#f1f5f9' },
];

// Backend regex for catering package name
const PACKAGE_NAME_REGEX = /^[A-Za-z0-9][A-Za-z0-9\s&.,'()\/+\-]*$/;

/**
 * Catering / Package Admin Module
 * Complete backend integration for Catering Packages & Inquiries
 */
const CateringModule = ({ onNavigate, initialTab }) => {
    // Active Tab: 'packages' | 'leads'
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

    // ─── Packages State ───
    const [packages, setPackages] = useState([]);
    const [packageSearch, setPackageSearch] = useState('');
    const [packageStatus, setPackageStatus] = useState('all'); // 'all' | 'active' | 'inactive'
    const [packageRateUnit, setPackageRateUnit] = useState('all');
    const [packagePage, setPackagePage] = useState(1);
    const [packagePageSize, setPackagePageSize] = useState(10);
    const [packageTotalRecords, setPackageTotalRecords] = useState(0);
    const [isPackagesLoading, setIsPackagesLoading] = useState(false);

    // ─── Leads / Inquiries State ───
    const [leads, setLeads] = useState([]);
    const [leadSearch, setLeadSearch] = useState('');
    const [leadStatus, setLeadStatus] = useState('all');
    const [leadPage, setLeadPage] = useState(1);
    const [leadPageSize, setLeadPageSize] = useState(10);
    const [leadTotalRecords, setLeadTotalRecords] = useState(0);
    const [isLeadsLoading, setIsLeadsLoading] = useState(false);

    // ─── Food Items (for bundling dishes) ───
    const [foodItems, setFoodItems] = useState([]);
    const [isFoodItemsLoading, setIsFoodItemsLoading] = useState(false);

    // ─── Modals State ───
    const [isPackageModalOpen, setIsPackageModalOpen] = useState(false);
    const [packageFormMode, setPackageFormMode] = useState('create'); // 'create' | 'edit'
    const [editingPackage, setEditingPackage] = useState(null);
    const [isViewPackageModalOpen, setIsViewPackageModalOpen] = useState(false);
    const [viewingPackage, setViewingPackage] = useState(null);
    const [isDeletePackageModalOpen, setIsDeletePackageModalOpen] = useState(false);
    const [deletingPackage, setDeletingPackage] = useState(null);

    // Lead Modals & Status Form
    const [viewingLead, setViewingLead] = useState(null);
    const [isViewLeadModalOpen, setIsViewLeadModalOpen] = useState(false);
    const [updatingLeadStatus, setUpdatingLeadStatus] = useState({ id: null, status: 'new', notes: '' });
    const [isUpdateStatusModalOpen, setIsUpdateStatusModalOpen] = useState(false);

    // Full View Lightbox Image Modal
    const [fullViewImage, setFullViewImage] = useState(null);

    // Form Submission & Toast States
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [toastMessage, setToastMessage] = useState(null);

    const showToast = (msg, type = 'success') => {
        setToastMessage({ text: msg, type });
        setTimeout(() => {
            setToastMessage(null);
        }, 3500);
    };

    // Image URL Resolver
    const resolveImageUrl = (imagePath) => {
        if (!imagePath) return null;
        if (typeof imagePath === 'object') {
            imagePath = imagePath.image_path || imagePath.image_url || imagePath.url || '';
        }
        if (!imagePath || typeof imagePath !== 'string') return null;
        if (
            imagePath.startsWith('http://') ||
            imagePath.startsWith('https://') ||
            imagePath.startsWith('blob:') ||
            imagePath.startsWith('data:')
        ) {
            return imagePath;
        }
        const cleanPath = imagePath.startsWith('/') ? imagePath.slice(1) : imagePath;
        return `http://localhost/sm_groups/backend/${cleanPath}`;
    };

    // Package Form Fields
    const [packageFormData, setPackageFormData] = useState({
        name: '',
        description: '',
        rate: '',
        rate_unit: 'per_person',
        gst_rate: '18.00',
        price_includes_gst: '1',
        minimum_quantity: '1',
        status: 'active',
        imageFile: null,
        imagePreview: null,
        removeImage: false,
        items: [], // [{ food_item_id, quantity, name, category_name, food_type }]
    });
    const [packageFormErrors, setPackageFormErrors] = useState({});

    /**
     * Fetch Food Catalog Items
     */
    const fetchFoodItems = useCallback(async () => {
        setIsFoodItemsLoading(true);
        try {
            const res = await cateringApi.listFoodItems('limit=100&status=active');
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
     * Fetch Catering Packages with server-side pagination
     */
    const fetchPackages = useCallback(
        async (
            page = packagePage,
            limit = packagePageSize,
            search = packageSearch,
            status = packageStatus,
            rateUnit = packageRateUnit
        ) => {
            setIsPackagesLoading(true);
            try {
                const params = new URLSearchParams();
                params.append('page', String(page));
                params.append('limit', String(limit));
                if (search && search.trim()) params.append('search', search.trim());
                if (status && status !== 'all') params.append('status', status);
                if (rateUnit && rateUnit !== 'all') params.append('rate_unit', rateUnit);

                const res = await cateringApi.listPackages(params.toString());
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
                console.warn('Catering packages fetch error:', err.message);
                showToast('Failed to load catering packages.', 'error');
            } finally {
                setIsPackagesLoading(false);
            }
        },
        [packagePage, packagePageSize, packageSearch, packageStatus, packageRateUnit]
    );

    useEffect(() => {
        if (activeTab === 'packages') {
            fetchPackages(packagePage, packagePageSize, packageSearch, packageStatus, packageRateUnit);
        }
    }, [fetchPackages, activeTab, packagePage, packagePageSize, packageSearch, packageStatus, packageRateUnit]);

    /**
     * Fetch Catering Leads / Inquiries with server-side pagination
     */
    const fetchLeads = useCallback(
        async (page = leadPage, limit = leadPageSize, search = leadSearch, status = leadStatus) => {
            setIsLeadsLoading(true);
            try {
                const params = new URLSearchParams();
                params.append('page', String(page));
                params.append('limit', String(limit));
                if (search && search.trim()) params.append('search', search.trim());
                if (status && status !== 'all') params.append('status', status);

                const res = await cateringApi.listLeads(params.toString());
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
                console.warn('Catering leads fetch error:', err.message);
                showToast('Failed to load catering inquiries.', 'error');
            } finally {
                setIsLeadsLoading(false);
            }
        },
        [leadPage, leadPageSize, leadSearch, leadStatus]
    );

    useEffect(() => {
        if (activeTab === 'leads') {
            fetchLeads(leadPage, leadPageSize, leadSearch, leadStatus);
        }
    }, [fetchLeads, activeTab, leadPage, leadPageSize, leadSearch, leadStatus]);

    // Calculate Package Statistics
    const packageStats = useMemo(() => {
        const total = packageTotalRecords;
        const active = packages.filter((p) => p.status === 'active').length;
        const inactive = packages.filter((p) => p.status === 'inactive').length;
        return { total, active, inactive };
    }, [packages, packageTotalRecords]);

    // Calculate Lead Statistics
    const leadStats = useMemo(() => {
        const total = leadTotalRecords;
        const newLeads = leads.filter((l) => normalizeLead(l).status === 'new').length;
        const converted = leads.filter((l) => normalizeLead(l).status === 'converted').length;
        const followUp = leads.filter((l) => {
            const st = normalizeLead(l).status;
            return st === 'follow_up' || st === 'contacted';
        }).length;
        return { total, newLeads, converted, followUp };
    }, [leads, leadTotalRecords]);

    // React Select Options for Food Catalog
    const foodItemSelectOptions = useMemo(() => {
        return foodItems.map((fi) => ({
            value: fi.id,
            label: `${fi.name} (${fi.category_name || 'General'}${fi.food_type ? ` - ${fi.food_type.toUpperCase()}` : ''})`,
            item: fi,
        }));
    }, [foodItems]);

    // Form Validation
    const validatePackageForm = () => {
        const errors = {};
        const name = packageFormData.name.trim();

        if (!name) {
            errors.name = 'Package name is required.';
        } else if (name.length < 2 || name.length > 150) {
            errors.name = 'Package name must be between 2 and 150 characters.';
        } else if (!PACKAGE_NAME_REGEX.test(name)) {
            errors.name = 'Name contains invalid characters.';
        } else if (/\s{2,}/.test(name)) {
            errors.name = 'Name cannot contain multiple consecutive spaces.';
        }

        const rateNum = Number(packageFormData.rate);
        if (packageFormData.rate === '' || packageFormData.rate === null || packageFormData.rate === undefined) {
            errors.rate = 'Rate is required.';
        } else if (isNaN(rateNum) || rateNum < 0) {
            errors.rate = 'Rate must be a positive number.';
        }

        const minQtyNum = Number(packageFormData.minimum_quantity);
        if (!packageFormData.minimum_quantity || isNaN(minQtyNum) || minQtyNum < 1) {
            errors.minimum_quantity = 'Minimum quantity must be at least 1.';
        }

        if (packageFormData.description && packageFormData.description.length > 5000) {
            errors.description = 'Description cannot exceed 5000 characters.';
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
            rate: '',
            rate_unit: 'per_person',
            gst_rate: '18.00',
            price_includes_gst: '1',
            minimum_quantity: '1',
            status: 'active',
            imageFile: null,
            imagePreview: null,
            removeImage: false,
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
            rate: pkg.rate ? String(pkg.rate) : '',
            rate_unit: pkg.rate_unit || 'per_person',
            gst_rate: pkg.gst_rate !== undefined && pkg.gst_rate !== null ? String(pkg.gst_rate) : '18.00',
            price_includes_gst: pkg.price_includes_gst === true || pkg.price_includes_gst === 1 || pkg.price_includes_gst === '1' ? '1' : '0',
            minimum_quantity: pkg.minimum_quantity ? String(pkg.minimum_quantity) : '1',
            status: pkg.status || 'active',
            imageFile: null,
            imagePreview: pkg.image_url || pkg.image_path ? resolveImageUrl(pkg.image_url || pkg.image_path) : null,
            removeImage: false,
            items: Array.isArray(pkg.items)
                ? pkg.items.map((it) => ({
                    food_item_id: Number(it.food_item_id),
                    quantity: Number(it.quantity) || 1,
                    name: it.food_item?.name || it.food_item_name || it.name || `Item #${it.food_item_id}`,
                    category_name: it.category?.name || it.food_category_name || it.category_name || '',
                    food_type: it.food_item?.food_type || it.food_type || '',
                }))
                : [],
        });
        setPackageFormErrors({});
        setIsPackageModalOpen(true);
    };

    // Open View Package Modal with full details from backend
    const handleOpenViewPackage = async (pkg) => {
        setViewingPackage(pkg);
        setIsViewPackageModalOpen(true);
        try {
            const res = await cateringApi.viewPackage(pkg.id);
            if (res.success && res.data?.status && res.data?.data) {
                setViewingPackage((prev) => ({ ...prev, ...res.data.data }));
            }
        } catch (err) {
            console.warn('Catering package detail fetch note:', err.message);
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
            formData.append('rate', String(packageFormData.rate).trim());
            formData.append('rate_unit', packageFormData.rate_unit);
            formData.append('gst_rate', String(packageFormData.gst_rate || 18));
            formData.append('price_includes_gst', packageFormData.price_includes_gst === '1' || packageFormData.price_includes_gst === 1 || packageFormData.price_includes_gst === true ? '1' : '0');
            formData.append('minimum_quantity', String(packageFormData.minimum_quantity || 1));
            formData.append('status', packageFormData.status);

            if (packageFormData.description && packageFormData.description.trim()) {
                formData.append('description', packageFormData.description.trim());
            }

            // Items array formatted as backend expected JSON string: [{"food_item_id": 1, "quantity": 1}]
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
                const res = await cateringApi.createPackage(formData);
                if (res.success && res.data?.status) {
                    showToast('Catering package created successfully!', 'success');
                    setIsPackageModalOpen(false);
                    fetchPackages(1, packagePageSize, packageSearch, packageStatus, packageRateUnit);
                } else {
                    setPackageFormErrors({
                        submit: res.error || res.data?.message || 'Failed to create catering package.',
                    });
                }
            } else {
                formData.append('package_id', String(editingPackage.id));
                if (packageFormData.removeImage) {
                    formData.append('remove_image', '1');
                }

                const res = await cateringApi.updatePackage(formData);
                if (res.success && res.data?.status) {
                    showToast('Catering package updated successfully!', 'success');
                    setIsPackageModalOpen(false);
                    fetchPackages(packagePage, packagePageSize, packageSearch, packageStatus, packageRateUnit);
                } else {
                    setPackageFormErrors({
                        submit: res.error || res.data?.message || 'Failed to update catering package.',
                    });
                }
            }
        } catch (err) {
            setPackageFormErrors({ submit: err.message || 'An unexpected error occurred.' });
        } finally {
            setIsSubmitting(false);
        }
    };

    // Quick Status Toggle for Catering Package
    const handleTogglePackageStatus = async (pkg) => {
        const newStatus = pkg.status === 'active' ? 'inactive' : 'active';
        try {
            const formData = new FormData();
            formData.append('package_id', String(pkg.id));
            formData.append('name', pkg.name);
            formData.append('rate', String(pkg.rate));
            formData.append('rate_unit', pkg.rate_unit || 'per_person');
            formData.append('status', newStatus);

            const res = await cateringApi.updatePackage(formData);
            if (res.success && res.data?.status) {
                showToast(`Package status switched to ${newStatus}.`, 'success');
                fetchPackages(packagePage, packagePageSize, packageSearch, packageStatus, packageRateUnit);
            } else {
                showToast(res.error || res.data?.message || 'Could not update status.', 'error');
            }
        } catch (err) {
            showToast(err.message || 'Failed to toggle status.', 'error');
        }
    };

    // Confirm Delete Catering Package
    const handleConfirmDeletePackage = async () => {
        if (!deletingPackage) return;
        setIsSubmitting(true);
        try {
            const res = await cateringApi.deletePackage(deletingPackage.id);
            if (res.success && res.data?.status) {
                showToast('Catering package deleted successfully.', 'success');
                setIsDeletePackageModalOpen(false);
                setDeletingPackage(null);
                fetchPackages(packagePage, packagePageSize, packageSearch, packageStatus, packageRateUnit);
            } else {
                showToast(res.error || res.data?.message || 'Could not delete catering package.', 'error');
            }
        } catch (err) {
            showToast(err.message || 'Failed to delete package.', 'error');
        } finally {
            setIsSubmitting(false);
        }
    };

    // Add item to package builder
    const handleAddItemToPackage = (selectedOption) => {
        if (!selectedOption || !selectedOption.item) return;
        const item = selectedOption.item;
        setPackageFormData((prev) => {
            const exists = prev.items.some((it) => it.food_item_id === item.id);
            if (exists) {
                return {
                    ...prev,
                    items: prev.items.map((it) =>
                        it.food_item_id === item.id ? { ...it, quantity: it.quantity + 1 } : it
                    ),
                };
            }
            return {
                ...prev,
                items: [
                    ...prev.items,
                    {
                        food_item_id: item.id,
                        quantity: 1,
                        name: item.name,
                        category_name: item.category_name || '',
                        food_type: item.food_type || '',
                    },
                ],
            };
        });
    };

    // Update item quantity in package
    const handleUpdateItemQuantity = (foodItemId, newQty) => {
        const qtyNum = parseInt(newQty, 10);
        if (isNaN(qtyNum) || qtyNum < 1) return;
        setPackageFormData((prev) => ({
            ...prev,
            items: prev.items.map((it) =>
                it.food_item_id === foodItemId ? { ...it, quantity: Math.min(qtyNum, 999) } : it
            ),
        }));
    };

    // Remove item from package
    const handleRemoveItemFromPackage = (foodItemId) => {
        setPackageFormData((prev) => ({
            ...prev,
            items: prev.items.filter((it) => it.food_item_id !== foodItemId),
        }));
    };

    // Handle Lead Status Update Submit
    const handleUpdateLeadStatusSubmit = async (e) => {
        e.preventDefault();
        if (!updatingLeadStatus.id) return;
        setIsSubmitting(true);
        try {
            const res = await cateringApi.updateLeadStatus(
                updatingLeadStatus.id,
                updatingLeadStatus.status,
                updatingLeadStatus.notes
            );
            if (res.success && res.data?.status) {
                showToast('Inquiry status updated successfully!', 'success');
                setIsUpdateStatusModalOpen(false);
                fetchLeads(leadPage, leadPageSize, leadSearch, leadStatus);
            } else {
                showToast(res.error || res.data?.message || 'Failed to update lead status.', 'error');
            }
        } catch (err) {
            showToast(err.message || 'Error updating lead status.', 'error');
        } finally {
            setIsSubmitting(false);
        }
    };

    // Open View Lead Modal with full details
    const handleOpenViewLead = async (lead) => {
        setViewingLead(lead);
        setIsViewLeadModalOpen(true);
        try {
            const res = await cateringApi.viewLead(lead.id);
            if (res.success && res.data?.status && res.data?.data) {
                setViewingLead((prev) => ({ ...prev, ...res.data.data }));
            }
        } catch (err) {
            console.warn('Catering lead details note:', err.message);
        }
    };

    // Format Rate Display
    const formatRateDisplay = (rate, unit) => {
        const num = Number(rate);
        if (isNaN(num)) return '—';
        const formatted = new Intl.NumberFormat('en-IN', {
            style: 'currency',
            currency: 'INR',
            maximumFractionDigits: 2,
        }).format(num);

        const unitObj = RATE_UNITS.find((u) => u.value === unit);
        return `${formatted} ${unitObj?.short || ''}`;
    };

    // Search select options for packages
    const packageSearchOptions = useMemo(() => {
        if (!packages || packages.length === 0) return [];
        const opts = [];
        packages.forEach((pkg) => {
            if (pkg.name) opts.push({ value: pkg.name, label: pkg.name });
            if (pkg.description) opts.push({ value: pkg.description, label: pkg.description.length > 50 ? pkg.description.slice(0, 50) + '...' : pkg.description });
        });
        const unique = [];
        const seen = new Set();
        for (const opt of opts) {
            const key = opt.value.toLowerCase().trim();
            if (!seen.has(key)) {
                seen.add(key);
                unique.push(opt);
            }
        }
        return unique;
    }, [packages]);

    const currentPackageSelectValue = useMemo(() => {
        if (!packageSearch) return null;
        return packageSearchOptions.find((opt) => opt.value.toLowerCase() === packageSearch.toLowerCase()) || {
            value: packageSearch,
            label: packageSearch,
        };
    }, [packageSearch, packageSearchOptions]);

    // Search select options for leads
    const leadSearchOptions = useMemo(() => {
        if (!leads || leads.length === 0) return [];
        const opts = [];
        leads.forEach((l) => {
            const norm = normalizeLead(l);
            if (norm.leadReference) opts.push({ value: norm.leadReference, label: norm.leadReference });
            if (norm.customerName) opts.push({ value: norm.customerName, label: norm.customerName });
            if (norm.customerPhone) opts.push({ value: norm.customerPhone, label: norm.customerPhone });
            if (norm.customerEmail) opts.push({ value: norm.customerEmail, label: norm.customerEmail });
            if (norm.eventType) opts.push({ value: norm.eventType, label: `Event: ${norm.eventType}` });
            if (norm.packageName) opts.push({ value: norm.packageName, label: `Package: ${norm.packageName}` });
        });
        const unique = [];
        const seen = new Set();
        for (const opt of opts) {
            const key = opt.value.toLowerCase().trim();
            if (!seen.has(key)) {
                seen.add(key);
                unique.push(opt);
            }
        }
        return unique;
    }, [leads]);

    const currentLeadSelectValue = useMemo(() => {
        if (!leadSearch) return null;
        return leadSearchOptions.find((opt) => opt.value.toLowerCase() === leadSearch.toLowerCase()) || {
            value: leadSearch,
            label: leadSearch,
        };
    }, [leadSearch, leadSearchOptions]);

    return (
        <div className="room-types-module">
            {/* ─── TOAST NOTIFICATION ─── */}
            {toastMessage && (
                <div className={`admin-toast admin-toast--${toastMessage.type}`} role="alert">
                    <span className="admin-toast__icon">
                        {toastMessage.type === 'success' ? '✓' : '⚠'}
                    </span>
                    <span className="admin-toast__text">{toastMessage.text}</span>
                    <button
                        type="button"
                        className="admin-toast__close"
                        onClick={() => setToastMessage(null)}
                        aria-label="Close notification"
                    >
                        ✕
                    </button>
                </div>
            )}

            {/* ─── MODULE HEADER ─── */}
            <div className="dashboard-page-header">
                <div className="dashboard-page-header__info">
                    <h2 className="dashboard-page-header__title">Catering Management</h2>
                    <p className="dashboard-page-header__desc">
                        Create and manage catering packages, dish menus, pricing units, and banquet food inquiries.
                    </p>
                </div>

                <div className="dashboard-page-header__actions">
                    {activeTab === 'packages' && (
                        <button
                            type="button"
                            className="admin-primary-btn"
                            onClick={handleOpenAddPackage}
                            id="add-catering-pkg-btn"
                        >
                            <svg
                                width="18"
                                height="18"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                strokeWidth="2.2"
                                strokeLinecap="round"
                                strokeLinejoin="round"
                            >
                                <line x1="12" y1="5" x2="12" y2="19" />
                                <line x1="5" y1="12" x2="19" y2="12" />
                            </svg>
                            <span>Add Catering Package</span>
                        </button>
                    )}
                </div>
            </div>

            {/* ═════════════════════════════════════════════════════════════ */}
            {/* TAB 1: CATERING PACKAGES */}
            {/* ═════════════════════════════════════════════════════════════ */}
            {activeTab === 'packages' && (
                <>
                    {/* Stats Overview Bar */}
                    <div className="room-types-stats-grid">
                        <div className="room-type-stat-card">
                            <div className="room-type-stat-card__icon room-type-stat-card__icon--gold">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                    <path d="M12 2v20M2 12h20M4.93 4.93l14.14 14.14M4.93 19.07l14.14-14.14" />
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

                        <div className="room-type-stat-card">
                            <div className="room-type-stat-card__icon room-type-stat-card__icon--blue">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                                    <polyline points="14 2 14 8 20 8" />
                                    <line x1="16" y1="13" x2="8" y2="13" />
                                    <line x1="16" y1="17" x2="8" y2="17" />
                                </svg>
                            </div>
                            <div className="room-type-stat-card__info">
                                <span className="room-type-stat-card__label">Total Inquiries</span>
                                <span className="room-type-stat-card__value">{leadStats.total}</span>
                            </div>
                        </div>
                    </div>

                    {/* Filter & Search Toolbar */}
                    <div className="room-types-toolbar" style={{ flexWrap: 'wrap', gap: '10px', justifyContent: 'flex-start' }}>
                        <div className="room-types-search-select-wrap" style={{ minWidth: '240px', maxWidth: '340px', flex: '1 1 240px' }}>
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
                                placeholder="Search or select catering package..."
                                isClearable
                                isSearchable
                                styles={reactSelectStyles}
                                className="react-select-container"
                                classNamePrefix="react-select"
                                noOptionsMessage={({ inputValue }) =>
                                    inputValue ? `No packages match "${inputValue}"` : 'Type to search packages...'
                                }
                            />
                        </div>

                        <div style={{ display: 'flex', alignItems: 'center', gap: '8px', flexWrap: 'wrap' }}>
                            {/* Package Status Dropdown */}
                            <div style={{ minWidth: '150px' }}>
                                <Select
                                    options={PACKAGE_STATUS_FILTER_OPTIONS}
                                    value={
                                        PACKAGE_STATUS_FILTER_OPTIONS.find((opt) => opt.value === packageStatus) ||
                                        PACKAGE_STATUS_FILTER_OPTIONS[0]
                                    }
                                    onChange={(selected) => {
                                        setPackageStatus(selected ? selected.value : 'all');
                                        setPackagePage(1);
                                    }}
                                    isSearchable={false}
                                    styles={reactSelectStyles}
                                    className="react-select-container"
                                    classNamePrefix="react-select"
                                    placeholder="All Status"
                                />
                            </div>

                            {/* Rate Unit Dropdown */}
                            <div style={{ minWidth: '170px' }}>
                                <Select
                                    options={RATE_UNIT_FILTER_OPTIONS}
                                    value={
                                        RATE_UNIT_FILTER_OPTIONS.find((opt) => opt.value === packageRateUnit) ||
                                        RATE_UNIT_FILTER_OPTIONS[0]
                                    }
                                    onChange={(selected) => {
                                        setPackageRateUnit(selected ? selected.value : 'all');
                                        setPackagePage(1);
                                    }}
                                    isSearchable={false}
                                    styles={reactSelectStyles}
                                    className="react-select-container"
                                    classNamePrefix="react-select"
                                />
                            </div>
                        </div>
                    </div>

                    {/* Catering Packages Data Table */}
                    <div className="admin-table-card">
                        {isPackagesLoading ? (
                            <div className="dashboard-loading-state" style={{ padding: '40px', textAlign: 'center', color: 'var(--text-secondary)' }}>
                                <div
                                    className="admin-spinner"
                                    style={{
                                        width: '32px',
                                        height: '32px',
                                        border: '3px solid rgba(212, 160, 23, 0.2)',
                                        borderTopColor: '#d4a017',
                                        borderRadius: '50%',
                                        animation: 'spin 0.8s linear infinite',
                                        margin: '0 auto 12px',
                                    }}
                                />
                                <p style={{ fontSize: '0.9rem', fontWeight: 500 }}>Loading catering packages from server...</p>
                            </div>
                        ) : packages.length === 0 ? (
                            <div className="dashboard-empty-state">
                                <div className="dashboard-empty-state__icon-wrap">
                                    <svg width="42" height="42" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round">
                                        <path d="M18 8h1a4 4 0 0 1 0 8h-1" />
                                        <path d="M2 8h16v9a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4V8z" />
                                        <line x1="6" y1="2" x2="6" y2="4" />
                                        <line x1="10" y1="2" x2="10" y2="4" />
                                        <line x1="14" y1="2" x2="14" y2="4" />
                                    </svg>
                                </div>
                                <h4 className="dashboard-empty-state__title">No catering packages found</h4>
                                <p className="dashboard-empty-state__desc">
                                    {packageSearch || packageStatus !== 'all' || packageRateUnit !== 'all'
                                        ? 'No packages matched your active filters.'
                                        : 'No catering packages have been created yet. Click below to add your first package.'}
                                </p>
                                {packageSearch || packageStatus !== 'all' || packageRateUnit !== 'all' ? (
                                    <button
                                        type="button"
                                        className="admin-secondary-btn"
                                        onClick={() => {
                                            setPackageSearch('');
                                            setPackageStatus('all');
                                            setPackageRateUnit('all');
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
                                                <th>Pricing & Rate</th>
                                                <th>Min Qty</th>
                                                <th>Included Dishes</th>
                                                <th>Status</th>
                                                <th>Created At</th>
                                                <th style={{ textAlign: 'right', width: '130px' }}>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {packages.map((pkg) => {
                                                const imageUrl = resolveImageUrl(pkg.image_url || pkg.image_path);
                                                const itemsList = Array.isArray(pkg.items) ? pkg.items : [];
                                                const itemsCount = pkg.item_count ?? itemsList.length;

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
                                                                        width: '60px',
                                                                        height: '46px',
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
                                                                {pkg.description && (
                                                                    <span
                                                                        style={{
                                                                            fontSize: '0.78rem',
                                                                            color: 'var(--text-secondary)',
                                                                            display: '-webkit-box',
                                                                            WebkitLineClamp: 1,
                                                                            WebkitBoxOrient: 'vertical',
                                                                            overflow: 'hidden',
                                                                        }}
                                                                    >
                                                                        {pkg.description}
                                                                    </span>
                                                                )}
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <div>
                                                                <span style={{ fontWeight: '700', fontSize: '0.92rem', color: 'var(--text-primary)' }}>
                                                                    {formatRateDisplay(pkg.rate, pkg.rate_unit)}
                                                                </span>
                                                                <div style={{ fontSize: '0.74rem', color: '#64748b', marginTop: '1px' }}>
                                                                    {pkg.price_includes_gst == 1 || pkg.price_includes_gst === true ? (
                                                                        <span>Incl. {pkg.gst_rate ?? 18}% GST</span>
                                                                    ) : (
                                                                        <span>+{pkg.gst_rate ?? 18}% GST extra</span>
                                                                    )}
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <span style={{ fontSize: '0.86rem', fontWeight: '600' }}>
                                                                {pkg.minimum_quantity || 1}
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <span style={{ fontSize: '0.86rem', fontWeight: '600', color: 'var(--text-primary)' }}>
                                                                {itemsCount} {itemsCount === 1 ? 'dish' : 'dishes'}
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

            {/* ═════════════════════════════════════════════════════════════ */}
            {/* TAB 2: CATERING LEADS / INQUIRIES */}
            {/* ═════════════════════════════════════════════════════════════ */}
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
                        <div className="room-types-search-select-wrap" style={{ minWidth: '240px', flex: 1 }}>
                            <Select
                                options={leadSearchOptions}
                                value={currentLeadSelectValue}
                                onChange={(selected) => {
                                    setLeadSearch(selected ? selected.value : '');
                                    setLeadPage(1);
                                }}
                                onInputChange={(inputValue, { action }) => {
                                    if (action === 'input-change') {
                                        setLeadSearch(inputValue);
                                        setLeadPage(1);
                                    }
                                }}
                                placeholder="Search customer name, phone, or email..."
                                isClearable
                                isSearchable
                                styles={reactSelectStyles}
                                className="react-select-container"
                                classNamePrefix="react-select"
                                noOptionsMessage={({ inputValue }) =>
                                    inputValue ? `No leads match "${inputValue}"` : 'Type to search leads...'
                                }
                            />
                        </div>

                        <div className="room-types-filter-group">
                            <button
                                type="button"
                                className={`room-types-filter-pill ${leadStatus === 'all' ? 'active' : ''}`}
                                onClick={() => {
                                    setLeadStatus('all');
                                    setLeadPage(1);
                                }}
                            >
                                All Status
                            </button>
                            {LEAD_STATUSES.map((st) => (
                                <button
                                    key={st.value}
                                    type="button"
                                    className={`room-types-filter-pill ${leadStatus === st.value ? 'active' : ''}`}
                                    onClick={() => {
                                        setLeadStatus(st.value);
                                        setLeadPage(1);
                                    }}
                                >
                                    {st.label}
                                </button>
                            ))}
                        </div>
                    </div>

                    {/* Leads Table */}
                    <div className="admin-table-card">
                        {isLeadsLoading ? (
                            <div className="dashboard-loading-state" style={{ padding: '40px', textAlign: 'center', color: 'var(--text-secondary)' }}>
                                <div
                                    className="admin-spinner"
                                    style={{
                                        width: '32px',
                                        height: '32px',
                                        border: '3px solid rgba(212, 160, 23, 0.2)',
                                        borderTopColor: '#d4a017',
                                        borderRadius: '50%',
                                        animation: 'spin 0.8s linear infinite',
                                        margin: '0 auto 12px',
                                    }}
                                />
                                <p style={{ fontSize: '0.9rem', fontWeight: 500 }}>Loading catering inquiries from server...</p>
                            </div>
                        ) : leads.length === 0 ? (
                            <div className="dashboard-empty-state">
                                <div className="dashboard-empty-state__icon-wrap">
                                    <svg width="42" height="42" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round">
                                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                                        <circle cx="9" cy="7" r="4" />
                                    </svg>
                                </div>
                                <h4 className="dashboard-empty-state__title">No catering inquiries found</h4>
                                <p className="dashboard-empty-state__desc">
                                    {leadSearch || leadStatus !== 'all'
                                        ? 'No customer inquiries matched your search criteria.'
                                        : 'No catering or food service inquiries recorded yet.'}
                                </p>
                            </div>
                        ) : (
                            <>
                                <div className="admin-table-responsive">
                                    <table className="admin-table">
                                        <thead>
                                            <tr>
                                                <th style={{ width: '70px' }}>#Lead</th>
                                                <th>Customer Name</th>
                                                <th>Contact Details</th>
                                                <th>Event Date & Guests</th>
                                                <th>Package Selected</th>
                                                <th>Status</th>
                                                <th>Date Submitted</th>
                                                <th style={{ textAlign: 'right', width: '120px' }}>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {leads.map((lead) => {
                                                const norm = normalizeLead(lead);
                                                const statusObj = LEAD_STATUSES.find((s) => s.value === norm.status) || {
                                                    label: norm.status,
                                                    color: '#475569',
                                                    bg: '#f1f5f9',
                                                };

                                                return (
                                                    <tr key={norm.id || lead.id} className="admin-table-row">
                                                        <td className="admin-table-cell--id">
                                                            <span style={{ fontWeight: 600 }}>#{norm.id || lead.id}</span>
                                                            {norm.leadReference && (
                                                                <div style={{ fontSize: '0.74rem', color: 'var(--text-secondary)' }}>
                                                                    {norm.leadReference}
                                                                </div>
                                                            )}
                                                        </td>
                                                        <td>
                                                            <span style={{ fontWeight: '700', color: 'var(--text-primary)' }}>
                                                                {norm.customerName || 'Anonymous Customer'}
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <div style={{ fontSize: '0.84rem' }}>
                                                                <div>📞 {norm.customerPhone ? <a href={`tel:${norm.customerPhone}`} style={{ color: 'inherit', textDecoration: 'none' }}>{norm.customerPhone}</a> : '—'}</div>
                                                                {norm.customerEmail && <div style={{ color: '#64748b' }}>✉ <a href={`mailto:${norm.customerEmail}`} style={{ color: 'inherit', textDecoration: 'none' }}>{norm.customerEmail}</a></div>}
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <div style={{ fontSize: '0.84rem' }}>
                                                                <div>📅 {norm.eventDate || 'Not specified'}{norm.eventTime ? ` (${norm.eventTime})` : ''}</div>
                                                                <div style={{ color: '#64748b' }}>
                                                                    👥 {norm.guestCount !== null ? `${norm.guestCount} guests` : '—'}
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <span
                                                                style={{
                                                                    display: 'inline-block',
                                                                    padding: '3px 8px',
                                                                    borderRadius: '6px',
                                                                    fontSize: '0.78rem',
                                                                    fontWeight: '700',
                                                                    backgroundColor: '#f5edd8',
                                                                    color: '#8a6508',
                                                                }}
                                                            >
                                                                {norm.packageName || (norm.eventType ? `${norm.eventType} Catering` : 'Custom Menu')}
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <span
                                                                style={{
                                                                    display: 'inline-block',
                                                                    padding: '3px 10px',
                                                                    borderRadius: '20px',
                                                                    fontSize: '0.76rem',
                                                                    fontWeight: '700',
                                                                    textTransform: 'capitalize',
                                                                    backgroundColor: statusObj.bg,
                                                                    color: statusObj.color,
                                                                }}
                                                            >
                                                                {statusObj.label}
                                                            </span>
                                                        </td>
                                                        <td className="admin-table-cell--desc" style={{ color: 'var(--text-secondary)', fontSize: '0.84rem' }}>
                                                            {norm.createdAt
                                                                ? new Date(norm.createdAt).toLocaleDateString('en-US', {
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
                                                                    title="View Details"
                                                                    aria-label={`View lead #${norm.id || lead.id}`}
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
                                                                            id: norm.id || lead.id,
                                                                            status: norm.status || 'new',
                                                                            notes: '',
                                                                        });
                                                                        setIsUpdateStatusModalOpen(true);
                                                                    }}
                                                                    title="Update Status"
                                                                    aria-label={`Update lead status #${norm.id || lead.id}`}
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

            {/* ═════════════════════════════════════════════════════════════ */}
            {/* MODALS */}
            {/* ═════════════════════════════════════════════════════════════ */}

            {/* ─── CREATE / EDIT CATERING PACKAGE MODAL ─── */}
            {isPackageModalOpen && (
                <div className="admin-modal-overlay" onClick={() => !isSubmitting && setIsPackageModalOpen(false)}>
                    <div
                        className="admin-modal"
                        style={{ maxWidth: '680px' }}
                        onClick={(e) => e.stopPropagation()}
                        role="dialog"
                        aria-modal="true"
                    >
                        <div className="admin-modal__header">
                            <div className="admin-modal__title-wrap">
                                <h3 className="admin-modal__title">
                                    {packageFormMode === 'create' ? 'Add Catering Package' : 'Edit Catering Package'}
                                </h3>
                                <p className="admin-modal__subtitle">
                                    {packageFormMode === 'create'
                                        ? 'Define a new catering package with custom rate, unit, and bundled food items.'
                                        : `Editing package #${editingPackage?.id} (${editingPackage?.name})`}
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
                                    <div className="admin-form-alert admin-form-alert--error" style={{ marginBottom: '16px' }}>
                                        {packageFormErrors.submit}
                                    </div>
                                )}

                                {/* Name & Rate Unit */}
                                <div className="admin-form-row admin-form-row-2">
                                    <div className="admin-form-group">
                                        <label className="admin-form-label" htmlFor="pkg-name">
                                            Package Name <span className="admin-form-required">*</span>
                                        </label>
                                        <input
                                            type="text"
                                            id="pkg-name"
                                            className={`admin-form-input ${packageFormErrors.name ? 'admin-form-input--error' : ''}`}
                                            placeholder="e.g. Grand Wedding Feast"
                                            value={packageFormData.name}
                                            onChange={(e) => {
                                                setPackageFormData((prev) => ({ ...prev, name: e.target.value }));
                                                if (packageFormErrors.name) setPackageFormErrors((prev) => ({ ...prev, name: null }));
                                            }}
                                            maxLength={150}
                                            required
                                        />
                                        {packageFormErrors.name && (
                                            <span className="admin-form-error">{packageFormErrors.name}</span>
                                        )}
                                    </div>

                                    <div className="admin-form-group">
                                        <label className="admin-form-label" htmlFor="pkg-rate-unit">
                                            Rate Unit <span className="admin-form-required">*</span>
                                        </label>
                                        <select
                                            id="pkg-rate-unit"
                                            className="admin-form-select"
                                            value={packageFormData.rate_unit}
                                            onChange={(e) => setPackageFormData((prev) => ({ ...prev, rate_unit: e.target.value }))}
                                        >
                                            {RATE_UNITS.map((u) => (
                                                <option key={u.value} value={u.value}>
                                                    {u.label}
                                                </option>
                                            ))}
                                        </select>
                                    </div>
                                </div>

                                {/* Pricing & GST breakdown */}
                                <div className="admin-form-row admin-form-row-3">
                                    <div className="admin-form-group">
                                        <label className="admin-form-label" htmlFor="pkg-rate">
                                            Rate (₹) <span className="admin-form-required">*</span>
                                        </label>
                                        <input
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            id="pkg-rate"
                                            className={`admin-form-input ${packageFormErrors.rate ? 'admin-form-input--error' : ''}`}
                                            placeholder="e.g. 750.00"
                                            value={packageFormData.rate}
                                            onChange={(e) => {
                                                setPackageFormData((prev) => ({ ...prev, rate: e.target.value }));
                                                if (packageFormErrors.rate) setPackageFormErrors((prev) => ({ ...prev, rate: null }));
                                            }}
                                            required
                                        />
                                        {packageFormErrors.rate && (
                                            <span className="admin-form-error">{packageFormErrors.rate}</span>
                                        )}
                                    </div>

                                    <div className="admin-form-group">
                                        <label className="admin-form-label" htmlFor="pkg-gst-rate">
                                            GST Rate (%)
                                        </label>
                                        <input
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            max="100"
                                            id="pkg-gst-rate"
                                            className="admin-form-input"
                                            placeholder="18.00"
                                            value={packageFormData.gst_rate}
                                            onChange={(e) => setPackageFormData((prev) => ({ ...prev, gst_rate: e.target.value }))}
                                        />
                                    </div>

                                    <div className="admin-form-group">
                                        <label className="admin-form-label" htmlFor="pkg-price-gst">
                                            Price Includes GST?
                                        </label>
                                        <select
                                            id="pkg-price-gst"
                                            className="admin-form-select"
                                            value={packageFormData.price_includes_gst}
                                            onChange={(e) => setPackageFormData((prev) => ({ ...prev, price_includes_gst: e.target.value }))}
                                        >
                                            <option value="1">Yes (GST Included)</option>
                                            <option value="0">No (GST Extra)</option>
                                        </select>
                                    </div>
                                </div>

                                {/* Minimum Quantity & Status */}
                                <div className="admin-form-row admin-form-row-2">
                                    <div className="admin-form-group">
                                        <label className="admin-form-label" htmlFor="pkg-min-qty">
                                            Minimum Quantity
                                        </label>
                                        <input
                                            type="number"
                                            min="1"
                                            max="100000"
                                            id="pkg-min-qty"
                                            className={`admin-form-input ${packageFormErrors.minimum_quantity ? 'admin-form-input--error' : ''}`}
                                            placeholder="1"
                                            value={packageFormData.minimum_quantity}
                                            onChange={(e) => {
                                                setPackageFormData((prev) => ({ ...prev, minimum_quantity: e.target.value }));
                                                if (packageFormErrors.minimum_quantity) setPackageFormErrors((prev) => ({ ...prev, minimum_quantity: null }));
                                            }}
                                        />
                                        {packageFormErrors.minimum_quantity && (
                                            <span className="admin-form-error">{packageFormErrors.minimum_quantity}</span>
                                        )}
                                    </div>

                                    <div className="admin-form-group">
                                        <label className="admin-form-label" htmlFor="pkg-status">
                                            Package Status
                                        </label>
                                        <select
                                            id="pkg-status"
                                            className="admin-form-select"
                                            value={packageFormData.status}
                                            onChange={(e) => setPackageFormData((prev) => ({ ...prev, status: e.target.value }))}
                                        >
                                            <option value="active">Active (Available for booking)</option>
                                            <option value="inactive">Inactive (Hidden from catalog)</option>
                                        </select>
                                    </div>
                                </div>

                                {/* Package Cover Photo Upload */}
                                <div className="admin-form-group">
                                    <label className="admin-form-label">Package Photo (Optional)</label>
                                    <div style={{ display: 'flex', alignItems: 'center', gap: '16px' }}>
                                        {packageFormData.imagePreview && !packageFormData.removeImage && (
                                            <div
                                                style={{
                                                    position: 'relative',
                                                    width: '80px',
                                                    height: '60px',
                                                    backgroundColor: '#1a1612',
                                                    borderRadius: '8px',
                                                    border: '1px solid rgba(40, 30, 20, 0.15)',
                                                    overflow: 'hidden',
                                                    display: 'flex',
                                                    alignItems: 'center',
                                                    justifyContent: 'center',
                                                    padding: '2px',
                                                }}
                                            >
                                                <img
                                                    src={packageFormData.imagePreview}
                                                    alt="Preview"
                                                    style={{
                                                        maxWidth: '100%',
                                                        maxHeight: '100%',
                                                        width: 'auto',
                                                        height: 'auto',
                                                        objectFit: 'contain',
                                                        display: 'block',
                                                    }}
                                                />
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        setPackageFormData((prev) => ({
                                                            ...prev,
                                                            imageFile: null,
                                                            imagePreview: null,
                                                            removeImage: true,
                                                        }))
                                                    }
                                                    style={{
                                                        position: 'absolute',
                                                        top: '-6px',
                                                        right: '-6px',
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
                                                    title="Remove photo"
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
                                                        removeImage: false,
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
                                            Description & Menu Highlights
                                        </label>
                                        <span className="admin-char-count">
                                            {(packageFormData.description || '').length} / 5000
                                        </span>
                                    </div>
                                    <textarea
                                        id="pkg-desc"
                                        rows={3}
                                        className={`admin-form-textarea ${packageFormErrors.description ? 'admin-form-input--error' : ''}`}
                                        placeholder="Describe dietary highlights, live counter options, and catering presentation details..."
                                        value={packageFormData.description}
                                        onChange={(e) => {
                                            setPackageFormData((prev) => ({ ...prev, description: e.target.value }));
                                            if (packageFormErrors.description) setPackageFormErrors((prev) => ({ ...prev, description: null }));
                                        }}
                                        maxLength={5000}
                                    />
                                </div>

                                {/* Included Food Items Builder */}
                                <div className="admin-form-group">
                                    <label className="admin-form-label">
                                        Included Dishes (From Food Catalog)
                                    </label>
                                    <div style={{ marginBottom: '10px' }}>
                                        <Select
                                            options={foodItemSelectOptions}
                                            onChange={handleAddItemToPackage}
                                            placeholder="Pick dishes from food catalog to add into this package..."
                                            styles={reactSelectStyles}
                                            value={null}
                                            isSearchable
                                            isLoading={isFoodItemsLoading}
                                            noOptionsMessage={() => 'No food catalog items found'}
                                        />
                                    </div>

                                    {packageFormData.items.length === 0 ? (
                                        <div
                                            style={{
                                                padding: '14px',
                                                backgroundColor: '#faf7f2',
                                                borderRadius: '10px',
                                                fontSize: '0.84rem',
                                                color: '#8a6508',
                                                textAlign: 'center',
                                                border: '1px dashed #e2d3b5',
                                            }}
                                        >
                                            No items bundled yet. Use the dropdown above to add dishes into this catering package.
                                        </div>
                                    ) : (
                                        <div
                                            style={{
                                                display: 'flex',
                                                flexDirection: 'column',
                                                gap: '8px',
                                                maxHeight: '180px',
                                                overflowY: 'auto',
                                                paddingRight: '4px',
                                            }}
                                        >
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
                                                                max={999}
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

            {/* ─── VIEW CATERING PACKAGE MODAL ─── */}
            {isViewPackageModalOpen && viewingPackage && (
                <div className="admin-modal-overlay" onClick={() => setIsViewPackageModalOpen(false)}>
                    <div
                        className="admin-modal admin-modal--view"
                        style={{ maxWidth: '640px' }}
                        onClick={(e) => e.stopPropagation()}
                        role="dialog"
                        aria-modal="true"
                    >
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
                                        {RATE_UNITS.find((u) => u.value === viewingPackage.rate_unit)?.label || viewingPackage.rate_unit}
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
                            {/* Photo if available - Shown fully with contain mode and click to expand */}
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
                                <div className="room-type-detail-item">
                                    <span className="room-type-detail-label">Rate & Pricing</span>
                                    <span className="room-type-detail-val" style={{ fontWeight: 700, color: 'var(--gold-800, #966708)' }}>
                                        {formatRateDisplay(viewingPackage.rate, viewingPackage.rate_unit)}
                                    </span>
                                </div>

                                <div className="room-type-detail-item">
                                    <span className="room-type-detail-label">GST Details</span>
                                    <span className="room-type-detail-val">
                                        {viewingPackage.gst_rate ?? 18}% ({viewingPackage.price_includes_gst == 1 || viewingPackage.price_includes_gst === true ? 'Included' : 'Extra'})
                                    </span>
                                </div>

                                <div className="room-type-detail-item">
                                    <span className="room-type-detail-label">Minimum Order Qty</span>
                                    <span className="room-type-detail-val">
                                        {viewingPackage.minimum_quantity || 1}
                                    </span>
                                </div>

                                <div className="room-type-detail-item">
                                    <span className="room-type-detail-label">Inquiry References</span>
                                    <span className="room-type-detail-val">
                                        {viewingPackage.lead_reference_count ?? 0} Inquiries
                                    </span>
                                </div>

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
                                        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(240px, 1fr))', gap: '8px', marginTop: '6px' }}>
                                            {viewingPackage.items.map((it, idx) => (
                                                <div
                                                    key={idx}
                                                    style={{
                                                        backgroundColor: '#ffffff',
                                                        borderRadius: '8px',
                                                        padding: '8px 12px',
                                                        border: '1px solid rgba(40, 30, 20, 0.08)',
                                                        fontSize: '0.84rem',
                                                        display: 'flex',
                                                        justifyContent: 'space-between',
                                                        alignItems: 'center',
                                                    }}
                                                >
                                                    <div>
                                                        <span style={{ fontWeight: '600', display: 'block' }}>
                                                            {it.food_item_name || it.name || `Dish #${it.food_item_id}`}
                                                        </span>
                                                        {(it.food_category_name || it.category_name) && (
                                                            <span style={{ fontSize: '0.74rem', color: '#64748b' }}>
                                                                {it.food_category_name || it.category_name}
                                                            </span>
                                                        )}
                                                    </div>
                                                    <span style={{ color: '#8a6508', fontWeight: '700', backgroundColor: '#faf7f2', padding: '2px 8px', borderRadius: '4px', border: '1px solid rgba(212, 160, 23, 0.2)' }}>
                                                        ×{it.quantity}
                                                    </span>
                                                </div>
                                            ))}
                                        </div>
                                    ) : (
                                        <p style={{ margin: 0, fontSize: '0.85rem', color: '#a8a29e' }}>No food items mapped into this package.</p>
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
                                Edit Package
                            </button>
                        </div>
                    </div>
                </div>
            )}

            {/* ─── DELETE CATERING PACKAGE CONFIRMATION MODAL ─── */}
            {isDeletePackageModalOpen && deletingPackage && (
                <div className="admin-modal-overlay" onClick={() => !isSubmitting && setIsDeletePackageModalOpen(false)}>
                    <div className="admin-modal admin-modal--delete" style={{ maxWidth: '460px' }} onClick={(e) => e.stopPropagation()} role="dialog" aria-modal="true">
                        <div className="admin-modal__header">
                            <div className="admin-modal__title-wrap">
                                <h3 className="admin-modal__title">Delete Catering Package</h3>
                                <p className="admin-modal__subtitle">This action cannot be undone.</p>
                            </div>
                            <button
                                type="button"
                                className="admin-modal__close-btn"
                                onClick={() => !isSubmitting && setIsDeletePackageModalOpen(false)}
                                aria-label="Close dialog"
                            >
                                ✕
                            </button>
                        </div>

                        <div className="admin-modal__body">
                            <p style={{ margin: 0, color: 'var(--text-primary)', fontSize: '0.92rem', lineHeight: 1.5 }}>
                                Are you sure you want to permanently delete the catering package{' '}
                                <strong>"{deletingPackage.name}"</strong> (ID #{deletingPackage.id})?
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

            {/* ─── VIEW CATERING LEAD MODAL ─── */}
            {isViewLeadModalOpen && viewingLead && (() => {
                const norm = normalizeLead(viewingLead);
                const statusObj = LEAD_STATUSES.find((s) => s.value === norm.status) || {
                    label: norm.status,
                    color: '#475569',
                    bg: '#f1f5f9',
                };

                return (
                    <div className="admin-modal-overlay" onClick={() => setIsViewLeadModalOpen(false)}>
                        <div className="admin-modal admin-modal--view" style={{ maxWidth: '640px' }} onClick={(e) => e.stopPropagation()} role="dialog" aria-modal="true">
                            <div className="admin-modal__header">
                                <div className="admin-modal__title-wrap">
                                    <div className="admin-modal__badge-row">
                                        <span className="room-type-id-pill">Lead #{norm.id}</span>
                                        {norm.leadReference && <span className="room-type-slug-badge">{norm.leadReference}</span>}
                                        <span
                                            style={{
                                                display: 'inline-block',
                                                padding: '3px 10px',
                                                borderRadius: '20px',
                                                fontSize: '0.78rem',
                                                fontWeight: '700',
                                                textTransform: 'capitalize',
                                                backgroundColor: statusObj.bg,
                                                color: statusObj.color,
                                            }}
                                        >
                                            {statusObj.label}
                                        </span>
                                    </div>
                                    <h3 className="admin-modal__title">{norm.customerName || 'Catering Inquiry'}</h3>
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
                                        <span className="room-type-detail-label">Customer Name</span>
                                        <span className="room-type-detail-val" style={{ fontWeight: 600 }}>{norm.customerName || '—'}</span>
                                    </div>

                                    <div className="room-type-detail-item">
                                        <span className="room-type-detail-label">Phone Number</span>
                                        <span className="room-type-detail-val">
                                            {norm.customerPhone ? <a href={`tel:${norm.customerPhone}`} style={{ color: 'inherit', textDecoration: 'none' }}>{norm.customerPhone}</a> : '—'}
                                        </span>
                                    </div>

                                    <div className="room-type-detail-item">
                                        <span className="room-type-detail-label">Email Address</span>
                                        <span className="room-type-detail-val">
                                            {norm.customerEmail ? <a href={`mailto:${norm.customerEmail}`} style={{ color: 'inherit', textDecoration: 'none' }}>{norm.customerEmail}</a> : '—'}
                                        </span>
                                    </div>

                                    <div className="room-type-detail-item">
                                        <span className="room-type-detail-label">Event Type</span>
                                        <span className="room-type-detail-val">{norm.eventType || 'Catering Event'}</span>
                                    </div>

                                    <div className="room-type-detail-item">
                                        <span className="room-type-detail-label">Event Date & Time</span>
                                        <span className="room-type-detail-val">
                                            {norm.eventDate || '—'}{norm.eventTime ? ` (${norm.eventTime})` : ''}
                                        </span>
                                    </div>

                                    <div className="room-type-detail-item">
                                        <span className="room-type-detail-label">Expected Guest Count</span>
                                        <span className="room-type-detail-val" style={{ fontWeight: 600 }}>
                                            {norm.guestCount !== null ? `${norm.guestCount} Guests` : '—'}
                                        </span>
                                    </div>

                                    <div className="room-type-detail-item">
                                        <span className="room-type-detail-label">Selected Package</span>
                                        <span className="room-type-detail-val">
                                            {norm.packageName || 'Custom Package'}
                                        </span>
                                    </div>

                                    <div className="room-type-detail-item">
                                        <span className="room-type-detail-label">Estimated Total Amount</span>
                                        <span className="room-type-detail-val" style={{ fontWeight: 700, color: 'var(--gold-800)' }}>
                                            {norm.estimatedAmount
                                                ? `₹${Number(norm.estimatedAmount).toLocaleString('en-IN')}`
                                                : '—'}
                                        </span>
                                    </div>

                                    {norm.venueAddress && (
                                        <div className="room-type-detail-item full-width">
                                            <span className="room-type-detail-label">Venue / Event Address</span>
                                            <p className="room-type-detail-text">
                                                📍 {norm.venueAddress}
                                            </p>
                                        </div>
                                    )}

                                    <div className="room-type-detail-item full-width">
                                        <span className="room-type-detail-label">Customer Message & Requirements</span>
                                        <p className="room-type-detail-text">
                                            {norm.otherRequirements || norm.requirement || 'No additional customer message provided.'}
                                        </p>
                                    </div>

                                    {norm.packageItems && norm.packageItems.length > 0 && (
                                        <div className="room-type-detail-item full-width">
                                            <span className="room-type-detail-label">Package Dishes & Menu Items</span>
                                            <div style={{ display: 'flex', flexWrap: 'wrap', gap: '6px', marginTop: '6px' }}>
                                                {norm.packageItems.map((item, idx) => (
                                                    <span
                                                        key={idx}
                                                        style={{
                                                            fontSize: '0.8rem',
                                                            padding: '4px 10px',
                                                            borderRadius: '6px',
                                                            backgroundColor: '#faf7f2',
                                                            border: '1px solid rgba(212, 160, 23, 0.25)',
                                                            color: '#1a1612',
                                                            fontWeight: '500',
                                                        }}
                                                    >
                                                        🍽️ {item.food_item_name || item.name || `Item #${item.food_item_id}`} {item.quantity ? `(x${item.quantity})` : ''}
                                                    </span>
                                                ))}
                                            </div>
                                        </div>
                                    )}

                                    {norm.customFoodItems && norm.customFoodItems.length > 0 && (
                                        <div className="room-type-detail-item full-width">
                                            <span className="room-type-detail-label">Custom Requested Food Items</span>
                                            <div style={{ display: 'flex', flexWrap: 'wrap', gap: '6px', marginTop: '6px' }}>
                                                {norm.customFoodItems.map((item, idx) => (
                                                    <span
                                                        key={idx}
                                                        style={{
                                                            fontSize: '0.8rem',
                                                            padding: '4px 10px',
                                                            borderRadius: '6px',
                                                            backgroundColor: '#ecfdf5',
                                                            border: '1px solid rgba(5, 150, 105, 0.25)',
                                                            color: '#065f46',
                                                            fontWeight: '500',
                                                        }}
                                                    >
                                                        ✨ {item.food_item_name || item.name || `Custom Item #${item.food_item_id}`} {item.quantity ? `(x${item.quantity})` : ''} {item.notes ? `[${item.notes}]` : ''}
                                                    </span>
                                                ))}
                                            </div>
                                        </div>
                                    )}

                                    <div className="room-type-detail-item">
                                        <span className="room-type-detail-label">Inquiry Date</span>
                                        <span className="room-type-detail-val text-muted">
                                            {norm.createdAt ? new Date(norm.createdAt).toLocaleString() : '—'}
                                        </span>
                                    </div>

                                    <div className="room-type-detail-item">
                                        <span className="room-type-detail-label">Last Updated</span>
                                        <span className="room-type-detail-val text-muted">
                                            {norm.updatedAt ? new Date(norm.updatedAt).toLocaleString() : '—'}
                                        </span>
                                    </div>
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
                                            id: norm.id,
                                            status: norm.status || 'new',
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
                                        {LEAD_STATUSES.map((st) => (
                                            <option key={st.value} value={st.value}>
                                                {st.label}
                                            </option>
                                        ))}
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
                                        placeholder="Enter internal communication notes, quotation discussions, or customer decisions..."
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

export default CateringModule;
