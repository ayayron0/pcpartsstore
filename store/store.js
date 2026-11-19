const apiBase = new URL("../", window.location.href);
const partsUrl = new URL("parts", apiBase);
const brandsUrl = new URL("brands", apiBase);

const grid = document.getElementById("productGrid");
const message = document.getElementById("catalogMessage");
const resultCount = document.getElementById("resultCount");
const pageInfo = document.getElementById("pageInfo");
const previousButton = document.getElementById("previousPage");
const nextButton = document.getElementById("nextPage");
const searchInput = document.getElementById("searchInput");
const brandFilter = document.getElementById("brandFilter");
const sortSelect = document.getElementById("sortSelect");
const filterForm = document.getElementById("filterForm");

const money = new Intl.NumberFormat("en-CA", {
  style: "currency",
  currency: "CAD",
});

const initialParams = new URLSearchParams(location.search);
const allowedSorts = ["id:asc", "price:asc", "price:desc", "name:asc"];

let page = Math.min(
  100,
  Math.max(1, Number.parseInt(initialParams.get("page"), 10) || 1)
);

let activeRequest;
let brands = new Map();

searchInput.value = initialParams.get("search") || "";

const initialSort =
  `${initialParams.get("sort") || "id"}:` +
  `${initialParams.get("direction") || "asc"}`;

sortSelect.value = allowedSorts.includes(initialSort)
  ? initialSort
  : "id:asc";

const initialCategory = initialParams.get("category") || "";

for (const radio of filterForm.elements.category) {
  radio.checked = radio.value === initialCategory;
}

// Ignore unknown category values.
if (!filterForm.querySelector('input[name="category"]:checked')) {
  filterForm.elements.category.value = "";
}

async function readJson(url, signal) {
  const response = await fetch(url, {
    headers: { Accept: "application/json" },
    credentials: "same-origin",
    signal,
  });

  const contentType = response.headers.get("Content-Type") || "";

  if (!contentType.includes("application/json")) {
    throw new Error("The server returned an unexpected response.");
  }

  const body = await response.json();

  if (!response.ok) {
    throw new Error(body.error || `Request failed (${response.status}).`);
  }

  return body;
}

function showMessage(text) {
  message.textContent = text;
  message.hidden = !text;
}

function createElement(tag, className, text) {
  const element = document.createElement(tag);
  if (className) element.className = className;
  if (text !== undefined) element.textContent = text;
  return element;
}

function renderPart(part) {
  const card = createElement("article", "product-card");

  const image = createElement("div", "product-image");
  const placeholder = createElement("div", "product-placeholder");
  placeholder.append(
    createElement("strong", "", part.category),
    createElement("small", "", "Image coming soon")
  );
  image.append(placeholder);

  const body = createElement("div", "product-body");
  const brandName = brands.get(String(part.brand_id));

  const category = createElement(
    "p",
    "product-category",
    [brandName, part.category].filter(Boolean).join(" · ")
  );

  const title = createElement("h3", "product-title", part.name);
  const price = createElement(
    "p",
    "product-price",
    money.format(Number(part.price))
  );
  price.append(createElement("small", "", "CAD"));

  const button = createElement("button", "button", "View details");
  button.type = "button";
  button.addEventListener("click", () => openPart(part.id));

  body.append(category, title, price, button);
  card.append(image, body);
  return card;
}

function buildQuery() {
  const [sort, direction] = sortSelect.value.split(":");
  const params = new URLSearchParams({
    page: String(page),
    limit: "12",
    sort,
    direction,
  });

  const search = searchInput.value.trim();
  const category = filterForm.elements.category.value;

  if (search) params.set("search", search);
  if (category) params.set("category", category);
  if (brandFilter.value) params.set("brand_id", brandFilter.value);

  return params;
}

