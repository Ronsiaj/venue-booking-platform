import GallerySection from '../GallerySection';
import Header from '../Header';
import Footer from '../Footer';

const GalleryPage = ({ onNavigate }) => {
  return (
    <>
      <Header onNavigate={onNavigate} currentPage="/gallery" />
      <main>
        <div className="page-hero page-hero--about">
          <div className="page-hero__content">
            <span className="page-hero__badge">Gallery</span>
            <h1 className="page-hero__title">Photo Gallery</h1>
            <p className="page-hero__desc">Cherished moments from weddings, receptions, and celebrations at SM Groups</p>
          </div>
        </div>
        <GallerySection />
      </main>
      <Footer onNavigate={onNavigate} />
    </>
  );
};

export default GalleryPage;
