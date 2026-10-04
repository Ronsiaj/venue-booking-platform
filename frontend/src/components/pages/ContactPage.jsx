import ContactSection from '../ContactSection';
import Header from '../Header';
import Footer from '../Footer';

const ContactPage = ({ onNavigate }) => {
  return (
    <>
      <Header onNavigate={onNavigate} currentPage="/contact" />
      <main>
        <div className="page-hero page-hero--about">
          <div className="page-hero__content">
            <span className="page-hero__badge">Contact</span>
            <h1 className="page-hero__title">Get In Touch</h1>
            <p className="page-hero__desc">Reach out for bookings, enquiries, and venue visits</p>
          </div>
        </div>
        <ContactSection />
      </main>
      <Footer onNavigate={onNavigate} />
    </>
  );
};

export default ContactPage;
