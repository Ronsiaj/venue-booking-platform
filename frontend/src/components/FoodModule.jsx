import React, { useState, useMemo, useRef, useEffect, useCallback } from 'react';
import Select from 'react-select';
import Pagination from './Pagination';
import api from '../services/api';
import serviceRestaurant from '../assets/images/service-restaurant.jpg';
import serviceCatering from '../assets/images/service-catering.jpg';

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
 * Standard Food Type Options supported by backend
 */
export const FOOD_TYPES = [
    { value: 'veg', label: 'Vegetarian', icon: '🌱', color: '#16a34a', bg: 'rgba(22, 163, 74, 0.1)' },
    { value: 'non_veg', label: 'Non-Vegetarian', icon: '🍗', color: '#dc2626', bg: 'rgba(220, 38, 38, 0.1)' },
    { value: 'egg', label: 'Contains Egg', icon: '🥚', color: '#d97706', bg: 'rgba(217, 119, 6, 0.1)' },
    { value: 'vegan', label: 'Vegan', icon: '🥗', color: '#059669', bg: 'rgba(5, 150, 105, 0.1)' },
    { value: 'other', label: 'Other / Specialty', icon: '🍽️', color: '#64748b', bg: 'rgba(100, 116, 139, 0.1)' },
];

export const FOOD_TYPE_FILTER_OPTIONS = [
    { value: 'all', label: 'All Types' },
    { value: 'veg', label: '🌱 Veg' },
    { value: 'non_veg', label: '🍗 Non-Veg' },
];

export const FOOD_STATUS_FILTER_OPTIONS = [
    { value: 'all', label: 'All Status' },
    { value: 'active', label: 'Active' },
    { value: 'inactive', label: 'Inactive' },
];

/**
 * Meal Type Options for Room Food Packages
 */
export const MEAL_TYPES = [
    { value: 'breakfast', label: 'Breakfast Package', icon: '🍳', color: '#d97706', bg: 'rgba(217, 119, 6, 0.1)' },
    { value: 'lunch', label: 'Lunch Package', icon: '🍛', color: '#ea580c', bg: 'rgba(234, 88, 12, 0.1)' },
    { value: 'dinner', label: 'Dinner Package', icon: '🍲', color: '#7c3aed', bg: 'rgba(124, 58, 237, 0.1)' },
    { value: 'combo', label: 'All-Day Combo', icon: '🍱', color: '#059669', bg: 'rgba(5, 150, 105, 0.1)' },
    { value: 'other', label: 'Custom / Other', icon: '🍽️', color: '#475569', bg: 'rgba(71, 85, 105, 0.1)' },
];

/**
 * Initial Fallback Categories (maps to backend food_categories)
 */
const INITIAL_CATEGORIES = [
    { id: 1, name: 'Starters & Appetizers' },
    { id: 2, name: 'Main Course' },
    { id: 3, name: 'Biryani & Rice' },
    { id: 4, name: 'Tandoori & Breads' },
    { id: 5, name: 'Desserts & Sweets' },
    { id: 6, name: 'Beverages & Drinks' },
];

/**
 * Initial Fallback Food Items (maps to backend food_items)
 */
const INITIAL_FOOD_ITEMS = [
    {
        id: 1,
        food_category_id: 1,
        category_name: 'Starters & Appetizers',
        name: 'Paneer Tikka Angaara',
        description: 'Cottage cheese cubes marinated in fiery tandoori spices and charred to perfection in clay oven.',
        food_type: 'veg',
        image_path: null,
        status: 'active',
        created_at: '2026-09-01 10:00:00',
        updated_at: '2026-09-01 10:00:00',
    },
    {
        id: 2,
        food_category_id: 3,
        category_name: 'Biryani & Rice',
        name: 'Royal Chicken Dum Biryani',
        description: 'Long-grain aged basmati rice cooked on slow charcoal dum with tender chicken and aromatic whole spices.',
        food_type: 'non_veg',
        image_path: null,
        status: 'active',
        created_at: '2026-09-01 10:15:00',
        updated_at: '2026-09-15 14:00:00',
    },
    {
        id: 3,
        food_category_id: 2,
        category_name: 'Main Course',
        name: 'Dal Makhani Bukhara',
        description: 'Slow-simmered whole black lentils cooked overnight with churned butter and rich dairy cream.',
        food_type: 'veg',
        image_path: null,
        status: 'active',
        created_at: '2026-09-02 11:00:00',
        updated_at: '2026-09-20 09:30:00',
    },
    {
        id: 4,
        food_category_id: 2,
        category_name: 'Main Course',
        name: 'Chettinad Egg Masala Curry',
        description: 'Boiled eggs simmered in authentic roasted spices, poppy seeds, and fresh coconut curry.',
        food_type: 'egg',
        image_path: null,
        status: 'active',
        created_at: '2026-09-03 12:20:00',
        updated_at: '2026-09-03 12:20:00',
    },
    {
        id: 5,
        food_category_id: 5,
        category_name: 'Desserts & Sweets',
        name: 'Elaneer Payasam (Tender Coconut)',
        description: 'Traditional coastal dessert made from tender coconut pulp, coconut milk, cardamom, and jaggery.',
        food_type: 'vegan',
        image_path: null,
        status: 'active',
        created_at: '2026-09-04 15:00:00',
        updated_at: '2026-09-25 18:00:00',
    },
    {
        id: 6,
        food_category_id: 6,
        category_name: 'Beverages & Drinks',
        name: 'South Indian Filter Kaapi',
        description: 'Freshly brewed chicory blend decoction frothed with boiling farm milk in traditional brass dabara.',
        food_type: 'veg',
        image_path: null,
        status: 'inactive',
        created_at: '2026-09-05 16:30:00',
        updated_at: '2026-09-26 11:00:00',
    }
];

/**
 * Initial Fallback Room Food Packages (maps to backend room_food_packages)
 */
const INITIAL_ROOM_PACKAGES = [
    {
        id: 1,
        name: 'Executive Lunch Combo',
        description: 'Complete wholesome lunch meal package for guest suite reservations.',
        meal_type: 'lunch',
        status: 'active',
        total_items: 2,
        items: [
            { food_item_id: 1, food_item_name: 'Paneer Tikka Angaara', food_type: 'veg', quantity: 2 },
            { food_item_id: 2, food_item_name: 'Royal Chicken Dum Biryani', food_type: 'non_veg', quantity: 1 }
        ],
        created_at: '2026-09-21 13:55:25',
        updated_at: '2026-09-21 13:55:25'
    },
    {
        id: 2,
        name: 'Deluxe Breakfast Set',
        description: 'Nutritious South Indian breakfast platter with hot brew beverage.',
        meal_type: 'breakfast',
        status: 'active',
        total_items: 1,
        items: [
            { food_item_id: 6, food_item_name: 'South Indian Filter Kaapi', food_type: 'veg', quantity: 2 }
        ],
        created_at: '2026-09-22 09:00:00',
        updated_at: '2026-09-22 09:00:00'
    }
];

/**
 * Main Food & Dining Admin Module
 */
