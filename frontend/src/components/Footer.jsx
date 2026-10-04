import { useEffect, useRef, useState } from 'react';

const Footer = ({ onNavigate }) => {
  const footerRef = useRef(null);
  const [isVisible, setIsVisible] = useState(false);
  const [newsletterEmail, setNewsletterEmail] = useState('');
  const [newsletterStatus, setNewsletterStatus] = useState(null);

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

    const el = footerRef.current;
    if (el) observer.observe(el);
    return () => observer.disconnect();
  }, []);

  const scrollToTop = () => {
    window.scrollTo({ top: 0, behavior: 'smooth' });
  };

  const handleLinkClick = (e, href) => {
    e.preventDefault();
    if (onNavigate) {
      onNavigate(href);
    }
  };

  const handleNewsletterSubmit = (e) => {
    e.preventDefault();
    if (!newsletterEmail || !newsletterEmail.includes('@')) {
      setNewsletterStatus({ type: 'error', message: 'Please enter a valid email address.' });
      return;
    }
    setNewsletterStatus({ type: 'success', message: 'Thank you for subscribing to SM Groups updates!' });
    setNewsletterEmail('');
    setTimeout(() => {
      setNewsletterStatus(null);
    }, 5000);
  };

  const navLinks = [
    { label: 'Home', href: '/' },
    { label: 'About Us', href: '/about' },
    { label: 'Lodge & Rooms', href: '/lodge' },
    { label: 'Photo Gallery', href: '/gallery' },
    { label: 'Facilities', href: '/facilities' },
    { label: 'Contact Us', href: '/contact' },
  ];

  const serviceLinks = [
    { label: 'Halls & Venues', href: '/halls' },
    { label: 'Grand Catering Service', href: '/catering' },
    { label: 'Restaurant & Dining', href: '/restaurant' },
    { label: 'SM Lodge & Suites', href: '/lodge' },
  ];

  return (
    <footer
      className={`footer-wrapper ${isVisible ? 'footer-wrapper--visible' : ''}`}
      ref={footerRef}
    >
      <div className="footer__container">
        {/* Main Columns Grid */}
        <div className="footer__grid">
          {/* Col 1: Brand, Bio & Social Circles */}
          <div className="footer__col footer__col--brand">
            <a href="/" className="footer__logo" onClick={(e) => handleLinkClick(e, '/')}>
              <div className="footer__logo-avatar">
                <span>SM</span>
              </div>
              <div className="footer__logo-text-group">
                <span className="footer__logo-title">SM Groups</span>
                <span className="footer__logo-subtitle">CELEBRATING HOSPITALITY</span>
              </div>
            </a>

            <p className="footer__desc">
              Premier event destinations & luxury hospitality in Karaikudi. We create timeless celebration experiences, authentic traditional weddings, and unforgettable grand occasions.
            </p>

            {/* Circular Social Buttons */}
            <div className="footer__social-circles">
              <a
                href="https://www.instagram.com/smlodge_smmahal?stkn=MW4yY2diZmIzc2Jvcw=="
                target="_blank"
                rel="noopener noreferrer"
                className="footer__social-circle-btn"
                aria-label="Instagram"
                title="Follow on Instagram"
              >
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                  <rect width="20" height="20" x="2" y="2" rx="5" ry="5" />
                  <path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z" />
                  <line x1="17.5" x2="17.51" y1="6.5" y2="6.5" />
                </svg>
              </a>

              <a
                href="https://facebook.com"
                target="_blank"
                rel="noopener noreferrer"
                className="footer__social-circle-btn"
                aria-label="Facebook"
                title="Facebook"
              >
                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                  <path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z" />
                </svg>
              </a>

              <a
                href="tel:8148165115"
                className="footer__social-circle-btn"
                aria-label="Call Us"
                title="Call Directly"
              >
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                  <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z" />
                </svg>
              </a>

              <a
                href="mailto:info@smweddinghall.com"
                className="footer__social-circle-btn"
                aria-label="Email Us"
                title="Send Email"
              >
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                  <rect width="20" height="16" x="2" y="4" rx="2" />
                  <path d="M22 7l-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7" />
                </svg>
              </a>
            </div>
          </div>

          {/* Col 2: Navigation Links */}
          <div className="footer__col">
            <h3 className="footer__col-title">Quick Links</h3>
            <ul className="footer__links-list">
              {navLinks.map((link, idx) => (
                <li key={idx}>
                  <a href={link.href} className="footer__link" onClick={(e) => handleLinkClick(e, link.href)}>
                    <span>{link.label}</span>
                  </a>
                </li>
              ))}
            </ul>
          </div>

          {/* Col 3: Services */}
          <div className="footer__col">
            <h3 className="footer__col-title">Our Services</h3>
            <ul className="footer__links-list">
              {serviceLinks.map((link, idx) => (
                <li key={idx}>
                  <a href={link.href} className="footer__link" onClick={(e) => handleLinkClick(e, link.href)}>
                    <span>{link.label}</span>
                  </a>
                </li>
              ))}
            </ul>
          </div>

          {/* Col 4: Reach Us */}
          <div className="footer__col footer__col--reach">
            <h3 className="footer__col-title">Reach Us</h3>
            <div className="footer__contact-list">
              <div className="footer__contact-line">
                <a href="tel:8148165115" className="footer__contact-link">+91 81481 65115</a>
                <a href="tel:8148275115" className="footer__contact-link">+91 81482 75115</a>
                <a href="tel:04565460645" className="footer__contact-link">04565-460645</a>
              </div>

              <div className="footer__contact-line">
                <a href="mailto:info@smweddinghall.com" className="footer__contact-link">
                  info@smweddinghall.com
                </a>
              </div>

              <div className="footer__contact-line">
                <address className="footer__address">
                  No.07/07, Kottaiyur main road, near water tank, Karaikudi - 630002
                </address>
              </div>
            </div>
          </div>

          {/* Col 5: Newsletter Sign Up */}
          <div className="footer__col footer__col--newsletter">
            <h3 className="footer__col-title">Newsletter Sign Up</h3>
            <p className="footer__newsletter-desc">
              Sign up for exclusive event updates, festive packages & booking discounts.
            </p>

            <form onSubmit={handleNewsletterSubmit} className="footer__newsletter-form">
              <div className="footer__newsletter-box">
                <input
                  type="email"
                  className="footer__newsletter-input"
                  placeholder="enter your email address"
                  value={newsletterEmail}
                  onChange={(e) => setNewsletterEmail(e.target.value)}
                  aria-label="Email address for newsletter"
                />
                <button type="submit" className="footer__newsletter-submit">
                  SUBMIT
                </button>
              </div>
            </form>

            {newsletterStatus && (
              <div
                className={`footer__newsletter-msg footer__newsletter-msg--${newsletterStatus.type}`}
                role="alert"
              >
                {newsletterStatus.message}
              </div>
            )}
          </div>
        </div>
      </div>

      {/* Bottom Full-Width Dark Bar */}
      <div className="footer__bottom-bar">
        <div className="footer__bottom-container">
          <p className="footer__copyright">
            © {new Date().getFullYear()} <span className="footer__copyright-brand">SM Groups</span>. All Rights Reserved.
          </p>

          <div className="footer__bottom-actions">
            {/* Payment / Trust Badges */}
            <div className="footer__payment-badges" aria-label="Accepted payment methods">
              <span className="footer__payment-badge footer__payment-badge--razorpay">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="#3395ff" style={{ marginRight: '5px' }}>
                  <path d="M22.436 0l-11.91 7.773-4.854 4.148-5.672 3.655 4.318 4.297 3.327-2.146 1.773-1.514 1.159 4.354 1.83 3.433 11.529-17.5-6.938 10.702 3.799-2.451 4.669-14.751z" />
                </svg>
                Razorpay Secure
              </span>
            </div>

            <button
              onClick={scrollToTop}
              className="footer__back-to-top"
              aria-label="Scroll back to top of page"
            >
              <span>Back to Top</span>
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round">
                <path d="M18 15l-6-6-6 6" />
              </svg>
            </button>
          </div>
        </div>
      </div>
    </footer>
  );
};

export default Footer;
