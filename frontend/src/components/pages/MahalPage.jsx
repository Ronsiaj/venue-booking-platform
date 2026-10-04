import Header from '../Header';
import Footer from '../Footer';
import serviceMahal from '../../assets/images/service-mahal.jpg';
import galleryWedding from '../../assets/images/gallery-wedding.jpg';
import galleryBanquet from '../../assets/images/gallery-banquet.jpg';

const MahalPage = ({ onNavigate }) => {
  const facilities = [
    { icon: '❄️', label: 'Central Air Conditioning' },
    { icon: '🎭', label: 'Grand Mandapam Stage' },
    { icon: '💡', label: 'Professional Lighting' },
    { icon: '🔊', label: 'Premium Sound System' },
    { icon: '🅿️', label: 'Ample Parking' },
    { icon: '⚡', label: '24/7 Power Backup' },
    { icon: '🍽️', label: 'In-house Catering Kitchen' },
    { icon: '📷', label: 'Photography-ready Backdrops' },
  ];

  const suitableEvents = [
    'Traditional Weddings',
    'Hindu Muhurtham Ceremonies',
    'Grand Receptions',
    'Engagement Ceremonies',
    'Haldi & Mehendi',
    'Anniversary Celebrations',
  ];

  return (
    <>
      <Header onNavigate={onNavigate} currentPage="/services/mahal" />
      <main>
        {/* Hero Banner */}
        <div className="page-hero page-hero--service">
          <img src={serviceMahal} alt="SM Mahal Marriage Hall" className="page-hero__bg-img" />
          <div className="page-hero__overlay" />
          <div className="page-hero__content">
            <span className="page-hero__badge">Marriage Hall</span>
            <h1 className="page-hero__title">SM Mahal</h1>
            <p className="page-hero__desc">Grand traditional South Indian wedding venue with majestic mandapam</p>
          </div>
        </div>

        <div className="service-page">
          <div className="service-page__container">
            {/* Overview */}
            <section className="service-page__section">
              <div className="service-page__text-block">
                <h2 className="service-page__heading">The Perfect Wedding Venue</h2>
                <p className="service-page__text">
                  SM Mahal is a grand traditional marriage hall designed for hosting unforgettable South Indian weddings and grand celebrations. With its majestic mandapam, ornate floral arrangements, and warm golden lighting, every detail creates an atmosphere of timeless elegance.
                </p>
                <p className="service-page__text">
                  Our spacious, fully air-conditioned hall accommodates large gatherings with comfort, while our experienced event coordination team ensures every ritual and ceremony flows seamlessly.
                </p>
              </div>
            </section>

            {/* Capacity */}
            <section className="service-page__section">
              <h2 className="service-page__heading">Capacity & Seating</h2>
              <div className="service-page__stats-row">
                <div className="service-page__stat-card">
                  <span className="service-page__stat-num">400+</span>
                  <span className="service-page__stat-label">Indoor Seating</span>
                </div>
                <div className="service-page__stat-card">
                  <span className="service-page__stat-num">600+</span>
                  <span className="service-page__stat-label">With Outdoor Extension</span>
                </div>
                <div className="service-page__stat-card">
                  <span className="service-page__stat-num">Theatre</span>
                  <span className="service-page__stat-label">& Round-Table Arrangements</span>
                </div>
              </div>
            </section>

            {/* Gallery */}
            <section className="service-page__section">
              <h2 className="service-page__heading">Hall Gallery</h2>
              <div className="service-page__gallery-grid">
                <div className="service-page__gallery-item">
                  <img src={serviceMahal} alt="SM Mahal grand mandapam" loading="lazy" />
                </div>
                <div className="service-page__gallery-item">
                  <img src={galleryWedding} alt="Wedding ceremony at SM Mahal" loading="lazy" />
                </div>
                <div className="service-page__gallery-item">
                  <img src={galleryBanquet} alt="Banquet setup at SM Mahal" loading="lazy" />
                </div>
              </div>
            </section>

            {/* Facilities */}
            <section className="service-page__section">
              <h2 className="service-page__heading">Facilities & Amenities</h2>
              <div className="service-page__amenities-grid">
                {facilities.map((f, i) => (
                  <div key={i} className="service-page__amenity-chip">
                    <span className="service-page__amenity-icon">{f.icon}</span>
                    <span className="service-page__amenity-label">{f.label}</span>
                  </div>
                ))}
              </div>
            </section>

            {/* Suitable Events */}
            <section className="service-page__section">
              <h2 className="service-page__heading">Ideal For</h2>
              <div className="service-page__tags-wrap">
                {suitableEvents.map((ev, i) => (
                  <span key={i} className="service-page__event-tag">{ev}</span>
                ))}
              </div>
            </section>

            {/* CTA */}
            <section className="service-page__cta-section">
              <h2 className="service-page__cta-title">Ready to Book SM Mahal?</h2>
              <p className="service-page__cta-desc">Contact us for availability, pricing, and to schedule a venue visit.</p>
              <div className="service-page__cta-btns">
                <button className="service-page__cta-btn service-page__cta-btn--primary" onClick={() => onNavigate('/contact')}>
                  Enquire Now
                </button>
                <a href="tel:8148165115" className="service-page__cta-btn service-page__cta-btn--secondary">
                  Call: +91 81481 65115
                </a>
              </div>
            </section>
          </div>
        </div>
      </main>
      <Footer onNavigate={onNavigate} />
    </>
  );
};

export default MahalPage;
