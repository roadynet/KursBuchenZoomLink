const courseList = document.querySelector("#courseList");
const form = document.querySelector("#courseForm");
const search = document.querySelector("#search");
const statusFilter = document.querySelector("#statusFilter");
const resetDemo = document.querySelector("#resetDemo");
const totalCourses = document.querySelector("#totalCourses");
const totalBookings = document.querySelector("#totalBookings");
const openSeats = document.querySelector("#openSeats");

let courses = [];

async function requestJson(url, options = {}) {
  const response = await fetch(url, {
    ...options,
    headers: {
      "Content-Type": "application/json",
      ...(options.headers || {})
    }
  });

  if (!response.ok) {
    const contentType = response.headers.get("Content-Type") || "";
    if (contentType.includes("application/json")) {
      const payload = await response.json();
      throw new Error(payload.error || `HTTP ${response.status}`);
    }

    const message = await response.text();
    throw new Error(message || `HTTP ${response.status}`);
  }

  if (response.status === 204) {
    return null;
  }

  return response.json();
}

function formatDate(value) {
  return new Intl.DateTimeFormat("de-DE", {
    day: "2-digit",
    month: "2-digit",
    year: "numeric"
  }).format(new Date(`${value}T12:00:00`));
}

function renderSummary() {
  const bookings = courses.reduce((sum, course) => sum + course.booked, 0);
  const seats = courses.reduce((sum, course) => sum + Math.max(course.capacity - course.booked, 0), 0);

  totalCourses.textContent = courses.length;
  totalBookings.textContent = bookings;
  openSeats.textContent = seats;
}

function getVisibleCourses() {
  const term = search.value.trim().toLowerCase();
  const status = statusFilter.value;

  return courses
    .filter((course) => status === "alle" || course.status === status)
    .filter((course) => `${course.title} ${course.teacher}`.toLowerCase().includes(term))
    .sort((a, b) => `${a.date}${a.time}`.localeCompare(`${b.date}${b.time}`));
}

function renderCourses() {
  const visibleCourses = getVisibleCourses();
  courseList.innerHTML = "";

  if (visibleCourses.length === 0) {
    courseList.innerHTML = '<div class="empty-state">Keine Kurse gefunden.</div>';
    renderSummary();
    return;
  }

  for (const course of visibleCourses) {
    const card = document.createElement("article");
    card.className = "course-card";
    card.innerHTML = `
      <div class="course-main">
        <div class="course-heading">
          <span class="badge" data-status="${course.status}">${course.status}</span>
          <h3>${escapeHtml(course.title)}</h3>
        </div>
        <p class="course-meta">
          <span>${escapeHtml(course.teacher)}</span>
          <span>${formatDate(course.date)} um ${course.time} Uhr</span>
          <span>${course.booked}/${course.capacity} Plaetze belegt</span>
        </p>
        <div class="course-link-row">
          <a class="zoom-link" href="${escapeHtml(course.zoomLink)}" target="_blank" rel="noreferrer">Zoom-Link oeffnen</a>
          <span class="zoom-chip">${zoomLabel(course)}</span>
        </div>
        ${renderBookings(course)}
      </div>
      <div class="course-side">
        ${renderBookingForm(course)}
        <button class="danger-button" type="button" data-delete-id="${escapeHtml(course.id)}">Loeschen</button>
      </div>
    `;
    courseList.appendChild(card);
  }

  renderSummary();
}

function renderBookings(course) {
  if (!course.bookings || course.bookings.length === 0) {
    return '<div class="booking-empty">Noch keine Buchungen.</div>';
  }

  const items = course.bookings
    .map((booking) => `
      <li>
        <div class="booking-person">
          <strong>${escapeHtml(booking.name)}</strong>
          <span class="payment-chip" data-status="${escapeHtml(booking.paymentStatus || "open")}">${paymentLabel(booking)}</span>
        </div>
        <span>${escapeHtml(booking.email)}</span>
        ${booking.note ? `<em>${escapeHtml(booking.note)}</em>` : ""}
        ${renderPaymentAction(booking)}
      </li>
    `)
    .join("");

  return `
    <div class="booking-block">
      <h4>Buchungen</h4>
      <ul class="booking-list">${items}</ul>
    </div>
  `;
}

