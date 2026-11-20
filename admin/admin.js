const apiBase = new URL("../", window.location.href);
const collectionLimit = 5;
const allBrandsLimit = 50;

const loginPanel = document.getElementById("loginPanel");
const dashboard = document.getElementById("adminDashboard");
const loginForm = document.getElementById("adminLoginForm");
const loginMessage = document.getElementById("loginMessage");
const pageMessage = document.getElementById("pageMessage");
const adminIdentity = document.getElementById("adminIdentity");
const logoutButton = document.getElementById("logoutButton");
const partForm = document.getElementById("partForm");
const brandForm = document.getElementById("brandForm");

let csrfToken = null;
let editingPartId = null;
let editingBrandId = null;
let allBrands = [];

const collectionState = {
  parts: {
    page: 1,
    sort: "id",
    direction: "asc",
    search: "",
    brand_id: "",
  },
  brands: { page: 1, sort: "id", direction: "asc", name: "" },
  users: { page: 1, sort: "id", direction: "asc", name: "" },
  orders: {
    page: 1,
    sort: "id",
    direction: "asc",
    user_id: "",
    status: "",
  },
};

function showLoginMessage(message) {
  loginMessage.textContent = message;
}

function showPageMessage(message, type = "info") {
  pageMessage.textContent = message;
  pageMessage.hidden = message === "";

  pageMessage.classList.remove(
    "admin-message--success",
    "admin-message--error",
  );

  if (message && type === "success") {
    pageMessage.classList.add("admin-message--success");
  } else if (message && type === "error") {
    pageMessage.classList.add("admin-message--error");
  }
}

function showSignedOut() {
  csrfToken = null;
  adminIdentity.textContent = "Not signed in";
  logoutButton.hidden = true;
  dashboard.hidden = true;
  loginPanel.hidden = false;
}

function showUserSession(user, token) {
  csrfToken = token;
  logoutButton.hidden = false;
  adminIdentity.textContent = `Signed in as ${user.email}`;

  if (user.role !== "admin") {
    window.location.replace("../store/index.html");
    return;
  }

  loginPanel.hidden = true;
  dashboard.hidden = false;
  showLoginMessage("");
  showPageMessage("");

  loadInventory();
  loadUsersAndOrders();
}

async function apiRequest(path, method = "GET", body) {
  const headers = { Accept: "application/json" };
  const isWrite = !["GET", "HEAD", "OPTIONS"].includes(method);

  if (isWrite) {
    headers["Content-Type"] = "application/json";
  }

  if (isWrite && csrfToken) {
    headers["X-CSRF-Token"] = csrfToken;
  }

  const response = await fetch(new URL(path, apiBase), {
    method,
    headers,
    credentials: "same-origin",
    cache: "no-store",
    body: body === undefined ? undefined : JSON.stringify(body),
  });

  const contentType = response.headers.get("Content-Type") || "";

  if (!contentType.includes("application/json")) {
    throw new Error("The server returned an unexpected response.");
  }

  const result = await response.json();

  if (!response.ok) {
    throw new Error(
      `HTTP ${response.status}: ${result.error || "Request failed."}`,
    );
  }

  return result;
}

function collectionUrl(resource, state, filters) {
  const params = new URLSearchParams({
    page: String(state.page),
    limit: String(collectionLimit),
    sort: state.sort,
    direction: state.direction,
  });

  for (const [key, value] of Object.entries(filters)) {
    if (value !== "" && value !== null && value !== undefined) {
      params.set(key, String(value));
    }
  }

  return `${resource}?${params.toString()}`;
}

function updatePager(prefix, metadata, state) {
  const currentPage = Number(metadata.current_page) || 1;
  const totalPages = Math.max(1, Number(metadata.total_pages) || 1);

  state.page = currentPage;

  document.getElementById(`${prefix}PageInfo`).textContent =
    `Page ${currentPage} of ${totalPages} (Total: ${metadata.total_items})`;

  document.getElementById(`${prefix}PrevBtn`).disabled = currentPage <= 1;
  document.getElementById(`${prefix}NextBtn`).disabled =
    currentPage >= totalPages;
}

