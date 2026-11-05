const API_URL = "http://localhost/pcpartsstore/parts";
const BRANDS_URL = "http://localhost/pcpartsstore/brands";

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

// Load brand options for the create and update forms
async function loadBrands() {
  try {
    const response = await fetch(`${BRANDS_URL}?page=1&limit=50`, {
      method: "GET",
      headers: { Accept: "application/json" },
    });

    const result = await response.json();

    if (!response.ok) {
      showFeedback(
        result.error || "Could not load brands.",
        true,
        result.details,
      );
      return;
    }

    const brandSelects = [
      document.getElementById("partBrandId"),
      document.getElementById("updatePartBrandId"),
    ];

    brandSelects.forEach((select) => {
      select.innerHTML = '<option value="">Select a brand</option>';

      result.data.forEach((brand) => {
        const option = document.createElement("option");
        option.value = brand.id;
        option.textContent = brand.name;
        select.appendChild(option);
      });
    });
  } catch (error) {
    showFeedback("Network failure while loading brands.", true);
  }
}

// Fetch the paginated and filtered parts collection
async function loadParts() {
  try {
    const url = new URL(API_URL);
    url.searchParams.set("page", currentPage);
    url.searchParams.set("limit", limit);

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
        result.error || "Failed to load parts.",
        true,
        result.details,
      );
      return;
    }

    // Render table rows
    const tbody = document.getElementById("partTableBody");
    tbody.innerHTML = "";

    result.data.forEach((part) => {
      const row = `<tr>
        <td>${part.id}</td>
        <td>${part.name}</td>
        <td>${part.category}</td>
        <td>${part.brand_id}</td>
        <td>${part.price}</td>
        <td>
          <button onclick="changePartPrice(${part.id})">Change Price</button>
          <button onclick="deletePart(${part.id})">Delete</button>
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
  loadParts();
}

function applyFilter() {
  currentNameFilter = document.getElementById("filter").value.trim();
  currentPage = 1;
  loadParts();
}

function resetFilter() {
  document.getElementById("filter").value = "";
  currentNameFilter = "";
  currentPage = 1;
  loadParts();
}

// Load a part into the PUT update form
async function editPart(id) {
  try {
    const response = await fetch(`${API_URL}/${id}`, {
      method: "GET",
      headers: { Accept: "application/json" },
    });

    const part = await response.json();

    if (!response.ok) {
      showFeedback(
        part.error || "Could not load the part.",
        true,
        part.details,
      );
      return;
    }

    document.getElementById("updatePartId").value = part.id;
    document.getElementById("updatePartName").value = part.name;
    document.getElementById("updatePartCategory").value = part.category;
    document.getElementById("updatePartBrandId").value = part.brand_id;
    document.getElementById("updatePartPrice").value = part.price;

    document
      .getElementById("updatePartForm")
      .scrollIntoView({ behavior: "smooth" });
  } catch (error) {
    showFeedback("Network failure while loading the part.", true);
  }
}

// Update a part with PUT
const updatePartForm = document.getElementById("updatePartForm");

updatePartForm.addEventListener("submit", async function (event) {
  event.preventDefault();

  const id = document.getElementById("updatePartId").value;
  const name = document.getElementById("updatePartName").value.trim();
  const category = document.getElementById("updatePartCategory").value.trim();
  const brandIdText = document.getElementById("updatePartBrandId").value.trim();
  const priceText = document.getElementById("updatePartPrice").value.trim();

  const brandId = Number(brandIdText);
  const price = Number(priceText);

  // Client-side input validation
  const clientErrors = {};

  if (name.length < 2 || name.length > 255) {
    clientErrors.name = "Name must be between 2 and 255 characters.";
  }

  if (category.length < 2 || category.length > 255) {
    clientErrors.category = "Category must be between 2 and 255 characters.";
  }

  if (brandIdText === "" || !Number.isInteger(brandId) || brandId < 1) {
    clientErrors.brand_id = "Brand ID must be a positive integer.";
  }

  if (priceText === "" || !Number.isFinite(price) || price < 0) {
    clientErrors.price = "Price must be a number of 0 or more.";
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
      body: JSON.stringify({
        name: name,
        category: category,
        brand_id: brandId,
        price: price,
      }),
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

    showFeedback("Part updated successfully.", false);
    updatePartForm.reset();
    loadParts();
  } catch (error) {
    showFeedback("Network failure while updating the part.", true);
  }
});

// Delete a part
async function deletePart(id) {
  try {
    const response = await fetch(`${API_URL}/${id}`, {
      method: "DELETE",
      headers: { Accept: "application/json" },
    });

    const result = await response.json();

    if (!response.ok) {
      showFeedback(
        result.error || "Could not delete the part.",
        true,
        result.details,
      );
      return;
    }

    showFeedback(result.message || "Part deleted successfully.", false);
    loadParts();
  } catch (error) {
    showFeedback("Network failure while deleting the part.", true);
  }
}

// PATCH a part's price
async function changePartPrice(id) {
  const priceInput = prompt("Enter the new price:");

  if (priceInput === null) {
    return;
  }

  const priceText = priceInput.trim();
  const price = Number(priceText);

  if (priceText === "" || !Number.isFinite(price) || price < 0) {
    showFeedback("Enter a valid price of 0 or more.", true);
    return;
  }

  try {
    const response = await fetch(`${API_URL}/${id}`, {
      method: "PATCH",
      headers: {
        Accept: "application/json",
        "Content-Type": "application/json",
      },
      body: JSON.stringify({ price: price }),
    });

    const result = await response.json();

    if (!response.ok) {
      showFeedback(
        result.error || "Could not update the price.",
        true,
        result.details,
      );
      return;
    }

    showFeedback(result.message || "Part price updated successfully.", false);
    loadParts();
  } catch (error) {
    showFeedback("Network failure while updating the price.", true);
  }
}

// Create a part with POST
const partForm = document.getElementById("addPartForm");

partForm.addEventListener("submit", async function (event) {
  event.preventDefault();

  const name = document.getElementById("partName").value.trim();
  const category = document.getElementById("partCategory").value.trim();
  const brandIdText = document.getElementById("partBrandId").value.trim();
  const priceText = document.getElementById("partPrice").value.trim();

  const brandId = Number(brandIdText);
  const price = Number(priceText);

  // Client-side input validation
  const clientErrors = {};

  if (name.length < 2 || name.length > 255) {
    clientErrors.name = "Name must be between 2 and 255 characters.";
  }

  if (category.length < 2 || category.length > 255) {
    clientErrors.category = "Category must be between 2 and 255 characters.";
  }

  if (brandIdText === "" || !Number.isInteger(brandId) || brandId < 1) {
    clientErrors.brand_id = "Brand ID must be a positive integer.";
  }

  if (priceText === "" || !Number.isFinite(price) || price < 0) {
    clientErrors.price = "Price must be a number of 0 or more.";
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
      body: JSON.stringify({
        name: name,
        category: category,
        brand_id: brandId,
        price: price,
      }),
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

    showFeedback(`Success! Part created with ID: ${result.id}`, false);
    partForm.reset();
    loadParts();
  } catch (error) {
    showFeedback("Network failure while creating the part.", true);
  }
});

// Load brand options before loading the parts table
loadBrands().then(loadParts);
