const apiBase = new URL("../", window.location.href);

const content = document.getElementById("ordersContent");
const identity = document.getElementById("ordersIdentity");
const message = document.getElementById("ordersMessage");
const signedOutLinks = document.getElementById("signedOutLinks");
const retryButton = document.getElementById("retryButton");
const list = document.getElementById("ordersList");
const count = document.getElementById("orderCount");
const statusFilter = document.getElementById("statusFilter");
const previousButton = document.getElementById("previousPage");
const nextButton = document.getElementById("nextPage");
const pageInfo = document.getElementById("pageInfo");

const details = document.getElementById("orderDetails");
const detailTitle = document.getElementById("detailTitle");
const detailMessage = document.getElementById("detailMessage");

let currentUser = null;
let page = 1;
let activeStatus = "";
let listRequest = null;
let detailRequest = null;

function showMessage(text, isError = false) {
  message.textContent = text;
  message.dataset.error = String(isError);
  message.hidden = !text;
}

function showSignedOut() {
  currentUser = null;
  listRequest?.abort();
  detailRequest?.abort();
  content.hidden = true;
  details.close();
  retryButton.hidden = true;
  signedOutLinks.hidden = false;
  identity.textContent = "Sign in to view your orders.";
}

async function readApi(path, signal) {
  const response = await fetch(new URL(path, apiBase), {
    headers: { Accept: "application/json" },
    credentials: "same-origin",
    cache: "no-store",
    signal,
  });

  const contentType = response.headers.get("Content-Type") || "";

  if (!contentType.includes("application/json")) {
    throw new Error("Unexpected server response. Please try again.");
  }

  const result = await response.json();

  if (!response.ok) {
    if (response.status === 401) {
      showSignedOut();
    }

    const error = new Error(result.error || "Request failed.");
    error.status = response.status;
    throw error;
  }

  return result;
}

function createOrderCard(order) {
  const card = document.createElement("article");
  card.className = "account-card";

  const title = document.createElement("h2");
  title.textContent = `Order #${order.id}`;

  const status = document.createElement("p");
  status.textContent = `Status: ${order.status}`;

  const button = document.createElement("button");
  button.className = "button";
  button.type = "button";
  button.textContent = "View order";
  button.addEventListener("click", () => openOrder(order.id));

  card.append(title, status, button);
  return card;
}

async function loadOrders() {
  if (!currentUser) return;

  listRequest?.abort();
  const controller = new AbortController();
  listRequest = controller;

  list.setAttribute("aria-busy", "true");
  list.replaceChildren();
  previousButton.disabled = true;
  nextButton.disabled = true;
  count.textContent = "Loading orders…";
  retryButton.hidden = true;
  showMessage("");

  const params = new URLSearchParams({
    page: String(page),
    limit: "5",
    sort: "id",
    direction: "desc",
    // Customers are also restricted by the server.
    // This filter keeps the page personal for admin accounts too.
    user_id: String(currentUser.id),
  });

  if (activeStatus) {
    params.set("status", activeStatus);
  }

  try {
    const result = await readApi(
      `orders?${params}`,
      controller.signal
    );

    if (!Array.isArray(result.data) || !result.metadata) {
      throw new Error("The server returned an unexpected order list.");
    }

    const meta = result.metadata;
    const totalPages = Math.max(1, Number(meta.total_pages));

    list.replaceChildren(...result.data.map(createOrderCard));
    count.textContent =
      `${meta.total_items} matching order${Number(meta.total_items) === 1 ? "" : "s"}`;

    pageInfo.textContent = `Page ${meta.current_page} of ${totalPages}`;
    previousButton.disabled = page <= 1;
    nextButton.disabled = page >= totalPages || page >= 100;

    if (result.data.length === 0) {
      showMessage(
        activeStatus
          ? "No orders match this status."
          : "You don’t have any orders yet."
      );
    }
  } catch (error) {
    if (error.name === "AbortError") return;

    count.textContent = "";
    showMessage(error.message, true);
    retryButton.hidden = !currentUser;
  } finally {
    if (listRequest === controller) {
      list.setAttribute("aria-busy", "false");
    }
  }
}

async function openOrder(id) {
  detailRequest?.abort();

  const controller = new AbortController();
  detailRequest = controller;

  const itemContainer = document.getElementById("detailItems");
  const totalElement = document.getElementById("detailTotal");

  const orderMoney = new Intl.NumberFormat("en-CA", {
    style: "currency",
    currency: "CAD",
  });

  itemContainer.replaceChildren();
  totalElement.textContent = "";

  if (!details.open) {
  details.showModal();
}
  details.setAttribute("aria-busy", "true");
  detailTitle.textContent = `Order #${id}`;
  detailMessage.textContent = "Loading order…";

  try {
    const order = await readApi(
      `orders/${encodeURIComponent(id)}`,
      controller.signal
    );

    if (controller.signal.aborted) return;

    if (!Array.isArray(order.items)) {
      throw new Error("Order items are missing from the server response.");
    }

    detailTitle.textContent = `Order #${order.id}`;
    detailMessage.textContent = `Status: ${order.status}`;

    if (order.items.length === 0) {
      itemContainer.textContent = "No items were recorded for this order.";
    }

    for (const item of order.items) {
      const card = document.createElement("article");
      card.className = "account-card";

      const name = document.createElement("h3");
      name.textContent = item.name;

      const quantity = document.createElement("p");
      quantity.textContent = `Quantity: ${item.quantity}`;

      const price = document.createElement("p");
      price.textContent = item.unit_price === null
        ? "Purchase price was not recorded."
        : `Purchase price: ${
            orderMoney.format(Number(item.unit_price))
          } each`;

      const lineTotal = document.createElement("p");
      lineTotal.textContent = item.line_total === null
        ? "Line total unavailable."
        : `Line total: ${
            orderMoney.format(Number(item.line_total))
          }`;

      card.append(name, quantity, price, lineTotal);
      itemContainer.append(card);
    }

    totalElement.textContent = order.total === null
      ? "Total unavailable: this order has no complete purchase-price record."
      : `Order total: ${
          orderMoney.format(Number(order.total))
        } CAD`;
  } catch (error) {
    if (error.name !== "AbortError") {
      detailMessage.textContent = error.message;
    }
  } finally {
    if (detailRequest === controller) {
      details.setAttribute("aria-busy", "false");
    }
  }
}

async function start() {
  retryButton.hidden = true;
  showMessage("");

  try {
    const result = await readApi("auth/me");
    currentUser = result.user;

    identity.textContent = `Orders for ${currentUser.email}`;
    content.hidden = false;
    signedOutLinks.hidden = true;

    await loadOrders();
  } catch (error) {
    if (error.status !== 401) {
      identity.textContent = "Could not load your session.";
      showMessage(error.message, true);
      retryButton.hidden = false;
    }
  }
}

document.getElementById("orderFilter")
  .addEventListener("submit", (event) => {
    event.preventDefault();
    activeStatus = statusFilter.value;
    page = 1;
    detailRequest?.abort();
    details.close();
    loadOrders();
  });

previousButton.addEventListener("click", () => {
  if (page > 1) {
    page--;
    loadOrders();
  }
});

nextButton.addEventListener("click", () => {
  page++;
  loadOrders();
});

document.getElementById("closeDetails")
  .addEventListener("click", () => {
    detailRequest?.abort();
    details.close();
  });

retryButton.addEventListener("click", () => {
  if (currentUser) {
    loadOrders();
  } else {
    start();
  }
});

start();

// Stop an unfinished request when Close or Escape dismisses the popup.
details.addEventListener("close", () => {
  detailRequest?.abort();
});