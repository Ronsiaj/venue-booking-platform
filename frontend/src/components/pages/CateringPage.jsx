import { useState, useEffect, useCallback, useMemo } from 'react';
import Select from 'react-select';
import Header from '../Header';
import Footer from '../Footer';
import DatePicker from '../DatePicker';
import { cateringApi } from '../../services/cateringApi';
import { validateRequiredFields } from '../../services/formValidation';
import serviceCatering from '../../assets/images/service-catering.jpg';

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
const resolveCateringImgUrl = (imgPath) => {
  if (!imgPath) return null;
  if (typeof imgPath === 'string') {
    if (imgPath.startsWith('http') || imgPath.startsWith('blob:') || imgPath.startsWith('data:')) return imgPath;
    return `http://localhost/sm_groups/backend/${imgPath.replace(/^\/+/, '')}`;
  }
  return null;
};

const CateringPage = ({ onNavigate }) => {
  const [packages, setPackages] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  // Filters
  const [selectedPackage, setSelectedPackage] = useState(null);

  // Enquiry Modal state
  const [enquiringPackage, setEnquiringPackage] = useState(null);
  const [isSubmittingEnquiry, setIsSubmittingEnquiry] = useState(false);
  const [enquirySuccess, setEnquirySuccess] = useState(null);
  const [enquiryFormErrors, setEnquiryFormErrors] = useState({});
  const [enquiryFormData, setEnquiryFormData] = useState({
    customer_name: '',
    customer_phone: '',
    customer_email: '',
    event_type: 'Wedding Feast',
    event_date: '',
    event_time: 'Lunch',
    guest_count: 100,
    venue_address: '',
    other_requirements: '',
  });

  // Fetch catering packages from backend
  const fetchPackages = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await cateringApi.listPackages('limit=50&status=active');
      if (res.success && res.data?.data) {
        setPackages(res.data.data);
      } else if (res.success && Array.isArray(res.data)) {
        setPackages(res.data);
      } else {
        setPackages([]);
        if (res.error) setError(res.error);
      }
    } catch (err) {
      setError(err.message || 'Failed to load catering packages.');
      setPackages([]);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    fetchPackages();
  }, [fetchPackages]);

  // Package Options for React Select Dropdown
  const packageOptions = useMemo(() => {
    return packages.map((pkg) => ({
      value: pkg.id,
      label: pkg.name,
      pkg,
    }));
  }, [packages]);

  // Filtered packages
  const filteredPackages = useMemo(() => {
    return packages.filter((pkg) => {
      if (!selectedPackage) return true;
      return pkg.id === selectedPackage.value;
    });
  }, [packages, selectedPackage]);

  // Open Enquiry Modal
  const handleOpenEnquire = (pkg) => {
    setEnquiringPackage(pkg);
    setEnquirySuccess(null);
    setEnquiryFormErrors({});
    setEnquiryFormData({
      customer_name: '',
      customer_phone: '',
      customer_email: '',
      event_type: 'Wedding Feast',
      event_date: '',
      event_time: 'Lunch',
      guest_count: pkg.minimum_quantity || 100,
      venue_address: '',
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
        catering_package_id: Number(enquiringPackage.id),
        event_type: enquiryFormData.event_type,
        event_date: enquiryFormData.event_date || undefined,
        event_time: enquiryFormData.event_time || undefined,
        guest_count: Number(enquiryFormData.guest_count) || undefined,
        venue_address: enquiryFormData.venue_address.trim() || undefined,
        other_requirements: enquiryFormData.other_requirements.trim() || undefined,
      };

      const res = await cateringApi.createLead(payload);
      if (res.success) {
        setEnquirySuccess({
          reference: res.data?.data?.lead?.lead_reference || 'Ref Generated',
          message: 'Your catering enquiry has been received! Our culinary management team will get in touch with you shortly.',
        });
      } else {
        setEnquiryFormErrors({ submit: res.error || 'Failed to submit catering enquiry. Please try again.' });
      }
    } catch (err) {
      setEnquiryFormErrors({ submit: err.message || 'An unexpected error occurred.' });
    } finally {
      setIsSubmittingEnquiry(false);
    }
  };

  return (
    <>
      <Header onNavigate={onNavigate} currentPage="/catering" />

      <main>
        {/* Hero Section */}
        <div className="page-hero page-hero--service">
          <img src={serviceCatering} alt="SM Groups Catering Services" className="page-hero__bg-img" />
          <div className="page-hero__overlay" />
          <div className="page-hero__content">
            <span className="page-hero__badge">Gourmet Catering</span>
            <h1 className="page-hero__title">Grand Catering & Feasts</h1>
            <p className="page-hero__desc">
              Authentic South Indian banana leaf feasts, traditional wedding menus, and multi-cuisine buffet spreads curated for unforgettable celebrations.
            </p>
          </div>
        </div>

        <div className="service-page">
          <div className="service-page__container">

            {/* Dynamic Search & Filter Bar with React Select */}
            <section className="lodge-search-wrapper" style={{ maxWidth: '480px', marginLeft: 'auto', padding: '16px 20px', marginBottom: '32px' }}>
              <div className="lodge-search-grid" style={{ gridTemplateColumns: selectedPackage ? 'minmax(200px, 1fr) auto' : '1fr' }}>
                <div className="lodge-field-group">
                  <label className="lodge-field-label" htmlFor="catering-package-select">
                    <span>Select Catering Package</span>
                  </label>
                  <Select
                    inputId="catering-package-select"
                    options={packageOptions}
                    value={selectedPackage}
                    onChange={(selected) => setSelectedPackage(selected)}
                    placeholder="Select Catering Package..."
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
                {selectedPackage && (
                  <div style={{ display: 'flex', alignItems: 'flex-end' }}>
                    <button
                      type="button"
                      className="lodge-btn-reset"
                      style={{ height: '42px', padding: '0 20px' }}
                      onClick={() => setSelectedPackage(null)}
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

            {/* Packages Grid */}
            <section className="service-page__section" id="catering-packages-catalog">
              <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: '24px' }}>
                <h2 className="service-page__heading" style={{ margin: 0 }}>Catering Packages & Menus</h2>
                {!loading && (
                  <span style={{ fontSize: '0.84rem', fontWeight: 700, color: '#854d0e', background: '#ffffff', padding: '4px 12px', borderRadius: '20px', border: '1px solid #fef08a', boxShadow: '0 1px 3px rgba(0,0,0,0.04)' }}>
                    {filteredPackages.length} {filteredPackages.length === 1 ? 'Package' : 'Packages'} Available
                  </span>
                )}
              </div>

              {/* Loading Skeleton */}
              {loading && (
                <div className="lodge-rooms-grid">
                  {[1, 2].map((n) => (
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
                      <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9" />
                      <path d="M13.73 21a2 2 0 0 1-3.46 0" />
                    </svg>
                  </div>
                  <h3 className="dashboard-empty-state__title">No Catering Packages Found</h3>
                  <p className="dashboard-empty-state__desc">No packages matched your query. Try resetting your search filter.</p>
                  <button type="button" className="service-page__cta-btn" onClick={() => setSelectedPackage(null)}>
                    View All Packages
                  </button>
                </div>
              )}

              {/* Real Package Cards */}
              {!loading && filteredPackages.length > 0 && (
                <div className="lodge-rooms-grid">
                  {filteredPackages.map((pkg) => {
                    const imgUrl = resolveCateringImgUrl(pkg.image_path);
                    const rateFormatted = pkg.rate ? `₹${Number(pkg.rate).toLocaleString('en-IN')}` : 'Custom Quote';
                    const rateUnit = pkg.rate_unit ? `per ${pkg.rate_unit.replace('_', ' ')}` : 'per person';

                    return (
                      <div key={pkg.id} className="lodge-room-card" id={`catering-card-${pkg.id}`}>
                        {/* Image Container or Clean No-Photo State */}
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
                                  <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9" /><path d="M13.73 21a2 2 0 0 1-3.46 0" />
                                </svg>
                              </div>
                              <span className="dynamic-card-no-img__title">{pkg.name}</span>
                              <span className="dynamic-card-no-img__tag">Catering Package</span>
                            </div>
                          )}
                          <span className="lodge-room-type-tag">Catering Package</span>
                          <span className="lodge-room-status-badge lodge-room-status-badge--available">
                            Active
                          </span>
                        </div>

                        {/* Card Body */}
                        <div className="lodge-room-content">
                          <div className="lodge-room-header">
                            <h3 className="lodge-room-title">{pkg.name}</h3>
                          </div>

                          {/* Capacity & Items Specs */}
                          <div className="lodge-room-specs">
                            <div className="lodge-room-spec-item" title="Minimum Order">
                              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                                <circle cx="9" cy="7" r="4" />
                              </svg>
                              <span>Min {pkg.minimum_quantity || 50} Guests</span>
                            </div>
                            {pkg.items && (
                              <div className="lodge-room-spec-item" title="Menu Items Count">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                                  <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9" />
                                </svg>
                                <span>{pkg.items.length} Course Items</span>
                              </div>
                            )}
                          </div>

                          {/* Item Highlights Chips */}
                          {pkg.items && pkg.items.length > 0 && (
                            <div className="lodge-room-amenities-row">
                              {pkg.items.slice(0, 3).map((item, idx) => (
                                <span key={idx} className="lodge-room-amenity-chip">
                                  <span style={{ width: '6px', height: '6px', borderRadius: '50%', background: item.food_type === 'veg' ? '#16a34a' : '#dc2626' }} />
                                  <span>{item.food_item_name}</span>
                                </span>
                              ))}
                              {pkg.items.length > 3 && (
                                <span className="lodge-room-amenity-chip lodge-room-amenity-chip--more">
                                  +{pkg.items.length - 3} more items
                                </span>
                              )}
                            </div>
                          )}

                          {/* Card Footer */}
                          <div className="lodge-room-footer">
                            <div className="lodge-room-price-block">
                              <div className="lodge-room-price-val">
                                {rateFormatted}
                              </div>
                              <div className="lodge-room-price-period">{rateUnit}</div>
                              {pkg.price_includes_gst && (
                                <div className="lodge-room-gst-info">✓ GST Included</div>
                              )}
                            </div>

                            <div className="lodge-room-actions">
                              <button
                                type="button"
                                className="lodge-btn-view"
                                onClick={() => onNavigate(`/catering/${pkg.id}`)}
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

      {/* Hall Enquiry Modal */}
      {enquiringPackage && (
        <div className="lodge-modal-backdrop" onClick={() => !isSubmittingEnquiry && setEnquiringPackage(null)}>
          <div className="lodge-modal-dialog" onClick={(e) => e.stopPropagation()} style={{ maxWidth: '640px' }} role="dialog" aria-modal="true">
            <div className="lodge-modal-header">
              <div className="lodge-modal-title-group">
                <span className="lodge-modal-badge">Catering Reservation</span>
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
                    Enquiry Submitted Successfully
                  </h3>
                  <p style={{ color: 'var(--text-secondary)', fontSize: '0.92rem', lineHeight: '1.6', marginBottom: '16px' }}>
                    {enquirySuccess.message}
                  </p>
                  <div style={{ background: '#f8fafc', padding: '12px 20px', borderRadius: '10px', border: '1px solid #e2e8f0', display: 'inline-block', marginBottom: '20px' }}>
                    <span style={{ fontSize: '0.78rem', color: '#64748b', textTransform: 'uppercase', letterSpacing: '0.05em', display: 'block' }}>
                      Lead Reference Code
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
                        placeholder="e.g. Senthil Kumar"
                        value={enquiryFormData.customer_name}
                        onChange={(e) => {
                          setEnquiryFormData({ ...enquiryFormData, customer_name: e.target.value });
                          if (enquiryFormErrors.customer_name) setEnquiryFormErrors(prev => ({ ...prev, customer_name: undefined }));
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
                          if (enquiryFormErrors.customer_phone) setEnquiryFormErrors(prev => ({ ...prev, customer_phone: undefined }));
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
                        placeholder="e.g. senthil@gmail.com"
                        value={enquiryFormData.customer_email}
                        onChange={(e) => setEnquiryFormData({ ...enquiryFormData, customer_email: e.target.value })}
                      />
                    </div>

                    {/* Event Type */}
                    <div className="lodge-field-group">
                      <label className="lodge-field-label">Event Type</label>
                      <select
                        className="lodge-field-select"
                        value={enquiryFormData.event_type}
                        onChange={(e) => setEnquiryFormData({ ...enquiryFormData, event_type: e.target.value })}
                      >
                        <option value="Wedding Feast">Wedding Feast</option>
                        <option value="Muhurtham Lunch">Muhurtham Lunch</option>
                        <option value="Reception Dinner">Reception Dinner</option>
                        <option value="Engagement Ceremony">Engagement Ceremony</option>
                        <option value="Birthday Celebration">Birthday Celebration</option>
                        <option value="Corporate Event">Corporate Event</option>
                        <option value="Other Feast">Other Feast</option>
                      </select>
                    </div>
                  </div>

                  <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(180px, 1fr))', gap: '14px', marginBottom: '14px' }}>
                    {/* Date */}
                    <div className="lodge-field-group">
                      <label className="lodge-field-label">Preferred Date</label>
                      <DatePicker
                        className="lodge-field-input"
                        value={enquiryFormData.event_date}
                        onChange={(e) => setEnquiryFormData({ ...enquiryFormData, event_date: e.target.value })}
                        placeholder="Select Event Date"
                      />
                    </div>

                    {/* Session */}
                    <div className="lodge-field-group">
                      <label className="lodge-field-label">Session / Time</label>
                      <select
                        className="lodge-field-select"
                        value={enquiryFormData.event_time}
                        onChange={(e) => setEnquiryFormData({ ...enquiryFormData, event_time: e.target.value })}
                      >
                        <option value="Lunch">Lunch (11 AM - 3 PM)</option>
                        <option value="Dinner">Dinner (7 PM - 11 PM)</option>
                        <option value="Breakfast">Breakfast (6 AM - 10 AM)</option>
                        <option value="High Tea">High Tea / Snacks (4 PM - 6 PM)</option>
                        <option value="Full Day Feast">Full Day Feast</option>
                      </select>
                    </div>

                    {/* Guests */}
                    <div className="lodge-field-group">
                      <label className="lodge-field-label">Expected Guests</label>
                      <input
                        type="number"
                        min={enquiringPackage.minimum_quantity || 25}
                        step="25"
                        className="lodge-field-input"
                        value={enquiryFormData.guest_count}
                        onChange={(e) => setEnquiryFormData({ ...enquiryFormData, guest_count: e.target.value })}
                      />
                    </div>
                  </div>

                  {/* Delivery Venue Address */}
                  <div className="lodge-field-group" style={{ marginBottom: '14px' }}>
                    <label className="lodge-field-label">Event Venue / Delivery Address</label>
                    <input
                      type="text"
                      className="lodge-field-input"
                      placeholder="e.g. SM Mahal, Tirupur or Home Address"
                      value={enquiryFormData.venue_address}
                      onChange={(e) => setEnquiryFormData({ ...enquiryFormData, venue_address: e.target.value })}
                    />
                  </div>

                  {/* Special Notes */}
                  <div className="lodge-field-group" style={{ marginBottom: '18px' }}>
                    <label className="lodge-field-label">Special Dietary Preferences / Notes</label>
                    <textarea
                      rows="2"
                      className="lodge-field-input"
                      placeholder="e.g. Vegetarian counters, live sweet stalls, special regional items..."
                      value={enquiryFormData.other_requirements}
                      onChange={(e) => setEnquiryFormData({ ...enquiryFormData, other_requirements: e.target.value })}
                    />
                  </div>

                  <div style={{ display: 'flex', justifyContent: 'flex-end', gap: '10px' }}>
                    <button
                      type="button"
                      className="lodge-btn-reset"
                      onClick={() => setEnquiringPackage(null)}
                      disabled={isSubmittingEnquiry}
                    >
                      Cancel
                    </button>
                    <button
                      type="submit"
                      className="lodge-btn-book"
                      disabled={isSubmittingEnquiry}
                    >
                      {isSubmittingEnquiry ? 'Submitting...' : 'Submit Catering Enquiry'}
                    </button>
                  </div>
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

export default CateringPage;
