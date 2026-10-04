import { createSlice } from '@reduxjs/toolkit';

const initialState = {
    token: "",
    user: null,
    isAuthenticated: false,
};

const authSlice = createSlice({
    name: 'auth',
    initialState,
    reducers: {
        loginSuccess: (state, action) => {
            state.token = action.payload.token;
            state.user = action.payload.user;
            state.isAuthenticated = true;
        },
        logoutSuccess: (state) => {
            state.token = "";
            state.user = null;
            state.isAuthenticated = false;
        },
    },
});

export const { loginSuccess, logoutSuccess } = authSlice.actions;
export default authSlice.reducer;