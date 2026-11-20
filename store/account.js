const apiBase = new URL("../", window.location.href);

const form = document.getElementById("accountForm");
const message = document.getElementById("accountMessage");
const identity = document.getElementById("accountIdentity");
const saveButton = document.getElementById("saveButton");
const logoutButton = document.getElementById("logoutButton");
const retryButton = document.getElementById("retryButton");
const signedOutLinks = document.getElementById("signedOutLinks");
const adminLink = document.getElementById("adminLink");

let currentUser = null;
let csrfToken = null;
let busy = false;

function showMessage(text, isError = false) {
  message.textContent = text;
  message.dataset.error = String(isError);
  message.hidden = !text;
}

function setBusy(value) {
  busy = value;
  saveButton.disabled = value;
  logoutButton.disabled = value;
  retryButton.disabled = value;

  for (const input of form.querySelectorAll("input")) {
    input.disabled = value;
  }
}

function showSignedOut() {
  currentUser = null;
  csrfToken = null;
  form.hidden = true;
  logoutButton.hidden = true;
  adminLink.hidden = true;
  signedOutLinks.hidden = false;
  identity.textContent = "Sign in to view your account.";
}

function showAccount(result) {
  currentUser = result.user;
  csrfToken = result.csrf_token;

  document.getElementById("firstName").value = currentUser.first_name;
  document.getElementById("lastName").value = currentUser.last_name;
  document.getElementById("email").value = currentUser.email;

  identity.textContent = `Signed in as ${currentUser.email}`;
  form.hidden = false;
  logoutButton.hidden = false;
  adminLink.hidden = currentUser.role !== "admin";
  signedOutLinks.hidden = true;
  retryButton.hidden = true;
}

async function apiRequest(path, method = "GET", body) {
  const headers = { Accept: "application/json" };
  const isWrite = !["GET", "HEAD", "OPTIONS"].includes(method);

  if (isWrite) {
    headers["Content-Type"] = "application/json";

    if (csrfToken) {
      headers["X-CSRF-Token"] = csrfToken;
    }
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
    throw new Error("Unexpected server response. Please try again.");
  }

  const result = await response.json();

  if (!response.ok) {
    if (response.status === 401) {
      showSignedOut();
    }

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

async function loadAccount() {
  if (busy) return;

  setBusy(true);
  showMessage("");
  retryButton.hidden = true;

  try {
    const result = await apiRequest("auth/me");
    showAccount(result);
  } catch (error) {
    if (error.status === 401) {
      showMessage("Please sign in from the shop to continue.");
    } else {
      identity.textContent = "Could not load your account.";
      showMessage(error.message, true);
      retryButton.hidden = false;
    }
  } finally {
    setBusy(false);
  }
}

form.addEventListener("submit", async (event) => {
  event.preventDefault();

  if (busy || !currentUser || !form.reportValidity()) return;

  const updates = {
    first_name: document.getElementById("firstName").value.trim(),
    last_name: document.getElementById("lastName").value.trim(),
    email: document.getElementById("email").value.trim(),
  };

  setBusy(true);
  saveButton.textContent = "Saving…";
  showMessage("");

  try {
    await apiRequest(
      `users/${encodeURIComponent(currentUser.id)}`,
      "PATCH",
      updates
    );

    // Confirm the saved values by reading them back from the server.
    showAccount(await apiRequest("auth/me"));
    showMessage("Your account details have been saved.");
  } catch (error) {
    showMessage(error.message, true);
  } finally {
    setBusy(false);
    saveButton.textContent = "Save changes";
  }
});

logoutButton.addEventListener("click", async () => {
  if (busy) return;

  setBusy(true);
  showMessage("");

  try {
    await apiRequest("auth/logout", "POST");
    showSignedOut();
    showMessage("You’re signed out.");
  } catch (error) {
    showMessage(error.message, true);
  } finally {
    setBusy(false);
  }
});

retryButton.addEventListener("click", loadAccount);

loadAccount();