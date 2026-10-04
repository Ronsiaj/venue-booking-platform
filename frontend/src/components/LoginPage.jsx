import { useState } from 'react';
import api from '../services/api';
import { saveAuthSession } from '../services/Utils';

const LoginPage = ({ onNavigate }) => {
  const [formData, setFormData] = useState({
    username: '',
    password: '',
  });

  const [showPassword, setShowPassword] = useState(false);
  const [formError, setFormError] = useState('');
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [loginSuccessMessage, setLoginSuccessMessage] = useState('');

  const handleChange = (e) => {
    const { name, value } = e.target;
    setFormData((prev) => ({
      ...prev,
      [name]: value,
    }));
    if (formError) setFormError('');
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    if (isSubmitting) return;

    const emailValue = formData.username.trim().toLowerCase();
    const passwordValue = formData.password;

    // Client-side validation matching backend requirements
    if (!emailValue) {
      setFormError('Please enter your email address.');
      return;
    }

    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(emailValue)) {
      setFormError('Please enter a valid email address.');
      return;
    }

    if (!passwordValue) {
      setFormError('Please enter your password.');
      return;
    }

    if (passwordValue.length < 8) {
      setFormError('Password must be at least 8 characters.');
      return;
    }

    setIsSubmitting(true);
    setFormError('');

    try {
      // Backend login.php strictly requires: { email, password }
      const response = await api.post('/auth/login.php', {
        email: emailValue,
        password: passwordValue,
      });

      if (response.success && response.data?.status === true && response.data?.data) {
        const authData = response.data.data;
        
        // Save isolated session storage (Admin and Customer never overwrite each other)
        saveAuthSession(authData);

        const successMsg = response.data.message || 'Login successful!';
        setLoginSuccessMessage(successMsg);

        // Role-based navigation
        setTimeout(() => {
          if (authData.role === 'admin' || authData.role === 'staff') {
            onNavigate('/dashboard');
          } else {
            onNavigate('/');
          }
        }, 500);
      } else {
        const errorMsg = response.error || response.data?.message || 'Invalid email or password.';
        setFormError(errorMsg);
        setIsSubmitting(false);
      }
    } catch (err) {
      setFormError(err.message || 'Unable to connect to login server. Please try again.');
      setIsSubmitting(false);
    }
  };


  return (
    <div className="auth-reference-page">
      {/* Background Soft Glow Orbs */}
      <div className="auth-reference-page__orb auth-reference-page__orb--1" aria-hidden="true"></div>
      <div className="auth-reference-page__orb auth-reference-page__orb--2" aria-hidden="true"></div>

      <div className="auth-reference-page__wrapper">
        {/* Top Back Navigation Bar */}
        <div className="auth-reference-page__nav">
          <button
            type="button"
            className="auth-reference-page__back-btn"
            onClick={() => onNavigate('/')}
            aria-label="Back to Home"
          >
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round">
              <path d="M15 18 l-6 -6 6 -6" />
            </svg>
            <span>Back to Home</span>
          </button>

          <a href="#hero-section" className="auth-reference-page__brand" onClick={(e) => { e.preventDefault(); onNavigate('/'); }}>
            <span className="auth-reference-page__brand-icon">SM</span>
            <span className="auth-reference-page__brand-text">Groups</span>
          </a>
        </div>

        {/* Auth Card Matching Reference UI */}
        <div className="auth-ui-card">
          {/* Top Curved Dark Container */}
          <div className="auth-ui-card__top">
            <h2 className="auth-ui-card__top-title">Hello, Welcome</h2>
            <p className="auth-ui-card__top-subtitle">Don&apos;t have an Account?</p>
            <button
              type="button"
              className="auth-ui-card__top-pill-btn"
              onClick={() => onNavigate('/register')}
            >
              Register
            </button>
          </div>

          {/* Bottom Form Section */}
          <div className="auth-ui-card__body">
            <h1 className="auth-ui-card__form-title">Login</h1>

            {loginSuccessMessage && (
              <div className="auth-ui-card__success" role="status">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
                  <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                  <polyline points="22 4 12 14.01 9 11.01"></polyline>
                </svg>
                <span>{loginSuccessMessage}</span>
              </div>
            )}

            {formError && (
              <div className="auth-ui-card__error" role="alert">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                  <circle cx="12" cy="12" r="10"></circle>
                  <line x1="12" y1="8" x2="12" y2="12"></line>
                  <line x1="12" y1="16" x2="12.01" y2="16"></line>
                </svg>
                <span>{formError}</span>
              </div>
            )}

            <form onSubmit={handleSubmit} className="auth-ui-form" autoComplete="off" noValidate>
              {/* Email / Username Field */}
              <div className="auth-ui-form__field">
                <input
                  type="email"
                  name="username"
                  value={formData.username}
                  onChange={handleChange}
                  placeholder="Email Address"
                  className="auth-ui-form__input"
                  autoComplete="off"
                  required
                />
                <span className="auth-ui-form__icon" aria-hidden="true">
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                  </svg>
                </span>
              </div>

              {/* Password Field */}
              <div className="auth-ui-form__field">
                <input
                  type={showPassword ? 'text' : 'password'}
                  name="password"
                  value={formData.password}
                  onChange={handleChange}
                  placeholder="Password"
                  className="auth-ui-form__input"
                  autoComplete="new-password"
                  required
                />
                <button
                  type="button"
                  className="auth-ui-form__icon auth-ui-form__icon--btn"
                  onClick={() => setShowPassword((prev) => !prev)}
                  aria-label={showPassword ? 'Hide password' : 'Show password'}
                >
                  {showPassword ? (
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                      <path d="M9.88 9.88a3 3 0 1 0 4.24 4.24" />
                      <path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68" />
                      <path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61" />
                      <line x1="2" y1="2" x2="22" y2="22" />
                    </svg>
                  ) : (
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                      <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z" />
                      <circle cx="12" cy="12" r="3" />
                    </svg>
                  )}
                </button>
              </div>

              {/* Forgot Password Link */}
              <div className="auth-ui-form__forgot-wrap">
                <a
                  href="#forgot-password"
                  className="auth-ui-form__forgot-link"
                  onClick={(e) => { e.preventDefault(); }}
                >
                  Forgot Password
                </a>
              </div>

              {/* Main Login CTA Button */}
              <button
                type="submit"
                className="auth-ui-form__submit-btn"
                disabled={isSubmitting}
              >
                {isSubmitting ? 'Logging in...' : 'Login'}
              </button>
            </form>
          </div>
        </div>

        {/* Footer Copyright */}
        <p className="auth-reference-page__footer">
          © {new Date().getFullYear()} SM Groups • All rights reserved.
        </p>
      </div>
    </div>
  );
};

export default LoginPage;