function populateBrandSelect(selectId, firstOptionText) {
  const select = document.getElementById(selectId);
  select.replaceChildren(new Option(firstOptionText, ""));

  for (const brand of allBrands) {
    select.add(new Option(brand.name, String(brand.id)));
  }
}

async function loadInventory() {
  try {
    const brandParams = new URLSearchParams({
      page: "1",
      limit: String(allBrandsLimit),
      sort: "name",
      direction: "asc",
    });

    const brandOptionsResult = await apiRequest(`brands?${brandParams}`);

    if (!Array.isArray(brandOptionsResult.data)) {
      throw new Error("The server returned an unexpected brand list.");
    }

    allBrands = brandOptionsResult.data;
    populateBrandSelect("partBrandId", "Select a brand");
    populateBrandSelect("partsBrandFilter", "All brands");

    await Promise.all([loadBrandsPage(), loadPartsPage()]);
  } catch (error) {
    showPageMessage(error.message, "error");
  }
}

async function loadBrandsPage() {
  const state = collectionState.brands;

  try {
    const result = await apiRequest(
      collectionUrl("brands", state, { name: state.name }),
    );

    if (!Array.isArray(result.data)) {
      throw new Error("The server returned an unexpected brand list.");
    }

    const rows = result.data.map((brand) => {
      const row = document.createElement("tr");
      addCell(row, brand.id);
      addCell(row, brand.name);

      const actions = document.createElement("td");
      actions.append(
        makeActionButton("Edit", () => startBrandEdit(brand)),
        makeActionButton("Delete", () => deleteBrand(brand)),
      );
      row.append(actions);
      return row;
    });

    document.getElementById("brandsTableBody").replaceChildren(...rows);
    updatePager("brands", result.metadata, state);
  } catch (error) {
    showPageMessage(error.message, "error");
  }
}

async function loadPartsPage() {
  const state = collectionState.parts;

  try {
    const result = await apiRequest(
      collectionUrl("parts", state, {
        search: state.search,
        brand_id: state.brand_id,
      }),
    );

    if (!Array.isArray(result.data)) {
      throw new Error("The server returned an unexpected parts list.");
    }

    const brandNames = new Map(
      allBrands.map((brand) => [String(brand.id), brand.name]),
    );

    const rows = result.data.map((part) => {
      const row = document.createElement("tr");
      addCell(row, part.id);
      addCell(row, part.name);
      addCell(row, part.category);
      addCell(row, brandNames.get(String(part.brand_id)) ?? part.brand_id);
      addCell(row, Number(part.price).toFixed(2));

      const actions = document.createElement("td");
      actions.append(
        makeActionButton("Edit", () => startPartEdit(part)),
        makeActionButton("Delete", () => deletePart(part)),
      );
      row.append(actions);
      return row;
    });

    document.getElementById("partsTableBody").replaceChildren(...rows);
    updatePager("parts", result.metadata, state);
  } catch (error) {
    showPageMessage(error.message, "error");
  }
}

async function loadUsersAndOrders() {
  await Promise.all([loadUsersPage(), loadOrdersPage()]);
}

async function loadUsersPage() {
  const state = collectionState.users;

  try {
    const result = await apiRequest(
      collectionUrl("users", state, { name: state.name }),
    );

    if (!Array.isArray(result.data)) {
      throw new Error("The server returned an unexpected users list.");
    }

    const rows = result.data.map((user) => {
      const row = document.createElement("tr");
      addCell(row, user.id);
      addCell(row, user.first_name);
      addCell(row, user.last_name);
      addCell(row, user.email);

      const roleCell = document.createElement("td");
      const badge = document.createElement("span");
      const role = String(user.role || "customer").toLowerCase();
      badge.className = `role-badge role-${role}`;
      badge.textContent = role;
      roleCell.append(badge);
      row.append(roleCell);

      const actions = document.createElement("td");
      actions.append(
        makeActionButton("Edit", () => editUser(user)),
        makeActionButton("Delete", () => deleteUser(user)),
      );
      row.append(actions);
      return row;
    });

    document.getElementById("usersTableBody").replaceChildren(...rows);
    updatePager("users", result.metadata, state);
  } catch (error) {
    showPageMessage(error.message, "error");
  }
}

