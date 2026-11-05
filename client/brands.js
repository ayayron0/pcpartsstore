const API_URL = "http://localhost/pcpartsstore/brands";

let currentPage = 1;
const limit = 5;
let currentNameFilter = "";

// Helper to display error or success messages in the UI
function showFeedback(message, isError = true, detailsObj = null) {
  const box = document.getElementById("feedbackBox");
  box.style.display = "block";
  box.className = isError ? "box error-box" : "box success-box";

  let html = `<strong>${message}</strong>`;

  if (detailsObj) {
    html += "<ul>";

    for (const [field, issue] of Object.entries(detailsObj)) {
      html += `<li><strong>${field}:</strong> ${issue}</li>`;
    }

    html += "</ul>";
  }

  box.innerHTML = html;
}

// Fetch the paginated and filtered brands collection
async function loadBrands() {
  try {
    const url = new URL(API_URL);
    url.searchParams.set("page", currentPage);
    url.searchParams.set("limit", limit);
    url.searchParams.set("sort", document.getElementById("sortBy").value);
    url.searchParams.set(
      "direction",
      document.getElementById("sortDirection").value,
    );

    if (currentNameFilter !== "") {
      url.searchParams.set("name", currentNameFilter);
    }

    const response = await fetch(url, {
      method: "GET",
      headers: { Accept: "application/json" },
    });

    const result = await response.json();

    if (!response.ok) {
      showFeedback(
        result.error || "Failed to load brands.",
        true,
        result.details,
      );
      return;
    }

    // Render table rows
    const tbody = document.getElementById("brandTableBody");
    tbody.innerHTML = "";

    result.data.forEach((brand) => {
      const row = `<tr>
        <td>${brand.id}</td>
        <td>${brand.name}</td>
        <td>
          <button onclick="changeBrandName(${brand.id})">Change Name</button>
          <button onclick="deleteBrand(${brand.id})">Delete</button>
        </td>
      </tr>`;

      tbody.innerHTML += row;
    });

    // Update pagination controls
    const meta = result.metadata;
    const totalPages = meta.total_pages || 1;

    document.getElementById("pageInfo").innerText =
      `Page ${meta.current_page} of ${totalPages} ` +
      `(Total: ${meta.total_items})`;

    document.getElementById("prevBtn").disabled = meta.current_page <= 1;
    document.getElementById("nextBtn").disabled =
      meta.current_page >= totalPages;
  } catch (error) {
    showFeedback("Network error: Could not reach the API server.", true);
  }
}

// Pagination and filter handlers
function changePage(direction) {
  currentPage += direction;
  loadBrands();
}

function applyFilter() {
  currentNameFilter = document.getElementById("filter").value.trim();
  currentPage = 1;
  loadBrands();
}

function resetFilter() {
  document.getElementById("filter").value = "";
  currentNameFilter = "";
  currentPage = 1;
  loadBrands();
}

// Sorting handler
function applySort() {
  currentPage = 1;
  loadBrands();
}

// PATCH a brand's name
async function changeBrandName(id) {
  const nameInput = prompt("Enter the new brand name:");

  if (nameInput === null) {
    return;
  }

  const name = nameInput.trim();

  if (name.length < 2 || name.length > 255) {
    showFeedback("Brand name must be between 2 and 255 characters.", true);
    return;
  }

  try {
    const response = await fetch(`${API_URL}/${id}`, {
      method: "PATCH",
      headers: {
        Accept: "application/json",
        "Content-Type": "application/json",
      },
      body: JSON.stringify({ name: name }),
    });

    const result = await response.json();

    if (!response.ok) {
      showFeedback(
        result.error || "Could not update the brand name.",
        true,
        result.details,
      );
      return;
    }

    showFeedback(result.message || "Brand name updated successfully.", false);
    loadBrands();
  } catch (error) {
    showFeedback("Network failure while updating the brand.", true);
  }
}

// Delete a brand
async function deleteBrand(id) {
  try {
    const response = await fetch(`${API_URL}/${id}`, {
      method: "DELETE",
      headers: { Accept: "application/json" },
    });

    const result = await response.json();

    if (!response.ok) {
      showFeedback(
        result.error || "Could not delete the brand.",
        true,
        result.details,
      );
      return;
    }

    showFeedback(result.message || "Brand deleted successfully.", false);
    loadBrands();
  } catch (error) {
    showFeedback("Network failure while deleting the brand.", true);
  }
}

// Create a brand with POST
const brandForm = document.getElementById("addBrandForm");

brandForm.addEventListener("submit", async function (event) {
  event.preventDefault();

  const name = document.getElementById("brandName").value.trim();
  const clientErrors = {};

  if (name.length === 0) {
    clientErrors.name = "Please enter a brand name.";
  }

  if (Object.keys(clientErrors).length > 0) {
    showFeedback("Client-side validation failed.", true, clientErrors);
    return;
  }

  try {
    const response = await fetch(API_URL, {
      method: "POST",
      headers: {
        Accept: "application/json",
        "Content-Type": "application/json",
      },
      body: JSON.stringify({ name: name }),
    });

    const result = await response.json();

    if (!response.ok) {
      showFeedback(
        `Server Error (${response.status}): ${result.error}`,
        true,
        result.details || null,
      );
      return;
    }

    showFeedback(`Success! Brand created with ID: ${result.id}`, false);
    brandForm.reset();
    loadBrands();
  } catch (error) {
    showFeedback("Network failure while creating the brand.", true);
  }
});

// Update a brand with PUT
const updateBrandForm = document.getElementById("updateBrandForm");

updateBrandForm.addEventListener("submit", async function (event) {
  event.preventDefault();

  const idText = document.getElementById("updateBrandId").value.trim();
  const name = document.getElementById("updateBrandName").value.trim();
  const id = Number(idText);
  const clientErrors = {};

  if (idText === "" || !Number.isInteger(id) || id < 1) {
    clientErrors.id = "Brand ID must be a positive integer.";
  }

  if (name.length === 0) {
    clientErrors.name = "Please enter a brand name.";
  }

  if (Object.keys(clientErrors).length > 0) {
    showFeedback("Client-side validation failed.", true, clientErrors);
    return;
  }

  try {
    const response = await fetch(`${API_URL}/${id}`, {
      method: "PUT",
      headers: {
        Accept: "application/json",
        "Content-Type": "application/json",
      },
      body: JSON.stringify({ name: name }),
    });

    const result = await response.json();

    if (!response.ok) {
      showFeedback(
        `Server Error (${response.status}): ${result.error}`,
        true,
        result.details || null,
      );
      return;
    }

    showFeedback("Brand updated successfully.", false);
    updateBrandForm.reset();
    loadBrands();
  } catch (error) {
    showFeedback("Network failure while updating the brand.", true);
  }
});

// Load the initial page
loadBrands();
