import { useState, useEffect, useCallback } from 'react';
import Header from '../Header';
import Footer from '../Footer';
import DatePicker from '../DatePicker';
import { hallApi } from '../../services/api';
import { validateRequiredFields } from '../../services/formValidation';

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

// SVG Icon Helper
const getFacilityIcon = (facilityName = '') => {
  const key = facilityName.toLowerCase();
  if (key.includes('air') || key.includes('ac')) {
    return (
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
        <path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6" />
      </svg>
    );
  }
  if (key.includes('cater') || key.includes('food') || key.includes('dining')) {
    return (
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
        <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9" /><path d="M13.73 21a2 2 0 0 1-3.46 0" />
      </svg>
    );
  }
  if (key.includes('park')) {
    return (
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
        <rect x="3" y="3" width="18" height="18" rx="4" /><path d="M9 17V7h4a3 3 0 0 1 0 6H9" />
      </svg>
    );
  }
  if (key.includes('sound') || key.includes('audio') || key.includes('mic')) {
    return (
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
        <polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5" /><path d="M19.07 4.93a10 10 0 0 1 0 14.14M15.54 8.46a5 5 0 0 1 0 7.07" />
      </svg>
    );
  }
  return (
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
      <polyline points="20 6 9 17 4 12" />
    </svg>
  );
};

