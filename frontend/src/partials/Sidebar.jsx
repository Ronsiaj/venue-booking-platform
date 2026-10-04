import React, { useState } from 'react';
import { getAuthSession } from '../services/Utils';

const Sidebar = ({ handleLogout, mobileOpen, onCloseMobile, activePath = '/dashboard', onNavigate }) => {
    const adminSession = getAuthSession('admin');
    const user = adminSession?.user;
    const isStaff = user?.role === 'staff';
    const [openMenus, setOpenMenus] = useState({});

    // Base navigation items with their respective paths & icons
    const allNavItems = [
        {
            name: 'Dashboard',
            path: '/dashboard',
            roles: ['admin', 'staff', 'super_admin'],
            icon: (
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                    <rect x="3" y="3" width="7" height="9" rx="1" />
                    <rect x="14" y="3" width="7" height="5" rx="1" />
                    <rect x="14" y="12" width="7" height="9" rx="1" />
                    <rect x="3" y="16" width="7" height="5" rx="1" />
                </svg>
            )
        },
        {
            name: 'Bookings',
            path: '/dashboard/bookings',
            roles: ['admin', 'staff', 'super_admin'],
            icon: (
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2" />
                    <line x1="16" y1="2" x2="16" y2="6" />
                    <line x1="8" y1="2" x2="8" y2="6" />
                    <line x1="3" y1="10" x2="21" y2="10" />
                </svg>
            )
        },
        {
            name: isStaff ? 'My Leads' : 'Global Leads',
            path: '/dashboard/leads',
            roles: ['admin', 'staff', 'super_admin'],
            icon: (
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                    <circle cx="9" cy="7" r="4" />
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87" />
                    <path d="M16 3.13a4 4 0 0 1 0 7.75" />
                </svg>
            )
        },
        {
            name: 'Rooms',
            path: '/dashboard/rooms',
            roles: ['admin', 'staff', 'super_admin'],
            icon: (
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                    <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" />
                    <polyline points="9 22 9 12 15 12 15 22" />
                </svg>
            )
        },
        {
            name: 'Room Types',
            path: '/dashboard/room-types',
            roles: ['admin', 'staff', 'super_admin'],
            icon: (
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                    <rect x="3" y="3" width="18" height="18" rx="2" />
                    <path d="M9 3v18" />
                    <path d="M14 9h4" />
                    <path d="M14 15h4" />
                </svg>
            )
        },
        {
            name: 'Amenities',
            path: '/dashboard/amenities',
            roles: ['admin', 'staff', 'super_admin'],
            icon: (
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                    <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" />
                </svg>
            )
        },
        {
            name: 'Halls',
            path: '/dashboard/halls',
            roles: ['admin', 'staff', 'super_admin'],
            icon: (
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                    <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" />
                    <polyline points="9 22 9 12 15 12 15 22" />
                </svg>
            )
        },
        {
            name: 'Hall Types',
            path: '/dashboard/hall-types',
            roles: ['admin', 'staff', 'super_admin'],
            icon: (
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                    <rect x="3" y="3" width="7" height="9" rx="1" />
                    <rect x="14" y="3" width="7" height="5" rx="1" />
                    <rect x="14" y="12" width="7" height="9" rx="1" />
                    <rect x="3" y="16" width="7" height="5" rx="1" />
                </svg>
            )
        },
        {
            name: 'Hall Facilities',
            path: '/dashboard/hall-facilities',
            roles: ['admin', 'staff', 'super_admin'],
            icon: (
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                    <path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z" />
                </svg>
            )
        },
        {
            name: 'Food',
            path: '/dashboard/food',
            roles: ['admin', 'staff', 'super_admin'],
            icon: (
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                    <path d="M18 8h1a4 4 0 0 1 0 8h-1" />
                    <path d="M2 8h16v9a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4V8z" />
                    <line x1="6" y1="2" x2="6" y2="4" />
                    <line x1="10" y1="2" x2="10" y2="4" />
                    <line x1="14" y1="2" x2="14" y2="4" />
                </svg>
            ),
            submenus: [
                {
                    name: 'Food Menu Dishes',
                    path: '/dashboard/food?tab=dishes',
                    tab: 'dishes',
                    roles: ['admin', 'staff', 'super_admin'],
                },
                {
                    name: 'Room Food Packages',
                    path: '/dashboard/food?tab=packages',
                    tab: 'packages',
                    roles: ['admin', 'staff', 'super_admin'],
                }
            ]
        },
        {
            name: 'Dining',
            path: '/dashboard/dining',
            roles: ['admin', 'staff', 'super_admin'],
            icon: (
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                    <path d="M3 2v7c0 1.1.9 2 2 2h4a2 2 0 0 0 2-2V2" />
                    <path d="M7 2v20" />
                    <path d="M21 15V2v0a5 5 0 0 0-5 5v6c0 1.1.9 2 2 2h3Zm0 0v7" />
                </svg>
            ),
            submenus: [
                {
                    name: 'Dining Packages',
                    path: '/dashboard/dining?tab=packages',
                    tab: 'packages',
                    roles: ['admin', 'staff', 'super_admin'],
                },
                {
                    name: 'Dining Inquiries',
                    path: '/dashboard/dining?tab=leads',
                    tab: 'leads',
                    roles: ['admin', 'staff', 'super_admin'],
                }
            ]
        },
        {
            name: 'Catering',
            path: '/dashboard/catering',
            roles: ['admin', 'staff', 'super_admin'],
            icon: (
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                    <path d="M6 13.87A4 4 0 0 1 7.41 6a5.11 5.11 0 0 1 1.05-1.54 5 5 0 0 1 7.08 0A5.11 5.11 0 0 1 16.59 6 4 4 0 0 1 18 13.87V21H6Z" />
                    <line x1="6" y1="17" x2="18" y2="17" />
                </svg>
            ),
            submenus: [
                {
                    name: 'Catering Packages',
                    path: '/dashboard/catering?tab=packages',
                    tab: 'packages',
                    roles: ['admin', 'staff', 'super_admin'],
                },
                {
                    name: 'Catering Inquiries / Leads',
                    path: '/dashboard/catering?tab=leads',
                    tab: 'leads',
                    roles: ['admin', 'staff', 'super_admin'],
                }
            ]
        },
        {
            name: 'Payments',
            path: '/dashboard/payments',
            roles: ['admin', 'staff', 'super_admin'],
            icon: (
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                    <rect x="1" y="4" width="22" height="16" rx="2" ry="2" />
                    <line x1="1" y1="10" x2="23" y2="10" />
                </svg>
            )
        },
        {
            name: 'Reports',
            path: '/dashboard/reports',
            roles: ['admin', 'staff', 'super_admin'],
            icon: (
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                    <line x1="18" y1="20" x2="18" y2="10" />
                    <line x1="12" y1="20" x2="12" y2="4" />
                    <line x1="6" y1="20" x2="6" y2="14" />
                    <line x1="2" y1="20" x2="22" y2="20" />
                </svg>
            )
        },
        {
            name: 'Notifications',
            path: '/dashboard/notifications',
            roles: ['admin', 'staff', 'super_admin'],
            icon: (
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                    <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9" />
                    <path d="M13.73 21a2 2 0 0 1-3.46 0" />
                </svg>
            )
        },
        {
            name: 'Staff',
            path: '/dashboard/staff',
            roles: ['admin', 'super_admin'],
            icon: (
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                    <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
                    <circle cx="9" cy="7" r="4" />
                    <path d="M22 21v-2a4 4 0 0 0-3-3.87" />
                    <path d="M16 3.13a4 4 0 0 1 0 7.75" />
                </svg>
            )
        }
    ];

    // Filter items based on current role
    const currentRole = user?.role || (isStaff ? 'staff' : 'admin');
    const navItems = allNavItems
        .filter((item) => item.roles.includes(currentRole))
        .map((item) => {
            if (!item.submenus) return item;
            return {
                ...item,
                submenus: item.submenus.filter((sub) => sub.roles.includes(currentRole))
            };
        });

    const isMenuExpanded = (item) => {
        const itemBase = item.path.split('?')[0];
        const currentBase = activePath.split('?')[0];
        const isCurrentActive = currentBase === itemBase;
        if (openMenus[item.name] !== undefined) {
            return openMenus[item.name];
        }
        return isCurrentActive;
    };

    const handleParentMenuClick = (item, e) => {
        e.preventDefault();
        const itemBase = item.path.split('?')[0];
        const currentBase = activePath.split('?')[0];
        const visibleSubs = item.submenus || [];

        if (currentBase !== itemBase) {
            setOpenMenus((prev) => ({ ...prev, [item.name]: true }));
            const targetPath = visibleSubs.length > 0 ? visibleSubs[0].path : item.path;
            if (onCloseMobile) onCloseMobile();
            if (onNavigate) onNavigate(targetPath);
        } else {
            setOpenMenus((prev) => ({ ...prev, [item.name]: !isMenuExpanded(item) }));
        }
    };

    const handleChevronToggle = (item, e) => {
        e.preventDefault();
        e.stopPropagation();
        setOpenMenus((prev) => ({ ...prev, [item.name]: !isMenuExpanded(item) }));
    };

    const handleNavClick = (path, e) => {
        e.preventDefault();
        if (onCloseMobile) onCloseMobile();
        if (onNavigate) onNavigate(path);
    };

    const isSubActive = (sub, parentPath) => {
        const currentBase = activePath.split('?')[0];
        if (currentBase !== parentPath) return false;
        const params = new URLSearchParams(activePath.includes('?') ? activePath.split('?')[1] : '');
        const currentTab = params.get('tab');
        if (currentTab) {
            return currentTab === sub.tab;
        }
        // Default tab when no query parameter is specified
        return sub.tab === 'dishes' || (parentPath !== '/dashboard/food' && sub.tab === 'packages');
    };

    return (
        <aside className={`dashboard-sidebar ${mobileOpen ? 'mobile-open' : ''}`}>
            {/* Top Brand Logo Section */}
            <div className="sidebar-logo-wrap">
                <div className="sidebar-brand-box">
                    <div className="sidebar-brand-avatar">
                        <span>SM</span>
                    </div>
                    <div className="sidebar-brand-text-wrap">
                        <span className="sidebar-brand-title">SM Groups</span>
                        <span className="sidebar-brand-badge">
                            {isStaff ? 'STAFF CONSOLE' : 'ADMIN CONSOLE'}
                        </span>
                    </div>
                </div>

                <button
                    onClick={onCloseMobile}
                    className="mobile-close-sidebar-btn"
                    aria-label="Close Navigation Menu"
                    type="button"
                >
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                        <line x1="18" y1="6" x2="6" y2="18" />
                        <line x1="6" y1="6" x2="18" y2="18" />
                    </svg>
                </button>
            </div>

            {/* Navigation List (Role-filtered menu items for Dashboard) */}
            <div className="sidebar-nav-scroll-area">
                <div className="sidebar-nav-group">
                    <span className="sidebar-group-title">MAIN MENU</span>
                    <nav className="sidebar-nav-list">
                        {navItems.map((item) => {
                            const hasSubmenus = item.submenus && item.submenus.length > 0;
                            const itemBase = item.path.split('?')[0];
                            const currentBase = activePath.split('?')[0];
                            const isParentActive =
                                currentBase === itemBase ||
                                (item.path === '/dashboard/leads' && currentBase === '/dashboard/global-leads') ||
                                (item.path === '/dashboard/payments' &&
                                    (currentBase === '/dashboard/payment' ||
                                        currentBase === '/payments' ||
                                        currentBase === '/payment' ||
                                        currentBase.startsWith('/dashboard/payments'))) ||
                                (item.path === '/dashboard/reports' &&
                                    (currentBase === '/dashboard/report' ||
                                        currentBase === '/reports' ||
                                        currentBase === '/report' ||
                                        currentBase.startsWith('/dashboard/reports')));

                            if (hasSubmenus) {
                                const expanded = isMenuExpanded(item);
                                return (
                                    <div key={item.name} className="sidebar-nav-item-group">
                                        <button
                                            type="button"
                                            onClick={(e) => handleParentMenuClick(item, e)}
                                            className={`sidebar-submenu-toggle ${isParentActive ? 'parent-active' : ''}`}
                                            aria-expanded={expanded}
                                        >
                                            <div className="sidebar-submenu-toggle-left">
                                                <span className="sidebar-nav-icon">{item.icon}</span>
                                                <span className="sidebar-nav-label">{item.name}</span>
                                            </div>
                                            <span
                                                className={`sidebar-submenu-arrow ${expanded ? 'expanded' : ''}`}
                                                onClick={(e) => handleChevronToggle(item, e)}
                                                title={expanded ? 'Collapse' : 'Expand'}
                                            >
                                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round">
                                                    <polyline points="6 9 12 15 18 9" />
                                                </svg>
                                            </span>
                                        </button>

                                        {expanded && (
                                            <div className="sidebar-submenu-list">
                                                {item.submenus.map((sub) => {
                                                    const subActive = isSubActive(sub, item.path);
                                                    return (
                                                        <a
                                                            key={sub.name}
                                                            href={sub.path}
                                                            onClick={(e) => handleNavClick(sub.path, e)}
                                                            className={`sidebar-submenu-item ${subActive ? 'active' : ''}`}
                                                        >
                                                            <span className="sidebar-submenu-bullet" />
                                                            <span className="sidebar-submenu-label">{sub.name}</span>
                                                            {subActive && <span className="sidebar-active-indicator" />}
                                                        </a>
                                                    );
                                                })}
                                            </div>
                                        )}
                                    </div>
                                );
                            }

                            return (
                                <a
                                    key={item.name}
                                    href={item.path}
                                    onClick={(e) => handleNavClick(item.path, e)}
                                    className={`sidebar-nav-item ${isParentActive ? 'active' : ''}`}
                                >
                                    <span className="sidebar-nav-icon">{item.icon}</span>
                                    <span className="sidebar-nav-label">{item.name}</span>
                                    {isParentActive && <span className="sidebar-active-indicator" />}
                                </a>
                            );
                        })}
                    </nav>
                </div>
            </div>

            {/* Footer Section: User Profile & Logout */}
            <div className="sidebar-footer-section">
                <div className="sidebar-profile-card">
                    <div className="sidebar-profile-avatar">
                        {user?.name ? user.name.slice(0, 2).toUpperCase() : (isStaff ? 'ST' : 'AD')}
                    </div>
                    <div className="sidebar-profile-info">
                        <span className="sidebar-profile-name">{user?.name || (isStaff ? 'Staff Member' : 'Administrator')}</span>
                        <span className="sidebar-profile-role">{user?.role ? user.role.toUpperCase() : (isStaff ? 'STAFF' : 'ADMIN')}</span>
                    </div>
                </div>

                <button
                    type="button"
                    onClick={handleLogout}
                    className="sidebar-logout-btn"
                >
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
                        <polyline points="16 17 21 12 16 7" />
                        <line x1="21" y1="12" x2="9" y2="12" />
                    </svg>
                    <span>Log Out</span>
                </button>
            </div>
        </aside>
    );
};

export default Sidebar;
