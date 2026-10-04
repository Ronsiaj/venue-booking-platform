import { useState, useEffect, useRef } from 'react';
import heroBg from '../assets/images/hero-bg.jpg';

const HeroSection = () => {
  const [isVisible, setIsVisible] = useState(false);
  const [currentSlide, setCurrentSlide] = useState(0);
  const heroRef = useRef(null);

  const taglines = [
    'Weddings',
    'Corporate Events',
    'Grand Celebrations',
    'Private Parties',
    'Social Gatherings',
  ];

  // Entry animation
  useEffect(() => {
    const timer = setTimeout(() => setIsVisible(true), 100);
    return () => clearTimeout(timer);
  }, []);

  // Tagline rotation
  useEffect(() => {
    const interval = setInterval(() => {
      setCurrentSlide((prev) => (prev + 1) % taglines.length);
    }, 3000);
    return () => clearInterval(interval);
  }, [taglines.length]);

  // Parallax on mouse move
  useEffect(() => {
    const heroEl = heroRef.current;
    if (!heroEl) return;

    const handleMouseMove = (e) => {
      const rect = heroEl.getBoundingClientRect();
      const x = (e.clientX - rect.left) / rect.width - 0.5;
      const y = (e.clientY - rect.top) / rect.height - 0.5;

      const bgImage = heroEl.querySelector('.hero-section__bg-image');
      if (bgImage) {
        bgImage.style.transform = `scale(1.08) translate(${x * -15}px, ${y * -10}px)`;
      }
    };

    heroEl.addEventListener('mousemove', handleMouseMove);
    return () => heroEl.removeEventListener('mousemove', handleMouseMove);
  }, []);

  return (
    <section
      id="hero-section"
      className={`hero-section ${isVisible ? 'hero-section--visible' : ''}`}
      ref={heroRef}
    >
      {/* Background Layer */}
      <div className="hero-section__bg">
        <img
          src={heroBg}
          alt="SM Groups premium event hall"
          className="hero-section__bg-image"
          loading="eager"
        />
        <div className="hero-section__bg-overlay"></div>
        <div className="hero-section__bg-gradient"></div>
      </div>

      {/* Floating Particles */}
      <div className="hero-section__particles" aria-hidden="true">
        {Array.from({ length: 20 }).map((_, i) => (
          <span
            key={i}
            className="hero-section__particle"
            style={{
              left: `${Math.random() * 100}%`,
              animationDelay: `${Math.random() * 8}s`,
              animationDuration: `${6 + Math.random() * 8}s`,
              opacity: 0.15 + Math.random() * 0.25,
              width: `${2 + Math.random() * 4}px`,
              height: `${2 + Math.random() * 4}px`,
            }}
          />
        ))}
      </div>

      {/* Main Content */}
      <div className="hero-section__content">
        <div className="hero-section__text-block">
          {/* Brand Badge */}
          <div className="hero-section__badge">
            <span className="hero-section__badge-dot"></span>
            <span className="hero-section__badge-text">Premium Event Venues</span>
          </div>

          {/* Main Heading */}
          <h1 className="hero-section__title">
            <span className="hero-section__title-line hero-section__title-line--1">
              Where Every
            </span>
            <span className="hero-section__title-line hero-section__title-line--2">
              Moment Becomes
            </span>
            <span className="hero-section__title-line hero-section__title-line--accent">
              Unforgettable
            </span>
          </h1>

          {/* Rotating Tagline */}
          <div className="hero-section__tagline">
            <span className="hero-section__tagline-label">Perfect For</span>
            <div className="hero-section__tagline-slider">
              {taglines.map((tag, i) => (
                <span
                  key={tag}
                  className={`hero-section__tagline-item ${
                    i === currentSlide ? 'hero-section__tagline-item--active' : ''
                  }`}
                >
                  {tag}
                </span>
              ))}
            </div>
          </div>

          {/* Description */}
          <p className="hero-section__description">
            Experience world-class event spaces designed for life's most cherished celebrations. 
            From intimate gatherings to grand galas, SM Groups delivers perfection in every detail.
          </p>

          {/* CTA Buttons */}
          <div className="hero-section__cta-group">
            <button
              id="hero-book-now"
              className="hero-section__cta hero-section__cta--primary"
              type="button"
            >
              <span className="hero-section__cta-text">Book Your Event</span>
              <svg
                className="hero-section__cta-arrow"
                width="20"
                height="20"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                strokeWidth="2"
                strokeLinecap="round"
                strokeLinejoin="round"
              >
                <line x1="5" y1="12" x2="19" y2="12" />
                <polyline points="12 5 19 12 12 19" />
              </svg>
            </button>
            <button
              id="hero-explore-venues"
              className="hero-section__cta hero-section__cta--secondary"
              type="button"
            >
              <svg
                className="hero-section__cta-icon"
                width="18"
                height="18"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                strokeWidth="2"
                strokeLinecap="round"
                strokeLinejoin="round"
              >
                <circle cx="11" cy="11" r="8" />
                <line x1="21" y1="21" x2="16.65" y2="16.65" />
              </svg>
              <span className="hero-section__cta-text">Explore Venues</span>
            </button>
          </div>
        </div>
      </div>

      {/* Scroll Indicator */}
      <div className="hero-section__scroll-indicator" aria-label="Scroll down">
        <div className="hero-section__scroll-mouse">
          <div className="hero-section__scroll-wheel"></div>
        </div>
        <span className="hero-section__scroll-text">Scroll to explore</span>
      </div>

      {/* Decorative Corner */}
      <div className="hero-section__corner hero-section__corner--top-left" aria-hidden="true"></div>
      <div className="hero-section__corner hero-section__corner--bottom-right" aria-hidden="true"></div>
    </section>
  );
};

export default HeroSection;
