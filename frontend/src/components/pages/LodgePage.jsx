import { useState, useEffect, useCallback, useMemo } from 'react';
import Header from '../Header';
import Footer from '../Footer';
import DatePicker from '../DatePicker';
import { roomApi } from '../../services/api';
import serviceLodge from '../../assets/images/service-lodge.jpg';

// Amenity Icon Mapper
const getAmenityIcon = (iconName, amenityName = '') => {
  const key = (iconName || amenityName).toLowerCase();
  if (key.includes('wifi')) {
    return (
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
        <path d="M5 12.55a11 11 0 0 1 14.08 0" /><path d="M1.42 9a16 16 0 0 1 21.16 0" /><path d="M8.53 16.11a6 6 0 0 1 6.95 0" /><line x1="12" y1="20" x2="12.01" y2="20" />
      </svg>
    );
  }
  if (key.includes('ac') || key.includes('air') || key.includes('condition')) {
    return (
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
        <path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6" />
      </svg>
    );
  }
  if (key.includes('tv') || key.includes('television')) {
    return (
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
        <rect x="2" y="7" width="20" height="15" rx="2" ry="2" /><polyline points="17 2 12 7 7 2" />
      </svg>
    );
  }
  if (key.includes('hot') || key.includes('water') || key.includes('geyser')) {
    return (
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
        <path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z" />
      </svg>
    );
  }
  if (key.includes('parking') || key.includes('car')) {
    return (
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
        <rect x="3" y="3" width="18" height="18" rx="4" /><path d="M9 17V7h4a3 3 0 0 1 0 6H9" />
      </svg>
    );
  }
  if (key.includes('power') || key.includes('backup') || key.includes('generator')) {
    return (
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
        <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2" />
      </svg>
    );
  }
  return (
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
      <polyline points="20 6 9 17 4 12" />
    </svg>
  );
};

// Image URL Resolver - only returns valid backend URLs, never substitutes random stock photos
const resolveRoomImgUrl = (img) => {
  if (!img) return null;
  if (typeof img === 'string') {
    if (img.startsWith('http') || img.startsWith('blob:') || img.startsWith('data:')) return img;
    return `http://localhost/sm_groups/backend/${img.replace(/^\/+/, '')}`;
  }
  if (img.url) return img.url;
  if (img.image_path) {
    if (img.image_path.startsWith('http') || img.image_path.startsWith('blob:') || img.image_path.startsWith('data:')) return img.image_path;
    return `http://localhost/sm_groups/backend/${img.image_path.replace(/^\/+/, '')}`;
  }
  return null;
};

