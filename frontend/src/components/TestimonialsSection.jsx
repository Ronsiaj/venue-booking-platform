import { useState, useEffect, useRef, useCallback } from 'react';

const TestimonialsSection = () => {
  const sectionRef = useRef(null);
  const [isVisible, setIsVisible] = useState(false);
  const [activeIndex, setActiveIndex] = useState(0);
  const [isPaused, setIsPaused] = useState(false);
  const touchStartX = useRef(null);
  const touchEndX = useRef(null);

  // Clearly defined client experience testimonial structures (ready for backend / database connection)
  const testimonials = [
    {
      id: 1,
      quote:
        "Hosting our traditional wedding at SM Mahal was an unforgettable experience. The spacious air-conditioned hall, grand stage decor, and seamless parking arrangements made our special day truly magical for every guest.",
      author: "S. Annamalai & Family",
      eventType: "Traditional Wedding Celebration",
      venue: "SM Mahal / Marriage Hall",
      rating: 5,
      date: "Recent Host",
      initials: "SA",
    },
    {
      id: 2,
      quote:
        "The hospitality and room accommodations at SM Lodge exceeded all our expectations. Our out-of-town guests were delighted with the clean suites, prompt 24/7 service, and prime location in Karaikudi.",
      author: "R. Meenakshi Sundaram",
      eventType: "Family Reception & Guest Stay",
      venue: "SM Lodge & Suites",
      rating: 5,
      date: "Verified Guest",
      initials: "MS",
    },
    {
      id: 3,
      quote:
        "Exceptional catering service and exquisite authentic feast! From the traditional leaf-service lunch to the grand evening buffet, the food taste, hygiene, and attentive staff won everyone's praise.",
      author: "Dr. K. Ramanathan",
      eventType: "Grand Engagement & Catering",
      venue: "Grand Catering Service",
      rating: 5,
      date: "Event Patron",
      initials: "KR",
    },
    {
      id: 4,
      quote:
        "We celebrated our daughter's birthday at the Event Hall. The ambiance, pristine sound acoustics, and supportive management team ensured every moment went smoothly without any hassle.",
      author: "P. Karthikeyan & Divya",
      eventType: "Birthday & Family Celebration",
      venue: "Event Hall / Party Hall",
      rating: 5,
      date: "Host Family",
      initials: "KD",
    },
  ];

  // Intersection Observer for scroll entrance
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

  // Next / Previous Navigation
  const handleNext = useCallback(() => {
    setActiveIndex((prev) => (prev + 1) % testimonials.length);
  }, [testimonials.length]);

  const handlePrev = useCallback(() => {
    setActiveIndex((prev) => (prev - 1 + testimonials.length) % testimonials.length);
  }, [testimonials.length]);

  // Autoplay functionality with pause on hover
  useEffect(() => {
    if (isPaused) return;
    const interval = setInterval(() => {
      handleNext();
    }, 6000);
    return () => clearInterval(interval);
  }, [isPaused, handleNext]);

  // Touch Swipe Handlers for Mobile
  const handleTouchStart = (e) => {
    touchStartX.current = e.targetTouches[0].clientX;
  };

  const handleTouchMove = (e) => {
    touchEndX.current = e.targetTouches[0].clientX;
  };

  const handleTouchEnd = () => {
    if (!touchStartX.current || !touchEndX.current) return;
    const distance = touchStartX.current - touchEndX.current;
    if (distance > 50) {
      handleNext();
    } else if (distance < -50) {
      handlePrev();
    }
    touchStartX.current = null;
    touchEndX.current = null;
  };

  // Keyboard navigation
  const handleKeyDown = (e) => {
    if (e.key === 'ArrowLeft') {
      handlePrev();
    } else if (e.key === 'ArrowRight') {
      handleNext();
    }
  };

  return (
    <section
      id="testimonials"
      className={`testimonials-section ${isVisible ? 'testimonials-section--visible' : ''}`}
      ref={sectionRef}
      onMouseEnter={() => setIsPaused(true)}
      onMouseLeave={() => setIsPaused(false)}
      onFocus={() => setIsPaused(true)}
      onBlur={() => setIsPaused(false)}
      onKeyDown={handleKeyDown}
      tabIndex="0"
      aria-label="Client Testimonials and Experiences"
    >
      {/* Decorative top transition */}
      <div className="testimonials__edge" aria-hidden="true"></div>

      <div className="testimonials__container">
        {/* Section Header */}
        <div className="testimonials__header">
          <div className="testimonials__eyebrow-wrap">
            <span className="testimonials__eyebrow-line"></span>
            <span className="testimonials__eyebrow-text">PATRON EXPERIENCES</span>
            <span className="testimonials__eyebrow-line"></span>
          </div>

          <div className="testimonials__eyebrow-dots" aria-hidden="true">
            <span></span>
            <span></span>
            <span></span>
          </div>

          <h2 className="testimonials__title">
            Words of <span className="testimonials__title-accent">Appreciation</span>
          </h2>

          <p className="testimonials__subtitle">
            Heartfelt reflections and memorable celebrations shared by our valued guests and host families across Karaikudi.
          </p>
        </div>

        {/* Featured Testimonial Carousel Deck */}
        <div
          className="testimonials__deck-wrapper"
          onTouchStart={handleTouchStart}
          onTouchMove={handleTouchMove}
          onTouchEnd={handleTouchEnd}
        >
          {/* Main Active Testimonial Card */}
          <div className="testimonials__showcase">
            <div className="testimonials__card testimonials__card--active" key={testimonials[activeIndex].id}>
              {/* Top Quote Icon & Rating */}
              <div className="testimonials__card-top">
                <div className="testimonials__quote-icon" aria-hidden="true">
                  <svg width="36" height="36" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M14.017 21v-7.391c0-5.704 3.731-9.57 8.983-10.609l.995 2.151c-2.432.917-3.995 3.638-3.995 5.849h4v10h-9.983zm-14.017 0v-7.391c0-5.704 3.748-9.57 9-10.609l.996 2.151c-2.433.917-3.996 3.638-3.996 5.849h3.983v10h-9.983z" />
                  </svg>
                </div>

                <div className="testimonials__rating" aria-label={`Rating: ${testimonials[activeIndex].rating} out of 5 stars`}>
                  <div className="testimonials__stars">
                    {[...Array(testimonials[activeIndex].rating)].map((_, i) => (
                      <svg key={i} className="testimonials__star" width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                        <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2" />
                      </svg>
                    ))}
                  </div>
                  <span className="testimonials__rating-badge">Verified Experience</span>
                </div>
              </div>

              {/* Quote Body */}
              <blockquote className="testimonials__quote-text">
                &ldquo;{testimonials[activeIndex].quote}&rdquo;
              </blockquote>

              {/* Author & Event Info Footer */}
              <div className="testimonials__author-footer">
                <div className="testimonials__author-info">
                  <div className="testimonials__avatar" aria-hidden="true">
                    <span>{testimonials[activeIndex].initials}</span>
                  </div>
                  <div className="testimonials__author-details">
                    <h3 className="testimonials__author-name">{testimonials[activeIndex].author}</h3>
                    <div className="testimonials__event-tags">
                      <span className="testimonials__event-tag">{testimonials[activeIndex].eventType}</span>
                      <span className="testimonials__tag-dot">•</span>
                      <span className="testimonials__venue-tag">{testimonials[activeIndex].venue}</span>
                    </div>
                  </div>
                </div>

                <div className="testimonials__date-badge">
                  <span className="testimonials__status-dot"></span>
                  <span>{testimonials[activeIndex].date}</span>
                </div>
              </div>
            </div>
          </div>

          {/* Navigation Controls & Indicators */}
          <div className="testimonials__controls-row">
            <button
              onClick={handlePrev}
              className="testimonials__nav-btn testimonials__nav-btn--prev"
              aria-label="Previous testimonial"
            >
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round">
                <path d="M15 18 l-6 -6 6 -6" />
              </svg>
            </button>

            {/* Dot Indicators */}
            <div className="testimonials__dots" role="tablist" aria-label="Testimonial slides">
              {testimonials.map((_, idx) => (
                <button
                  key={idx}
                  onClick={() => setActiveIndex(idx)}
                  className={`testimonials__dot ${idx === activeIndex ? 'testimonials__dot--active' : ''}`}
                  role="tab"
                  aria-selected={idx === activeIndex}
                  aria-label={`Go to testimonial ${idx + 1}`}
                />
              ))}
            </div>

            <button
              onClick={handleNext}
              className="testimonials__nav-btn testimonials__nav-btn--next"
              aria-label="Next testimonial"
            >
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round">
                <path d="M9 18 l6 -6 -6 -6" />
              </svg>
            </button>
          </div>
        </div>


      </div>
    </section>
  );
};

export default TestimonialsSection;
