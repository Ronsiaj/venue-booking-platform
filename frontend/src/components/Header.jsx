import { useState, useEffect, useCallback } from 'react';
import { getAuthSession, clearAuthSession } from '../services/Utils';
import { authApi } from '../services/api';

const Header = ({ onNavigate, currentPage = '/' }) => {
  const [isScrolled, setIsScrolled] = useState(false);
  const [isMobileMenuOpen, setIsMobileMenuOpen] = useState(false);
  const [isServicesOpen, setIsServicesOpen] = useState(false);
  const [isMobileServicesOpen, setIsMobileServicesOpen] = useState(false);
  const [isLoggingOut, setIsLoggingOut] = useState(false);

  const [authSession, setAuthSession] = useState(() => getAuthSession('customer'));
  const isCustomerLoggedIn = authSession.isAuthenticated;
  const customerName = authSession.user?.name || 'Guest';
  const firstName = customerName.split(' ')[0];

  useEffect(() => {
    setAuthSession(getAuthSession('customer'));
  }, [currentPage]);

  useEffect(() => {
    const handleAuthChange = () => {
      setAuthSession(getAuthSession('customer'));
    };
    window.addEventListener('storage', handleAuthChange);
    window.addEventListener('auth-change', handleAuthChange);
    return () => {
      window.removeEventListener('storage', handleAuthChange);
      window.removeEventListener('auth-change', handleAuthChange);
    };
  }, []);

  const handleHeaderLogout = async (e) => {
    if (e && e.preventDefault) e.preventDefault();
    if (isLoggingOut) return;
    setIsLoggingOut(true);
    try {
      await authApi.logout();
    } catch (err) {
      console.warn('Header logout warning:', err.message);
    } finally {
      clearAuthSession('customer');
      setIsLoggingOut(false);
      setIsMobileMenuOpen(false);
      if (onNavigate) {
        onNavigate('/login');
      } else {
        window.location.href = '/login';
      }
    }
  };

  const isHomePage = currentPage === '/';

  const navLinks = [
    { id: 'nav-home', label: 'Home', href: '/' },
    { id: 'nav-hall', label: 'Hall', href: '/halls' },
    { id: 'nav-catering', label: 'Catering', href: '/catering' },
    { id: 'nav-restaurant', label: 'Dining', href: '/restaurant' },
    { id: 'nav-lodge', label: 'Lodge', href: '/lodge' },
    { id: 'nav-about', label: 'About', href: '/about' },
    { id: 'nav-contact', label: 'Contact', href: '/contact' },
  ];

  // Scroll detection for header style change
  const handleScroll = useCallback(() => {
    setIsScrolled(window.scrollY > 80);
  }, []);

  useEffect(() => {
    window.addEventListener('scroll', handleScroll, { passive: true });
    handleScroll();
    return () => window.removeEventListener('scroll', handleScroll);
  }, [handleScroll]);

  // Lock body scroll when mobile menu open
  useEffect(() => {
    document.body.style.overflow = isMobileMenuOpen ? 'hidden' : '';
    return () => { document.body.style.overflow = ''; };
  }, [isMobileMenuOpen]);

  // Close mobile menu on resize
  useEffect(() => {
    const handleResize = () => {
      if (window.innerWidth > 960 && isMobileMenuOpen) {
        setIsMobileMenuOpen(false);
      }
    };
    window.addEventListener('resize', handleResize);
    return () => window.removeEventListener('resize', handleResize);
  }, [isMobileMenuOpen]);

  // Close services dropdown on click outside
  useEffect(() => {
    const handleClickOutside = (e) => {
      if (isServicesOpen && !e.target.closest('.header__nav-dropdown-wrap')) {
        setIsServicesOpen(false);
      }
    };
    document.addEventListener('click', handleClickOutside);
    return () => document.removeEventListener('click', handleClickOutside);
  }, [isServicesOpen]);

  const handleNavClick = (e, href) => {
    e.preventDefault();
    setIsMobileMenuOpen(false);
    setIsServicesOpen(false);
    setIsMobileServicesOpen(false);

    if (href === '#') return;

    // If it's a hash link on the home page, scroll to section
    if (href.startsWith('#') && isHomePage) {
      const target = document.getElementById(href.replace('#', ''));
      if (target) target.scrollIntoView({ behavior: 'smooth', block: 'start' });
      return;
    }

    if (onNavigate) onNavigate(href);
  };

  const isActive = (href) => {
    if (href === '/') return currentPage === '/';
    if (href === '/halls') {
      return (
        currentPage === '/halls' ||
        currentPage === '/services/mahal' ||
        currentPage === '/services/event-hall' ||
        currentPage.startsWith('/hall')
      );
    }
    if (href === '/catering') {
      return currentPage === '/catering' || currentPage === '/services/catering' || currentPage.startsWith('/catering/');
    }
    if (href === '/restaurant') {
      return (
        currentPage === '/restaurant' ||
        currentPage === '/services/restaurant' ||
        currentPage.startsWith('/restaurant/') ||
        currentPage.startsWith('/services/restaurant/') ||
        currentPage.startsWith('/dining/')
      );
    }
    if (href === '/lodge') {
      return currentPage === '/lodge' || currentPage.startsWith('/lodge');
    }
    return currentPage.startsWith(href);
  };

  const toggleMobileMenu = () => setIsMobileMenuOpen(prev => !prev);

  // Shared full-width header styling across Home and all customer pages
  const headerClass = [
    'header',
    isHomePage ? '' : 'header--inner-page',
    (isScrolled || !isHomePage) ? 'header--scrolled' : '',
    isMobileMenuOpen ? 'header--menu-open' : '',
  ].filter(Boolean).join(' ');

  return (
    <>
      <header id="site-header" className={headerClass}>
        <div className="header__inner">
          {/* Logo */}
          <a href="/" className="header__logo" onClick={(e) => handleNavClick(e, '/')}>
            <span className="header__logo-icon">SM</span>
            <span className="header__logo-text">Groups</span>
          </a>

          {/* Desktop Navigation */}
          <nav className="header__nav" aria-label="Main navigation">
            <ul className="header__nav-list">
              {navLinks.map((link) => {
                if (link.dropdown) {
                  return (
                    <li key={link.id} className="header__nav-item header__nav-dropdown-wrap">
                      <button
                        className={`header__nav-link header__nav-link--dropdown ${currentPage.startsWith('/services') ? 'header__nav-link--active' : ''}`}
                        onClick={(e) => {
                          e.stopPropagation();
                          setIsServicesOpen(prev => !prev);
                        }}
                        aria-expanded={isServicesOpen}
                        aria-haspopup="true"
                        type="button"
                      >
                        {link.label}
                        <svg className="header__dropdown-chevron" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round" style={{ transform: isServicesOpen ? 'rotate(180deg)' : 'rotate(0deg)', transition: 'transform 0.2s ease' }}>
                          <polyline points="6 9 12 15 18 9" />
                        </svg>
                      </button>
                      <div className={`header__dropdown-menu ${isServicesOpen ? 'header__dropdown-menu--open' : ''}`}>
                        {link.dropdown.map(sub => (
                          <a
                            key={sub.id}
                            href={sub.href}
                            className={`header__dropdown-link ${isActive(sub.href) ? 'header__dropdown-link--active' : ''}`}
                            onClick={(e) => handleNavClick(e, sub.href)}
                          >
                            {sub.label}
                          </a>
                        ))}
                      </div>
                    </li>
                  );
                }

                return (
                  <li key={link.id} className="header__nav-item">
                    <a
                      id={link.id}
                      href={link.href}
                      className={`header__nav-link ${isActive(link.href) ? 'header__nav-link--active' : ''}`}
                      onClick={(e) => handleNavClick(e, link.href)}
                    >
                      {link.label}
                    </a>
                  </li>
                );
              })}
            </ul>
          </nav>

          {/* Desktop Actions */}
          <div className="header__actions">
            {isCustomerLoggedIn ? (
              <>
                <a
                  id="header-my-bookings"
                  href="/my-bookings"
                  className={`header__login-btn ${currentPage === '/my-bookings' ? 'header__login-btn--active' : ''}`}
                  onClick={(e) => { e.preventDefault(); if (onNavigate) onNavigate('/my-bookings'); }}
                  title="View My Room Bookings"
                >
                  <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round">
                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2" />
                    <line x1="16" y1="2" x2="16" y2="6" />
                    <line x1="8" y1="2" x2="8" y2="6" />
                    <line x1="3" y1="10" x2="21" y2="10" />
                  </svg>
                  <span>My Bookings</span>
                </a>

                <a
                  id="header-profile"
                  href="/profile"
                  className={`header__login-btn ${currentPage === '/profile' ? 'header__login-btn--active' : ''}`}
                  onClick={(e) => { e.preventDefault(); if (onNavigate) onNavigate('/profile'); }}
                  title="View Customer Profile"
                >
                  <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round">
                    <path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2" />
                    <circle cx="12" cy="7" r="4" />
                  </svg>
                  <span>{firstName || 'My Profile'}</span>
                </a>

                <button
                  id="header-logout-btn"
                  type="button"
                  className="header__login-btn header__logout-btn"
                  onClick={handleHeaderLogout}
                  title="Logout"
                  aria-label="Logout"
                  disabled={isLoggingOut}
                >
                  <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
                    <polyline points="16 17 21 12 16 7" />
                    <line x1="21" y1="12" x2="9" y2="12" />
                  </svg>
                  <span>{isLoggingOut ? 'Logging out...' : 'Logout'}</span>
                </button>
              </>
            ) : (
              <>
                <a
                  id="header-login"
                  href="/login"
                  className="header__login-btn"
                  onClick={(e) => { e.preventDefault(); if (onNavigate) onNavigate('/login'); }}
                >
                  <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round">
                    <path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2" />
                    <circle cx="12" cy="7" r="4" />
                  </svg>
                  <span>Login</span>
                </a>
                <a
                  id="header-book-now"
                  href="/lodge"
                  className="header__cta"
                  onClick={(e) => { e.preventDefault(); if (onNavigate) onNavigate('/lodge'); }}
                >
                  Book Now
                </a>
              </>
            )}
          </div>

          {/* Hamburger */}
          <button
            id="mobile-menu-toggle"
            className={`header__hamburger ${isMobileMenuOpen ? 'header__hamburger--active' : ''}`}
            type="button"
            onClick={toggleMobileMenu}
            aria-label={isMobileMenuOpen ? 'Close menu' : 'Open menu'}
            aria-expanded={isMobileMenuOpen}
          >
            <span className="header__hamburger-line" />
            <span className="header__hamburger-line" />
            <span className="header__hamburger-line" />
          </button>
        </div>
      </header>

      {/* Mobile Menu Overlay */}
      <div
        className={`header__mobile-overlay ${isMobileMenuOpen ? 'header__mobile-overlay--visible' : ''}`}
        onClick={() => setIsMobileMenuOpen(false)}
        aria-hidden="true"
      />

      {/* Mobile Menu Panel */}
      <div
        className={`header__mobile-menu ${isMobileMenuOpen ? 'header__mobile-menu--open' : ''}`}
        role="dialog"
        aria-modal="true"
        aria-label="Mobile navigation"
      >
        <div className="header__mobile-header">
          <a href="/" className="header__logo" onClick={(e) => handleNavClick(e, '/')}>
            <span className="header__logo-icon">SM</span>
            <span className="header__logo-text">Groups</span>
          </a>
          <button
            className="header__mobile-close"
            type="button"
            onClick={() => setIsMobileMenuOpen(false)}
            aria-label="Close navigation menu"
          >
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
              <line x1="18" y1="6" x2="6" y2="18" />
              <line x1="6" y1="6" x2="18" y2="18" />
            </svg>
          </button>
        </div>

        <nav className="header__mobile-nav">
          <ul className="header__mobile-list">
            {navLinks.map((link, index) => {
              if (link.dropdown) {
                return (
                  <li key={link.id} className="header__mobile-item" style={{ transitionDelay: isMobileMenuOpen ? `${0.05 + index * 0.05}s` : '0s' }}>
                    <button
                      className={`header__mobile-link header__mobile-link--dropdown ${currentPage.startsWith('/services') ? 'header__mobile-link--active' : ''}`}
                      onClick={() => setIsMobileServicesOpen(prev => !prev)}
                      type="button"
                    >
                      <span>{link.label}</span>
                      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round" style={{ transform: isMobileServicesOpen ? 'rotate(180deg)' : 'rotate(0deg)', transition: 'transform 0.2s ease' }}>
                        <polyline points="6 9 12 15 18 9" />
                      </svg>
                    </button>
                    <div className={`header__mobile-dropdown ${isMobileServicesOpen ? 'header__mobile-dropdown--open' : ''}`}>
                      {link.dropdown.map(sub => (
                        <a
                          key={sub.id}
                          href={sub.href}
                          className={`header__mobile-dropdown-link ${isActive(sub.href) ? 'header__mobile-dropdown-link--active' : ''}`}
                          onClick={(e) => handleNavClick(e, sub.href)}
                        >
                          {sub.label}
                        </a>
                      ))}
                    </div>
                  </li>
                );
              }

              return (
                <li
                  key={link.id}
                  className="header__mobile-item"
                  style={{ transitionDelay: isMobileMenuOpen ? `${0.05 + index * 0.05}s` : '0s' }}
                >
                  <a
                    href={link.href}
                    className={`header__mobile-link ${isActive(link.href) ? 'header__mobile-link--active' : ''}`}
                    onClick={(e) => handleNavClick(e, link.href)}
                  >
                    {link.label}
                  </a>
                </li>
              );
            })}
          </ul>
          <div
            className="header__mobile-cta-wrap"
            style={{ transitionDelay: isMobileMenuOpen ? `${0.05 + navLinks.length * 0.05}s` : '0s' }}
          >
            {isCustomerLoggedIn ? (
              <>
                <a
                  href="/my-bookings"
                  className="header__mobile-login-btn"
                  onClick={(e) => { e.preventDefault(); setIsMobileMenuOpen(false); if (onNavigate) onNavigate('/my-bookings'); }}
                >
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round">
                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2" />
                    <line x1="16" y1="2" x2="16" y2="6" />
                    <line x1="8" y1="2" x2="8" y2="6" />
                    <line x1="3" y1="10" x2="21" y2="10" />
                  </svg>
                  <span>My Bookings</span>
                </a>
                <a
                  href="/profile"
                  className="header__mobile-login-btn"
                  onClick={(e) => { e.preventDefault(); setIsMobileMenuOpen(false); if (onNavigate) onNavigate('/profile'); }}
                >
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round">
                    <path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2" />
                    <circle cx="12" cy="7" r="4" />
                  </svg>
                  <span>My Profile ({firstName})</span>
                </a>
                <button
                  type="button"
                  className="header__mobile-login-btn header__mobile-logout-btn"
                  onClick={handleHeaderLogout}
                  disabled={isLoggingOut}
                  title="Logout"
                  aria-label="Logout"
                >
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
                    <polyline points="16 17 21 12 16 7" />
                    <line x1="21" y1="12" x2="9" y2="12" />
                  </svg>
                  <span>{isLoggingOut ? 'Logging out...' : 'Logout'}</span>
                </button>
              </>
            ) : (
              <>
                <a
                  href="/login"
                  className="header__mobile-login-btn"
                  onClick={(e) => { e.preventDefault(); setIsMobileMenuOpen(false); if (onNavigate) onNavigate('/login'); }}
                >
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round">
                    <path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2" />
                    <circle cx="12" cy="7" r="4" />
                  </svg>
                  <span>Login</span>
                </a>
                <a
                  href="/lodge"
                  className="header__mobile-cta"
                  onClick={(e) => { e.preventDefault(); setIsMobileMenuOpen(false); if (onNavigate) onNavigate('/lodge'); }}
                >
                  Book Now
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                    <line x1="5" y1="12" x2="19" y2="12" />
                    <polyline points="12 5 19 12 12 19" />
                  </svg>
                </a>
              </>
            )}
          </div>
        </nav>
      </div>
    </>
  );
};

export default Header;
