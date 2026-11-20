const registrationUrl = new URL("../users", window.location.href);

const form = document.getElementById("registerForm");
const submitButton = document.getElementById("registerSubmit");
const message = document.getElementById("registerMessage");
const password = document.getElementById("password");
const confirmation = document.getElementById("confirmPassword");

function showMessage(text, isError = false) {
  message.textContent = text;
  message.dataset.error = String(isError);
  message.hidden = !text;
}

function checkPasswords() {
  confirmation.setCustomValidity(
    confirmation.value !== password.value
      ? "Passwords must match."
      : ""
  );
}

password.addEventListener("input", checkPasswords);
confirmation.addEventListener("input", checkPasswords);

form.addEventListener("submit", async (event) => {
  event.preventDefault();
  checkPasswords();

  if (!form.reportValidity()) return;

  submitButton.disabled = true;
  submitButton.textContent = "Creating account…";
  showMessage("");

  try {
    const response = await fetch(registrationUrl, {
      method: "POST",
      credentials: "same-origin",
      headers: {
        Accept: "application/json",
        "Content-Type": "application/json",
      },
      body: JSON.stringify({
        first_name: document.getElementById("firstName").value.trim(),
        last_name: document.getElementById("lastName").value.trim(),
        email: document.getElementById("email").value.trim(),
        password: password.value,
      }),
    });

    const contentType = response.headers.get("Content-Type") || "";

    if (!contentType.includes("application/json")) {
      throw new Error("Unexpected server response. Please try again.");
    }

    const result = await response.json();

    if (!response.ok) {
      const details = result.details
        ? Object.values(result.details).join("\n")
        : "";

      throw new Error(
        [result.error || "Registration failed.", details]
          .filter(Boolean)
          .join("\n")
      );
    }

    form.reset();
    form.hidden = true;

    showMessage(
      "Account created. Return to the shop and sign in with your email and password."
    );
  } catch (error) {
    showMessage(error.message || "Could not connect to the server.", true);
  } finally {
    submitButton.disabled = false;
    submitButton.textContent = "Create account";
  }
});