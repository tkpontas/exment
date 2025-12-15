/**
 * Fix scroll position restore issue after browser back button
 * For custom table list view in Exment
 */
(function() {
    'use strict';

    // Storage key for scroll positions
    const SCROLL_POSITION_KEY = 'exment_scroll_positions';
    const MAX_HISTORY = 50; // Keep last 50 positions
    const CLASSNAME_CUSTOM_VALUE_GRID = 'block_custom_value_grid';

    // Get current page identifier
    function getPageKey() {
        return window.location.pathname + window.location.search;
    }

    // Get all scroll positions from sessionStorage
    function getScrollPositions() {
        try {
            const data = sessionStorage.getItem(SCROLL_POSITION_KEY);
            return data ? JSON.parse(data) : {};
        } catch (e) {
            console.warn('Failed to load scroll positions:', e);
            return {};
        }
    }

    // Save scroll positions to sessionStorage
    function saveScrollPositions(positions) {
        try {
            // Limit the number of stored positions
            const keys = Object.keys(positions);
            if (keys.length > MAX_HISTORY) {
                // Sort by timestamp and remove oldest entries
                const sortedKeys = keys.sort((a, b) => {
                    return (positions[a].timestamp || 0) - (positions[b].timestamp || 0);
                });
                sortedKeys.slice(0, keys.length - MAX_HISTORY).forEach(key => {
                    delete positions[key];
                });
            }
            sessionStorage.setItem(SCROLL_POSITION_KEY, JSON.stringify(positions));
        } catch (e) {
            console.warn('Failed to save scroll positions:', e);
        }
    }

    // Save current scroll position
    function saveCurrentScrollPosition() {
        const pageKey = getPageKey();
        const scrollTop = $(window).scrollTop() || document.documentElement.scrollTop || document.body.scrollTop || 0;
        
        const positions = getScrollPositions();
        positions[pageKey] = {
            scrollTop: scrollTop,
            timestamp: Date.now()
        };
        saveScrollPositions(positions);
        
        // Also store in history state if available
        if (window.history && window.history.replaceState) {
            const currentState = window.history.state || {};
            currentState.scrollTop = scrollTop;
            try {
                window.history.replaceState(currentState, document.title);
            } catch (e) {
                console.warn('Failed to update history state:', e);
            }
        }
    }

    // Restore scroll position
    function restoreScrollPosition() {
        const pageKey = getPageKey();
        const positions = getScrollPositions();
        const savedPosition = positions[pageKey];
        
        let scrollTop = 0;
        
        // Try to get from history state first
        if (window.history && window.history.state && typeof window.history.state.scrollTop === 'number') {
            scrollTop = window.history.state.scrollTop;
        } 
        // Fallback to sessionStorage
        else if (savedPosition && typeof savedPosition.scrollTop === 'number') {
            scrollTop = savedPosition.scrollTop;
        }
        
        if (scrollTop > 0) {
            // Use setTimeout to ensure DOM is ready
            setTimeout(function() {
                window.scrollTo(0, scrollTop);
                // Verify and retry if needed
                setTimeout(function() {
                    const currentScroll = window.pageYOffset || document.documentElement.scrollTop;
                    if (Math.abs(currentScroll - scrollTop) > 50) {
                        window.scrollTo(0, scrollTop);
                    }
                }, 100);
            }, 0);
        }
    }

    // Clear old scroll positions (older than 1 hour)
    function clearOldPositions() {
        const positions = getScrollPositions();
        const now = Date.now();
        const oneHour = 60 * 60 * 1000;
        
        Object.keys(positions).forEach(key => {
            try {
                const position = positions[key];
                if (!position || typeof position !== 'object' || !position.timestamp || now - position.timestamp > oneHour) {
                    delete positions[key];
                }
            } catch (e) {
                // Remove invalid entry
                delete positions[key];
            }
        });
        
        saveScrollPositions(positions);
    }

    // Initialize on document ready
    $(function() {
        // Prevent multiple initialization
        if (window.exmentScrollFixInitialized) {
            return;
        }
        window.exmentScrollFixInitialized = true;

        // Disable browser's default scroll restoration
        if ('scrollRestoration' in history) {
            history.scrollRestoration = 'manual';
        }

        // Clear old positions on page load
        clearOldPositions();

        // Restore scroll position on page load
        restoreScrollPosition();

        // Save scroll position periodically while scrolling
        let scrollTimer;
        $(window).on('scroll', function() {
            clearTimeout(scrollTimer);
            scrollTimer = setTimeout(function() {
                // Only save if we're on a list page (grid view)
                if ($('.' + CLASSNAME_CUSTOM_VALUE_GRID).length > 0) {
                    saveCurrentScrollPosition();
                }
            }, 150);
        });

        // Save scroll position before leaving page
        $(window).on('beforeunload', function() {
            if ($('.' + CLASSNAME_CUSTOM_VALUE_GRID).length > 0) {
                saveCurrentScrollPosition();
            }
        });

        // Handle pjax events
        if ($.pjax) {
            // Save scroll position before pjax request
            $(document).on('pjax:send', function(event, xhr, options) {
                if ($('.' + CLASSNAME_CUSTOM_VALUE_GRID).length > 0) {
                    saveCurrentScrollPosition();
                }
            });

            // Restore scroll position after pjax complete
            $(document).on('pjax:complete', function(event, xhr, textStatus, options) {
                // Wait for content to be rendered
                setTimeout(function() {
                    if ($('.' + CLASSNAME_CUSTOM_VALUE_GRID).length > 0) {
                        restoreScrollPosition();
                    }
                }, 50);
            });

            // Also try to restore on pjax:end
            $(document).on('pjax:end', function(event, xhr, options) {
                setTimeout(function() {
                    if ($('.' + CLASSNAME_CUSTOM_VALUE_GRID).length > 0) {
                        restoreScrollPosition();
                    }
                }, 100);
            });
        }

        // Handle browser back/forward buttons
        $(window).on('popstate', function(event) {
            setTimeout(function() {
                if ($('.' + CLASSNAME_CUSTOM_VALUE_GRID).length > 0) {
                    restoreScrollPosition();
                }
            }, 50);
        });

        // Save position when clicking on links in grid
        $('.' + CLASSNAME_CUSTOM_VALUE_GRID).on('click', 'a', function() {
            saveCurrentScrollPosition();
        });
    });
})();