async function loadParts() {
  activeRequest?.abort();
  const controller = new AbortController();
  activeRequest = controller;

  grid.setAttribute("aria-busy", "true");
  grid.replaceChildren();
  previousButton.disabled = true;
  nextButton.disabled = true;
  resultCount.textContent = "Loading components…";
  showMessage("");

  const params = buildQuery();
  const url = new URL(partsUrl);
  url.search = params.toString();

  const pageUrl = new URL(location.href);
  pageUrl.search = params.toString();
  history.replaceState(null, "", pageUrl);

  const selectedCategory = filterForm.querySelector(
    'input[name="category"]:checked'
  );

  document.getElementById("catalogTitle").textContent =
    selectedCategory?.value
      ? selectedCategory.parentElement.textContent.trim()
      : "All components";

  for (const link of document.querySelectorAll(".category-links a")) {
    const linkCategory = new URL(link.href).searchParams.get("category") || "";

    if (linkCategory === filterForm.elements.category.value) {
      link.setAttribute("aria-current", "page");
    } else {
      link.removeAttribute("aria-current");
    }
  }

  try {
    const result = await readJson(url, controller.signal);

    if (!Array.isArray(result.data) || !result.metadata) {
      throw new Error("The product response is missing catalogue data.");
    }

    const meta = result.metadata;
    const totalPages = Math.max(1, Number(meta.total_pages));

    grid.replaceChildren(...result.data.map(renderPart));
    resultCount.textContent =
      `${meta.total_items} component${Number(meta.total_items) === 1 ? "" : "s"} found`;

    pageInfo.textContent = `Page ${meta.current_page} of ${totalPages}`;
    previousButton.disabled = page <= 1;
    nextButton.disabled = page >= totalPages || page >= 100;

    if (!result.data.length) {
      showMessage("No components found. Try another search or reset your filters.");
    }
  } catch (error) {
    if (error.name === "AbortError") return;
    resultCount.textContent = "Could not load components";
    pageInfo.textContent = `Page ${page}`;
    showMessage(error.message);
  } finally {
    if (activeRequest === controller) {
      grid.setAttribute("aria-busy", "false");
    }
  }
}

async function loadBrands() {
  let brandPage = 1;
  brandFilter.disabled = true;

  try {
    // Load all available brand pages, within the API's page bound.
    while (brandPage <= 100) {
      const url = new URL(brandsUrl);
      url.search = new URLSearchParams({
        page: String(brandPage),
        limit: "50",
      });

      const result = await readJson(url);

      if (!Array.isArray(result.data) || !result.metadata) {
        throw new Error("Unexpected brand response.");
      }

      for (const brand of result.data) {
        brands.set(String(brand.id), brand.name);
      }

      if (brandPage >= Number(result.metadata.total_pages)) break;
      brandPage++;
    }

    for (const [id, name] of brands) {
      brandFilter.add(new Option(name, id));
    }

    const initialBrand = initialParams.get("brand_id");
    if (initialBrand && brands.has(initialBrand)) {
      brandFilter.value = initialBrand;
    }

    brandFilter.disabled = false;
  } catch (error) {
    brandFilter.options[0].textContent = "Brands unavailable";
  }
}

// Product details use the real individual-item endpoint.
const detailDialog = document.createElement("dialog");
detailDialog.className = "product-dialog";
detailDialog.setAttribute("aria-label", "Product details");

const closeDetail = createElement("button", "button", "Close");
closeDetail.type = "button";
closeDetail.addEventListener("click", () => detailDialog.close());

const detailContent = document.createElement("div");
detailDialog.append(closeDetail, detailContent);
document.body.append(detailDialog);

let detailRequest;
detailDialog.addEventListener("close", () => detailRequest?.abort());

async function openPart(id) {
  detailRequest?.abort();
  const controller = new AbortController();
  detailRequest = controller;
  detailContent.textContent = "Loading product…";
  if (!detailDialog.open) detailDialog.showModal();

  try {
    const part = await readJson(
      new URL(`parts/${encodeURIComponent(id)}`, apiBase),
      controller.signal
    );
    if (controller.signal.aborted || !detailDialog.open) return;

    detailContent.replaceChildren(
      createElement("p", "product-category", part.category),
      createElement("h2", "", part.name),
      createElement("p", "product-price", money.format(Number(part.price))),
      createElement("p", "", `Brand: ${brands.get(String(part.brand_id)) || part.brand_id}`),
      createElement("p", "", `Product ID: ${part.id}`)
    );
  } catch (error) {
    if (error.name !== "AbortError") detailContent.textContent = error.message;
  }
}

document.getElementById("searchForm").addEventListener("submit", (event) => {
  event.preventDefault();
  page = 1;
  loadParts();
});

