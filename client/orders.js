const API_URL = new URL("../orders", window.location.href).href;

let currentPage = 1;
const limit = 5;

let currentUserFilter = "";
let currentStatusFilter = "";
let currentSort = "id";
let currentDirection = "asc";
let editingOrderId = null;

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

// Fetch and display a page of orders
async function loadOrders() {
    const tbody = document.getElementById("orderTableBody");
    const listStatus = document.getElementById("listStatus");

    document.getElementById("prevBtn").disabled = true;
    document.getElementById("nextBtn").disabled = true;
    document.getElementById("pageInfo").textContent = "";
    tbody.replaceChildren();
    listStatus.textContent = "Loading orders...";

    try {
        const params = new URLSearchParams({
            page: currentPage,
            limit: limit,
            sort: currentSort,
            direction: currentDirection
        });

        if (currentUserFilter !== "") {
            params.set("user_id", currentUserFilter);
        }

        if (currentStatusFilter !== "") {
            params.set("status", currentStatusFilter);
        }

        const response = await fetch(`${API_URL}?${params}`, {
            method: "GET",
            headers: {
                Accept: "application/json"
            }
        });

        const result = await response.json();

        if (!response.ok) {
            listStatus.textContent = "Unable to load orders.";
            showFeedback(result.error, true, result.details);
            return;
        }

        const meta = result.metadata;

        // Return to the last available page after deleting its final row
        if (currentPage > 1 && currentPage > meta.total_pages) {
            currentPage = Math.max(1, meta.total_pages);
            await loadOrders();
            return;
        }

        listStatus.textContent = result.data.length === 0
            ? "No orders found."
            : `GET orders — ${response.status}`;

        for (const order of result.data) {
            const row = document.createElement("tr");

            // Display API values as plain text
            for (const value of [
                order.id,
                order.user_id,
                order.status
            ]) {
                const cell = document.createElement("td");
                cell.textContent = value;
                row.appendChild(cell);
            }

            const actions = document.createElement("td");

            const editButton = document.createElement("button");
            editButton.textContent = "Edit Status";
            editButton.onclick = () => editOrder(order);

            const deleteButton = document.createElement("button");
            deleteButton.textContent = "Delete";
            deleteButton.onclick = () => deleteOrder(order.id);

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
        listStatus.textContent = "Unable to load orders.";
        showFeedback("Could not load orders. Check the API connection.");
    }
}

// Move between pages while preserving filters and sorting
function changePage(direction) {
    currentPage += direction;
    loadOrders();
}

// Apply filters and sorting, starting at page 1
document.getElementById("filterForm").addEventListener("submit", function(event) {
    event.preventDefault();

    currentUserFilter =
        document.getElementById("filterUserId").value.trim();

    currentStatusFilter =
        document.getElementById("filterStatus").value;

    currentSort = document.getElementById("sortField").value;
    currentDirection = document.getElementById("sortDirection").value;
    currentPage = 1;

    document.getElementById("feedbackBox").hidden = true;
    loadOrders();
});

// Clear filters and restore default sorting
function resetFilter() {
    document.getElementById("filterForm").reset();

    currentUserFilter = "";
    currentStatusFilter = "";
    currentSort = "id";
    currentDirection = "asc";
    currentPage = 1;

    document.getElementById("feedbackBox").hidden = true;
    loadOrders();
}

// Fill the form to edit an order's status
function editOrder(order) {
    editingOrderId = order.id;

    const userInput = document.getElementById("orderUserId");
    userInput.value = order.user_id;
    userInput.disabled = true;

    document.getElementById("orderStatus").value = order.status;
    document.getElementById("formHeading").textContent =
        "Edit Order " + order.id;

    document.getElementById("saveBtn").textContent = "Save Status";
    document.getElementById("cancelBtn").hidden = false;
}

// Return the form to create mode
function resetOrderForm() {
    editingOrderId = null;

    document.getElementById("orderForm").reset();
    document.getElementById("orderUserId").disabled = false;
    document.getElementById("formHeading").textContent = "Create Order";
    document.getElementById("saveBtn").textContent = "Create Order";
    document.getElementById("cancelBtn").hidden = true;
}

// Create an order or update its status
document.getElementById("orderForm").addEventListener("submit", async function(event) {
    event.preventDefault();

    const isEditing = editingOrderId !== null;
    const userId = Number(document.getElementById("orderUserId").value);
    const status = document.getElementById("orderStatus").value;

    const clientErrors = {};

    if (!isEditing && (!Number.isInteger(userId) || userId < 1)) {
        clientErrors.user_id = "Please enter a positive integer user ID.";
    }

    if (Object.keys(clientErrors).length > 0) {
        showFeedback("Client-Side Validation Failed", true, clientErrors);
        return;
    }

    const url = isEditing ? `${API_URL}/${editingOrderId}` : API_URL;

    // PATCH accepts only status; POST also requires user_id
    const payload = isEditing
        ? { status: status }
        : { user_id: userId, status: status };

    const saveButton = document.getElementById("saveBtn");
    saveButton.disabled = true;

    try {
        const response = await fetch(url, {
            method: isEditing ? "PATCH" : "POST",
            headers: {
                Accept: "application/json",
                "Content-Type": "application/json"
            },
            body: JSON.stringify(payload)
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
        resetOrderForm();
        await loadOrders();

    } catch (error) {
        showFeedback("Network failure while saving the order.");
    } finally {
        saveButton.disabled = false;
    }
});

// Delete an order; the API rejects orders with linked items
async function deleteOrder(id) {
    if (!confirm(`Delete order ${id}?`)) {
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

        if (editingOrderId === id) {
            resetOrderForm();
        }

        showFeedback(result.message, false);
        await loadOrders();

    } catch (error) {
        showFeedback("Network failure while deleting the order.");
    }
}

// Load the first page when the script runs
loadOrders();