async function loadOrdersPage() {
  const state = collectionState.orders;

  try {
    const result = await apiRequest(
      collectionUrl("orders", state, {
        user_id: state.user_id,
        status: state.status,
      }),
    );

    if (!Array.isArray(result.data)) {
      throw new Error("The server returned an unexpected orders list.");
    }

    const statusClasses = {
      Processing: "status-processing",
      Shipped: "status-shipped",
      Delivered: "status-delivered",
      Cancelled: "status-cancelled",
    };

    const rows = result.data.map((order) => {
      const row = document.createElement("tr");
      addCell(row, order.id);
      addCell(row, order.user_id);

      const statusCell = document.createElement("td");
      const badge = document.createElement("span");
      badge.className = `status-badge ${statusClasses[order.status] || ""}`;
      badge.textContent = order.status || "Unknown";
      statusCell.append(badge);
      row.append(statusCell);

      const actions = document.createElement("td");
      actions.append(
        makeActionButton("Edit", () => editOrder(order)),
        makeActionButton("Delete", () => deleteOrder(order)),
      );
      row.append(actions);
      return row;
    });

    document.getElementById("ordersTableBody").replaceChildren(...rows);
    updatePager("orders", result.metadata, state);
  } catch (error) {
    showPageMessage(error.message, "error");
  }
}

function bindCollectionControls(
  prefix,
  state,
  loadPage,
  readFilters,
  resetFilters,
) {
  document
    .getElementById(`${prefix}SearchButton`)
    .addEventListener("click", () => {
      Object.assign(state, readFilters());
      state.page = 1;
      showPageMessage("");
      loadPage();
    });

  document
    .getElementById(`${prefix}ResetButton`)
    .addEventListener("click", () => {
      resetFilters();
      state.page = 1;
      state.sort = "id";
      state.direction = "asc";

      document.getElementById(`${prefix}SortBy`).value = state.sort;
      document.getElementById(`${prefix}SortDirection`).value = state.direction;

      showPageMessage("");
      loadPage();
    });

  document
    .getElementById(`${prefix}SortBy`)
    .addEventListener("change", (event) => {
      state.sort = event.target.value;
      state.page = 1;
      loadPage();
    });

  document
    .getElementById(`${prefix}SortDirection`)
    .addEventListener("change", (event) => {
      state.direction = event.target.value;
      state.page = 1;
      loadPage();
    });

  document.getElementById(`${prefix}PrevBtn`).addEventListener("click", () => {
    state.page = Math.max(1, state.page - 1);
    loadPage();
  });

  document.getElementById(`${prefix}NextBtn`).addEventListener("click", () => {
    state.page += 1;
    loadPage();
  });
}

bindCollectionControls(
  "parts",
  collectionState.parts,
  loadPartsPage,
  () => ({
    search: document.getElementById("partsSearch").value.trim(),
    brand_id: document.getElementById("partsBrandFilter").value,
  }),
  () => {
    document.getElementById("partsSearch").value = "";
    document.getElementById("partsBrandFilter").value = "";
    collectionState.parts.search = "";
    collectionState.parts.brand_id = "";
  },
);

bindCollectionControls(
  "brands",
  collectionState.brands,
  loadBrandsPage,
  () => ({ name: document.getElementById("brandsSearch").value.trim() }),
  () => {
    document.getElementById("brandsSearch").value = "";
    collectionState.brands.name = "";
  },
);

bindCollectionControls(
  "users",
  collectionState.users,
  loadUsersPage,
  () => ({ name: document.getElementById("usersSearch").value.trim() }),
  () => {
    document.getElementById("usersSearch").value = "";
    collectionState.users.name = "";
  },
);

