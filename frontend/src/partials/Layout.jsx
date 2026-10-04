import React, { useState } from 'react';
import Sidebar from './Sidebar';
import api from '../services/api';
import { getAuthSession, clearAuthSession } from '../services/Utils';

const DashboardLayout = ({ children, onNavigate, activePath = '/dashboard' }) => {
    const [mobileSidebarOpen, setMobileSidebarOpen] = useState(false);
    const [isLoggingOut, setIsLoggingOut] = useState(false);
    const adminSession = getAuthSession('admin');
    const user = adminSession?.user;
    const isStaff = user?.role === 'staff';

    const handleLogout = async () => {
        if (isLoggingOut) return;
        setIsLoggingOut(true);
        try {
            const token = adminSession?.token;
            if (token) {
                await api.post('/auth/logout.php', {}, { token });
            }
        } catch (err) {
            console.warn('Backend logout warning:', err.message);
        } finally {
            clearAuthSession('admin');
            setIsLoggingOut(false);
            if (onNavigate) {
                onNavigate('/login');
            } else {
                window.location.href = '/login';
            }
        }
    };

    const todayDateStr = new Date().toLocaleDateString('en-US', {
        weekday: 'short',
        month: 'short',
        day: 'numeric',
        year: 'numeric'
    });

    return (
        <div className="dashboard-shell">
            {/* Sidebar Component */}
            <Sidebar
                handleLogout={handleLogout}
                mobileOpen={mobileSidebarOpen}
                onCloseMobile={() => setMobileSidebarOpen(false)}
                activePath={activePath}
                onNavigate={onNavigate}
            />

            {/* Mobile Drawer Backdrop Overlay */}
            {mobileSidebarOpen && (
                <div
                    className="sidebar-mobile-backdrop"
                    onClick={() => setMobileSidebarOpen(false)}
                    aria-hidden="true"
                />
            )}

            {/* Main Content Workspace Panel */}
            <div className="workspace-container">
                {/* Top Workspace Header */}
                <header className="workspace-header">
                    <div className="workspace-header-left">
                        <button
                            onClick={() => setMobileSidebarOpen(true)}
                            className="mobile-nav-toggle-btn"
                            aria-label="Open Navigation Menu"
                            type="button"
                        >
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round">
                                <line x1="3" y1="12" x2="21" y2="12" />
                                <line x1="3" y1="6" x2="21" y2="6" />
                                <line x1="3" y1="18" x2="21" y2="18" />
                            </svg>
                        </button>

                        <div className="workspace-header-title-box">
                            <h1 className="workspace-header-title">
                                {isStaff ? 'Staff Dashboard' : 'Admin Dashboard'}
                            </h1>
                            <span className="workspace-header-subtitle">
                                {isStaff ? 'SM Groups Operations & Guest Management' : 'SM Groups Venue & Room Management'}
                            </span>
                        </div>
                    </div>

                    <div className="workspace-header-actions">
                        {/* Status Badge & Date */}
                        <div className="workspace-header-status-pill">
                            <span className="status-live-dot" />
                            <span className="status-date-text">{todayDateStr}</span>
                        </div>

                        {/* Profile Pill */}
                        <button
                            type="button"
                            className="header-profile-section"
                            onClick={() => onNavigate && onNavigate('/dashboard/profile')}
                            title="View Self Profile"
                            aria-label="View Self Profile"
                        >
                            <div className="header-profile-avatar">
                                {user?.name ? user.name.slice(0, 2).toUpperCase() : (isStaff ? 'ST' : 'AD')}
                            </div>
                            <div className="header-profile-info">
                                <span className="header-profile-name">{user?.name || (isStaff ? 'Staff Member' : 'Administrator')}</span>
                                <span className="header-profile-role-badge">
                                    <span className="header-profile-role">{user?.role ? user.role.toUpperCase() : (isStaff ? 'STAFF' : 'ADMIN')}</span>
                                    <span className="header-profile-role-icon">
                                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round">
                                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
                                            <circle cx="12" cy="7" r="4" />
                                        </svg>
                                    </span>
                                </span>
                            </div>
                        </button>

                        {/* Quick Logout Button */}
                        <button
                            type="button"
                            className="header-logout-btn"
                            onClick={handleLogout}
                            title="Log Out"
                        >
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
                                <polyline points="16 17 21 12 16 7" />
                                <line x1="21" y1="12" x2="9" y2="12" />
                            </svg>
                            <span className="header-logout-text">Logout</span>
                        </button>
                    </div>
                </header>

                {/* Workspace Body */}
                <main className="workspace-content">
                    {children}
                </main>
            </div>
        </div>
    );
};

export default DashboardLayout;