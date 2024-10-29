document.addEventListener("DOMContentLoaded", function () {
  if (typeof FullCalendar !== "undefined") {
    // Ensure FullCalendar is loaded
    var calendarEl = document.getElementById("calendar");

    var calendar = new FullCalendar.Calendar(calendarEl, {
      initialView: "dayGridMonth",
      headerToolbar: {
        left: "prev,next today",
        center: "title",
        right: "dayGridMonth,timeGridWeek,timeGridDay",
      },
      events: vehicleBookingsData.bookings, // Use the localized PHP data
      eventClick: function (info) {
        // Display modal and populate with event data
        var modal = document.getElementById("eventModal");
        var modalTitle = document.getElementById("modalTitle");
        var modalName = document.getElementById("modalName");
        var modalRoute = document.getElementById("modalRoute");
        var modalAdults = document.getElementById("modalAdults");
        var modalChildren = document.getElementById("modalChildren");
        var modalTravelMethod = document.getElementById("modalTravelMethod");
        var modalTotalPrice = document.getElementById("modalTotalPrice");
        var modalSurfaceArea = document.getElementById("modalSurfaceArea");
        var modalVehicleType = document.getElementById("modalVehicleType");
        var modalVehicleBrand = document.getElementById("modalVehicleBrand");
        var modalVehicleModel = document.getElementById("modalVehicleModel");
        var modalPlateNumber = document.getElementById("modalPlateNumber");
        // var modalDescription = document.getElementById("modalDescription");

        modalTitle.textContent = info.event.title;
        modalName.textContent = info.event.extendedProps.name;
        // modalRoute.textContent = "Route: " + info.event.extendedProps.route;
        modalAdults.textContent = "Adults: " + info.event.extendedProps.adults;
        modalChildren.textContent =
          "Children: " + info.event.extendedProps.children;
        modalTravelMethod.textContent =
          "Travel Method: " + info.event.extendedProps.travel_method;
        modalTotalPrice.textContent =
          "Total Price: " + info.event.extendedProps.total_price;
        modalSurfaceArea.textContent =
          "Surface Area (m²): " + info.event.extendedProps.surface_area;
        modalVehicleType.textContent =
          "Vehicle Type: " + info.event.extendedProps.vehicle_type;
        modalVehicleBrand.textContent =
          "Vehicle Brand: " + info.event.extendedProps.vehicle_brand;
        modalVehicleModel.textContent =
          "Vehicle Model: " + info.event.extendedProps.vehicle_model;
        modalPlateNumber.textContent =
          "Plate Number: " + info.event.extendedProps.plate_number;

        // modalDescription.textContent = info.event.extendedProps.description;

        // Show the modal
        modal.style.display = "flex";
      },
    });

    calendar.render();

    // Close modal when the user clicks the 'X' button or outside the modal
    var modal = document.getElementById("eventModal");
    var span = document.getElementsByClassName("close")[0];

    // When the user clicks on <span> (x), close the modal
    span.onclick = function () {
      modal.style.display = "none";
    };

    // When the user clicks anywhere outside of the modal, close it
    window.onclick = function (event) {
      if (event.target == modal) {
        modal.style.display = "none";
      }
    };
  } else {
    console.error("FullCalendar is not defined.");
  }
});