bindCollectionControls(
  "orders",
  collectionState.orders,
  loadOrdersPage,
  () => ({
    user_id: document.getElementById("ordersUserIdFilter").value.trim(),
    status: document.getElementById("ordersStatusFilter").value,
  }),
  () => {
    document.getElementById("ordersUserIdFilter").value = "";
    document.getElementById("ordersStatusFilter").value = "";
    collectionState.orders.user_id = "";
    collectionState.orders.status = "";
  },
);

function makeActionButton(label, action) {
  const button = document.createElement("button");
  button.type = "button";
  button.className = `button table-action ${
    label === "Edit" ? "action-edit" : "action-delete"
  }`;
  button.textContent = label;
  button.addEventListener("click", action);
  return button;
}

function addCell(row, value) {
  const cell = document.createElement("td");
  cell.textContent = String(value ?? "");
  row.append(cell);
}

function startPartEdit(part) {
  editingPartId = String(part.id);
  document.getElementById("partName").value = part.name;
  document.getElementById("partCategory").value = part.category;
  document.getElementById("partBrandId").value = String(part.brand_id);
  document.getElementById("partPrice").value = part.price;
  partForm.querySelector('[type="submit"]').textContent = "Save part";
  document.getElementById("cancelPartEdit").hidden = false;
  partForm.scrollIntoView({ behavior: "smooth", block: "center" });
}

function startBrandEdit(brand) {
  editingBrandId = String(brand.id);
  document.getElementById("brandName").value = brand.name;
  brandForm.querySelector('[type="submit"]').textContent = "Save brand";
  document.getElementById("cancelBrandEdit").hidden = false;
  brandForm.scrollIntoView({ behavior: "smooth", block: "center" });
}

function resetPartForm() {
  editingPartId = null;
  partForm.reset();
  partForm.querySelector('[type="submit"]').textContent = "Add part";
  document.getElementById("cancelPartEdit").hidden = true;
}

function resetBrandForm() {
  editingBrandId = null;
  brandForm.reset();
  brandForm.querySelector('[type="submit"]').textContent = "Add brand";
  document.getElementById("cancelBrandEdit").hidden = true;
}

partForm.addEventListener("submit", async (event) => {
  event.preventDefault();

  const isEditing = editingPartId !== null;
  const part = {
    name: document.getElementById("partName").value.trim(),
    category: document.getElementById("partCategory").value.trim(),
    brand_id: Number(document.getElementById("partBrandId").value),
    price: Number(document.getElementById("partPrice").value),
  };

  const path = isEditing
    ? `parts/${encodeURIComponent(editingPartId)}`
    : "parts";

  try {
    const result = await apiRequest(path, isEditing ? "PUT" : "POST", part);

    resetPartForm();
    collectionState.parts.page = 1;
    await loadInventory();

    showPageMessage(
      result.message || (isEditing ? "Part updated." : "Part added."),
      "success",
    );
  } catch (error) {
    showPageMessage(error.message, "error");
  }
});

brandForm.addEventListener("submit", async (event) => {
  event.preventDefault();

  const isEditing = editingBrandId !== null;
  const name = document.getElementById("brandName").value.trim();
  const path = isEditing
    ? `brands/${encodeURIComponent(editingBrandId)}`
    : "brands";

  try {
    const result = await apiRequest(path, isEditing ? "PUT" : "POST", { name });

    resetBrandForm();
    collectionState.brands.page = 1;
    await loadInventory();

    showPageMessage(
      result.message || (isEditing ? "Brand updated." : "Brand added."),
      "success",
    );
  } catch (error) {
    showPageMessage(error.message, "error");
  }
});

document
  .getElementById("cancelPartEdit")
  .addEventListener("click", resetPartForm);

document
  .getElementById("cancelBrandEdit")
  .addEventListener("click", resetBrandForm);

async function deletePart(part) {
  if (!window.confirm(`Delete part "${part.name}"?`)) return;

  try {
    const result = await apiRequest(
      `parts/${encodeURIComponent(part.id)}`,
      "DELETE",
    );

    collectionState.parts.page = 1;
    await loadInventory();
    showPageMessage(result.message || "Part deleted.", "success");
  } catch (error) {
    showPageMessage(error.message, "error");
  }
}

