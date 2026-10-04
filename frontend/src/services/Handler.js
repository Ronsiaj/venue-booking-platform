import { loadingFalse } from "../Reducer/loaderSlice";
import ApiCall from "./ApiCall";
import { reloadWindow } from "./Utils";

/**
 * Global API Handler Wrapper
 * 
 * @param {string} method - 'GET' | 'POST' | 'PUT' | 'DELETE' | 'PATCH'
 * @param {string} url - API Endpoint
 * @param {object|FormData} data - Payload data (Accepts both JSON objects and FormData)
 * @param {object} headers - Custom headers
 */
const Handler = async ({ method, url, data = {}, headers = {} }) => {
    let responseData = { success: false, data: {}, status: null };

    // Detect if data is FormData and setup headers automatically if needed
    const requestHeaders = { ...headers };
    if (data instanceof FormData) {
        // Let Axios and browser handle setting the multipart/form-data boundary
        delete requestHeaders['Content-Type'];
    }

    try {
        const response = await ApiCall({
            url,
            method,
            data,
            headers: requestHeaders
        });

        // If response is zip/binary or success flag is true
        if (requestHeaders['Content-Type'] === 'application/zip' || response.success) {
            responseData.success = true;
            responseData.data = response;
            responseData.status = 200;
        } else {
            responseData.success = false;
            responseData.data = response;
            responseData.status = response.status || 200;
        }
    } catch (error) {
        const errorStatus = error?.response?.status;
        responseData.success = false;
        responseData.status = errorStatus;
        responseData.data = error?.response?.data || { message: "Retry after sometime" };

        try {
            loadingFalse();
        } catch (e) {
            console.error("loaderSlice action dispatch failed:", e);
        }
    } finally {
        const { success, data, status } = responseData;

        if (status === 401 || status === 403 || status === 504) {
            console.error("Auth Error detected in Handler:", status);
            reloadWindow();
            return { success: false, message: "Session expired. Redirecting..." };
        }

        if (success) {
            // 🔥 Don't override 'summary'. Store everything separately.
            return {
                success: true,
                message: data?.msg || data?.message,
                token: data?.token,

                // Data mapping: Array nested-ah irundha edukum, illana direct-ah edukum
                data: data?.data || data,

                // Stats & Meta
                summary: data?.summary,
                charts: data?.charts,
                payment_split: data?.payment_split,
                filters: data?.filters,

                // 🔥 Pagination: Works for both Nested and Flat types
                page: data?.pagination?.current_page || data?.page || 1,
                total: data?.pagination?.total_records || data?.total || 0,
                limit: data?.pagination?.per_page || data?.limit || 10,
                total_pages: data?.pagination?.total_pages || data?.total_pages ||
                    Math.ceil((data?.pagination?.total_records || data?.total || 0) /
                        (data?.pagination?.per_page || data?.limit || 10))
            };
        } else {
            return {
                success: false,
                message: data?.msg || data?.message || "Something went wrong",
                errors: data?.errors
            };
        }
    }
};

export default Handler;
