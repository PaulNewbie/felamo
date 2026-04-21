$(document).ready(function() {
    
    // --- HELPER: SHOW ALERT ---
    const showAlert = (type, message) => {
        if ($("#alert").length) {
            $("#alert").removeClass().addClass(`alert ${type}`).text(message).show();
            setTimeout(() => {
                $("#alert").fadeOut("slow");
            }, 3000);
        } else {
            const icon = type === 'alert-success' ? 'success' : 'error';
            if (typeof Swal !== 'undefined') {
                Swal.fire({ 
                    icon: icon, 
                    title: message, 
                    confirmButtonColor: '#880f0b',
                    timer: 2000 // Auto close after 2 seconds
                });
            } else {
                alert(message);
            }
        }
    };

    // --- LOAD STUDENTS FUNCTION ---
    const loadStudents = () => {
        const sectionId = $("#hidden_section_id").val();
        
        $.ajax({
            type: "POST",
            url: "../backend/api/web/students.php",
            data: { 
                requestType: "GetStudentsBySection",
                section_id: sectionId
            },
            dataType: "json", // Force response to be JSON
            success: function(response) {
                let tbody = $("#student-table-tbody");
                tbody.empty(); // Clear current list

                // Check if response has data
                // Some backends return {status: 'success', data: [...]}
                // Others might return just the array [...]
                const students = response.data || response;

                if (Array.isArray(students) && students.length > 0) {
                    students.forEach(student => {
                        // Handle null values gracefully
                        const email = student.email || 'N/A';
                        const contact = student.contact_no || 'N/A';
                        const middle = student.middle_name || '';
                        
                        tbody.append(`
                            <tr>
                                <td>${student.lrn}</td>
                                <td>${email}</td>
                                <td>${contact}</td>
                                <td>${student.first_name}</td>
                                <td>${middle}</td>
                                <td>${student.last_name}</td>
                                <td>${student.birth_date}</td>
                                <td>${student.gender}</td>
                            </tr>
                        `);
                    });
                } else {
                    tbody.append('<tr><td colspan="8" class="text-center text-muted">No students assigned to this section yet.</td></tr>');
                }
            },
            error: function(xhr, status, error) {
                console.error("Load Error:", error);
            }
        });
    };

    // --- SUBMIT FORM ---
    $("#assign-student-form").submit(function(e) {
        e.preventDefault(); // Stop page reload

        const lrn = $("#assign_lrn").val(); 
        const formData = new FormData(this);
        
        formData.append("requestType", "InsertStudent");
        formData.append("section_id", $("#hidden_section_id").val());
        formData.append("password", lrn); // Default password

        $.ajax({
            type: "POST",
            url: "../backend/api/web/students.php",
            data: formData,
            contentType: false,
            processData: false,
            dataType: "json", // Expect JSON response
            success: function(response) {
                if (response.status === "success" || response.status === 200) {
                    
                    // 1. Show Success Message
                    showAlert("alert-success", "Student added successfully!");
                    
                    // 2. Close Modal
                    $("#insertStudentModal").modal('hide');
                    
                    // 3. Reset Form
                    $("#assign-student-form")[0].reset();
                    
                    // 4. RELOAD THE TABLE IMMEDIATELY
                    loadStudents(); 

                } else {
                    showAlert("alert-danger", response.message || "Failed to add student.");
                }
            },
            error: function(xhr, status, error) {
                console.error("Submit Error:", xhr.responseText);
                showAlert("alert-danger", "Error saving data. Check console.");
            }
        });
    });

    // Initial Load when page opens
    loadStudents();
});