async function deleteBrand(brand) {
  if (!window.confirm(`Delete brand "${brand.name}"?`)) return;

  try {
    const result = await apiRequest(
      `brands/${encodeURIComponent(brand.id)}`,
      "DELETE",
    );

    collectionState.brands.page = 1;
    await loadInventory();
    showPageMessage(result.message || "Brand deleted.", "success");
  } catch (error) {
    showPageMessage(error.message, "error");
  }
}

async function editUser(user) {
  const firstName = window.prompt("First name:", user.first_name);
  if (firstName === null) return;

  const lastName = window.prompt("Last name:", user.last_name);
  if (lastName === null) return;

  const email = window.prompt("Email:", user.email);
  if (email === null) return;

  try {
    const result = await apiRequest(
      `users/${encodeURIComponent(user.id)}`,
      "PATCH",
      {
        first_name: firstName.trim(),
        last_name: lastName.trim(),
        email: email.trim(),
      },
    );

    collectionState.users.page = 1;
    await loadUsersPage();
    showPageMessage(result.message || "User updated.", "success");
  } catch (error) {
    showPageMessage(error.message, "error");
  }
}

async function deleteUser(user) {
  if (!window.confirm(`Delete user "${user.email}"?`)) return;

  try {
    const result = await apiRequest(
      `users/${encodeURIComponent(user.id)}`,
      "DELETE",
    );

    collectionState.users.page = 1;
    await loadUsersPage();
    showPageMessage(result.message || "User deleted.", "success");
  } catch (error) {
    showPageMessage(error.message, "error");
  }
}

async function editOrder(order) {
  const status = window.prompt(
    "New status (Processing, Shipped, Delivered, Cancelled):",
    order.status,
  );
  if (status === null) return;

  try {
    const result = await apiRequest(
      `orders/${encodeURIComponent(order.id)}`,
      "PATCH",
      { status: status.trim() },
    );

    collectionState.orders.page = 1;
    await loadOrdersPage();
    showPageMessage(result.message || "Order updated.", "success");
  } catch (error) {
    showPageMessage(error.message, "error");
  }
}

async function deleteOrder(order) {
  if (!window.confirm(`Delete order #${order.id}?`)) return;

  try {
    const result = await apiRequest(
      `orders/${encodeURIComponent(order.id)}`,
      "DELETE",
    );

    collectionState.orders.page = 1;
    await loadOrdersPage();
    showPageMessage(result.message || "Order deleted.", "success");
  } catch (error) {
    showPageMessage(error.message, "error");
  }
}

async function restoreSession() {
  try {
    const result = await apiRequest("auth/me");
    showUserSession(result.user, result.csrf_token);
  } catch {
    showSignedOut();
  }
}

loginForm.addEventListener("submit", async (event) => {
  event.preventDefault();
  showLoginMessage("Signing in…");

  try {
    const result = await apiRequest("auth/login", "POST", {
      email: document.getElementById("loginEmail").value.trim(),
      password: document.getElementById("loginPassword").value,
    });

    showUserSession(result.user, result.csrf_token);
    loginForm.reset();
  } catch (error) {
    showLoginMessage(error.message);
  }
});

logoutButton.addEventListener("click", async () => {
  try {
    await apiRequest("auth/logout", "POST");
    showSignedOut();
    showLoginMessage("You are signed out.");
  } catch (error) {
    showPageMessage(error.message, "error");
  }
});

const adminTabButtons = document.querySelectorAll(".admin-tabs [data-panel]");
const adminPanels = document.querySelectorAll("#adminDashboard > section");

adminTabButtons.forEach((button) => {
  button.addEventListener("click", () => {
    const selectedPanel = button.dataset.panel;
    showPageMessage("");

    adminTabButtons.forEach((tab) => {
      tab.setAttribute("aria-selected", String(tab === button));
    });

    adminPanels.forEach((panel) => {
      panel.hidden = panel.id !== selectedPanel;
    });
  });
});

// Mark Parts as the initially selected tab.
adminTabButtons[0]?.setAttribute("aria-selected", "true");

restoreSession();
