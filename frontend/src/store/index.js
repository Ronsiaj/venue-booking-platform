import { configureStore, combineReducers } from '@reduxjs/toolkit';
import { persistStore, persistReducer } from 'redux-persist';
import authReducer from './authSlice';

// 1. Vite ESM இம்போர்ட் பிரச்சனையை தவிர்க்க Custom Storage உருவாக்குங்கள்
const customStorage = {
    getItem: (key) => {
        return Promise.resolve(localStorage.getItem(key));
    },
    setItem: (key, value) => {
        return Promise.resolve(localStorage.setItem(key, value));
    },
    removeItem: (key) => {
        return Promise.resolve(localStorage.removeItem(key));
    },
};

const rootReducer = combineReducers({
    auth: authReducer,
});

const persistConfig = {
    key: 'root',
    storage: customStorage,
    whitelist: ['auth'],
};

const persistedReducer = persistReducer(persistConfig, rootReducer);

export const store = configureStore({
    reducer: persistedReducer,
    middleware: (getDefaultMiddleware) =>
        getDefaultMiddleware({
            serializableCheck: false,
        }),
});

export const persistor = persistStore(store);