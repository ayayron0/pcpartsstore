const apiBase = new URL("../", window.location.href);
const cart = window.DoubleBoltCart;

const list = document.getElementById("cartItems");
const message = document.getElementById("cartMessage");
const identity = document.getElementById("cartIdentity");
const total = document.getElementById("cartTotal");
const checkoutButton = document.getElementById("checkoutButton");
const refreshButton = document.getElementById("refreshCart");
const clearButton = document.getElementById("clearCart");
const signInLink = document.getElementById("signInLink");
const ordersLink = document.getElementById("ordersLink");

const money = new Intl.NumberFormat("en-CA", {
  style: "currency",
  currency: "CAD",
});

let user = null;
let csrfToken = null;
let pending = null;
let busy = false;
let ready = false;
let displayedItems = [];

function element(tag, text, className) {
  const node = document.createElement(tag);
  if (text !== undefined) node.textContent = text;
  if (className) node.className = className;
  return node;
}

function showMessage(text, isError = false) {
  message.textContent = text;
  message.dataset.error = String(isError);
  message.hidden = !text;
}

function pendingStorageKey() {
  return `doublebolt.checkout.v1.${user.id}`;
}

function updateButtons() {
  checkoutButton.disabled =
    busy || !user || !ready || displayedItems.length === 0;

  checkoutButton.textContent = busy
    ? "Please wait…"
    : pending
      ? "Retry checkout"
      : "Place order";

  refreshButton.disabled = busy;
  clearButton.disabled = busy || !!pending;

  for (const control of list.querySelectorAll("input, button")) {
    control.disabled = busy || !!pending;
  }
}

async function apiRequest(path, method = "GET", body) {
  const headers = { Accept: "application/json" };

  if (method !== "GET") {
    headers["Content-Type"] = "application/json";
    headers["X-CSRF-Token"] = csrfToken;
  }

  const response = await fetch(new URL(path, apiBase), {
    method,
    headers,
    credentials: "same-origin",
    cache: "no-store",
    signal: AbortSignal.timeout(20000),
    body: body === undefined ? undefined : JSON.stringify(body),
  });

  if (!(response.headers.get("Content-Type") || "")
      .includes("application/json")) {
    throw new Error("Unexpected server response. Please try again.");
  }

  const result = await response.json();

  if (!response.ok) {
    const details = result.details
      ? Object.values(result.details).join("\n")
      : "";

    const error = new Error(
      [result.error || "Request failed.", details]
        .filter(Boolean)
        .join("\n")
    );

    error.status = response.status;
    throw error;
  }

  return result;
}

async function loadSession() {
  try {
    const session = await apiRequest("auth/me");
    user = session.user;
    csrfToken = session.csrf_token;
    identity.textContent = `Checking out as ${user.email}`;
    signInLink.hidden = true;
  } catch (error) {
    user = null;
    csrfToken = null;
    signInLink.hidden = false;

    identity.textContent = error.status === 401
      ? "Sign in from the shop before placing an order."
      : "Could not check your session. Use Refresh cart to retry.";
  }
}

function readPending() {
  if (!user) return null;

  const saved = localStorage.getItem(pendingStorageKey());
  if (!saved) return null;

  const value = JSON.parse(saved);

  if (
    !value
    || typeof value.checkout_key !== "string"
    || !/^[A-Za-z0-9_-]{16,64}$/.test(value.checkout_key)
    || !Array.isArray(value.items)
    || value.items.length < 1
    || value.items.length > 50
  ) {
    throw new Error("Saved checkout is invalid.");
  }

  const seen = new Set();

  for (const item of value.items) {
    if (
      !item
      || !Number.isSafeInteger(item.part_id)
      || item.part_id < 1
      || !Number.isInteger(item.quantity)
      || item.quantity < 1
      || item.quantity > 99
      || seen.has(item.part_id)
    ) {
      throw new Error("Saved checkout contains invalid items.");
    }

    seen.add(item.part_id);
  }

  return value;
}

function createCard(item, part, failure) {
  const card = element("article", undefined, "account-card");

  card.append(element(
    "h2",
    part ? part.name : `Component #${item.part_id}`
  ));

  if (part) {
    card.append(element(
      "p",
      `${money.format(Number(part.price))} each`
    ));
  } else {
    card.append(element(
      "p",
      failure || "Could not load this component."
    ));
  }

  const label = element("label", "Quantity");
  const input = document.createElement("input");

  input.id = `quantity-${item.part_id}`;
  input.type = "number";
  input.min = "1";
  input.max = "99";
  input.step = "1";
  input.value = String(item.quantity);
  label.htmlFor = input.id;

  input.addEventListener("change", async () => {
    try {
      if (!input.reportValidity()) return;
      cart.setQuantity(item.part_id, Number(input.value));
      await loadCart();
    } catch (error) {
      showMessage(error.message, true);
    }
  });

  const remove = element("button", "Remove", "button");
  remove.type = "button";

  remove.addEventListener("click", async () => {
    try {
      cart.remove(item.part_id);
      await loadCart();
    } catch (error) {
      showMessage(error.message, true);
    }
  });

  const controls = element("div", undefined, "account-form");
  controls.append(label, input, remove);
  card.append(controls);

  return card;
}

