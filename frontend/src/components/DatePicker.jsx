import { useState, useRef, useEffect, useCallback, useMemo } from 'react';

const DAYS_OF_WEEK = ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'];
const MONTH_NAMES = [
  'January', 'February', 'March', 'April', 'May', 'June',
  'July', 'August', 'September', 'October', 'November', 'December'
];

const parseDateString = (str) => {
  if (!str || typeof str !== 'string') return null;
  const parts = str.split('-');
  if (parts.length !== 3) return null;
  const y = parseInt(parts[0], 10);
  const m = parseInt(parts[1], 10) - 1;
  const d = parseInt(parts[2], 10);
  if (isNaN(y) || isNaN(m) || isNaN(d)) return null;
  return new Date(y, m, d);
};

const formatDateString = (date) => {
  if (!date || isNaN(date.getTime())) return '';
  const y = date.getFullYear();
  const m = String(date.getMonth() + 1).padStart(2, '0');
  const d = String(date.getDate()).padStart(2, '0');
  return `${y}-${m}-${d}`;
};

const DatePicker = ({
  value = '',
  onChange,
  min = '',
  max = '',
  placeholder = 'Select Date',
  className = 'lodge-field-input',
  id,
  name,
  required = false,
  disabled = false,
  style = {},
}) => {
  const [isOpen, setIsOpen] = useState(false);
  const containerRef = useRef(null);

  const selectedDate = useMemo(() => parseDateString(value), [value]);
  const minDate = useMemo(() => parseDateString(min), [min]);
  const maxDate = useMemo(() => parseDateString(max), [max]);

  // Current viewed month/year in calendar popup
  const [viewYear, setViewYear] = useState(() => {
    return selectedDate ? selectedDate.getFullYear() : (minDate ? minDate.getFullYear() : new Date().getFullYear());
  });
  const [viewMonth, setViewMonth] = useState(() => {
    return selectedDate ? selectedDate.getMonth() : (minDate ? minDate.getMonth() : new Date().getMonth());
  });

  // Sync view when selectedDate or min changes
  useEffect(() => {
    if (selectedDate) {
      setViewYear(selectedDate.getFullYear());
      setViewMonth(selectedDate.getMonth());
    }
  }, [selectedDate]);

  // Close popup on click outside
  useEffect(() => {
    if (!isOpen) return;
    const handleClickOutside = (e) => {
      if (containerRef.current && !containerRef.current.contains(e.target)) {
        setIsOpen(false);
      }
    };
    document.addEventListener('mousedown', handleClickOutside);
    document.addEventListener('touchstart', handleClickOutside);
    return () => {
      document.removeEventListener('mousedown', handleClickOutside);
      document.removeEventListener('touchstart', handleClickOutside);
    };
  }, [isOpen]);

  const handlePrevMonth = (e) => {
    e.preventDefault();
    e.stopPropagation();
    if (viewMonth === 0) {
      setViewMonth(11);
      setViewYear((y) => y - 1);
    } else {
      setViewMonth((m) => m - 1);
    }
  };

  const handleNextMonth = (e) => {
    e.preventDefault();
    e.stopPropagation();
    if (viewMonth === 11) {
      setViewMonth(0);
      setViewYear((y) => y + 1);
    } else {
      setViewMonth((m) => m + 1);
    }
  };

  const handleSelectDate = (date) => {
    const formatted = formatDateString(date);
    if (onChange) {
      // Send both standard event-like object and raw string
      onChange({ target: { value: formatted, name: name || id } });
    }
    setIsOpen(false);
  };

  const handleClear = (e) => {
    e.preventDefault();
    e.stopPropagation();
    if (onChange) {
      onChange({ target: { value: '', name: name || id } });
    }
    setIsOpen(false);
  };

  const handleToday = (e) => {
    e.preventDefault();
    e.stopPropagation();
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    handleSelectDate(today);
  };

  // Generate calendar days
  const calendarDays = useMemo(() => {
    const firstDayOfMonth = new Date(viewYear, viewMonth, 1).getDay();
    const daysInMonth = new Date(viewYear, viewMonth + 1, 0).getDate();
    const daysInPrevMonth = new Date(viewYear, viewMonth, 0).getDate();

    const days = [];

    // Previous month padding days
    for (let i = firstDayOfMonth - 1; i >= 0; i--) {
      const d = new Date(viewYear, viewMonth - 1, daysInPrevMonth - i);
      days.push({ date: d, isCurrentMonth: false });
    }

    // Current month days
    for (let i = 1; i <= daysInMonth; i++) {
      const d = new Date(viewYear, viewMonth, i);
      days.push({ date: d, isCurrentMonth: true });
    }

    // Next month padding days to complete 6 rows (42 days) or at least full weeks
    const totalRendered = days.length;
    const remaining = totalRendered % 7 === 0 ? 0 : 7 - (totalRendered % 7);
    for (let i = 1; i <= remaining; i++) {
      const d = new Date(viewYear, viewMonth + 1, i);
      days.push({ date: d, isCurrentMonth: false });
    }

    return days;
  }, [viewYear, viewMonth]);

  const todayStr = formatDateString(new Date());

  return (
    <div
      ref={containerRef}
      className="custom-datepicker-wrapper"
      style={{ position: 'relative', width: '100%', minWidth: 0, ...style }}
    >
      {/* Clickable Input Trigger */}
      <div
        id={id}
        className={`${className} custom-datepicker-input`}
        onClick={() => !disabled && setIsOpen((prev) => !prev)}
        style={{
          cursor: disabled ? 'not-allowed' : 'pointer',
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'space-between',
          userSelect: 'none',
          backgroundColor: disabled ? '#f5f5f5' : undefined,
        }}
      >
        <span style={{ color: value ? 'inherit' : '#9ca3af', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
          {value || placeholder}
        </span>
        <svg
          width="16"
          height="16"
          viewBox="0 0 24 24"
          fill="none"
          stroke="var(--gold-600, #b45309)"
          strokeWidth="2"
          strokeLinecap="round"
          strokeLinejoin="round"
          style={{ flexShrink: 0, marginLeft: '8px' }}
        >
          <rect x="3" y="4" width="18" height="18" rx="2" ry="2" />
          <line x1="16" y1="2" x2="16" y2="6" />
          <line x1="8" y1="2" x2="8" y2="6" />
          <line x1="3" y1="10" x2="21" y2="10" />
        </svg>
      </div>

      {/* Hidden input for HTML form validation if required */}
      {required && (
        <input
          type="text"
          value={value}
          required={required}
          readOnly
          tabIndex={-1}
          style={{
            position: 'absolute',
            opacity: 0,
            pointerEvents: 'none',
            height: 0,
            width: 0,
            bottom: 0,
            left: 0,
          }}
        />
      )}

      {/* Custom Calendar Dropdown Popup */}
      {isOpen && (
        <div className="custom-datepicker-popup">
          {/* Header Month / Year Navigation */}
          <div className="custom-datepicker-header">
            <span className="custom-datepicker-title">
              {MONTH_NAMES[viewMonth]} {viewYear}
            </span>
            <div className="custom-datepicker-nav">
              <button
                type="button"
                className="custom-datepicker-nav-btn"
                onClick={handlePrevMonth}
                title="Previous Month"
              >
                ‹
              </button>
              <button
                type="button"
                className="custom-datepicker-nav-btn"
                onClick={handleNextMonth}
                title="Next Month"
              >
                ›
              </button>
            </div>
          </div>

          {/* Day of Week Labels */}
          <div className="custom-datepicker-weekdays">
            {DAYS_OF_WEEK.map((day) => (
              <span key={day} className="custom-datepicker-weekday">
                {day}
              </span>
            ))}
          </div>

          {/* Days Grid */}
          <div className="custom-datepicker-days">
            {calendarDays.map(({ date, isCurrentMonth }, idx) => {
              const dateStr = formatDateString(date);
              const isSelected = value === dateStr;
              const isToday = dateStr === todayStr;

              // Check if date is before min
              const isBeforeMin = minDate && date < minDate;
              // Check if date is after max
              const isAfterMax = maxDate && date > maxDate;
              const isDisabled = isBeforeMin || isAfterMax;

              return (
                <button
                  key={idx}
                  type="button"
                  disabled={isDisabled}
                  onClick={(e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    if (!isDisabled) handleSelectDate(date);
                  }}
                  className={`custom-datepicker-day ${
                    !isCurrentMonth ? 'custom-datepicker-day--dimmed' : ''
                  } ${isSelected ? 'custom-datepicker-day--selected' : ''} ${
                    isToday ? 'custom-datepicker-day--today' : ''
                  } ${isDisabled ? 'custom-datepicker-day--disabled' : ''}`}
                >
                  {date.getDate()}
                </button>
              );
            })}
          </div>

          {/* Footer Actions */}
          <div className="custom-datepicker-footer">
            <button
              type="button"
              className="custom-datepicker-action-btn"
              onClick={handleClear}
            >
              Clear
            </button>
            <button
              type="button"
              className="custom-datepicker-action-btn custom-datepicker-action-btn--primary"
              onClick={handleToday}
            >
              Today
            </button>
          </div>
        </div>
      )}
    </div>
  );
};

export default DatePicker;
