import { useEffect, useRef, useState } from 'react';
import serviceMahal from '../assets/images/service-mahal.jpg';
import serviceEventHall from '../assets/images/service-event-hall.jpg';
import serviceLodge from '../assets/images/service-lodge.jpg';
import serviceCatering from '../assets/images/service-catering.jpg';
import serviceRestaurant from '../assets/images/service-restaurant.jpg';

const ServicesSection = ({ onNavigate }) => {
  const sectionRef = useRef(null);
  const [isVisible, setIsVisible] = useState(false);

  useEffect(() => {
    const observer = new IntersectionObserver(
      ([entry]) => {
        if (entry.isIntersecting) {
          setIsVisible(true);
          observer.disconnect();
        }
      },
      { threshold: 0.1 }
    );

    const el = sectionRef.current;
    if (el) observer.observe(el);
    return () => observer.disconnect();
  }, []);

  const services = [
    {
      id: 'mahal',
      title: 'Mahal / Marriage Hall',
      shortTitle: 'Wedding Mahal',
      description: 'Grand traditional South Indian wedding venue with majestic mandapam.',
      image: serviceMahal,
      alt: 'Grand South Indian wedding mahal',
      href: '/services/mahal',
      icon: (
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round">
          <circle cx="8" cy="12" r="5" />
          <circle cx="16" cy="12" r="5" />
        </svg>
      ),
    },
    {
      id: 'event-hall',
      title: 'Event / Party Hall',
      shortTitle: 'Event & Party Hall',
      description: 'Modern elegance for celebrations, corporate events, and milestones.',
      image: serviceEventHall,
      alt: 'Contemporary event hall',
      href: '/services/event-hall',
      icon: (
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round">
          <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2" />
        </svg>
      ),
    },
    {
      id: 'lodge',
      title: 'SM Lodge',
      shortTitle: 'SM Lodge',
      description: 'Well-appointed luxury guest suites and rooms for every traveler.',
      image: serviceLodge,
      alt: 'Comfortable guest suite at SM Lodge',
      href: '/lodge',
      icon: (
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round">
          <path d="M2 4v16M2 8h18a2 2 0 0 1 2 2v10M2 17h20M6 8v9" />
        </svg>
      ),
    },
    {
      id: 'catering',
      title: 'Catering',
      shortTitle: 'Grand Catering',
      description: 'Authentic South Indian banana-leaf feasts and gourmet dining.',
      image: serviceCatering,
      alt: 'Traditional South Indian feast',
      href: '/services/catering',
      icon: (
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round">
          <path d="M18 11V6a2 2 0 0 0-2-2v0a2 2 0 0 0-2 2v0M4 11h16a1 1 0 0 1 1 1v1a7 7 0 0 1-7 7H10a7 7 0 0 1-7-7v-1a1 1 0 0 1 1-1Z" />
          <path d="M12 2v2" />
        </svg>
      ),
    },
    {
      id: 'restaurant',
      title: 'Restaurant & Dining',
      shortTitle: 'Restaurant & Dining',
      description: 'Delicious Chettinad recipes, gourmet dining packages, and multi-cuisine flavors.',
      image: serviceRestaurant,
      alt: 'Elegant restaurant interior at SM Groups',
      href: '/restaurant',
      icon: (
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round">
          <path d="M18 2v20M21 2v4a3 3 0 0 1-3 3M3 2v7a3 3 0 0 0 3 3h0a3 3 0 0 0 3-3V2M6 12v10" />
        </svg>
      ),
    },
  ];

  const handleCardClick = (href, e) => {
    e.preventDefault();
    if (onNavigate) onNavigate(href);
  };

  return (
    <section
      id="services"
      className={`services ${isVisible ? 'services--visible' : ''}`}
      ref={sectionRef}
    >
      {/* Decorative top transition */}
      <div className="services__edge" aria-hidden="true"></div>

      <div className="services__container">
        {/* Section Header */}
        <div className="services__header">
          <div className="services__eyebrow-wrap">
            <span className="services__eyebrow-line"></span>
            <span className="services__eyebrow-text">SERVICES</span>
            <span className="services__eyebrow-line"></span>
          </div>
          <div className="services__eyebrow-dots" aria-hidden="true">
            <span></span><span></span><span></span><span></span><span></span>
          </div>
          <h2 className="services__title">
            Celebrate Your <span className="services__title-accent">Occasions</span>
          </h2>
          <p className="services__subtitle">
            From intimate gatherings to grand celebrations, we make every occasion unforgettable.
          </p>
        </div>

        {/* Services Cards Grid with links to detail pages */}
        <div className="services__cards-grid">
          {services.map((service, index) => (
            <article
              key={service.id}
              className={`services__card services__card--${service.id}`}
              style={{ transitionDelay: `${0.1 + index * 0.1}s`, cursor: 'pointer' }}
              onClick={(e) => handleCardClick(service.href, e)}
              role="link"
              tabIndex={0}
              onKeyDown={(e) => { if (e.key === 'Enter') handleCardClick(service.href, e); }}
            >
              <div className="services__card-image-wrap">
                <img
                  src={service.image}
                  alt={service.alt}
                  className="services__card-img"
                  loading="lazy"
                />
                <div className="services__card-icon-badge" aria-hidden="true">
                  {service.icon}
                </div>
              </div>
              <div className="services__card-content">
                <h3 className="services__card-title">{service.title}</h3>
                <p className="services__card-desc">{service.description}</p>
                <span className="services__card-link-text">
                  Learn More →
                </span>
              </div>
            </article>
          ))}
        </div>
      </div>
    </section>
  );
};

export default ServicesSection;
