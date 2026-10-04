import Header from '../Header';
import Footer from '../Footer';
import serviceEventHall from '../../assets/images/service-event-hall.jpg';
import galleryBirthday from '../../assets/images/gallery-birthday.jpg';
import galleryEngagement from '../../assets/images/gallery-engagement.jpg';

const EventHallPage = ({ onNavigate }) => {
  const facilities = [
    { icon: '❄️', label: 'Air Conditioned' },
    { icon: '🎤', label: 'Stage with Lighting' },
    { icon: '🔊', label: 'Sound & DJ Setup' },
    { icon: '🍽️', label: 'Buffet / Dining Area' },
    { icon: '🅿️', label: 'Dedicated Parking' },
    { icon: '⚡', label: 'Power Backup' },
  ];

  const suitableEvents = [
    'Birthday Parties',
    'Engagement Ceremonies',
    'Reception Parties',
    'Corporate Events',
    'Conferences & Seminars',
    'Family Get-Togethers',
    'Farewell & Reunion Events',
    'Award Ceremonies',
  ];

  return (
    <>
      <Header onNavigate={onNavigate} currentPage="/services/event-hall" />
      <main>
        <div className="page-hero page-hero--service">
          <img src={serviceEventHall} alt="SM Event Hall" className="page-hero__bg-img" />
          <div className="page-hero__overlay" />
          <div className="page-hero__content">
            <span className="page-hero__badge">Event Hall</span>
            <h1 className="page-hero__title">Event & Party Hall</h1>
            <p className="page-hero__desc">Modern elegance for celebrations, corporate events, and milestones</p>
          </div>
        </div>

        <div className="service-page">
          <div className="service-page__container">
            <section className="service-page__section">
              <div className="service-page__text-block">
                <h2 className="service-page__heading">Celebrate Every Milestone</h2>
                <p className="service-page__text">
                  Our contemporary Event & Party Hall is designed for celebrations that deserve an elevated experience. From intimate birthday gatherings to large-scale corporate conferences, the hall adapts to your vision with modern amenities and ambient lighting.
                </p>
                <p className="service-page__text">
                  The versatile space features a well-appointed stage, premium sound system, and flexible seating arrangements that transform effortlessly for any occasion.
                </p>
              </div>
            </section>

            <section className="service-page__section">
              <h2 className="service-page__heading">Capacity</h2>
              <div className="service-page__stats-row">
                <div className="service-page__stat-card">
                  <span className="service-page__stat-num">200+</span>
                  <span className="service-page__stat-label">Indoor Seating</span>
                </div>
                <div className="service-page__stat-card">
                  <span className="service-page__stat-num">350+</span>
                  <span className="service-page__stat-label">Standing / Cocktail</span>
                </div>
              </div>
            </section>

            <section className="service-page__section">
              <h2 className="service-page__heading">Event Gallery</h2>
              <div className="service-page__gallery-grid">
                <div className="service-page__gallery-item">
                  <img src={serviceEventHall} alt="Event hall setup" loading="lazy" />
                </div>
                <div className="service-page__gallery-item">
                  <img src={galleryBirthday} alt="Birthday celebration at event hall" loading="lazy" />
                </div>
                <div className="service-page__gallery-item">
                  <img src={galleryEngagement} alt="Engagement ceremony" loading="lazy" />
                </div>
              </div>
            </section>

            <section className="service-page__section">
              <h2 className="service-page__heading">Facilities</h2>
              <div className="service-page__amenities-grid">
                {facilities.map((f, i) => (
                  <div key={i} className="service-page__amenity-chip">
                    <span className="service-page__amenity-icon">{f.icon}</span>
                    <span className="service-page__amenity-label">{f.label}</span>
                  </div>
                ))}
              </div>
            </section>

            <section className="service-page__section">
              <h2 className="service-page__heading">Perfect For</h2>
              <div className="service-page__tags-wrap">
                {suitableEvents.map((ev, i) => (
                  <span key={i} className="service-page__event-tag">{ev}</span>
                ))}
              </div>
            </section>

            <section className="service-page__cta-section">
              <h2 className="service-page__cta-title">Plan Your Event With Us</h2>
              <p className="service-page__cta-desc">Reach out for hall availability, customized setups, and pricing details.</p>
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

export default EventHallPage;
