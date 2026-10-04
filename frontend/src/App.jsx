import { useState, useEffect } from 'react'
import Header from './components/Header'
import HeroSection from './components/HeroSection'
import AboutSection from './components/AboutSection'
import ServicesSection from './components/ServicesSection'
import Footer from './components/Footer'
import LoginPage from './components/LoginPage'
import RegisterPage from './components/RegisterPage'
import DashboardLayout from './partials/Layout'
import AdminDashboard from './components/AdminDashboard'
import RoomTypesModule from './components/RoomTypesModule'
import RoomsModule from './components/RoomsModule'
import HallTypesModule from './components/HallTypesModule'
import HallsModule from './components/HallsModule'
import HallFacilitiesModule from './components/HallFacilitiesModule'
import AmenitiesModule from './components/AmenitiesModule'
import FoodModule from './components/FoodModule'
import DiningModule from './components/DiningModule'
import CateringModule from './components/CateringModule'
import BookingsModule from './components/BookingsModule'
import GlobalLeadsModule from './components/GlobalLeadsModule'
import NotificationsModule from './components/NotificationsModule'
import ReportsModule from './components/ReportsModule'
import PaymentsModule from './components/PaymentsModule'
import StaffModule from './components/StaffModule'
import { isRoleAuthenticated, getAuthSession } from './services/Utils'

// Page components
import AboutPage from './components/pages/AboutPage'
import HallsPage from './components/pages/HallsPage'
import HallDetailsPage from './components/pages/HallDetailsPage'
import MahalPage from './components/pages/MahalPage'
import EventHallPage from './components/pages/EventHallPage'
import CateringPage from './components/pages/CateringPage'
import CateringDetailsPage from './components/pages/CateringDetailsPage'
import RestaurantPage from './components/pages/RestaurantPage'
import RestaurantDetailsPage from './components/pages/RestaurantDetailsPage'
import LodgePage from './components/pages/LodgePage'
import RoomDetailsPage from './components/pages/RoomDetailsPage'
import GalleryPage from './components/pages/GalleryPage'
import FacilitiesPage from './components/pages/FacilitiesPage'
import ContactPage from './components/pages/ContactPage'
import ProfilePage from './components/pages/ProfilePage'
import MyBookingsPage from './components/pages/MyBookingsPage'

const KNOWN_ROUTES = [
  '/login', '/register', '/profile', '/account', '/my-bookings', '/my-reservations', '/dashboard', '/dashboard/profile', '/dashboard/account', '/dashboard/room-types', '/dashboard/rooms', '/dashboard/hall-types', '/dashboard/halls', '/dashboard/hall-facilities', '/dashboard/amenities', '/dashboard/food', '/dashboard/dining', '/dashboard/catering', '/dashboard/bookings', '/dashboard/payments', '/dashboard/payment', '/payments', '/payment', '/dashboard/leads', '/dashboard/global-leads', '/dashboard/notifications', '/dashboard/reports', '/dashboard/report', '/reports', '/report', '/dashboard/staff', '/staff',
  '/about', '/halls', '/catering', '/restaurant', '/lodge', '/gallery', '/facilities', '/contact',
  '/services/halls', '/services/mahal', '/services/event-hall', '/services/catering', '/services/restaurant',
];

const isRecognizedRoute = (path) => {
  if (KNOWN_ROUTES.some((r) => path === r || path.startsWith('/dashboard'))) return true;
  if (path.startsWith('/halls/') || path.startsWith('/hall/')) return true;
  if (path.startsWith('/catering/') || path.startsWith('/services/catering/')) return true;
  if (path.startsWith('/restaurant/') || path.startsWith('/services/restaurant/') || path.startsWith('/dining/')) return true;
  if (path.startsWith('/lodge/') || path.startsWith('/rooms/') || path.startsWith('/room/')) return true;
  return false;
};

