const API_URL = new URL("../users", window.location.href).href;

let currentPage = 1;
const limit = 5;

let currentNameFilter = "";
let currentSort = "id";
let currentDirection = "asc";
let editingUserId = null;

// Display error or success messages safely
function showFeedback(message, isError = true, detailsObj = null) {
    const box = document.getElementById("feedbackBox");

    box.hidden = false;
    box.className = isError ? "box error-box" : "box success-box";
    box.replaceChildren();

    const heading = document.createElement("strong");
    heading.textContent = message;
    box.appendChild(heading);

    if (detailsObj) {
        const list = document.createElement("ul");

        for (const [field, issue] of Object.entries(detailsObj)) {
            const item = document.createElement("li");
            item.textContent = `${field}: ${issue}`;
            list.appendChild(item);
        }

        box.appendChild(list);
    }
}

// Fetch and display a page of users
async function loadUsers() {
    const tbody = document.getElementById("userTableBody");
    const listStatus = document.getElementById("listStatus");

    document.getElementById("prevBtn").disabled = true;
    document.getElementById("nextBtn").disabled = true;
    document.getElementById("pageInfo").textContent = "";
    tbody.replaceChildren();
    listStatus.textContent = "Loading users...";

    try {
        const params = new URLSearchParams({
            page: currentPage,
            limit: limit,
            sort: currentSort,
            direction: currentDirection
        });

        if (currentNameFilter !== "") {
            params.set("name", currentNameFilter);
        }

        const response = await fetch(`${API_URL}?${params}`, {
            method: "GET",
            headers: {
                Accept: "application/json"
            }
        });

        const result = await response.json();

        if (!response.ok) {
            listStatus.textContent = "Unable to load users.";
            showFeedback(result.error, true, result.details);
            return;
        }

        const meta = result.metadata;

        // Return to the last available page after deleting its final row
        if (currentPage > 1 && currentPage > meta.total_pages) {
            currentPage = Math.max(1, meta.total_pages);
            await loadUsers();
            return;
        }

        listStatus.textContent = result.data.length === 0
            ? "No users found."
            : `GET users — ${response.status}`;

        for (const user of result.data) {
            const row = document.createElement("tr");

            // Use textContent so user data is displayed as plain text
            for (const value of [
                user.id,
                user.first_name,
                user.last_name,
                user.email
            ]) {
                const cell = document.createElement("td");
                cell.textContent = value;
                row.appendChild(cell);
            }

            const actions = document.createElement("td");

            const editButton = document.createElement("button");
            editButton.textContent = "Edit";
            editButton.onclick = () => editUser(user);

            const deleteButton = document.createElement("button");
            deleteButton.textContent = "Delete";
            deleteButton.onclick = () => deleteUser(user.id);

            actions.appendChild(editButton);
            actions.appendChild(deleteButton);
            row.appendChild(actions);
            tbody.appendChild(row);
        }

        document.getElementById("pageInfo").textContent =
            `Page ${meta.current_page} of ${meta.total_pages || 1} ` +
            `(Total: ${meta.total_items})`;

        document.getElementById("prevBtn").disabled =
            meta.current_page <= 1;

        document.getElementById("nextBtn").disabled =
            meta.current_page >= meta.total_pages;

    } catch (error) {
        listStatus.textContent = "Unable to load users.";
        showFeedback("Could not load users. Check the API connection.");
    }
}

// Move between pages while preserving the applied filters and sorting
function changePage(direction) {
    currentPage += direction;
    loadUsers();
}

// Apply the selected filter and sorting, starting at page 1
document.getElementById("filterForm").addEventListener("submit", function(event) {
    event.preventDefault();

    currentNameFilter = document.getElementById("filterName").value.trim();
    currentSort = document.getElementById("sortField").value;
    currentDirection = document.getElementById("sortDirection").value;
    currentPage = 1;

    document.getElementById("feedbackBox").hidden = true;
    loadUsers();
});

// Clear the filter and restore default sorting
function resetFilter() {
    document.getElementById("filterForm").reset();

    currentNameFilter = "";
    currentSort = "id";
    currentDirection = "asc";
    currentPage = 1;

    document.getElementById("feedbackBox").hidden = true;
    loadUsers();
}

// Fill the form with the selected user's values
function editUser(user) {
    editingUserId = user.id;

    document.getElementById("firstName").value = user.first_name;
    document.getElementById("lastName").value = user.last_name;
    document.getElementById("userEmail").value = user.email;

    document.getElementById("formHeading").textContent = "Edit User " + user.id;
    document.getElementById("saveBtn").textContent = "Save Changes";
    document.getElementById("cancelBtn").hidden = false;
}

// Return the form to create mode
function resetUserForm() {
    editingUserId = null;

    document.getElementById("userForm").reset();
    document.getElementById("formHeading").textContent = "Create User";
    document.getElementById("saveBtn").textContent = "Create User";
    document.getElementById("cancelBtn").hidden = true;
}

// Create a user or update the selected user
document.getElementById("userForm").addEventListener("submit", async function(event) {
    event.preventDefault();

    const firstName = document.getElementById("firstName").value.trim();
    const lastName = document.getElementById("lastName").value.trim();
    const email = document.getElementById("userEmail").value.trim();

    const clientErrors = {};

    if (firstName === "") {
        clientErrors.first_name = "Please enter a first name.";
    }

    if (lastName === "") {
        clientErrors.last_name = "Please enter a last name.";
    }

    if (email === "") {
        clientErrors.email = "Please enter an email.";
    }

    if (Object.keys(clientErrors).length > 0) {
        showFeedback("Client-Side Validation Failed", true, clientErrors);
        return;
    }

    const isEditing = editingUserId !== null;
    const url = isEditing ? `${API_URL}/${editingUserId}` : API_URL;

    const saveButton = document.getElementById("saveBtn");
    saveButton.disabled = true;

    try {
        const response = await fetch(url, {
            method: isEditing ? "PATCH" : "POST",
            headers: {
                Accept: "application/json",
                "Content-Type": "application/json"
            },
            body: JSON.stringify({
                first_name: firstName,
                last_name: lastName,
                email: email
            })
        });

        const result = await response.json();

        if (!response.ok) {
            showFeedback(
                `Server Error (${response.status}): ${result.error}`,
                true,
                result.details
            );
            return;
        }

        const message = isEditing
            ? result.message
            : `${result.message}. ID: ${result.id}`;

        showFeedback(message, false);
        resetUserForm();
        await loadUsers();

    } catch (error) {
        showFeedback("Network failure while saving the user.");
    } finally {
        saveButton.disabled = false;
    }
});

// Delete a user; the API rejects users with linked orders
async function deleteUser(id) {
    if (!confirm(`Delete user ${id}?`)) {
        return;
    }

    try {
        const response = await fetch(`${API_URL}/${id}`, {
            method: "DELETE",
            headers: {
                Accept: "application/json"
            }
        });

        const result = await response.json();

        if (!response.ok) {
            showFeedback(
                `Server Error (${response.status}): ${result.error}`,
                true,
                result.details
            );
            return;
        }

        if (editingUserId === id) {
            resetUserForm();
        }

        showFeedback(result.message, false);
        await loadUsers();

    } catch (error) {
        showFeedback("Network failure while deleting the user.");
    }
}

// Load the first page when the script runs
loadUsers();