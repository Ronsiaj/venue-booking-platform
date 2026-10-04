import { useState, useEffect, useCallback, useMemo } from 'react';
import Select from 'react-select';
import Header from '../Header';
import Footer from '../Footer';
import DatePicker from '../DatePicker';
import { hallApi } from '../../services/api';
import { validateRequiredFields } from '../../services/formValidation';
import serviceMahal from '../../assets/images/service-mahal.jpg';

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
const resolveHallImgUrl = (img) => {
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

// Facility Icon Mapper
const getFacilityIcon = (facilityName = '') => {
  const key = facilityName.toLowerCase();
  if (key.includes('air') || key.includes('ac')) {
    return (
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
        <path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6" />
      </svg>
    );
  }
  if (key.includes('cater') || key.includes('food') || key.includes('dining')) {
    return (
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
        <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9" /><path d="M13.73 21a2 2 0 0 1-3.46 0" />
      </svg>
    );
  }
  if (key.includes('park')) {
    return (
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
        <rect x="3" y="3" width="18" height="18" rx="4" /><path d="M9 17V7h4a3 3 0 0 1 0 6H9" />
      </svg>
    );
  }
  if (key.includes('sound') || key.includes('audio') || key.includes('mic')) {
    return (
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
        <polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5" /><path d="M19.07 4.93a10 10 0 0 1 0 14.14M15.54 8.46a5 5 0 0 1 0 7.07" />
      </svg>
    );
  }
  return (
    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
      <polyline points="20 6 9 17 4 12" />
    </svg>
  );
};

const HallsPage = ({ onNavigate }) => {
  const [halls, setHalls] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  // Filters
  const [selectedHall, setSelectedHall] = useState(null);
  const [selectedType, setSelectedType] = useState('all');

  // Enquiry Modal state
  const [enquiringHall, setEnquiringHall] = useState(null);
  const [isSubmittingEnquiry, setIsSubmittingEnquiry] = useState(false);
  const [enquirySuccess, setEnquirySuccess] = useState(null);
  const [enquiryFormErrors, setEnquiryFormErrors] = useState({});
  const [enquiryFormData, setEnquiryFormData] = useState({
    customer_name: '',
    customer_phone: '',
    customer_email: '',
    event_type: 'Wedding',
    event_date: '',
    event_time: 'Full Day',
    guest_count: 500,
    food_required: true,
    decoration_required: true,
    other_requirements: '',
  });

  // Fetch halls from backend
  const fetchHalls = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await hallApi.list('limit=50&status=available');
      if (res.success && res.data?.data) {
        setHalls(res.data.data);
      } else if (res.success && Array.isArray(res.data)) {
        setHalls(res.data);
      } else {
        setHalls([]);
        if (res.error) setError(res.error);
      }
    } catch (err) {
      setError(err.message || 'Failed to load halls.');
      setHalls([]);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    fetchHalls();
  }, [fetchHalls]);

  // Unique hall types
  const hallTypes = useMemo(() => {
    const types = new Set();
    halls.forEach((h) => {
      const typeName = h.hall_type?.name;
      if (typeName) types.add(typeName);
    });
    return Array.from(types);
  }, [halls]);

  // Dynamic Hall options for React Select
  const hallOptions = useMemo(() => {
    return halls.map((h) => ({
      value: h.id,
      label: h.name,
      hallType: h.hall_type?.name,
    }));
  }, [halls]);

  // Filtered halls
  const filteredHalls = useMemo(() => {
    return halls.filter((h) => {
      const matchHall = !selectedHall || h.id === selectedHall.value;
      const matchType =
        selectedType === 'all' ||
        (h.hall_type?.name && h.hall_type.name.toLowerCase() === selectedType.toLowerCase());
      return matchHall && matchType;
    });
  }, [halls, selectedHall, selectedType]);

  // Open Enquiry Modal
  const handleOpenEnquire = (hall) => {
    setEnquiringHall(hall);
    setEnquirySuccess(null);
    setEnquiryFormErrors({});
    setEnquiryFormData({
      customer_name: '',
      customer_phone: '',
      customer_email: '',
      event_type: hall.hall_type?.name?.includes('Party') ? 'Birthday / Party' : 'Wedding Reception',
      event_date: '',
      event_time: 'Full Day',
      guest_count: hall.capacity || 500,
      food_required: true,
      decoration_required: true,
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
        hall_id: Number(enquiringHall.id),
        event_type: enquiryFormData.event_type,
        event_date: enquiryFormData.event_date || undefined,
        event_time: enquiryFormData.event_time || undefined,
        guest_count: Number(enquiryFormData.guest_count) || undefined,
        food_required: enquiryFormData.food_required,
        decoration_required: enquiryFormData.decoration_required,
        other_requirements: enquiryFormData.other_requirements.trim() || undefined,
      };

      const res = await hallApi.createLead(payload);
      if (res.success) {
        setEnquirySuccess({
          reference: res.data?.data?.lead?.lead_reference || 'Ref Generated',
          message: 'Your hall enquiry has been registered successfully! Our event coordination team will contact you shortly.',
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
      <Header onNavigate={onNavigate} currentPage="/halls" />

      <main>
        {/* Hero Section */}
        <div className="page-hero page-hero--service">
          <img src={serviceMahal} alt="SM Groups Halls & Venues" className="page-hero__bg-img" />
          <div className="page-hero__overlay" />
          <div className="page-hero__content">
            <span className="page-hero__badge">Marriage & Event Venues</span>
            <h1 className="page-hero__title">Grand Halls & Celebrations</h1>
            <p className="page-hero__desc">
              Majestic, air-conditioned marriage and party halls engineered for unforgettable South Indian weddings, grand receptions, and celebrations.
            </p>
          </div>
        </div>

        <div className="service-page">
          <div className="service-page__container">

            {/* Filter Bar */}
            <section className="lodge-search-wrapper">
              <div className="lodge-search-grid">
                {/* Select Hall Dropdown */}
                <div className="lodge-field-group">
                  <label className="lodge-field-label" htmlFor="hall-select-filter">
                    <span>Select Hall</span>
                  </label>
                  <Select
                    inputId="hall-select-filter"
                    options={hallOptions}
                    value={selectedHall}
                    onChange={(selected) => setSelectedHall(selected)}
                    placeholder="Select Hall..."
                    isClearable
                    isSearchable
                    styles={reactSelectStyles}
                    className="react-select-container"
                    classNamePrefix="react-select"
                    noOptionsMessage={({ inputValue }) =>
                      inputValue ? `No venue matches "${inputValue}"` : 'No venues available'
                    }
                  />
                </div>

                {/* Hall Type Filter */}
                <div className="lodge-field-group">
                  <label className="lodge-field-label" htmlFor="hall-type-filter">
                    <span>Venue Category</span>
                  </label>
                  <select
                    id="hall-type-filter"
                    className="lodge-field-select"
                    value={selectedType}
                    onChange={(e) => setSelectedType(e.target.value)}
                  >
                    <option value="all">All Venue Types</option>
                    {hallTypes.map((type, idx) => (
                      <option key={idx} value={type}>{type}</option>
                    ))}
                  </select>
                </div>

                {/* Clear Filters */}
                {(selectedHall || selectedType !== 'all') && (
                  <div style={{ display: 'flex', alignItems: 'flex-end' }}>
                    <button
                      type="button"
                      className="lodge-btn-reset"
                      style={{ width: '100%', height: '42px' }}
                      onClick={() => {
                        setSelectedHall(null);
                        setSelectedType('all');
                      }}
                    >
                      Reset Filters
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

            {/* Halls Grid */}
            <section className="service-page__section" id="halls-catalog">
              <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: '24px' }}>
                <h2 className="service-page__heading" style={{ margin: 0 }}>Available Halls & Venues</h2>
                {!loading && (
                  <span style={{ fontSize: '0.84rem', fontWeight: 700, color: '#854d0e', background: '#ffffff', padding: '4px 12px', borderRadius: '20px', border: '1px solid #fef08a', boxShadow: '0 1px 3px rgba(0,0,0,0.04)' }}>
                    {filteredHalls.length} {filteredHalls.length === 1 ? 'Venue' : 'Venues'} Available
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
              {!loading && filteredHalls.length === 0 && (
                <div className="dashboard-empty-state" style={{ background: '#ffffff', borderRadius: '18px', border: '1px solid rgba(212, 160, 23, 0.2)' }}>
                  <div className="dashboard-empty-state__icon-wrap">
                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                      <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" />
                    </svg>
                  </div>
                  <h3 className="dashboard-empty-state__title">No Venues Found</h3>
                  <p className="dashboard-empty-state__desc">No halls match your current filter settings. Reset filters to view all venues.</p>
                  <button type="button" className="service-page__cta-btn" onClick={() => { setSearchQuery(''); setSelectedType('all'); }}>
                    Show All Halls
                  </button>
                </div>
              )}

              {/* Real Hall Cards */}
              {!loading && filteredHalls.length > 0 && (
                <div className="lodge-rooms-grid">
                  {filteredHalls.map((hall) => {
                    const primaryImg = hall.images?.find((img) => img.is_primary) || hall.images?.[0] || hall.image_path || hall.image;
                    const imgUrl = resolveHallImgUrl(primaryImg);

                    return (
                      <div key={hall.id} className="lodge-room-card" id={`hall-card-${hall.id}`}>
                        {/* Image or Clean No-Photo State */}
                        <div className="lodge-room-img-container">
                          {imgUrl ? (
                            <img
                              src={imgUrl}
                              alt={hall.name}
                              className="lodge-room-img"
                              loading="lazy"
                            />
                          ) : (
                            <div className="dynamic-card-no-img">
                              <div className="dynamic-card-no-img__icon">
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round">
                                  <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" />
                                  <polyline points="9 22 9 12 15 12 15 22" />
                                </svg>
                              </div>
                              <span className="dynamic-card-no-img__title">{hall.name}</span>
                              <span className="dynamic-card-no-img__tag">{hall.hall_type?.name || 'Grand Venue'}</span>
                            </div>
                          )}
                          <span className="lodge-room-type-tag">
                            {hall.hall_type?.name || 'Grand Venue'}
                          </span>
                        </div>

                        {/* Content */}
                        <div className="lodge-room-content">
                          <div className="lodge-room-header">
                            <h3 className="lodge-room-title">{hall.name}</h3>
                          </div>

                          {/* Key Specs */}
                          <div className="lodge-room-specs">
                            <div className="lodge-room-spec-item" title="Guest Capacity">
                              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                                <circle cx="9" cy="7" r="4" />
                              </svg>
                              <span>{hall.capacity ? `${hall.capacity} Guests` : 'On Request'}</span>
                            </div>
                            {hall.area && (
                              <div className="lodge-room-spec-item" title="Hall Area">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                                  <rect x="3" y="3" width="18" height="18" rx="2" />
                                </svg>
                                <span>{hall.area}</span>
                              </div>
                            )}
                          </div>

                          {/* Top 3 Facilities */}
                          {hall.facilities && hall.facilities.length > 0 && (
                            <div className="lodge-room-amenities-row">
                              {hall.facilities.slice(0, 3).map((fac, idx) => (
                                <span key={idx} className="lodge-room-amenity-chip">
                                  {getFacilityIcon(fac.name)}
                                  <span>{fac.name}</span>
                                </span>
                              ))}
                              {hall.facilities.length > 3 && (
                                <span className="lodge-room-amenity-chip lodge-room-amenity-chip--more">
                                  +{hall.facilities.length - 3} more
                                </span>
                              )}
                            </div>
                          )}

                          {/* Footer: Price + Actions */}
                          <div className="lodge-room-footer">
                            <div className="lodge-room-price-block">
                              <div className="lodge-room-price-val">
                                {hall.price_description || 'Custom Quote'}
                              </div>
                            </div>

                            <div className="lodge-room-actions">
                              <button
                                type="button"
                                className="lodge-btn-view"
                                onClick={() => onNavigate(`/halls/${hall.id}`)}
                              >
                                View Details
                              </button>
                              <button
                                type="button"
                                className="lodge-btn-book"
                                onClick={() => handleOpenEnquire(hall)}
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
      {enquiringHall && (
        <div className="lodge-modal-backdrop" onClick={() => !isSubmittingEnquiry && setEnquiringHall(null)}>
          <div className="lodge-modal-dialog" onClick={(e) => e.stopPropagation()} style={{ maxWidth: '640px' }} role="dialog" aria-modal="true">
            <div className="lodge-modal-header">
              <div className="lodge-modal-title-group">
                <span className="lodge-modal-badge">Booking Enquiry</span>
                <h2 className="lodge-modal-title">{enquiringHall.name}</h2>
              </div>
              {!isSubmittingEnquiry && (
                <button
                  type="button"
                  className="lodge-modal-close-btn"
                  onClick={() => setEnquiringHall(null)}
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
                      onClick={() => setEnquiringHall(null)}
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
                        <option value="Traditional Wedding">Traditional Wedding</option>
                        <option value="Wedding Reception">Wedding Reception</option>
                        <option value="Engagement Ceremony">Engagement Ceremony</option>
                        <option value="Birthday Celebration">Birthday Celebration</option>
                        <option value="Corporate Event">Corporate Event</option>
                        <option value="Other Function">Other Function</option>
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
                        placeholder="Select Date"
                      />
                    </div>

                    {/* Session */}
                    <div className="lodge-field-group">
                      <label className="lodge-field-label">Time Slot</label>
                      <select
                        className="lodge-field-select"
                        value={enquiryFormData.event_time}
                        onChange={(e) => setEnquiryFormData({ ...enquiryFormData, event_time: e.target.value })}
                      >
                        <option value="Morning (6 AM - 2 PM)">Morning (6 AM - 2 PM)</option>
                        <option value="Evening (4 PM - 11 PM)">Evening (4 PM - 11 PM)</option>
                        <option value="Full Day">Full Day (24 Hrs)</option>
                      </select>
                    </div>

                    {/* Guests */}
                    <div className="lodge-field-group">
                      <label className="lodge-field-label">Expected Guests</label>
                      <input
                        type="number"
                        min="50"
                        step="50"
                        className="lodge-field-input"
                        value={enquiryFormData.guest_count}
                        onChange={(e) => setEnquiryFormData({ ...enquiryFormData, guest_count: e.target.value })}
                      />
                    </div>
                  </div>

                  {/* Requirements Checkboxes */}
                  <div style={{ display: 'flex', gap: '20px', marginBottom: '14px', padding: '10px 0' }}>
                    <label style={{ display: 'flex', alignItems: 'center', gap: '8px', fontSize: '0.85rem', cursor: 'pointer' }}>
                      <input
                        type="checkbox"
                        checked={enquiryFormData.food_required}
                        onChange={(e) => setEnquiryFormData({ ...enquiryFormData, food_required: e.target.checked })}
                      />
                      <span>Catering / Food Required</span>
                    </label>
                    <label style={{ display: 'flex', alignItems: 'center', gap: '8px', fontSize: '0.85rem', cursor: 'pointer' }}>
                      <input
                        type="checkbox"
                        checked={enquiryFormData.decoration_required}
                        onChange={(e) => setEnquiryFormData({ ...enquiryFormData, decoration_required: e.target.checked })}
                      />
                      <span>Decoration Required</span>
                    </label>
                  </div>

                  {/* Special Notes */}
                  <div className="lodge-field-group" style={{ marginBottom: '18px' }}>
                    <label className="lodge-field-label">Special Requirements / Notes</label>
                    <textarea
                      rows="2"
                      className="lodge-field-input"
                      placeholder="Additional details about your event..."
                      value={enquiryFormData.other_requirements}
                      onChange={(e) => setEnquiryFormData({ ...enquiryFormData, other_requirements: e.target.value })}
                    />
                  </div>

                  <div style={{ display: 'flex', justifyContent: 'flex-end', gap: '10px' }}>
                    <button
                      type="button"
                      className="lodge-btn-view"
                      disabled={isSubmittingEnquiry}
                      onClick={() => setEnquiringHall(null)}
                    >
                      Cancel
                    </button>
                    <button
                      type="submit"
                      className="lodge-btn-book"
                      disabled={isSubmittingEnquiry}
                    >
                      {isSubmittingEnquiry ? 'Submitting...' : 'Submit Enquiry'}
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

export default HallsPage;
