import { useEffect, useRef, useState } from 'react';

const FacilitiesSection = () => {
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
      { threshold: 0.08 }
    );

    const el = sectionRef.current;
    if (el) observer.observe(el);
    return () => observer.disconnect();
  }, []);

  const facilities = [
    {
      id: 'indoor-ac-hall',
      num: '01',
      name: 'Indoor AC Hall',
      detail: '400+ Seating',
      icon: (
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round">
          <path d="M3 21h18" />
          <path d="M3 7v14" />
          <path d="M21 7v14" />
          <path d="M2 7h20" />
          <path d="M6 7V4a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v3" />
          <path d="M9 14h6" />
          <path d="M12 11v6" />
        </svg>
      ),
    },
    {
      id: 'outdoor-lounge',
      num: '02',
      name: 'Outdoor Lounge',
      detail: 'Mini Open Hall',
      icon: (
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round">
          <path d="M12 2v8" />
          <path d="m4.93 10.93 1.41 1.41" />
          <path d="M2 18h20" />
          <path d="M20 18v3" />
          <path d="M4 18v3" />
          <path d="M4 10a8 8 0 0 1 16 0" />
          <path d="M12 10v8" />
        </svg>
      ),
    },
    {
      id: 'indoor-dining',
      num: '03',
      name: 'Indoor Dining',
      detail: 'Leaf Serve, 200 Guests',
      icon: (
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round">
          <path d="M18 11V6a2 2 0 0 0-2-2v0a2 2 0 0 0-2 2v0M4 11h16a1 1 0 0 1 1 1v1a7 7 0 0 1-7 7H10a7 7 0 0 1-7-7v-1a1 1 0 0 1 1-1Z" />
          <path d="M12 2v2" />
          <path d="M6 18v3" />
          <path d="M18 18v3" />
        </svg>
      ),
    },
    {
      id: 'outdoor-buffet',
      num: '04',
      name: 'Outdoor Buffet Lounge',
      detail: '400 Guests',
      icon: (
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round">
          <path d="M12 2a5 5 0 0 0-5 5v1h10V7a5 5 0 0 0-5-5Z" />
          <path d="M3 14h18" />
          <path d="M4 14a8 8 0 0 0 16 0" />
          <path d="M7 14v5" />
          <path d="M17 14v5" />
          <circle cx="12" cy="2" r="1" fill="currentColor" />
        </svg>
      ),
    },
    {
      id: 'parking',
      num: '05',
      name: 'Parking',
      detail: 'Cars & Two-Wheelers',
      icon: (
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round">
          <rect width="18" height="18" x="3" y="3" rx="4" />
          <path d="M9 17V7h4a3 3 0 0 1 0 6H9" />
        </svg>
      ),
    },
    {
      id: 'power-backup',
      num: '06',
      name: 'Power Backup',
      detail: '24×7 Generator',
      icon: (
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round">
          <path d="M13 2 3 14h9l-1 8 10-12h-9l1-8z" />
        </svg>
      ),
    },
    {
      id: 'mini-lounge',
      num: '07',
      name: 'Mini Lounge',
      detail: '70 Guests',
      icon: (
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round">
          <path d="M4 11V6a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v5" />
          <path d="M2 11h20v5a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2v-5Z" />
          <path d="M6 18v2" />
          <path d="M18 18v2" />
          <path d="M4 11v3" />
          <path d="M20 11v3" />
        </svg>
      ),
    },
    {
      id: 'guest-rooms',
      num: '08',
      name: 'Guest Rooms',
      detail: 'Individual & Dormitory Rooms',
      icon: (
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round">
          <path d="M2 4v16" />
          <path d="M2 8h18a2 2 0 0 1 2 2v10" />
          <path d="M2 17h20" />
          <path d="M6 8v9" />
          <circle cx="8" cy="12" r="1.5" />
        </svg>
      ),
    },
    {
      id: 'photography-spots',
      num: '09',
      name: 'Photography Spots',
      detail: 'Lush Green Backdrop',
      icon: (
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round">
          <path d="M14.5 4h-5L7 7H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3l-2.5-3z" />
          <circle cx="12" cy="13" r="3" />
        </svg>
      ),
    },
    {
      id: 'kitchen',
      num: '10',
      name: 'Kitchen',
      detail: 'Catering Support',
      icon: (
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round">
          <path d="M6 13.8V4a2 2 0 0 1 2-2h0a2 2 0 0 1 2 2v9.8" />
          <path d="M10 9H6" />
          <path d="M14 2v10" />
          <path d="M14 6a3 3 0 0 1 3-3h0a3 3 0 0 1 3 3v6" />
          <path d="M4 14h16a2 2 0 0 1 2 2v1a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4v-1a2 2 0 0 1 2-2Z" />
        </svg>
      ),
    },
  ];

  return (
    <section
      id="facilities"
      className={`facilities-section ${isVisible ? 'facilities-section--visible' : ''}`}
      ref={sectionRef}
    >
      {/* Decorative top transition */}
      <div className="facilities__edge" aria-hidden="true"></div>

      <div className="facilities__container">
        {/* Section Header */}
        <div className="facilities__header">
          <div className="facilities__eyebrow-wrap">
            <span className="facilities__eyebrow-line"></span>
            <span className="facilities__eyebrow-text">FACILITIES & AMENITIES</span>
            <span className="facilities__eyebrow-line"></span>
          </div>
          <div className="facilities__eyebrow-dots" aria-hidden="true">
            <span></span>
            <span></span>
            <span></span>
            <span></span>
            <span></span>
          </div>
          <h2 className="facilities__title">
            Engineered for <span className="facilities__title-accent">Excellence</span>
          </h2>
          <p className="facilities__subtitle">
            Thoughtful spaces and modern infrastructure designed to deliver flawless hospitality, comfort, and seamless celebration management.
          </p>
        </div>

        {/* Facilities Editorial Grid */}
        <div className="facilities__grid">
          {facilities.map((facility, index) => (
            <div
              key={facility.id}
              className={`facilities__card facilities__card--${facility.id}`}
              style={{ transitionDelay: `${0.05 + index * 0.05}s` }}
            >
              {/* Card Header: Index & Icon */}
              <div className="facilities__card-top">
                <span className="facilities__card-num">{facility.num}</span>
                <div className="facilities__card-icon" aria-hidden="true">
                  {facility.icon}
                </div>
              </div>

              {/* Card Body: Name & Short Detail */}
              <div className="facilities__card-body">
                <h3 className="facilities__card-name">{facility.name}</h3>
                <div className="facilities__card-detail-pill">
                  <span className="facilities__card-detail-dot"></span>
                  <span className="facilities__card-detail-text">{facility.detail}</span>
                </div>
              </div>

              {/* Bottom Decorative Line */}
              <div className="facilities__card-footer" aria-hidden="true">
                <span className="facilities__card-footer-line"></span>
              </div>
            </div>
          ))}
        </div>
      </div>
    </section>
  );
};

export default FacilitiesSection;
