window.DoubleBoltCart = (() => {
  const storageKey = "doublebolt.cart.v1";

  function validate(items) {
    if (!Array.isArray(items) || items.length > 50) {
      throw new Error("Saved cart is invalid. Reset it from the cart page.");
    }

    const seen = new Set();

    for (const item of items) {
      if (
        !item
        || !Number.isSafeInteger(item.part_id)
        || item.part_id < 1
        || !Number.isInteger(item.quantity)
        || item.quantity < 1
        || item.quantity > 99
        || seen.has(item.part_id)
      ) {
        throw new Error("Saved cart is invalid. Reset it from the cart page.");
      }

      seen.add(item.part_id);
    }

    return items
      .map(({ part_id, quantity }) => ({ part_id, quantity }))
      .sort((a, b) => a.part_id - b.part_id);
  }

  function getItems() {
    const saved = localStorage.getItem(storageKey);
    return validate(saved === null ? [] : JSON.parse(saved));
  }

  function save(items) {
    const validated = validate(items);
    localStorage.setItem(storageKey, JSON.stringify(validated));
    window.dispatchEvent(new Event("doublebolt:cartchange"));
  }

  function add(partId) {
    partId = Number(partId);

    if (!Number.isSafeInteger(partId) || partId < 1) {
      throw new Error("Invalid product ID.");
    }

    const items = getItems();
    const existing = items.find((item) => item.part_id === partId);

    if (existing) {
      if (existing.quantity >= 99) {
        throw new Error("Maximum quantity is 99 per component.");
      }

      existing.quantity++;
    } else {
      if (items.length >= 50) {
        throw new Error("Your cart can contain up to 50 different components.");
      }

      items.push({ part_id: partId, quantity: 1 });
    }

    save(items);
  }

  function setQuantity(partId, quantity) {
    if (!Number.isInteger(quantity) || quantity < 1 || quantity > 99) {
      throw new Error("Choose a quantity from 1–99.");
    }

    const items = getItems();
    const item = items.find((entry) => entry.part_id === Number(partId));

    if (!item) throw new Error("This component is no longer in your cart.");

    item.quantity = quantity;
    save(items);
  }

  function remove(partId) {
    save(getItems().filter((item) => item.part_id !== Number(partId)));
  }

  function clear() {
    localStorage.removeItem(storageKey);
    window.dispatchEvent(new Event("doublebolt:cartchange"));
  }

  function clearIfMatches(snapshot) {
    if (JSON.stringify(getItems()) !== JSON.stringify(validate(snapshot))) {
      return false;
    }

    clear();
    return true;
  }

  return Object.freeze({
    getItems,
    add,
    setQuantity,
    remove,
    clear,
    clearIfMatches,
  });
})();