import { createSlice } from '@reduxjs/toolkit';

const initialState = {
    loading: false,
};

const loaderSlice = createSlice({
    name: 'loader',
    initialState,
    reducers: {
        loadingTrue: (state) => {
            state.loading = true;
        },
        loadingFalse: (state) => {
            state.loading = false;
        },
    },
});

export const { loadingTrue, loadingFalse } = loaderSlice.actions;
export default loaderSlice.reducer;
