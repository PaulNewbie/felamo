$(document).ready(function () {
  const aralin_id = $("#hidden_aralin_id").val();

  const showAlert = (type, message) => {
    $("#alert").removeClass().addClass(`alert ${type}`).text(message).show();

    setTimeout(() => {
      $("#alert").fadeOut("slow", function () {
        $(this).removeClass().text("").hide();
      });
    }, 2000);
  };

  const loadWatchHistory = () => {
    $.ajax({
      type: "POST",
      url: "../backend/api/web/aralin.php",
      data: { requestType: "GetWatchHistory", aralin_id },
      success: function (response) {
        let res = JSON.parse(response);

        if (res.status === "success") {
          const history = res.data;
          let rowsHtml = "";

          history.forEach((data) => {
            rowsHtml += `
                <tr>
                  <td>${data.first_name} ${data.last_name}</td>
                  <td>${data.lrn}</td>
                  <td>${data.completed_at}</td>
                </tr>
              `;
          });

          $("#watch-history-table-tbody").html(rowsHtml);
        } else {
          $("#watch-history-table-tbody").html(
            `<tr><td colspan="5">Failed to load history: ${res.message}</td></tr>`
          );
        }
      },
      error: function () {
        $("#watch-history-table-tbody").html(
          `<tr><td colspan="5">Server error while loading aralin.</td></tr>`
        );
      },
    });
  };

  loadWatchHistory();
});