const FoodModule = ({ onNavigate, initialTab }) => {
    // Top-level Navigation: 'dishes' | 'packages'
    const [activeTab, setActiveTab] = useState(() => {
        if (initialTab === 'packages' || initialTab === 'dishes') return initialTab;
        const params = new URLSearchParams(window.location.search);
        return params.get('tab') === 'packages' ? 'packages' : 'dishes';
    });

    useEffect(() => {
        if (initialTab && (initialTab === 'packages' || initialTab === 'dishes')) {
            setActiveTab(initialTab);
        } else {
            const params = new URLSearchParams(window.location.search);
            const tab = params.get('tab');
            if (tab === 'packages' || tab === 'dishes') {
                setActiveTab(tab);
            }
        }
    }, [initialTab]);

    // ─────────────────────────────────────────────────────────────
    // DISHES STATE (Tab 1)
    // ─────────────────────────────────────────────────────────────
    const [foodItems, setFoodItems] = useState(INITIAL_FOOD_ITEMS);
    const [categories, setCategories] = useState(INITIAL_CATEGORIES);

    const [searchQuery, setSearchQuery] = useState('');
    const [selectedCategoryFilter, setSelectedCategoryFilter] = useState('all');
    const [selectedFoodTypeFilter, setSelectedFoodTypeFilter] = useState('all');
    const [selectedStatusFilter, setSelectedStatusFilter] = useState('all');

    const [currentPage, setCurrentPage] = useState(1);
    const [pageSize, setPageSize] = useState(5);
    const [totalRecords, setTotalRecords] = useState(INITIAL_FOOD_ITEMS.length);
    const [isLoadingDishes, setIsLoadingDishes] = useState(false);

    // Dishes Modals
    const [isFormModalOpen, setIsFormModalOpen] = useState(false);
    const [formMode, setFormMode] = useState('create');
    const [editingItem, setEditingItem] = useState(null);

    const [isViewModalOpen, setIsViewModalOpen] = useState(false);
    const [viewingItem, setViewingItem] = useState(null);

    const [isDeleteModalOpen, setIsDeleteModalOpen] = useState(false);
    const [deletingItem, setDeletingItem] = useState(null);

    const [formData, setFormData] = useState({
        name: '',
        food_category_id: '',
        food_type: 'veg',
        status: 'active',
        description: '',
        imageFile: null,
        imagePreview: null,
    });
    const [formErrors, setFormErrors] = useState({});
    const fileInputRef = useRef(null);

    // ─────────────────────────────────────────────────────────────
    // ROOM PACKAGES STATE (Tab 2)
    // ─────────────────────────────────────────────────────────────
    const [roomPackages, setRoomPackages] = useState(INITIAL_ROOM_PACKAGES);
    const [packageSearchQuery, setPackageSearchQuery] = useState('');
    const [packageMealTypeFilter, setPackageMealTypeFilter] = useState('all');
    const [packageStatusFilter, setPackageStatusFilter] = useState('all');

    const [packageCurrentPage, setPackageCurrentPage] = useState(1);
    const [packagePageSize, setPackagePageSize] = useState(5);
    const [packageTotalRecords, setPackageTotalRecords] = useState(INITIAL_ROOM_PACKAGES.length);
    const [isLoadingPackages, setIsLoadingPackages] = useState(false);

    // Packages Modals
    const [isPackageFormModalOpen, setIsPackageFormModalOpen] = useState(false);
    const [packageFormMode, setPackageFormMode] = useState('create');
    const [editingPackage, setEditingPackage] = useState(null);

    const [isPackageViewModalOpen, setIsPackageViewModalOpen] = useState(false);
    const [viewingPackage, setViewingPackage] = useState(null);

    const [isPackageDeleteModalOpen, setIsPackageDeleteModalOpen] = useState(false);
    const [deletingPackage, setDeletingPackage] = useState(null);

    const [packageFormData, setPackageFormData] = useState({
        name: '',
        description: '',
        meal_type: 'breakfast',
        status: 'active',
        items: [], // Array of { food_item_id, quantity, food_item_name, food_type }
    });
    const [packageFormErrors, setPackageFormErrors] = useState({});
    const [selectedDishToAdd, setSelectedDishToAdd] = useState('');

    // Shared Toast state
    const [toastMessage, setToastMessage] = useState(null);
    const showToast = (msg, type = 'success') => {
        setToastMessage({ text: msg, type });
        setTimeout(() => setToastMessage(null), 3500);
    };

    // Helper to resolve food image URL
    const resolveFoodImage = (item) => {
        if (!item) return serviceRestaurant;
        if (item.image_preview) return item.image_preview;
        if (item.image_path) {
            if (item.image_path.startsWith('http')) return item.image_path;
            return `http://localhost/sm_groups/backend/${item.image_path.replace(/^\/+/, '')}`;
        }
        return item.food_type === 'non_veg' || item.food_type === 'egg' ? serviceCatering : serviceRestaurant;
    };

    const getCategoryName = (catId, item = null) => {
        if (item?.category_name) return item.category_name;
        const found = categories.find((c) => c.id === Number(catId));
        return found ? found.name : 'Uncategorized';
    };

    const renderFoodTypeBadge = (type) => {
        const found = FOOD_TYPES.find((t) => t.value === type) || FOOD_TYPES[0];
        return (
            <span
                className="food-type-pill"
                style={{
                    backgroundColor: found.bg,
                    color: found.color,
                    borderColor: found.color,
                }}
            >
                <span className="food-type-pill__icon">{found.icon}</span>
                <span className="food-type-pill__label">{found.label}</span>
            </span>
        );
    };

    const renderMealTypeBadge = (mealType) => {
        const found = MEAL_TYPES.find((t) => t.value === mealType) || MEAL_TYPES[0];
        return (
            <span
                className="food-type-pill"
                style={{
                    backgroundColor: found.bg,
                    color: found.color,
                    borderColor: found.color,
                }}
            >
                <span className="food-type-pill__icon">{found.icon}</span>
                <span className="food-type-pill__label">{found.label}</span>
            </span>
        );
    };

    // ─────────────────────────────────────────────────────────────
    // BACKEND API CALLS: CATEGORIES & DISHES
    // ─────────────────────────────────────────────────────────────
    useEffect(() => {
        const fetchCategoriesList = async () => {
            try {
                const res = await api.get('/food/categories/list.php?limit=100');
                if (res.success && res.data?.status && res.data?.data?.categories) {
                    setCategories(res.data.data.categories);
                } else if (res.data?.data?.categories) {
                    setCategories(res.data.data.categories);
                }
            } catch (err) {
                console.warn('Backend food categories note:', err.message);
            }
        };
        fetchCategoriesList();
    }, []);

    const fetchFoodItems = useCallback(
        async (
            page = currentPage,
            limit = pageSize,
            search = searchQuery,
            categoryId = selectedCategoryFilter,
            type = selectedFoodTypeFilter,
            status = selectedStatusFilter
        ) => {
            setIsLoadingDishes(true);
            try {
                const params = new URLSearchParams();
                params.append('page', String(page));
                params.append('limit', String(limit));
                if (search && search.trim()) params.append('search', search.trim());
                if (categoryId && categoryId !== 'all') params.append('food_category_id', String(categoryId));
                if (type && type !== 'all') params.append('food_type', type);
                if (status && status !== 'all') params.append('status', status);

                const res = await api.get(`/food/item/list.php?${params.toString()}`);
                if (res.success && res.data?.status && res.data?.data) {
                    const items = res.data.data.food_items || [];
                    setFoodItems(items);
                    if (res.data.data.pagination) {
                        setTotalRecords(res.data.data.pagination.total_records ?? items.length);
                    }
                } else if (res.data?.data?.food_items) {
                    setFoodItems(res.data.data.food_items);
                    if (res.data.data.pagination) {
                        setTotalRecords(res.data.data.pagination.total_records ?? res.data.data.food_items.length);
                    }
                }
            } catch (err) {
                console.warn('Backend food items fetch note:', err.message);
            } finally {
                setIsLoadingDishes(false);
            }
        },
        [currentPage, pageSize, searchQuery, selectedCategoryFilter, selectedFoodTypeFilter, selectedStatusFilter]
    );

    useEffect(() => {
        fetchFoodItems(
            currentPage,
            pageSize,
            searchQuery,
            selectedCategoryFilter,
            selectedFoodTypeFilter,
            selectedStatusFilter
        );
    }, [
        fetchFoodItems,
        currentPage,
        pageSize,
        searchQuery,
        selectedCategoryFilter,
        selectedFoodTypeFilter,
        selectedStatusFilter
    ]);

    // ─────────────────────────────────────────────────────────────
    // BACKEND API CALLS: ROOM FOOD PACKAGES
    // ─────────────────────────────────────────────────────────────
    const fetchRoomPackages = useCallback(
        async (
            page = packageCurrentPage,
            limit = packagePageSize,
            search = packageSearchQuery,
            mealType = packageMealTypeFilter,
            status = packageStatusFilter
        ) => {
            setIsLoadingPackages(true);
            try {
                const params = new URLSearchParams();
                params.append('page', String(page));
                params.append('limit', String(limit));
                if (search && search.trim()) params.append('search', search.trim());
                if (mealType && mealType !== 'all') params.append('meal_type', mealType);
                if (status && status !== 'all') params.append('status', status);

                const res = await api.get(`/food/room_package/list.php?${params.toString()}`);
                if (res.success && res.data?.status && Array.isArray(res.data.data)) {
                    setRoomPackages(res.data.data);
                    if (res.data.pagination) {
                        setPackageTotalRecords(res.data.pagination.total_records ?? res.data.data.length);
                    }
                } else if (res.data?.data && Array.isArray(res.data.data)) {
                    setRoomPackages(res.data.data);
                    if (res.data.pagination) {
                        setPackageTotalRecords(res.data.pagination.total_records ?? res.data.data.length);
                    }
                }
            } catch (err) {
                console.warn('Backend room packages fetch note:', err.message);
            } finally {
                setIsLoadingPackages(false);
            }
        },
        [packageCurrentPage, packagePageSize, packageSearchQuery, packageMealTypeFilter, packageStatusFilter]
    );

    useEffect(() => {
        if (activeTab === 'packages') {
            fetchRoomPackages(
                packageCurrentPage,
                packagePageSize,
                packageSearchQuery,
                packageMealTypeFilter,
                packageStatusFilter
            );
        }
    }, [
        fetchRoomPackages,
        activeTab,
        packageCurrentPage,
        packagePageSize,
        packageSearchQuery,
        packageMealTypeFilter,
        packageStatusFilter
    ]);

    // ─────────────────────────────────────────────────────────────
    // STATS & SEARCH OPTIONS
    // ─────────────────────────────────────────────────────────────
    const dishStats = useMemo(() => {
        const total = totalRecords || foodItems.length;
        const veg = foodItems.filter((f) => f.food_type === 'veg' || f.food_type === 'vegan').length;
        const nonVeg = foodItems.filter((f) => f.food_type === 'non_veg' || f.food_type === 'egg').length;
        const inactive = foodItems.filter((f) => f.status === 'inactive').length;
        return { total, veg, nonVeg, inactive };
    }, [foodItems, totalRecords]);

    const packageStats = useMemo(() => {
        const total = packageTotalRecords || roomPackages.length;
        const breakfast = roomPackages.filter((p) => p.meal_type === 'breakfast' || p.meal_type === 'combo').length;
        const lunchDinner = roomPackages.filter((p) => p.meal_type === 'lunch' || p.meal_type === 'dinner').length;
        const inactive = roomPackages.filter((p) => p.status === 'inactive').length;
        return { total, breakfast, lunchDinner, inactive };
    }, [roomPackages, packageTotalRecords]);

    const dishSearchSelectOptions = useMemo(() => {
        return foodItems.map((item) => ({
            value: item.name,
            label: `${item.name} (${getCategoryName(item.food_category_id, item)})`,
        }));
    }, [foodItems, categories]);

    const packageSearchSelectOptions = useMemo(() => {
        return roomPackages.map((pkg) => ({
            value: pkg.name,
            label: `${pkg.name} (${pkg.meal_type})`,
        }));
    }, [roomPackages]);

    const dishPickerOptions = useMemo(() => {
        return foodItems.map((dish) => {
            const foundType = FOOD_TYPES.find((t) => t.value === dish.food_type);
            const typeLabel = foundType ? `${foundType.icon} ${foundType.label}` : dish.food_type;
            return {
                value: String(dish.id),
                label: `${dish.name} (${typeLabel})`,
            };
        });
    }, [foodItems]);

    // ─────────────────────────────────────────────────────────────
    // DISH FORM HANDLERS
    // ─────────────────────────────────────────────────────────────
    const validateDishForm = () => {
        const errors = {};
        const trimmed = formData.name.trim();
        if (!trimmed) errors.name = 'Food item name is required.';
        else if (trimmed.length < 2) errors.name = 'Name must be at least 2 characters.';
        else if (trimmed.length > 100) errors.name = 'Name cannot exceed 100 characters.';

        if (formData.description && formData.description.length > 1000) {
            errors.description = 'Description cannot exceed 1000 characters.';
        }
        setFormErrors(errors);
        return Object.keys(errors).length === 0;
    };

    const handleOpenAddDish = () => {
        setFormMode('create');
        setEditingItem(null);
        setFormData({
            name: '',
            food_category_id: categories[0]?.id || '',
            food_type: 'veg',
            status: 'active',
            description: '',
            imageFile: null,
            imagePreview: null,
        });
        setFormErrors({});
        setIsFormModalOpen(true);
    };

    const handleOpenEditDish = (item) => {
        setFormMode('edit');
        setEditingItem(item);
        setFormData({
            name: item.name,
            food_category_id: item.food_category_id || '',
            food_type: item.food_type || 'veg',
            status: item.status || 'active',
            description: item.description || '',
            imageFile: null,
            imagePreview: resolveFoodImage(item),
        });
        setFormErrors({});
        setIsFormModalOpen(true);
    };

    const handleDishFormSubmit = async (e) => {
        e.preventDefault();
        if (!validateDishForm()) return;

        const trimmed = formData.name.trim();
        const postData = new FormData();
        postData.append('name', trimmed);
        if (formData.food_category_id) postData.append('food_category_id', String(formData.food_category_id));
        postData.append('food_type', formData.food_type);
        postData.append('status', formData.status);
        if (formData.description) postData.append('description', formData.description.trim());
        if (formData.imageFile) postData.append('image', formData.imageFile);

        if (formMode === 'create') {
            try {
                const res = await api.post('/food/item/create.php', postData);
                if (res.success && res.data?.status) {
                    showToast(`Food dish "${trimmed}" created successfully.`);
                    fetchFoodItems();
                } else {
                    const now = new Date().toISOString().slice(0, 19).replace('T', ' ');
                    const newItem = {
                        id: Date.now(),
                        name: trimmed,
                        food_category_id: formData.food_category_id ? Number(formData.food_category_id) : null,
                        category_name: getCategoryName(formData.food_category_id),
                        food_type: formData.food_type,
                        status: formData.status,
                        description: formData.description.trim(),
                        image_path: null,
                        image_preview: formData.imagePreview,
                        created_at: now,
                        updated_at: now,
                    };
                    setFoodItems((prev) => [newItem, ...prev]);
                    setTotalRecords((prev) => prev + 1);
                    showToast(`Food dish "${trimmed}" added.`);
                }
            } catch {
                showToast(`Food dish "${trimmed}" added.`);
            }
        } else {
            postData.append('food_item_id', String(editingItem.id));
            try {
                const res = await api.post('/food/item/update.php', postData);
                if (res.success && res.data?.status) {
                    showToast(`Food dish "${trimmed}" updated successfully.`);
                    fetchFoodItems();
                } else {
                    const now = new Date().toISOString().slice(0, 19).replace('T', ' ');
                    setFoodItems((prev) =>
                        prev.map((item) =>
                            item.id === editingItem.id
                                ? {
                                    ...item,
                                    name: trimmed,
                                    food_category_id: formData.food_category_id ? Number(formData.food_category_id) : null,
                                    category_name: getCategoryName(formData.food_category_id),
                                    food_type: formData.food_type,
                                    status: formData.status,
                                    description: formData.description.trim(),
                                    image_preview: formData.imagePreview || item.image_preview,
                                    updated_at: now,
                                }
                                : item
                        )
                    );
                    showToast(`Food dish "${trimmed}" updated.`);
                }
            } catch {
                showToast(`Food dish "${trimmed}" updated.`);
            }
        }

        setIsFormModalOpen(false);
    };

    const handleConfirmDeleteDish = async () => {
        if (!deletingItem) return;
        try {
            const res = await api.post('/food/item/delete.php', { food_item_id: deletingItem.id });
            if (res.success && res.data?.status) {
                showToast(`Food dish "${deletingItem.name}" deleted successfully.`);
                fetchFoodItems();
            } else {
                setFoodItems((prev) => prev.filter((item) => item.id !== deletingItem.id));
                setTotalRecords((prev) => Math.max(0, prev - 1));
                showToast(`Food dish "${deletingItem.name}" deleted.`);
            }
        } catch {
            setFoodItems((prev) => prev.filter((item) => item.id !== deletingItem.id));
            setTotalRecords((prev) => Math.max(0, prev - 1));
            showToast(`Food dish "${deletingItem.name}" deleted.`);
        } finally {
            setIsDeleteModalOpen(false);
            setDeletingItem(null);
        }
    };

    // ─────────────────────────────────────────────────────────────
    // ROOM PACKAGE FORM HANDLERS (Customization: Dishes & Quantities)
    // ─────────────────────────────────────────────────────────────
    const validatePackageForm = () => {
        const errors = {};
        const trimmed = packageFormData.name.trim();
        if (!trimmed) errors.name = 'Package name is required.';
        else if (trimmed.length < 2) errors.name = 'Name must be at least 2 characters.';
        else if (trimmed.length > 100) errors.name = 'Name cannot exceed 100 characters.';

        if (packageFormData.description && packageFormData.description.length > 1000) {
            errors.description = 'Description cannot exceed 1000 characters.';
        }
        setPackageFormErrors(errors);
        return Object.keys(errors).length === 0;
    };

    const handleOpenAddPackage = () => {
        setPackageFormMode('create');
        setEditingPackage(null);
        setPackageFormData({
            name: '',
            description: '',
            meal_type: 'breakfast',
            status: 'active',
            items: [],
        });
        setSelectedDishToAdd('');
        setPackageFormErrors({});
        setIsPackageFormModalOpen(true);
    };

    const handleOpenEditPackage = (pkg) => {
        setPackageFormMode('edit');
        setEditingPackage(pkg);
        setPackageFormData({
            name: pkg.name,
            description: pkg.description || '',
            meal_type: pkg.meal_type || 'breakfast',
            status: pkg.status || 'active',
            items: (pkg.items || []).map((it) => ({
                food_item_id: it.food_item_id,
                food_item_name: it.food_item_name || it.name,
                food_type: it.food_type || 'veg',
                quantity: it.quantity || 1,
            })),
        });
        setSelectedDishToAdd('');
        setPackageFormErrors({});
        setIsPackageFormModalOpen(true);
    };

    // Add selected food item to package bundle
    const handleAddDishToPackage = () => {
        if (!selectedDishToAdd) return;
        const foundDish = foodItems.find((f) => f.id === Number(selectedDishToAdd));
        if (!foundDish) return;

        setPackageFormData((prev) => {
            const exists = prev.items.find((it) => it.food_item_id === foundDish.id);
            if (exists) {
                return {
                    ...prev,
                    items: prev.items.map((it) =>
                        it.food_item_id === foundDish.id ? { ...it, quantity: it.quantity + 1 } : it
                    ),
                };
            }
            return {
                ...prev,
                items: [
                    ...prev.items,
                    {
                        food_item_id: foundDish.id,
                        food_item_name: foundDish.name,
                        food_type: foundDish.food_type,
                        quantity: 1,
                    },
                ],
            };
        });
        setSelectedDishToAdd('');
    };

    // Adjust item quantity in package
    const handlePackageItemQuantityChange = (dishId, newQty) => {
        if (newQty <= 0) {
            handleRemovePackageItem(dishId);
            return;
        }
        setPackageFormData((prev) => ({
            ...prev,
            items: prev.items.map((it) => (it.food_item_id === dishId ? { ...it, quantity: newQty } : it)),
        }));
    };

    // Remove item from package
    const handleRemovePackageItem = (dishId) => {
        setPackageFormData((prev) => ({
            ...prev,
            items: prev.items.filter((it) => it.food_item_id !== dishId),
        }));
    };

    // Submit Room Package (Create / Update)
    const handlePackageFormSubmit = async (e) => {
        e.preventDefault();
        if (!validatePackageForm()) return;

        const trimmed = packageFormData.name.trim();
        const payload = {
            name: trimmed,
            description: packageFormData.description.trim(),
            meal_type: packageFormData.meal_type,
            status: packageFormData.status,
            items: packageFormData.items.map((it) => ({
                food_item_id: Number(it.food_item_id),
                quantity: Number(it.quantity) || 1,
            })),
        };

        if (packageFormMode === 'create') {
            try {
                const res = await api.post('/food/room_package/create.php', payload);
                if (res.success && res.data?.status) {
                    showToast(`Room package "${trimmed}" created successfully.`);
                    fetchRoomPackages();
                } else {
                    const now = new Date().toISOString().slice(0, 19).replace('T', ' ');
                    const newPkg = {
                        id: Date.now(),
                        name: trimmed,
                        description: packageFormData.description.trim(),
                        meal_type: packageFormData.meal_type,
                        status: packageFormData.status,
                        total_items: packageFormData.items.length,
                        items: [...packageFormData.items],
                        created_at: now,
                        updated_at: now,
                    };
                    setRoomPackages((prev) => [newPkg, ...prev]);
                    setPackageTotalRecords((prev) => prev + 1);
                    showToast(`Room package "${trimmed}" added.`);
                }
            } catch {
                showToast(`Room package "${trimmed}" added.`);
            }
        } else {
            payload.package_id = editingPackage.id;
            try {
                const res = await api.post('/food/room_package/update.php', payload);
                if (res.success && res.data?.status) {
                    showToast(`Room package "${trimmed}" updated successfully.`);
                    fetchRoomPackages();
                } else {
                    const now = new Date().toISOString().slice(0, 19).replace('T', ' ');
                    setRoomPackages((prev) =>
                        prev.map((pkg) =>
                            pkg.id === editingPackage.id
                                ? {
                                    ...pkg,
                                    name: trimmed,
                                    description: packageFormData.description.trim(),
                                    meal_type: packageFormData.meal_type,
                                    status: packageFormData.status,
                                    total_items: packageFormData.items.length,
                                    items: [...packageFormData.items],
                                    updated_at: now,
                                }
                                : pkg
                        )
                    );
                    showToast(`Room package "${trimmed}" updated.`);
                }
            } catch {
                showToast(`Room package "${trimmed}" updated.`);
            }
        }

        setIsPackageFormModalOpen(false);
    };

    const handleConfirmDeletePackage = async () => {
        if (!deletingPackage) return;
        try {
            const res = await api.post('/food/room_package/delete.php', { package_id: deletingPackage.id });
            if (res.success && res.data?.status) {
                showToast(`Package "${deletingPackage.name}" deleted successfully.`);
                fetchRoomPackages();
            } else {
                setRoomPackages((prev) => prev.filter((p) => p.id !== deletingPackage.id));
                setPackageTotalRecords((prev) => Math.max(0, prev - 1));
                showToast(`Package "${deletingPackage.name}" deleted.`);
            }
        } catch {
            setRoomPackages((prev) => prev.filter((p) => p.id !== deletingPackage.id));
            setPackageTotalRecords((prev) => Math.max(0, prev - 1));
            showToast(`Package "${deletingPackage.name}" deleted.`);
        } finally {
            setIsPackageDeleteModalOpen(false);
            setDeletingPackage(null);
        }
    };

    return (
        <div className="food-module rooms-module">
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
                    <h2 className="dashboard-page-header__title">Food & Catering Management</h2>
                    <p className="dashboard-page-header__desc">
                        Manage individual food dishes, dietary types, and composed Room Dining Food Packages for guest suite reservations.
                    </p>
                </div>
                <div className="dashboard-page-header__actions">
                    {activeTab === 'dishes' ? (
                        <button
                            type="button"
                            className="admin-primary-btn"
                            onClick={handleOpenAddDish}
                            id="add-food-item-btn"
                        >
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round">
                                <line x1="12" y1="5" x2="12" y2="19" />
                                <line x1="5" y1="12" x2="19" y2="12" />
                            </svg>
                            <span>Add New Food Dish</span>
                        </button>
                    ) : (
                        <button
                            type="button"
                            className="admin-primary-btn"
                            onClick={handleOpenAddPackage}
                            id="add-room-package-btn"
                        >
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round">
                                <line x1="12" y1="5" x2="12" y2="19" />
                                <line x1="5" y1="12" x2="19" y2="12" />
                            </svg>
                            <span>Create Room Package</span>
                        </button>
                    )}
                </div>
            </div>

            {/* ═════════════════════════════════════════════════════════════ */}
            {/* TAB 1: FOOD MENU DISHES */}
            {/* ═════════════════════════════════════════════════════════════ */}
            {activeTab === 'dishes' && (
                <>
                    {/* Stats Overview Grid */}
                    <div className="room-types-stats-grid">
                        <div className="room-type-stat-card">
                            <div className="room-type-stat-card__icon room-type-stat-card__icon--gold">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                    <path d="M18 8h1a4 4 0 0 1 0 8h-1" />
                                    <path d="M2 8h16v9a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4V8z" />
                                    <line x1="6" y1="2" x2="6" y2="4" />
                                    <line x1="10" y1="2" x2="10" y2="4" />
                                    <line x1="14" y1="2" x2="14" y2="4" />
                                </svg>
                            </div>
                            <div className="room-type-stat-card__info">
                                <span className="room-type-stat-card__label">Total Dishes</span>
                                <span className="room-type-stat-card__value">{dishStats.total}</span>
                            </div>
                        </div>

                        <div className="room-type-stat-card">
                            <div className="room-type-stat-card__icon room-type-stat-card__icon--green">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                    <path d="M12 2v20M2 12h20M4.93 4.93l14.14 14.14M4.93 19.07l14.14-14.14" />
                                </svg>
                            </div>
                            <div className="room-type-stat-card__info">
                                <span className="room-type-stat-card__label">Veg / Vegan</span>
                                <span className="room-type-stat-card__value">{dishStats.veg}</span>
                            </div>
                        </div>

                        <div className="room-type-stat-card">
                            <div className="room-type-stat-card__icon room-type-stat-card__icon--rose">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                    <circle cx="12" cy="12" r="10" />
                                    <path d="M12 8v8" />
                                    <path d="M8 12h8" />
                                </svg>
                            </div>
                            <div className="room-type-stat-card__info">
                                <span className="room-type-stat-card__label">Non-Veg / Egg</span>
                                <span className="room-type-stat-card__value">{dishStats.nonVeg}</span>
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
                                <span className="room-type-stat-card__value">{dishStats.inactive}</span>
                            </div>
                        </div>
                    </div>

                    {/* Filter & Search Toolbar */}
                    <div className="room-types-toolbar" style={{ flexWrap: 'wrap', gap: '10px', justifyContent: 'flex-start' }}>
                        <div className="room-types-search-select-wrap" style={{ minWidth: '240px', maxWidth: '320px', flex: '1 1 240px' }}>
                            <Select
                                options={dishSearchSelectOptions}
                                value={
                                    searchQuery
                                        ? dishSearchSelectOptions.find((o) => o.value.toLowerCase() === searchQuery.toLowerCase()) || {
                                            value: searchQuery,
                                            label: searchQuery,
                                        }
                                        : null
                                }
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
                                placeholder="Search or select food dish..."
                                isClearable
                                isSearchable
                                styles={reactSelectStyles}
                                className="react-select-container"
                                classNamePrefix="react-select"
                                noOptionsMessage={({ inputValue }) =>
                                    inputValue ? `No food dishes match "${inputValue}"` : 'No options'
                                }
                            />
                        </div>

                        <div style={{ display: 'flex', alignItems: 'center', gap: '8px', flexWrap: 'wrap' }}>
                            {/* Category filter with React Select */}
                            <div style={{ minWidth: '180px' }}>
                                <Select
                                    options={[
                                        { value: 'all', label: 'All Categories' },
                                        ...categories.map((cat) => ({ value: String(cat.id), label: cat.name })),
                                    ]}
                                    value={
                                        selectedCategoryFilter === 'all'
                                            ? { value: 'all', label: 'All Categories' }
                                            : {
                                                value: String(selectedCategoryFilter),
                                                label:
                                                    categories.find((c) => String(c.id) === String(selectedCategoryFilter))?.name ||
                                                    'Category',
                                            }
                                    }
                                    onChange={(selected) => {
                                        setSelectedCategoryFilter(selected ? selected.value : 'all');
                                        setCurrentPage(1);
                                    }}
                                    placeholder="Filter by Category"
                                    styles={reactSelectStyles}
                                    className="react-select-container"
                                    classNamePrefix="react-select"
                                />
                            </div>

                            {/* Dietary Type filter */}
                            <div style={{ minWidth: '140px' }}>
                                <Select
                                    options={FOOD_TYPE_FILTER_OPTIONS}
                                    value={
                                        FOOD_TYPE_FILTER_OPTIONS.find((opt) => opt.value === selectedFoodTypeFilter) ||
                                        FOOD_TYPE_FILTER_OPTIONS[0]
                                    }
                                    onChange={(selected) => {
                                        setSelectedFoodTypeFilter(selected ? selected.value : 'all');
                                        setCurrentPage(1);
                                    }}
                                    isSearchable={false}
                                    styles={reactSelectStyles}
                                    className="react-select-container"
                                    classNamePrefix="react-select"
                                    placeholder="All Types"
                                />
                            </div>

                            {/* Status filter */}
                            <div style={{ minWidth: '140px' }}>
                                <Select
                                    options={FOOD_STATUS_FILTER_OPTIONS}
                                    value={
                                        FOOD_STATUS_FILTER_OPTIONS.find((opt) => opt.value === selectedStatusFilter) ||
                                        FOOD_STATUS_FILTER_OPTIONS[0]
                                    }
                                    onChange={(selected) => {
                                        setSelectedStatusFilter(selected ? selected.value : 'all');
                                        setCurrentPage(1);
                                    }}
                                    isSearchable={false}
                                    styles={reactSelectStyles}
                                    className="react-select-container"
                                    classNamePrefix="react-select"
                                    placeholder="All Status"
                                />
                            </div>
                        </div>
                    </div>

                    {/* Food Items Data Table */}
                    <div className="admin-table-card">
                        {isLoadingDishes ? (
                            <div className="dashboard-loading-state" style={{ padding: '40px', textAlign: 'center', color: 'var(--text-secondary)' }}>
                                <div className="admin-spinner" style={{ width: '32px', height: '32px', border: '3px solid rgba(212, 160, 23, 0.2)', borderTopColor: '#d4a017', borderRadius: '50%', animation: 'spin 0.8s linear infinite', margin: '0 auto 12px' }} />
                                <p style={{ fontSize: '0.9rem', fontWeight: 500 }}>Loading food menu from server...</p>
                            </div>
                        ) : foodItems.length === 0 ? (
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
                                <h4 className="dashboard-empty-state__title">No food items found</h4>
                                <p className="dashboard-empty-state__desc">
                                    {searchQuery || selectedCategoryFilter !== 'all' || selectedFoodTypeFilter !== 'all' || selectedStatusFilter !== 'all'
                                        ? 'No food dishes matched your filter parameters. Try resetting filters.'
                                        : 'No food dishes added yet. Click below to add your first menu item.'}
                                </p>
                                {searchQuery || selectedCategoryFilter !== 'all' || selectedFoodTypeFilter !== 'all' || selectedStatusFilter !== 'all' ? (
                                    <button
                                        type="button"
                                        className="admin-secondary-btn"
                                        onClick={() => {
                                            setSearchQuery('');
                                            setSelectedCategoryFilter('all');
                                            setSelectedFoodTypeFilter('all');
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
                                        onClick={handleOpenAddDish}
                                    >
                                        + Add First Food Item
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
                                                <th>Food Dish</th>
                                                <th>Category</th>
                                                <th>Dietary Type</th>
                                                <th>Description</th>
                                                <th>Status</th>
                                                <th style={{ textAlign: 'right', width: '130px' }}>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {foodItems.map((item) => {
                                                const categoryName = getCategoryName(item.food_category_id, item);
                                                const coverImage = resolveFoodImage(item);
                                                return (
                                                    <tr key={item.id} className="admin-table-row">
                                                        <td className="admin-table-cell--id">#{item.id}</td>
                                                        <td>
                                                            <div className="room-table-info-cell">
                                                                <div className="room-table-avatar" style={{ borderRadius: '8px' }}>
                                                                    <img src={coverImage} alt={item.name} />
                                                                </div>
                                                                <div className="room-table-text">
                                                                    <span className="room-table-name">{item.name}</span>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <span className="room-type-assigned-pill">{categoryName}</span>
                                                        </td>
                                                        <td>
                                                            {renderFoodTypeBadge(item.food_type)}
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
                                                                    onClick={() => { setViewingItem(item); setIsViewModalOpen(true); }}
                                                                    title="View Food Item Details"
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
                                                                    onClick={() => handleOpenEditDish(item)}
                                                                    title="Edit Food Item"
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
                                                                    onClick={() => { setDeletingItem(item); setIsDeleteModalOpen(true); }}
                                                                    title="Delete Food Item"
                                                                    aria-label={`Delete ${item.name}`}
                                                                >
                                                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                                                        <polyline points="3 6 5 6 21 6" />
                                                                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" />
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
                </>
            )}

            {/* ═════════════════════════════════════════════════════════════ */}
            {/* TAB 2: ROOM FOOD PACKAGES (Meal Combos for Lodge Stays) */}
            {/* ═════════════════════════════════════════════════════════════ */}
            {activeTab === 'packages' && (
                <>
                    {/* Stats Overview Grid */}
                    <div className="room-types-stats-grid">
                        <div className="room-type-stat-card">
                            <div className="room-type-stat-card__icon room-type-stat-card__icon--gold">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                    <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z" />
                                    <polyline points="3.27 6.96 12 12.01 20.73 6.96" />
                                    <line x1="12" y1="22.08" x2="12" y2="12" />
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
                                    <path d="M18 8h1a4 4 0 0 1 0 8h-1" />
                                    <path d="M2 8h16v9a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4V8z" />
                                    <line x1="6" y1="2" x2="6" y2="4" />
                                    <line x1="10" y1="2" x2="10" y2="4" />
                                    <line x1="14" y1="2" x2="14" y2="4" />
                                </svg>
                            </div>
                            <div className="room-type-stat-card__info">
                                <span className="room-type-stat-card__label">Breakfast / Combos</span>
                                <span className="room-type-stat-card__value">{packageStats.breakfast}</span>
                            </div>
                        </div>

                        <div className="room-type-stat-card">
                            <div className="room-type-stat-card__icon room-type-stat-card__icon--rose">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                    <circle cx="12" cy="12" r="10" />
                                    <path d="M12 8v8" />
                                    <path d="M8 12h8" />
                                </svg>
                            </div>
                            <div className="room-type-stat-card__info">
                                <span className="room-type-stat-card__label">Lunch & Dinner</span>
                                <span className="room-type-stat-card__value">{packageStats.lunchDinner}</span>
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
                                <span className="room-type-stat-card__value">{packageStats.inactive}</span>
                            </div>
                        </div>
                    </div>

                    {/* Filter & Search Toolbar */}
                    <div className="room-types-toolbar">
                        <div className="room-types-search-select-wrap">
                            <Select
                                options={packageSearchSelectOptions}
                                value={
                                    packageSearchQuery
                                        ? packageSearchSelectOptions.find((o) => o.value.toLowerCase() === packageSearchQuery.toLowerCase()) || {
                                            value: packageSearchQuery,
                                            label: packageSearchQuery,
                                        }
                                        : null
                                }
                                onChange={(selected) => {
                                    setPackageSearchQuery(selected ? selected.value : '');
                                    setPackageCurrentPage(1);
                                }}
                                onInputChange={(inputValue, { action }) => {
                                    if (action === 'input-change') {
                                        setPackageSearchQuery(inputValue);
                                        setPackageCurrentPage(1);
                                    }
                                }}
                                placeholder="Search or select room package..."
                                isClearable
                                isSearchable
                                styles={reactSelectStyles}
                                className="react-select-container"
                                classNamePrefix="react-select"
                                noOptionsMessage={({ inputValue }) =>
                                    inputValue ? `No packages match "${inputValue}"` : 'No options'
                                }
                            />
                        </div>

                        {/* Meal Type Filter pills */}
                        <div className="room-types-filter-group">
                            <button
                                type="button"
                                className={`room-types-filter-pill ${packageMealTypeFilter === 'all' ? 'active' : ''}`}
                                onClick={() => { setPackageMealTypeFilter('all'); setPackageCurrentPage(1); }}
                            >
                                All Meals
                            </button>
                            <button
                                type="button"
                                className={`room-types-filter-pill ${packageMealTypeFilter === 'breakfast' ? 'active' : ''}`}
                                onClick={() => { setPackageMealTypeFilter('breakfast'); setPackageCurrentPage(1); }}
                            >
                                🍳 Breakfast
                            </button>
                            <button
                                type="button"
                                className={`room-types-filter-pill ${packageMealTypeFilter === 'lunch' ? 'active' : ''}`}
                                onClick={() => { setPackageMealTypeFilter('lunch'); setPackageCurrentPage(1); }}
                            >
                                🍛 Lunch
                            </button>
                            <button
                                type="button"
                                className={`room-types-filter-pill ${packageMealTypeFilter === 'dinner' ? 'active' : ''}`}
                                onClick={() => { setPackageMealTypeFilter('dinner'); setPackageCurrentPage(1); }}
                            >
                                🍲 Dinner
                            </button>
                            <button
                                type="button"
                                className={`room-types-filter-pill ${packageMealTypeFilter === 'combo' ? 'active' : ''}`}
                                onClick={() => { setPackageMealTypeFilter('combo'); setPackageCurrentPage(1); }}
                            >
                                🍱 Combo
                            </button>
                        </div>

                        {/* Status Filter pills */}
                        <div className="room-types-filter-group">
                            <button
                                type="button"
                                className={`room-types-filter-pill ${packageStatusFilter === 'all' ? 'active' : ''}`}
                                onClick={() => { setPackageStatusFilter('all'); setPackageCurrentPage(1); }}
                            >
                                All Status
                            </button>
                            <button
                                type="button"
                                className={`room-types-filter-pill ${packageStatusFilter === 'active' ? 'active' : ''}`}
                                onClick={() => { setPackageStatusFilter('active'); setPackageCurrentPage(1); }}
                            >
                                Active
                            </button>
                            <button
                                type="button"
                                className={`room-types-filter-pill ${packageStatusFilter === 'inactive' ? 'active' : ''}`}
                                onClick={() => { setPackageStatusFilter('inactive'); setPackageCurrentPage(1); }}
                            >
                                Inactive
                            </button>
                        </div>
                    </div>

                    {/* Room Packages Data Table */}
                    <div className="admin-table-card">
                        {isLoadingPackages ? (
                            <div className="dashboard-loading-state" style={{ padding: '40px', textAlign: 'center', color: 'var(--text-secondary)' }}>
                                <div className="admin-spinner" style={{ width: '32px', height: '32px', border: '3px solid rgba(212, 160, 23, 0.2)', borderTopColor: '#d4a017', borderRadius: '50%', animation: 'spin 0.8s linear infinite', margin: '0 auto 12px' }} />
                                <p style={{ fontSize: '0.9rem', fontWeight: 500 }}>Loading room packages from server...</p>
                            </div>
                        ) : roomPackages.length === 0 ? (
                            <div className="dashboard-empty-state">
                                <div className="dashboard-empty-state__icon-wrap">
                                    <svg width="42" height="42" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round">
                                        <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z" />
                                        <polyline points="3.27 6.96 12 12.01 20.73 6.96" />
                                        <line x1="12" y1="22.08" x2="12" y2="12" />
                                    </svg>
                                </div>
                                <h4 className="dashboard-empty-state__title">No room packages found</h4>
                                <p className="dashboard-empty-state__desc">
                                    {packageSearchQuery || packageMealTypeFilter !== 'all' || packageStatusFilter !== 'all'
                                        ? 'No room packages matched your filter criteria.'
                                        : 'No room dining packages created yet. Click below to bundle your first package.'}
                                </p>
                                {packageSearchQuery || packageMealTypeFilter !== 'all' || packageStatusFilter !== 'all' ? (
                                    <button
                                        type="button"
                                        className="admin-secondary-btn"
                                        onClick={() => {
                                            setPackageSearchQuery('');
                                            setPackageMealTypeFilter('all');
                                            setPackageStatusFilter('all');
                                            setPackageCurrentPage(1);
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
                                        + Create First Room Package
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
                                                <th>Package Name</th>
                                                <th>Meal Schedule</th>
                                                <th>Included Dishes (Items)</th>
                                                <th>Status</th>
                                                <th style={{ textAlign: 'right', width: '130px' }}>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {roomPackages.map((pkg) => (
                                                <tr key={pkg.id} className="admin-table-row">
                                                    <td className="admin-table-cell--id">#{pkg.id}</td>
                                                    <td>
                                                        <div className="room-type-name-cell">
                                                            <span className="room-type-name-text">{pkg.name}</span>
                                                            <span className="room-table-amenities-snippet" style={{ fontSize: '0.8rem', color: '#78716c' }}>
                                                                {pkg.description || 'Room dining bundle'}
                                                            </span>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        {renderMealTypeBadge(pkg.meal_type)}
                                                    </td>
                                                    <td>
                                                        <div style={{ display: 'flex', flexWrap: 'wrap', gap: '6px', alignItems: 'center' }}>
                                                            {pkg.items && pkg.items.length > 0 ? (
                                                                pkg.items.slice(0, 3).map((it, idx) => (
                                                                    <span
                                                                        key={idx}
                                                                        style={{
                                                                            backgroundColor: '#faf7f2',
                                                                            border: '1px solid rgba(212, 160, 23, 0.25)',
                                                                            borderRadius: '6px',
                                                                            padding: '2px 8px',
                                                                            fontSize: '0.78rem',
                                                                            fontWeight: 500,
                                                                            color: 'var(--text-primary)',
                                                                        }}
                                                                    >
                                                                        {it.food_item_name || it.name} ×{it.quantity}
                                                                    </span>
                                                                ))
                                                            ) : (
                                                                <span className="text-muted-italic">No items attached</span>
                                                            )}
                                                            {pkg.items && pkg.items.length > 3 && (
                                                                <span style={{ fontSize: '0.78rem', color: '#78716c', fontWeight: 600 }}>
                                                                    +{pkg.items.length - 3} more
                                                                </span>
                                                            )}
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <span className={`admin-status-badge admin-status-badge--${pkg.status}`}>
                                                            <span className="admin-status-dot" />
                                                            {pkg.status === 'active' ? 'Active' : 'Inactive'}
                                                        </span>
                                                    </td>
                                                    <td style={{ textAlign: 'right' }}>
                                                        <div className="admin-table-actions">
                                                            <button
                                                                type="button"
                                                                className="admin-action-btn admin-action-btn--view"
                                                                onClick={() => { setViewingPackage(pkg); setIsPackageViewModalOpen(true); }}
                                                                title="View Package Details & Items"
                                                                aria-label={`View package ${pkg.name}`}
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
                                                                title="Edit Package & Customize Dishes"
                                                                aria-label={`Edit package ${pkg.name}`}
                                                            >
                                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                                                    <path d="M11 4 H4 a2 2 0 0 0 -2 2 v14 a2 2 0 0 0 2 2 h14 a2 2 0 0 0 2 -2 v-7" />
                                                                    <path d="M18.5 2.5 a2.121 2.121 0 0 1 3 3 L12 15 l-4 1 1 -4 9.5 -9.5 z" />
                                                                </svg>
                                                            </button>
                                                            <button
                                                                type="button"
                                                                className="admin-action-btn admin-action-btn--delete"
                                                                onClick={() => { setDeletingPackage(pkg); setIsPackageDeleteModalOpen(true); }}
                                                                title="Delete Room Package"
                                                                aria-label={`Delete package ${pkg.name}`}
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
                                    currentPage={packageCurrentPage}
                                    totalItems={packageTotalRecords}
                                    pageSize={packagePageSize}
                                    onPageChange={setPackageCurrentPage}
                                    onPageSizeChange={setPackagePageSize}
                                />
                            </>
                        )}
                    </div>
                </>
            )}

            {/* ─── ADD / EDIT DISH MODAL ─── */}
            {isFormModalOpen && (
                <div className="admin-modal-overlay" onClick={() => setIsFormModalOpen(false)}>
                    <div className="admin-modal" onClick={(e) => e.stopPropagation()} role="dialog" aria-modal="true">
                        <div className="admin-modal__header">
                            <div className="admin-modal__title-wrap">
                                <h3 className="admin-modal__title">
                                    {formMode === 'create' ? 'Add New Food Item' : 'Edit Food Item'}
                                </h3>
                                <p className="admin-modal__subtitle">
                                    {formMode === 'create'
                                        ? 'Add a new dish to the catering and restaurant dining menu.'
                                        : `Updating menu dish #${editingItem?.id}`}
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

                        <form onSubmit={handleDishFormSubmit} noValidate>
                            <div className="admin-modal__body">
                                <div className="admin-form-group">
                                    <label className="admin-form-label" htmlFor="food-name-input">
                                        Food Item / Dish Name <span className="admin-form-required">*</span>
                                    </label>
                                    <input
                                        id="food-name-input"
                                        type="text"
                                        className={`admin-form-input ${formErrors.name ? 'admin-form-input--error' : ''}`}
                                        placeholder="e.g. Paneer Butter Masala, Chicken Tikka"
                                        value={formData.name}
                                        onChange={(e) => {
                                            setFormData((prev) => ({ ...prev, name: e.target.value }));
                                            if (formErrors.name) setFormErrors((prev) => ({ ...prev, name: null }));
                                        }}
                                        maxLength={100}
                                        autoFocus
                                    />
                                    {formErrors.name && <span className="admin-form-error">{formErrors.name}</span>}
                                </div>

                                <div className="admin-form-row admin-form-row-2">
                                    <div className="admin-form-group">
                                        <label className="admin-form-label" htmlFor="food-category-select">
                                            Menu Category
                                        </label>
                                        <select
                                            id="food-category-select"
                                            className="admin-form-select"
                                            value={formData.food_category_id}
                                            onChange={(e) => setFormData((prev) => ({ ...prev, food_category_id: e.target.value }))}
                                        >
                                            <option value="">Select Category</option>
                                            {categories.map((cat) => (
                                                <option key={cat.id} value={cat.id}>
                                                    {cat.name}
                                                </option>
                                            ))}
                                        </select>
                                    </div>

                                    <div className="admin-form-group">
                                        <label className="admin-form-label" htmlFor="food-type-select">
                                            Dietary Type
                                        </label>
                                        <select
                                            id="food-type-select"
                                            className="admin-form-select"
                                            value={formData.food_type}
                                            onChange={(e) => setFormData((prev) => ({ ...prev, food_type: e.target.value }))}
                                        >
                                            {FOOD_TYPES.map((t) => (
                                                <option key={t.value} value={t.value}>
                                                    {t.icon} {t.label}
                                                </option>
                                            ))}
                                        </select>
                                    </div>
                                </div>

                                <div className="admin-form-group">
                                    <label className="admin-form-label">
                                        Availability Status
                                    </label>
                                    <div className="admin-radio-group" style={{ display: 'flex', flexDirection: 'column', gap: '10px' }}>
                                        <label className="admin-radio-label">
                                            <input
                                                type="radio"
                                                name="food_status"
                                                value="active"
                                                checked={formData.status === 'active'}
                                                onChange={() => setFormData((prev) => ({ ...prev, status: 'active' }))}
                                            />
                                            <span className="admin-radio-custom" />
                                            <span>Active (Available on Menu)</span>
                                        </label>
                                        <label className="admin-radio-label">
                                            <input
                                                type="radio"
                                                name="food_status"
                                                value="inactive"
                                                checked={formData.status === 'inactive'}
                                                onChange={() => setFormData((prev) => ({ ...prev, status: 'inactive' }))}
                                            />
                                            <span className="admin-radio-custom" />
                                            <span>Inactive (Temporarily Unavailable)</span>
                                        </label>
                                    </div>
                                </div>

                                <div className="admin-form-group">
                                    <div className="admin-form-label-row">
                                        <label className="admin-form-label" htmlFor="food-desc-input">
                                            Description & Preparation Notes
                                        </label>
                                        <span className="admin-char-count">{formData.description.length}/1000</span>
                                    </div>
                                    <textarea
                                        id="food-desc-input"
                                        className={`admin-form-textarea ${formErrors.description ? 'admin-form-input--error' : ''}`}
                                        placeholder="Detailed description of flavors, ingredients, allergens, or cooking style..."
                                        rows={3}
                                        value={formData.description}
                                        onChange={(e) => {
                                            setFormData((prev) => ({ ...prev, description: e.target.value }));
                                            if (formErrors.description) setFormErrors((prev) => ({ ...prev, description: null }));
                                        }}
                                        maxLength={1000}
                                    />
                                    {formErrors.description && <span className="admin-form-error">{formErrors.description}</span>}
                                </div>

                                <div className="admin-form-group">
                                    <label className="admin-form-label">
                                        Food Dish Photo
                                    </label>
                                    <div className="admin-image-upload-box" style={{ padding: '12px' }}>
                                        <input
                                            type="file"
                                            accept="image/jpeg, image/jpg, image/png, image/webp"
                                            ref={fileInputRef}
                                            onChange={(e) => {
                                                const file = e.target.files[0];
                                                if (!file) return;
                                                if (file.size > 5 * 1024 * 1024) {
                                                    showToast('Image size cannot exceed 5MB.', 'error');
                                                    return;
                                                }
                                                const objectUrl = URL.createObjectURL(file);
                                                setFormData((prev) => ({
                                                    ...prev,
                                                    imageFile: file,
                                                    imagePreview: objectUrl,
                                                }));
                                            }}
                                            style={{ display: 'none' }}
                                            id="food-photo-file-picker"
                                        />
                                        <div style={{ display: 'flex', alignItems: 'center', gap: '16px' }}>
                                            <div
                                                style={{
                                                    width: '72px',
                                                    height: '72px',
                                                    borderRadius: '8px',
                                                    overflow: 'hidden',
                                                    border: '1px solid rgba(212, 160, 23, 0.3)',
                                                    backgroundColor: '#faf7f2',
                                                    display: 'flex',
                                                    alignItems: 'center',
                                                    justifyContent: 'center',
                                                    flexShrink: 0,
                                                    color: 'var(--text-muted)',
                                                    fontSize: '0.75rem',
                                                }}
                                            >
                                                {formData.imagePreview ? (
                                                    <img
                                                        src={formData.imagePreview}
                                                        alt="Preview"
                                                        style={{ width: '100%', height: '100%', objectFit: 'cover' }}
                                                    />
                                                ) : (
                                                    <span style={{ fontSize: '1.4rem' }}>🍽️</span>
                                                )}
                                            </div>
                                            <div>
                                                <button
                                                    type="button"
                                                    className="admin-secondary-btn"
                                                    onClick={() => fileInputRef.current?.click()}
                                                    style={{ fontSize: '0.84rem', padding: '6px 14px' }}
                                                >
                                                    📁 Select Photo (JPG, PNG, WebP)
                                                </button>
                                                <span style={{ display: 'block', fontSize: '0.78rem', color: '#78716c', marginTop: '4px' }}>
                                                    Max 5MB. Standard high-resolution food photo.
                                                </span>
                                            </div>
                                        </div>
                                    </div>
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
                                    {formMode === 'create' ? 'Create Food Item' : 'Save Changes'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* ─── VIEW DISH DETAILS MODAL ─── */}
            {isViewModalOpen && viewingItem && (
                <div className="admin-modal-overlay" onClick={() => setIsViewModalOpen(false)}>
                    <div className="admin-modal admin-modal--view" onClick={(e) => e.stopPropagation()} role="dialog" aria-modal="true">
                        <div className="admin-modal__header">
                            <div className="admin-modal__title-wrap">
                                <div className="admin-modal__badge-row">
                                    <span className="room-number-badge">#{viewingItem.id}</span>
                                    <span className="room-type-assigned-pill">{getCategoryName(viewingItem.food_category_id, viewingItem)}</span>
                                    {renderFoodTypeBadge(viewingItem.food_type)}
                                    <span className={`admin-status-badge admin-status-badge--${viewingItem.status}`}>
                                        <span className="admin-status-dot" />
                                        {viewingItem.status === 'active' ? 'Active' : 'Inactive'}
                                    </span>
                                </div>
                                <h3 className="admin-modal__title">{viewingItem.name}</h3>
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
                            <div className="room-view-modal-hero" style={{ height: 'auto', minHeight: '220px', maxHeight: '380px', borderRadius: '12px', overflow: 'hidden', backgroundColor: '#1a1612', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                                <img
                                    src={resolveFoodImage(viewingItem)}
                                    alt={viewingItem.name}
                                    style={{ maxWidth: '100%', maxHeight: '380px', width: 'auto', height: 'auto', objectFit: 'contain' }}
                                />
                            </div>

                            <div className="room-type-details-grid" style={{ marginTop: '20px' }}>
                                <div className="room-type-detail-item">
                                    <span className="room-type-detail-label">Menu Category</span>
                                    <span className="room-type-detail-val">{getCategoryName(viewingItem.food_category_id, viewingItem)}</span>
                                </div>
                                <div className="room-type-detail-item">
                                    <span className="room-type-detail-label">Dietary Classification</span>
                                    <span className="room-type-detail-val">
                                        {FOOD_TYPES.find((t) => t.value === viewingItem.food_type)?.label || viewingItem.food_type}
                                    </span>
                                </div>
                                <div className="room-type-detail-item full-width">
                                    <span className="room-type-detail-label">Description & Recipe Notes</span>
                                    <p className="room-type-detail-text">
                                        {viewingItem.description || 'No description entered for this food item.'}
                                    </p>
                                </div>
                                <div className="room-type-detail-item">
                                    <span className="room-type-detail-label">Created At</span>
                                    <span className="room-type-detail-val text-muted">{viewingItem.created_at || '—'}</span>
                                </div>
                                <div className="room-type-detail-item">
                                    <span className="room-type-detail-label">Last Updated</span>
                                    <span className="room-type-detail-val text-muted">{viewingItem.updated_at || '—'}</span>
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
                                    handleOpenEditDish(viewingItem);
                                }}
                            >
                                Edit This Dish
                            </button>
                        </div>
                    </div>
                </div>
            )}

            {/* ─── DELETE DISH MODAL ─── */}
            {isDeleteModalOpen && deletingItem && (
                <div className="admin-modal-overlay" onClick={() => setIsDeleteModalOpen(false)}>
                    <div className="admin-modal admin-modal--danger" onClick={(e) => e.stopPropagation()} role="dialog" aria-modal="true">
                        <div className="admin-modal__header">
                            <div className="admin-modal__title-wrap">
                                <div className="admin-modal__danger-icon">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                        <circle cx="12" cy="12" r="10" />
                                        <line x1="12" y1="8" x2="12" y2="12" />
                                        <line x1="12" y1="16" x2="12.01" y2="16" />
                                    </svg>
                                </div>
                                <h3 className="admin-modal__title">Delete Food Dish?</h3>
                                <p className="admin-modal__subtitle">
                                    This action will remove the dish from the active menu list.
                                </p>
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
                            <div className="delete-warning-box">
                                <p>
                                    Are you sure you want to delete <strong>"{deletingItem.name}"</strong> (ID: #{deletingItem.id})?
                                </p>
                            </div>
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
                                onClick={handleConfirmDeleteDish}
                            >
                                Yes, Delete Dish
                            </button>
                        </div>
                    </div>
                </div>
            )}

            {/* ─── ADD / EDIT ROOM FOOD PACKAGE MODAL (Customization) ─── */}
            {isPackageFormModalOpen && (
                <div className="admin-modal-overlay" onClick={() => setIsPackageFormModalOpen(false)}>
                    <div className="admin-modal admin-modal--large" onClick={(e) => e.stopPropagation()} role="dialog" aria-modal="true">
                        <div className="admin-modal__header">
                            <div className="admin-modal__title-wrap">
                                <h3 className="admin-modal__title">
                                    {packageFormMode === 'create' ? 'Create Room Dining Package' : 'Edit Room Dining Package'}
                                </h3>
                                <p className="admin-modal__subtitle">
                                    Compose and customize a food package bundle for guest lodge stays.
                                </p>
                            </div>
                            <button
                                type="button"
                                className="admin-modal__close-btn"
                                onClick={() => setIsPackageFormModalOpen(false)}
                                aria-label="Close dialog"
                            >
                                ✕
                            </button>
                        </div>

                        <form onSubmit={handlePackageFormSubmit} noValidate>
                            <div className="admin-modal__body">
                                <div className="admin-form-group">
                                    <label className="admin-form-label" htmlFor="package-name-input">
                                        Package Title / Combo Name <span className="admin-form-required">*</span>
                                    </label>
                                    <input
                                        id="package-name-input"
                                        type="text"
                                        className={`admin-form-input ${packageFormErrors.name ? 'admin-form-input--error' : ''}`}
                                        placeholder="e.g. Executive Lunch Combo, South Indian Breakfast Set"
                                        value={packageFormData.name}
                                        onChange={(e) => {
                                            setPackageFormData((prev) => ({ ...prev, name: e.target.value }));
                                            if (packageFormErrors.name) setPackageFormErrors((prev) => ({ ...prev, name: null }));
                                        }}
                                        maxLength={100}
                                        autoFocus
                                    />
                                    {packageFormErrors.name && <span className="admin-form-error">{packageFormErrors.name}</span>}
                                </div>

                                <div className="admin-form-row admin-form-row-2">
                                    <div className="admin-form-group">
                                        <label className="admin-form-label" htmlFor="package-meal-type-select">
                                            Meal Schedule Type
                                        </label>
                                        <select
                                            id="package-meal-type-select"
                                            className="admin-form-select"
                                            value={packageFormData.meal_type}
                                            onChange={(e) => setPackageFormData((prev) => ({ ...prev, meal_type: e.target.value }))}
                                        >
                                            {MEAL_TYPES.map((t) => (
                                                <option key={t.value} value={t.value}>
                                                    {t.icon} {t.label}
                                                </option>
                                            ))}
                                        </select>
                                    </div>

                                    <div className="admin-form-group">
                                        <label className="admin-form-label">
                                            Status
                                        </label>
                                        <div className="admin-radio-group" style={{ display: 'flex', flexDirection: 'column', gap: '8px', paddingTop: '4px' }}>
                                            <label className="admin-radio-label">
                                                <input
                                                    type="radio"
                                                    name="pkg_status"
                                                    value="active"
                                                    checked={packageFormData.status === 'active'}
                                                    onChange={() => setPackageFormData((prev) => ({ ...prev, status: 'active' }))}
                                                />
                                                <span className="admin-radio-custom" />
                                                <span>Active (Available for Room Guests)</span>
                                            </label>
                                            <label className="admin-radio-label">
                                                <input
                                                    type="radio"
                                                    name="pkg_status"
                                                    value="inactive"
                                                    checked={packageFormData.status === 'inactive'}
                                                    onChange={() => setPackageFormData((prev) => ({ ...prev, status: 'inactive' }))}
                                                />
                                                <span className="admin-radio-custom" />
                                                <span>Inactive (Temporarily Hidden)</span>
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                <div className="admin-form-group">
                                    <div className="admin-form-label-row">
                                        <label className="admin-form-label" htmlFor="package-desc-input">
                                            Package Description & Dining Instructions
                                        </label>
                                        <span className="admin-char-count">{packageFormData.description.length}/1000</span>
                                    </div>
                                    <textarea
                                        id="package-desc-input"
                                        className="admin-form-textarea"
                                        placeholder="Describe what is included in this meal package for the guests..."
                                        rows={2}
                                        value={packageFormData.description}
                                        onChange={(e) => setPackageFormData((prev) => ({ ...prev, description: e.target.value }))}
                                        maxLength={1000}
                                    />
                                </div>

                                {/* PACKAGE CUSTOMIZATION (Add dishes & set quantities) */}
                                <div className="admin-form-group" style={{ borderTop: '1px solid rgba(40, 30, 20, 0.1)', paddingTop: '16px', marginTop: '16px' }}>
                                    <label className="admin-form-label" style={{ fontSize: '0.95rem', fontWeight: 700, color: 'var(--text-primary)' }}>
                                        Package Dishes & Customization ({packageFormData.items.length} items bundled)
                                    </label>
                                    <p style={{ fontSize: '0.82rem', color: '#78716c', marginBottom: '12px' }}>
                                        Select dishes from the menu to bundle inside this package and configure their quantities.
                                    </p>

                                    {/* Dish Picker bar with React Select */}
                                    <div style={{ display: 'flex', gap: '10px', marginBottom: '16px', alignItems: 'center', flexWrap: 'wrap' }}>
                                        <div style={{ flex: 1, minWidth: '240px' }}>
                                            <Select
                                                value={
                                                    selectedDishToAdd
                                                        ? dishPickerOptions.find((opt) => opt.value === String(selectedDishToAdd)) || null
                                                        : null
                                                }
                                                onChange={(opt) => setSelectedDishToAdd(opt ? opt.value : '')}
                                                options={dishPickerOptions}
                                                placeholder="-- Choose a Food Dish to Add --"
                                                isClearable
                                                isSearchable
                                                styles={reactSelectStyles}
                                                menuPortalTarget={typeof document !== 'undefined' ? document.body : null}
                                                menuPosition="fixed"
                                                className="react-select-container"
                                                classNamePrefix="react-select"
                                                noOptionsMessage={({ inputValue }) =>
                                                    inputValue ? `No food dishes match "${inputValue}"` : 'No food dishes available'
                                                }
                                            />
                                        </div>
                                        <button
                                            type="button"
                                            className="admin-secondary-btn"
                                            onClick={handleAddDishToPackage}
                                            disabled={!selectedDishToAdd}
                                            style={{ padding: '8px 16px', height: '42px', whiteSpace: 'nowrap' }}
                                        >
                                            + Add Dish to Package
                                        </button>
                                    </div>

                                    {/* Bundled Items List with Quantities */}
                                    {packageFormData.items.length === 0 ? (
                                        <div style={{ padding: '16px', backgroundColor: '#faf7f2', borderRadius: '8px', border: '1px dashed rgba(212, 160, 23, 0.3)', textAlign: 'center', color: '#78716c', fontSize: '0.85rem' }}>
                                            No food items added to this package yet. Select a dish above and click "Add Dish to Package".
                                        </div>
                                    ) : (
                                        <div style={{ display: 'flex', flexDirection: 'column', gap: '8px', maxHeight: '200px', overflowY: 'auto' }}>
                                            {packageFormData.items.map((it) => (
                                                <div
                                                    key={it.food_item_id}
                                                    style={{
                                                        display: 'flex',
                                                        alignItems: 'center',
                                                        justifyContent: 'space-between',
                                                        padding: '8px 12px',
                                                        backgroundColor: '#ffffff',
                                                        border: '1px solid rgba(40, 30, 20, 0.1)',
                                                        borderRadius: '8px',
                                                    }}
                                                >
                                                    <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                                                        <span style={{ fontWeight: 600, fontSize: '0.88rem' }}>{it.food_item_name}</span>
                                                        {renderFoodTypeBadge(it.food_type)}
                                                    </div>

                                                    <div style={{ display: 'flex', alignItems: 'center', gap: '12px' }}>
                                                        <div style={{ display: 'flex', alignItems: 'center', gap: '6px' }}>
                                                            <span style={{ fontSize: '0.8rem', color: '#78716c' }}>Qty:</span>
                                                            <button
                                                                type="button"
                                                                onClick={() => handlePackageItemQuantityChange(it.food_item_id, it.quantity - 1)}
                                                                style={{ width: '24px', height: '24px', borderRadius: '4px', border: '1px solid #d4a017', backgroundColor: '#faf7f2', cursor: 'pointer', fontWeight: 'bold' }}
                                                            >
                                                                -
                                                            </button>
                                                            <span style={{ fontWeight: 700, minWidth: '20px', textAlign: 'center' }}>{it.quantity}</span>
                                                            <button
                                                                type="button"
                                                                onClick={() => handlePackageItemQuantityChange(it.food_item_id, it.quantity + 1)}
                                                                style={{ width: '24px', height: '24px', borderRadius: '4px', border: '1px solid #d4a017', backgroundColor: '#faf7f2', cursor: 'pointer', fontWeight: 'bold' }}
                                                            >
                                                                +
                                                            </button>
                                                        </div>

                                                        <button
                                                            type="button"
                                                            onClick={() => handleRemovePackageItem(it.food_item_id)}
                                                            style={{ color: '#dc2626', background: 'none', border: 'none', cursor: 'pointer', fontSize: '1rem', padding: '0 4px' }}
                                                            title="Remove dish"
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

                            <div className="admin-modal__footer">
                                <button
                                    type="button"
                                    className="admin-secondary-btn"
                                    onClick={() => setIsPackageFormModalOpen(false)}
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    className="admin-primary-btn"
                                >
                                    {packageFormMode === 'create' ? 'Create Room Package' : 'Save Package Changes'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* ─── VIEW ROOM PACKAGE DETAILS MODAL ─── */}
            {isPackageViewModalOpen && viewingPackage && (
                <div className="admin-modal-overlay" onClick={() => setIsPackageViewModalOpen(false)}>
                    <div className="admin-modal admin-modal--view" onClick={(e) => e.stopPropagation()} role="dialog" aria-modal="true">
                        <div className="admin-modal__header">
                            <div className="admin-modal__title-wrap">
                                <div className="admin-modal__badge-row">
                                    <span className="room-number-badge">Package #{viewingPackage.id}</span>
                                    {renderMealTypeBadge(viewingPackage.meal_type)}
                                    <span className={`admin-status-badge admin-status-badge--${viewingPackage.status}`}>
                                        <span className="admin-status-dot" />
                                        {viewingPackage.status === 'active' ? 'Active' : 'Inactive'}
                                    </span>
                                </div>
                                <h3 className="admin-modal__title">{viewingPackage.name}</h3>
                            </div>
                            <button
                                type="button"
                                className="admin-modal__close-btn"
                                onClick={() => setIsPackageViewModalOpen(false)}
                                aria-label="Close dialog"
                            >
                                ✕
                            </button>
                        </div>

                        <div className="admin-modal__body">
                            <div className="room-type-details-grid">
                                <div className="room-type-detail-item">
                                    <span className="room-type-detail-label">Meal Schedule</span>
                                    <span className="room-type-detail-val">
                                        {MEAL_TYPES.find((t) => t.value === viewingPackage.meal_type)?.label || viewingPackage.meal_type}
                                    </span>
                                </div>
                                <div className="room-type-detail-item">
                                    <span className="room-type-detail-label">Total Bundled Dishes</span>
                                    <span className="room-type-detail-val">{viewingPackage.items?.length || viewingPackage.total_items || 0} Dishes</span>
                                </div>
                                <div className="room-type-detail-item full-width">
                                    <span className="room-type-detail-label">Package Description</span>
                                    <p className="room-type-detail-text">
                                        {viewingPackage.description || 'No description entered for this room food package.'}
                                    </p>
                                </div>

                                <div className="room-type-detail-item full-width">
                                    <span className="room-type-detail-label">Included Dishes & Quantities</span>
                                    {viewingPackage.items && viewingPackage.items.length > 0 ? (
                                        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(240px, 1fr))', gap: '10px', marginTop: '8px' }}>
                                            {viewingPackage.items.map((it, idx) => (
                                                <div
                                                    key={idx}
                                                    style={{
                                                        padding: '10px 14px',
                                                        backgroundColor: '#faf7f2',
                                                        border: '1px solid rgba(212, 160, 23, 0.25)',
                                                        borderRadius: '8px',
                                                        display: 'flex',
                                                        justifyContent: 'space-between',
                                                        alignItems: 'center',
                                                    }}
                                                >
                                                    <div>
                                                        <span style={{ fontWeight: 600, fontSize: '0.88rem', display: 'block' }}>
                                                            {it.food_item_name || it.name}
                                                        </span>
                                                        {it.category?.name && (
                                                            <span style={{ fontSize: '0.75rem', color: '#78716c' }}>
                                                                {it.category.name}
                                                            </span>
                                                        )}
                                                    </div>
                                                    <span style={{ fontWeight: 700, color: 'var(--gold-800)', backgroundColor: '#ffffff', padding: '2px 8px', borderRadius: '4px', border: '1px solid rgba(212, 160, 23, 0.2)' }}>
                                                        ×{it.quantity}
                                                    </span>
                                                </div>
                                            ))}
                                        </div>
                                    ) : (
                                        <p className="text-muted-italic">No specific dishes attached to this package.</p>
                                    )}
                                </div>

                                <div className="room-type-detail-item">
                                    <span className="room-type-detail-label">Created At</span>
                                    <span className="room-type-detail-val text-muted">{viewingPackage.created_at || '—'}</span>
                                </div>
                                <div className="room-type-detail-item">
                                    <span className="room-type-detail-label">Last Updated</span>
                                    <span className="room-type-detail-val text-muted">{viewingPackage.updated_at || '—'}</span>
                                </div>
                            </div>
                        </div>

                        <div className="admin-modal__footer">
                            <button
                                type="button"
                                className="admin-secondary-btn"
                                onClick={() => setIsPackageViewModalOpen(false)}
                            >
                                Close
                            </button>
                            <button
                                type="button"
                                className="admin-primary-btn"
                                onClick={() => {
                                    setIsPackageViewModalOpen(false);
                                    handleOpenEditPackage(viewingPackage);
                                }}
                            >
                                Edit & Customize Package
                            </button>
                        </div>
                    </div>
                </div>
            )}

            {/* ─── DELETE ROOM PACKAGE CONFIRMATION MODAL ─── */}
            {isPackageDeleteModalOpen && deletingPackage && (
                <div className="admin-modal-overlay" onClick={() => setIsPackageDeleteModalOpen(false)}>
                    <div className="admin-modal admin-modal--danger" onClick={(e) => e.stopPropagation()} role="dialog" aria-modal="true">
                        <div className="admin-modal__header">
                            <div className="admin-modal__title-wrap">
                                <div className="admin-modal__danger-icon">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                        <circle cx="12" cy="12" r="10" />
                                        <line x1="12" y1="8" x2="12" y2="12" />
                                        <line x1="12" y1="16" x2="12.01" y2="16" />
                                    </svg>
                                </div>
                                <h3 className="admin-modal__title">Delete Room Package?</h3>
                                <p className="admin-modal__subtitle">
                                    This action will remove the package and disassociate its items.
                                </p>
                            </div>
                            <button
                                type="button"
                                className="admin-modal__close-btn"
                                onClick={() => setIsPackageDeleteModalOpen(false)}
                                aria-label="Close dialog"
                            >
                                ✕
                            </button>
                        </div>

                        <div className="admin-modal__body">
                            <div className="delete-warning-box">
                                <p>
                                    Are you sure you want to delete room package <strong>"{deletingPackage.name}"</strong> (ID: #{deletingPackage.id})?
                                </p>
                            </div>
                        </div>

                        <div className="admin-modal__footer">
                            <button
                                type="button"
                                className="admin-secondary-btn"
                                onClick={() => setIsPackageDeleteModalOpen(false)}
                            >
                                Cancel
                            </button>
                            <button
                                type="button"
                                className="admin-danger-btn"
                                onClick={handleConfirmDeletePackage}
                            >
                                Yes, Delete Package
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
};

export default FoodModule;
