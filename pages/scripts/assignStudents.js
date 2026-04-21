$(document).ready(function () {
  const teacher_id = $("#hidden_teacher_id").val();

  const showAlert = (type, message) => {
    $("#alert").removeClass().addClass(`alert ${type}`).text(message).show();

    setTimeout(() => {
      $("#alert").fadeOut("slow", function () {
        $(this).removeClass().text("").hide();
      });
    }, 2000);
  };

  const loadAssignedStudents = () => {
    $.ajax({
      type: "POST",
      url: "../backend/api/web/student_teacher_assignment.php",
      data: { requestType: "GetAssignedStudents", teacher_id },
      success: function (response) {
        let res = JSON.parse(response);
        console.log(res);

        if (res.status === "success") {
          let rows = "";

          res.data.forEach((student) => {
            const fullName = `${student.first_name ?? ""} ${
              student.middle_name ?? ""
            } ${student.last_name ?? ""}`.trim();

            rows += `
              <tr>
                <td>${student.student_lrn}</td>
                <td>${fullName == "" ? "No account found" : fullName}</td>
                <td></td>
              </tr>
            `;
          });

          $("#student-table-tbody").html(rows);
        } else {
          $("#student-table-tbody").html(`
            <tr><td colspan="3" class="text-center text-danger">Failed to load students</td></tr>
          `);
        }
      },
      error: function () {
        $("#student-table-tbody").html(`
          <tr><td colspan="3" class="text-center text-danger">Error connecting to server</td></tr>
        `);
      },
    });
  };

  $("#assign-student-form").submit(function (e) {
    e.preventDefault();

    let lrn = $("#assign_lrn").val();

    $.ajax({
      type: "POST",
      url: "../backend/api/web/student_teacher_assignment.php",
      data: { requestType: "AssignStudent", lrn, teacher_id },
      success: function (response) {
        let res = JSON.parse(response);

        if (res.status === "success") {
          showAlert("alert-success", res.message);

          loadAssignedStudents();

          const modal = bootstrap.Modal.getInstance(
            document.getElementById("insertStudentModal")
          );
          if (modal) modal.hide();
        } else {
          showAlert("alert-danger", res.message);
        }
      },
    });
  });

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
      let lrnArray = [];

      rows.slice(1).forEach((row, index) => {
        const columns = row.split(",");
        const lrn = columns[0]?.trim();
        lrnArray.push(lrn);
      });

      $.ajax({
        type: "POST",
        url: "../backend/api/web/student_teacher_assignment.php",
        data: { requestType: "ImportLRN", lrnArray, teacher_id },
        success: function (response) {
          let res = JSON.parse(response);

          if (res.status === "success") {
            showAlert("alert-success", res.message);
            loadAssignedStudents();
          } else {
            showAlert("alert-danger", res.message);
          }
        },
      });
    };

    reader.readAsText(file);
  });

  loadAssignedStudents();
});