filterForm.addEventListener("submit", (event) => {
  event.preventDefault();
  page = 1;
  loadParts();
});

sortSelect.addEventListener("change", () => {
  page = 1;
  loadParts();
});

document.getElementById("resetFilters").addEventListener("click", () => {
  filterForm.reset();
  searchInput.value = "";
  sortSelect.value = "id:asc";
  page = 1;
  loadParts();
});

previousButton.addEventListener("click", () => {
  if (page > 1) {
    page--;
    loadParts();
  }
});

nextButton.addEventListener("click", () => {
  page++;
  loadParts();
});

loadBrands().then(loadParts);

// Account session — tokens stay in memory.
let currentUser = null;
let csrfToken = null;

const accountButton = document.getElementById("accountButton");
const loginDialog = document.getElementById("loginDialog");
const loginForm = document.getElementById("loginForm");
const loginMessage = document.getElementById("loginMessage");
const loginSubmit = document.getElementById("loginSubmit");

const logoutButton = createElement("button", "", "Sign out");
logoutButton.type = "button";
logoutButton.hidden = true;
document.querySelector(".account-nav").append(logoutButton);

const accountNotice = createElement("p", "catalog-message");
accountNotice.setAttribute("role", "status");
accountNotice.hidden = true;
document.querySelector(".shop-heading").after(accountNotice);

function showAccountMessage(text) {
  accountNotice.textContent = text;
  accountNotice.hidden = !text;
}

function updateAccountUI() {
  accountButton.textContent = currentUser
    ? currentUser.first_name || currentUser.email
    : "Sign in";

  logoutButton.hidden = !currentUser;
}

function clearAccount() {
  currentUser = null;
  csrfToken = null;
  updateAccountUI();
}

function showLoginMessage(text) {
  loginMessage.textContent = text;
  loginMessage.hidden = !text;
}

function openLogin() {
  showLoginMessage("");
  loginDialog.showModal();
}

accountButton.addEventListener("click", () => {
  if (currentUser) {
    showAccountMessage(`Signed in as ${currentUser.email}.`);
    return;
  }

  openLogin();
});

document.getElementById("closeLogin").addEventListener("click", () => {
  loginDialog.close();
});

loginDialog.addEventListener("close", () => {
  document.getElementById("loginPassword").value = "";
});

// Shared helper for authenticated API requests.
async function accountRequest(path, method = "GET", body) {
  const headers = { Accept: "application/json" };
  const isWrite = !["GET", "HEAD", "OPTIONS"].includes(method);

  if (body !== undefined) {
    headers["Content-Type"] = "application/json";
  }

  if (isWrite && csrfToken) {
    headers["X-CSRF-Token"] = csrfToken;
  }

  // Current middleware requires JSON Content-Type even for bodyless POST.
  if (isWrite && body === undefined) {
    headers["Content-Type"] = "application/json";
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

  if (response.status === 401) {
    clearAccount();
  }

  if (!response.ok) {
    throw new Error(result.error || `Request failed (${response.status}).`);
  }

  return result;
}

async function restoreSession() {
  try {
    const result = await accountRequest("auth/me");
    currentUser = result.user;
    csrfToken = result.csrf_token;
    updateAccountUI();
  } catch (error) {
    clearAccount();
  }
}

loginForm.addEventListener("submit", async (event) => {
  event.preventDefault();
  loginSubmit.disabled = true;
  loginSubmit.textContent = "Signing in…";
  showLoginMessage("");

  try {
    const result = await accountRequest("auth/login", "POST", {
      email: document.getElementById("loginEmail").value.trim(),
      password: document.getElementById("loginPassword").value,
    });

    currentUser = result.user;
    csrfToken = result.csrf_token;
    updateAccountUI();

    loginForm.reset();
    loginDialog.close();
    showAccountMessage("You’re signed in.");
  } catch (error) {
    showLoginMessage(error.message);
  } finally {
    loginSubmit.disabled = false;
    loginSubmit.textContent = "Sign in";
  }
});

logoutButton.addEventListener("click", async () => {
  logoutButton.disabled = true;

  try {
    await accountRequest("auth/logout", "POST");
    clearAccount();
    showAccountMessage("You’re signed out.");
  } catch (error) {
    showAccountMessage(error.message);
  } finally {
    logoutButton.disabled = false;
  }
});

restoreSession();
