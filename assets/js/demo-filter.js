jQuery(document).ready(function($) {
    // Demo category filter functionality
    
    // Initialize filter
    function initializeDemoFilter() {
        // Add filter UI if not exists
        if ($('#demo-category-filter').length === 0) {
            const filterHTML = `
                <div id="demo-category-filter" class="demo-filter-wrapper">
                    <h3>Filter Demos by Category <span id="demo-count"></span></h3>
                    <div class="filter-buttons">
                        <button class="filter-btn active" data-category="all">All Demos</button>
                        <button class="filter-btn" data-category="community">Community</button>
                        <button class="filter-btn" data-category="lms">Learning (LMS)</button>
                        <button class="filter-btn" data-category="marketplace">Marketplace</button>
                        <button class="filter-btn" data-category="directory">Directory</button>
                        <button class="filter-btn" data-category="jobs">Jobs</button>
                    </div>
                </div>
            `;
            
            $('#demos_import_filter').before(filterHTML);
        }
        
        // Update initial count
        updateDemoCount($('.demo-details').length);
        
        // Hide demos without categories (if needed)
        $('.demo-details').each(function() {
            const categories = $(this).attr('data-categories');
            if (!categories || categories.trim() === '') {
                // Optionally hide demos without categories
                // $(this).hide();
            }
        });
    }
    
    // Filter demos
    $(document).on('click', '.filter-btn', function() {
        const category = $(this).data('category');
        
        // Update active button
        $('.filter-btn').removeClass('active');
        $(this).addClass('active');
        
        // Filter demos
        if (category === 'all') {
            $('.demo-details').show();
        } else {
            $('.demo-details').each(function() {
                const categories = $(this).attr('data-categories') || '';
                if (categories.includes(category)) {
                    $(this).show();
                } else {
                    $(this).hide();
                }
            });
        }
        
        // Update count
        const visibleCount = $('.demo-details:visible').length;
        updateDemoCount(visibleCount);
    });
    
    // Update demo count
    function updateDemoCount(count) {
        $('#demo-count').text(`(${count} demos)`);
    }
    
    // Initialize on page load
    if ($('#demos_import_filter').length > 0) {
        setTimeout(initializeDemoFilter, 500);
    }
});