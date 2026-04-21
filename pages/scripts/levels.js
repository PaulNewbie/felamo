$(document).ready(function () {
    const auth_user_id = $("#hidden_user_id").val();

    const loadAntas = () => {
        $.ajax({
            type: "POST",
            url: "../backend/api/web/levels.php",
            data: { requestType: "GetLevels", auth_user_id },
            success: function (response) {
                let res = JSON.parse(response);

                if (res.status === "success") {
                    let levels = res.data;
                    let html = "";

                    if (levels.length === 0) {
                        html = `
                            <div class="p-4 text-center text-muted">
                                Walang antas na nahanap.
                            </div>`;
                    } else {
                        levels.forEach((level) => {
                            let markahanName = "";
                            
                            // Determine Label
                            switch (level.level) {
                                case 1: markahanName = "Unang Markahan"; break;
                                case 2: markahanName = "Pangalawang Markahan"; break;
                                case 3: markahanName = "Pangatlong Markahan"; break;
                                case 4: markahanName = "Ika-apat na Markahan"; break;
                                default: markahanName = "Markahan " + level.level;
                            }

                            // Generate the List Item HTML
                            html += `
                                <div class="markahan-item">
                                    <span class="markahan-title">${markahanName}</span>
                                    
                                    <div class="dropdown">
                                        <button class="btn-action-red dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                            Action
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end shadow">
                                            
                                            <li>
                                                <a class="dropdown-item" href="level_details.php?level=${level.id}">
                                                    <i class="bi bi-eye me-2"></i> View Details
                                                </a>
                                            </li>

                                            <li>
                                                <a class="dropdown-item" href="create_assessment.php?level=${level.id}">
                                                    <i class="bi bi-pencil-square me-2"></i> Create Assessment
                                                </a>
                                            </li>

                                            <li>
                                                <a class="dropdown-item" href="taken_assessments.php?level=${level.id}">
                                                    <i class="bi bi-journal-check me-2"></i> View Taken Assessment
                                                </a>
                                            </li>

                                        </ul>
                                    </div>
                                </div>
                            `;
                        });
                    }

                    $("#levels-container").html(html);
                } else {
                    $("#levels-container").html(`<div class="p-4 text-center text-danger">Failed to load levels.</div>`);
                }
            },
            error: function () {
                $("#levels-container").html(`<div class="p-4 text-center text-danger">Server error.</div>`);
            },
        });
    };

    loadAntas();
});