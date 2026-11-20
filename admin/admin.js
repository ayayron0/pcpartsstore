const apiBase = new URL("../", window.location.href);

const loginPanel = document.getElementById("loginPanel");
const dashboard = document.getElementById("adminDashboard");
const loginForm = document.getElementById("adminLoginForm");
const loginMessage = document.getElementById("loginMessage");
const pageMessage = document.getElementById("pageMessage");
const adminIdentity = document.getElementById("adminIdentity");
const logoutButton = document.getElementById("logoutButton");

let csrfToken = null;
let editingPartId = null;
let editingBrandId = null;

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
  const headers = {
    Accept: "application/json",
  };

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

async function loadInventory() {
  const params = new URLSearchParams({ page: "1", limit: "50" });

  try {
    const [brandResult, partResult] = await Promise.all([
      apiRequest(`brands?${params}`),
      apiRequest(`parts?${params}`),
    ]);

    if (!Array.isArray(brandResult.data) || !Array.isArray(partResult.data)) {
      throw new Error("The server returned an unexpected list.");
    }

    const brandNames = new Map(
      brandResult.data.map((brand) => [String(brand.id), brand.name]),
    );

    const brandRows = brandResult.data.map((brand) => {
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

    document.getElementById("brandsTableBody").replaceChildren(...brandRows);

    const partRows = partResult.data.map((part) => {
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

    document.getElementById("partsTableBody").replaceChildren(...partRows);

    const brandSelect = document.getElementById("partBrandId");
    brandSelect.replaceChildren(new Option("Select a brand", ""));

    for (const brand of brandResult.data) {
      brandSelect.add(new Option(brand.name, brand.id));
    }

    showPageMessage("");
  } catch (error) {
    showPageMessage(error.message, "error");
  }
}

async function loadUsersAndOrders() {
  const params = new URLSearchParams({ page: "1", limit: "50" });

  try {
    const [userResult, orderResult] = await Promise.all([
      apiRequest(`users?${params}`),
      apiRequest(`orders?${params}`),
    ]);

    if (!Array.isArray(userResult.data) || !Array.isArray(orderResult.data)) {
      throw new Error("The server returned an unexpected list.");
    }

    const userRows = userResult.data.map((user) => {
      const row = document.createElement("tr");

      addCell(row, user.id);
      addCell(row, user.first_name);
      addCell(row, user.last_name);
      addCell(row, user.email);

      const roleCell = document.createElement("td");
      const roleBadge = document.createElement("span");
      const role = String(user.role || "customer").toLowerCase();

      roleBadge.className = `role-badge role-${role}`;
      roleBadge.textContent = role;
      roleCell.append(roleBadge);
      row.append(roleCell);

      const actions = document.createElement("td");
      actions.append(
        makeActionButton("Edit", () => editUser(user)),
        makeActionButton("Delete", () => deleteUser(user)),
      );
      row.append(actions);

      return row;
    });

    document.getElementById("usersTableBody").replaceChildren(...userRows);

    const orderRows = orderResult.data.map((order) => {
      const row = document.createElement("tr");
      addCell(row, order.id);
      addCell(row, order.user_id);
      //   addCell(row, order.status);

      const statusCell = document.createElement("td");
      const statusBadge = document.createElement("span");
      const status = String(order.status || "").toLowerCase();

      statusBadge.className = `status-badge status-${status}`;
      statusBadge.textContent = order.status || "Unknown";
      statusCell.append(statusBadge);
      row.append(statusCell);

      const actions = document.createElement("td");
      actions.append(
        makeActionButton("Edit", () => editOrder(order)),
        makeActionButton("Delete", () => deleteOrder(order)),
      );
      row.append(actions);

      return row;
    });

    document.getElementById("ordersTableBody").replaceChildren(...orderRows);
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

    await loadUsersAndOrders();
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

    await loadUsersAndOrders();
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

    await loadUsersAndOrders();
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

    await loadUsersAndOrders();
    showPageMessage(result.message || "Order deleted.", "success");
  } catch (error) {
    showPageMessage(error.message, "error");
  }
}

const partForm = document.getElementById("partForm");
const brandForm = document.getElementById("brandForm");

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

async function deletePart(part) {
  if (!window.confirm(`Delete part "${part.name}"?`)) return;

  try {
    const result = await apiRequest(
      `parts/${encodeURIComponent(part.id)}`,
      "DELETE",
    );
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
    await loadInventory();
    showPageMessage(result.message || "Brand deleted.", "success");
  } catch (error) {
    showPageMessage(error.message, "error");
  }
}

function addCell(row, value) {
  const cell = document.createElement("td");
  cell.textContent = String(value ?? "");
  row.append(cell);
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
