import React from 'react';

/**
 * Reusable Admin Pagination Component
 * Consistent pagination control across all SM Groups admin listing modules.
 */
const Pagination = ({
    currentPage = 1,
    totalItems = 0,
    totalRecords = 0,
    totalPages: explicitTotalPages,
    pageSize = 10,
    onPageChange,
    onPageSizeChange,
    pageSizeOptions = [5, 10, 20, 50],
}) => {
    const effectiveTotal = Number(totalRecords || totalItems || 0);
    const effectivePageSize = Number(pageSize || 10);
    const calculatedPages = Math.ceil(effectiveTotal / effectivePageSize) || 1;
    const totalPages = explicitTotalPages || calculatedPages;

    if (effectiveTotal <= 0) return null;

    const startItem = (currentPage - 1) * effectivePageSize + 1;
    const endItem = Math.min(currentPage * effectivePageSize, effectiveTotal);

    /**
     * Generate page items with smart ellipsis calculation
     */
    const getPageNumbers = () => {
        const pages = [];
        const maxVisible = 5;

        if (totalPages <= maxVisible) {
            for (let i = 1; i <= totalPages; i++) {
                pages.push(i);
            }
        } else {
            // Always show page 1
            pages.push(1);

            if (currentPage > 3) {
                pages.push('dots-left');
            }

            const start = Math.max(2, currentPage - 1);
            const end = Math.min(totalPages - 1, currentPage + 1);

            for (let i = start; i <= end; i++) {
                if (!pages.includes(i)) {
                    pages.push(i);
                }
            }

            if (currentPage < totalPages - 2) {
                pages.push('dots-right');
            }

            // Always show last page
            if (!pages.includes(totalPages)) {
                pages.push(totalPages);
            }
        }

        return pages;
    };

    const handlePageClick = (page) => {
        if (page >= 1 && page <= totalPages && page !== currentPage) {
            onPageChange(page);
        }
    };

    return (
        <div className="admin-pagination-container">
            {/* Left: Summary Info */}
            <div className="admin-pagination-info">
                Showing <span className="admin-pagination-highlight">{startItem}</span> to{' '}
                <span className="admin-pagination-highlight">{endItem}</span> of{' '}
                <span className="admin-pagination-highlight">{effectiveTotal}</span> entries
            </div>

            {/* Middle: Rows per page selector */}
            {onPageSizeChange && (
                <div className="admin-pagination-size-wrap">
                    <label htmlFor="admin-page-size-select" className="admin-pagination-size-label">
                        Rows per page:
                    </label>
                    <select
                        id="admin-page-size-select"
                        className="admin-pagination-size-select"
                        value={pageSize}
                        onChange={(e) => {
                            onPageSizeChange(Number(e.target.value));
                            onPageChange(1);
                        }}
                    >
                        {pageSizeOptions.map((opt) => (
                            <option key={opt} value={opt}>
                                {opt}
                            </option>
                        ))}
                    </select>
                </div>
            )}

            {/* Right: Page Buttons */}
            <div className="admin-pagination-nav">
                {/* First Page Button */}
                <button
                    type="button"
                    className="admin-pagination-btn admin-pagination-btn--nav"
                    onClick={() => handlePageClick(1)}
                    disabled={currentPage === 1}
                    title="First Page"
                    aria-label="First Page"
                >
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round">
                        <polyline points="11 17 6 12 11 7" />
                        <polyline points="18 17 13 12 18 7" />
                    </svg>
                </button>

                {/* Previous Page Button */}
                <button
                    type="button"
                    className="admin-pagination-btn admin-pagination-btn--nav"
                    onClick={() => handlePageClick(currentPage - 1)}
                    disabled={currentPage === 1}
                    title="Previous Page"
                    aria-label="Previous Page"
                >
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round">
                        <polyline points="15 18 9 12 15 6" />
                    </svg>
                </button>

                {/* Numbered Page Buttons */}
                <div className="admin-pagination-pages">
                    {getPageNumbers().map((item, idx) => {
                        if (item === 'dots-left' || item === 'dots-right') {
                            return (
                                <span key={`dots-${idx}`} className="admin-pagination-dots">
                                    •••
                                </span>
                            );
                        }

                        const isCurrent = item === currentPage;
                        return (
                            <button
                                key={item}
                                type="button"
                                className={`admin-pagination-btn admin-pagination-btn--page ${isCurrent ? 'active' : ''}`}
                                onClick={() => handlePageClick(item)}
                                aria-current={isCurrent ? 'page' : undefined}
                            >
                                {item}
                            </button>
                        );
                    })}
                </div>

                {/* Next Page Button */}
                <button
                    type="button"
                    className="admin-pagination-btn admin-pagination-btn--nav"
                    onClick={() => handlePageClick(currentPage + 1)}
                    disabled={currentPage === totalPages}
                    title="Next Page"
                    aria-label="Next Page"
                >
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round">
                        <polyline points="9 18 15 12 9 6" />
                    </svg>
                </button>

                {/* Last Page Button */}
                <button
                    type="button"
                    className="admin-pagination-btn admin-pagination-btn--nav"
                    onClick={() => handlePageClick(totalPages)}
                    disabled={currentPage === totalPages}
                    title="Last Page"
                    aria-label="Last Page"
                >
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round">
                        <polyline points="13 17 18 12 13 7" />
                        <polyline points="6 17 11 12 6 7" />
                    </svg>
                </button>
            </div>
        </div>
    );
};

export default Pagination;
