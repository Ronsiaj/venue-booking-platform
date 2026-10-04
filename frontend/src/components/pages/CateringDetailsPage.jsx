import { useState, useEffect, useCallback } from 'react';
import Header from '../Header';
import Footer from '../Footer';
import DatePicker from '../DatePicker';
import { cateringApi } from '../../services/cateringApi';
import { validateRequiredFields } from '../../services/formValidation';

// Image URL Resolver - only returns valid backend URLs, never substitutes random stock photos
const resolveCateringImgUrl = (imgPath) => {
  if (!imgPath) return null;
  if (typeof imgPath === 'string') {
    if (imgPath.startsWith('http') || imgPath.startsWith('blob:') || imgPath.startsWith('data:')) return imgPath;
    return `http://localhost/sm_groups/backend/${imgPath.replace(/^\/+/, '')}`;
  }
  return null;
};

const CateringDetailsPage = ({ packageId, onNavigate }) => {
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
    event_type: 'Wedding Feast',
    event_date: '',
    event_time: 'Lunch',
    guest_count: 100,
    venue_address: '',
    other_requirements: '',
  });

  // Fetch Catering Package Details from Backend
  const fetchPackageDetails = useCallback(async () => {
    if (!packageId) return;
    setLoading(true);
    setError(null);
    try {
      const res = await cateringApi.viewPackage(packageId);
      if (res.success && res.data?.data) {
        setPkg(res.data.data);
        if (res.data.data.minimum_quantity) {
          setFormData((prev) => ({ ...prev, guest_count: res.data.data.minimum_quantity }));
        }
      } else if (res.success && res.data?.id) {
        setPkg(res.data);
      } else {
        setError(res.error || 'Catering package not found.');
      }
    } catch (err) {
      setError(err.message || 'Failed to load catering package details.');
    } finally {
      setLoading(false);
    }
  }, [packageId]);

  useEffect(() => {
    fetchPackageDetails();
  }, [fetchPackageDetails]);

  // Handle Submit Catering Enquiry
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
        catering_package_id: Number(packageId),
        event_type: formData.event_type,
        event_date: formData.event_date || undefined,
        event_time: formData.event_time || undefined,
        guest_count: Number(formData.guest_count) || undefined,
        venue_address: formData.venue_address.trim() || undefined,
        other_requirements: formData.other_requirements.trim() || undefined,
      };

      const res = await cateringApi.createLead(payload);
      if (res.success) {
        setEnquirySuccess({
          reference: res.data?.data?.lead?.lead_reference || res.data?.lead_id || 'Ref #' + Math.floor(1000 + Math.random() * 9000),
          message: 'Your catering enquiry has been submitted successfully! Our culinary management team will contact you shortly.',
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

  const imgUrl = resolveCateringImgUrl(pkg?.image_path);
  const rateFormatted = pkg?.rate ? `₹${Number(pkg.rate).toLocaleString('en-IN')}` : 'Custom Quote';
  const rateUnit = pkg?.rate_unit ? `per ${pkg.rate_unit.replace('_', ' ')}` : 'per person';

  return (
    <div className="hall-details">
      <Header onNavigate={onNavigate} currentPage="/catering" />

      <main className="hall-details__inner">
        {/* Back Link */}
        <button
          type="button"
          className="hall-details__back-btn"
          onClick={() => onNavigate('/catering')}
        >
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round">
            <line x1="19" y1="12" x2="5" y2="12" />
            <polyline points="12 19 5 12 12 5" />
          </svg>
          <span>Back to Catering Packages</span>
        </button>

        {/* Loading State */}
        {loading && (
          <div className="hall-details__loading">
            <div className="hall-details__spinner" />
            <p style={{ fontSize: '1.05rem', fontWeight: 600 }}>Loading Catering Package Details...</p>
          </div>
        )}

        {/* Error State */}
        {!loading && error && (
          <div className="hall-details__error-state">
            <span style={{ fontSize: '2.5rem', display: 'block', marginBottom: '12px' }}>⚠️</span>
            <h2 style={{ color: '#dc2626', marginBottom: '8px', fontFamily: 'var(--font-serif)' }}>Unable to Load Package</h2>
            <p style={{ color: 'var(--text-muted)', marginBottom: '24px' }}>{error}</p>
            <button type="button" className="lodge-btn-book" style={{ padding: '12px 28px' }} onClick={() => onNavigate('/catering')}>
              Return to Catering Menus
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
                    Gourmet Catering
                  </span>
                </div>
              ) : (
                <div className="hall-details__showcase" style={{ minHeight: '320px', display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center', background: 'linear-gradient(135deg, #fdfbf7 0%, #f5efe6 100%)', border: '1px solid rgba(212, 160, 23, 0.25)', borderRadius: '18px', padding: '32px', textAlign: 'center', position: 'relative' }}>
                  <div style={{ width: '60px', height: '60px', borderRadius: '50%', background: '#ffffff', border: '1.5px solid rgba(212, 160, 23, 0.3)', display: 'flex', alignItems: 'center', justifyContent: 'center', color: '#d4a017', marginBottom: '14px', boxShadow: '0 8px 20px rgba(212, 160, 23, 0.12)' }}>
                    <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round">
                      <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9" />
                      <path d="M13.73 21a2 2 0 0 1-3.46 0" />
                    </svg>
                  </div>
                  <h3 style={{ fontFamily: 'var(--font-serif)', fontSize: '1.4rem', fontWeight: 700, color: 'var(--text-primary)', margin: '0 0 6px' }}>{pkg.name}</h3>
                  <p style={{ color: 'var(--text-muted)', fontSize: '0.88rem', margin: 0 }}>Curated Catering Service by SM Groups</p>
                  <span className="hall-details__showcase-badge" style={{ position: 'absolute', top: '16px', left: '16px' }}>
                    Gourmet Catering
                  </span>
                </div>
              )}

              {/* Title & Pricing */}
              <div className="hall-details__title-area">
                <h1 className="hall-details__name">{pkg.name}</h1>
                <div className="hall-details__price-row">
                  <span className="hall-details__price">
                    {rateFormatted}
                  </span>
                  <span className="hall-details__price-note">
                    {rateUnit} {pkg.price_includes_gst ? '(GST Included)' : '(+ GST Extra)'}
                  </span>
                </div>
              </div>

              {/* 4 Feature Highlight Cards */}
              <div className="hall-details__highlights">
                {/* Rate / Plate */}
                <div className="hall-details__highlight-card">
                  <div className="hall-details__highlight-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                      <line x1="12" y1="1" x2="12" y2="23" />
                      <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6" />
                    </svg>
                  </div>
                  <span className="hall-details__highlight-label">Rate / Unit</span>
                  <span className="hall-details__highlight-value">{rateFormatted} {rateUnit}</span>
                </div>

                {/* Minimum Order */}
                <div className="hall-details__highlight-card">
                  <div className="hall-details__highlight-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                      <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                      <circle cx="9" cy="7" r="4" />
                      <path d="M23 21v-2a4 4 0 0 0-3-3.87" />
                      <path d="M16 3.13a4 4 0 0 1 0 7.75" />
                    </svg>
                  </div>
                  <span className="hall-details__highlight-label">Minimum Order</span>
                  <span className="hall-details__highlight-value">Min {pkg.minimum_quantity || 50} Guests</span>
                </div>

                {/* Tax Specification */}
                <div className="hall-details__highlight-card">
                  <div className="hall-details__highlight-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                      <rect x="3" y="3" width="18" height="18" rx="4" />
                      <path d="M9 9h6v6H9z" />
                    </svg>
                  </div>
                  <span className="hall-details__highlight-label">Tax Specification</span>
                  <span className="hall-details__highlight-value">
                    {pkg.price_includes_gst ? '✓ GST Included' : '+ GST Applicable'}
                  </span>
                </div>

                {/* Course Items Count */}
                <div className="hall-details__highlight-card">
                  <div className="hall-details__highlight-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                      <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9" />
                      <path d="M13.73 21a2 2 0 0 1-3.46 0" />
                    </svg>
                  </div>
                  <span className="hall-details__highlight-label">Menu Items</span>
                  <span className="hall-details__highlight-value">
                    {pkg.items && pkg.items.length > 0 ? `${pkg.items.length} Course Items` : 'Curated Spread'}
                  </span>
                </div>
              </div>

              {/* Package Description */}
              {pkg.description && (
                <div className="hall-details__desc-block">
                  <h3 className="hall-details__section-title">Menu Overview & Description</h3>
                  <p className="hall-details__desc-text">{pkg.description}</p>
                </div>
              )}

              {/* Included Course Items Breakdown */}
              {pkg.items && pkg.items.length > 0 && (
                <div className="hall-details__facilities-block">
                  <h3 className="hall-details__section-title">
                    Included Course Items & Dishes ({pkg.items.length})
                  </h3>
                  <div className="hall-details__facilities-grid">
                    {pkg.items.map((item, i) => (
                      <div key={i} className="hall-details__facility-item" style={{ flexDirection: 'column', alignItems: 'flex-start', gap: '6px' }}>
                        <div style={{ display: 'flex', alignItems: 'center', gap: '8px', width: '100%' }}>
                          <span
                            style={{
                              width: '8px',
                              height: '8px',
                              borderRadius: '50%',
                              background: item.food_type === 'veg' ? '#16a34a' : '#dc2626',
                              flexShrink: 0,
                            }}
                            title={item.food_type === 'veg' ? 'Vegetarian' : 'Non-Vegetarian'}
                          />
                          <span className="hall-details__facility-name" style={{ fontWeight: 700 }}>
                            {item.food_item_name}
                          </span>
                        </div>
                        {item.food_category_name && (
                          <span style={{ fontSize: '0.74rem', color: 'var(--gold-700)', textTransform: 'uppercase', letterSpacing: '0.04em', fontWeight: 600, paddingLeft: '16px' }}>
                            {item.food_category_name}
                          </span>
                        )}
                        {item.food_item_description && (
                          <span style={{ fontSize: '0.8rem', color: 'var(--text-muted)', lineHeight: '1.4', paddingLeft: '16px' }}>
                            {item.food_item_description}
                          </span>
                        )}
                      </div>
                    ))}
                  </div>
                </div>
              )}
            </div>

            {/* RIGHT COLUMN: Reservation / Catering Quote Card */}
            <div className="hall-details__enquiry-wrapper">
              <div className="hall-details__enquiry-card">
                <h2 className="hall-details__enquiry-title">Request Catering Quote</h2>

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
                        onClick={() => setEnquirySuccess(null)}
                      >
                        Submit Another Enquiry
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

                    {/* Event Type & Date */}
                    <div className="hall-details__field-row">
                      <div>
                        <label className="hall-details__field-label">Event Type</label>
                        <select
                          value={formData.event_type}
                          onChange={(e) => setFormData({ ...formData, event_type: e.target.value })}
                          className="hall-details__field-select"
                        >
                          <option value="Wedding Feast">Wedding Feast</option>
                          <option value="Muhurtham Lunch">Muhurtham Lunch</option>
                          <option value="Reception Dinner">Reception Dinner</option>
                          <option value="Engagement Ceremony">Engagement Ceremony</option>
                          <option value="Birthday Party">Birthday Party</option>
                          <option value="Corporate Event">Corporate Event</option>
                          <option value="Other Celebration">Other Celebration</option>
                        </select>
                      </div>
                      <div>
                        <label className="hall-details__field-label">Event Date</label>
                        <DatePicker
                          value={formData.event_date}
                          onChange={(e) => setFormData({ ...formData, event_date: e.target.value })}
                          className="hall-details__field-input"
                          placeholder="Select Event Date"
                        />
                      </div>
                    </div>

                    {/* Session & Guests */}
                    <div className="hall-details__field-row">
                      <div>
                        <label className="hall-details__field-label">Preferred Session</label>
                        <select
                          value={formData.event_time}
                          onChange={(e) => setFormData({ ...formData, event_time: e.target.value })}
                          className="hall-details__field-select"
                        >
                          <option value="Lunch">Lunch (11 AM - 3 PM)</option>
                          <option value="Dinner">Dinner (7 PM - 11 PM)</option>
                          <option value="Breakfast">Breakfast (6 AM - 10 AM)</option>
                          <option value="High Tea">High Tea / Snacks (4 PM - 6 PM)</option>
                          <option value="Full Day Feast">Full Day Feast</option>
                        </select>
                      </div>
                      <div>
                        <label className="hall-details__field-label">Expected Guests</label>
                        <input
                          type="number"
                          min={pkg.minimum_quantity || 25}
                          value={formData.guest_count}
                          onChange={(e) => setFormData({ ...formData, guest_count: e.target.value })}
                          className="hall-details__field-input"
                        />
                      </div>
                    </div>

                    {/* Delivery / Venue Address */}
                    <div className="hall-details__field">
                      <label className="hall-details__field-label">Event Venue / Delivery Address</label>
                      <input
                        type="text"
                        placeholder="e.g. SM Mahal, Chennai or Home Address"
                        value={formData.venue_address}
                        onChange={(e) => setFormData({ ...formData, venue_address: e.target.value })}
                        className="hall-details__field-input"
                      />
                    </div>

                    {/* Special Notes */}
                    <div className="hall-details__field" style={{ marginBottom: '24px' }}>
                      <label className="hall-details__field-label">Dietary Preferences / Special Notes</label>
                      <textarea
                        rows={2}
                        placeholder="e.g. Specific regional dishes, pure vegetarian counters, live stalls..."
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
                      {isSubmitting ? (
                        <>
                          <div className="hall-details__btn-spinner" />
                          <span>Submitting Enquiry...</span>
                        </>
                      ) : (
                        <span>Request Catering Quote</span>
                      )}
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

export default CateringDetailsPage;
