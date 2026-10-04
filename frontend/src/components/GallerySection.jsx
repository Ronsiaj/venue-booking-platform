import { useState, useEffect, useRef } from 'react';
import galleryWedding from '../assets/images/gallery-wedding.jpg';
import galleryReception from '../assets/images/gallery-reception.jpg';
import galleryHaldi from '../assets/images/gallery-haldi.jpg';
import galleryEngagement from '../assets/images/gallery-engagement.jpg';
import galleryBirthday from '../assets/images/gallery-birthday.jpg';
import galleryBanquet from '../assets/images/gallery-banquet.jpg';
import serviceMahal from '../assets/images/service-mahal.jpg';
import aboutVenue from '../assets/images/about-venue.jpg';
import serviceEventHall from '../assets/images/service-event-hall.jpg';

const GallerySection = () => {
  const sectionRef = useRef(null);
  const [isVisible, setIsVisible] = useState(false);
  const [activeFilter, setActiveFilter] = useState('all');
  const [activeLightboxImage, setActiveLightboxImage] = useState(null);

  useEffect(() => {
    const observer = new IntersectionObserver(
      ([entry]) => {
        if (entry.isIntersecting) {
          setIsVisible(true);
          observer.disconnect();
        }
      },
      { threshold: 0.08 }
    );

    const el = sectionRef.current;
    if (el) observer.observe(el);
    return () => observer.disconnect();
  }, []);

  // Lock body scroll when lightbox is open
  useEffect(() => {
    if (activeLightboxImage) {
      document.body.style.overflow = 'hidden';
    } else {
      document.body.style.overflow = '';
    }
    return () => {
      document.body.style.overflow = '';
    };
  }, [activeLightboxImage]);

  // Handle ESC key to close lightbox
  useEffect(() => {
    const handleKeyDown = (e) => {
      if (e.key === 'Escape') {
        setActiveLightboxImage(null);
      }
    };
    window.addEventListener('keydown', handleKeyDown);
    return () => window.removeEventListener('keydown', handleKeyDown);
  }, []);

  const categories = [
    { id: 'all', label: 'All' },
    { id: 'celebration', label: 'Celebration' },
    { id: 'wedding', label: 'Wedding' },
    { id: 'reception', label: 'Reception' },
  ];

  const galleryItems = [
    {
      id: 1,
      category: 'wedding',
      image: galleryWedding,
      alt: 'Traditional South Indian wedding muhurtham ceremony on grand mandapam',
    },
    {
      id: 2,
      category: 'reception',
      image: galleryReception,
      alt: 'Grand evening wedding reception stage with illuminated floral wall and chandeliers',
    },
    {
      id: 3,
      category: 'celebration',
      image: galleryHaldi,
      alt: 'Vibrant traditional South Indian Haldi ceremony celebration setup',
    },
    {
      id: 4,
      category: 'wedding',
      image: galleryEngagement,
      alt: 'Auspicious South Indian engagement ceremony decor with brass urli and fresh flowers',
    },
    {
      id: 5,
      category: 'celebration',
      image: galleryBirthday,
      alt: 'Milestone luxury birthday celebration stage with marquee lights and floral arches',
    },
    {
      id: 6,
      category: 'reception',
      image: galleryBanquet,
      alt: 'Grand event banquet hall with illuminated stage and guest seating',
    },
    {
      id: 7,
      category: 'wedding',
      image: serviceMahal,
      alt: 'Grand traditional marriage hall mandapam with ornate floral arrangements',
    },
    {
      id: 8,
      category: 'celebration',
      image: aboutVenue,
      alt: 'Celebration venue with elegant architectural ambiance',
    },
    {
      id: 9,
      category: 'reception',
      image: serviceEventHall,
      alt: 'Contemporary event hall decorated for an evening reception',
    },
  ];

  const filteredItems = activeFilter === 'all'
    ? galleryItems
    : galleryItems.filter((item) => item.category === activeFilter);

  return (
    <section
      id="gallery"
      className={`gallery-section ${isVisible ? 'gallery-section--visible' : ''}`}
      ref={sectionRef}
    >
      {/* Decorative top transition */}
      <div className="gallery__edge" aria-hidden="true"></div>

      <div className="gallery__container">
        {/* Section Header */}
        <div className="gallery__header">
          <div className="gallery__eyebrow-wrap">
            <span className="gallery__eyebrow-line"></span>
            <span className="gallery__eyebrow-text">GALLERY</span>
            <span className="gallery__eyebrow-line"></span>
          </div>
          <div className="gallery__eyebrow-dots" aria-hidden="true">
            <span></span>
            <span></span>
            <span></span>
            <span></span>
            <span></span>
          </div>
          <h2 className="gallery__title">
            Moments of Joy & <span className="gallery__title-accent">Celebration</span>
          </h2>
          <p className="gallery__subtitle">
            Explore cherished weddings, receptions, and memorable celebrations hosted at SM Groups.
          </p>

          {/* Filter Pills: All | Celebration | Wedding | Reception */}
          <div className="gallery__filters" role="tablist" aria-label="Gallery category filters">
            {categories.map((cat) => (
              <button
                key={cat.id}
                role="tab"
                aria-selected={activeFilter === cat.id}
                className={`gallery__filter-btn ${
                  activeFilter === cat.id ? 'gallery__filter-btn--active' : ''
                }`}
                onClick={() => setActiveFilter(cat.id)}
              >
                {cat.label}
              </button>
            ))}
          </div>
        </div>

        {/* Gallery Masonry Layout (Pure Photography - No Text) */}
        <div className="gallery__masonry">
          {filteredItems.map((item) => (
            <div
              key={item.id}
              className="gallery__item"
              onClick={() => setActiveLightboxImage(item)}
              role="button"
              tabIndex={0}
              onKeyDown={(e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                  e.preventDefault();
                  setActiveLightboxImage(item);
                }
              }}
              aria-label="View photo in full resolution"
            >
              <img
                src={item.image}
                alt={item.alt}
                className="gallery__image"
                loading="lazy"
              />
            </div>
          ))}
        </div>
      </div>

      {/* Lightbox Modal (Clean, image-focused) */}
      {activeLightboxImage && (
        <div
          className="gallery__lightbox"
          onClick={() => setActiveLightboxImage(null)}
          role="dialog"
          aria-modal="true"
          aria-label="Full size photo"
        >
          <div
            className="gallery__lightbox-content"
            onClick={(e) => e.stopPropagation()}
          >
            <button
              className="gallery__lightbox-close"
              onClick={() => setActiveLightboxImage(null)}
              aria-label="Close modal"
            >
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round">
                <line x1="18" y1="6" x2="6" y2="18"></line>
                <line x1="6" y1="6" x2="18" y2="18"></line>
              </svg>
            </button>
            <img
              src={activeLightboxImage.image}
              alt={activeLightboxImage.alt}
              className="gallery__lightbox-img"
            />
          </div>
        </div>
      )}
    </section>
  );
};

export default GallerySection;
