import { useEffect, useRef, useState } from 'react';
import aboutVenue from '../assets/images/about-venue.jpg';
import aboutTeam from '../assets/images/about-team.jpg';

const AboutSection = () => {
  const sectionRef = useRef(null);
  const [isVisible, setIsVisible] = useState(false);

  // Intersection Observer for entrance animation
  useEffect(() => {
    const observer = new IntersectionObserver(
      ([entry]) => {
        if (entry.isIntersecting) {
          setIsVisible(true);
          observer.disconnect();
        }
      },
      { threshold: 0.15 }
    );

    const el = sectionRef.current;
    if (el) observer.observe(el);
    return () => observer.disconnect();
  }, []);

  const highlights = [
    {
      icon: (
        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round">
          <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" />
          <polyline points="9 22 9 12 15 12 15 22" />
        </svg>
      ),
      number: '5+',
      label: 'Premium Venues',
    },
    {
      icon: (
        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round">
          <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
          <circle cx="9" cy="7" r="4" />
          <path d="M23 21v-2a4 4 0 0 0-3-3.87" />
          <path d="M16 3.13a4 4 0 0 1 0 7.75" />
        </svg>
      ),
      number: '2000+',
      label: 'Guests Capacity',
    },
    {
      icon: (
        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round">
          <circle cx="12" cy="12" r="10" />
          <polyline points="12 6 12 12 16 14" />
        </svg>
      ),
      number: '15+',
      label: 'Years Legacy',
    },
  ];

  return (
    <section
      id="about-section"
      className={`about ${isVisible ? 'about--visible' : ''}`}
      ref={sectionRef}
    >
      {/* Decorative top edge — visual transition from hero */}
      <div className="about__edge" aria-hidden="true"></div>

      <div className="about__container">
        {/* ── Left: Image Composition ── */}
        <div className="about__visual">
          <div className="about__img-primary-wrap">
            <img
              src={aboutVenue}
              alt="SM Groups premium event hall with elegant table settings and chandelier lighting"
              className="about__img-primary"
              loading="lazy"
            />
            {/* Gold accent frame */}
            <div className="about__img-frame" aria-hidden="true"></div>
          </div>

          {/* Overlapping secondary image */}
          <div className="about__img-secondary-wrap">
            <img
              src={aboutTeam}
              alt="SM Groups event planning team arranging a luxury banquet table"
              className="about__img-secondary"
              loading="lazy"
            />
          </div>

          {/* Experience badge overlapping images */}
          <div className="about__experience-badge">
            <span className="about__experience-number">15</span>
            <span className="about__experience-text">Years of<br />Excellence</span>
          </div>
        </div>

        {/* ── Right: Text Content ── */}
        <div className="about__content">
          {/* Section label */}
          <div className="about__label">
            <span className="about__label-line" aria-hidden="true"></span>
            <span className="about__label-text">About SM Groups</span>
          </div>

          <h2 className="about__title">
            Crafting Celebrations<br />
            <span className="about__title-accent">That Last a Lifetime</span>
          </h2>

          <p className="about__lead">
            For over 15 years, SM Groups has been the trusted name behind the region's 
            most memorable weddings, corporate events, and grand celebrations.
          </p>

          <p className="about__body">
            Our venues blend architectural grandeur with modern amenities, offering 
            a canvas where your vision comes to life. Every detail — from the 
            placement of each centrepiece to the flow of the evening — is curated 
            by our dedicated team of event specialists.
          </p>

          {/* Highlights Row */}
          <div className="about__highlights">
            {highlights.map((item, index) => (
              <div key={index} className="about__highlight">
                <div className="about__highlight-icon">{item.icon}</div>
                <span className="about__highlight-number">{item.number}</span>
                <span className="about__highlight-label">{item.label}</span>
              </div>
            ))}
          </div>

          {/* Signature/Promise */}
          <div className="about__promise">
            <div className="about__promise-line" aria-hidden="true"></div>
            <p className="about__promise-text">
              "We don't just host events — we create experiences that 
              families cherish for generations."
            </p>
          </div>
        </div>
      </div>
    </section>
  );
};

export default AboutSection;
