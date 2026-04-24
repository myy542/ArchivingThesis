// This file handles AJAX requests for get_department_theses.php
function loadDepartmentTheses(deptId, containerId) {
    const container = document.getElementById(containerId);
    if (!container) return;
    
    container.innerHTML = '<div class="loading-spinner"></div><div>Loading theses...</div>';
    
    fetch(`get_department_theses.php?dept_id=${deptId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.theses && data.theses.length > 0) {
                let html = '<div class="theses-list">';
                data.theses.forEach(thesis => {
                    html += `
                        <div class="thesis-item">
                            <div class="thesis-info">
                                <div class="thesis-title">${escapeHtml(thesis.title)}</div>
                                <div class="thesis-meta">
                                    <span><i class="fas fa-user"></i> ${escapeHtml(thesis.student)}</span>
                                    <span><i class="fas fa-chalkboard-user"></i> ${escapeHtml(thesis.adviser)}</span>
                                    <span><i class="fas fa-calendar"></i> ${escapeHtml(thesis.date)}</span>
                                </div>
                            </div>
                            <div class="thesis-status status-${thesis.status.toLowerCase()}">${escapeHtml(thesis.status)}</div>
                        </div>
                    `;
                });
                html += '</div>';
                container.innerHTML = html;
            } else {
                container.innerHTML = '<div class="empty-state"><i class="fas fa-folder-open"></i><p>No theses found for this department.</p></div>';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            container.innerHTML = '<div class="error-state"><i class="fas fa-exclamation-circle"></i><p>Error loading theses. Please try again.</p></div>';
        });
}

function escapeHtml(text) {
    if (!text) return '';
    return text.replace(/[&<>]/g, function(m) {
        if (m === '&') return '&amp;';
        if (m === '<') return '&lt;';
        if (m === '>') return '&gt;';
        return m;
    });
}

console.log('get_department_theses.js loaded');