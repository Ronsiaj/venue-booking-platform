import { useState } from 'react';
import api from '../services/api';
import { saveAuthSession } from '../services/Utils';

const RegisterPage = ({ onNavigate }) => {
  const [formData, setFormData] = useState({
    name: '',
    email: '',
    phone: '',
    password: '',
    confirmPassword: '',
  });

  const [showPassword, setShowPassword] = useState(false);
  const [showConfirmPassword, setShowConfirmPassword] = useState(false);
  const [formError, setFormError] = useState('');
  const [successMessage, setSuccessMessage] = useState('');
  const [isSubmitting, setIsSubmitting] = useState(false);

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

    const nameValue = formData.name.trim();
    const emailValue = formData.email.trim().toLowerCase();
    const phoneValue = formData.phone.trim();
    const passwordValue = formData.password;
    const confirmPasswordValue = formData.confirmPassword;

    // 1. Name Validation
    if (!nameValue) {
      setFormError('Name is required.');
      return;
    }
    if (nameValue.length < 2 || nameValue.length > 100) {
      setFormError('Name must be between 2 and 100 characters.');
      return;
    }
    if (!/^[A-Za-z]+(?: [A-Za-z]+)*$/.test(nameValue)) {
      setFormError('Name can contain only letters with single spaces between words.');
      return;
    }

    // 2. Email Validation
    if (!emailValue) {
      setFormError('Email is required.');
      return;
    }
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(emailValue)) {
      setFormError('Please enter a valid email address.');
      return;
    }

    // 3. Phone Validation
    if (!phoneValue) {
      setFormError('Phone number is required.');
      return;
    }
    if (!/^\d+$/.test(phoneValue)) {
      setFormError('Phone number must contain only digits.');
      return;
    }
    if (!/^[6-9]\d{9}$/.test(phoneValue)) {
      setFormError('Please enter a valid 10-digit Indian mobile number.');
      return;
    }
    if (/^(\d)\1{9}$/.test(phoneValue)) {
      setFormError('Please enter a valid mobile number.');
      return;
    }

    // 4. Password Validation
    if (!passwordValue) {
      setFormError('Password is required.');
      return;
    }
    if (passwordValue.length < 8 || passwordValue.length > 72) {
      setFormError('Password must be between 8 and 72 characters.');
      return;
    }
    if (!/[A-Z]/.test(passwordValue)) {
      setFormError('Password must contain at least one uppercase letter.');
      return;
    }
    if (!/[a-z]/.test(passwordValue)) {
      setFormError('Password must contain at least one lowercase letter.');
      return;
    }
    if (!/[0-9]/.test(passwordValue)) {
      setFormError('Password must contain at least one number.');
      return;
    }

    // 5. Confirm Password Validation
    if (!confirmPasswordValue) {
      setFormError('Confirm password is required.');
      return;
    }
    if (passwordValue !== confirmPasswordValue) {
      setFormError('Password and confirm password do not match.');
      return;
    }

    setIsSubmitting(true);
    setFormError('');

    try {
      // Backend customer_register.php expects: { name, email, phone, password, confirm_password }
      const response = await api.post('/auth/customer_register.php', {
        name: nameValue,
        email: emailValue,
        phone: phoneValue,
        password: passwordValue,
        confirm_password: confirmPasswordValue,
      });

      if (response.success && response.data?.status === true && response.data?.data) {
        const authData = response.data.data;
        
        // Save isolated Customer session (does not overwrite Admin/Staff session)
        saveAuthSession(authData);

        const successMsg = response.data.message || 'Customer registration successful.';
        setSuccessMessage(successMsg);

        setTimeout(() => {
          onNavigate('/login');
        }, 600);
      } else {
        const errorMsg = response.error || response.data?.message || 'Unable to complete registration.';
        setFormError(errorMsg);
        setIsSubmitting(false);
      }
    } catch (err) {
      setFormError(err.message || 'Unable to connect to server. Please try again.');
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
            <p className="auth-ui-card__top-subtitle">Already have an Account?</p>
            <button
              type="button"
              className="auth-ui-card__top-pill-btn"
              onClick={() => onNavigate('/login')}
            >
              Login
            </button>
          </div>

          {/* Bottom Form Section */}
          <div className="auth-ui-card__body">
            <h1 className="auth-ui-card__form-title">Register</h1>

            {successMessage && (
              <div className="auth-ui-card__success" role="status">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
                  <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                  <polyline points="22 4 12 14.01 9 11.01"></polyline>
                </svg>
                <span>{successMessage}</span>
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

              {/* Full Name */}
              <div className="auth-ui-form__field">
                <input
                  type="text"
                  name="name"
                  value={formData.name}
                  onChange={handleChange}
                  placeholder="Full Name"
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

              {/* Email Address */}
              <div className="auth-ui-form__field">
                <input
                  type="email"
                  name="email"
                  value={formData.email}
                  onChange={handleChange}
                  placeholder="Email Address"
                  className="auth-ui-form__input"
                  autoComplete="off"
                  required
                />
                <span className="auth-ui-form__icon" aria-hidden="true">
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/>
                  </svg>
                </span>
              </div>

              {/* Phone Number */}
              <div className="auth-ui-form__field">
                <input
                  type="tel"
                  name="phone"
                  value={formData.phone}
                  onChange={handleChange}
                  placeholder="Phone Number"
                  className="auth-ui-form__input"
                  autoComplete="off"
                  required
                />
                <span className="auth-ui-form__icon" aria-hidden="true">
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M6.62 10.79c1.44 2.83 3.76 5.14 6.59 6.59l2.2-2.2c.27-.27.67-.36 1.02-.24 1.12.37 2.33.57 3.57.57.55 0 1 .45 1 1V20c0 .55-.45 1-1 1-9.39 0-17-7.61-17-17 0-.55.45-1 1-1h3.5c.55 0 1 .45 1 1 0 1.25.2 2.45.57 3.57.11.35.03.74-.25 1.02l-2.2 2.2z"/>
                  </svg>
                </span>
              </div>

              {/* Password */}
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

              {/* Confirm Password */}
              <div className="auth-ui-form__field">
                <input
                  type={showConfirmPassword ? 'text' : 'password'}
                  name="confirmPassword"
                  value={formData.confirmPassword}
                  onChange={handleChange}
                  placeholder="Confirm Password"
                  className="auth-ui-form__input"
                  autoComplete="new-password"
                  required
                />
                <button
                  type="button"
                  className="auth-ui-form__icon auth-ui-form__icon--btn"
                  onClick={() => setShowConfirmPassword((prev) => !prev)}
                  aria-label={showConfirmPassword ? 'Hide password' : 'Show password'}
                >
                  {showConfirmPassword ? (
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

              {/* Main Register CTA Button */}
              <button
                type="submit"
                className="auth-ui-form__submit-btn"
                disabled={isSubmitting}
              >
                {isSubmitting ? 'Registering...' : 'Register'}
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

export default RegisterPage;
