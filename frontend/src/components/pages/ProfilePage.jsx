import { useState, useEffect, useCallback } from 'react';
import Header from '../Header';
import Footer from '../Footer';
import { authApi } from '../../services/api';
import { clearAuthSession, getAuthSession, isRoleAuthenticated } from '../../services/Utils';

const ProfilePage = ({ onNavigate, isDashboardView = false }) => {
  const [profile, setProfile] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [isLoggingOut, setIsLoggingOut] = useState(false);
  const [logoutModalOpen, setLogoutModalOpen] = useState(false);

  // ─────────────────────────────────────────────────────────────
  // IDENTIFY ACTIVE SESSION ROLE
  // ─────────────────────────────────────────────────────────────
  const getActiveRole = useCallback(() => {
    const staffSession = getAuthSession('staff');
    if (staffSession?.isAuthenticated && (staffSession.role === 'staff' || staffSession.user?.role === 'staff')) {
      return 'staff';
    }
    const adminSession = getAuthSession('admin');
    if (adminSession?.isAuthenticated && (adminSession.role === 'admin' || adminSession.user?.role === 'admin')) {
      return 'admin';
    }
    const customerSession = getAuthSession('customer');
    if (customerSession?.isAuthenticated) {
      return 'customer';
    }
    return 'customer';
  }, []);

  // ─────────────────────────────────────────────────────────────
  // FETCH USER PROFILE (/auth/profile.php)
  // ─────────────────────────────────────────────────────────────
  const fetchProfile = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const activeRole = getActiveRole();
      const session = getAuthSession(activeRole);
      
      if (!session?.isAuthenticated) {
        if (!isRoleAuthenticated('customer') && !isRoleAuthenticated('admin') && !isRoleAuthenticated('staff')) {
          onNavigate('/login');
          return;
        }
      }

      let res;
      if (activeRole === 'staff') {
        res = await authApi.staffProfile();
      } else if (activeRole === 'admin') {
        res = await authApi.adminProfile();
      } else {
        res = await authApi.profile();
      }

      if (res.success && res.data?.status && res.data?.data) {
        setProfile(res.data.data);
      } else {
        const errorMsg = res.error || res.data?.message || 'Failed to load profile details.';
        setError(errorMsg);
      }
    } catch (err) {
      setError(err.message || 'An unexpected error occurred while loading profile.');
    } finally {
      setLoading(false);
    }
  }, [getActiveRole, onNavigate]);

  useEffect(() => {
    fetchProfile();
  }, [fetchProfile]);

  // ─────────────────────────────────────────────────────────────
  // LOGOUT FLOW (/auth/logout.php)
  // ─────────────────────────────────────────────────────────────
  const handleLogout = async () => {
    if (isLoggingOut) return;
    setIsLoggingOut(true);
    const activeRole = profile?.role || getActiveRole();

    try {
      if (activeRole === 'staff') {
        await authApi.staffLogout();
      } else if (activeRole === 'admin') {
        await authApi.adminLogout();
      } else {
        await authApi.logout();
      }
    } catch (err) {
      console.warn('Backend logout warning:', err.message);
    } finally {
      if (activeRole === 'staff' || activeRole === 'admin') {
        clearAuthSession('admin');
        clearAuthSession('staff');
      } else {
        clearAuthSession('customer');
      }
      setIsLoggingOut(false);
      setLogoutModalOpen(false);

      if (onNavigate) {
        onNavigate('/login');
      } else {
        window.location.href = '/login';
      }
    }
  };

  const formatDate = (dateStr) => {
    if (!dateStr) return '—';
    try {
      const d = new Date(dateStr);
      if (isNaN(d.getTime())) return dateStr;
      return d.toLocaleDateString('en-IN', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
      });
    } catch {
      return dateStr;
    }
  };

  const getInitials = (name) => {
    if (!name) return 'U';
    const parts = name.trim().split(' ');
    if (parts.length >= 2) {
      return (parts[0][0] + parts[1][0]).toUpperCase();
    }
    return name.slice(0, 2).toUpperCase();
  };

  const isStaffUser = profile?.role === 'staff' || getActiveRole() === 'staff';
  const isAdminUser = profile?.role === 'admin' || getActiveRole() === 'admin';

  const content = (
    <>
      {/* Profile Hero Banner */}
      <section className="profile-hero-section">
        <div className="profile-hero-inner">
          <div className="profile-hero-badge">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
              <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
              <circle cx="12" cy="7" r="4" />
            </svg>
            <span>
              {isStaffUser ? 'Staff Account' : (isAdminUser ? 'Administrator Account' : 'Customer Account')}
            </span>
          </div>
          <h1 className="profile-hero-title">
            {isStaffUser ? 'Staff Profile & Credentials' : (isAdminUser ? 'Administrator Profile' : 'My Profile & Account')}
          </h1>
          <p className="profile-hero-subtitle">
            {isStaffUser
              ? 'Manage your staff credentials and review your SM Groups internal operations profile.'
              : (isAdminUser
                ? 'Manage your executive administrator credentials and system access permissions.'
                : 'Manage your guest account details and explore your SM Groups hospitality privileges.')}
          </p>
        </div>
      </section>

      {/* Profile Content Container */}
      <div className="profile-container">
        {loading && (
          <div className="profile-state-card profile-state--loading">
            <div className="profile-spinner"></div>
            <p>Retrieving your verified {isStaffUser ? 'staff' : (isAdminUser ? 'admin' : 'customer')} profile...</p>
          </div>
        )}

        {!loading && error && (
          <div className="profile-state-card profile-state--error">
            <div className="profile-error-icon">
              <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                <circle cx="12" cy="12" r="10" />
                <line x1="12" y1="8" x2="12" y2="12" />
                <line x1="12" y1="16" x2="12.01" y2="16" />
              </svg>
            </div>
            <h3 className="profile-error-title">Unable to Load Profile</h3>
            <p className="profile-error-desc">{error}</p>
            <div className="profile-error-actions">
              <button type="button" className="profile-btn profile-btn--primary" onClick={fetchProfile}>
                Retry Loading
              </button>
              <button type="button" className="profile-btn profile-btn--secondary" onClick={() => onNavigate('/login')}>
                Re-Login
              </button>
            </div>
          </div>
        )}

        {!loading && !error && profile && (
          <div className="profile-layout-grid">
            {/* Left Column: Profile Card */}
            <aside className="profile-sidebar-card">
              <div className="profile-avatar-wrap">
                <div className="profile-avatar-circle">
                  {getInitials(profile.name)}
                </div>
                <span className={`profile-status-dot profile-status-dot--${profile.status || 'active'}`} title={`Account Status: ${profile.status}`} />
              </div>

              <h2 className="profile-card-name">{profile.name}</h2>
              <p className="profile-card-email">{profile.email}</p>

              <div className="profile-tags-row">
                <span className="profile-role-tag">
                  {isStaffUser ? 'Staff Member' : (isAdminUser ? 'Administrator' : 'Guest Account')}
                </span>
                <span className={`profile-status-tag profile-status-tag--${profile.status || 'active'}`}>
                  {profile.status === 'active' ? 'Active Member' : profile.status}
                </span>
              </div>

              <div className="profile-card-meta">
                <div className="profile-meta-item">
                  <span className="profile-meta-label">
                    {isStaffUser ? 'Staff ID' : (isAdminUser ? 'Admin ID' : 'Customer ID')}
                  </span>
                  <span className="profile-meta-value">#{profile.user_id}</span>
                </div>
                <div className="profile-meta-item">
                  <span className="profile-meta-label">Member Since</span>
                  <span className="profile-meta-value">{formatDate(profile.created_at)}</span>
                </div>
              </div>

              <div className="profile-sidebar-actions">
                {isStaffUser || isAdminUser ? (
                  <button
                    type="button"
                    className="profile-btn profile-btn--primary"
                    style={{ width: '100%', display: 'flex', alignItems: 'center', justifyContent: 'center', gap: '8px', padding: '11px 18px', borderRadius: '10px' }}
                    onClick={() => onNavigate('/dashboard/bookings')}
                  >
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round">
                      <rect x="3" y="4" width="18" height="18" rx="2" ry="2" />
                      <line x1="16" y1="2" x2="16" y2="6" />
                      <line x1="8" y1="2" x2="8" y2="6" />
                      <line x1="3" y1="10" x2="21" y2="10" />
                    </svg>
                    <span>Manage Bookings</span>
                  </button>
                ) : (
                  <button
                    type="button"
                    className="profile-btn profile-btn--primary"
                    style={{ width: '100%', display: 'flex', alignItems: 'center', justifyContent: 'center', gap: '8px', padding: '11px 18px', borderRadius: '10px' }}
                    onClick={() => onNavigate('/my-bookings')}
                  >
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round">
                      <rect x="3" y="4" width="18" height="18" rx="2" ry="2" />
                      <line x1="16" y1="2" x2="16" y2="6" />
                      <line x1="8" y1="2" x2="8" y2="6" />
                      <line x1="3" y1="10" x2="21" y2="10" />
                    </svg>
                    <span>My Room Bookings</span>
                  </button>
                )}

                <button
                  type="button"
                  className="profile-logout-btn"
                  onClick={() => setLogoutModalOpen(true)}
                  disabled={isLoggingOut}
                >
                  <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
                    <polyline points="16 17 21 12 16 7" />
                    <line x1="21" y1="12" x2="9" y2="12" />
                  </svg>
                  <span>{isLoggingOut ? 'Logging out...' : 'Log Out Account'}</span>
                </button>
              </div>
            </aside>

            {/* Right Column: Profile Overview & Details */}
            <div className="profile-main-details">
              {/* Details Card */}
              <div className="profile-card">
                <div className="profile-card__header">
                  <div className="profile-card__title-wrap">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                      <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
                      <circle cx="12" cy="7" r="4" />
                    </svg>
                    <h3 className="profile-card__title">
                      {isStaffUser ? 'Verified Staff Account Details' : (isAdminUser ? 'Administrator Account Details' : 'Verified Account Information')}
                    </h3>
                  </div>
                  <span className="profile-viewonly-badge">View Only</span>
                </div>

                <div className="profile-fields-grid">
                  <div className="profile-field-box">
                    <span className="profile-field-label">Full Name</span>
                    <span className="profile-field-value">{profile.name || '—'}</span>
                  </div>

                  <div className="profile-field-box">
                    <span className="profile-field-label">Email Address</span>
                    <span className="profile-field-value">{profile.email || '—'}</span>
                  </div>

                  <div className="profile-field-box">
                    <span className="profile-field-label">Phone Number</span>
                    <span className="profile-field-value">{profile.phone || 'Not provided'}</span>
                  </div>

                  <div className="profile-field-box">
                    <span className="profile-field-label">Account Role</span>
                    <span className="profile-field-value" style={{ textTransform: 'capitalize' }}>
                      {profile.role || (isStaffUser ? 'Staff' : (isAdminUser ? 'Admin' : 'Customer'))}
                    </span>
                  </div>

                  <div className="profile-field-box">
                    <span className="profile-field-label">Registration Date</span>
                    <span className="profile-field-value">{formatDate(profile.created_at)}</span>
                  </div>

                  <div className="profile-field-box">
                    <span className="profile-field-label">Last Updated</span>
                    <span className="profile-field-value">{formatDate(profile.updated_at)}</span>
                  </div>
                </div>
              </div>

              {/* Quick Navigation / Explore Privileges */}
              <div className="profile-card profile-explore-card">
                <div className="profile-card__header">
                  <div className="profile-card__title-wrap">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                      <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2" />
                    </svg>
                    <h3 className="profile-card__title">
                      {isStaffUser || isAdminUser ? 'Operational Quick Access' : 'SM Groups Services & Bookings'}
                    </h3>
                  </div>
                </div>

                <p className="profile-explore-desc">
                  {isStaffUser || isAdminUser
                    ? 'Access and manage core property reservations, guest check-ins, financial audit trails, and hall bookings.'
                    : 'Ready for your next reservation? Explore our premium accommodation suites, banquet halls, and dining experiences.'}
                </p>

                <div className="profile-shortcuts-grid">
                  {isStaffUser || isAdminUser ? (
                    <>
                      <button type="button" className="profile-shortcut-item" onClick={() => onNavigate('/dashboard/bookings')}>
                        <div className="profile-shortcut-icon">🏨</div>
                        <div className="profile-shortcut-info">
                          <span className="profile-shortcut-title">Room Bookings</span>
                          <span className="profile-shortcut-sub">Check-in / Check-out</span>
                        </div>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                          <polyline points="9 18 15 12 9 6" />
                        </svg>
                      </button>

                      <button type="button" className="profile-shortcut-item" onClick={() => onNavigate('/dashboard/payments')}>
                        <div className="profile-shortcut-icon">💳</div>
                        <div className="profile-shortcut-info">
                          <span className="profile-shortcut-title">Payments & Audits</span>
                          <span className="profile-shortcut-sub">Razorpay transaction status</span>
                        </div>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                          <polyline points="9 18 15 12 9 6" />
                        </svg>
                      </button>

                      <button type="button" className="profile-shortcut-item" onClick={() => onNavigate('/dashboard/catering?tab=leads')}>
                        <div className="profile-shortcut-icon">🍽️</div>
                        <div className="profile-shortcut-info">
                          <span className="profile-shortcut-title">Inquiries & Leads</span>
                          <span className="profile-shortcut-sub">Catering and hall inquiries</span>
                        </div>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                          <polyline points="9 18 15 12 9 6" />
                        </svg>
                      </button>

                      <button type="button" className="profile-shortcut-item" onClick={() => onNavigate('/dashboard/halls')}>
                        <div className="profile-shortcut-icon">🏛️</div>
                        <div className="profile-shortcut-info">
                          <span className="profile-shortcut-title">Hall Venues</span>
                          <span className="profile-shortcut-sub">Banquet venue details</span>
                        </div>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                          <polyline points="9 18 15 12 9 6" />
                        </svg>
                      </button>
                    </>
                  ) : (
                    <>
                      <button type="button" className="profile-shortcut-item" onClick={() => onNavigate('/lodge')}>
                        <div className="profile-shortcut-icon">🏨</div>
                        <div className="profile-shortcut-info">
                          <span className="profile-shortcut-title">Lodge & Rooms</span>
                          <span className="profile-shortcut-sub">Browse available rooms</span>
                        </div>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                          <polyline points="9 18 15 12 9 6" />
                        </svg>
                      </button>

                      <button type="button" className="profile-shortcut-item" onClick={() => onNavigate('/halls')}>
                        <div className="profile-shortcut-icon">🏛️</div>
                        <div className="profile-shortcut-info">
                          <span className="profile-shortcut-title">Banquet & Halls</span>
                          <span className="profile-shortcut-sub">Plan grand occasions</span>
                        </div>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                          <polyline points="9 18 15 12 9 6" />
                        </svg>
                      </button>

                      <button type="button" className="profile-shortcut-item" onClick={() => onNavigate('/restaurant')}>
                        <div className="profile-shortcut-icon">🍽️</div>
                        <div className="profile-shortcut-info">
                          <span className="profile-shortcut-title">Dining & Restaurant</span>
                          <span className="profile-shortcut-sub">Curated food experiences</span>
                        </div>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                          <polyline points="9 18 15 12 9 6" />
                        </svg>
                      </button>

                      <button type="button" className="profile-shortcut-item" onClick={() => onNavigate('/contact')}>
                        <div className="profile-shortcut-icon">📞</div>
                        <div className="profile-shortcut-info">
                          <span className="profile-shortcut-title">Help & Support</span>
                          <span className="profile-shortcut-sub">Reach our concierge desk</span>
                        </div>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                          <polyline points="9 18 15 12 9 6" />
                        </svg>
                      </button>
                    </>
                  )}
                </div>
              </div>
            </div>
          </div>
        )}
      </div>
    </>
  );

  return (
    <div className="profile-page-wrapper">
      {!isDashboardView && <Header onNavigate={onNavigate} currentPage="/profile" />}

      <main className="profile-main-content">
        {content}
      </main>

      {/* ─── CONFIRM LOGOUT MODAL ─── */}
      {logoutModalOpen && (
        <div className="profile-modal-overlay" onClick={() => !isLoggingOut && setLogoutModalOpen(false)}>
          <div className="profile-modal-box" onClick={(e) => e.stopPropagation()} role="dialog" aria-modal="true">
            <div className="profile-modal-icon">
              <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round">
                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
                <polyline points="16 17 21 12 16 7" />
                <line x1="21" y1="12" x2="9" y2="12" />
              </svg>
            </div>
            <h3 className="profile-modal-title">Sign Out of Account?</h3>
            <p className="profile-modal-desc">
              Are you sure you want to end your {isStaffUser ? 'staff' : (isAdminUser ? 'administrator' : 'customer')} session on this device?
            </p>
            <div className="profile-modal-actions">
              <button
                type="button"
                className="profile-btn profile-btn--secondary"
                onClick={() => setLogoutModalOpen(false)}
                disabled={isLoggingOut}
              >
                Cancel
              </button>
              <button
                type="button"
                className="profile-btn profile-btn--danger"
                onClick={handleLogout}
                disabled={isLoggingOut}
              >
                {isLoggingOut ? 'Signing out...' : 'Yes, Sign Out'}
              </button>
            </div>
          </div>
        </div>
      )}

      {!isDashboardView && <Footer onNavigate={onNavigate} />}
    </div>
  );
};

export default ProfilePage;

