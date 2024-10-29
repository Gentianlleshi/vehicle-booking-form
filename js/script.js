document.addEventListener("DOMContentLoaded", function () {
  flatpickr("#date-picker", {
    dateFormat: "Y-m-d",
    minDate: "today",
    enableTime: false,
    // disable: [
    //   function (date) {
    //     return date.getDay() === 0 || date.getDay() === 6;
    //   },
    // ],
  });
});
