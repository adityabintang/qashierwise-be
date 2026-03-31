<style>
    :root {
        --glass-bg: rgba(255, 255, 255, 0.7);
        --glass-border: rgba(139, 92, 246, 0.2);
        --glass-shadow: 0 8px 32px 0 rgba(139, 92, 246, 0.1);
        --primary-purple: #8b5cf6;
        --primary-purple-light: #a78bfa;
        --primary-purple-dark: #7c3aed;
    }

    /* Background - Subtle gradient */
    body {
        background: linear-gradient(135deg, #f8f9fa 0%, #f3f4f6 100%) !important;
        background-attachment: fixed !important;
    }

    /* Sidebar - Clean white with glass effect */
    aside[class*="fi-sidebar"] {
        background: rgba(255, 255, 255, 0.95) !important;
        backdrop-filter: blur(10px) saturate(180%) !important;
        -webkit-backdrop-filter: blur(10px) saturate(180%) !important;
        border-right: 1px solid rgba(139, 92, 246, 0.1) !important;
        box-shadow: 2px 0 10px rgba(139, 92, 246, 0.05) !important;
    }

    /* Sidebar active item - Purple accent */
    aside[class*="fi-sidebar"] a[class*="fi-active"],
    aside[class*="fi-sidebar"] button[class*="fi-active"] {
        background: linear-gradient(135deg, rgba(139, 92, 246, 0.1) 0%, rgba(167, 139, 250, 0.1) 100%) !important;
        border-left: 3px solid var(--primary-purple) !important;
        color: var(--primary-purple-dark) !important;
    }

    /* Sidebar hover */
    aside[class*="fi-sidebar"] a:hover,
    aside[class*="fi-sidebar"] button:hover {
        background: rgba(139, 92, 246, 0.05) !important;
    }

    /* Topbar - Glass effect */
    header[class*="fi-topbar"] {
        background: rgba(255, 255, 255, 0.9) !important;
        backdrop-filter: blur(20px) saturate(180%) !important;
        -webkit-backdrop-filter: blur(20px) saturate(180%) !important;
        border-bottom: 1px solid rgba(139, 92, 246, 0.1) !important;
        box-shadow: 0 2px 10px rgba(139, 92, 246, 0.05) !important;
    }

    /* Cards & Sections - Clean white with subtle glass */
    section[class*="fi-section"],
    div[class*="fi-card"] {
        background: rgba(255, 255, 255, 0.95) !important;
        backdrop-filter: blur(10px) !important;
        -webkit-backdrop-filter: blur(10px) !important;
        border: 1px solid rgba(139, 92, 246, 0.1) !important;
        border-radius: 1rem !important;
        box-shadow: 0 4px 20px rgba(139, 92, 246, 0.08) !important;
        transition: all 0.3s ease !important;
    }

    section[class*="fi-section"]:hover,
    div[class*="fi-card"]:hover {
        box-shadow: 0 8px 30px rgba(139, 92, 246, 0.12) !important;
        transform: translateY(-2px) !important;
    }

    /* Tables - Clean white */
    div[class*="fi-ta-table"] {
        background: rgba(255, 255, 255, 0.98) !important;
        backdrop-filter: blur(10px) !important;
        border-radius: 1rem !important;
        overflow: hidden !important;
        border: 1px solid rgba(139, 92, 246, 0.1) !important;
    }

    /* Table header */
    thead[class*="fi-ta-header"] {
        background: linear-gradient(135deg, rgba(139, 92, 246, 0.05) 0%, rgba(167, 139, 250, 0.05) 100%) !important;
    }

    /* Table Rows Hover */
    tr[class*="fi-ta-row"]:hover {
        background: rgba(139, 92, 246, 0.03) !important;
    }

    /* Buttons Primary - Purple gradient */
    button[class*="fi-btn-primary"],
    a[class*="fi-btn-primary"] {
        background: linear-gradient(135deg, var(--primary-purple) 0%, var(--primary-purple-light) 100%) !important;
        border: none !important;
        box-shadow: 0 4px 15px rgba(139, 92, 246, 0.3) !important;
        transition: all 0.3s ease !important;
    }

    button[class*="fi-btn-primary"]:hover,
    a[class*="fi-btn-primary"]:hover {
        transform: translateY(-2px) !important;
        box-shadow: 0 6px 20px rgba(139, 92, 246, 0.4) !important;
    }

    /* Buttons Secondary - Glass effect */
    button[class*="fi-btn-secondary"],
    a[class*="fi-btn-secondary"] {
        background: rgba(255, 255, 255, 0.9) !important;
        backdrop-filter: blur(10px) !important;
        border: 1px solid rgba(139, 92, 246, 0.2) !important;
        color: var(--primary-purple) !important;
    }

    button[class*="fi-btn-secondary"]:hover,
    a[class*="fi-btn-secondary"]:hover {
        background: rgba(139, 92, 246, 0.1) !important;
    }

    /* Inputs - Clean white with purple accent */
    input[class*="fi-input"],
    select[class*="fi-select"],
    textarea[class*="fi-textarea"] {
        background: rgba(255, 255, 255, 0.95) !important;
        backdrop-filter: blur(5px) !important;
        border: 1px solid rgba(139, 92, 246, 0.2) !important;
        color: #1f2937 !important;
        transition: all 0.3s ease !important;
    }

    input[class*="fi-input"]:focus,
    select[class*="fi-select"]:focus,
    textarea[class*="fi-textarea"]:focus {
        border-color: var(--primary-purple) !important;
        box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1) !important;
    }

    input[class*="fi-input"]::placeholder,
    textarea[class*="fi-textarea"]::placeholder {
        color: rgba(107, 114, 128, 0.6) !important;
    }

    /* Modal - Glass effect */
    div[class*="fi-modal-window"] {
        background: rgba(255, 255, 255, 0.95) !important;
        backdrop-filter: blur(30px) saturate(180%) !important;
        border: 1px solid rgba(139, 92, 246, 0.2) !important;
        border-radius: 1.25rem !important;
        box-shadow: 0 20px 60px rgba(139, 92, 246, 0.2) !important;
    }

    /* Stats/Widgets - Glass cards with purple accent */
    div[class*="fi-wi-stats-overview-stat"] {
        background: rgba(255, 255, 255, 0.95) !important;
        backdrop-filter: blur(10px) !important;
        border: 1px solid rgba(139, 92, 246, 0.15) !important;
        border-radius: 1rem !important;
        box-shadow: 0 4px 20px rgba(139, 92, 246, 0.08) !important;
        transition: all 0.3s ease !important;
    }

    div[class*="fi-wi-stats-overview-stat"]:hover {
        transform: translateY(-4px) !important;
        box-shadow: 0 8px 30px rgba(139, 92, 246, 0.15) !important;
        border-color: rgba(139, 92, 246, 0.3) !important;
    }

    /* Badges - Purple accent */
    span[class*="fi-badge"] {
        background: rgba(139, 92, 246, 0.1) !important;
        color: var(--primary-purple-dark) !important;
        border: 1px solid rgba(139, 92, 246, 0.2) !important;
    }

    /* Dropdown Menu - Glass effect */
    div[class*="fi-dropdown-list"] {
        background: rgba(255, 255, 255, 0.98) !important;
        backdrop-filter: blur(20px) saturate(180%) !important;
        border: 1px solid rgba(139, 92, 246, 0.15) !important;
        border-radius: 0.75rem !important;
        box-shadow: 0 10px 40px rgba(139, 92, 246, 0.15) !important;
    }

    div[class*="fi-dropdown-list-item"]:hover {
        background: rgba(139, 92, 246, 0.05) !important;
    }

    /* Tabs */
    button[class*="fi-tabs-item"][aria-selected="true"] {
        background: linear-gradient(135deg, rgba(139, 92, 246, 0.1) 0%, rgba(167, 139, 250, 0.1) 100%) !important;
        color: var(--primary-purple-dark) !important;
        border-bottom: 2px solid var(--primary-purple) !important;
    }

    /* Pagination - Purple accent */
    button[class*="fi-pagination-item"][aria-current="page"] {
        background: linear-gradient(135deg, var(--primary-purple) 0%, var(--primary-purple-light) 100%) !important;
        color: #fff !important;
    }

    /* Text Colors - Keep dark for readability */
    h1, h2, h3, h4, h5, h6 {
        color: #1f2937 !important;
    }

    span[class*="fi-header-heading"],
    span[class*="fi-section-header-heading"] {
        color: #1f2937 !important;
        font-weight: 600 !important;
    }

    p, span, label {
        color: #4b5563 !important;
    }

    /* Scrollbar - Purple accent */
    ::-webkit-scrollbar {
        width: 10px;
        height: 10px;
    }

    ::-webkit-scrollbar-track {
        background: rgba(139, 92, 246, 0.05);
        border-radius: 10px;
    }

    ::-webkit-scrollbar-thumb {
        background: linear-gradient(135deg, rgba(139, 92, 246, 0.3) 0%, rgba(167, 139, 250, 0.3) 100%);
        border-radius: 10px;
        transition: all 0.3s ease;
    }

    ::-webkit-scrollbar-thumb:hover {
        background: linear-gradient(135deg, rgba(139, 92, 246, 0.5) 0%, rgba(167, 139, 250, 0.5) 100%);
    }

    /* Notification - Glass effect */
    .fi-no-notification {
        background: rgba(255, 255, 255, 0.95) !important;
        backdrop-filter: blur(20px) !important;
        border: 1px solid rgba(139, 92, 246, 0.2) !important;
        box-shadow: 0 10px 40px rgba(139, 92, 246, 0.15) !important;
    }

    /* Search input - Glass effect */
    input[class*="fi-global-search-input"] {
        background: rgba(255, 255, 255, 0.9) !important;
        backdrop-filter: blur(10px) !important;
        border: 1px solid rgba(139, 92, 246, 0.2) !important;
    }

    input[class*="fi-global-search-input"]:focus {
        border-color: var(--primary-purple) !important;
        box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1) !important;
    }

    /* Toggle - Purple accent */
    button[class*="fi-toggle"][aria-checked="true"] {
        background: linear-gradient(135deg, var(--primary-purple) 0%, var(--primary-purple-light) 100%) !important;
    }

    /* Checkbox - Purple accent */
    input[type="checkbox"]:checked {
        background: linear-gradient(135deg, var(--primary-purple) 0%, var(--primary-purple-light) 100%) !important;
        border-color: var(--primary-purple) !important;
    }

    /* File upload - Glass effect */
    div[class*="fi-fo-file-upload"] {
        background: rgba(255, 255, 255, 0.9) !important;
        backdrop-filter: blur(10px) !important;
        border: 2px dashed rgba(139, 92, 246, 0.3) !important;
        transition: all 0.3s ease !important;
    }

    div[class*="fi-fo-file-upload"]:hover {
        background: rgba(139, 92, 246, 0.05) !important;
        border-color: var(--primary-purple) !important;
    }

    /* Breadcrumbs */
    nav[class*="fi-breadcrumbs"] {
        background: rgba(255, 255, 255, 0.8) !important;
        backdrop-filter: blur(10px) !important;
        border-radius: 0.5rem !important;
        padding: 0.5rem 1rem !important;
    }

    /* Loading spinner - Purple */
    svg[class*="fi-spinner"] {
        color: var(--primary-purple) !important;
    }

    /* Accent decorations */
    div[class*="fi-section-header"]::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 4px;
        height: 100%;
        background: linear-gradient(135deg, var(--primary-purple) 0%, var(--primary-purple-light) 100%);
        border-radius: 2px;
    }

    /* ===== DARK MODE SUPPORT ===== */
    .dark body {
        background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%) !important;
    }

    /* Dark mode - Sidebar */
    .dark aside[class*="fi-sidebar"] {
        background: rgba(30, 41, 59, 0.95) !important;
        border-right: 1px solid rgba(139, 92, 246, 0.2) !important;
    }

    /* Dark mode - Topbar */
    .dark header[class*="fi-topbar"] {
        background: rgba(30, 41, 59, 0.9) !important;
        border-bottom: 1px solid rgba(139, 92, 246, 0.2) !important;
    }

    /* Dark mode - Cards & Sections */
    .dark section[class*="fi-section"],
    .dark div[class*="fi-card"] {
        background: rgba(30, 41, 59, 0.95) !important;
        border: 1px solid rgba(139, 92, 246, 0.2) !important;
    }

    /* Dark mode - Tables */
    .dark div[class*="fi-ta-table"] {
        background: rgba(30, 41, 59, 0.98) !important;
        border: 1px solid rgba(139, 92, 246, 0.2) !important;
    }

    /* Dark mode - Table header */
    .dark thead[class*="fi-ta-header"] {
        background: rgba(139, 92, 246, 0.1) !important;
    }

    /* Dark mode - Inputs */
    .dark input[class*="fi-input"],
    .dark select[class*="fi-select"],
    .dark textarea[class*="fi-textarea"] {
        background: rgba(30, 41, 59, 0.95) !important;
        border: 1px solid rgba(139, 92, 246, 0.3) !important;
        color: #e2e8f0 !important;
    }

    .dark input[class*="fi-input"]::placeholder,
    .dark textarea[class*="fi-textarea"]::placeholder {
        color: rgba(226, 232, 240, 0.5) !important;
    }

    /* Dark mode - Search input */
    .dark input[class*="fi-global-search-input"],
    .dark input[type="search"] {
        background: rgba(30, 41, 59, 0.9) !important;
        border: 1px solid rgba(139, 92, 246, 0.3) !important;
        color: #e2e8f0 !important;
    }

    /* Dark mode - Modal */
    .dark div[class*="fi-modal-window"] {
        background: rgba(30, 41, 59, 0.98) !important;
        border: 1px solid rgba(139, 92, 246, 0.3) !important;
    }

    /* Dark mode - Dropdown */
    .dark div[class*="fi-dropdown-list"] {
        background: rgba(30, 41, 59, 0.98) !important;
        border: 1px solid rgba(139, 92, 246, 0.3) !important;
    }

    /* Dark mode - Text colors */
    .dark h1, .dark h2, .dark h3, .dark h4, .dark h5, .dark h6 {
        color: #f1f5f9 !important;
    }

    .dark span[class*="fi-header-heading"],
    .dark span[class*="fi-section-header-heading"] {
        color: #f1f5f9 !important;
    }

    .dark p, .dark span, .dark label {
        color: #cbd5e1 !important;
    }

    /* Dark mode - Breadcrumbs */
    .dark nav[class*="fi-breadcrumbs"] {
        background: rgba(30, 41, 59, 0.8) !important;
    }

    /* Dark mode - File upload */
    .dark div[class*="fi-fo-file-upload"] {
        background: rgba(30, 41, 59, 0.9) !important;
        border: 2px dashed rgba(139, 92, 246, 0.4) !important;
    }

    /* Dark mode - Notification */
    .dark .fi-no-notification {
        background: rgba(30, 41, 59, 0.95) !important;
        border: 1px solid rgba(139, 92, 246, 0.3) !important;
    }

    /* Dark mode - Stats widgets */
    .dark div[class*="fi-wi-stats-overview-stat"] {
        background: rgba(30, 41, 59, 0.95) !important;
        border: 1px solid rgba(139, 92, 246, 0.25) !important;
    }

    /* Dark mode - Badges */
    .dark span[class*="fi-badge"] {
        background: rgba(139, 92, 246, 0.2) !important;
        color: var(--primary-purple-light) !important;
        border: 1px solid rgba(139, 92, 246, 0.3) !important;
    }

    /* Dark mode - Scrollbar */
    .dark ::-webkit-scrollbar-track {
        background: rgba(139, 92, 246, 0.1);
    }

    .dark ::-webkit-scrollbar-thumb {
        background: linear-gradient(135deg, rgba(139, 92, 246, 0.4) 0%, rgba(167, 139, 250, 0.4) 100%);
    }

    .dark ::-webkit-scrollbar-thumb:hover {
        background: linear-gradient(135deg, rgba(139, 92, 246, 0.6) 0%, rgba(167, 139, 250, 0.6) 100%);
    }

    /* Dark mode - Pagination input */
    .dark input[type="number"],
    .dark select {
        background: rgba(30, 41, 59, 0.95) !important;
        border: 1px solid rgba(139, 92, 246, 0.3) !important;
        color: #e2e8f0 !important;
    }

    /* Dark mode - Main content area */
    .dark main[class*="fi-main"] {
        background: transparent !important;
    }

    /* Dark mode - Page background */
    .dark div[class*="fi-page"] {
        background: transparent !important;
    }
</style>
