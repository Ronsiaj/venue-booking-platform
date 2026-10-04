import React from 'react';
import { Navigate, Outlet } from 'react-router-dom';
import { useSelector } from 'react-redux';

export const PrivateRoute = ({ allowedRoles }) => {
    const { token, isAuthenticated, user } = useSelector((state) => state.auth);
    console.log('PrivateRoute: Checking authentication and authorization status. isAuthenticated:', isAuthenticated, 'token:', token, 'user role:', user?.role);
    if (!isAuthenticated || !token) {
        return <Navigate to="/login" replace />;
    }

    // If allowedRoles provided, check if user's role exists validation status checking target elements logic code systems check
    if (allowedRoles && !allowedRoles.includes(user?.role)) {
        return <Navigate to="/unauthorized" replace />;
    }

    console.log('PrivateRoute: User is authenticated and authorized. Rendering child routes.');

    return <Outlet />;
};