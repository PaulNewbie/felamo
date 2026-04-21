$(document).ready(function () {
  
  // --- HELPER FUNCTIONS ---
  const showAlert = (type, message) => {
    // Check if #alert element exists, if not create it dynamically (optional safety)
    if ($("#alert").length === 0) {
       // You might want to ensure an element with id="alert" exists in your HTML
       // or use SweetAlert like in previous versions. 
       // For now, I'll stick to your provided code structure.
    }
    
    $("#alert").removeClass().addClass(`alert ${type}`).text(message).show();

    setTimeout(() => {
      $("#alert").fadeOut("slow", function () {
        $(this).removeClass().text("").hide();
      });
    }, 2000);
  };

  const loadTeacher = () => {
    $.ajax({
      type: "POST",
      url: "../backend/api/web/admin.php",
      data: { requestType: "GetTeachers" },
      success: function (response) {
        try {
          // Handle response if it's already an object or a string
          let res = typeof response === 'string' ? JSON.parse(response) : response;
          
          if (res.status === "success") {
            let tbody = $("#teacher-table-tbody");
            tbody.empty();

            if (res.data && res.data.length > 0) {
              res.data.forEach((teacher) => {
                const status = teacher.is_active == 1 ? "Active" : "Inactive";
                tbody.append(`
                  <tr>
                    <td>${teacher.name}</td>
                    <td>${teacher.email}</td>
                    <td>${teacher.grade_level ?? "N/A"}</td>
                    <td>${status}</td>
                    <td>
                        <a href="assign_sections.php?tId=${teacher.id}" class="btn btn-sm btn-primary" style="font-size: 12px">Assign Section</a>
                    </td>
                  </tr>
                `);
              });
            } else {
               tbody.append('<tr><td colspan="5" class="text-center">No teachers found.</td></tr>');
            }
          } else {
            showAlert("alert-danger", "Failed to load teachers.");
          }
        } catch (err) {
          console.error("JSON parse error:", err);
          showAlert("alert-danger", "Invalid server response.");
        }
      },
      error: function () {
        showAlert("alert-danger", "Error fetching teacher data.");
      },
    });
  };

  // --- FIX: PASSWORD TOGGLE LOGIC ---
  $("#togglePassword").click(function () {
    const passwordInput = $("#teacher-password");
    const icon = $(this).find("i");

    if (passwordInput.attr("type") === "password") {
      passwordInput.attr("type", "text"); // Show Password
      icon.removeClass("bi-eye").addClass("bi-eye-slash");
    } else {
      passwordInput.attr("type", "password"); // Hide Password
      icon.removeClass("bi-eye-slash").addClass("bi-eye");
    }
  });

  // --- FIX: GENERATE PASSWORD LOGIC ---
  $("#generatePasswordBtn").click(function () {
    const length = 12;
    const charset = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()_+";
    let password = "";
    for (let i = 0, n = charset.length; i < length; ++i) {
      password += charset.charAt(Math.floor(Math.random() * n));
    }
    
    // Set value and show it immediately so user can see what was generated
    $("#teacher-password").val(password);
    $("#teacher-password").attr("type", "text");
    $("#togglePassword i").removeClass("bi-eye").addClass("bi-eye-slash");
  });

  // --- FORM SUBMISSION ---
  $("#insert-teacher-form").submit(function (e) {
    e.preventDefault();

    const name = $("#teacher-name").val().trim();
    const email = $("#teacher-email").val().trim();
    const password = $("#teacher-password").val();

    if (!name || !email || !password) {
      showAlert("alert-warning", "All fields are required.");
      return;
    }

    $.ajax({
      type: "POST",
      url: "../backend/api/web/admin.php",
      data: {
        requestType: "InsertTeacher",
        name,
        email,
        password,
      },
      success: function (response) {
        try {
          let res = typeof response === 'string' ? JSON.parse(response) : response;
          
          if (res.status === "success") {
            showAlert("alert-success", res.message);
            loadTeacher();
            $("#insert-teacher-form")[0].reset();
            
            // Reset password field visibility to hidden after save
            $("#teacher-password").attr("type", "password");
            $("#togglePassword i").removeClass("bi-eye-slash").addClass("bi-eye");

            // Close Modal correctly using Bootstrap 5 API
            const modalEl = document.getElementById("insertTeacherModal");
            const modal = bootstrap.Modal.getInstance(modalEl);
            if (modal) {
                modal.hide();
            } else {
                // Fallback if getInstance fails (sometimes happens if modal wasn't initialized via JS)
                $(modalEl).modal('hide'); 
            }
            
          } else {
            showAlert("alert-danger", res.message || "Insert failed.");
          }
        } catch (err) {
          console.error("JSON parse error:", err);
          showAlert("alert-danger", "Unexpected response from server.");
        }
      },
      error: function () {
        showAlert("alert-danger", "Failed to submit data.");
      },
    });
  });

  // --- CSV IMPORT ---
  $("#CSV").on("change", function (e) {
    const file = e.target.files[0];

    if (!file) {
      alert("No file selected.");
      return;
    }

    const reader = new FileReader();

    reader.onload = function (e) {
      const text = e.target.result;
      const rows = text.trim().split("\n");
      let teacherAccs = [];

      rows.slice(1).forEach((row) => {
        const columns = row.split(",");
        if(columns.length >= 3) {
            const accName = columns[0]?.trim();
            const accEmail = columns[1]?.trim();
            const accPassword = columns[2]?.trim();

            const obj = {
              name: accName,
              email: accEmail,
              password: accPassword,
            };
            teacherAccs.push(obj);
        }
      });

      console.log(teacherAccs);

      $.ajax({
        type: "POST",
        url: "../backend/api/web/admin.php",
        data: {
          requestType: "ImportTeachers",
          teacherAccs: JSON.stringify(teacherAccs),
        },
        success: function (response) {
          try {
              let res = typeof response === 'string' ? JSON.parse(response) : response;

              if (res.status === "success") {
                showAlert("alert-success", res.message);
                loadTeacher();
              } else {
                showAlert("alert-danger", res.message);
              }
          } catch(e) {
              console.error(e);
          }
        },
      });
    };

    reader.readAsText(file);
  });

  // Initial Load
  loadTeacher();
});