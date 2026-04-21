$(document).ready(function () {
    const auth_user_id = $("#hidden_user_id").val();
    const level_id = $("#hidden_level_id").val();

    const loadTakenAssessments = () => {
        // 1. Set Loading State (Just Icon, No Text)
        $("#taken-assessments-list").html(`
            <tr>
                <td colspan="5" class="text-center py-5">
                    <div class="spinner-border text-secondary" role="status" style="width: 2rem; height: 2rem; border-width: 0.25em;"></div>
                </td>
            </tr>
        `);

        $.ajax({
            type: "POST",
            url: "../backend/api/web/taken_assessment.php",
            data: { 
                requestType: "GetTakenAssessments", 
                level_id: level_id,
                teacher_id: auth_user_id,
                filter: "all" 
            },
            dataType: "json", 
            success: function (response) {
                let data = response.data ? response.data : response; 
                if (Array.isArray(response)) {
                    data = response;
                }

                let html = "";

                if (!data || data.length === 0) {
                    html = `
                        <tr>
                            <td colspan="5" class="text-center py-5 text-muted fw-bold">
                                No assessments found.
                            </td>
                        </tr>`;
                } else {
                    data.forEach((item) => {
                        let id = item.id;
                        let title = item.assessment_title || item.title || "Untitled Assessment";
                        let studentName = item.student_name || item.fullname || "Unknown Student";
                        let score = item.score || 0;
                        let total = item.total_items || 0;
                        
                        // Date Formatting
                        let dateTaken = "N/A";
                        if (item.created_at) {
                            let dateObj = new Date(item.created_at);
                            if (!isNaN(dateObj)) {
                                dateTaken = dateObj.toLocaleDateString('en-US', { 
                                    year: 'numeric', month: 'short', day: 'numeric' 
                                });
                            }
                        }

                        // Status Color
                        let scorePercent = total > 0 ? (score / total) * 100 : 0;
                        let badgeClass = scorePercent >= 75 ? "bg-success" : (scorePercent >= 50 ? "bg-warning text-dark" : "bg-danger");

                        // 5-Column Row (Matches PHP Header)
                        html += `
                        <tr>
                            <td class="fw-bold text-dark">${title}</td>
                            <td class="fw-semibold text-secondary">${studentName}</td>
                            <td class="text-date">${dateTaken}</td>
                            <td>
                                <span class="badge ${badgeClass} rounded-pill px-3">
                                    ${score} / ${total}
                                </span>
                            </td>
                            <td class="text-end">
                                <a href="view_result.php?id=${id}" class="btn-action-red">
                                    <i class="bi bi-eye-fill"></i> View
                                </a>
                            </td>
                        </tr>
                        `;
                    });
                }
                $("#taken-assessments-list").html(html);
            },
            error: function (xhr, status, error) {
                console.error("AJAX Error:", xhr.responseText);
                $("#taken-assessments-list").html(`
                    <tr>
                        <td colspan="5" class="text-center text-danger py-4 fw-bold">
                            <i class="bi bi-exclamation-circle me-2"></i> Failed to load data.
                        </td>
                    </tr>
                `);
            },
        });
    };

    loadTakenAssessments();
});