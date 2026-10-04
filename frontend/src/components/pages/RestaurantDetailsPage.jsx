import { useState, useEffect, useCallback } from 'react';
import Header from '../Header';
import Footer from '../Footer';
import DatePicker from '../DatePicker';
import { diningApi } from '../../services/api';
import { validateRequiredFields } from '../../services/formValidation';

// Image URL Resolver - only returns valid backend URLs, never substitutes random stock photos
const resolveDiningImgUrl = (imgPath) => {
  if (!imgPath) return null;
  if (typeof imgPath === 'string') {
    if (imgPath.startsWith('http') || imgPath.startsWith('blob:') || imgPath.startsWith('data:')) return imgPath;
    return `http://localhost/sm_groups/backend/${imgPath.replace(/^\/+/, '')}`;
  }
  return null;
};

const RestaurantDetailsPage = ({ packageId, onNavigate }) => {
  const [pkg, setPkg] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  // Enquiry Form State
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [enquirySuccess, setEnquirySuccess] = useState(null);
  const [formErrors, setFormErrors] = useState({});
  const [formData, setFormData] = useState({
    customer_name: '',
    customer_phone: '',
    customer_email: '',
    meal_type: 'Dinner',
    dining_date: '',
    dining_time: '19:30',
    guest_count: 4,
    other_requirements: '',
  });

  // Fetch Dining Package Details from Backend
  const fetchPackageDetails = useCallback(async () => {
    if (!packageId) return;
    setLoading(true);
    setError(null);
    try {
      const res = await diningApi.viewPackage(packageId);
      if (res.success && res.data?.data) {
        setPkg(res.data.data);
        if (res.data.data.meal_type) {
          setFormData((prev) => ({ ...prev, meal_type: res.data.data.meal_type }));
        }
      } else if (res.success && res.data?.id) {
        setPkg(res.data);
      } else {
        setError(res.error || 'Dining package not found.');
      }
    } catch (err) {
      setError(err.message || 'Failed to load dining package details.');
    } finally {
      setLoading(false);
    }
  }, [packageId]);

  useEffect(() => {
    fetchPackageDetails();
  }, [fetchPackageDetails]);

  // Handle Submit Dining Enquiry
  const handleSubmitEnquiry = async (e) => {
    e.preventDefault();
    const validation = validateRequiredFields(formData, ['customer_name', 'customer_phone']);
    if (!validation.isValid) {
      setFormErrors(validation.errors);
      return;
    }

    setIsSubmitting(true);
    setFormErrors({});
    try {
      const payload = {
        customer_name: formData.customer_name.trim(),
        customer_phone: formData.customer_phone.trim(),
        customer_email: formData.customer_email.trim() || undefined,
        dining_package_id: Number(packageId),
        meal_type: formData.meal_type || pkg?.meal_type || 'Dinner',
        dining_date: formData.dining_date || undefined,
        dining_time: formData.dining_time || undefined,
        guest_count: Number(formData.guest_count) || 4,
        other_requirements: formData.other_requirements.trim() || undefined,
      };

      const res = await diningApi.createLead(payload);
      if (res.success) {
        setEnquirySuccess({
          reference: res.data?.lead_id || res.data?.id || res.data?.data?.id || 'Ref #' + Math.floor(1000 + Math.random() * 9000),
          message: 'Your dining reservation enquiry has been submitted successfully! Our restaurant team will contact you shortly.',
        });
      } else {
        setFormErrors({ submit: res.error || 'Failed to submit enquiry. Please try again.' });
      }
    } catch (err) {
      setFormErrors({ submit: err.message || 'An unexpected error occurred.' });
    } finally {
      setIsSubmitting(false);
    }
  };

  const imgUrl = resolveDiningImgUrl(pkg?.image_path);
  const totalItemsCount = pkg?.total_items || (Array.isArray(pkg?.items) ? pkg.items.length : 0);

  // Group items by category if available
  const itemsByCategory = Array.isArray(pkg?.items) ? pkg.items.reduce((acc, item) => {
    const catName = item.category?.name || 'Special Dishes & Delicacies';
    if (!acc[catName]) acc[catName] = [];
    acc[catName].push(item);
    return acc;
  }, {}) : {};

  return (
    <div className="hall-details">
      <Header onNavigate={onNavigate} currentPage="/restaurant" />

      <main className="hall-details__inner">
        {/* Back Link */}
        <button
          type="button"
          className="hall-details__back-btn"
          onClick={() => onNavigate('/restaurant')}
        >
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round">
            <line x1="19" y1="12" x2="5" y2="12" />
            <polyline points="12 19 5 12 12 5" />
          </svg>
          <span>Back to Restaurant & Dining</span>
        </button>

        {/* Loading State */}
        {loading && (
          <div className="hall-details__loading">
            <div className="hall-details__spinner" />
            <p style={{ fontSize: '1.05rem', fontWeight: 600 }}>Loading Dining Package Details...</p>
          </div>
        )}

        {/* Error State */}
        {!loading && error && (
          <div className="hall-details__error-state">
            <span style={{ fontSize: '2.5rem', display: 'block', marginBottom: '12px' }}>⚠️</span>
            <h2 style={{ color: '#dc2626', marginBottom: '8px', fontFamily: 'var(--font-serif)' }}>Unable to Load Menu</h2>
            <p style={{ color: 'var(--text-muted)', marginBottom: '24px' }}>{error}</p>
            <button type="button" className="lodge-btn-book" style={{ padding: '12px 28px' }} onClick={() => onNavigate('/restaurant')}>
              Return to Restaurant & Dining
            </button>
          </div>
        )}

        {/* 2-Column Dedicated Layout */}
        {!loading && !error && pkg && (
          <div className="hall-details__grid">
            {/* LEFT COLUMN: Showcase & Specs */}
            <div>
              {/* Primary Image or Clean Image-Free State */}
              {imgUrl ? (
                <div className="hall-details__showcase">
                  <img
                    src={imgUrl}
                    alt={pkg.name}
                  />
                  <span className="hall-details__showcase-badge">
                    {pkg.meal_type || 'Restaurant & Dining'}
                  </span>
                </div>
              ) : (
                <div className="hall-details__showcase" style={{ minHeight: '320px', display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center', background: 'linear-gradient(135deg, #fdfbf7 0%, #f5efe6 100%)', border: '1px solid rgba(212, 160, 23, 0.25)', borderRadius: '18px', padding: '32px', textAlign: 'center', position: 'relative' }}>
                  <div style={{ width: '60px', height: '60px', borderRadius: '50%', background: '#ffffff', border: '1.5px solid rgba(212, 160, 23, 0.3)', display: 'flex', alignItems: 'center', justifyContent: 'center', color: '#d4a017', marginBottom: '14px', boxShadow: '0 8px 20px rgba(212, 160, 23, 0.12)' }}>
                    <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round">
                      <path d="M18 8h1a4 4 0 0 1 0 8h-1" />
                      <path d="M2 8h16v9a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4V8z" />
                      <line x1="6" y1="1" x2="6" y2="4" />
                      <line x1="10" y1="1" x2="10" y2="4" />
                      <line x1="14" y1="1" x2="14" y2="4" />
                    </svg>
                  </div>
                  <h3 style={{ fontFamily: 'var(--font-serif)', fontSize: '1.4rem', fontWeight: 700, color: 'var(--text-primary)', margin: '0 0 6px' }}>{pkg.name}</h3>
                  <p style={{ color: 'var(--text-muted)', fontSize: '0.88rem', margin: 0 }}>SM Groups Restaurant & Fine Dining</p>
                  <span className="hall-details__showcase-badge" style={{ position: 'absolute', top: '16px', left: '16px' }}>
                    {pkg.meal_type || 'Restaurant & Dining'}
                  </span>
                </div>
              )}

              {/* Title & Meal Category */}
              <div className="hall-details__title-area">
                <h1 className="hall-details__name">{pkg.name}</h1>
                <div className="hall-details__price-row">
                  <span className="hall-details__price" style={{ fontSize: '1.4rem', color: 'var(--gold-600, #b45309)' }}>
                    {pkg.meal_type || 'Gourmet Dining Experience'}
                  </span>
                  <span className="hall-details__price-note">
                    Authentic South Indian & Multi-Cuisine
                  </span>
                </div>
              </div>

              {/* 4 Feature Highlight Cards */}
              <div className="hall-details__highlights">
                {/* Meal Category */}
                <div className="hall-details__highlight-card">
                  <div className="hall-details__highlight-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                      <circle cx="12" cy="12" r="10" />
                      <polyline points="12 6 12 12 16 14" />
                    </svg>
                  </div>
                  <span className="hall-details__highlight-label">Meal Category</span>
                  <span className="hall-details__highlight-value">{pkg.meal_type || 'Full Dining'}</span>
                </div>

                {/* Course Items */}
                <div className="hall-details__highlight-card">
                  <div className="hall-details__highlight-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                      <path d="M18 8h1a4 4 0 0 1 0 8h-1" />
                      <path d="M2 8h16v9a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4V8z" />
                    </svg>
                  </div>
                  <span className="hall-details__highlight-label">Included Dishes</span>
                  <span className="hall-details__highlight-value">{totalItemsCount > 0 ? `${totalItemsCount} Curated Dishes` : 'Multi-Course'}</span>
                </div>

                {/* Ambience & Service */}
                <div className="hall-details__highlight-card">
                  <div className="hall-details__highlight-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                      <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2" />
                    </svg>
                  </div>
                  <span className="hall-details__highlight-label">Dining Style</span>
                  <span className="hall-details__highlight-value">Fine Dine & Table Service</span>
                </div>

                {/* Status */}
                <div className="hall-details__highlight-card">
                  <div className="hall-details__highlight-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                      <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
                      <polyline points="22 4 12 14.01 9 11.01" />
                    </svg>
                  </div>
                  <span className="hall-details__highlight-label">Availability</span>
                  <span className="hall-details__highlight-value">{pkg.status === 'active' || !pkg.status ? '✓ Available Daily' : 'Seasonal / On Request'}</span>
                </div>
              </div>

              {/* Package Description */}
              {pkg.description && (
                <div className="hall-details__desc-block">
                  <h3 className="hall-details__section-title">About This Dining Experience</h3>
                  <p className="hall-details__desc-text">{pkg.description}</p>
                </div>
              )}

              {/* Included Food Items Breakdown */}
              {Array.isArray(pkg.items) && pkg.items.length > 0 && (
                <div className="hall-details__facilities-block">
                  <h3 className="hall-details__section-title">
                    Included Dishes & Specialties ({pkg.items.length})
                  </h3>

                  {Object.entries(itemsByCategory).map(([categoryName, catItems], catIdx) => (
                    <div key={catIdx} style={{ marginBottom: '20px' }}>
                      <h4 style={{ fontSize: '0.92rem', fontWeight: 700, color: 'var(--gold-700, #b45309)', textTransform: 'uppercase', letterSpacing: '0.04em', margin: '0 0 10px 0', borderBottom: '1px solid rgba(212, 160, 23, 0.2)', paddingBottom: '6px' }}>
                        {categoryName}
                      </h4>
                      <div className="hall-details__facilities-grid">
                        {catItems.map((item, i) => {
                          const food = item.food_item || item;
                          const isVeg = (food.food_type || item.food_type || '').toLowerCase().includes('veg') && !(food.food_type || item.food_type || '').toLowerCase().includes('non');
                          const dishImg = resolveDiningImgUrl(food.image_path || item.image_path);

                          return (
                            <div key={i} className="hall-details__facility-item" style={{ flexDirection: 'column', alignItems: 'flex-start', gap: '6px' }}>
                              <div style={{ display: 'flex', alignItems: 'center', gap: '8px', width: '100%' }}>
                                <span
                                  style={{
                                    width: '8px',
                                    height: '8px',
                                    borderRadius: '50%',
                                    background: isVeg ? '#16a34a' : '#dc2626',
                                    flexShrink: 0,
                                  }}
                                  title={isVeg ? 'Vegetarian' : 'Non-Vegetarian'}
                                />
                                <span className="hall-details__facility-name" style={{ fontWeight: 700 }}>
                                  {food.name || item.food_item_name || 'Gourmet Dish'}
                                </span>
                                {item.quantity > 1 && (
                                  <span style={{ fontSize: '0.75rem', fontWeight: 600, color: 'var(--text-muted)', marginLeft: 'auto' }}>
                                    Qty: {item.quantity}
                                  </span>
                                )}
                              </div>
                              {dishImg && (
                                <img
                                  src={dishImg}
                                  alt={food.name || 'Dish'}
                                  style={{ width: '100%', height: '110px', objectFit: 'cover', borderRadius: '8px', marginTop: '4px' }}
                                />
                              )}
                              {food.description && (
                                <span style={{ fontSize: '0.8rem', color: 'var(--text-muted)', lineHeight: '1.4', paddingLeft: '16px' }}>
                                  {food.description}
                                </span>
                              )}
                            </div>
                          );
                        })}
                      </div>
                    </div>
                  ))}
                </div>
              )}
            </div>

            {/* RIGHT COLUMN: Dining Enquiry Card */}
            <div className="hall-details__enquiry-wrapper">
              <div className="hall-details__enquiry-card">
                <h2 className="hall-details__enquiry-title">Reserve Your Table</h2>

                {enquirySuccess ? (
                  <div className="hall-details__success">
                    <div className="hall-details__success-icon">
                      <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5">
                        <polyline points="20 6 9 17 4 12" />
                      </svg>
                    </div>
                    <h3 className="hall-details__success-title">Enquiry Submitted!</h3>
                    <p className="hall-details__success-msg">{enquirySuccess.message}</p>
                    <div className="hall-details__ref-box">
                      <span className="hall-details__ref-label">Reference ID</span>
                      <span className="hall-details__ref-value">{enquirySuccess.reference}</span>
                    </div>
                    <div>
                      <button
                        type="button"
                        className="lodge-btn-view"
                        style={{ width: '100%', padding: '12px' }}
                        onClick={() => {
                          setEnquirySuccess(null);
                          setFormData({
                            customer_name: '',
                            customer_phone: '',
                            customer_email: '',
                            meal_type: pkg?.meal_type || 'Dinner',
                            dining_date: '',
                            dining_time: '19:30',
                            guest_count: 4,
                            other_requirements: '',
                          });
                        }}
                      >
                        Submit Another Reservation
                      </button>
                    </div>
                  </div>
                ) : (
                  <form onSubmit={handleSubmitEnquiry} noValidate>
                    {formErrors.submit && (
                      <div className="hall-details__error-banner">{formErrors.submit}</div>
                    )}

                    {/* Customer Name */}
                    <div className="hall-details__field">
                      <label className="hall-details__field-label">Your Full Name *</label>
                      <input
                        type="text"
                        placeholder="e.g. Ramesh Sundaram"
                        value={formData.customer_name}
                        onChange={(e) => {
                          setFormData({ ...formData, customer_name: e.target.value });
                          if (formErrors.customer_name) setFormErrors((prev) => ({ ...prev, customer_name: undefined }));
                        }}
                        className={`hall-details__field-input ${formErrors.customer_name ? 'hall-details__field-input--error' : ''}`}
                      />
                      {formErrors.customer_name && (
                        <span className="hall-details__field-error">{formErrors.customer_name}</span>
                      )}
                    </div>

                    {/* Phone Number */}
                    <div className="hall-details__field">
                      <label className="hall-details__field-label">Mobile Phone Number *</label>
                      <input
                        type="tel"
                        placeholder="e.g. 9876543210"
                        value={formData.customer_phone}
                        onChange={(e) => {
                          setFormData({ ...formData, customer_phone: e.target.value });
                          if (formErrors.customer_phone) setFormErrors((prev) => ({ ...prev, customer_phone: undefined }));
                        }}
                        className={`hall-details__field-input ${formErrors.customer_phone ? 'hall-details__field-input--error' : ''}`}
                      />
                      {formErrors.customer_phone && (
                        <span className="hall-details__field-error">{formErrors.customer_phone}</span>
                      )}
                    </div>

                    {/* Email Address */}
                    <div className="hall-details__field">
                      <label className="hall-details__field-label">Email Address</label>
                      <input
                        type="email"
                        placeholder="e.g. ramesh@gmail.com"
                        value={formData.customer_email}
                        onChange={(e) => setFormData({ ...formData, customer_email: e.target.value })}
                        className="hall-details__field-input"
                      />
                    </div>

                    {/* Meal Type & Dining Date */}
                    <div className="hall-details__field-row">
                      <div>
                        <label className="hall-details__field-label">Meal Session</label>
                        <select
                          value={formData.meal_type}
                          onChange={(e) => setFormData({ ...formData, meal_type: e.target.value })}
                          className="hall-details__field-select"
                        >
                          <option value="Dinner">Dinner (7 PM - 11 PM)</option>
                          <option value="Lunch">Lunch (11 AM - 3:30 PM)</option>
                          <option value="Breakfast">Breakfast (7 AM - 10:30 AM)</option>
                          <option value="High Tea">High Tea (4 PM - 6:30 PM)</option>
                          <option value="Buffet">Special Buffet</option>
                        </select>
                      </div>
                      <div>
                        <label className="hall-details__field-label">Preferred Date</label>
                        <DatePicker
                          value={formData.dining_date}
                          onChange={(e) => setFormData({ ...formData, dining_date: e.target.value })}
                          className="hall-details__field-input"
                          placeholder="Select Dining Date"
                        />
                      </div>
                    </div>

                    {/* Preferred Time & Guests */}
                    <div className="hall-details__field-row">
                      <div>
                        <label className="hall-details__field-label">Preferred Time</label>
                        <input
                          type="time"
                          value={formData.dining_time}
                          onChange={(e) => setFormData({ ...formData, dining_time: e.target.value })}
                          className="hall-details__field-input"
                        />
                      </div>
                      <div>
                        <label className="hall-details__field-label">Guest Count</label>
                        <input
                          type="number"
                          min="1"
                          max="100"
                          value={formData.guest_count}
                          onChange={(e) => setFormData({ ...formData, guest_count: e.target.value })}
                          className="hall-details__field-input"
                        />
                      </div>
                    </div>

                    {/* Special Requests */}
                    <div className="hall-details__field" style={{ marginBottom: '24px' }}>
                      <label className="hall-details__field-label">Special Requests / Preferences</label>
                      <textarea
                        rows={2}
                        placeholder="Mention any table preferences, pure veg requirements, or celebration requests..."
                        value={formData.other_requirements}
                        onChange={(e) => setFormData({ ...formData, other_requirements: e.target.value })}
                        className="hall-details__field-textarea"
                      />
                    </div>

                    {/* Submit Button */}
                    <button
                      type="submit"
                      disabled={isSubmitting}
                      className="hall-details__submit-btn"
                    >
                      {isSubmitting ? 'Submitting Reservation...' : 'Reserve Dining Experience'}
                    </button>
                  </form>
                )}
              </div>
            </div>
          </div>
        )}
      </main>

      <Footer onNavigate={onNavigate} />
    </div>
  );
};

export default RestaurantDetailsPage;
