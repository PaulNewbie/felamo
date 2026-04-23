let questionsList = []; // Array to store questions temporarily

$(document).ready(function () {
    
    // --- UPDATED: FETCH EXISTING ASSESSMENT USING ARALIN ID ---
    const aralinId = $("#hidden_aralin_id").val();
    if (aralinId) {
        fetchExistingAssessment(aralinId);
    }

    // --- Listen for dropdown filter changes ---
    $("#question-filter").on("change", function() {
        renderQuestions();
    });

    // --- MAIN FORM SUBMIT ---
    $("#create-assessment-form").on("submit", function (e) {
        e.preventDefault();

        const title = $("#assessment_title").val();
        if (!title) { alert("Please enter an Assessment Title."); return; }

        let formData = new FormData(this);
        let assessmentId = $("#hidden_assessment_id").val();

        // FIX 1: REDUNDANCY CHECK - Update if it exists, Create if it doesn't
        if (assessmentId) {
            formData.append('requestType', 'UpdateAssessment');
            formData.append('assessment_id', assessmentId);
        } else {
            formData.append('requestType', 'CreateAssessment');
        }

        // Append Fixed Data
        formData.append('teacher_id', $("#hidden_user_id").val());
        formData.append('aralin_id', $("#hidden_aralin_id").val()); // CRITICAL: Uses aralin_id now
        
        // Append Defaults for removed fields
        formData.append('due_date', ''); 
        formData.append('time_limit', 0);
        formData.append('is_active', 0);

        // Append the QUESTIONS LIST as a JSON string
        formData.append('questions_data', JSON.stringify(questionsList));

        // Send AJAX
        $.ajax({
            type: "POST",
            url: "../backend/api/web/asssessments.php", 
            data: formData,
            dataType: "json",
            contentType: false, 
            processData: false, 
            beforeSend: function() {
                $(".btn-submit").prop("disabled", true).html('Saving...');
            },
            success: function (response) {
                $(".btn-submit").prop("disabled", false).html('<i class="bi bi-check-circle me-2"></i> Step 1: Save Details');
                if (response.status === "success") {
                    
                    // FIX 2: NO MORE REDIRECTING! Show popup and unlock CSV.
                    Swal.fire({
                        icon: 'success',
                        title: 'Details Saved!',
                        text: 'Database record created. You can now upload your CSV questions below!',
                        timer: 2500,
                        showConfirmButton: false
                    });
                    
                    // Fetch the newly created ID to unlock the CSV upload button
                    const currentAralinId = $("#hidden_aralin_id").val();
                    fetchExistingAssessment(currentAralinId); 
                    
                } else {
                    alert("Error: " + (response.message || "Unknown error"));
                }
            },
            error: function (xhr) {
                $(".btn-submit").prop("disabled", false).html('<i class="bi bi-check-circle me-2"></i> Step 1: Save Details');
                console.error("Error:", xhr.responseText);
                alert("Server Error.");
            }
        });
    });
});

// --- UPDATED FUNCTION: Fetch existing assessment data using aralin_id ---
function fetchExistingAssessment(aralinId) {
    $.ajax({
        type: "POST",
        url: "../backend/api/web/asssessments.php",
        data: { requestType: 'GetAssessment', aralin_id: aralinId }, // CRITICAL: Sent as aralin_id
        dataType: "json",
        success: function(response) {
            if (response.status === "success" && response.data && response.data.length > 0) {
                let assessment = response.data[0]; 
                
                // Pre-fill Title and Description
                $("#assessment_title").val(assessment.title);
                $("#assessment_description").val(assessment.description);
                
                // Set the hidden assessment_id for the form
                let assessmentId = assessment.id; 
                $("#hidden_assessment_id").val(assessmentId);

                // Fetch the actual questions attached to this assessment
                fetchQuestionsByType(assessmentId);
            }
        },
        error: function(err) {
            console.log("No existing assessment found for this lesson (or error occurred).", err);
        }
    });
}

// --- Fetch questions from the NEW Unified Table ---
function fetchQuestionsByType(assessmentId) {
    $.ajax({
        type: "POST",
        url: "../backend/api/web/asssessments.php",
        data: { requestType: 'GetUnifiedQuestions', assessment_id: assessmentId },
        dataType: "json",
        success: function(response) {
            if (response.status === "success" && response.data && response.data.length > 0) {
                questionsList = []; // Clear before re-populating to prevent duplicates in UI
                response.data.forEach(q => {
                    // Map the new unified database fields to the UI
                    let qData = { 
                        type: q.type.toUpperCase(), // e.g. MULTIPLE_CHOICE
                        question: q.question_text, 
                        correct: q.correct_answer,
                        is_existing: true, 
                        id: q.id 
                    };
                    questionsList.push(qData);
                });
                renderQuestions();
            }
        }
    });
}

// --- HELPER FOR MODAL SELECTION UI ---
function selectRadio(val) {
    let radioBtn = $("#radio" + val);
    radioBtn.prop("checked", true);
    let form = radioBtn.closest("form");
    form.find(".choice-item").removeClass("active-choice");
    radioBtn.closest(".choice-item").addClass("active-choice");
}

$(document).on('change', 'input[type="radio"]', function() {
    let val = $(this).val();
    if ($(this).attr("id") && $(this).attr("id").startsWith("radio")) {
        selectRadio(val);
    }
});

