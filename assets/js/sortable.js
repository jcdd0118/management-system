document.addEventListener('DOMContentLoaded', () => {
    const table = document.querySelector('table');
    if (!table) return;
    
    const headers = table.querySelectorAll('.sortable');
    let sortDirection = {};

    headers.forEach(header => {
        header.addEventListener('click', () => {
            const sortKey = header.getAttribute('data-sort');
            const isAscending = !sortDirection[sortKey] || sortDirection[sortKey] === 'desc';
            sortDirection[sortKey] = isAscending ? 'asc' : 'desc';

            // Update sort icons
            headers.forEach(h => {
                const icon = h.querySelector('i');
                if (h === header) {
                    icon.className = `bi bi-sort-${sortKey === 'year' ? 'numeric' : 'alpha'}-${isAscending ? 'down' : 'up'}`;
                } else {
                    icon.className = `bi bi-sort-${h.getAttribute('data-sort') === 'year' ? 'numeric' : 'alpha'}-down`;
                }
            });

            // Sort table rows
            const tbody = table.querySelector('tbody');
            if (!tbody) return;
            
            const rows = Array.from(tbody.querySelectorAll('tr'));
            const index = Array.from(header.parentElement.children).indexOf(header);

            rows.sort((a, b) => {
                let aValue = a.children[index].textContent.trim();
                let bValue = b.children[index].textContent.trim();

                // Handle numeric sorting for Year column
                if (sortKey === 'year') {
                    aValue = parseInt(aValue) || 0;
                    bValue = parseInt(bValue) || 0;
                    return isAscending ? aValue - bValue : bValue - aValue;
                }

                // Text sorting for other columns
                return isAscending
                    ? aValue.localeCompare(bValue)
                    : bValue.localeCompare(aValue);
            });

            // Rebuild table body while preserving event listeners
            // Use DocumentFragment for better performance
            const fragment = document.createDocumentFragment();
            rows.forEach(row => fragment.appendChild(row));
            tbody.appendChild(fragment);
        });
    });
});