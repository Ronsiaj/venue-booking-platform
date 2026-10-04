import axios from 'axios';
import { deleteAllCookies, getCookie, reloadWindow } from './Utils';

const ApiCall = axios.create({
    baseURL: import.meta.env.VITE_API_BASE_URL || 'http://localhost/sm_groups/backend',
    timeout: 20000,
    headers: { 'Content-Type': 'application/json' }
});

ApiCall.interceptors.request.use(
    (config) => {
        const token = getCookie('token');
        if (token) {
            config.headers.Authorization = `Bearer ${token}`;
        }

        // Automatically handle FormData requests to let browser set boundary
        if (config.data instanceof FormData) {
            delete config.headers['Content-Type'];
        }

        return config;
    },
    (error) => {
        return Promise.reject(error);
    }
);

ApiCall.interceptors.response.use(
    (response) => {
        return response.data;
    },
    (error) => {
        if (error?.response?.status === 401) {
            deleteAllCookies();
            localStorage.removeItem('persist:root');
            reloadWindow();
        }
        return Promise.reject(error);
    }
);

export default ApiCall;