async function loadCart() {
  if (busy) return;

  busy = true;
  ready = false;
  list.setAttribute("aria-busy", "true");
  list.replaceChildren();
  total.textContent = "Loading cart…";
  showMessage("");
  updateButtons();

  try {
    await loadSession();

    pending = readPending();
    displayedItems = pending ? pending.items : cart.getItems();

    if (displayedItems.length === 0) {
      total.textContent = "";
      showMessage("Your cart is empty. Browse components to get started.");
      return;
    }

    const results = await Promise.all(
      displayedItems.map(async (item) => {
        try {
          const part = await apiRequest(
            `parts/${encodeURIComponent(item.part_id)}`
          );

          const price = Number(part.price);

          if (!Number.isFinite(price) || price < 0) {
            throw new Error("This component has an invalid price.");
          }

          return { item, part };
        } catch (error) {
          return { item, failure: error.message };
        }
      })
    );

    let totalCents = 0;
    let allAvailable = true;

    for (const result of results) {
      list.append(createCard(
        result.item,
        result.part,
        result.failure
      ));

      if (result.part) {
        totalCents +=
          Math.round(Number(result.part.price) * 100)
          * result.item.quantity;
      } else {
        allAvailable = false;
      }
    }

    total.textContent = allAvailable
      ? `Estimated total: ${money.format(totalCents / 100)} CAD`
      : "Total unavailable. Remove unavailable items or refresh.";

    // A pending request can be retried even if a part has since changed.
    ready = allAvailable || !!pending;

    if (pending) {
      showMessage(
        "A checkout is awaiting confirmation. Retry it to recover the " +
        "original result before changing these items."
      );
    }
  } catch (error) {
    total.textContent = "";
    showMessage(error.message, true);
  } finally {
    busy = false;
    list.setAttribute("aria-busy", "false");
    updateButtons();
  }
}

checkoutButton.addEventListener("click", async () => {
  if (busy || !user || !ready || displayedItems.length === 0) return;

  busy = true;
  updateButtons();
  showMessage("");

  let completedOrderId = null;

  try {
    // Refresh the session and token before placing the order.
    const session = await apiRequest("auth/me");

    if (String(session.user.id) !== String(user.id)) {
      window.location.reload();
      return;
    }

    csrfToken = session.csrf_token;

    // Persist the exact request before sending it.
    pending = readPending();

    if (!pending) {
      if (
        JSON.stringify(cart.getItems())
        !== JSON.stringify(displayedItems)
      ) {
        throw new Error("Your cart changed. Refresh it before checkout.");
      }

      pending = {
        checkout_key: crypto.randomUUID(),
        items: displayedItems.map(({ part_id, quantity }) => ({
          part_id,
          quantity,
        })),
      };

      localStorage.setItem(
        pendingStorageKey(),
        JSON.stringify(pending)
      );
    }

    const result = await apiRequest("orders", "POST", pending);

    if (!result.id) {
      throw new Error("Could not confirm the order. Retry checkout.");
    }

    completedOrderId = result.id;

    // Preserve cart changes made in another tab.
    cart.clearIfMatches(pending.items);

    // Remove retry state only after handling the confirmed order.
    localStorage.removeItem(pendingStorageKey());
    pending = null;
  } catch (error) {
    // These responses reject the request without creating a new order.
    if (
      completedOrderId === null
      && [400, 409, 422].includes(error.status)
      && user
    ) {
      try {
        localStorage.removeItem(pendingStorageKey());
        pending = null;
      } catch {
        // Keep the original request available for a safe retry.
      }
    }

    if (completedOrderId !== null) {
      ordersLink.hidden = false;
      showMessage(
        `Order #${completedOrderId} was placed, but cart cleanup failed. ` +
        "Use Retry checkout to recover the same order.",
        true
      );
    } else {
      showMessage(
        error.message +
        (pending
          ? " Your checkout request is saved. Retry uses the same key."
          : ""),
        true
      );
    }

    if (error.status === 401) {
      user = null;
      csrfToken = null;
      signInLink.hidden = false;
      identity.textContent = "Your session expired. Sign in from the shop.";
    }
  } finally {
    busy = false;

    if (completedOrderId !== null && pending === null) {
      await loadCart();
      ordersLink.hidden = false;
      showMessage(
        `Order #${completedOrderId} placed successfully. ` +
        "You can view it in My orders."
      );
    }

    updateButtons();
  }
});

refreshButton.addEventListener("click", loadCart);

clearButton.addEventListener("click", async () => {
  if (busy || pending) return;
  if (!window.confirm("Remove all components from your cart?")) return;

  try {
    cart.clear();
    await loadCart();
  } catch (error) {
    showMessage(error.message, true);
  }
});

window.addEventListener("storage", () => {
  if (!busy) loadCart();
});

loadCart();