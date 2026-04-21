let questionsList = []; // Array to store questions temporarily

$(document).ready(function () {
    
    // --- NEW: FETCH EXISTING ASSESSMENT AND QUESTIONS ON LOAD ---
    const levelId = $("#hidden_level_id").val();
    if (levelId) {
        fetchExistingAssessment(levelId);
    }

    // --- NEW: Listen for dropdown filter changes ---
    $("#question-filter").on("change", function() {
        renderQuestions();
    });

    // --- MAIN FORM SUBMIT ---
    $("#create-assessment-form").on("submit", function (e) {
        e.preventDefault();

        const title = $("#assessment_title").val();
        if (!title) { alert("Please enter an Assessment Title."); return; }

        let formData = new FormData(this);

        // Append Fixed Data
        formData.append('requestType', 'CreateAssessment');
        formData.append('teacher_id', $("#hidden_user_id").val());
        formData.append('level_id', $("#hidden_level_id").val());
        
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
                $(".btn-submit").prop("disabled", false).html('<i class="bi bi-check-circle me-2"></i> Save Assessment');
                if (response.status === "success") {
                    alert("Assessment saved successfully!");
                    window.location.href = "levels.php"; 
                } else {
                    alert("Error: " + (response.message || "Unknown error"));
                }
            },
            error: function (xhr) {
                $(".btn-submit").prop("disabled", false).html('<i class="bi bi-check-circle me-2"></i> Save Assessment');
                console.error("Error:", xhr.responseText);
                alert("Server Error.");
            }
        });
    });
});

// --- NEW FUNCTION: Fetch existing assessment data ---
function fetchExistingAssessment(levelId) {
    $.ajax({
        type: "POST",
        url: "../backend/api/web/asssessments.php",
        data: { requestType: 'GetAssessment', level_id: levelId },
        dataType: "json",
        success: function(response) {
            // FIX: Check if data exists and get the first record (response.data[0]) 
            // because your backend returns an array of assessments.
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
            console.log("No existing assessment found for this level (or error occurred).", err);
        }
    });
}

// --- NEW FUNCTION: Fetch questions from all 4 tables ---
function fetchQuestionsByType(assessmentId) {
    const questionTypes = [
        { req: "GetMultiQuestions", type: "MCQ" },
        { req: "GetTrueOrFalseQuestions", type: "TF" },
        { req: "GetIdentificationQuestions", type: "IDENT" },
        { req: "GetJumbledWordsQuestions", type: "JUMBLED" }
    ];

    questionTypes.forEach(qt => {
        $.ajax({
            type: "POST",
            url: "../backend/api/web/asssessments.php",
            data: { requestType: qt.req, assessment_id: assessmentId },
            dataType: "json",
            success: function(response) {
                if (response.status === "success" && response.data && response.data.length > 0) {
                    response.data.forEach(q => {
                        // Map database fields to the structure expected by your render function
                        let qData = { type: qt.type, question: q.question, is_existing: true, id: q.id };
                        
                        // Map answer formats depending on the type
                        if (qt.type === 'MCQ') {
                            qData.a = q.choice_a;
                            qData.b = q.choice_b;
                            qData.c = q.choice_c;
                            qData.d = q.choice_d;
                            qData.correct = q.correct_answer || q.answer; 
                        } else if (qt.type === 'TF') {
                            // Map 1/0 back to "True"/"False" string for UI
                            qData.correct = (q.answer === 1 || q.answer === 'true' || q.answer === true) ? "True" : "False";
                        } else {
                            qData.correct = q.correct_answer || q.answer; 
                        }
                        
                        questionsList.push(qData);
                    });
                    
                    // Render the UI after pushing the fetched questions
                    renderQuestions();
                }
            }
        });
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

// --- RENDER PREVIEW LIST ---
function renderQuestions() {
    let container = $("#questions-list");
    let wrapper = $("#questions-preview-container");
    container.empty();

    // Get the current value from the dropdown
    let filterVal = $("#question-filter").val() || "ALL";

    if (questionsList.length > 0) {
        wrapper.removeClass("d-none");
        $("#q-count").text(questionsList.length); // Keep showing the total count of all questions

        let visibleCount = 0; // Track how many match the filter

        questionsList.forEach((q, index) => {
            // Skip this question if it doesn't match the selected filter
            if (filterVal !== "ALL" && q.type !== filterVal) {
                return; 
            }
            
            visibleCount++; // Found a match!

            let badgeClass = "bg-secondary";
            if(q.type === 'MCQ') badgeClass = "bg-primary";
            if(q.type === 'TF') badgeClass = "bg-success";
            if(q.type === 'IDENT') badgeClass = "bg-info text-dark";
            if(q.type === 'JUMBLED') badgeClass = "bg-warning text-dark";
            
            // Wrap the added-question-item in a col-lg-4 (3 columns) div
            let html = `
                <div class="col-lg-4 col-md-6 col-12">
                    <div class="added-question-item">
                        <span class="badge ${badgeClass} mb-2">${q.type}</span>
                        <p class="mb-1 fw-bold">${q.question}</p>
                        <small class="text-muted">Answer: ${q.correct}</small>
                        <button type="button" class="btn-remove-q" onclick="removeQuestion(${index})">&times;</button>
                    </div>
                </div>
            `;
            container.append(html);
        });

        // If the user selects a filter but no questions of that type exist
        if (visibleCount === 0) {
            container.append(`
                <div class="col-12 text-center text-muted fst-italic my-4">
                    No ${filterVal !== 'ALL' ? filterVal : ''} questions found.
                </div>
            `);
        }

    } else {
        wrapper.addClass("d-none");
    }
}

function removeQuestion(index) {
    questionsList.splice(index, 1);
    renderQuestions();
}