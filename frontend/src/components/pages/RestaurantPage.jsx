import { useState, useEffect, useCallback, useMemo } from 'react';
import Select from 'react-select';
import Header from '../Header';
import Footer from '../Footer';
import DatePicker from '../DatePicker';
import { diningApi } from '../../services/api';
import { validateRequiredFields } from '../../services/formValidation';
import serviceRestaurant from '../../assets/images/service-restaurant.jpg';

// React Select styling matching customer portal theme
const reactSelectStyles = {
  control: (base, state) => ({
    ...base,
    backgroundColor: state.isFocused ? '#ffffff' : '#faf7f2',
    borderColor: state.isFocused ? '#d4a017' : 'rgba(212, 160, 23, 0.25)',
    borderRadius: '10px',
    minHeight: '42px',
    boxShadow: state.isFocused ? '0 0 0 3px rgba(212, 160, 23, 0.15)' : 'none',
    '&:hover': {
      borderColor: '#d4a017',
    },
    cursor: 'pointer',
    fontFamily: 'var(--font-sans, inherit)',
  }),
  option: (base, state) => ({
    ...base,
    backgroundColor: state.isSelected
      ? '#d4a017'
      : state.isFocused
      ? '#faf7f2'
      : '#ffffff',
    color: state.isSelected ? '#1a1612' : 'var(--text-primary, #1c1917)',
    fontWeight: state.isSelected ? '700' : '500',
    fontSize: '0.88rem',
    cursor: 'pointer',
    '&:active': {
      backgroundColor: '#f5edd8',
    },
  }),
  placeholder: (base) => ({
    ...base,
    color: '#a8a29e',
    fontSize: '0.88rem',
  }),
  singleValue: (base) => ({
    ...base,
    color: 'var(--text-primary, #1c1917)',
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
    backgroundColor: 'rgba(212, 160, 23, 0.15)',
  }),
};

// Image URL Resolver - only returns valid backend URLs, never substitutes random stock photos
const resolveDiningImgUrl = (imgPath) => {
  if (!imgPath) return null;
  if (typeof imgPath === 'string') {
    if (imgPath.startsWith('http') || imgPath.startsWith('blob:') || imgPath.startsWith('data:')) return imgPath;
    return `http://localhost/sm_groups/backend/${imgPath.replace(/^\/+/, '')}`;
  }
  return null;
};

