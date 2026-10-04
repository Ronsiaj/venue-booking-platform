import { useState, useEffect, useRef } from 'react';

const ContactSection = () => {
  const sectionRef = useRef(null);
  const [isVisible, setIsVisible] = useState(false);
  const [formSubmitted, setFormSubmitted] = useState(false);
  const [formData, setFormData] = useState({
    name: '',
    phone: '',
    email: '',
    eventType: '',
    message: '',
  });

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

  const handleChange = (e) => {
    const { name, value } = e.target;
    setFormData((prev) => ({ ...prev, [name]: value }));
  };

  const handleSubmit = (e) => {
    e.preventDefault();
    setFormSubmitted(true);
    setTimeout(() => {
      setFormSubmitted(false);
      setFormData({
        name: '',
        phone: '',
        email: '',
        eventType: '',
        message: '',
      });
    }, 4000);
  };

  return (
    <section
      id="contact"
      className={`contact-section ${isVisible ? 'contact-section--visible' : ''}`}
      ref={sectionRef}
    >
      {/* Decorative top transition */}
      <div className="contact__edge" aria-hidden="true"></div>

      <div className="contact__container">
        <div className="contact__layout">
          {/* Left Column: Contact Information */}
          <div className="contact__info-col">
            <div className="contact__eyebrow-wrap">
              <span className="contact__eyebrow-dash">—</span>
              <span className="contact__eyebrow-text">CONTACT US</span>
              <span className="contact__eyebrow-dash">—</span>
            </div>

            <h2 className="contact__title">
              We&apos;d Love to Hear <br />
              <span className="contact__title-accent">From You</span>
            </h2>

            <p className="contact__subtitle">
              Have questions or want to book our hall? Get in touch with us today!
            </p>

            <div className="contact__info-list">
              {/* Call Us */}
              <div className="contact__info-item">
                <div className="contact__info-icon" aria-hidden="true">
                  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                    <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                  </svg>
                </div>
                <div className="contact__info-content">
                  <span className="contact__info-label">CALL US</span>
                  <div className="contact__info-phones">
                    <a href="tel:8148165115" className="contact__info-val">
                      +91 81481 65115
                    </a>
                    <a href="tel:8148275115" className="contact__info-val">
                      +91 81482 75115
                    </a>
                    <a href="tel:04565460645" className="contact__info-val">
                      04565-460645
                    </a>
                  </div>
                </div>
              </div>

              {/* Email Us */}
              <div className="contact__info-item">
                <div className="contact__info-icon" aria-hidden="true">
                  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                    <rect width="20" height="16" x="2" y="4" rx="2"></rect>
                    <path d="M22 7 l-8.97 5.7 a1.94 1.94 0 0 1 -2.06 0 L2 7"></path>
                  </svg>
                </div>
                <div className="contact__info-content">
                  <span className="contact__info-label">EMAIL US</span>
                  <a href="mailto:info@smweddinghall.com" className="contact__info-val">
                    info@smweddinghall.com
                  </a>
                </div>
              </div>

              {/* Visit Us */}
              <div className="contact__info-item">
                <div className="contact__info-icon" aria-hidden="true">
                  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                    <path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"></path>
                    <circle cx="12" cy="10" r="3"></circle>
                  </svg>
                </div>
                <div className="contact__info-content">
                  <span className="contact__info-label">VISIT US</span>
                  <address className="contact__info-val contact__info-val--address">
                    No.07/07, Kottaiyur main road, near water tank, karaikudi - 630002
                  </address>
                  <div className="contact__map-pill">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                      <polygon points="3 6 9 3 15 6 21 3 21 18 15 21 9 18 3 21"></polygon>
                      <line x1="9" x2="9" y1="3" y2="18"></line>
                      <line x1="15" x2="15" y1="6" y2="21"></line>
                    </svg>
                    <span>Location Map (Coming Soon)</span>
                  </div>
                </div>
              </div>

              {/* Instagram Alone */}
              <div className="contact__info-item">
                <div className="contact__info-icon" aria-hidden="true">
                  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                    <rect width="20" height="20" x="2" y="2" rx="5" ry="5"></rect>
                    <path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"></path>
                    <line x1="17.5" x2="17.51" y1="6.5" y2="6.5"></line>
                  </svg>
                </div>
                <div className="contact__info-content">
                  <span className="contact__info-label">INSTAGRAM</span>
                  <a
                    href="https://www.instagram.com/smlodge_smmahal?stkn=MW4yY2diZmIzc2Jvcw=="
                    target="_blank"
                    rel="noopener noreferrer"
                    className="contact__info-val contact__info-val--link"
                  >
                    @smlodge_smmahal
                  </a>
                </div>
              </div>
            </div>
          </div>

          {/* Right Column: Floating Contact Form Card */}
          <div className="contact__form-col">
            <div className="contact__form-card">
              {formSubmitted ? (
                <div className="contact__form-success" role="alert">
                  <div className="contact__form-success-icon" aria-hidden="true">
                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
                      <path d="M20 6 9 17l-5-5"></path>
                    </svg>
                  </div>
                  <h3 className="contact__form-success-title">Message Received!</h3>
                  <p className="contact__form-success-desc">
                    Thank you for reaching out. Our events hospitality team will contact you shortly.
                  </p>
                </div>
              ) : (
                <form className="contact__form" onSubmit={handleSubmit}>
                  {/* Row 1: Name and Phone */}
                  <div className="contact__form-row">
                    <div className="contact__form-field">
                      <input
                        type="text"
                        id="contact-name"
                        name="name"
                        value={formData.name}
                        onChange={handleChange}
                        placeholder="Your Name"
                        required
                        className="contact__input"
                      />
                    </div>
                    <div className="contact__form-field">
                      <input
                        type="tel"
                        id="contact-phone"
                        name="phone"
                        value={formData.phone}
                        onChange={handleChange}
                        placeholder="Your Phone"
                        required
                        className="contact__input"
                      />
                    </div>
                  </div>

                  {/* Row 2: Email */}
                  <div className="contact__form-field">
                    <input
                      type="email"
                      id="contact-email"
                      name="email"
                      value={formData.email}
                      onChange={handleChange}
                      placeholder="Your Email"
                      required
                      className="contact__input"
                    />
                  </div>

                  {/* Row 3: Event Type Text Input */}
                  <div className="contact__form-field">
                    <input
                      type="text"
                      id="contact-event-type"
                      name="eventType"
                      value={formData.eventType}
                      onChange={handleChange}
                      placeholder="Event Type (e.g. Wedding, Reception, Birthday)"
                      required
                      className="contact__input"
                    />
                  </div>

                  {/* Row 4: Message */}
                  <div className="contact__form-field">
                    <textarea
                      id="contact-message"
                      name="message"
                      value={formData.message}
                      onChange={handleChange}
                      placeholder="Your Message"
                      rows="4"
                      required
                      className="contact__textarea"
                    ></textarea>
                  </div>

                  {/* Submit Button */}
                  <button type="submit" className="contact__submit-btn">
                    <span>SEND MESSAGE</span>
                    <span className="contact__submit-arrow" aria-hidden="true">→</span>
                  </button>
                </form>
              )}
            </div>
          </div>
        </div>
      </div>
    </section>
  );
};

export default ContactSection;
