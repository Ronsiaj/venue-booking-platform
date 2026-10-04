import AboutSection from '../AboutSection';
import Header from '../Header';
import Footer from '../Footer';

const AboutPage = ({ onNavigate }) => {
  return (
    <>
      <Header onNavigate={onNavigate} currentPage="/about" />
      <main>
        <div className="page-hero page-hero--about">
          <div className="page-hero__content">
            <span className="page-hero__badge">About Us</span>
            <h1 className="page-hero__title">Our Story & Legacy</h1>
            <p className="page-hero__desc">15+ years of crafting unforgettable celebrations in Karaikudi</p>
          </div>
        </div>
        <AboutSection />
      </main>
      <Footer onNavigate={onNavigate} />
    </>
  );
};

export default AboutPage;
