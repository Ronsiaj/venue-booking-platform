import React from 'react';
import { Navigate, Outlet } from 'react-router-dom';
import { useSelector } from 'react-redux';

export const PublicRoute = () => {
    const { isAuthenticated } = useSelector((state) => state.auth);
    console.log('PublicRoute: Checking authentication status. isAuthenticated:', isAuthenticated);
    // If already logged in, redirect away from public page to dashboard automatically
    return isAuthenticated ? <Navigate to="/dashboard" replace /> : <Outlet />;
    console.log('PublicRoute: User is not authenticated. Rendering public routes.');
};