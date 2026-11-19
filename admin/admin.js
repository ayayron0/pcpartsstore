const apiBase = new URL("../", window.location.href);

const loginPanel = document.getElementById("loginPanel");
const dashboard = document.getElementById("adminDashboard");
const loginForm = document.getElementById("adminLoginForm");
const loginMessage = document.getElementById("loginMessage");
const pageMessage = document.getElementById("pageMessage");
const adminIdentity = document.getElementById("adminIdentity");
const logoutButton = document.getElementById("logoutButton");

let csrfToken = null;

function showLoginMessage(message) {
  loginMessage.textContent = message;
}

function showPageMessage(message) {
  pageMessage.textContent = message;
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
    throw new Error(result.error || `Request failed (${response.status}).`);
  }

  return result;
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
    showPageMessage(error.message);
  }
});

restoreSession();