// --- FUNCTION TO SAVE QUESTION FROM MODAL ---
function saveQuestion(type) {
    let qData = { type: type, is_new: true };
    let isValid = true;

    if (type === 'MCQ') {
        qData.question = $("#mcq_question").val();
        qData.a = $("#mcq_a").val();
        qData.b = $("#mcq_b").val();
        qData.c = $("#mcq_c").val();
        qData.d = $("#mcq_d").val();
        qData.correct = $("input[name='mcq_correct']:checked").val();
        if (!qData.question || !qData.a || !qData.b || !qData.correct) isValid = false;
    } 
    else if (type === 'TF') {
        qData.question = $("#tf_question").val();
        qData.correct = $("input[name='tf_correct']:checked").val();
        if (!qData.question || !qData.correct) isValid = false;
    }
    else if (type === 'IDENT') {
        qData.question = $("#ident_question").val();
        qData.correct = $("#ident_answer").val();
        if (!qData.question || !qData.correct) isValid = false;
    }
    else if (type === 'JUMBLED') {
        qData.question = $("#jumbled_question").val();
        qData.correct = $("#jumbled_answer").val();
        if (!qData.question || !qData.correct) isValid = false;
    }

    if (!isValid) {
        alert("Please fill in all required fields.");
        return;
    }

    questionsList.push(qData);
    renderQuestions();

    $("#formMCQ")[0].reset();
    $("#formTF")[0].reset();
    $("#formIdent")[0].reset();
    $("#formJumbled")[0].reset();
    $(".choice-item").removeClass("active-choice");
    
    $(".modal").modal("hide");
}

// --- RENDER LIST FUNCTION ---
function renderQuestions() {
    let container = $("#questions-list");
    let wrapper = $("#questions-preview-container");
    let emptyState = $("#empty-state");
    
    container.empty();

    let filterVal = $("#question-filter").val() || "ALL";

    if (questionsList.length > 0) {
        wrapper.removeClass("d-none");
        emptyState.addClass("d-none"); // Hides "No Questions Uploaded Yet"
        
        $("#q-count").text(questionsList.length);

        let visibleCount = 0;

        questionsList.forEach((q, index) => {
            // Map the old HTML filter values to our new Database types
            let typeMatches = false;
            if (filterVal === "ALL") typeMatches = true;
            else if (filterVal === "MCQ" && q.type === "MULTIPLE_CHOICE") typeMatches = true;
            else if (filterVal === "TF" && q.type === "TRUE_FALSE") typeMatches = true;
            else if (filterVal === "IDENT" && q.type === "IDENTIFICATION") typeMatches = true;
            else if (filterVal === "JUMBLED" && q.type === "JUMBLED_WORD") typeMatches = true;

            if (!typeMatches) return; 
            
            visibleCount++;

            let badgeClass = "bg-secondary";
            if(q.type === 'MULTIPLE_CHOICE') badgeClass = "bg-primary";
            if(q.type === 'TRUE_FALSE') badgeClass = "bg-success";
            if(q.type === 'IDENTIFICATION') badgeClass = "bg-info text-dark";
            if(q.type === 'JUMBLED_WORD') badgeClass = "bg-warning text-dark";
            
            // Clean up the text (e.g. MULTIPLE_CHOICE -> MULTIPLE CHOICE)
            let displayType = q.type.replace('_', ' '); 
            
            let html = `
                <div class="col-lg-4 col-md-6 col-12">
                    <div class="added-question-item">
                        <span class="badge ${badgeClass} mb-2">${displayType}</span>
                        <p class="mb-1 fw-bold">${q.question}</p>
                        <small class="text-muted">Answer: ${q.correct}</small>
                    </div>
                </div>
            `;
            container.append(html);
        });

        if (visibleCount === 0) {
            container.append(`
                <div class="col-12 text-center text-muted fst-italic my-4">
                    No questions found for this filter.
                </div>
            `);
        }

    } else {
        wrapper.addClass("d-none");
        emptyState.removeClass("d-none"); // Shows "No Questions Uploaded Yet" if empty
    }
}

function removeQuestion(index) {
    questionsList.splice(index, 1);
    renderQuestions();
}

// --- UNIFIED BULK CSV UPLOAD ---
$(document).on("click", "#btn-upload-csv", function() {
    const assessmentId = $("#hidden_assessment_id").val();
    
    // Validation 1: Check if assessment is saved
    if (!assessmentId) {
        Swal.fire({
            icon: 'warning',
            title: 'Database Record Missing',
            text: 'You must click "Step 1: Save Details" first to generate a database record. Once saved, you can upload your CSV!'
        });
        return;
    }

    const fileInput = $("#bulk_csv_file")[0];
    
    // Validation 2: Check if file is selected
    if (fileInput.files.length === 0) {
        Swal.fire({
            icon: 'warning',
            title: 'No File Selected',
            text: 'Please select your CSV file first.'
        });
        return;
    }

    const file = fileInput.files[0];
    const formData = new FormData();
    formData.append("csv_file", file);
    formData.append("assessment_id", assessmentId);

    // Show loading popup
    Swal.fire({
        title: 'Uploading Questions...',
        text: 'Validating your CSV data. Please wait.',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    // Send the file to our new PHP script
    $.ajax({
        url: '../backend/api/web/upload-questions.php',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function(response) {
            if (response.status === 200) {
                Swal.fire({
                    icon: 'success',
                    title: 'Upload Successful!',
                    text: response.message
                }).then(() => {
                    $("#bulk_csv_file").val(''); // Clear the file input
                    questionsList = []; // Clear current preview list
                    fetchQuestionsByType(assessmentId); // Refresh the preview UI
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Upload Failed',
                    text: response.message // Shows exactly which row had an error
                });
            }
        },
        error: function(xhr) {
            Swal.fire({
                icon: 'error',
                title: 'Server Error',
                text: 'An unexpected error occurred. Check the console.'
            });
            console.error(xhr.responseText);
        }
    });
});