// Date Helpers - Formatted as YYYY-MM-DD in local time
const formatDateToYYYYMMDD = (d) => {
  const year = d.getFullYear();
  const month = String(d.getMonth() + 1).padStart(2, '0');
  const day = String(d.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
};

const getTodayDateString = () => {
  return formatDateToYYYYMMDD(new Date());
};

const getTomorrowDateString = () => {
  const d = new Date();
  d.setDate(d.getDate() + 1);
  return formatDateToYYYYMMDD(d);
};

// Helper to reliably extract array of rooms from various backend response shapes
const extractRoomsArray = (res) => {
  if (!res) return [];
  if (Array.isArray(res)) return res;
  if (Array.isArray(res.data)) return res.data;
  if (Array.isArray(res.data?.data?.rooms)) return res.data.data.rooms;
  if (Array.isArray(res.data?.rooms)) return res.data.rooms;
  if (Array.isArray(res.data?.data)) return res.data.data;
  return [];
};

const LodgePage = ({ onNavigate }) => {
  // Data state
  const [rooms, setRooms] = useState([]);
  const [roomTypes, setRoomTypes] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  // Search & Filter state
  const [checkInDate, setCheckInDate] = useState(getTodayDateString());
  const [checkOutDate, setCheckOutDate] = useState(getTomorrowDateString());
  const [guests, setGuests] = useState(1);
  const [selectedRoomType, setSelectedRoomType] = useState('all');
  const [searchQuery, setSearchQuery] = useState('');

  // Active search metadata
  const [isAvailabilityActive, setIsAvailabilityActive] = useState(false);
  const [activeSearchMeta, setActiveSearchMeta] = useState(null);

  // Helper to extract and update room types from room list
  const updateDiscoveredRoomTypes = useCallback((roomsList) => {
    if (!Array.isArray(roomsList) || roomsList.length === 0) return;
    setRoomTypes((prev) => {
      const typesMap = new Map(prev.map((t) => [t.id, t.name]));
      roomsList.forEach((r) => {
        const typeId = r.room_type_id || r.room_type?.id;
        const typeName = r.room_type?.name;
        if (typeId && typeName && !typesMap.has(typeId)) {
          typesMap.set(typeId, typeName);
        }
      });
      return Array.from(typesMap.entries()).map(([id, name]) => ({ id, name }));
    });
  }, []);

  // Fetch standard room list
  const fetchAllRooms = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await roomApi.list('limit=100');
      const roomsList = extractRoomsArray(res);
      if (res.success && roomsList.length > 0) {
        setRooms(roomsList);
        updateDiscoveredRoomTypes(roomsList);
      } else if (res.success) {
        setRooms([]);
      } else {
        setRooms([]);
        if (res.error) setError(res.error);
      }
    } catch (err) {
      setError(err.message || 'Failed to load rooms.');
      setRooms([]);
    } finally {
      setLoading(false);
    }
  }, [updateDiscoveredRoomTypes]);

  // Handle Availability Search
  const handleCheckAvailability = async (e) => {
    if (e) e.preventDefault();
    if (!checkInDate || !checkOutDate) {
      setError('Please select both check-in and check-out dates.');
      return;
    }

    if (checkOutDate <= checkInDate) {
      setError('Check-out date must be after check-in date.');
      return;
    }

    setLoading(true);
    setError(null);

    const queryParams = new URLSearchParams();
    queryParams.append('check_in_date', checkInDate);
    queryParams.append('check_out_date', checkOutDate);
    if (guests > 1) queryParams.append('guests', guests);
    if (selectedRoomType !== 'all') queryParams.append('room_type_id', selectedRoomType);
    if (searchQuery.trim()) queryParams.append('search', searchQuery.trim());
    queryParams.append('limit', '100');

    try {
      const res = await roomApi.availability(queryParams.toString());
      const roomsList = extractRoomsArray(res);

      if (res.success) {
        setRooms(roomsList);
        updateDiscoveredRoomTypes(roomsList);
        setIsAvailabilityActive(true);
        const searchMeta = res.data?.data?.search || res.data?.search || {
          check_in_date: checkInDate,
          check_out_date: checkOutDate,
          number_of_nights: Math.max(Math.ceil((new Date(checkOutDate) - new Date(checkInDate)) / (1000 * 60 * 60 * 24)), 1),
          guests: guests,
        };
        setActiveSearchMeta(searchMeta);
      } else {
        setRooms([]);
        setIsAvailabilityActive(true);
        setActiveSearchMeta({
          check_in_date: checkInDate,
          check_out_date: checkOutDate,
          number_of_nights: 1,
          guests: guests,
        });
        if (res.error) setError(res.error);
      }
    } catch (err) {
      setError(err.message || 'Availability check failed.');
      setRooms([]);
    } finally {
      setLoading(false);
    }
  };

  // Initial load: first search availability with today/tomorrow
  useEffect(() => {
    handleCheckAvailability();
  }, []); // eslint-disable-line react-hooks/exhaustive-deps

  // Reset all filters
  const handleResetFilters = () => {
    setCheckInDate(getTodayDateString());
    setCheckOutDate(getTomorrowDateString());
    setGuests(1);
    setSelectedRoomType('all');
    setSearchQuery('');
    setIsAvailabilityActive(false);
    setActiveSearchMeta(null);
    fetchAllRooms();
  };

  // Navigate to Room Details with dates
  const handleNavigateDetails = (roomId) => {
    const query = new URLSearchParams();
    if (checkInDate) query.append('check_in', checkInDate);
    if (checkOutDate) query.append('check_out', checkOutDate);
    if (guests) query.append('guests', guests);
    onNavigate(`/lodge/${roomId}?${query.toString()}`);
  };

  return (
    <>
      <Header onNavigate={onNavigate} currentPage="/lodge" />

      <main>
        {/* Hero Section */}
        <div className="page-hero page-hero--service page-hero--lodge">
          <img src={serviceLodge} alt="SM Lodge Accommodation" className="page-hero__bg-img" />
          <div className="page-hero__overlay" />
          <div className="page-hero__content">
            <span className="page-hero__badge">Online Room Booking</span>
            <h1 className="page-hero__title">SM Lodge & Luxury Suites</h1>
            <p className="page-hero__desc">
              Hygienic, comfortable guest suites and AC luxury rooms conveniently located right beside our grand marriage venues.
            </p>
          </div>
        </div>

        <div className="service-page lodge-page">
          <div className="service-page__container">

            {/* REAL-TIME AVAILABILITY SEARCH BAR */}
            <section className="lodge-search-wrapper">
              <form onSubmit={handleCheckAvailability}>
                {/* Responsive Grid: Compact on mobile, wider on desktop */}
                <div className="lodge-search-grid">
                  {/* Check-In */}
                  <div className="lodge-field-group" style={{ margin: 0 }}>
                    <label className="lodge-field-label" htmlFor="lodge-checkin">
                      <span>Check-In Date *</span>
                    </label>
                    <DatePicker
                      id="lodge-checkin"
                      min={getTodayDateString()}
                      className="lodge-field-input"
                      value={checkInDate}
                      onChange={(e) => setCheckInDate(e.target.value)}
                      placeholder="Select Check-in"
                      required
                    />
                  </div>

                  {/* Check-Out */}
                  <div className="lodge-field-group" style={{ margin: 0 }}>
                    <label className="lodge-field-label" htmlFor="lodge-checkout">
                      <span>Check-Out Date *</span>
                    </label>
                    <DatePicker
                      id="lodge-checkout"
                      min={checkInDate || getTodayDateString()}
                      className="lodge-field-input"
                      value={checkOutDate}
                      onChange={(e) => setCheckOutDate(e.target.value)}
                      placeholder="Select Check-out"
                      required
                    />
                  </div>

                  {/* Guests */}
                  <div className="lodge-field-group" style={{ margin: 0 }}>
                    <label className="lodge-field-label" htmlFor="lodge-guests">
                      <span>Guests</span>
                    </label>
                    <select
                      id="lodge-guests"
                      className="lodge-field-select"
                      value={guests}
                      onChange={(e) => setGuests(Number(e.target.value))}
                    >
                      {[1, 2, 3, 4, 5, 6, 8, 10].map((n) => (
                        <option key={n} value={n}>
                          {n} {n === 1 ? 'Guest' : 'Guests'}
                        </option>
                      ))}
                    </select>
                  </div>

                  {/* Room Type */}
                  <div className="lodge-field-group" style={{ margin: 0 }}>
                    <label className="lodge-field-label" htmlFor="lodge-type">
                      <span>Room Category</span>
                    </label>
                    <select
                      id="lodge-type"
                      className="lodge-field-select"
                      value={selectedRoomType}
                      onChange={(e) => setSelectedRoomType(e.target.value)}
                    >
                      <option value="all">All Room Types</option>
                      {roomTypes.map((t) => (
                        <option key={t.id} value={t.id}>
                          {t.name}
                        </option>
                      ))}
                    </select>
                  </div>

                  {/* Action Buttons */}
                  <div style={{ display: 'flex', gap: '8px', width: '100%' }}>
                    <button
                      type="submit"
                      className="lodge-btn-book"
                      disabled={loading}
                      style={{
                        flex: 1,
                        height: '42px',
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                        gap: '6px',
                        padding: '0 16px',
                        fontSize: '0.9rem',
                      }}
                    >
                      {loading ? (
                        <span>Checking...</span>
                      ) : (
                        <>
                          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round">
                            <circle cx="11" cy="11" r="8" /><line x1="21" y1="21" x2="16.65" y2="16.65" />
                          </svg>
                          <span>search</span>
                        </>
                      )}
                    </button>

                    {(isAvailabilityActive || selectedRoomType !== 'all') && (
                      <button
                        type="button"
                        className="lodge-btn-reset"
                        onClick={handleResetFilters}
                        style={{ height: '42px', padding: '0 14px' }}
                        title="Reset all filters"
                      >
                        Reset
                      </button>
                    )}
                  </div>
                </div>
              </form>
            </section>

            {/* Error banner */}
            {error && (
              <div style={{ padding: '12px 18px', borderRadius: '10px', background: '#fee2e2', color: '#b91c1c', fontSize: '0.88rem', marginBottom: '20px' }}>
                <span>{error}</span>
              </div>
            )}

            {/* Room Listing Header */}
            <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: '20px' }}>
              <h2 className="service-page__heading" style={{ margin: 0 }}>
                {isAvailabilityActive ? 'Available Rooms for Your Stay' : 'All Guest Rooms & Suites'}
              </h2>
              {!loading && (
                <span style={{ fontSize: '0.84rem', fontWeight: 700, color: '#854d0e', background: '#ffffff', padding: '4px 12px', borderRadius: '20px', border: '1px solid #fef08a', boxShadow: '0 1px 3px rgba(0,0,0,0.04)' }}>
                  {rooms.length} {rooms.length === 1 ? 'Room' : 'Rooms'} Available
                </span>
              )}
            </div>

            {/* Loading Skeleton */}
            {loading && (
              <div className="lodge-rooms-grid">
                {[1, 2, 3].map((n) => (
                  <div key={n} className="lodge-card-skeleton">
                    <div className="lodge-skeleton-img" />
                    <div className="lodge-skeleton-body">
                      <div className="lodge-skeleton-line" style={{ width: '40%' }} />
                      <div className="lodge-skeleton-line" style={{ width: '80%', height: '22px' }} />
                      <div className="lodge-skeleton-line" style={{ width: '60%' }} />
                      <div className="lodge-skeleton-line" style={{ width: '100%', height: '36px', marginTop: 'auto' }} />
                    </div>
                  </div>
                ))}
              </div>
            )}

            {/* Empty State */}
            {!loading && rooms.length === 0 && (
              <div className="dashboard-empty-state" style={{ background: '#ffffff', borderRadius: '18px', border: '1px solid rgba(212, 160, 23, 0.2)' }}>
                <div className="dashboard-empty-state__icon-wrap">
                  <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                    <path d="M2 4v16M2 8h18a2 2 0 0 1 2 2v10M2 17h20M6 8v9" />
                  </svg>
                </div>
                <h3 className="dashboard-empty-state__title">No Rooms Available</h3>
                <p className="dashboard-empty-state__desc">
                  No rooms match your chosen check-in/check-out dates and guest criteria. Try adjusting your dates or resetting the search.
                </p>
                <button type="button" className="service-page__cta-btn" onClick={handleResetFilters}>
                  View All Rooms
                </button>
              </div>
            )}

            {/* Room Cards Grid */}
            {!loading && rooms.length > 0 && (
              <div className="lodge-rooms-grid">
                {rooms.map((room) => {
                  const roomId = room.id || room.room_id;
                  const firstImg = Array.isArray(room.images) && room.images.length > 0 ? room.images[0] : room.image_path;
                  const imgUrl = resolveRoomImgUrl(firstImg);
                  const pricePerNight = Number(room.pricing?.price_per_night || room.price_per_night || 0);
                  const stayTotal = room.pricing?.stay_total ? Number(room.pricing.stay_total) : (isAvailabilityActive && activeSearchMeta?.number_of_nights ? pricePerNight * activeSearchMeta.number_of_nights : null);

                  return (
                    <div key={roomId} className="lodge-room-card" id={`room-card-${roomId}`}>
                      {/* Image or Clean No-Photo State */}
                      <div className="lodge-room-img-container">
                        {imgUrl ? (
                          <img
                            src={imgUrl}
                            alt={room.name || `Room ${room.room_number}`}
                            className="lodge-room-img"
                            loading="lazy"
                          />
                        ) : (
                          <div className="dynamic-card-no-img">
                            <div className="dynamic-card-no-img__icon">
                              <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round">
                                <path d="M2 4v16M2 8h18a2 2 0 0 1 2 2v10M2 17h20M6 8v9" />
                              </svg>
                            </div>
                            <span className="dynamic-card-no-img__title">{room.name || `Room #${room.room_number}`}</span>
                            <span className="dynamic-card-no-img__tag">{room.room_type?.name || 'SM Lodge'}</span>
                          </div>
                        )}
                        <span className="lodge-room-type-tag">
                          {room.room_type?.name || 'Standard Room'}
                        </span>
                      </div>

                      {/* Content */}
                      <div className="lodge-room-content">
                        <div className="lodge-room-header">
                          <h3 className="lodge-room-name">{room.name || `Room #${room.room_number}`}</h3>
                          <span style={{ fontSize: '0.78rem', color: 'var(--text-muted)', fontWeight: 600 }}>
                            Room #{room.room_number}
                          </span>
                        </div>

                        {/* Capacity & Beds Info */}
                        <div style={{ display: 'flex', gap: '12px', fontSize: '0.82rem', color: 'var(--text-secondary)', marginBottom: '8px' }}>
                          <span style={{ display: 'flex', alignItems: 'center', gap: '4px' }}>
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                              <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" /><circle cx="9" cy="7" r="4" />
                            </svg>
                            Up to {room.guest_capacity} Guests
                          </span>
                          <span style={{ display: 'flex', alignItems: 'center', gap: '4px' }}>
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                              <path d="M2 4v16M2 8h18a2 2 0 0 1 2 2v10M2 17h20M6 8v9" />
                            </svg>
                            {room.bed_count} Bed(s)
                          </span>
                        </div>

                        {/* Amenities Chips */}
                        {Array.isArray(room.amenities) && room.amenities.length > 0 && (
                          <div className="lodge-room-amenities-row">
                            {room.amenities.slice(0, 3).map((amenity, idx) => {
                              const name = typeof amenity === 'string' ? amenity : amenity.name;
                              const icon = typeof amenity === 'object' ? amenity.icon : '';
                              return (
                                <span key={idx} className="lodge-room-amenity-chip">
                                  {getAmenityIcon(icon, name)}
                                  <span>{name}</span>
                                </span>
                              );
                            })}
                            {room.amenities.length > 3 && (
                              <span className="lodge-room-amenity-chip lodge-room-amenity-chip--more">
                                +{room.amenities.length - 3}
                              </span>
                            )}
                          </div>
                        )}

                        {/* Footer: Price + Actions */}
                        <div className="lodge-room-footer">
                          <div className="lodge-room-price-block">
                            <div className="lodge-room-price-val">
                              ₹{pricePerNight.toLocaleString('en-IN')}
                              <span style={{ fontSize: '0.75rem', fontWeight: 500, color: 'var(--text-muted)' }}>/night</span>
                            </div>
                            {stayTotal !== null && isAvailabilityActive && (
                              <div style={{ fontSize: '0.74rem', color: '#15803d', fontWeight: 700 }}>
                                ₹{stayTotal.toLocaleString('en-IN')} total stay
                              </div>
                            )}
                          </div>

                          <div className="lodge-room-actions">
                            <button
                              type="button"
                              className="lodge-btn-view"
                              onClick={() => handleNavigateDetails(roomId)}
                            >
                              View Details
                            </button>
                            <button
                              type="button"
                              className="lodge-btn-book"
                              onClick={() => handleNavigateDetails(roomId)}
                            >
                              Book Now
                            </button>
                          </div>
                        </div>
                      </div>
                    </div>
                  );
                })}
              </div>
            )}
          </div>
        </div>
      </main>

      <Footer onNavigate={onNavigate} />
    </>
  );
};

export default LodgePage;