function App() {
  const [currentPath, setCurrentPath] = useState(() => {
    const path = window.location.pathname;
    if (isRecognizedRoute(path)) return path;
    return '/';
  });

  const navigate = (path) => {
    window.history.pushState({}, '', path);
    setCurrentPath(path);
    window.scrollTo({ top: 0, behavior: 'smooth' });
  };

  useEffect(() => {
    const handlePopState = () => {
      const path = window.location.pathname;
      if (isRecognizedRoute(path)) {
        setCurrentPath(path);
      } else {
        setCurrentPath('/');
      }
    };
    window.addEventListener('popstate', handlePopState);
    return () => window.removeEventListener('popstate', handlePopState);
  }, []);

  // Auth pages (unchanged)
  if (currentPath === '/login') return <LoginPage onNavigate={navigate} />;
  if (currentPath === '/register') return <RegisterPage onNavigate={navigate} />;

  // Profile & Account Page (Protected Route for Customer, Staff, Admin)
  if (currentPath === '/profile' || currentPath === '/account') {
    if (!isRoleAuthenticated('customer') && !isRoleAuthenticated('admin') && !isRoleAuthenticated('staff')) {
      return <LoginPage onNavigate={navigate} />;
    }
    return <ProfilePage onNavigate={navigate} />;
  }

  // Customer My Bookings Page (Protected Customer Route)
  if (currentPath === '/my-bookings' || currentPath === '/my-reservations' || currentPath === '/bookings') {
    if (!isRoleAuthenticated('customer')) {
      return <LoginPage onNavigate={navigate} />;
    }
    return <MyBookingsPage onNavigate={navigate} />;
  }

  // Standalone /reports redirect support
  if (currentPath === '/reports' || currentPath === '/report') {
    if (!isRoleAuthenticated('admin')) {
      return <LoginPage onNavigate={navigate} />;
    }
    return (
      <DashboardLayout onNavigate={navigate} activePath="/dashboard/reports">
        <ReportsModule onNavigate={navigate} />
      </DashboardLayout>
    );
  }

  // Standalone /payments redirect support
  if (currentPath === '/payments' || currentPath === '/payment') {
    if (!isRoleAuthenticated('admin')) {
      return <LoginPage onNavigate={navigate} />;
    }
    return (
      <DashboardLayout onNavigate={navigate} activePath="/dashboard/payments">
        <PaymentsModule onNavigate={navigate} />
      </DashboardLayout>
    );
  }

  // Dashboard modules (Protected Admin Routes)
  if (currentPath === '/dashboard' || currentPath.startsWith('/dashboard')) {
    if (!isRoleAuthenticated('admin')) {
      return <LoginPage onNavigate={navigate} />;
    }
    const cleanPath = currentPath.split('?')[0];
    const urlParams = new URLSearchParams(currentPath.includes('?') ? currentPath.split('?')[1] : window.location.search);
    const subTab = urlParams.get('tab');

    let dashboardContent = <AdminDashboard onNavigate={navigate} />;
    if (cleanPath === '/dashboard/room-types') {
      dashboardContent = <RoomTypesModule onNavigate={navigate} />;
    } else if (cleanPath === '/dashboard/rooms') {
      dashboardContent = <RoomsModule onNavigate={navigate} />;
    } else if (cleanPath === '/dashboard/hall-types') {
      dashboardContent = <HallTypesModule onNavigate={navigate} />;
    } else if (cleanPath === '/dashboard/halls') {
      dashboardContent = <HallsModule onNavigate={navigate} />;
    } else if (cleanPath === '/dashboard/hall-facilities') {
      dashboardContent = <HallFacilitiesModule onNavigate={navigate} />;
    } else if (cleanPath === '/dashboard/amenities') {
      dashboardContent = <AmenitiesModule onNavigate={navigate} />;
    } else if (cleanPath === '/dashboard/food') {
      dashboardContent = <FoodModule onNavigate={navigate} initialTab={subTab} />;
    } else if (cleanPath === '/dashboard/dining') {
      dashboardContent = <DiningModule onNavigate={navigate} initialTab={subTab} />;
    } else if (cleanPath === '/dashboard/catering') {
      dashboardContent = <CateringModule onNavigate={navigate} initialTab={subTab} />;
    } else if (cleanPath === '/dashboard/bookings') {
      dashboardContent = <BookingsModule onNavigate={navigate} />;
    } else if (
      cleanPath === '/dashboard/payments' ||
      cleanPath === '/dashboard/payment' ||
      cleanPath.startsWith('/dashboard/payments') ||
      cleanPath.startsWith('/dashboard/payment')
    ) {
      dashboardContent = <PaymentsModule onNavigate={navigate} />;
    } else if (cleanPath === '/dashboard/leads' || cleanPath === '/dashboard/global-leads') {
      dashboardContent = <GlobalLeadsModule onNavigate={navigate} />;
    } else if (cleanPath === '/dashboard/notifications') {
      dashboardContent = <NotificationsModule onNavigate={navigate} />;
    } else if (
      cleanPath === '/dashboard/reports' ||
      cleanPath === '/dashboard/report' ||
      cleanPath.startsWith('/dashboard/reports') ||
      cleanPath.startsWith('/dashboard/report')
    ) {
      dashboardContent = <ReportsModule onNavigate={navigate} />;
    } else if (cleanPath === '/dashboard/profile' || cleanPath === '/dashboard/account') {
      dashboardContent = <ProfilePage onNavigate={navigate} isDashboardView={true} />;
    } else if (
      cleanPath === '/dashboard/staff' ||
      cleanPath === '/staff' ||
      cleanPath.startsWith('/dashboard/staff')
    ) {
      const adminSession = getAuthSession('admin');
      if (adminSession?.user?.role === 'staff') {
        dashboardContent = <AdminDashboard onNavigate={navigate} />;
      } else {
        dashboardContent = <StaffModule onNavigate={navigate} />;
      }
    }

    return (
      <DashboardLayout onNavigate={navigate} activePath={currentPath}>
        {dashboardContent}
      </DashboardLayout>
    );
  }

  // Hall Details Dedicated Page
  // Hall Details Dedicated Page
  if (currentPath.startsWith('/halls/') || currentPath.startsWith('/hall/')) {
    const cleanPath = currentPath.split('?')[0];
    const parts = cleanPath.split('/');
    const hallId = parts[parts.length - 1];
    if (hallId && !isNaN(Number(hallId))) {
      return <HallDetailsPage hallId={Number(hallId)} onNavigate={navigate} />;
    }
  }

  // Catering Details Dedicated Page
  if (currentPath.startsWith('/catering/') || currentPath.startsWith('/services/catering/')) {
    const cleanPath = currentPath.split('?')[0];
    const parts = cleanPath.split('/');
    const packageId = parts[parts.length - 1];
    if (packageId && !isNaN(Number(packageId))) {
      return <CateringDetailsPage packageId={Number(packageId)} onNavigate={navigate} />;
    }
  }

  // Restaurant & Dining Details Dedicated Page
  if (currentPath.startsWith('/restaurant/') || currentPath.startsWith('/services/restaurant/') || currentPath.startsWith('/dining/')) {
    const cleanPath = currentPath.split('?')[0];
    const parts = cleanPath.split('/');
    const packageId = parts[parts.length - 1];
    if (packageId && !isNaN(Number(packageId))) {
      return <RestaurantDetailsPage packageId={Number(packageId)} onNavigate={navigate} />;
    }
  }

  // Room Details Dedicated Page
  if (currentPath.startsWith('/lodge/') || currentPath.startsWith('/rooms/') || currentPath.startsWith('/room/')) {
    const cleanPath = currentPath.split('?')[0];
    const parts = cleanPath.split('/');
    const roomId = parts[parts.length - 1];
    if (roomId && !isNaN(Number(roomId))) {
      return <RoomDetailsPage roomId={Number(roomId)} onNavigate={navigate} />;
    }
  }

  // Service pages (Dynamic Services Structure)
  if (currentPath === '/halls' || currentPath === '/services/halls') return <HallsPage onNavigate={navigate} />;
  if (currentPath === '/services/mahal') return <MahalPage onNavigate={navigate} />;
  if (currentPath === '/services/event-hall') return <EventHallPage onNavigate={navigate} />;
  if (currentPath === '/catering' || currentPath === '/services/catering') return <CateringPage onNavigate={navigate} />;
  if (currentPath === '/restaurant' || currentPath === '/services/restaurant') return <RestaurantPage onNavigate={navigate} />;

  // Standalone pages
  if (currentPath === '/about') return <AboutPage onNavigate={navigate} />;
  if (currentPath === '/lodge') return <LodgePage onNavigate={navigate} />;
  if (currentPath === '/gallery') return <GalleryPage onNavigate={navigate} />;
  if (currentPath === '/facilities') return <FacilitiesPage onNavigate={navigate} />;
  if (currentPath === '/contact') return <ContactPage onNavigate={navigate} />;

  // Home page (slimmed down)
  return (
    <>
      <Header onNavigate={navigate} currentPage="/" />
      <main>
        <HeroSection onNavigate={navigate} />
        <AboutSection />
        <ServicesSection onNavigate={navigate} />
      </main>
      <Footer onNavigate={navigate} />
    </>
  );
}

export default App;