const RestaurantPage = ({ onNavigate }) => {
  const [packages, setPackages] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  // Filters
  const [selectedPackage, setSelectedPackage] = useState(null);
  const [selectedMealType, setSelectedMealType] = useState('all');

  // Enquiry Modal state
  const [enquiringPackage, setEnquiringPackage] = useState(null);
  const [isSubmittingEnquiry, setIsSubmittingEnquiry] = useState(false);
  const [enquirySuccess, setEnquirySuccess] = useState(null);
  const [enquiryFormErrors, setEnquiryFormErrors] = useState({});
  const [enquiryFormData, setEnquiryFormData] = useState({
    customer_name: '',
    customer_phone: '',
    customer_email: '',
    meal_type: 'Dinner',
    dining_date: '',
    dining_time: '19:30',
    guest_count: 4,
    other_requirements: '',
  });

  // Fetch dining packages from backend
  const fetchPackages = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await diningApi.listPackages('limit=50&status=active');
      if (res.success && res.data?.data) {
        setPackages(res.data.data);
      } else if (res.success && Array.isArray(res.data)) {
        setPackages(res.data);
      } else if (res.data?.packages && Array.isArray(res.data.packages)) {
        setPackages(res.data.packages);
      } else {
        setPackages([]);
        if (res.error) setError(res.error);
      }
    } catch (err) {
      setError(err.message || 'Failed to load dining packages.');
      setPackages([]);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    fetchPackages();
  }, [fetchPackages]);

  // Unique meal types for filter
  const mealTypes = useMemo(() => {
    const types = new Set();
    packages.forEach((pkg) => {
      if (pkg.meal_type) types.add(pkg.meal_type);
    });
    return Array.from(types);
  }, [packages]);

  // React Select options for Dining Packages
  const packageOptions = useMemo(() => {
    return packages.map((pkg) => ({
      value: pkg.id,
      label: `${pkg.name}${pkg.meal_type ? ` (${pkg.meal_type})` : ''}`,
      data: pkg,
    }));
  }, [packages]);

  // Filtered packages
  const filteredPackages = useMemo(() => {
    return packages.filter((pkg) => {
      const matchesSelect = !selectedPackage || Number(pkg.id) === Number(selectedPackage.value);
      const matchesMeal =
        selectedMealType === 'all' ||
        (pkg.meal_type && pkg.meal_type.toLowerCase() === selectedMealType.toLowerCase());

      return matchesSelect && matchesMeal;
    });
  }, [packages, selectedPackage, selectedMealType]);

  // Open Enquiry Modal
  const handleOpenEnquire = (pkg) => {
    setEnquiringPackage(pkg);
    setEnquirySuccess(null);
    setEnquiryFormErrors({});
    setEnquiryFormData({
      customer_name: '',
      customer_phone: '',
      customer_email: '',
      meal_type: pkg.meal_type || 'Dinner',
      dining_date: '',
      dining_time: '19:30',
      guest_count: 4,
      other_requirements: '',
    });
  };

  // Submit Enquiry
  const handleEnquirySubmit = async (e) => {
    e.preventDefault();
    const validation = validateRequiredFields(enquiryFormData, ['customer_name', 'customer_phone']);
    if (!validation.isValid) {
      setEnquiryFormErrors(validation.errors);
      return;
    }

    setIsSubmittingEnquiry(true);
    setEnquiryFormErrors({});
    try {
      const payload = {
        customer_name: enquiryFormData.customer_name.trim(),
        customer_phone: enquiryFormData.customer_phone.trim(),
        customer_email: enquiryFormData.customer_email.trim() || undefined,
        dining_package_id: Number(enquiringPackage.id),
        meal_type: enquiryFormData.meal_type || enquiringPackage.meal_type || 'Dinner',
        dining_date: enquiryFormData.dining_date || undefined,
        dining_time: enquiryFormData.dining_time || undefined,
        guest_count: Number(enquiryFormData.guest_count) || 4,
        other_requirements: enquiryFormData.other_requirements.trim() || undefined,
      };

      const res = await diningApi.createLead(payload);
      if (res.success) {
        setEnquirySuccess({
          message: res.data?.message || 'Thank you! Your dining enquiry has been registered.',
          reference: res.data?.lead_id || res.data?.id || res.data?.data?.id || 'Ref #' + Math.floor(1000 + Math.random() * 9000),
        });
      } else {
        setEnquiryFormErrors({ submit: res.error || 'Failed to submit enquiry. Please try again.' });
      }
    } catch (err) {
      setEnquiryFormErrors({ submit: err.message || 'An unexpected error occurred.' });
    } finally {
      setIsSubmittingEnquiry(false);
    }
  };

  return (
    <>
      <Header onNavigate={onNavigate} currentPage="/restaurant" />

      <main>
        {/* Hero Section */}
        <div className="page-hero page-hero--service">
          <img src={serviceRestaurant} alt="SM Restaurant & Dining" className="page-hero__bg-img" />
          <div className="page-hero__overlay" />
          <div className="page-hero__content">
            <span className="page-hero__badge">Restaurant & Dining</span>
            <h1 className="page-hero__title">Restaurant & Dining Experiences</h1>
            <p className="page-hero__desc">
              Authentic Chettinad recipes, curated multi-course dining packages, and traditional feast experiences.
            </p>
          </div>
        </div>

        <div className="service-page">
          <div className="service-page__container">
            {/* Dynamic Search & Filter Bar with React Select */}
            <section className="lodge-search-wrapper" style={{ maxWidth: '480px', marginLeft: 'auto', padding: '16px 20px', marginBottom: '32px' }}>
              <div className="lodge-search-grid" style={{ gridTemplateColumns: selectedPackage || selectedMealType !== 'all' ? 'minmax(200px, 1fr) auto' : '1fr' }}>
                {/* React Select Package dropdown */}
                <div className="lodge-field-group">
                  <label className="lodge-field-label" htmlFor="dining-package-select">
                    <span>Select Dining Package</span>
                  </label>
                  <Select
                    inputId="dining-package-select"
                    options={packageOptions}
                    value={selectedPackage}
                    onChange={(selected) => setSelectedPackage(selected)}
                    placeholder="Select Dining Package..."
                    isClearable
                    isSearchable
                    styles={reactSelectStyles}
                    className="react-select-container"
                    classNamePrefix="react-select"
                    noOptionsMessage={({ inputValue }) =>
                      inputValue ? `No package matches "${inputValue}"` : 'No packages available'
                    }
                  />
                </div>

                {/* Reset button if active */}
                {(selectedPackage || selectedMealType !== 'all') && (
                  <div style={{ display: 'flex', alignItems: 'flex-end' }}>
                    <button
                      type="button"
                      className="lodge-btn-reset"
                      style={{ height: '42px', padding: '0 20px' }}
                      onClick={() => {
                        setSelectedPackage(null);
                        setSelectedMealType('all');
                      }}
                    >
                      Reset Filter
                    </button>
                  </div>
                )}
              </div>
            </section>

            {/* Error banner */}
            {error && (
              <div style={{ padding: '14px 20px', borderRadius: '12px', background: '#fee2e2', color: '#b91c1c', marginBottom: '24px' }}>
                <span>{error}</span>
              </div>
            )}

            {/* Dining Packages Grid */}
            <section className="service-page__section" id="dining-catalog">
              <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: '24px' }}>
                <h2 className="service-page__heading" style={{ margin: 0 }}>Available Dining Packages & Menus</h2>
                {!loading && (
                  <span style={{ fontSize: '0.84rem', fontWeight: 700, color: '#854d0e', background: '#ffffff', padding: '4px 12px', borderRadius: '20px', border: '1px solid #fef08a', boxShadow: '0 1px 3px rgba(0,0,0,0.04)' }}>
                    {filteredPackages.length} {filteredPackages.length === 1 ? 'Package' : 'Packages'} Available
                  </span>
                )}
              </div>

              {/* Loading Skeleton */}
              {loading && (
                <div className="lodge-rooms-grid">
                  {[1, 2, 3].map((n) => (
                    <div key={n} className="lodge-card-skeleton">
                      <div className="lodge-skeleton-img" />
                      <div className="lodge-skeleton-body">
                        <div className="lodge-skeleton-line" style={{ width: '40%' }} />
                        <div className="lodge-skeleton-line" style={{ width: '80%', height: '22px' }} />
                        <div className="lodge-skeleton-line" style={{ width: '60%' }} />
                        <div className="lodge-skeleton-line" style={{ width: '100%', height: '36px', marginTop: 'auto' }} />
                      </div>
                    </div>
                  ))}
                </div>
              )}

              {/* Empty State */}
              {!loading && filteredPackages.length === 0 && (
                <div className="dashboard-empty-state" style={{ background: '#ffffff', borderRadius: '18px', border: '1px solid rgba(212, 160, 23, 0.2)' }}>
                  <div className="dashboard-empty-state__icon-wrap">
                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                      <path d="M18 8h1a4 4 0 0 1 0 8h-1" />
                      <path d="M2 8h16v9a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4V8z" />
                      <line x1="6" y1="1" x2="6" y2="4" />
                      <line x1="10" y1="1" x2="10" y2="4" />
                      <line x1="14" y1="1" x2="14" y2="4" />
                    </svg>
                  </div>
                  <h3 className="dashboard-empty-state__title">No Dining Packages Found</h3>
                  <p className="dashboard-empty-state__desc">No dining packages match your current filter settings. Reset filters to view all menus.</p>
                  <button type="button" className="service-page__cta-btn" onClick={() => { setSelectedPackage(null); setSelectedMealType('all'); }}>
                    Show All Menus
                  </button>
                </div>
              )}

              {/* Real Dining Cards */}
              {!loading && filteredPackages.length > 0 && (
                <div className="lodge-rooms-grid">
                  {filteredPackages.map((pkg) => {
                    const imgUrl = resolveDiningImgUrl(pkg.image_path || pkg.image);

                    return (
                      <div key={pkg.id} className="lodge-room-card" id={`dining-card-${pkg.id}`}>
                        {/* Image or Clean No-Photo State */}
                        <div className="lodge-room-img-container">
                          {imgUrl ? (
                            <img
                              src={imgUrl}
                              alt={pkg.name}
                              className="lodge-room-img"
                              loading="lazy"
                            />
                          ) : (
                            <div className="dynamic-card-no-img">
                              <div className="dynamic-card-no-img__icon">
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round">
                                  <path d="M18 8h1a4 4 0 0 1 0 8h-1" />
                                  <path d="M2 8h16v9a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4V8z" />
                                  <line x1="6" y1="1" x2="6" y2="4" />
                                  <line x1="10" y1="1" x2="10" y2="4" />
                                  <line x1="14" y1="1" x2="14" y2="4" />
                                </svg>
                              </div>
                              <span className="dynamic-card-no-img__title">{pkg.name}</span>
                              <span className="dynamic-card-no-img__tag">{pkg.meal_type || 'Restaurant & Dining'}</span>
                            </div>
                          )}
                          <span className="lodge-room-type-tag">
                            {pkg.meal_type || 'Restaurant & Dining'}
                          </span>
                        </div>

                        {/* Content */}
                        <div className="lodge-room-content">
                          <div className="lodge-room-header">
                            <h3 className="lodge-room-name">{pkg.name}</h3>
                          </div>

                          {pkg.description && (
                            <p className="lodge-room-desc">{pkg.description}</p>
                          )}

                          {/* Items chips */}
                          {Array.isArray(pkg.items) && pkg.items.length > 0 && (
                            <div className="lodge-room-amenities-row">
                              {pkg.items.slice(0, 3).map((item, idx) => (
                                <span key={idx} className="lodge-room-amenity-chip">
                                  <span>• {item.food_item?.name || item.name || item.food_item_name || 'Dish'}</span>
                                </span>
                              ))}
                              {pkg.items.length > 3 && (
                                <span className="lodge-room-amenity-chip lodge-room-amenity-chip--more">
                                  +{pkg.items.length - 3} more
                                </span>
                              )}
                            </div>
                          )}

                          {/* Footer: Meal Type info + Actions */}
                          <div className="lodge-room-footer">
                            <div className="lodge-room-price-block">
                              <span style={{ fontSize: '0.9rem', fontWeight: 700, color: 'var(--gold-700, #b45309)' }}>
                                {pkg.meal_type || 'Dining Package'}
                              </span>
                            </div>

                            <div className="lodge-room-actions">
                              <button
                                type="button"
                                className="lodge-btn-view"
                                onClick={() => onNavigate(`/restaurant/${pkg.id}`)}
                              >
                                View Details
                              </button>
                              <button
                                type="button"
                                className="lodge-btn-book"
                                onClick={() => handleOpenEnquire(pkg)}
                              >
                                Enquire Now
                              </button>
                            </div>
                          </div>
                        </div>
                      </div>
                    );
                  })}
                </div>
              )}
            </section>
          </div>
        </div>
      </main>

      {/* ENQUIRY MODAL */}
      {enquiringPackage && (
        <div className="lodge-modal-backdrop" onClick={() => !isSubmittingEnquiry && setEnquiringPackage(null)}>
          <div className="lodge-modal-dialog" onClick={(e) => e.stopPropagation()} style={{ maxWidth: '640px' }} role="dialog" aria-modal="true">
            <div className="lodge-modal-header">
              <div className="lodge-modal-title-group">
                <span className="lodge-modal-badge">{enquiringPackage.meal_type || 'Dining Reservation'}</span>
                <h2 className="lodge-modal-title">{enquiringPackage.name}</h2>
              </div>
              {!isSubmittingEnquiry && (
                <button
                  type="button"
                  className="lodge-modal-close-btn"
                  onClick={() => setEnquiringPackage(null)}
                  aria-label="Close dialog"
                >
                  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                    <line x1="18" y1="6" x2="6" y2="18" />
                    <line x1="6" y1="6" x2="18" y2="18" />
                  </svg>
                </button>
              )}
            </div>

            <div className="lodge-modal-body">
              {enquirySuccess ? (
                <div style={{ textAlign: 'center', padding: '24px 12px' }}>
                  <div style={{ width: '60px', height: '60px', borderRadius: '50%', background: '#dcfce7', color: '#16a34a', display: 'flex', alignItems: 'center', justifyContent: 'center', margin: '0 auto 16px' }}>
                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5">
                      <polyline points="20 6 9 17 4 12" />
                    </svg>
                  </div>
                  <h3 style={{ fontFamily: 'var(--font-serif)', fontSize: '1.3rem', color: 'var(--text-primary)', marginBottom: '8px' }}>
                    Reservation Enquiry Registered
                  </h3>
                  <p style={{ color: 'var(--text-secondary)', fontSize: '0.92rem', lineHeight: '1.6', marginBottom: '16px' }}>
                    {enquirySuccess.message}
                  </p>
                  <div style={{ background: '#f8fafc', padding: '12px 20px', borderRadius: '10px', border: '1px solid #e2e8f0', display: 'inline-block', marginBottom: '20px' }}>
                    <span style={{ fontSize: '0.78rem', color: '#64748b', textTransform: 'uppercase', letterSpacing: '0.05em', display: 'block' }}>
                      Booking Reference Code
                    </span>
                    <strong style={{ fontSize: '1.15rem', color: '#0f172a' }}>{enquirySuccess.reference}</strong>
                  </div>
                  <div>
                    <button
                      type="button"
                      className="lodge-btn-book"
                      style={{ padding: '10px 24px' }}
                      onClick={() => setEnquiringPackage(null)}
                    >
                      Done
                    </button>
                  </div>
                </div>
              ) : (
                <form onSubmit={handleEnquirySubmit} noValidate>
                  {enquiryFormErrors.submit && (
                    <div style={{ padding: '10px 14px', borderRadius: '8px', background: '#fee2e2', color: '#b91c1c', fontSize: '0.85rem', marginBottom: '16px' }}>
                      {enquiryFormErrors.submit}
                    </div>
                  )}

                  <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(220px, 1fr))', gap: '14px', marginBottom: '14px' }}>
                    {/* Name */}
                    <div className="lodge-field-group">
                      <label className="lodge-field-label">Your Name *</label>
                      <input
                        type="text"
                        className="lodge-field-input"
                        placeholder="e.g. Ramesh Sundaram"
                        value={enquiryFormData.customer_name}
                        onChange={(e) => {
                          setEnquiryFormData({ ...enquiryFormData, customer_name: e.target.value });
                          if (enquiryFormErrors.customer_name) setEnquiryFormErrors((prev) => ({ ...prev, customer_name: undefined }));
                        }}
                      />
                      {enquiryFormErrors.customer_name && (
                        <span style={{ color: '#dc2626', fontSize: '0.75rem', marginTop: '4px', display: 'block' }}>{enquiryFormErrors.customer_name}</span>
                      )}
                    </div>

                    {/* Phone */}
                    <div className="lodge-field-group">
                      <label className="lodge-field-label">Phone Number *</label>
                      <input
                        type="tel"
                        className="lodge-field-input"
                        placeholder="e.g. 9876543210"
                        value={enquiryFormData.customer_phone}
                        onChange={(e) => {
                          setEnquiryFormData({ ...enquiryFormData, customer_phone: e.target.value });
                          if (enquiryFormErrors.customer_phone) setEnquiryFormErrors((prev) => ({ ...prev, customer_phone: undefined }));
                        }}
                      />
                      {enquiryFormErrors.customer_phone && (
                        <span style={{ color: '#dc2626', fontSize: '0.75rem', marginTop: '4px', display: 'block' }}>{enquiryFormErrors.customer_phone}</span>
                      )}
                    </div>
                  </div>

                  <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(220px, 1fr))', gap: '14px', marginBottom: '14px' }}>
                    {/* Email */}
                    <div className="lodge-field-group">
                      <label className="lodge-field-label">Email Address</label>
                      <input
                        type="email"
                        className="lodge-field-input"
                        placeholder="e.g. ramesh@gmail.com"
                        value={enquiryFormData.customer_email}
                        onChange={(e) => setEnquiryFormData({ ...enquiryFormData, customer_email: e.target.value })}
                      />
                    </div>

                    {/* Meal Type */}
                    <div className="lodge-field-group">
                      <label className="lodge-field-label">Meal Session</label>
                      <select
                        className="lodge-field-select"
                        value={enquiryFormData.meal_type}
                        onChange={(e) => setEnquiryFormData({ ...enquiryFormData, meal_type: e.target.value })}
                      >
                        <option value="Dinner">Dinner (7 PM - 11 PM)</option>
                        <option value="Lunch">Lunch (11 AM - 3:30 PM)</option>
                        <option value="Breakfast">Breakfast (7 AM - 10:30 AM)</option>
                        <option value="High Tea">High Tea (4 PM - 6:30 PM)</option>
                        <option value="Buffet">Special Buffet</option>
                      </select>
                    </div>
                  </div>

                  <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(180px, 1fr))', gap: '14px', marginBottom: '14px' }}>
                    {/* Date */}
                    <div className="lodge-field-group">
                      <label className="lodge-field-label">Dining Date</label>
                      <DatePicker
                        className="lodge-field-input"
                        value={enquiryFormData.dining_date}
                        onChange={(e) => setEnquiryFormData({ ...enquiryFormData, dining_date: e.target.value })}
                        placeholder="Select Dining Date"
                      />
                    </div>

                    {/* Time */}
                    <div className="lodge-field-group">
                      <label className="lodge-field-label">Preferred Time</label>
                      <input
                        type="time"
                        className="lodge-field-input"
                        value={enquiryFormData.dining_time}
                        onChange={(e) => setEnquiryFormData({ ...enquiryFormData, dining_time: e.target.value })}
                      />
                    </div>

                    {/* Guests */}
                    <div className="lodge-field-group">
                      <label className="lodge-field-label">Guest Count</label>
                      <input
                        type="number"
                        min="1"
                        max="300"
                        className="lodge-field-input"
                        value={enquiryFormData.guest_count}
                        onChange={(e) => setEnquiryFormData({ ...enquiryFormData, guest_count: e.target.value })}
                      />
                    </div>
                  </div>

                  {/* Requirements */}
                  <div className="lodge-field-group" style={{ marginBottom: '20px' }}>
                    <label className="lodge-field-label">Special Dietary Requests / Notes</label>
                    <textarea
                      rows={3}
                      className="lodge-field-input"
                      style={{ resize: 'vertical' }}
                      placeholder="e.g. Vegetarian preference, table arrangement request, allergy notes..."
                      value={enquiryFormData.other_requirements}
                      onChange={(e) => setEnquiryFormData({ ...enquiryFormData, other_requirements: e.target.value })}
                    />
                  </div>

                  <button
                    type="submit"
                    disabled={isSubmittingEnquiry}
                    className="lodge-btn-book"
                    style={{ width: '100%', padding: '13px', fontSize: '1rem' }}
                  >
                    {isSubmittingEnquiry ? 'Submitting Reservation...' : 'Submit Dining Enquiry'}
                  </button>
                </form>
              )}
            </div>
          </div>
        </div>
      )}

      <Footer onNavigate={onNavigate} />
    </>
  );
};

export default RestaurantPage;
