$(document).ready(function () {
  const auth_user_id = $("#hidden_user_id").val();

  const showAlert = (type, message) => {
    $("#alert").removeClass().addClass(`alert ${type}`).text(message).show();

    setTimeout(() => {
      $("#alert").fadeOut("slow", function () {
        $(this).removeClass().text("").hide();
      });
    }, 2000);
  };

  const loadProfile = () => {
    $.ajax({
      type: "POST",
      url: "../backend/api/web/auth.php",
      data: { requestType: "GetProfileDetails", auth_user_id },
      success: function (response) {
        let res = JSON.parse(response);

        if (res.status === "success") {
          $("#name").val(res.data.name);
          $("#email").val(res.data.email);
        } else {
          showAlert("alert-danger", "Someting went wrong!");
        }
      },
      error: function () {
        showAlert("alert-danger", "Someting went wrong!");
      },
    });
  };

  $("#editUserForm").submit(function (e) {
    e.preventDefault();

    let name = $("#name").val();
    let email = $("#email").val();
    let newPassword = $("#newPassword").val();
    let newPassword2 = $("#newPassword2").val();

    if (newPassword || newPassword2) {
      if (newPassword !== newPassword2) {
        showAlert("alert-danger", "Passwords do not match.");
        return;
      }
    } else {
      newPassword = "";
    }

    $.ajax({
      type: "POST",
      url: "../backend/api/web/auth.php",
      data: {
        requestType: "EditUser",
        auth_user_id,
        name,
        email,
        newPassword,
      },
      success: function (response) {
        let res =
          typeof response === "string" ? JSON.parse(response) : response;
        console.log(res);

        if (res.status === "success") {
          $("#editUserForm")[0].reset();
          showAlert("alert-success", res.message);
          loadProfile();
        } else {
          showAlert("alert-danger", res.message || "Update failed.");
        }
      },
      error: function () {
        showAlert("alert-danger", "Server error occurred.");
      },
    });
  });

  loadProfile();
});
