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

  const loadAssignedSections = () => {
    $.ajax({
      type: "POST",
      url: "../backend/api/web/section_assignment.php",
      data: { requestType: "GetAssignedSections", teacher_id },
      success: function (response) {
        let res = JSON.parse(response);
        console.log(res);

        if (res.status === "success") {
          let rows = "";

          res.data.forEach((section) => {
            rows += `
                <tr>
                  <td>${section.section_name}</td>
                  <td>
                    <a href="assign_students-v2.php?sectionId=${
                          section.id
                        }" class="btn btn-sm btn-primary" style="font-size: 12px">Assign Students</a>
                  </td>
                </tr>
              `;
          });

          $("#section-table-tbody").html(rows);
        } else {
          $("#section-table-tbody").html(`
              <tr><td colspan="2" class="text-center text-danger">Failed to load sections</td></tr>
            `);
        }
      },
      error: function () {
        $("#section-table-tbody").html(`
            <tr><td colspan="2" class="text-center text-danger">Error connecting to server</td></tr>
          `);
      },
    });
  };

  $("#assign-section-form").submit(function (e) {
    e.preventDefault();

    let section_name = $("#section_name").val().trim();

    if (!section_name) {
      showAlert("alert-danger", "Section name is required.");
      return;
    }

    $.ajax({
      type: "POST",
      url: "../backend/api/web/section_assignment.php",
      data: {
        requestType: "AssignSection",
        teacher_id,
        section_name,
      },
      success: function (response) {
        let res = JSON.parse(response);

        if (res.status === "success") {
          showAlert("alert-success", res.message);

          loadAssignedSections();

          const modal = bootstrap.Modal.getInstance(
            document.getElementById("assignSectionModal")
          );
          if (modal) modal.hide();

          $("#assign-section-form")[0].reset();
        } else {
          showAlert("alert-danger", res.message);
        }
      },
    });
  });

  loadAssignedSections();
});