function paymentLabel(booking) {
  if (booking.confirmationSentAt) {
    return "Bestaetigt";
  }

  if (booking.paymentStatus === "paid") {
    return "Bezahlt";
  }

  return "Offen";
}

function renderPaymentAction(booking) {
  if (booking.confirmationSentAt) {
    return `<small>Mail versendet am ${escapeHtml(formatDate(booking.confirmationSentAt.slice(0, 10)))}</small>`;
  }

  if (booking.paymentStatus === "paid") {
    return `<button class="payment-button" type="button" data-confirm-booking-id="${escapeHtml(booking.id)}">Bestaetigung senden</button>`;
  }

  return `<button class="payment-button" type="button" data-confirm-booking-id="${escapeHtml(booking.id)}">Bezahlt bestaetigen</button>`;
}

function zoomLabel(course) {
  if (course.zoomProvider === "placeholder") {
    return "Zoom-Platzhalter";
  }

  if (course.zoomProvider === "api") {
    return "Zoom API";
  }

  return course.zoomStatus || "Zoom";
}

function renderBookingForm(course) {
  if (course.booked >= course.capacity) {
    return '<div class="booking-closed">Ausgebucht</div>';
  }

  return `
    <form class="booking-form" data-course-id="${escapeHtml(course.id)}">
      <label>
        Name
        <input name="name" type="text" required placeholder="Teilnehmende Person">
      </label>
      <label>
        E-Mail
        <input name="email" type="email" required placeholder="name@example.com">
      </label>
      <label>
        Notiz
        <input name="note" type="text" placeholder="Optional">
      </label>
      <button class="secondary-button" type="submit">Buchung erstellen</button>
    </form>
  `;
}

function escapeHtml(value) {
  return String(value)
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;")
    .replaceAll("'", "&#039;");
}

async function loadCourses() {
  courses = await requestJson("/api/courses");
  renderCourses();
}

form.addEventListener("submit", async (event) => {
  event.preventDefault();
  const data = new FormData(form);
  const payload = Object.fromEntries(data.entries());
  payload.capacity = Number(payload.capacity);
  payload.booked = Number(payload.booked);

  try {
    await requestJson("/api/courses", {
      method: "POST",
      body: JSON.stringify(payload)
    });

    form.reset();
    await loadCourses();
  } catch (error) {
    window.alert(error.message);
  }
});

courseList.addEventListener("submit", async (event) => {
  const bookingForm = event.target.closest(".booking-form");
  if (!bookingForm) {
    return;
  }

  event.preventDefault();
  const data = new FormData(bookingForm);
  const payload = Object.fromEntries(data.entries());

  try {
    await requestJson(`/api/courses/${bookingForm.dataset.courseId}/bookings`, {
      method: "POST",
      body: JSON.stringify(payload)
    });

    await loadCourses();
  } catch (error) {
    window.alert(error.message);
  }
});

courseList.addEventListener("click", async (event) => {
  const confirmButton = event.target.closest("button[data-confirm-booking-id]");
  if (confirmButton) {
    confirmButton.disabled = true;
    confirmButton.textContent = "Sende...";

    try {
      await requestJson(`/api/bookings/${confirmButton.dataset.confirmBookingId}/confirm-payment`, {
        method: "POST"
      });
      await loadCourses();
    } catch (error) {
      window.alert(error.message);
      confirmButton.disabled = false;
      confirmButton.textContent = "Bezahlt bestaetigen";
    }

    return;
  }

  const button = event.target.closest("button[data-delete-id]");
  if (!button) {
    return;
  }

  await requestJson(`/api/courses/${button.dataset.deleteId}`, { method: "DELETE" });
  await loadCourses();
});

resetDemo.addEventListener("click", async () => {
  await requestJson("/api/courses/reset", { method: "POST" });
  await loadCourses();
});

search.addEventListener("input", renderCourses);
statusFilter.addEventListener("change", renderCourses);
loadCourses().catch((error) => {
  courseList.innerHTML = `<div class="empty-state">Fehler beim Laden: ${escapeHtml(error.message)}</div>`;
});
