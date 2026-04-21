$(document).ready(function () {
    const level_id = $("#hidden_level_id").val();

    // --- LOAD ARALINS (TABLE VIEW) ---
    const loadAralins = () => {
        $.ajax({
            type: "POST",
            url: "../backend/api/web/aralin.php",
            data: {
                requestType: "GetAralin",
                level_id: level_id,
            },
            success: function (response) {
                let res = JSON.parse(response);
                if (res.status === "success") {
                    let aralins = res.data;
                    let html = "";

                    if (aralins.length === 0) {
                        html = `
                            <tr>
                                <td colspan="4" class="text-center py-5 text-muted">
                                    <i class="bi bi-inbox fs-1 d-block mb-2 opacity-25"></i>
                                    No lessons found. Click "New Aralin" to add one.
                                </td>
                            </tr>`;
                    } else {
                        aralins.forEach((aralin, index) => {
                            // Calculate Aralin Number (index + 1)
                            let lessonNum = index + 1;
                            
                            // Truncate summary if too long
                            let summary = aralin.summary || "";
                            if (summary.length > 100) summary = summary.substring(0, 100) + "...";

                            html += `
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="rounded-circle bg-light d-flex align-items-center justify-content-center me-3" 
                                                 style="width: 40px; height: 40px; color: #a71b1b; font-weight: bold;">
                                                ${lessonNum}
                                            </div>
                                            <span class="text-secondary fw-bold">Aralin ${lessonNum}</span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="aralin-title">${aralin.title}</div>
                                    </td>
                                    <td>
                                        <div class="aralin-summary">${summary}</div>
                                    </td>
                                    <td style="text-align: right;">
                                        <div class="dropdown">
                                            <button class="btn-action-red dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                Action
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end shadow">
                                                <li>
                                                    <a class="dropdown-item edit-aralin-btn" href="#" 
                                                       data-id="${aralin.id}" 
                                                       data-title="${aralin.title}" 
                                                       data-summary="${aralin.summary}" 
                                                       data-details="${aralin.details}" 
                                                       data-attachment="${aralin.attachment}">
                                                       <i class="bi bi-pencil-square me-2"></i> Edit
                                                    </a>
                                                </li>
                                                <li>
                                                    <a class="dropdown-item text-primary" href="../backend/storage/videos/${aralin.attachment}" target="_blank">
                                                        <i class="bi bi-play-circle me-2"></i> Preview Video
                                                    </a>
                                                </li>
                                                <li><hr class="dropdown-divider"></li>
                                                <li>
                                                    <a class="dropdown-item text-danger delete-aralin-btn" href="#" data-id="${aralin.id}">
                                                        <i class="bi bi-trash me-2"></i> Delete
                                                    </a>
                                                </li>
                                            </ul>
                                        </div>
                                    </td>
                                </tr>
                            `;
                        });
                    }

                    $("#antas-table-body").html(html);
                } else {
                    $("#antas-table-body").html('<tr><td colspan="4" class="text-center text-danger">Failed to load data.</td></tr>');
                }
            },
            error: function () {
                $("#antas-table-body").html('<tr><td colspan="4" class="text-center text-danger">Server error.</td></tr>');
            },
        });
    };

    loadAralins();

    // --- CREATE ARALIN ---
    // --- CREATE ARALIN ---
    $("#insert-aralin-form").submit(function (e) {
        e.preventDefault();
        
        // Change button text to show it's loading
        let submitBtn = $(this).find('button[type="submit"]');
        let originalText = submitBtn.text();
        submitBtn.text("Saving...").prop("disabled", true);

        let formData = new FormData(this);

        $.ajax({
            type: "POST",
            url: "../backend/api/web/aralin.php",
            data: formData,
            contentType: false,
            processData: false,
            success: function (response) {
                submitBtn.text(originalText).prop("disabled", false);
                try {
                    let res = JSON.parse(response);
                    if (res.status === "success") {
                        alert("Lesson successfully saved!");
                        $("#insertAralinModal").modal("hide");
                        $("#insert-aralin-form")[0].reset();
                        loadAralins(); // This reloads the table immediately
                    } else {
                        alert("Failed: " + res.message);
                    }
                } catch (err) {
                    console.error("Raw server response:", response);
                    alert("Server returned invalid data. Press F12 and check the Console tab for the exact PHP error.");
                }
            },
            error: function (xhr, status, error) {
                submitBtn.text(originalText).prop("disabled", false);
                
                // Try to parse the error message from the server if it's JSON
                try {
                    let res = JSON.parse(xhr.responseText);
                    alert("Error: " + res.message);
                } catch(e) {
                    alert("Server Error (" + xhr.status + "): " + xhr.responseText);
                }
            },
        });
    });

    // --- OPEN EDIT MODAL ---
    $(document).on("click", ".edit-aralin-btn", function (e) {
        e.preventDefault();
        
        let id = $(this).data("id");
        let title = $(this).data("title");
        let summary = $(this).data("summary");
        let details = $(this).data("details");
        let attachment = $(this).data("attachment");

        $("#edit-aralin-id").val(id);
        $("#edit-aralin-title").val(title);
        $("#edit-aralin-summary").val(summary);
        $("#edit-aralin-details").val(details);

        // Update Video Link
        if (attachment) {
            $("#current-video-link").attr("href", "../backend/storage/videos/" + attachment);
            $("#current-video-link").show();
            $("#current-video-text").text("Current video: " + attachment);
        } else {
            $("#current-video-link").hide();
            $("#current-video-text").text("No video attached.");
        }

        $("#editAralinModal").modal("show");
    });

    // --- SUBMIT EDIT FORM ---
    $("#edit-aralin-form").submit(function (e) {
        e.preventDefault();
        let formData = new FormData(this);

        $.ajax({
            type: "POST",
            url: "../backend/api/web/aralin.php",
            data: formData,
            contentType: false,
            processData: false,
            success: function (response) {
                let res = JSON.parse(response);
                if (res.status === "success") {
                    alert(res.message);
                    $("#editAralinModal").modal("hide");
                    loadAralins();
                } else {
                    alert(res.message);
                }
            },
            error: function () {
                alert("An error occurred.");
            },
        });
    });

    // --- DELETE HANDLER (Optional placeholder) ---
    $(document).on("click", ".delete-aralin-btn", function(e) {
        e.preventDefault();
        if(confirm("Are you sure you want to delete this lesson?")) {
            // Add delete logic here if needed (requires backend support)
            alert("Delete functionality coming soon.");
        }
    });
});