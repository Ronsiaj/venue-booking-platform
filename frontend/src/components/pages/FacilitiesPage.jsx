import FacilitiesSection from '../FacilitiesSection';
import Header from '../Header';
import Footer from '../Footer';

const FacilitiesPage = ({ onNavigate }) => {
  return (
    <>
      <Header onNavigate={onNavigate} currentPage="/facilities" />
      <main>
        <div className="page-hero page-hero--about">
          <div className="page-hero__content">
            <span className="page-hero__badge">Facilities</span>
            <h1 className="page-hero__title">Facilities & Amenities</h1>
            <p className="page-hero__desc">Modern infrastructure designed for flawless hospitality and comfort</p>
          </div>
        </div>
        <FacilitiesSection />
      </main>
      <Footer onNavigate={onNavigate} />
    </>
  );
};

export default FacilitiesPage;
