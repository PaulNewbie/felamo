$(document).ready(function () {
  $("#LoginForm").submit(function (e) {
    // 1. STOP everything else
    e.preventDefault();
    e.stopPropagation();

    $("#login-error").addClass("d-none");
    $("#btn-login").prop("disabled", true); // Optional: Disable button to prevent double clicks

    let email = $("#email").val();
    let password = $("#password").val();

    $.ajax({
      type: "POST",
      url: "backend/api/web/auth.php",
      data: { requestType: "Login", email: email, password: password },
      dataType: "json", // Force jQuery to expect JSON
      success: function (response) {
        console.log("Server response:", response);

        // If jQuery parsed it automatically, response is an object
        // If not, we parse it manually
        let res = typeof response === "string" ? JSON.parse(response) : response;

        if (res.status === "success") {
          // SUCCESS!
          console.log("Login success! Redirecting...");
          window.location.href = "pages/home.php";
        } else {
          // ERROR
          $("#login-error").text(res.message || "Invalid credentials").removeClass("d-none");
          $("#btn-login").prop("disabled", false);
        }
      },
      error: function (xhr, status, error) {
        console.error("AJAX Error:", xhr.responseText);
        
        // Even if it fails, sometimes it's just a JSON parse error but the login worked.
        // Let's try to parse the text response manually just in case.
        try {
            let cleanResponse = JSON.parse(xhr.responseText);
            if(cleanResponse.status === "success") {
                window.location.href = "pages/home.php";
                return;
            }
        } catch(e) {}

        $("#login-error").text("System Error. Check console for details.").removeClass("d-none");
        $("#btn-login").prop("disabled", false);
      },
    });

    // 2. Extra safety return
    return false;
  });

  // Keep your other code (Forgot Password, etc.) below...
});