const HallDetailsPage = ({ hallId, onNavigate }) => {
  const [hall, setHall] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [selectedImageIndex, setSelectedImageIndex] = useState(0);

  // Enquiry Form State
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [enquirySuccess, setEnquirySuccess] = useState(null);
  const [formErrors, setFormErrors] = useState({});
  const [formData, setFormData] = useState({
    customer_name: '',
    customer_phone: '',
    customer_email: '',
    event_type: 'Wedding Reception',
    event_date: '',
    event_time: 'Full Day',
    guest_count: 500,
    food_required: true,
    decoration_required: true,
    other_requirements: '',
  });

  // Fetch Hall Details from Backend
  const fetchHallDetails = useCallback(async () => {
    if (!hallId) return;
    setLoading(true);
    setError(null);
    try {
      const res = await hallApi.view(hallId);
      if (res.success && res.data?.data) {
        setHall(res.data.data);
        if (res.data.data.capacity) {
          setFormData((prev) => ({ ...prev, guest_count: res.data.data.capacity }));
        }
      } else if (res.success && res.data?.id) {
        setHall(res.data);
      } else {
        setError(res.error || 'Hall not found.');
      }
    } catch (err) {
      setError(err.message || 'Failed to load hall details.');
    } finally {
      setLoading(false);
    }
  }, [hallId]);

  useEffect(() => {
    fetchHallDetails();
  }, [fetchHallDetails]);

  // Handle Submit Enquiry
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
        hall_id: Number(hallId),
        event_type: formData.event_type,
        event_date: formData.event_date || undefined,
        event_time: formData.event_time || undefined,
        guest_count: Number(formData.guest_count) || undefined,
        food_required: formData.food_required,
        decoration_required: formData.decoration_required,
        other_requirements: formData.other_requirements.trim() || undefined,
      };

      const res = await hallApi.createLead(payload);
      if (res.success) {
        setEnquirySuccess({
          reference: res.data?.data?.lead?.lead_reference || res.data?.lead_id || 'Ref #' + Math.floor(1000 + Math.random() * 9000),
          message: 'Your venue reservation enquiry has been submitted successfully!',
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

  // Image list resolution - only valid backend photos
  const galleryImages = [];
  if (hall?.images && Array.isArray(hall.images) && hall.images.length > 0) {
    hall.images.forEach((img) => {
      const resolved = resolveHallImgUrl(img);
      if (resolved) galleryImages.push(resolved);
    });
  } else if (hall?.image_path || hall?.image) {
    const resolved = resolveHallImgUrl(hall.image_path || hall.image);
    if (resolved) galleryImages.push(resolved);
  }

  const currentMainImg = galleryImages[selectedImageIndex] || galleryImages[0] || null;

  return (
    <div className="hall-details">
      <Header onNavigate={onNavigate} currentPage="/halls" />

      <main className="hall-details__inner">
        {/* Back Link */}
        <button
          type="button"
          className="hall-details__back-btn"
          onClick={() => onNavigate('/halls')}
        >
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round">
            <line x1="19" y1="12" x2="5" y2="12" />
            <polyline points="12 19 5 12 12 5" />
          </svg>
          <span>Back to Halls</span>
        </button>

        {/* Loading State */}
        {loading && (
          <div className="hall-details__loading">
            <div className="hall-details__spinner" />
            <p style={{ fontSize: '1.05rem', fontWeight: 600 }}>Loading Venue Details...</p>
          </div>
        )}

        {/* Error State */}
        {!loading && error && (
          <div className="hall-details__error-state">
            <span style={{ fontSize: '2.5rem', display: 'block', marginBottom: '12px' }}>⚠️</span>
            <h2 style={{ color: '#dc2626', marginBottom: '8px', fontFamily: 'var(--font-serif)' }}>Unable to Load Hall</h2>
            <p style={{ color: 'var(--text-muted)', marginBottom: '24px' }}>{error}</p>
            <button type="button" className="lodge-btn-book" style={{ padding: '12px 28px' }} onClick={() => onNavigate('/halls')}>
              Return to Venues Catalog
            </button>
          </div>
        )}

        {/* 2-Column Layout */}
        {!loading && !error && hall && (
          <div className="hall-details__grid">
            {/* LEFT COLUMN: Showcase & Specs */}
            <div>
              {/* Primary Image or Clean Image-Free State */}
              {currentMainImg ? (
                <div className="hall-details__showcase">
                  <img
                    src={currentMainImg}
                    alt={hall.name}
                  />
                  {hall.hall_type?.name && (
                    <span className="hall-details__showcase-badge">
                      {hall.hall_type.name}
                    </span>
                  )}
                </div>
              ) : (
                <div className="hall-details__showcase" style={{ minHeight: '340px', display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center', background: 'linear-gradient(135deg, #fdfbf7 0%, #f5efe6 100%)', border: '1px solid rgba(212, 160, 23, 0.25)', borderRadius: '18px', padding: '32px', textAlign: 'center', position: 'relative' }}>
                  <div style={{ width: '60px', height: '60px', borderRadius: '50%', background: '#ffffff', border: '1.5px solid rgba(212, 160, 23, 0.3)', display: 'flex', alignItems: 'center', justifyContent: 'center', color: '#d4a017', marginBottom: '14px', boxShadow: '0 8px 20px rgba(212, 160, 23, 0.12)' }}>
                    <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round">
                      <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" />
                      <polyline points="9 22 9 12 15 12 15 22" />
                    </svg>
                  </div>
                  <h3 style={{ fontFamily: 'var(--font-serif)', fontSize: '1.4rem', fontWeight: 700, color: 'var(--text-primary)', margin: '0 0 6px' }}>{hall.name}</h3>
                  <p style={{ color: 'var(--text-muted)', fontSize: '0.88rem', margin: 0 }}>Exclusive Venue by SM Groups</p>
                  {hall.hall_type?.name && (
                    <span className="hall-details__showcase-badge" style={{ position: 'absolute', top: '16px', left: '16px' }}>
                      {hall.hall_type.name}
                    </span>
                  )}
                </div>
              )}

              {/* Gallery Thumbnails */}
              {galleryImages.length > 1 && (
                <div className="hall-details__thumbs">
                  {galleryImages.map((thumb, idx) => (
                    <button
                      key={idx}
                      type="button"
                      className={`hall-details__thumb-btn ${selectedImageIndex === idx ? 'hall-details__thumb-btn--active' : ''}`}
                      onClick={() => setSelectedImageIndex(idx)}
                    >
                      <img src={thumb} alt={`View ${idx + 1}`} />
                    </button>
                  ))}
                </div>
              )}

              {/* Title & Pricing */}
              <div className="hall-details__title-area">
                <h1 className="hall-details__name">{hall.name}</h1>
                <div className="hall-details__price-row">
                  <span className="hall-details__price">
                    {hall.price_description ? hall.price_description : 'Custom Quotation Available'}
                  </span>
                  {hall.price_description && (
                    <span className="hall-details__price-note">+ GST / event slot</span>
                  )}
                </div>
              </div>

              {/* 4 Feature Highlight Cards */}
              <div className="hall-details__highlights">
                {/* Timings */}
                <div className="hall-details__highlight-card">
                  <div className="hall-details__highlight-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                      <circle cx="12" cy="12" r="10" />
                      <polyline points="12 6 12 12 16 14" />
                    </svg>
                  </div>
                  <span className="hall-details__highlight-label">Event Slots</span>
                  <span className="hall-details__highlight-value">Morning / Evening / Full Day</span>
                </div>

                {/* Booking Policy */}
                <div className="hall-details__highlight-card">
                  <div className="hall-details__highlight-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                      <circle cx="12" cy="12" r="10" />
                      <line x1="4.93" y1="4.93" x2="19.07" y2="19.07" />
                    </svg>
                  </div>
                  <span className="hall-details__highlight-label">Booking Policy</span>
                  <span className="hall-details__highlight-value">Pre-booking Advance Applicable</span>
                </div>

                {/* Floor Area */}
                <div className="hall-details__highlight-card">
                  <div className="hall-details__highlight-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                      <path d="M3 3h18v18H3z" />
                      <path d="M9 3v18" />
                      <path d="M15 3v18" />
                      <path d="M3 9h18" />
                      <path d="M3 15h18" />
                    </svg>
                  </div>
                  <span className="hall-details__highlight-label">Floor Area</span>
                  <span className="hall-details__highlight-value">
                    {hall.area ? `${hall.area} sq.ft` : 'Spacious Mandapam'}
                  </span>
                </div>

                {/* Capacity */}
                <div className="hall-details__highlight-card">
                  <div className="hall-details__highlight-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                      <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                      <circle cx="9" cy="7" r="4" />
                      <path d="M23 21v-2a4 4 0 0 0-3-3.87" />
                      <path d="M16 3.13a4 4 0 0 1 0 7.75" />
                    </svg>
                  </div>
                  <span className="hall-details__highlight-label">Capacity</span>
                  <span className="hall-details__highlight-value">
                    {hall.capacity ? `${hall.capacity} Guests` : 'Flexible Seating'}
                  </span>
                </div>
              </div>

              {/* Venue Description */}
              {hall.description && (
                <div className="hall-details__desc-block">
                  <h3 className="hall-details__section-title">About {hall.name}</h3>
                  <p className="hall-details__desc-text">{hall.description}</p>
                </div>
              )}

              {/* Facilities List */}
              {hall.facilities && (
                <div className="hall-details__facilities-block">
                  <h3 className="hall-details__section-title">Venue Facilities & Amenities</h3>
                  <div className="hall-details__facilities-grid">
                    {Array.isArray(hall.facilities) ? (
                      hall.facilities.map((fac, i) => {
                        const name = typeof fac === 'string' ? fac : fac.facility_name || fac.name;
                        return (
                          <div key={i} className="hall-details__facility-item">
                            <span className="hall-details__facility-icon">{getFacilityIcon(name)}</span>
                            <span className="hall-details__facility-name">{name}</span>
                          </div>
                        );
                      })
                    ) : typeof hall.facilities === 'string' ? (
                      hall.facilities.split(',').map((fac, i) => (
                        <div key={i} className="hall-details__facility-item">
                          <span className="hall-details__facility-icon">{getFacilityIcon(fac.trim())}</span>
                          <span className="hall-details__facility-name">{fac.trim()}</span>
                        </div>
                      ))
                    ) : null}
                  </div>
                </div>
              )}
            </div>

            {/* RIGHT COLUMN: Reservation / Enquiry Card */}
            <div className="hall-details__enquiry-wrapper">
              <div className="hall-details__enquiry-card">
                <h2 className="hall-details__enquiry-title">Reserve Your Date</h2>

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
                  <form onSubmit={handleSubmitEnquiry}>
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

                    {/* Event Type & Date */}
                    <div className="hall-details__field-row">
                      <div>
                        <label className="hall-details__field-label">Event Type</label>
                        <select
                          value={formData.event_type}
                          onChange={(e) => setFormData({ ...formData, event_type: e.target.value })}
                          className="hall-details__field-select"
                        >
                          <option value="Wedding Reception">Wedding Reception</option>
                          <option value="Muhurtham">Muhurtham</option>
                          <option value="Engagement">Engagement</option>
                          <option value="Birthday / Party">Birthday / Party</option>
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

                    {/* Slot & Guests */}
                    <div className="hall-details__field-row">
                      <div>
                        <label className="hall-details__field-label">Preferred Slot</label>
                        <select
                          value={formData.event_time}
                          onChange={(e) => setFormData({ ...formData, event_time: e.target.value })}
                          className="hall-details__field-select"
                        >
                          <option value="Full Day">Full Day</option>
                          <option value="Morning">Morning (6 AM - 2 PM)</option>
                          <option value="Evening">Evening (4 PM - 11 PM)</option>
                        </select>
                      </div>
                      <div>
                        <label className="hall-details__field-label">Expected Guests</label>
                        <input
                          type="number"
                          min="10"
                          max="5000"
                          value={formData.guest_count}
                          onChange={(e) => setFormData({ ...formData, guest_count: e.target.value })}
                          className="hall-details__field-input"
                        />
                      </div>
                    </div>

                    {/* Checkboxes */}
                    <div className="hall-details__checkbox-row">
                      <label className="hall-details__checkbox-label">
                        <input
                          type="checkbox"
                          checked={formData.food_required}
                          onChange={(e) => setFormData({ ...formData, food_required: e.target.checked })}
                        />
                        <span>Grand Catering Required</span>
                      </label>
                      <label className="hall-details__checkbox-label">
                        <input
                          type="checkbox"
                          checked={formData.decoration_required}
                          onChange={(e) => setFormData({ ...formData, decoration_required: e.target.checked })}
                        />
                        <span>Stage Decor Required</span>
                      </label>
                    </div>

                    {/* Special Notes */}
                    <div className="hall-details__field" style={{ marginBottom: '24px' }}>
                      <label className="hall-details__field-label">Additional Notes / Special Requirements</label>
                      <textarea
                        rows={2}
                        placeholder="e.g. Need 4 bride/groom guest rooms, specific muhurtham timings..."
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
                      {isSubmitting ? 'Submitting...' : 'Confirm Reservation Enquiry'}
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

export default HallDetailsPage;
